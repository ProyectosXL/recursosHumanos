<?php

declare(strict_types=1);

namespace DocuGest\Agent;

use DocuGest\Engine\DocuGestCacheManager;
use DocuGest\Engine\DocuGestKnowledgeEngine;
use DocuGest\Engine\DocuGestMemoryManager;
use DocuGest\Engine\DocuGestUsageTracker;
use DocuGest\Providers\DocuGestGeminiClient;

require_once dirname(__DIR__) . '/Engine/DocuGestKnowledgeEngine.php';
require_once dirname(__DIR__) . '/Engine/DocuGestCacheManager.php';
require_once dirname(__DIR__) . '/Engine/DocuGestMemoryManager.php';
require_once dirname(__DIR__) . '/Engine/DocuGestUsageTracker.php';
require_once dirname(__DIR__) . '/Providers/DocuGestGeminiClient.php';
require_once dirname(__DIR__, 2) . '/Class/Politica.php';

/**
 * Runtime Agéntico ReAct 100% PHP para XL DocuGest 2.0 (inspirado en AntigravityAgentRuntime de Cerebro XL).
 *
 * Características:
 * 1. Caché L1/L2 en 0 tokens para consultas recurrentes.
 * 2. Pre-filtrado Híbrido + Compresión Quirúrgica de Secciones (~80% menos tokens que RAG tradicional).
 * 3. Loop ReAct con 4 Herramientas (Function Calling) para razonar y profundizar en documentos/versiones/glosario.
 * 4. Contexto de identidad corporativa desde W:\Gestionusuarios (Nombre, Rol, Sector).
 * 5. Pool rotativo de 4 claves Gemini + Fallback automático de modelos + Fallback determinista local sin red.
 */
final class DocuGestAgentRuntime
{
    private const MAX_REACT_TURNS = 6;

    private DocuGestKnowledgeEngine $knowledge;
    private DocuGestCacheManager $cache;
    private DocuGestGeminiClient $gemini;

    public function __construct()
    {
        $this->knowledge = DocuGestKnowledgeEngine::getInstance();
        $this->cache     = new DocuGestCacheManager();
        $this->gemini    = new DocuGestGeminiClient();
    }

