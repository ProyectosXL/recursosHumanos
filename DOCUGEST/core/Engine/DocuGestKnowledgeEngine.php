<?php

declare(strict_types=1);

namespace DocuGest\Engine;

use DocuGest\Providers\DocuGestGeminiClient;

require_once dirname(__DIR__) . '/Providers/DocuGestGeminiClient.php';
require_once __DIR__ . '/DocuGestPdfParser.php';
require_once __DIR__ . '/DocuGestCacheManager.php';
require_once __DIR__ . '/DocuGestUsageTracker.php';
require_once dirname(__DIR__, 3) . '/Class/conexion.php';

/**
 * Motor Compilado Híbrido de Conocimiento Documental para DocuGest 2.0.
 * Inspirado en AppsKnowledgeBaseEngine + CorporateKnowledgeEngine de Cerebro XL:
 *
 * - Compila todos los documentos PDF activos + metadatos + Glosario en un índice JSON (< 5ms).
 * - Cero latencia en frío (adiós HF Spaces).
 * - Buscador híbrido (BM25 + Título + Tags + Sector + Glosario + Sinónimos XL).
 * - Compresión quirúrgica por secciones para mínimo consumo de tokens (~80% de ahorro).
 */
final class DocuGestKnowledgeEngine
{
    private static ?self $instance = null;

    /** @var array<string, mixed>|null */
    private static ?array $memoryIndex = null;

    private string $indexFile;
    private string $docsDir;
    private string $fallbackDocsDir;

    private const STOPWORDS = [
        'el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas', 'de', 'del', 'al', 'a', 'ante',
        'bajo', 'cabe', 'con', 'contra', 'desde', 'en', 'entre', 'hacia', 'hasta', 'para',
        'por', 'segun', 'sin', 'sobre', 'tras', 'y', 'o', 'u', 'e', 'ni', 'que', 'cual',
        'como', 'donde', 'cuando', 'quien', 'cuanto', 'cuyo', 'esta', 'este', 'esto', 'estos',
        'esa', 'ese', 'eso', 'esos', 'es', 'son', 'ser', 'fue', 'fueron', 'hay', 'haber',
        'tener', 'tiene', 'tienen', 'hacer', 'hace', 'hacen', 'puedo', 'puede', 'pueden',
        'se', 'me', 'te', 'le', 'nos', 'les', 'lo', 'su', 'sus', 'mi', 'mis', 'tu', 'tus',
        'mas', 'menos', 'muy', 'ya', 'si', 'no', 'pero', 'porque', 'sobre', 'todo', 'toda',
        'todos', 'todas', 'algo', 'algun', 'alguna', 'necesito', 'saber', 'cual', 'cuales',
        'procedimiento', 'politica', 'instructivo', 'documento', 'manual', 'explicame', 'decime',
    ];

    /** @var array<string, string[]> */
    private const XL_SYNONYMS = [
        'oc'            => ['orden de compra', 'ordenes de compra', 'compra', 'emision'],
        'sc'            => ['solicitud de compra', 'solicitudes de compra', 'compra'],
        'trello'        => ['proyecto', 'desarrollo', 'tablero', 'tarjeta', 'innovacion'],
        'github'        => ['repositorio', 'repo', 'codigo', 'git', 'branch', 'rama'],
        'action'        => ['ticket', 'tickets', 'soporte', 'portal', 'mesa de ayuda', 'it'],
        'etiqueta'      => ['etiquetas', 'precio', 'precios', 'impresion', 'rotulo', 'cartel'],
        'nexo'          => ['orden', 'ordenes', 'ecommerce', 'tiendas', 'revision'],
        'remision'      => ['despacho', 'mercaderia', 'remito', 'transporte', 'envio', 'locales'],
        'inventario'    => ['stock', 'toma de inventario', 'deposito central', 'conteo', 'ajuste'],
        'sucursal'      => ['sucursales', 'alta', 'baja', 'apertura', 'cierre', 'tienda', 'local'],
        'recepcion'     => ['bienes', 'servicios', 'remito', 'factura', 'proveedor', 'conformidad'],
        'ecommerce'     => ['pedido', 'pedidos', 'retiro', 'tienda', 'entrega', 'online', 'vtex'],
        'aprobacion'    => ['aprobar', 'autorizacion', 'autorizar', 'firma', 'gerencia'],
    ];

    private function __construct()
    {
        $this->indexFile = dirname(__DIR__, 2) . '/storage/cache/docugest_compiled_index.json';
        $this->docsDir = dirname(__DIR__, 2) . '/documentos/';
        $this->fallbackDocsDir = dirname(__DIR__, 3) . '/p&p/documentos/';
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Carga el índice compilado en < 5ms; si aún no existe, lo compila automáticamente.
     *
     * @return array<string, mixed>
     */
    public function getCompiledIndex(bool $forceRebuild = false): array
    {
        if (!$forceRebuild && self::$memoryIndex !== null) {
            return self::$memoryIndex;
        }

        if (!$forceRebuild && is_file($this->indexFile)) {
            $raw = @file_get_contents($this->indexFile);
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && isset($decoded['documents']) && is_array($decoded['documents'])) {
                    self::$memoryIndex = $decoded;
                    return $decoded;
                }
            }
        }

