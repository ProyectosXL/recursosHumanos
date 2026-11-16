<?php

declare(strict_types=1);

namespace DocuGest\Providers;

use DocuGest\Config\DocuGestEnv;
use RuntimeException;

require_once dirname(__DIR__) . '/Config/DocuGestEnv.php';

/**
 * Cliente nativo PHP para Gemini REST API con:
 * - Pool rotativo de hasta 4 API Keys (estilo Cerebro XL)
 * - Fallback automático entre modelos ante 429/503/errores transitorios
 * - Soporte de Function Calling (tools), thinkingConfig y extracción multimodal de PDFs
 */
final class DocuGestGeminiClient
{
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

    /**
     * Ejecuta una llamada a generateContent con rotación de claves y fallback de modelos.
     *
     * @param array<string, mixed> $payload
     * @param string[]|null        $modelChain
     * @param callable|null        $onStatus
     * @return array<string, mixed> ['data' => array, 'model' => string, 'key_role' => string]
     */
    public function generateWithFallback(
        array $payload,
        ?array $modelChain = null,
        ?callable $onStatus = null,
        int $timeoutSec = 60
    ): array {
        $keysPool = DocuGestEnv::getAgentKeysPool();
        if (empty($keysPool)) {
            throw new RuntimeException('No hay ninguna GEMINI_API_KEY configurada en el entorno.');
        }

        $models = $modelChain ?? DocuGestEnv::getModelFallbackChain();
        $lastError = 'Sin respuesta del proveedor Gemini';

        foreach ($models as $modelName) {
            $modelPayload = $payload;
            $modelPayload['generationConfig'] = $this->normalizeGenerationConfig(
                $modelName,
                $payload['generationConfig'] ?? []
            );

            try {
                $resp = $this->callWithKeyRotation($modelName, $modelPayload, $keysPool, $onStatus, $timeoutSec);
                return [
                    'data'     => $resp['data'],
                    'model'    => $modelName,
                    'key_role' => $resp['key_role'],
                ];
            } catch (\Throwable $e) {
                $lastError = "[{$modelName}] " . $e->getMessage();
                if ($onStatus !== null) {
                    $onStatus("Cambiando a modelo de respaldo tras fallo en {$modelName}...");
                }
            }
        }

        throw new RuntimeException('Todas las claves y modelos Gemini fallaron: ' . $lastError);
    }

    /**
     * Rota por todas las claves disponibles del pool antes de saltar de modelo.
     *
     * @param array<string, mixed>                              $payload
     * @param array<string, array{key: string, label: string}>  $keysPool
     * @return array{data: array<string, mixed>, key_role: string}
     */
    public function callWithKeyRotation(
        string $modelName,
        array $payload,
        array $keysPool,
        ?callable $onStatus = null,
        int $timeoutSec = 60
    ): array {
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new RuntimeException('No se pudo codificar el payload JSON para Gemini.');
        }

        $lastHttp = 0;
        $lastErr = '';
        $idx = 0;

        foreach ($keysPool as $role => $info) {
            $apiKey = $info['key'] ?? '';
            if ($apiKey === '') {
                continue;
            }

            if ($idx > 0) {
                usleep(250000);
            }
            $idx++;

            $url = self::API_BASE . rawurlencode($modelName) . ':generateContent?key=' . rawurlencode($apiKey);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS     => $encoded,
                CURLOPT_TIMEOUT        => $timeoutSec,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            $body = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);

            if ($curlErr !== '') {
                $lastHttp = 0;
                $lastErr = $curlErr;
                continue;
            }

            if ($httpCode >= 200 && $httpCode < 300 && is_string($body)) {
                $decoded = json_decode($body, true);
                if (is_array($decoded) && isset($decoded['candidates'][0])) {
                    return [
                        'data'     => $decoded,
                        'key_role' => $role,
                    ];
                }
            }

            $lastHttp = $httpCode;
            $lastErr = is_string($body) ? mb_substr($body, 0, 350, 'UTF-8') : 'Respuesta vacía';

            if (in_array($httpCode, [429, 500, 502, 503, 504], true)) {
                if ($onStatus !== null) {
                    $onStatus("Rotando clave ({$role}) por código HTTP {$httpCode}...");
                }
                if ($httpCode === 429) {
                    usleep(650000);
                }
                continue;
            }