    /**
     * Ejecuta una consulta completa con trazabilidad SSE opcional.
     *
     * @param array<string, mixed> $userProfile Perfil devuelto por AccesoDocuGest::perfilUsuario()
     * @param callable|null        $emit        function(string $event, array $data): void
     * @return array<string, mixed>
     */
    public function handleQuery(
        string $question,
        array $userProfile,
        string $conversationId = 'default',
        ?callable $emit = null
    ): array {
        $t0 = microtime(true);
        $emitFn = $emit ?? static function (string $ev, array $payload): void {};

        $username = (string)($userProfile['username'] ?? 'usuario');
        $userSector = (string)($userProfile['sector_nombre'] ?? '');
        $memory = new DocuGestMemoryManager($username);
        $priorTurns = $memory->loadTurns($conversationId);
        $isFirstTurn = empty($priorTurns);

        // 1. Verificar Caché L1/L2 solo en primer turno de conversación (0 tokens, < 15ms)
        $cacheKey = $this->cache->buildCacheKey($question, $userSector);
        if ($isFirstTurn) {
            $cached = $this->cache->get($cacheKey);
            if (is_array($cached) && !empty($cached['answer'])) {
                $emitFn('thought', ['message' => 'Respuesta encontrada en memoria caché corporativa (' . ($cached['cache_layer'] ?? 'L2') . ')...']);
                $elapsed = round((microtime(true) - $t0) * 1000, 1);
                $cached['elapsed_ms'] = $elapsed;
                $cached['cached'] = true;

                DocuGestUsageTracker::record([
                    'username'        => $username,
                    'nombre_completo' => (string)($userProfile['nombre_completo'] ?? $username),
                    'sector_nombre'   => $userSector,
                    'rol'             => (string)($userProfile['rol'] ?? ''),
                    'action'          => 'chat_cache',
                    'model'           => 'cache-' . strtolower((string)($cached['cache_layer'] ?? 'l2')),
                    'key_role'        => 'cache',
                    'cached'          => true,
                    'prompt_tokens'   => 0,
                    'output_tokens'   => 0,
                    'total_tokens'    => 0,
                    'saved_tokens_est' => (int)($cached['usage']['total_tokens'] ?? 2850),
                    'elapsed_ms'      => $elapsed,
                    'tool_calls_count' => 0,
                    'preview'         => $question,
                ]);

                $memory->appendTurn($conversationId, $question, (string)$cached['answer'], $cached['sources'] ?? []);
                return $cached;
            }
        }

        // 2. Pre-filtrado Híbrido local (Costo: 0 tokens)
        $emitFn('thought', ['message' => 'Analizando catálogo documental, glosario y secciones relevantes...']);
        $retrieval = $this->knowledge->searchHybrid($question, $userSector, null, 4, 6);

        // 3. Construir System Prompt compacto con Identidad + Catálogo + Contexto Quirúrgico
        $systemPrompt = $this->buildSystemInstruction($userProfile, $retrieval, $memory->buildCompactHistoryBlock($conversationId));
        $tools = $this->getToolDeclarations();

        $contents = [
            [
                'role'  => 'user',
                'parts' => [['text' => $question]],
            ],
        ];

        $usedDocIds = [];
        foreach (($retrieval['docs'] ?? []) as $d) {
            $usedDocIds[(int)$d['id']] = true;
        }
        foreach (($retrieval['sections'] ?? []) as $s) {
            $usedDocIds[(int)$s['doc_id']] = true;
        }

        $answer = '';
        $modelUsed = '';
        $keyRoleUsed = 'none';
        $fallbackReason = '';
        $totalPromptTokens = 0;
        $totalOutputTokens = 0;
        $totalTokens = 0;
        $toolCallsLog = [];

        // 4. Loop ReAct con Function Calling y Fallback automático
        try {
            for ($turn = 0; $turn < self::MAX_REACT_TURNS; $turn++) {
                $payload = [
                    'systemInstruction' => [
                        'parts' => [['text' => $systemPrompt]],
                    ],
                    'contents'          => $contents,
                    'tools'             => $tools,
                    'generationConfig'  => [
                        'temperature'     => 0.2,
                        'maxOutputTokens' => 4096,
                        'thinkingConfig'  => [
                            'thinkingLevel' => 'low',
                        ],
                    ],
                ];

                $resp = $this->gemini->generateWithFallback(
                    $payload,
                    null,
                    static function (string $statusMsg) use ($emitFn): void {
                        $emitFn('thought', ['message' => $statusMsg]);
                    },
                    60
                );

                $modelUsed = (string)$resp['model'];
                $keyRoleUsed = (string)($resp['key_role'] ?? $keyRoleUsed);
                $respData = $resp['data'];

                if (isset($respData['usageMetadata']) && is_array($respData['usageMetadata'])) {
                    $totalPromptTokens += (int)($respData['usageMetadata']['promptTokenCount'] ?? 0);
                    $totalOutputTokens += (int)($respData['usageMetadata']['candidatesTokenCount'] ?? 0);
                    $totalTokens       += (int)($respData['usageMetadata']['totalTokenCount'] ?? 0);
                }

                $parts = $respData['candidates'][0]['content']['parts'] ?? [];
                $functionCalls = [];
                $textParts = [];
                $modelPartsToPreserve = [];

                foreach ($parts as $p) {
                    if (!empty($p['thought'])) {
                        $modelPartsToPreserve[] = $p;
                        continue;
                    }
                    if (isset($p['functionCall']) && is_array($p['functionCall'])) {
                        $functionCalls[] = $p['functionCall'];
                        $modelPartsToPreserve[] = $p;
                    } elseif (isset($p['text']) && is_string($p['text'])) {
                        $textParts[] = $p['text'];
                    }
                }

                if (!empty($functionCalls)) {
                    $contents[] = [
                        'role'  => 'model',
                        'parts' => $modelPartsToPreserve,
                    ];

                    $functionResponseParts = [];
                    foreach ($functionCalls as $fc) {
                        $toolName = (string)($fc['name'] ?? '');
                        $toolArgs = is_array($fc['args'] ?? null) ? $fc['args'] : [];

                        $emitFn('tool_call', [
                            'tool' => $toolName,
                            'args' => $toolArgs,
                        ]);

                        $toolResult = $this->executeTool($toolName, $toolArgs, $userSector, $usedDocIds);
                        $toolCallsLog[] = [
                            'tool' => $toolName,
                            'args' => $toolArgs,
                        ];

                        $functionResponseParts[] = [
                            'functionResponse' => [
                                'name'     => $toolName,
                                'response' => $toolResult,
                            ],
                        ];
                    }

                    $contents[] = [
                        'role'  => 'user',
                        'parts' => $functionResponseParts,
                    ];
                    continue;
                }

                $answer = trim(implode("\n", $textParts));
                if ($answer !== '') {
                    break;
                }
            }

            // Si agotó los turnos de herramientas con info ya recuperada, forzar 1 turno de síntesis sin tools
            if ($answer === '' && !empty($toolCallsLog)) {
                $emitFn('thought', ['message' => 'Consolidando toda la información documental en la respuesta final...']);
                $contents[] = [
                    'role'  => 'user',
                    'parts' => [[
                        'text' => 'Ya consultaste las herramientas necesarias. Redactá ahora la respuesta final completa, clara y estructurada citando la Fuente Normativa, sin invocar nuevas herramientas.',
                    ]],
                ];
                $synthPayload = [
                    'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
                    'contents'          => $contents,
                    'tools'             => $tools,
                    'toolConfig'        => [
                        'functionCallingConfig' => ['mode' => 'NONE'],
                    ],
                    'generationConfig'  => [
                        'temperature'     => 0.2,
                        'maxOutputTokens' => 4096,
                        'thinkingConfig'  => ['thinkingLevel' => 'low'],
                    ],
                ];
                $synthResp = $this->gemini->generateWithFallback($synthPayload, null, null, 60);
                $modelUsed = (string)$synthResp['model'];
                $keyRoleUsed = (string)($synthResp['key_role'] ?? $keyRoleUsed);
                $synthData = $synthResp['data'];
                if (isset($synthData['usageMetadata']) && is_array($synthData['usageMetadata'])) {
                    $totalPromptTokens += (int)($synthData['usageMetadata']['promptTokenCount'] ?? 0);
                    $totalOutputTokens += (int)($synthData['usageMetadata']['candidatesTokenCount'] ?? 0);
                    $totalTokens       += (int)($synthData['usageMetadata']['totalTokenCount'] ?? 0);
                }
                $answer = trim($this->gemini->extractTextFromResponse($synthData));
            }
        } catch (\Throwable $e) {
            $fallbackReason = trim(preg_replace('/key=[^&\s]+/i', 'key=***', $e->getMessage()) ?? $e->getMessage());
            error_log('DocuGestAgentRuntime Gemini error: ' . $fallbackReason);
        }

        // 5. Fallback local determinista si no hubo respuesta del LLM
        if ($answer === '') {
            $emitFn('thought', ['message' => 'Generando respuesta directa desde las secciones documentales indexadas...']);
            $answer = $this->buildDeterministicFallbackAnswer($question, $retrieval);
            $modelUsed = 'local-deterministic-fallback';
        }

        // Construir listado limpio de documentos fuente citables con botones Ver/Descargar PDF
        $sources = $this->resolveSourcesList(array_keys($usedDocIds), $retrieval);

        $elapsedMs = round((microtime(true) - $t0) * 1000, 1);

        $result = [
            'status'          => 'success',
            'answer'          => $answer,
            'sources'         => $sources,
            'model'           => $modelUsed,
            'cached'          => false,
            'elapsed_ms'      => $elapsedMs,
            'tool_calls'      => $toolCallsLog,
            'usage'           => [
                'prompt_tokens' => $totalPromptTokens,
                'output_tokens' => $totalOutputTokens,
                'total_tokens'  => $totalTokens,
            ],
            'conversation_id' => $conversationId,
        ];

        // Persistir auditoría de consumo IA para Control Total
        $logPreview = $question;
        if ($modelUsed === 'local-deterministic-fallback' && $fallbackReason !== '') {
            $logPreview = '[Motivo: ' . mb_substr($fallbackReason, 0, 70, 'UTF-8') . '] ' . $question;
        }
        DocuGestUsageTracker::record([
            'username'         => $username,
            'nombre_completo'  => (string)($userProfile['nombre_completo'] ?? $username),
            'sector_nombre'    => $userSector,
            'rol'              => (string)($userProfile['rol'] ?? ''),
            'action'           => $modelUsed === 'local-deterministic-fallback' ? 'fallback_local' : 'chat',
            'model'            => $modelUsed,
            'key_role'         => $modelUsed === 'local-deterministic-fallback' ? 'local' : $keyRoleUsed,
            'cached'           => false,
            'prompt_tokens'    => $totalPromptTokens,
            'output_tokens'    => $totalOutputTokens,
            'total_tokens'     => $totalTokens,
            'elapsed_ms'       => $elapsedMs,
            'tool_calls_count' => count($toolCallsLog),
            'preview'          => $logPreview,
        ]);

        // Persistir memoria del usuario y caché L1/L2
        $memory->appendTurn($conversationId, $question, $answer, $sources);
        if ($isFirstTurn && $modelUsed !== 'local-deterministic-fallback' && mb_strlen($answer, 'UTF-8') > 30) {
            $this->cache->set($cacheKey, $result);
        }

        return $result;
    }