        return $this->compileAllActiveDocuments(false)['index'];
    }

    /**
     * Compila TODOS los documentos activos y el Glosario desde SQL Server + PDFs locales.
     *
     * @return array<string, mixed>
     */
    public function compileAllActiveDocuments(bool $useAiEnrichment = false): array
    {
        $t0 = microtime(true);
        $cx = new \Conexion();
        $conn = $cx->conectar('central');
        if (!$conn) {
            throw new \RuntimeException('No se pudo conectar a SQL Server (central) para compilar el índice.');
        }

        $sqlDocs = "SELECT p.id, p.tipo, p.titulo, p.descripcion, p.sector_id,
                           s.nombre AS sector_nombre, p.archivo_nombre, p.ruta_archivo,
                           p.tags, p.version, p.fecha_creacion, p.fecha_actualizacion, p.creado_por
                      FROM Politicas_Procedimientos p WITH (NOLOCK)
                      LEFT JOIN Sectores s WITH (NOLOCK) ON p.sector_id = s.id
                     WHERE p.estado = 'activo'
                     ORDER BY p.id ASC";
        $stmt = sqlsrv_query($conn, $sqlDocs);
        if ($stmt === false) {
            sqlsrv_close($conn);
            throw new \RuntimeException('Error consultando Politicas_Procedimientos.');
        }

        $docs = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $docs[] = $row;
        }

        // Cargar índice anterior para reutilizar enriquecimiento IA si el archivo/versión no cambió
        $prevById = [];
        if (is_file($this->indexFile)) {
            $prevRaw = @file_get_contents($this->indexFile);
            $prevDec = is_string($prevRaw) ? json_decode($prevRaw, true) : null;
            if (is_array($prevDec) && !empty($prevDec['documents'])) {
                foreach ($prevDec['documents'] as $pDoc) {
                    $prevById[(int)($pDoc['id'] ?? 0)] = $pDoc;
                }
            }
        }

        $compiledDocs = [];
        $enrichedCount = 0;
        $totalSections = 0;

        foreach ($docs as $row) {
            $docId = (int)$row['id'];
            $prevDoc = $prevById[$docId] ?? null;
            $compiled = $this->buildCompiledDocEntry($conn, $row, $useAiEnrichment, $prevDoc);
            if (!empty($compiled['ai_enriched_now'])) {
                $enrichedCount++;
            }
            unset($compiled['ai_enriched_now']);
            $totalSections += count($compiled['sections'] ?? []);
            $compiledDocs[(string)$docId] = $compiled;
        }

        $glossary = $this->fetchGlossaryFromConn($conn);
        sqlsrv_close($conn);

        $index = [
            'schema_version' => 2,
            'compiled_at'    => date('c'),
            'document_count' => count($compiledDocs),
            'section_count'  => $totalSections,
            'glossary_count' => count($glossary),
            'documents'      => $compiledDocs,
            'glossary'       => $glossary,
        ];

        $this->saveCompiledIndex($index);
        (new DocuGestCacheManager())->invalidateAll();

        $elapsedMs = round((microtime(true) - $t0) * 1000, 1);

        return [
            'status'         => 'success',
            'document_count' => count($compiledDocs),
            'section_count'  => $totalSections,
            'glossary_count' => count($glossary),
            'ai_enriched'    => $enrichedCount,
            'elapsed_ms'     => $elapsedMs,
            'index'          => $index,
        ];
    }

    /**
     * Sincroniza un único documento en el índice compilado (al subir, actualizar versión, editar o reactivar).
     *
     * @return array<string, mixed>
     */
    public function syncDocumentById(int $docId, bool $useAiEnrichment = true): array
    {
        $index = $this->getCompiledIndex(false);
        $cx = new \Conexion();
        $conn = $cx->conectar('central');
        if (!$conn) {
            return ['status' => 'error', 'message' => 'Sin conexión a central'];
        }

        $sql = "SELECT p.id, p.tipo, p.titulo, p.descripcion, p.sector_id,
                       s.nombre AS sector_nombre, p.archivo_nombre, p.ruta_archivo,
                       p.tags, p.version, p.fecha_creacion, p.fecha_actualizacion, p.creado_por, p.estado
                  FROM Politicas_Procedimientos p WITH (NOLOCK)
                  LEFT JOIN Sectores s WITH (NOLOCK) ON p.sector_id = s.id
                 WHERE p.id = ?";
        $stmt = sqlsrv_query($conn, $sql, [$docId]);
        $row = $stmt ? sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) : null;

        if (!$row || ($row['estado'] ?? '') !== 'activo') {
            unset($index['documents'][(string)$docId]);
        } else {
            $compiled = $this->buildCompiledDocEntry($conn, $row, $useAiEnrichment, null);
            unset($compiled['ai_enriched_now']);
            $index['documents'][(string)$docId] = $compiled;
        }

        $index['glossary'] = $this->fetchGlossaryFromConn($conn);
        sqlsrv_close($conn);

        $index['compiled_at'] = date('c');
        $index['document_count'] = count($index['documents']);
        $index['glossary_count'] = count($index['glossary']);
        $secCount = 0;
        foreach ($index['documents'] as $d) {
            $secCount += count($d['sections'] ?? []);
        }
        $index['section_count'] = $secCount;

        $this->saveCompiledIndex($index);
        (new DocuGestCacheManager())->invalidateAll();

        return ['status' => 'success', 'doc_id' => $docId];
    }

    public function removeDocumentFromIndex(int $docId): void
    {
        $index = $this->getCompiledIndex(false);
        if (isset($index['documents'][(string)$docId])) {
            unset($index['documents'][(string)$docId]);
            $index['compiled_at'] = date('c');
            $index['document_count'] = count($index['documents']);
            $secCount = 0;
            foreach ($index['documents'] as $d) {
                $secCount += count($d['sections'] ?? []);
            }
            $index['section_count'] = $secCount;
            $this->saveCompiledIndex($index);
        }
        (new DocuGestCacheManager())->invalidateAll();
    }

    public function syncGlossaryOnly(): void
    {
        $index = $this->getCompiledIndex(false);
        $cx = new \Conexion();
        $conn = $cx->conectar('central');
        if ($conn) {
            $index['glossary'] = $this->fetchGlossaryFromConn($conn);
            $index['glossary_count'] = count($index['glossary']);
            $index['compiled_at'] = date('c');
            sqlsrv_close($conn);
            $this->saveCompiledIndex($index);
        }
        (new DocuGestCacheManager())->invalidateAll();
    }

    /**
     * Búsqueda híbrida (0 tokens) para el motor agéntico:
     * Combina Título + Tags + Sector + Glosario + Secciones BM25 + Sinónimos XL.
     *
     * @return array<string, mixed>
     */
    public function searchHybrid(
        string $query,
        ?string $userSector = null,
        ?string $tipoFilter = null,
        int $topDocs = 4,
        int $topSections = 6
    ): array {
        $index = $this->getCompiledIndex(false);
        $docs = $index['documents'] ?? [];
        $glossary = $index['glossary'] ?? [];

        $tokens = $this->tokenizeAndExpand($query);
        $normQuery = $this->normalizeText($query);
        $normUserSector = $this->normalizeText((string)$userSector);

        $docScores = [];
        $sectionCandidates = [];

        foreach ($docs as $docIdStr => $doc) {
            if ($tipoFilter !== null && $tipoFilter !== '' && ($doc['tipo'] ?? '') !== $tipoFilter) {
                continue;
            }

            $titleNorm = $this->normalizeText((string)($doc['titulo'] ?? ''));
            $tagsNorm = $this->normalizeText((string)($doc['tags'] ?? ''));
            $sectorNorm = $this->normalizeText((string)($doc['sector_nombre'] ?? ''));
            $summaryNorm = $this->normalizeText((string)($doc['resumen'] ?? ''));

            $docScore = 0.0;

            // Bonus si la frase exacta aparece en el título o tags
            if ($normQuery !== '' && mb_strlen($normQuery, 'UTF-8') >= 4 && str_contains($titleNorm, $normQuery)) {
                $docScore += 40.0;
            }

            // Bonus leve si coincide con el sector del usuario autenticado (Gestionusuarios)
            if ($normUserSector !== '' && $sectorNorm !== '' && (str_contains($sectorNorm, $normUserSector) || str_contains($normUserSector, $sectorNorm))) {
                $docScore += 3.5;
            }

            foreach ($tokens as $tok => $weight) {
                if (str_contains($titleNorm, $tok)) {
                    $docScore += 18.0 * $weight;
                }
                if (str_contains($tagsNorm, $tok)) {
                    $docScore += 11.0 * $weight;
                }
                if (str_contains($sectorNorm, $tok)) {
                    $docScore += 8.0 * $weight;
                }
                if (str_contains($summaryNorm, $tok)) {
                    $docScore += 6.0 * $weight;
                }
            }

            $bestSecScore = 0.0;
            foreach (($doc['sections'] ?? []) as $secIdx => $sec) {
                $secTitleNorm = $this->normalizeText((string)($sec['titulo_seccion'] ?? ''));
                $secBodyNorm = $this->normalizeText((string)($sec['contenido'] ?? ''));
                $secScore = 0.0;
                $hits = 0;

                foreach ($tokens as $tok => $weight) {
                    if (str_contains($secTitleNorm, $tok)) {
                        $secScore += 12.0 * $weight;
                        $hits++;
                    }
                    $cnt = substr_count($secBodyNorm, $tok);
                    if ($cnt > 0) {
                        // Saturación logarítmica estilo BM25 para evitar sesgo por repetición excesiva
                        $secScore += (3.5 + min(4, log(1 + $cnt) * 2.5)) * $weight;
                        $hits++;
                    }
                }

                // Bonus por cobertura de múltiples términos distintos de la consulta en la misma sección
                if ($hits >= 2) {
                    $secScore += $hits * 4.0;
                }

                if ($secScore > $bestSecScore) {
                    $bestSecScore = $secScore;
                }

                $combinedSecScore = $secScore + ($docScore * 0.65);
                if ($combinedSecScore > 4.0) {
                    $sectionCandidates[] = [
                        'doc_id'         => (int)$doc['id'],
                        'doc_titulo'     => (string)$doc['titulo'],
                        'doc_tipo'       => (string)$doc['tipo'],
                        'sector_nombre'  => (string)$doc['sector_nombre'],
                        'version'        => (string)$doc['version'],
                        'section_index'  => (int)$secIdx,
                        'titulo_seccion' => (string)($sec['titulo_seccion'] ?? 'Sección'),
                        'contenido'      => (string)($sec['contenido'] ?? ''),
                        'score'          => round($combinedSecScore, 2),
                    ];
                }
            }

            $totalDocScore = $docScore + $bestSecScore;
            if ($totalDocScore > 3.5) {
                $docScores[$docIdStr] = [
                    'id'            => (int)$doc['id'],
                    'titulo'        => (string)$doc['titulo'],
                    'tipo'          => (string)$doc['tipo'],
                    'sector_nombre' => (string)$doc['sector_nombre'],
                    'version'       => (string)$doc['version'],
                    'tags'          => (string)($doc['tags'] ?? ''),
                    'resumen'       => (string)($doc['resumen'] ?? ''),
                    'score'         => round($totalDocScore, 2),
                ];
            }
        }

        usort($docScores, static fn($a, $b) => $b['score'] <=> $a['score']);
        usort($sectionCandidates, static fn($a, $b) => $b['score'] <=> $a['score']);

        $topDocList = array_slice($docScores, 0, $topDocs);
        $topSectionList = array_slice($sectionCandidates, 0, $topSections);

        // Buscar términos del Glosario corporativo relevantes para la pregunta
        $matchedGlossary = $this->matchGlossary($query, $tokens, $glossary, 6);

        return [
            'docs'     => $topDocList,
            'sections' => $topSectionList,
            'glossary' => $matchedGlossary,
        ];
    }

    /**
     * Búsqueda instantánea (< 5ms) para la barra superior de DocuGest (Controller/buscar_documentos.php).
     * Busca en Título, Tags y Contenido completo extraído del PDF.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchForTopBar(string $searchTerm): array
    {
        $searchTerm = trim($searchTerm);
        if (mb_strlen($searchTerm, 'UTF-8') < 2) {
            return [];
        }

        $index = $this->getCompiledIndex(false);
        $docs = $index['documents'] ?? [];

        $termLower = mb_strtolower($searchTerm, 'UTF-8');
        $termNorm = $this->normalizeText($searchTerm);
        $resultados = [];

        foreach ($docs as $doc) {
            $coincidencias = [];
            $puntuacion = 0;

            $titulo = (string)($doc['titulo'] ?? '');
            $tituloLower = mb_strtolower($titulo, 'UTF-8');
            if (mb_strpos($tituloLower, $termLower) !== false || str_contains($this->normalizeText($titulo), $termNorm)) {
                $tituloResaltado = $this->highlightTerm($titulo, $searchTerm);
                $coincidencias[] = [
                    'tipo'  => 'titulo',
                    'texto' => $tituloResaltado,
                ];
                $puntuacion += 30;
            }

            // Coincidencia en tags
            $tagsStr = trim((string)($doc['tags'] ?? ''));
            if ($tagsStr !== '') {
                $tags = array_map('trim', explode(',', $tagsStr));
                $hayTag = false;
                $tagsHtml = [];
                foreach ($tags as $tag) {
                    if ($tag === '') {
                        continue;
                    }
                    if (mb_stripos($tag, $searchTerm, 0, 'UTF-8') !== false || str_contains($this->normalizeText($tag), $termNorm)) {
                        $tagsHtml[] = $this->highlightTerm($tag, $searchTerm);
                        $hayTag = true;
                        $puntuacion += 12;
                    } else {
                        $tagsHtml[] = htmlspecialchars($tag, ENT_QUOTES, 'UTF-8');
                    }
                }
                if ($hayTag) {
                    $coincidencias[] = [
                        'tipo'  => 'tag',
                        'texto' => 'Tags: ' . implode(', ', $tagsHtml),
                    ];
                }
            }

            // Coincidencia dentro del contenido completo del PDF (antes deshabilitado en p&p!)
            $fullText = (string)($doc['full_text'] ?? '');
            if ($fullText !== '') {
                $fragmentos = $this->extractHighlightedFragments($fullText, $searchTerm, 2);
                foreach ($fragmentos as $frag) {
                    $coincidencias[] = [
                        'tipo'  => 'contenido',
                        'texto' => $frag,
                    ];
                    $puntuacion += 8;
                }
            }

            if (!empty($coincidencias)) {
                $resultados[] = [
                    'id'            => (int)$doc['id'],
                    'titulo'        => $titulo,
                    'tipo'          => (string)($doc['tipo'] ?? 'procedimiento'),
                    'sector'        => (string)($doc['sector_nombre'] ?? ''),
                    'archivo'       => (string)($doc['archivo_nombre'] ?? ''),
                    'coincidencias' => $coincidencias,
                    'puntuacion'    => $puntuacion,
                ];
            }
        }

        usort($resultados, static fn($a, $b) => $b['puntuacion'] <=> $a['puntuacion']);
        return $resultados;
    }

    /**
     * Genera el Catálogo Maestro Compacto (~300-500 tokens) para inyectar al agente.
     */
    public function buildCompactCatalogPrompt(): string
    {
        $index = $this->getCompiledIndex(false);
        $docs = $index['documents'] ?? [];
        if (empty($docs)) {
            return "CATÁLOGO DOCUMENTAL: No hay documentos activos registrados.";
        }

        $lines = ["CATÁLOGO MAESTRO DE DOCUMENTOS VIGENTES (" . count($docs) . " activos):"];
        foreach ($docs as $doc) {
            $id = (int)$doc['id'];
            $tipo = strtoupper((string)($doc['tipo'] ?? 'PROC'));
            $titulo = trim((string)($doc['titulo'] ?? ''));
            $sector = trim((string)($doc['sector_nombre'] ?? ''));
            $ver = trim((string)($doc['version'] ?? '1.0'));
            $resumen = trim((string)($doc['resumen'] ?? ''));
            $shortSum = $resumen !== '' ? ' | ' . mb_substr($resumen, 0, 120, 'UTF-8') : '';
            $lines[] = "- [ID {$id}] ({$tipo} · Sector: {$sector} · v{$ver}) {$titulo}{$shortSum}";
        }

        return implode("\n", $lines);
    }

    /**
     * Obtiene el contenido completo o una sección puntual de un documento (para Function Calling).
     *
     * @return array<string, mixed>
     */
    public function readDocumentStructured(int $docId, ?string $sectionFilter = null): array
    {
        $index = $this->getCompiledIndex(false);
        $doc = $index['documents'][(string)$docId] ?? null;
        if (!is_array($doc)) {
            return ['status' => 'not_found', 'message' => "Documento ID {$docId} no encontrado en el catálogo activo."];
        }

        $sections = $doc['sections'] ?? [];
        if ($sectionFilter !== null && trim($sectionFilter) !== '') {
            $normFilter = $this->normalizeText($sectionFilter);
            $filtered = [];
            foreach ($sections as $s) {
                $tNorm = $this->normalizeText((string)($s['titulo_seccion'] ?? ''));
                $bNorm = $this->normalizeText((string)($s['contenido'] ?? ''));
                if (str_contains($tNorm, $normFilter) || str_contains($bNorm, $normFilter)) {
                    $filtered[] = $s;
                }
            }
            if (!empty($filtered)) {
                $sections = $filtered;
            }
        }

        // Compactar secciones para no exceder ~14.000 chars en tool response
        $outSections = [];
        $accLen = 0;
        foreach ($sections as $s) {
            $chunk = $s['contenido'] ?? '';
            if ($accLen + mb_strlen($chunk, 'UTF-8') > 14000) {
                break;
            }
            $outSections[] = [
                'titulo_seccion' => $s['titulo_seccion'] ?? '',
                'contenido'      => $chunk,
            ];
            $accLen += mb_strlen($chunk, 'UTF-8');
        }

        return [
            'status'        => 'ok',
            'id'            => (int)$doc['id'],
            'titulo'        => $doc['titulo'],
            'tipo'          => $doc['tipo'],
            'sector_nombre' => $doc['sector_nombre'],
            'version'       => $doc['version'],
            'tags'          => $doc['tags'],
            'resumen'       => $doc['resumen'],
            'total_secciones' => count($doc['sections'] ?? []),
            'secciones'     => $outSections,
        ];
    }

    /**
     * @param resource                    $conn
     * @param array<string, mixed>        $row
     * @param array<string, mixed>|null   $prevDoc
     * @return array<string, mixed>
     */
    private function buildCompiledDocEntry($conn, array $row, bool $useAiEnrichment, ?array $prevDoc): array
    {
        $docId = (int)$row['id'];
        $pdfPath = $this->resolvePdfPath($row);
        $fileHash = ($pdfPath !== null && is_file($pdfPath)) ? (string)@md5_file($pdfPath) : '';

        $rawText = ($pdfPath !== null) ? DocuGestPdfParser::extractText($pdfPath) : '';
        $sections = DocuGestPdfParser::splitIntoSemanticSections($rawText, (string)$row['titulo']);
        $resumen = trim((string)($row['descripcion'] ?? ''));
        $tagsStr = trim((string)($row['tags'] ?? ''));
        $aiEnrichedNow = false;

        // Reutilizar secciones IA previas si el PDF no cambió de hash, tiene contenido/secciones y ya fue estructurado por IA
        $canReusePrev = is_array($prevDoc)
            && $fileHash !== ''
            && ($prevDoc['file_hash'] ?? '') === $fileHash
            && !empty($prevDoc['sections'])
            && !empty($prevDoc['ai_structured'])
            && $tagsStr !== '';

        if ($canReusePrev) {
            if (!empty($prevDoc['resumen']) && $resumen === '') {
                $resumen = (string)$prevDoc['resumen'];
            }
            if (!empty($prevDoc['ai_structured'])) {
                $sections = $prevDoc['sections'];
            }
        } elseif ($useAiEnrichment && $pdfPath !== null) {
            try {
                $tIngest0 = microtime(true);
                $client = new DocuGestGeminiClient();
                $ai = $client->analyzePdfDocument($pdfPath, $rawText, $row);
                $ingestMs = round((microtime(true) - $tIngest0) * 1000, 1);
                $ingestUsage = is_array($ai['usage'] ?? null) ? $ai['usage'] : [];
                $actorUser = (string)($_SESSION['docugest_user_profile']['username'] ?? $_SESSION['username'] ?? ($row['creado_por'] ?? 'Sistema'));
                $actorName = (string)($_SESSION['docugest_user_profile']['nombre_completo'] ?? $actorUser);
                $actorRol  = (string)($_SESSION['docugest_user_profile']['rol'] ?? '');
                DocuGestUsageTracker::record([
                    'username'        => $actorUser,
                    'nombre_completo' => $actorName,
                    'sector_nombre'   => trim((string)($row['sector_nombre'] ?? '')),
                    'rol'             => $actorRol,
                    'action'          => mb_strlen(trim($rawText), 'UTF-8') < 350 ? 'ingestion_ocr' : 'ingestion_tags',
                    'model'           => (string)($ai['model'] ?? 'gemini-flash-lite-latest'),
                    'key_role'        => (string)($ai['key_role'] ?? 'primary'),
                    'cached'          => false,
                    'prompt_tokens'   => (int)($ingestUsage['prompt_tokens'] ?? 0),
                    'output_tokens'   => (int)($ingestUsage['output_tokens'] ?? 0),
                    'total_tokens'    => (int)($ingestUsage['total_tokens'] ?? 0),
                    'elapsed_ms'      => $ingestMs,
                    'preview'         => 'Doc #' . $docId . ': ' . trim((string)($row['titulo'] ?? '')),
                ]);
                if (($ai['status'] ?? '') === 'ok') {
                    $aiEnrichedNow = true;
                    if (!empty($ai['resumen_ejecutivo'])) {
                        $resumen = (string)$ai['resumen_ejecutivo'];
                    }
                    if (!empty($ai['secciones']) && is_array($ai['secciones'])) {
                        $aiSecs = [];
                        foreach ($ai['secciones'] as $s) {
                            $st = trim((string)($s['titulo_seccion'] ?? 'Sección'));
                            $sc = trim((string)($s['contenido'] ?? ''));
                            if ($sc !== '') {
                                $aiSecs[] = [
                                    'titulo_seccion' => $st,
                                    'contenido'      => $sc,
                                    'tokens_aprox'   => (int)ceil(mb_strlen($sc, 'UTF-8') / 4),
                                ];
                            }
                        }
                        if (!empty($aiSecs)) {
                            $sections = $aiSecs;
                        }
                    }

                    // Si el documento no tenía tags o tenía muy pocos, fusionar y actualizar BD
                    if (!empty($ai['tags']) && is_array($ai['tags'])) {
                        $mergedTags = $this->mergeTags($tagsStr, $ai['tags']);
                        if ($mergedTags !== '' && $mergedTags !== $tagsStr) {
                            $tagsStr = $mergedTags;
                            @sqlsrv_query(
                                $conn,
                                "UPDATE Politicas_Procedimientos SET tags = ? WHERE id = ?",
                                [$tagsStr, $docId]
                            );
                        }
                    }

                    // Insertar nuevos términos en Glosario si no existen
                    if (!empty($ai['glosario']) && is_array($ai['glosario'])) {
                        $this->upsertGlossaryTerms($conn, $ai['glosario'], (int)$row['sector_id']);
                    }
                }
            } catch (\Throwable $e) {
                error_log("DocuGest AI enrichment doc {$docId}: " . $e->getMessage());
            }
        }

        if ($resumen === '' && !empty($sections)) {
            $resumen = mb_substr(preg_replace('/\s+/u', ' ', (string)$sections[0]['contenido']) ?? '', 0, 220, 'UTF-8');
        }

        // Si el texto extraído localmente estaba vacío pero la IA devolvió secciones limpias, construir full_text desde las secciones
        if ($rawText === '' && !empty($sections)) {
            $parts = [];
            foreach ($sections as $s) {
                $parts[] = ($s['titulo_seccion'] ?? '') . "\n" . ($s['contenido'] ?? '');
            }
            $rawText = implode("\n\n", $parts);
        }

        return [
            'id'                  => $docId,
            'tipo'                => (string)($row['tipo'] ?? 'procedimiento'),
            'titulo'              => trim((string)($row['titulo'] ?? '')),
            'descripcion'         => trim((string)($row['descripcion'] ?? '')),
            'resumen'             => $resumen,
            'sector_id'           => (int)($row['sector_id'] ?? 0),
            'sector_nombre'       => trim((string)($row['sector_nombre'] ?? '')),
            'archivo_nombre'      => trim((string)($row['archivo_nombre'] ?? '')),
            'version'             => trim((string)($row['version'] ?? '1.0')) ?: '1.0',
            'tags'                => $tagsStr,
            'creado_por'          => trim((string)($row['creado_por'] ?? '')),
            'fecha_actualizacion' => $this->formatSqlDate($row['fecha_actualizacion'] ?? $row['fecha_creacion'] ?? null),
            'file_hash'           => $fileHash,
            'ai_structured'       => $aiEnrichedNow || !empty($prevDoc['ai_structured']),
            'ai_enriched_now'     => $aiEnrichedNow,
            'full_text'           => $rawText,
            'sections'            => $sections,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function resolvePdfPath(array $row): ?string
    {
        $candidates = [];
        if (!empty($row['archivo_nombre'])) {
            $candidates[] = $this->docsDir . basename((string)$row['archivo_nombre']);
        }
        if (!empty($row['ruta_archivo'])) {
            $candidates[] = (string)$row['ruta_archivo'];
        }
        if (!empty($row['archivo_nombre'])) {
            $candidates[] = $this->fallbackDocsDir . basename((string)$row['archivo_nombre']);
        }
        foreach ($candidates as $path) {
            if ($path !== '' && is_file($path) && is_readable($path)) {
                return $path;
            }
        }
        return null;
    }

    /**
     * @param resource $conn
     * @return array<int, array<string, mixed>>
     */
    private function fetchGlossaryFromConn($conn): array
    {
        $sql = "SELECT g.id, g.termino, g.definicion, g.sector_id, s.nombre AS sector_nombre
                  FROM Glosario g WITH (NOLOCK)
                  LEFT JOIN Sectores s WITH (NOLOCK) ON g.sector_id = s.id
                 ORDER BY g.termino ASC";
        $stmt = @sqlsrv_query($conn, $sql);
        if (!$stmt) {
            return [];
        }
        $list = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $list[] = [
                'id'            => (int)$row['id'],
                'termino'       => trim((string)$row['termino']),
                'definicion'    => trim((string)$row['definicion']),
                'sector_id'     => $row['sector_id'] !== null ? (int)$row['sector_id'] : null,
                'sector_nombre' => trim((string)($row['sector_nombre'] ?? '')),
            ];
        }
        return $list;
    }

    /**
     * @param resource                $conn
     * @param array<int, mixed>       $terms
     */
    private function upsertGlossaryTerms($conn, array $terms, int $sectorId): void
    {
        $inserted = 0;
        foreach ($terms as $item) {
            if (!is_array($item) || $inserted >= 5) {
                continue;
            }
            $term = trim((string)($item['termino'] ?? ''));
            $def  = trim((string)($item['definicion'] ?? ''));
            if ($term === '' || $def === '' || mb_strlen($term, 'UTF-8') > 120) {
                continue;
            }

            $chk = @sqlsrv_query(
                $conn,
                "SELECT COUNT(*) AS cnt FROM Glosario WHERE LOWER(LTRIM(RTRIM(termino))) = LOWER(?)",
                [$term]
            );
            $rowChk = $chk ? sqlsrv_fetch_array($chk, SQLSRV_FETCH_ASSOC) : null;
            if ($rowChk && (int)$rowChk['cnt'] === 0) {
                @sqlsrv_query(
                    $conn,
                    "INSERT INTO Glosario (termino, definicion, sector_id, fecha_creacion) VALUES (?, ?, ?, GETDATE())",
                    [$term, $def, $sectorId > 0 ? $sectorId : null]
                );
                $inserted++;
            }
        }
    }

    /**
     * @param string[] $aiTags
     */
    private function mergeTags(string $existingTags, array $aiTags): string
    {
        $existing = array_filter(array_map('trim', explode(',', $existingTags)));
        $seen = [];
        $final = [];

        foreach ($existing as $t) {
            $k = mb_strtolower($t, 'UTF-8');
            if (!isset($seen[$k])) {
                $seen[$k] = true;
                $final[] = $t;
            }
        }

        // Si el usuario ya cargó al menos 4 tags, solo completamos hasta 10; si estaba vacío, cargamos las de IA
        foreach ($aiTags as $t) {
            $clean = trim((string)$t);
            if ($clean === '') {
                continue;
            }
            $k = mb_strtolower($clean, 'UTF-8');
            if (!isset($seen[$k]) && count($final) < 12) {
                $seen[$k] = true;
                $final[] = $clean;
            }
        }

        return implode(', ', $final);
    }

    /**
     * @param array<string, float>              $tokens
     * @param array<int, array<string, mixed>>  $glossary
     * @return array<int, array<string, mixed>>
     */
    private function matchGlossary(string $query, array $tokens, array $glossary, int $limit = 6): array
    {
        $normQ = $this->normalizeText($query);
        $scored = [];

        foreach ($glossary as $g) {
            $tNorm = $this->normalizeText((string)($g['termino'] ?? ''));
            $dNorm = $this->normalizeText((string)($g['definicion'] ?? ''));
            $score = 0.0;

            if ($tNorm !== '' && str_contains($normQ, $tNorm)) {
                $score += 20.0;
            }
            foreach ($tokens as $tok => $w) {
                if (str_contains($tNorm, $tok)) {
                    $score += 8.0 * $w;
                }
                if (str_contains($dNorm, $tok)) {
                    $score += 3.0 * $w;
                }
            }

            if ($score > 3.5) {
                $g['score'] = $score;
                $scored[] = $g;
            }
        }

        usort($scored, static fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($scored, 0, $limit);
    }

    /**
     * Tokeniza la consulta y agrega sinónimos del dominio Extra Large con menor peso.
     *
     * @return array<string, float>
     */
    private function tokenizeAndExpand(string $text): array
    {
        $norm = $this->normalizeText($text);
        $rawWords = preg_split('/\s+/u', $norm) ?: [];
        $weights = [];

        foreach ($rawWords as $w) {
            $w = trim($w);
            if ($w === '' || mb_strlen($w, 'UTF-8') < 2 || in_array($w, self::STOPWORDS, true)) {
                continue;
            }
            $weights[$w] = max($weights[$w] ?? 0.0, 1.0);

            // Raíz simple plural -> singular
            if (str_ends_with($w, 'es') && mb_strlen($w, 'UTF-8') > 4) {
                $stem = mb_substr($w, 0, -2, 'UTF-8');
                $weights[$stem] = max($weights[$stem] ?? 0.0, 0.75);
            } elseif (str_ends_with($w, 's') && mb_strlen($w, 'UTF-8') > 3) {
                $stem = mb_substr($w, 0, -1, 'UTF-8');
                $weights[$stem] = max($weights[$stem] ?? 0.0, 0.75);
            }

            foreach (self::XL_SYNONYMS as $key => $syns) {
                if ($w === $key || str_contains($w, $key)) {
                    foreach ($syns as $s) {
                        $sNorm = $this->normalizeText($s);
                        if ($sNorm !== '') {
                            $weights[$sNorm] = max($weights[$sNorm] ?? 0.0, 0.65);
                        }
                    }
                }
            }
        }

        return $weights;
    }

    public function normalizeText(string $text): string
    {
        $lower = mb_strtolower(trim($text), 'UTF-8');
        $noAccents = strtr($lower, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
        $clean = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $noAccents) ?? $noAccents;
        return preg_replace('/\s+/u', ' ', trim($clean)) ?? '';
    }

    private function highlightTerm(string $text, string $term): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $quoted = preg_quote(htmlspecialchars($term, ENT_QUOTES, 'UTF-8'), '/');
        return preg_replace('/(' . $quoted . ')/iu', '<strong>$1</strong>', $escaped) ?? $escaped;
    }

    /**
     * @return string[]
     */
    private function extractHighlightedFragments(string $text, string $searchTerm, int $maxFragments = 2): array
    {
        $fragments = [];
        $textNorm = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $offset = 0;
        $count = 0;
        $termLen = mb_strlen($searchTerm, 'UTF-8');

        while ($count < $maxFragments && ($pos = mb_stripos($textNorm, $searchTerm, $offset, 'UTF-8')) !== false) {
            $start = max(0, $pos - 90);
            $len = min(mb_strlen($textNorm, 'UTF-8') - $start, 190 + $termLen);
            $frag = trim(mb_substr($textNorm, $start, $len, 'UTF-8'));
            if ($start > 0) {
                $frag = '...' . $frag;
            }
            if ($start + $len < mb_strlen($textNorm, 'UTF-8')) {
                $frag .= '...';
            }
            $fragments[] = $this->highlightTerm($frag, $searchTerm);
            $offset = $pos + $termLen;
            $count++;
        }

        return $fragments;
    }

    /**
     * @param mixed $val
     */
    private function formatSqlDate($val): string
    {
        if ($val instanceof \DateTimeInterface) {
            return $val->format('d/m/Y');
        }
        if (is_string($val) && trim($val) !== '') {
            $ts = strtotime($val);
            return $ts ? date('d/m/Y', $ts) : '';
        }
        return '';
    }

    /**
     * @param array<string, mixed> $index
     */
    private function saveCompiledIndex(array $index): void
    {
        $dir = dirname($this->indexFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        self::$memoryIndex = $index;
        @file_put_contents(
            $this->indexFile,
            json_encode($index, JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }
}
