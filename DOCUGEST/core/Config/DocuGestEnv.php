<?php

declare(strict_types=1);

namespace DocuGest\Config;

/**
 * Cargador centralizado y seguro de variables de entorno (.env) para DocuGest 2.0.
 * Compatible con W:\.env y el pool de claves LLM de Cerebro XL (W:\FrancoPrueba\bypass\storage\config\llm_keys.env).
 */
final class DocuGestEnv
{
    private static bool $loaded = false;

    /** @var array<string, string> */
    private static array $variables = [];

    public static function load(?string $customEnvPath = null): void
    {
        if (self::$loaded && $customEnvPath === null) {
            return;
        }
        self::$loaded = true;

        $candidatePaths = [];

        // 1. Raíz web real (D:\htdocs en servidor / W:\ en red / $_SERVER['DOCUMENT_ROOT'])
        $htdocsRoot = dirname(__DIR__, 4); // DOCUGEST/core/Config -> 4 niveles = htdocs
        $docRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/\\') : '';
        $searchRoots = array_values(array_unique(array_filter([
            $htdocsRoot,
            $docRoot,
            $docRoot !== '' ? dirname($docRoot) : '',
            dirname(__DIR__, 3),
        ])));

        foreach ($searchRoots as $root) {
            $candidatePaths[] = $root . '/.env';
            $candidatePaths[] = $root . '/FrancoPrueba/bypass/.env';
            $candidatePaths[] = $root . '/FrancoPrueba/bypass/storage/config/llm_keys.env';
        }

        // 2. Configuración local de DOCUGEST y fallback histórico de unidad W:
        $candidatePaths[] = dirname(__DIR__, 2) . '/.env';
        $candidatePaths[] = dirname(__DIR__, 2) . '/storage/config/llm_keys.env';
        $candidatePaths[] = 'W:/.env';
        $candidatePaths[] = 'W:/FrancoPrueba/bypass/storage/config/llm_keys.env';

        if ($customEnvPath !== null) {
            $candidatePaths[] = $customEnvPath;
        }

        $candidatePaths = array_values(array_unique($candidatePaths));

        foreach ($candidatePaths as $path) {
            if (is_file($path) && is_readable($path)) {
                self::parseEnvFile($path);
            }
        }

        $tz = self::get('APP_TIMEZONE', 'America/Argentina/Buenos_Aires') ?? 'America/Argentina/Buenos_Aires';
        @date_default_timezone_set($tz);
    }

    private static function parseEnvFile(string $filePath): void
    {
        $lines = @file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
                continue;
            }

            [$rawKey, $rawVal] = explode('=', $trimmed, 2);
            $key = trim($rawKey);
            if ($key === '') {
                continue;
            }

            $val = trim($rawVal);
            if (
                (str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                (str_starts_with($val, "'") && str_ends_with($val, "'"))
            ) {
                $val = substr($val, 1, -1);
            }

            if ($val !== '') {
                self::$variables[$key] = $val;
                if (!isset($_ENV[$key]) || $_ENV[$key] === '') {
                    $_ENV[$key] = $val;
                }
            }
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        self::load();

        if (isset(self::$variables[$key]) && self::$variables[$key] !== '') {
            return self::$variables[$key];
        }

        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return (string)$_ENV[$key];
        }

        $envVal = getenv($key);
        if ($envVal !== false && $envVal !== '') {
            return (string)$envVal;
        }

        return $default;
    }

    /**
     * Pool rotativo de hasta 4 API Keys Gemini para evitar 429/exhaustion.
     * Deduplica claves idénticas y permite rotación distribuida por hash/tiempo.
     *
     * @return array<string, array{key: string, label: string}>
     */
    public static function getAgentKeysPool(): array
    {
        self::load();

        $baseKey = (string)self::get('GEMINI_API_KEY', '');
        $candidates = [
            'agente_1' => ['key' => (string)(self::get('GEMINI_API_KEY_1') ?: self::get('GEMINI_AGENT1_KEY') ?: $baseKey), 'label' => 'Clave Principal'],
            'agente_2' => ['key' => (string)(self::get('GEMINI_API_KEY_2') ?: self::get('GEMINI_AGENT2_KEY') ?: ''), 'label' => 'Clave Secundaria'],
            'agente_3' => ['key' => (string)(self::get('GEMINI_API_KEY_3') ?: self::get('GEMINI_AGENT3_KEY') ?: ''), 'label' => 'Clave Respaldo A'],
            'agente_4' => ['key' => (string)(self::get('GEMINI_API_KEY_4') ?: self::get('GEMINI_AGENT4_KEY') ?: ''), 'label' => 'Clave Respaldo B'],
        ];

        $pool = [];
        $seen = [];
        foreach ($candidates as $role => $info) {
            $k = trim($info['key']);
            if ($k === '' || isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $pool[$role] = ['key' => $k, 'label' => $info['label']];
        }

        return $pool;
    }

    /**
     * Cadena ordenada de modelos Gemini (balance velocidad / razonamiento / ahorro de tokens).
     *
     * @return string[]
     */
    public static function getModelFallbackChain(): array
    {
        $configured = self::get('DOCUGEST_MODEL_CHAIN', '');
        if ($configured !== '') {
            $parts = array_values(array_filter(array_map('trim', explode(',', $configured))));
            if (!empty($parts)) {
                return $parts;
            }
        }

        return [
            'gemini-2.5-flash',
            'gemini-flash-lite-latest',
            'gemini-2.5-flash-lite',
            'gemini-3-flash-preview',
        ];
    }

    /**
     * Cadena optimizada de bajo costo para ingesta/extracción/tags de PDFs.
     *
     * @return string[]
     */
    public static function getIngestionModelChain(): array
    {
        return [
            'gemini-flash-lite-latest',
            'gemini-2.5-flash-lite',
            'gemini-2.5-flash',
            'gemini-3-flash-preview',
        ];
    }
}
