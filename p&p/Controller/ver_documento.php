<?php
/**
 * Entrega el PDF vigente de un documento para verlo en el navegador (inline).
 * - Exige login del hub.
 * - Solo documentos activos, salvo Admin.
 * - Nunca sirve versiones archivadas (documentos/versiones/).
 * El contador de vistas lo suma obtener_documento.php al abrir el visor.
 */
require_once __DIR__ . '/../Class/AccesoDocuGest.php';
AccesoDocuGest::exigirLogin();

require_once __DIR__ . '/../Class/Politica.php';

$documento_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($documento_id <= 0) {
    http_response_code(400);
    exit('Documento no especificado');
}

$politicaObj = new Politica();
$documento = $politicaObj->obtenerPorId($documento_id, false, !AccesoDocuGest::esAdmin());

if (!$documento) {
    http_response_code(404);
    exit('El documento no existe o fue dado de baja');
}

$ruta_archivo = $politicaObj->rutaArchivoVigente($documento);
if ($ruta_archivo === null) {
    error_log("ver_documento: archivo no encontrado para el documento $documento_id");
    http_response_code(404);
    exit('El archivo no existe en el servidor');
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

$nombre = str_replace(['"', "\r", "\n"], '', basename($documento['archivo_nombre']));
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $nombre . '"');
header('Content-Length: ' . filesize($ruta_archivo));
header('Cache-Control: private, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
readfile($ruta_archivo);
exit;
