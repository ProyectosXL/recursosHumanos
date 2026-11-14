<?php
/**
 * Intranet XL - vista previa de un comunicado, SIN GUARDAR NADA
 * La pide la ventana "Vista previa" de la carga (recursosHumanos/intranet/admin.php) con los datos del formulario.
 * Arma el comunicado igual que la pantalla real y lo devuelve con las MISMAS plantillas
 * (partials/comunicado_tarjeta.php y partials/comunicado_resumen.php), para que no puedan diferir.
 *
 * POST: los campos del formulario de comunicado (incluidos csrf, id, lote y archivos[...]).
 * Responde JSON: {ok, intranet: html, hub: html, aviso: html|null, faltan: [mensajes para publicar]}.
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
    intraResponder(['ok' => false, 'error' => 'No tenés permiso para ver la vista previa.'], 403);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    intraResponder(['ok' => false, 'error' => 'Método no permitido.'], 405);
}
if (empty($_SESSION['intra_csrf']) || !hash_equals((string)$_SESSION['intra_csrf'], (string)($_POST['csrf'] ?? ''))) {
    intraResponder(['ok' => false, 'error' => 'La sesión venció. Recargá la página y probá de nuevo.'], 403);
}

$usuario = (string)Auth::username();
session_write_close();

$id = (int)($_POST['id'] ?? 0) ?: null;
$lote = IntranetArchivos::loteValido($_POST['lote'] ?? '') ?? IntranetArchivos::loteNuevo();
$archivosOk = IntranetArchivos::disponible();

$esPlantilla = !empty($_POST['es_plantilla']) && Intranet::tienePlantillas();
[$archivos] = IntranetArchivos::delFormulario($_POST['archivos'] ?? [], $lote, $id, $usuario);
$cantImagenes = count(array_filter($archivos, fn($a) => $a['tipo'] === 'IMAGEN'));
// Se valida como para publicar, solo para avisar qué faltaría; la vista previa se muestra igual.
// Una plantilla se valida como plantilla (sin vigencia ni imagen obligatoria).
[$datos, $errores] = $esPlantilla
    ? IntranetAdmin::validarPlantilla($_POST, $cantImagenes, $archivosOk)
    : IntranetAdmin::validarComunicado($_POST, $cantImagenes, $archivosOk, 'PUBLICADO');

// Autor: el sector de quien lo cargó (si ya existe) o el de quien lo está cargando
$creador = $usuario;
if ($id && ($existente = IntranetAdmin::comunicado($id, null))) {
    $creador = (string)$existente['creado_por'];
}
$c = IntranetAdmin::paraMostrar($datos, $archivos, $id, IntranetAdmin::sectorDe($creador));

intraResponder([
    'ok'       => true,
    // Comentarios y reacciones, si están marcados: de ejemplo (en cero y sin poder usarse).
    // Los datos a completar [Nombre del local] se resaltan.
    'intranet' => Intranet::plantilla('comunicado_tarjeta', ['c' => $c, 't' => [
        'clases' => 'mb-0', 'interaccion' => 'completo', 'ejemplo' => true, 'resaltar' => true,
    ]]),
    'hub'      => Intranet::plantilla('comunicado_resumen', ['c' => $c, 'href' => '#', 'resaltar' => true]),
    'aviso'    => !empty($datos['aviso_emergente'])
        ? Intranet::plantilla('aviso_hub', ['avisos' => [$c], 'previa' => true])
        : null,
    'faltan'   => array_values(array_filter($errores, fn($k) => $k !== '_general', ARRAY_FILTER_USE_KEY)),
    'datos'    => IntranetAdmin::datosPendientes($datos),
]);