            // En errores 400 de esquema/payload no rotar 4 veces la misma solicitud inválida
            if ($httpCode >= 400 && $httpCode < 500) {
                break;
            }
        }

        throw new RuntimeException("HTTP {$lastHttp}: {$lastErr}");
    }

    /**
     * Analiza un documento PDF (texto extraído o binario inlineData si es necesario) para extraer:
     * - resumen_ejecutivo
     * - secciones semánticas limpias
     * - tags normalizados (8-12)
     * - términos del glosario (hasta 5)
     *
     * @param array<string, mixed> $docMeta
     * @return array<string, mixed>
     */
    public function analyzePdfDocument(string $pdfPath, string $rawExtractedText, array $docMeta): array
    {
        $titulo = trim((string)($docMeta['titulo'] ?? 'Documento'));
        $sector = trim((string)($docMeta['sector_nombre'] ?? 'General'));
        $tipo   = trim((string)($docMeta['tipo'] ?? 'procedimiento'));

        $instruction = "Sos un Analista Documental Corporativo de Extra Large (Laker S.A.).\n"
            . "Tu tarea es estructurar un documento normativo ({$tipo}) del sector '{$sector}' titulado '{$titulo}' para que un asistente RAG lo consulte con mínimo consumo de tokens y máxima precisión.\n"
            . "Devolvé EXCLUSIVAMENTE un JSON válido con esta estructura exacta:\n"
            . "{\n"
            . "  \"resumen_ejecutivo\": \"Síntesis clara en 2-3 oraciones de qué regula o enseña este documento y a quién aplica.\",\n"
            . "  \"secciones\": [\n"
            . "    {\"titulo_seccion\": \"Nombre claro de la sección\", \"contenido\": \"Contenido fiel, limpio, preservando pasos numerados, responsables, plazos, códigos y reglas clave en español.\"}\n"
            . "  ],\n"
            . "  \"tags\": [\"tag1\", \"tag2\"],\n"
            . "  \"glosario\": [\n"
            . "    {\"termino\": \"Término o Sistema\", \"definicion\": \"Definición breve y exacta según el documento.\"}\n"
            . "  ]\n"
            . "}\n"
            . "REGLAS:\n"
            . "- No inventes información que no esté en el documento.\n"
            . "- Genera entre 8 y 12 tags en minúsculas (sin símbolos raros), muy útiles para búsqueda.\n"
            . "- Genera hasta 5 términos clave para glosario (sistemas internos, siglas, conceptos operativos).\n"
            . "- Divide el contenido en secciones lógicas (Objetivo, Alcance, Responsables, Paso a Paso, Validaciones/Excepciones, etc.).";

        $parts = [['text' => $instruction]];

        $cleanTextLen = mb_strlen(trim($rawExtractedText), 'UTF-8');
        // Si el texto extraído localmente es pobre (< 350 chars) y el PDF pesa <= 5.5 MB, enviar el PDF binario multimodal
        if ($cleanTextLen < 350 && is_file($pdfPath) && filesize($pdfPath) > 0 && filesize($pdfPath) <= 5767168) {
            $pdfBytes = @file_get_contents($pdfPath);
            if ($pdfBytes !== false) {
                $parts[] = [
                    'inlineData' => [
                        'mimeType' => 'application/pdf',
                        'data'     => base64_encode($pdfBytes),
                    ],
                ];
            }
        } else {
            // Limitar texto enviado en ingesta a ~35.000 chars para eficiencia extrema
            $trimmedText = mb_substr(trim($rawExtractedText), 0, 35000, 'UTF-8');
            $parts[] = ['text' => "CONTENIDO COMPLETO EXTRAÍDO DEL PDF:\n\n" . $trimmedText];
        }

        $payload = [
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => $parts,
                ],
            ],
            'generationConfig' => [
                'temperature'      => 0.1,
                'maxOutputTokens'  => 8192,
                'responseMimeType' => 'application/json',
            ],
        ];

        $result = $this->generateWithFallback(
            $payload,
            DocuGestEnv::getIngestionModelChain(),
            null,
            90
        );

        $rawJson = $this->extractTextFromResponse($result['data']);
        $parsed = $this->safeParseJson($rawJson);
        $usageMeta = is_array($result['data']['usageMetadata'] ?? null) ? $result['data']['usageMetadata'] : [];

        return [
            'status'            => is_array($parsed) ? 'ok' : 'fallback',
            'model'             => $result['model'],
            'key_role'          => (string)($result['key_role'] ?? 'primary'),
            'usage'             => [
                'prompt_tokens' => (int)($usageMeta['promptTokenCount'] ?? 0),
                'output_tokens' => (int)($usageMeta['candidatesTokenCount'] ?? 0),
                'total_tokens'  => (int)($usageMeta['totalTokenCount'] ?? 0),
            ],
            'resumen_ejecutivo' => trim((string)($parsed['resumen_ejecutivo'] ?? '')),
            'secciones'         => is_array($parsed['secciones'] ?? null) ? $parsed['secciones'] : [],
            'tags'              => is_array($parsed['tags'] ?? null) ? $parsed['tags'] : [],
            'glosario'          => is_array($parsed['glosario'] ?? null) ? $parsed['glosario'] : [],
        ];
    }

    /**
     * Extrae el texto visible de la respuesta de Gemini (ignorando partes thought internas).
     *
     * @param array<string, mixed> $respData
     */
    public function extractTextFromResponse(array $respData): string
    {
        $parts = $respData['candidates'][0]['content']['parts'] ?? [];
        if (!is_array($parts)) {
            return '';
        }

        $texts = [];
        foreach ($parts as $part) {
            if (!empty($part['thought'])) {
                continue;
            }
            if (isset($part['text']) && is_string($part['text'])) {
                $texts[] = $part['text'];
            }
        }

        return trim(implode("\n", $texts));
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function normalizeGenerationConfig(string $modelName, array $config): array
    {
        $m = strtolower(trim($modelName));
        $normalized = $config;

        // Solo gemini-3* soporta thinkingConfig.thinkingLevel sin errores HTTP 400
        if (isset($normalized['thinkingConfig'])) {
            if (str_contains($m, 'lite') || !str_starts_with($m, 'gemini-3')) {
                unset($normalized['thinkingConfig']);
            }
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function safeParseJson(string $text): ?array
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return null;
        }
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Quitar fences ```json ... ```
        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $trimmed, $m)) {
            $decoded = json_decode($m[1], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($trimmed, $start, $end - $start + 1), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
