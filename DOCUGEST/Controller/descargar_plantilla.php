<?php
/**
 * Descarga la plantilla vacía de procedimiento (cualquier usuario logueado).
 * - ?check=1  → JSON {status:'success', disponible: true|false} (lo usa el botón antes de descargar)
 * - sin check → entrega el .docx con su nombre
 */
require_once __DIR__ . '/../Class/AccesoDocuGest.php';

$soloVerificar = isset($_GET['check']);
AccesoDocuGest::exigirLogin($soloVerificar ? AccesoDocuGest::JSON : AccesoDocuGest::PAGINA);

const PLANTILLA_PROCEDIMIENTO = 'Plantilla_Procedimiento.docx';
$ruta = dirname(__DIR__) . '/plantillas/' . PLANTILLA_PROCEDIMIENTO;
$disponible = is_file($ruta) && is_readable($ruta);

if ($soloVerificar) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'success', 'disponible' => $disponible]);
    exit;
}

if (!$disponible) {
    header('Location: ../index.php?' . http_build_query([
        'mensaje' => 'warning',
        'texto'   => 'La plantilla todavía no está disponible',
    ]));
    exit;
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . PLANTILLA_PROCEDIMIENTO . '"');
header('Content-Length: ' . filesize($ruta));
header('Cache-Control: private, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
readfile($ruta);
exit;
