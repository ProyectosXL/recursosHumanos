<?php
/**
 * Intranet XL - subida de un archivo de comunicado (imagen o PDF), de a uno
 * La llama la pantalla de carga apenas se elige un archivo (recursosHumanos/intranet/assets/intranet_carga.js).
 *
 * POST: csrf, lote (32 hex, el del formulario), tipo (IMAGEN|PDF), ocupados (cuántos de ese tipo
 *       tiene ya el formulario), archivo.
 * Responde JSON: {ok: true, archivo: {...}} o {ok: false, error: "motivo"}.
 * Acceso: sesión + permiso intranet.comunicados.publicar + CSRF de la pantalla de carga.
 */
require_once __DIR__ . '/Class/bootstrap.php';
require_once __DIR__ . '/Class/IntranetArchivos.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

function intraResponder(array $r, int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode($r, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!Auth::check()) {
    intraResponder(['ok' => false, 'error' => 'Tu sesión venció. Volvé a entrar.'], 401);
}
// Quien publica comunicados o quien administra plantillas (usa el mismo formulario)
if (!Intranet::puedePublicarComunicados() && !Intranet::puedeAdministrarPlantillas()) {
    intraResponder(['ok' => false, 'error' => 'No tenés permiso para subir archivos.'], 403);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    intraResponder(['ok' => false, 'error' => 'Método no permitido.'], 405);
}
// Si el envío supera post_max_size, PHP descarta todo (también el CSRF): avisar el motivo real
if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    intraResponder(['ok' => false, 'error' => 'El archivo pesa más de lo que acepta el servidor.'], 413);
}
if (empty($_SESSION['intra_csrf']) || !hash_equals((string)$_SESSION['intra_csrf'], (string)($_POST['csrf'] ?? ''))) {
    intraResponder(['ok' => false, 'error' => 'La sesión venció. Recargá la página y probá de nuevo.'], 403);
}

$usuario = (string)Auth::username();
session_write_close(); // las subidas en paralelo no se esperan entre sí

$lote = IntranetArchivos::loteValido($_POST['lote'] ?? '');
if ($lote === null) {
    intraResponder(['ok' => false, 'error' => 'Formulario no válido. Recargá la página.'], 400);
}

$r = IntranetArchivos::subir(
    $_FILES['archivo'] ?? [],
    strtoupper(trim((string)($_POST['tipo'] ?? ''))),
    $lote,
    max(0, (int)($_POST['ocupados'] ?? 0)),
    $usuario
);
intraResponder($r, $r['ok'] ? 200 : 422);
