<?php
// Acceso DocuGest: login nomás (lo llama el Asistente IA de cualquier usuario)
require_once __DIR__ . '/../Class/AccesoDocuGest.php';
AccesoDocuGest::exigirLogin(AccesoDocuGest::JSON);

/**
 * Empuja todos los documentos faltantes al HF Space vía /api/index-base64.
 * PHP lee los PDFs localmente y los envía como base64.
 * El HF Space no necesita acceder al servidor PHP (no depende de PHP_SERVER_URL).
 *
 * Modo fire-and-forget: retorna inmediatamente, la indexación continúa en background.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config_rag.php';

$config = RagConfig::getInstance();

if (!$config->isRagEnabled()) {
    echo json_encode(['success' => false, 'mensaje' => 'RAG deshabilitado']);
    exit;
}

// Verificar que el servicio esté online y tenga 0 chunks
$health_url = $config->getRagServiceUrl('/health');
$ch = curl_init($health_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$health_resp = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($http_code !== 200) {
    echo json_encode([
        'success' => false,
        'mensaje' => 'Servicio no disponible',
        'health_url' => $health_url,
        'http_code' => $http_code,
        'curl_error' => $curl_error
    ]);
    exit;
}

$health_data = json_decode($health_resp, true);
if (($health_data['total_chunks'] ?? 0) > 0) {
    echo json_encode([
        'success' => true,
        'ya_indexado' => true,
        'total_chunks' => $health_data['total_chunks'],
        'mensaje' => 'Ya indexado'
    ]);
    exit;
}

// Obtener lista de documentos desde PRODUCCION
$source_base_url = rtrim($config->getIndexSourceUrl(), '/');
$listar_url = $source_base_url . '/api/listar_documentos.php';

$ch = curl_init($listar_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
$listar_resp = curl_exec($ch);
$listar_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$listar_curl_error = curl_error($ch);
curl_close($ch);

if ($listar_http !== 200) {
    echo json_encode([
        'success' => false,
        'mensaje' => 'No se pudo obtener listado de documentos desde producción',
        'listar_url' => $listar_url,
        'http_code' => $listar_http,
        'curl_error' => $listar_curl_error
    ]);
    exit;
}

$listar_data = json_decode($listar_resp, true);
if (!$listar_data || !($listar_data['success'] ?? false) || !isset($listar_data['documentos'])) {
    echo json_encode([
        'success' => false,
        'mensaje' => 'Respuesta inválida al listar documentos desde producción',
        'listar_url' => $listar_url
    ]);
    exit;
}

$documentos = $listar_data['documentos'];

if (empty($documentos)) {
    echo json_encode(['success' => true, 'ya_indexado' => false, 'mensaje' => 'Sin documentos en BD', 'total' => 0]);
    exit;
}

$documentos_path = rtrim($config->getDocumentosPath(), '/\\');
$index_url = $config->getRagServiceUrl('/api/index-base64');
$total = count($documentos);

// ─── Responder inmediatamente al cliente JS ───────────────────────────────
// Content-Length + Connection: close hace que el browser resuelva el fetch
// apenas recibe los bytes, sin esperar que PHP termine de ejecutar.
$body = json_encode([
    'success'     => true,
    'ya_indexado' => false,
    'mensaje'     => "Iniciando indexación de $total documentos",
    'total'       => $total
]);
header('Content-Type: application/json');
header('Content-Length: ' . strlen($body));
header('Connection: close');

// Vaciar cualquier output buffer previo
while (ob_get_level() > 0) {
    ob_end_clean();
}
echo $body;
flush();

// fastcgi_finish_request() cierra la conexión inmediatamente en PHP-FPM
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// PHP sigue corriendo aunque el cliente haya cerrado la conexión
ignore_user_abort(true);
set_time_limit(600);

// Indexar cada documento enviando el PDF como base64
foreach ($documentos as $doc) {
    $doc_id = (int)($doc['id'] ?? 0);
    if ($doc_id <= 0) {
        error_log('trigger_refresh: documento sin id válido, se omite');
        continue;
    }

    // Intentar ruta absoluta guardada en BD, luego construir desde archivo_nombre
    $archivo = '';
    if (!empty($doc['ruta_archivo']) && file_exists($doc['ruta_archivo'])) {
        $archivo = $doc['ruta_archivo'];
    } elseif (!empty($doc['archivo_nombre'])) {
        $archivo = $documentos_path . '/' . $doc['archivo_nombre'];
    }

    // Fallback: si no existe archivo local, descargar PDF desde producción por ID
    $pdf_bytes = false;
    if (!empty($archivo) && file_exists($archivo)) {
        $pdf_bytes = file_get_contents($archivo);
        if ($pdf_bytes === false) {
            error_log("trigger_refresh: no se pudo leer archivo local: $archivo");
        }
    }

    if ($pdf_bytes === false) {
        $descargar_url = $source_base_url . '/api/descargar_pdf.php?id=' . urlencode((string)$doc_id);
        $ch = curl_init($descargar_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        $pdf_bytes = curl_exec($ch);
        $pdf_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $pdf_curl_error = curl_error($ch);
        curl_close($ch);

        if ($pdf_http !== 200 || $pdf_bytes === false || strlen($pdf_bytes) === 0) {
            error_log("trigger_refresh: no se pudo descargar PDF id=$doc_id desde producción (HTTP $pdf_http): $pdf_curl_error");
            continue;
        }
    }

    $tipo = $doc['tipo'] ?? 'politica';
    if (!in_array($tipo, ['politica', 'procedimiento'])) {
        $tipo = 'politica';
    }

    $payload = json_encode([
        'document_id'   => $doc_id,
        'titulo'        => $doc['titulo'] ?? ('Documento ' . $doc_id),
        'sector_nombre' => $doc['sector'] ?? ($doc['sector_nombre'] ?? 'General'),
        'tipo'          => $tipo,
        'contenido_base64' => base64_encode($pdf_bytes)
    ]);

    $ch = curl_init($index_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) {
        error_log("trigger_refresh: error indexando '{$doc['titulo']}' (HTTP $code): $resp");
    }

    // Pausa breve entre documentos para no saturar embeddings API
    usleep(300000); // 300ms
}