    /**
     * Construye el System Prompt de alta densidad y bajo consumo de tokens.
     *
     * @param array<string, mixed> $userProfile
     * @param array<string, mixed> $retrieval
     */
    private function buildSystemInstruction(array $userProfile, array $retrieval, string $historyBlock): string
    {
        $today = date('d/m/Y');
        $nombre = (string)($userProfile['nombre_completo'] ?? $userProfile['username'] ?? 'Usuario');
        $rol = (string)($userProfile['rol'] ?? 'USUARIO');
        $sector = (string)($userProfile['sector_nombre'] ?? '');
        $nivel = (string)($userProfile['nivel'] ?? 'Ver');

        $catalogBlock = $this->knowledge->buildCompactCatalogPrompt();

        // Compactar secciones pre-recuperadas (hasta ~7.500 chars máximo para eficiencia extrema)
        $sectionsText = "SECCIONES PRE-SELECCIONADAS DEL ÍNDICE DOCUMENTAL:\n";
        $charBudget = 7500;
        $usedChars = 0;

        if (!empty($retrieval['sections'])) {
            foreach ($retrieval['sections'] as $sec) {
                $header = "\n[DOC ID {$sec['doc_id']} | {$sec['doc_titulo']} | Sector: {$sec['sector_nombre']} | v{$sec['version']} | Sección: {$sec['titulo_seccion']}]\n";
                $body = trim((string)($sec['contenido'] ?? ''));
                $entry = $header . $body . "\n";
                $len = mb_strlen($entry, 'UTF-8');
                if ($usedChars + $len > $charBudget && $usedChars > 0) {
                    break;
                }
                $sectionsText .= $entry;
                $usedChars += $len;
            }
        } else {
            $sectionsText .= "(Sin coincidencia directa en el pre-filtrado. Usá `buscar_documentos_y_secciones` o `leer_documento_completo_o_seccion` según el Catálogo Maestro).\n";
        }

        $glossaryBlock = '';
        if (!empty($retrieval['glossary'])) {
            $glossaryBlock = "\nTÉRMINOS DE GLOSARIO CORPORATIVO RELACIONADOS:\n";
            foreach ($retrieval['glossary'] as $g) {
                $glossaryBlock .= "- **{$g['termino']}**: {$g['definicion']}\n";
            }
        }

        $sectorInfo = $sector !== '' ? ", Sector: {$sector}" : '';

        return "Sos el Asistente Normativo Inteligente de **XL DocuGest** (Extra Large / Laker S.A.).\n"
            . "Fecha actual: {$today}. Usuario autenticado: {$nombre} (Rol: {$rol}{$sectorInfo}, Nivel DocuGest: {$nivel}).\n\n"
            . "TU MISIÓN Y REGLAS INQUEBRANTABLES:\n"
            . "1. Respondé EXCLUSIVAMENTE en base a las Políticas, Procedimientos, Instructivos y Glosario oficial de Extra Large.\n"
            . "2. PROHIBIDO INVENTAR pasos, plazos, nombres de sistemas, formularios o responsables que no figuren en los documentos.\n"
            . "3. Si las secciones pre-cargadas son insuficientes, incompletas o cortan una tabla/paso a la mitad, INVOCÁ obligatoriamente `leer_documento_completo_o_seccion(documento_id)` o `buscar_documentos_y_secciones(consulta)` antes de responder (si necesitás leer varios documentos relacionados, llamalos juntos en paralelo en el mismo turno para mayor velocidad y ahorro).\n"
            . "4. Cuando expliques un procedimiento, presentalo de forma clara, accionable y ordenada (pasos numerados, responsables, requisitos y validaciones en bullets o tabla Markdown limpia).\n"
            . "5. Citá siempre al final en una sección breve '**Fuente Normativa**' el título exacto del documento, su Sector y su Versión vigente.\n"
            . "6. Si el usuario pregunta algo que no está cubierto en ningún documento cargado en el Catálogo Maestro, decilo con transparencia y sugerí los documentos más cercanos si aplica.\n\n"
            . $catalogBlock . "\n\n"
            . $sectionsText
            . $glossaryBlock
            . ($historyBlock !== '' ? "\n" . $historyBlock . "\n" : '');
    }

