<?php

declare(strict_types=1);

namespace DocuGest\Engine;

/**
 * Caché L1 (memoria) + L2 (archivo JSON) para respuestas del asistente DocuGest 2.0.
 * Permite responder preguntas frecuentes en < 15ms con consumo de 0 tokens
 * y se invalida automáticamente cuando se modifica cualquier documento o glosario.
 */
final class DocuGestCacheManager
{
    private const DEFAULT_TTL_SECONDS = 86400; // 24 horas

    /** @var array<string, array<string, mixed>> */
    private static array $l1Cache = [];

    private string $cacheDir;
    private string $epochFile;

    public function __construct(?string $cacheDir = null)
    {
        $baseDir = dirname(__DIR__, 2) . '/storage/cache';
        $this->cacheDir = $cacheDir ?? ($baseDir . '/answers');
        $this->epochFile = $baseDir . '/index_epoch.txt';

        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0775, true);
        }
    }

    public function getEpoch(): string
    {
        if (is_file($this->epochFile)) {
            $v = trim((string)@file_get_contents($this->epochFile));
            if ($v !== '') {
                return $v;
            }
        }
        $epoch = (string)time();
        @file_put_contents($this->epochFile, $epoch, LOCK_EX);
        return $epoch;
    }

    /**
     * Invalida toda la caché de respuestas incrementando el epoch del índice.
     */
    public function invalidateAll(): void
    {
        self::$l1Cache = [];
        $newEpoch = (string)microtime(true);
        @file_put_contents($this->epochFile, $newEpoch, LOCK_EX);

        if (is_dir($this->cacheDir)) {
            $files = @glob($this->cacheDir . '/*.cache.json');
            if (is_array($files)) {
                foreach ($files as $f) {
                    @unlink($f);
                }
            }
        }
    }

    public function buildCacheKey(string $question, string $userSector = ''): string
    {
        $norm = mb_strtolower(trim($question), 'UTF-8');
        $norm = strtr($norm, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        $norm = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $norm) ?? $norm;
        $norm = preg_replace('/\s+/u', ' ', trim($norm)) ?? $norm;
        $epoch = $this->getEpoch();
        return sha1($epoch . '|' . mb_strtolower(trim($userSector), 'UTF-8') . '|' . $norm);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $key): ?array
    {
        $now = time();

        if (isset(self::$l1Cache[$key])) {
            $entry = self::$l1Cache[$key];
            if ((int)($entry['expires_at'] ?? 0) > $now) {
                $entry['payload']['cache_layer'] = 'L1';
                return $entry['payload'];
            }
            unset(self::$l1Cache[$key]);
        }

        $file = $this->cacheDir . '/' . preg_replace('/[^a-f0-9]/i', '', $key) . '.cache.json';
        if (!is_file($file)) {
            return null;
        }

        $raw = @file_get_contents($file);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || (int)($decoded['expires_at'] ?? 0) <= $now) {
            @unlink($file);
            return null;
        }

        self::$l1Cache[$key] = $decoded;
        $decoded['payload']['cache_layer'] = 'L2';
        return $decoded['payload'] ?? null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function set(string $key, array $payload, int $ttlSeconds = self::DEFAULT_TTL_SECONDS): void
    {
        $entry = [
            'cached_at'  => time(),
            'expires_at' => time() + max(60, $ttlSeconds),
            'payload'    => $payload,
        ];

        self::$l1Cache[$key] = $entry;
        $file = $this->cacheDir . '/' . preg_replace('/[^a-f0-9]/i', '', $key) . '.cache.json';
        @file_put_contents($file, json_encode($entry, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
}
