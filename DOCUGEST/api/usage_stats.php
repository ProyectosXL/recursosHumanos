<?php
/**
 * Endpoint de Auditoría y Consumo IA para usuarios con rol Control Total.
 * - Exige sesión activa + rol Control Total / Admin Total de Gestionusuarios.
 */
require_once __DIR__ . '/../Class/AccesoDocuGest.php';
AccesoDocuGest::exigirControlTotal(AccesoDocuGest::JSON);

require_once __DIR__ . '/../core/Engine/DocuGestUsageTracker.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$period = trim((string)($_GET['period'] ?? 'month'));
if (!in_array($period, ['today', '7d', 'month', 'all'], true)) {
    $period = 'month';
}

$from = isset($_GET['from']) ? trim((string)$_GET['from']) : null;
$to = isset($_GET['to']) ? trim((string)$_GET['to']) : null;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 80;

try {
    $stats = \DocuGest\Engine\DocuGestUsageTracker::getStats($period, $from, $to, $limit);
    echo json_encode([
        'status' => 'success',
        'data'   => $stats,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Error obteniendo métricas de consumo: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
