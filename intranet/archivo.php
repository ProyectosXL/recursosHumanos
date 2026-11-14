<?php
/**
 * Intranet XL - entrega de imágenes y PDF de comunicados
 * Único punto por el que se ven los archivos (están fuera de la carpeta pública).
 *
 *   archivo.php?id=N              imagen o PDF (el PDF se abre en el navegador)
 *   archivo.php?id=N&m=1          miniatura de una imagen
 *   archivo.php?id=N&descargar=1  PDF como descarga
 *
 * Acceso: sesión iniciada. Todos ven los archivos de comunicados publicados y vigentes; quien
 * publica comunicados ve también borradores, programados, vencidos y recién subidos.
 * Caché del navegador PRIVADO (ningún proxy guarda copias): los archivos exigen sesión.
 */
require_once __DIR__ . '/Class/bootstrap.php';
require_once __DIR__ . '/Class/IntranetArchivos.php';

Auth::exigir();

$id = (int)($_GET['id'] ?? 0);
$miniatura = !empty($_GET['m']);
$descargar = !empty($_GET['descargar']);

function intraNoEncontrado(): void
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: private, no-store');
    echo 'No encontramos el archivo.';
    exit;
}

$a = ($id > 0 && IntranetArchivos::disponible()) ? IntranetArchivos::archivo($id) : null;
if (!$a || !IntranetArchivos::puedeVer($a)) {
    intraNoEncontrado();
}

// La sesión ya no hace falta: se libera para no frenar otras pestañas mientras se envía
session_write_close();

$esPdf = $a['tipo'] === 'PDF';
$rel = (!$esPdf && $miniatura && !empty($a['ruta_miniatura'])) ? $a['ruta_miniatura'] : $a['ruta_relativa'];
$ruta = IntranetArchivos::rutaAbsoluta($rel);
if ($ruta === null) {
    error_log("intranet/archivo.php: falta en disco el archivo $id ($rel)");
    intraNoEncontrado();
}

$tamano = (int)filesize($ruta);
$modif = (int)filemtime($ruta);
$etag = '"' . $a['id'] . '-' . ($rel === $a['ruta_relativa'] ? 'o' : 'm') . '-' . $modif . '-' . $tamano . '"';

// session_start() ya mandó "no-cache": se reemplaza por un caché privado de 7 días
header_remove('Pragma');
header_remove('Expires');
header('Cache-Control: private, max-age=604800');
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modif) . ' GMT');

if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    exit;
}

header('Content-Type: ' . $a['mime']);
header('Content-Length: ' . $tamano);

if ($esPdf) {
    $nombre = trim((string)($a['nombre'] ?: pathinfo($a['nombre_original'], PATHINFO_FILENAME)));
    $nombre = preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]+/u', ' ', $nombre) ?: 'documento';
    $ascii = preg_replace('/[^A-Za-z0-9 ._-]+/', '_', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombre) ?: 'documento');
    header(sprintf('Content-Disposition: %s; filename="%s.pdf"; filename*=UTF-8\'\'%s.pdf',
        $descargar ? 'attachment' : 'inline', $ascii, rawurlencode($nombre)));
} else {
    header('Content-Disposition: inline');
    header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'");
}

readfile($ruta);
