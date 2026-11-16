<?php
/**
 * Búsqueda avanzada instantánea en DocuGest 2.0 (< 5 ms).
 * Busca en Título, Tags y Contenido Completo extraído de todos los PDFs activos
 * usando el índice compilado local de DocuGestKnowledgeEngine.
 */
require_once __DIR__ . '/../Class/AccesoDocuGest.php';
AccesoDocuGest::exigirLogin(AccesoDocuGest::JSON);

ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

$searchTerm = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
if (mb_strlen($searchTerm, 'UTF-8') < 2) {
    echo json_encode([
        'status'     => 'error',
        'message'    => 'El término de búsqueda debe tener al menos 2 caracteres',
        'resultados' => [],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require_once __DIR__ . '/../core/Engine/DocuGestKnowledgeEngine.php';
    $engine = \DocuGest\Engine\DocuGestKnowledgeEngine::getInstance();
    $resultados = $engine->searchForTopBar($searchTerm);

    echo json_encode([
        'status'     => 'success',
        'resultados' => $resultados,
        'total'      => count($resultados),
        'termino'    => $searchTerm,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Error en buscar_documentos (DOCUGEST): ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status'     => 'error',
        'message'    => 'Error al procesar la búsqueda',
        'resultados' => [],
    ], JSON_UNESCAPED_UNICODE);
}