    /**
     * Declaraciones de Function Calling para Gemini.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getToolDeclarations(): array
    {
        return [
            [
                'functionDeclarations' => [
                    [
                        'name'        => 'buscar_documentos_y_secciones',
                        'description' => 'Busca en el índice completo de políticas, procedimientos e instructivos por palabras clave, sector o tipo cuando necesites encontrar otro documento o ampliar el contexto.',
                        'parameters'  => [
                            'type'       => 'OBJECT',
                            'properties' => [
                                'consulta' => [
                                    'type'        => 'STRING',
                                    'description' => 'Términos clave o tema a buscar (ej. "aprobación orden de compra", "alta sucursal", "etiquetas precios").',
                                ],
                                'tipo' => [
                                    'type'        => 'STRING',
                                    'enum'        => ['politica', 'procedimiento'],
                                    'description' => 'Filtro opcional por tipo de documento.',
                                ],
                            ],
                            'required' => ['consulta'],
                        ],
                    ],
                    [
                        'name'        => 'leer_documento_completo_o_seccion',
                        'description' => 'Lee el contenido estructurado completo o una sección específica de un documento por su ID numérico del Catálogo Maestro. Ideal para dar instrucciones paso a paso sin omitir detalles.',
                        'parameters'  => [
                            'type'       => 'OBJECT',
                            'properties' => [
                                'documento_id' => [
                                    'type'        => 'INTEGER',
                                    'description' => 'ID numérico del documento según el Catálogo Maestro.',
                                ],
                                'seccion' => [
                                    'type'        => 'STRING',
                                    'description' => 'Opcional: palabra clave o título de la sección específica a filtrar dentro del documento.',
                                ],
                            ],
                            'required' => ['documento_id'],
                        ],
                    ],
                    [
                        'name'        => 'consultar_glosario_corporativo',
                        'description' => 'Busca definiciones oficiales en el Glosario corporativo de XL DocuGest en SQL Server.',
                        'parameters'  => [
                            'type'       => 'OBJECT',
                            'properties' => [
                                'termino' => [
                                    'type'        => 'STRING',
                                    'description' => 'Término o sigla a consultar (vacío para listar términos disponibles).',
                                ],
                            ],
                        ],
                    ],
                    [
                        'name'        => 'obtener_historial_y_metadatos_documento',
                        'description' => 'Obtiene metadatos vigentes (creador, versión actual, fecha actualización, tags) e historial de versiones y cambios desde Historial_Versiones en SQL Server.',
                        'parameters'  => [
                            'type'       => 'OBJECT',
                            'properties' => [
                                'documento_id' => [
                                    'type'        => 'INTEGER',
                                    'description' => 'ID del documento a consultar.',
                                ],
                            ],
                            'required' => ['documento_id'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $args
     * @param array<int, bool>     $usedDocIds
     * @return array<string, mixed>
     */
    private function executeTool(string $toolName, array $args, string $userSector, array &$usedDocIds): array
    {
        try {
            if ($toolName === 'buscar_documentos_y_secciones') {
                $q = trim((string)($args['consulta'] ?? ''));
                $tipo = trim((string)($args['tipo'] ?? ''));
                $res = $this->knowledge->searchHybrid($q, $userSector, $tipo !== '' ? $tipo : null, 5, 6);
                foreach (($res['docs'] ?? []) as $d) {
                    $usedDocIds[(int)$d['id']] = true;
                }
                foreach (($res['sections'] ?? []) as $s) {
                    $usedDocIds[(int)$s['doc_id']] = true;
                }
                return [
                    'status'     => 'ok',
                    'documentos' => $res['docs'] ?? [],
                    'secciones'  => $res['sections'] ?? [],
                ];
            }

            if ($toolName === 'leer_documento_completo_o_seccion') {
                $docId = (int)($args['documento_id'] ?? 0);
                $sec = isset($args['seccion']) ? trim((string)$args['seccion']) : null;
                if ($docId <= 0) {
                    return ['status' => 'error', 'message' => 'documento_id inválido'];
                }
                $usedDocIds[$docId] = true;
                return $this->knowledge->readDocumentStructured($docId, $sec);
            }

            if ($toolName === 'consultar_glosario_corporativo') {
                $term = trim((string)($args['termino'] ?? ''));
                $pol = new \Politica();
                $rows = $pol->obtenerGlosario($term);
                $items = [];
                foreach (array_slice($rows, 0, 15) as $r) {
                    $items[] = [
                        'termino'    => $r['termino'] ?? '',
                        'definicion' => $r['definicion'] ?? '',
                        'sector'     => $r['sector_nombre'] ?? '',
                    ];
                }
                return ['status' => 'ok', 'terminos' => $items];
            }

            if ($toolName === 'obtener_historial_y_metadatos_documento') {
                $docId = (int)($args['documento_id'] ?? 0);
                if ($docId <= 0) {
                    return ['status' => 'error', 'message' => 'documento_id inválido'];
                }
                $usedDocIds[$docId] = true;
                $pol = new \Politica();
                $doc = $pol->obtenerPorId($docId, false, true);
                if (!$doc) {
                    return ['status' => 'not_found', 'message' => 'Documento no encontrado o inactivo'];
                }
                $hist = $pol->obtenerHistorialVersiones($docId);
                return [
                    'status'              => 'ok',
                    'id'                  => (int)$doc['id'],
                    'titulo'              => $doc['titulo'],
                    'tipo'                => $doc['tipo'],
                    'sector'              => $doc['sector_nombre'],
                    'version'             => $doc['version'] ?? '1.0',
                    'creado_por'          => $doc['creado_por'] ?? '',
                    'fecha_creacion'      => \Politica::formatearFecha($doc['fecha_creacion'] ?? null),
                    'fecha_actualizacion' => \Politica::formatearFecha($doc['fecha_actualizacion'] ?? null),
                    'tags'                => $doc['tags'] ?? '',
                    'historial'           => $hist,
                ];
            }

            return ['status' => 'error', 'message' => "Herramienta desconocida: {$toolName}"];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * @param int[]                $candidateDocIds
     * @param array<string, mixed> $retrieval
     * @return array<int, array<string, mixed>>
     */
    private function resolveSourcesList(array $candidateDocIds, array $retrieval): array
    {
        $index = $this->knowledge->getCompiledIndex(false);
        $docs = $index['documents'] ?? [];
        $scoreMap = [];
        foreach (($retrieval['docs'] ?? []) as $d) {
            $scoreMap[(int)$d['id']] = (float)$d['score'];
        }

        $sources = [];
        foreach ($candidateDocIds as $id) {
            $doc = $docs[(string)$id] ?? null;
            if (!is_array($doc)) {
                continue;
            }
            $sources[] = [
                'id'            => (int)$doc['id'],
                'titulo'        => (string)$doc['titulo'],
                'tipo'          => (string)$doc['tipo'],
                'sector_nombre' => (string)$doc['sector_nombre'],
                'version'       => (string)($doc['version'] ?? '1.0'),
                'score'         => $scoreMap[(int)$id] ?? 1.0,
            ];
        }

        usort($sources, static fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($sources, 0, 4);
    }

    /**
     * @param array<string, mixed> $retrieval
     */
    private function buildDeterministicFallbackAnswer(string $question, array $retrieval): string
    {
        $sections = $retrieval['sections'] ?? [];
        if (empty($sections)) {
            return "No encontré coincidencias directas en los documentos vigentes para \"{$question}\". "
                . "Probá reformular tu consulta usando el nombre del proceso o consultá el listado por sector en el panel principal.";
        }

        $out = "Encontré la siguiente información relevante en la base documental de **XL DocuGest**:\n\n";
        $shown = 0;
        foreach ($sections as $sec) {
            if ($shown >= 3) {
                break;
            }
            $out .= "### {$sec['doc_titulo']} — {$sec['titulo_seccion']}\n";
            $out .= trim((string)$sec['contenido']) . "\n\n";
            $shown++;
        }

        return trim($out);
    }
}
