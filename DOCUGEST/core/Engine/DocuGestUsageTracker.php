<?php

declare(strict_types=1);

namespace DocuGest\Engine;

/**
 * Monitor y Auditor de Consumo IA para XL DocuGest 2.0 (Control Total).
 *
 * Persiste métricas ligeras por consulta / ingesta en logs JSONL mensuales
 * bajo storage/logs/ia_usage_YYYY-MM.jsonl (protegido por .htaccess) y calcula
 * resúmenes para el tablero exclusivo de administradores Control Total:
 * - Tokens de entrada, salida y totales (por usuario, modelo, acción y clave)
 * - Consultas resueltas desde Caché L1/L2 (0 tokens consumidos y ahorro estimado)
 * - Costo estimado de referencia USD según tarifa lista de Gemini
 */
final class DocuGestUsageTracker
{
    private const CACHE_SAVED_TOKENS_ESTIMATE = 2850;

    /**
     * Registra un evento de uso IA o caché sin interrumpir el flujo principal si falla I/O.
     *
     * @param array<string, mixed> $event
     */
    public static function record(array $event): void
    {
        try {
            $logsDir = dirname(__DIR__, 2) . '/storage/logs';
            if (!is_dir($logsDir)) {
                @mkdir($logsDir, 0775, true);
            }

            $ts = time();
            $date = date('Y-m-d', $ts);
            $month = date('Y-m', $ts);
            $file = $logsDir . "/ia_usage_{$month}.jsonl";

            $cached = !empty($event['cached']);
            $model = trim((string)($event['model'] ?? ($cached ? 'cache-l2' : 'desconocido')));
            $action = trim((string)($event['action'] ?? ($cached ? 'chat_cache' : 'chat')));

            $promptTokens = max(0, (int)($event['prompt_tokens'] ?? 0));
            $outputTokens = max(0, (int)($event['output_tokens'] ?? 0));
            $totalTokens = max($promptTokens + $outputTokens, (int)($event['total_tokens'] ?? 0));
            $savedTokens = $cached ? max(0, (int)($event['saved_tokens_est'] ?? self::CACHE_SAVED_TOKENS_ESTIMATE)) : 0;

            $costUsd = ($cached || $totalTokens === 0)
                ? 0.0
                : self::estimateCostUsd($model, $promptTokens, $outputTokens);

            $rawPreview = trim((string)($event['preview'] ?? ''));
            $rawPreview = preg_replace('/\s+/u', ' ', $rawPreview) ?? $rawPreview;
            $preview = mb_substr($rawPreview, 0, 110, 'UTF-8');

            $row = [
                'ts'               => date('c', $ts),
                'date'             => $date,
                'username'         => self::normalizeUsername((string)($event['username'] ?? 'Sistema')),
                'nombre_completo'  => trim((string)($event['nombre_completo'] ?? ($event['username'] ?? 'Sistema'))),
                'sector_nombre'    => trim((string)($event['sector_nombre'] ?? '')),
                'rol'              => trim((string)($event['rol'] ?? '')),
                'action'           => $action,
                'model'            => $model,
                'key_role'         => trim((string)($event['key_role'] ?? ($cached ? 'cache' : 'none'))),
                'cached'           => $cached,
                'prompt_tokens'    => $promptTokens,
                'output_tokens'    => $outputTokens,
                'total_tokens'     => $totalTokens,
                'saved_tokens_est' => $savedTokens,
                'cost_usd'         => round($costUsd, 6),
                'elapsed_ms'       => round((float)($event['elapsed_ms'] ?? 0), 1),
                'tool_calls_count' => max(0, (int)($event['tool_calls_count'] ?? 0)),
                'preview'          => $preview,
            ];

            $line = json_encode($row, JSON_UNESCAPED_UNICODE);
            if (is_string($line) && $line !== '') {
                @file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);
            }
        } catch (\Throwable $e) {
            error_log('DocuGestUsageTracker::record error: ' . $e->getMessage());
        }
    }

    /**
     * Calcula estadísticas agregadas y detalle reciente para el panel de Control Total.
     *
     * @return array<string, mixed>
     */
    public static function getStats(
        string $period = 'month',
        ?string $from = null,
        ?string $to = null,
        int $limit = 80
    ): array {
        [$startDate, $endDate] = self::resolveDateRange($period, $from, $to);
        $events = self::readEventsInRange($startDate, $endDate);

        $summary = [
            'period'             => $period,
            'from'               => $startDate,
            'to'                 => $endDate,
            'total_requests'     => 0,
            'api_calls'          => 0,
            'cache_hits'         => 0,
            'fallback_calls'     => 0,
            'cache_hit_rate'     => 0.0,
            'prompt_tokens'      => 0,
            'output_tokens'      => 0,
            'total_tokens'       => 0,
            'saved_tokens_est'   => 0,
            'estimated_cost_usd' => 0.0,
            'avg_elapsed_ms'     => 0.0,
            'unique_users'       => 0,
        ];

        $elapsedSum = 0.0;
        $daily = [];
        $byUser = [];
        $byModel = [];
        $byAction = [];
        $byKeyRole = [];

        foreach ($events as $ev) {
            $d = (string)($ev['date'] ?? '');
            $u = (string)($ev['username'] ?? 'SISTEMA');
            $m = (string)($ev['model'] ?? 'desconocido');
            $a = (string)($ev['action'] ?? 'chat');
            $kr = (string)($ev['key_role'] ?? 'none');
            $cached = !empty($ev['cached']);
            $pt = (int)($ev['prompt_tokens'] ?? 0);
            $ot = (int)($ev['output_tokens'] ?? 0);
            $tt = (int)($ev['total_tokens'] ?? 0);
            $st = (int)($ev['saved_tokens_est'] ?? 0);
            $cost = (float)($ev['cost_usd'] ?? 0.0);
            $ms = (float)($ev['elapsed_ms'] ?? 0.0);

            $summary['total_requests']++;
            $elapsedSum += $ms;
            if ($cached) {
                $summary['cache_hits']++;
            } elseif ($m === 'local-deterministic-fallback' || $a === 'fallback_local') {
                $summary['fallback_calls']++;
            } else {
                $summary['api_calls']++;
            }

            $summary['prompt_tokens'] += $pt;
            $summary['output_tokens'] += $ot;
            $summary['total_tokens'] += $tt;
            $summary['saved_tokens_est'] += $st;
            $summary['estimated_cost_usd'] += $cost;

            if ($d !== '') {
                if (!isset($daily[$d])) {
                    $daily[$d] = [
                        'date'         => $d,
                        'requests'     => 0,
                        'api_calls'    => 0,
                        'cache_hits'   => 0,
                        'total_tokens' => 0,
                        'cost_usd'     => 0.0,
                    ];
                }
                $daily[$d]['requests']++;
                if ($cached) {
                    $daily[$d]['cache_hits']++;
                } elseif ($m !== 'local-deterministic-fallback') {
                    $daily[$d]['api_calls']++;
                }
                $daily[$d]['total_tokens'] += $tt;
                $daily[$d]['cost_usd'] += $cost;
            }

            if (!isset($byUser[$u])) {
                $byUser[$u] = [
                    'username'        => $u,
                    'nombre_completo' => (string)($ev['nombre_completo'] ?? $u),
                    'sector_nombre'   => (string)($ev['sector_nombre'] ?? ''),
                    'rol'             => (string)($ev['rol'] ?? ''),
                    'requests'        => 0,
                    'api_calls'       => 0,
                    'cache_hits'      => 0,
                    'prompt_tokens'   => 0,
                    'output_tokens'   => 0,
                    'total_tokens'    => 0,
                    'cost_usd'        => 0.0,
                    'last_seen'       => (string)($ev['ts'] ?? ''),
                ];
            }
            $byUser[$u]['requests']++;
            if ($cached) {
                $byUser[$u]['cache_hits']++;
            } elseif ($m !== 'local-deterministic-fallback') {
                $byUser[$u]['api_calls']++;
            }
            $byUser[$u]['prompt_tokens'] += $pt;
            $byUser[$u]['output_tokens'] += $ot;
            $byUser[$u]['total_tokens'] += $tt;
            $byUser[$u]['cost_usd'] += $cost;
            if (!empty($ev['ts']) && (string)$ev['ts'] > $byUser[$u]['last_seen']) {
                $byUser[$u]['last_seen'] = (string)$ev['ts'];
            }
            if (!empty($ev['nombre_completo'])) {
                $byUser[$u]['nombre_completo'] = (string)$ev['nombre_completo'];
            }
            if (!empty($ev['sector_nombre'])) {
                $byUser[$u]['sector_nombre'] = (string)$ev['sector_nombre'];
            }

            if (!isset($byModel[$m])) {
                $byModel[$m] = [
                    'model'         => $m,
                    'calls'         => 0,
                    'prompt_tokens' => 0,
                    'output_tokens' => 0,
                    'total_tokens'  => 0,
                    'cost_usd'      => 0.0,
                ];
            }
            $byModel[$m]['calls']++;
            $byModel[$m]['prompt_tokens'] += $pt;
            $byModel[$m]['output_tokens'] += $ot;
            $byModel[$m]['total_tokens'] += $tt;
            $byModel[$m]['cost_usd'] += $cost;

            if (!isset($byAction[$a])) {
                $byAction[$a] = [
                    'action'       => $a,
                    'calls'        => 0,
                    'total_tokens' => 0,
                    'cost_usd'     => 0.0,
                ];
            }
            $byAction[$a]['calls']++;
            $byAction[$a]['total_tokens'] += $tt;
            $byAction[$a]['cost_usd'] += $cost;

            if (!$cached && $kr !== '' && $kr !== 'none' && $kr !== 'cache') {
                if (!isset($byKeyRole[$kr])) {
                    $byKeyRole[$kr] = [
                        'key_role'     => $kr,
                        'calls'        => 0,
                        'total_tokens' => 0,
                        'cost_usd'     => 0.0,
                    ];
                }
                $byKeyRole[$kr]['calls']++;
                $byKeyRole[$kr]['total_tokens'] += $tt;
                $byKeyRole[$kr]['cost_usd'] += $cost;
            }
        }

        $totalReq = $summary['total_requests'];
        $summary['cache_hit_rate'] = $totalReq > 0
            ? round(($summary['cache_hits'] / $totalReq) * 100, 1)
            : 0.0;
        $summary['avg_elapsed_ms'] = $totalReq > 0
            ? round($elapsedSum / $totalReq, 1)
            : 0.0;
        $summary['estimated_cost_usd'] = round($summary['estimated_cost_usd'], 4);
        $summary['unique_users'] = count($byUser);

        ksort($daily);
        $dailyList = array_values(array_map(static function (array $item): array {
            $item['cost_usd'] = round($item['cost_usd'], 4);
            return $item;
        }, $daily));

        $usersList = array_values(array_map(static function (array $item): array {
            $item['cost_usd'] = round($item['cost_usd'], 4);
            return $item;
        }, $byUser));
        usort($usersList, static function (array $a, array $b): int {
            $cmp = $b['total_tokens'] <=> $a['total_tokens'];
            return $cmp !== 0 ? $cmp : ($b['requests'] <=> $a['requests']);
        });

        $modelsList = array_values(array_map(static function (array $item): array {
            $item['cost_usd'] = round($item['cost_usd'], 4);
            return $item;
        }, $byModel));
        usort($modelsList, static fn(array $a, array $b): int => $b['total_tokens'] <=> $a['total_tokens']);

        $actionsList = array_values(array_map(static function (array $item): array {
            $item['cost_usd'] = round($item['cost_usd'], 4);
            return $item;
        }, $byAction));
        usort($actionsList, static fn(array $a, array $b): int => $b['calls'] <=> $a['calls']);

        $keysList = array_values(array_map(static function (array $item): array {
            $item['cost_usd'] = round($item['cost_usd'], 4);
            return $item;
        }, $byKeyRole));
        usort($keysList, static fn(array $a, array $b): int => $b['calls'] <=> $a['calls']);

        $recent = array_slice(array_reverse($events), 0, max(10, min(300, $limit)));

        return [
            'summary'       => $summary,
            'daily_series'  => $dailyList,
            'by_user'       => $usersList,
            'by_model'      => $modelsList,
            'by_action'     => $actionsList,
            'by_key_role'   => $keysList,
            'recent_events' => $recent,
        ];
    }

    /**
     * Estima costo de lista USD por 1M tokens según modelo Gemini (referencia orientativa).
     */
    public static function estimateCostUsd(string $model, int $promptTokens, int $outputTokens): float
    {
        $m = strtolower($model);
        // Tarifas orientativas por 1M tokens [input, output]
        if (str_contains($m, 'flash-lite')) {
            $inRate = 0.10;
            $outRate = 0.40;
        } elseif (str_contains($m, 'pro')) {
            $inRate = 1.25;
            $outRate = 10.00;
        } elseif (str_contains($m, 'flash')) {
            $inRate = 0.30;
            $outRate = 2.50;
        } else {
            return 0.0;
        }

        return (($promptTokens / 1_000_000) * $inRate) + (($outputTokens / 1_000_000) * $outRate);
    }

    private static function normalizeUsername(string $username): string
    {
        $clean = strtoupper(trim($username));
        return $clean !== '' ? $clean : 'SISTEMA';
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function resolveDateRange(string $period, ?string $from, ?string $to): array
    {
        $today = date('Y-m-d');
        $isYmd = static fn(?string $s): bool => is_string($s) && (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $s);

        if ($isYmd($from) && $isYmd($to)) {
            return $from <= $to ? [$from, $to] : [$to, $from];
        }

        return match ($period) {
            'today' => [$today, $today],
            '7d'    => [date('Y-m-d', strtotime('-6 days')), $today],
            'all'   => ['2025-01-01', $today],
            default => [date('Y-m-01'), $today], // month
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function readEventsInRange(string $startDate, string $endDate): array
    {
        $logsDir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($logsDir)) {
            return [];
        }

        $files = glob($logsDir . '/ia_usage_*.jsonl') ?: [];
        sort($files);

        $startMonth = substr($startDate, 0, 7);
        $endMonth = substr($endDate, 0, 7);
        $events = [];

        foreach ($files as $file) {
            if (preg_match('/ia_usage_(\d{4}-\d{2})\.jsonl$/', $file, $m)) {
                $fileMonth = $m[1];
                if ($fileMonth < $startMonth || $fileMonth > $endMonth) {
                    continue;
                }
            }

            $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                continue;
            }

            foreach ($lines as $line) {
                $decoded = json_decode(trim($line), true);
                if (!is_array($decoded)) {
                    continue;
                }
                $d = (string)($decoded['date'] ?? '');
                if ($d !== '' && ($d < $startDate || $d > $endDate)) {
                    continue;
                }
                $events[] = $decoded;
            }
        }

        return $events;
    }
}
