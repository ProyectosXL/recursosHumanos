<?php
/**
 * Intranet XL - comentarios y reacciones de un comunicado (sin recargar la página)
 * La llama recursosHumanos/intranet/assets/intranet_interaccion.js desde la intranet y desde el aviso al entrar al hub.
 *
 * POST: csrf, comunicado (id), accion y, según la acción:
 *   reaccionar  tipo (ME_GUSTA | APLAUSOS | ME_ENCANTA): marca, cambia o deshace
 *   comentar    texto (plano, hasta 500)
 *   borrar      comentario (id): solo el autor; baja lógica
 *   ocultar     comentario (id): solo responsables de carga (intranet.comunicados.publicar)
 *   mostrar     comentario (id): ídem
 *   quienes     lista de quiénes reaccionaron (solo lectura; responsables: también desde el listado de carga)
 * Responde JSON: {ok, ...} o {ok: false, error}. Con los contadores actualizados del comunicado.
 * Acceso: sesión (cualquier usuario logueado) + CSRF. El comunicado tiene que estar activo,
 * publicado, vigente hoy y con la opción habilitada.
 */
require_once __DIR__ . '/Class/bootstrap.php';
require_once __DIR__ . '/Class/IntranetInteraccion.php';

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
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    intraResponder(['ok' => false, 'error' => 'Método no permitido.'], 405);
}
if (!Intranet::csrfValido($_POST['csrf'] ?? '')) {
    intraResponder(['ok' => false, 'error' => 'La sesión venció. Recargá la página y probá de nuevo.'], 403);
}

$usuario = trim((string)Auth::username());
$moderar = Intranet::puedePublicarComunicados();
$accion = (string)($_POST['accion'] ?? '');
$comunicadoId = (int)($_POST['comunicado'] ?? 0);
$nombre = in_array($accion, ['reaccionar', 'comentar', 'ocultar'], true) ? Intranet::nombreUsuario() : '';
session_write_close(); // no hace falta escribir la sesión: los pedidos no se esperan entre sí

if ($usuario === '') {
    intraResponder(['ok' => false, 'error' => 'Tu sesión venció. Volvé a entrar.'], 401);
}

// Quiénes reaccionaron: solo lectura. Lo ve cualquiera en un comunicado abierto con reacciones;
// los responsables de carga, además, en cualquier comunicado (desde el listado de carga).
if ($accion === 'quienes') {
    $abierto = $comunicadoId ? IntranetInteraccion::comunicadoAbierto($comunicadoId) : null;
    $puedeVer = ($abierto && !empty($abierto['permite_reacciones']))
        || ($moderar && $comunicadoId && Intranet::tieneInteraccion() && IntranetInteraccion::existeComunicado($comunicadoId));
    if (!$puedeVer) {
        intraResponder(['ok' => false, 'error' => 'Este comunicado no tiene reacciones.'], 409);
    }
    $quienes = IntranetInteraccion::quienes($comunicadoId);
    if ($quienes === null) {
        intraResponder(['ok' => false, 'error' => 'No pudimos leer las reacciones. Probá de nuevo.'], 500);
    }
    intraResponder(['ok' => true, 'html' => Intranet::plantilla('comunicado_quienes', ['quienes' => $quienes])]);
}

$c = $comunicadoId ? IntranetInteraccion::comunicadoAbierto($comunicadoId) : null;
if (!$c) {
    intraResponder(['ok' => false, 'error' => 'Este comunicado ya no admite comentarios ni reacciones.'], 409);
}

/** Contadores actualizados del comunicado, para que la pantalla quede igual que la base. */
function intraResumen(int $id, string $usuario): array
{
    return IntranetInteraccion::resumen([$id], $usuario)[$id] ?? IntranetInteraccion::resumenVacio();
}

function intraComentarioHtml(array $k, bool $moderar, string $usuario): string
{
    return Intranet::plantilla('comunicado_comentario', ['k' => $k, 'moderar' => $moderar, 'yo' => $usuario]);
}

switch ($accion) {
    case 'reaccionar':
        if (empty($c['permite_reacciones'])) {
            intraResponder(['ok' => false, 'error' => 'Este comunicado no tiene reacciones.'], 409);
        }
        $tipo = strtoupper(trim((string)($_POST['tipo'] ?? '')));
        if (!isset(IntranetInteraccion::REACCIONES[$tipo])) {
            intraResponder(['ok' => false, 'error' => 'Reacción no válida.'], 422);
        }
        $r = IntranetInteraccion::reaccionar($comunicadoId, $usuario, $nombre, $tipo);
        if (!$r['ok']) {
            intraResponder(['ok' => false, 'error' => 'No pudimos registrar tu reacción. Probá de nuevo.'], 500);
        }
        intraResponder(['ok' => true, 'resumen' => intraResumen($comunicadoId, $usuario)]);

    case 'comentar':
        if (empty($c['permite_comentarios'])) {
            intraResponder(['ok' => false, 'error' => 'Este comunicado no tiene comentarios.'], 409);
        }
        $r = IntranetInteraccion::comentar($comunicadoId, $usuario, $nombre, $_POST['texto'] ?? '');
        if (!$r['ok']) {
            intraResponder(['ok' => false, 'error' => $r['error']], $r['codigo']);
        }
        intraResponder(['ok' => true, 'html' => intraComentarioHtml($r['comentario'], $moderar, $usuario),
                        'resumen' => intraResumen($comunicadoId, $usuario)]);

    case 'borrar':
    case 'ocultar':
    case 'mostrar':
        if (empty($c['permite_comentarios'])) {
            intraResponder(['ok' => false, 'error' => 'Este comunicado no tiene comentarios.'], 409);
        }
        $comentarioId = (int)($_POST['comentario'] ?? 0);
        if ($accion === 'borrar') {
            if (!IntranetInteraccion::borrar($comentarioId, $comunicadoId, $usuario)) {
                intraResponder(['ok' => false, 'error' => 'No pudimos borrar el comentario. Probá de nuevo.'], 409);
            }
            intraResponder(['ok' => true, 'resumen' => intraResumen($comunicadoId, $usuario)]);
        }
        if (!$moderar) {
            intraResponder(['ok' => false, 'error' => 'No tenés permiso para esa acción.'], 403);
        }
        if (!IntranetInteraccion::ocultar($comentarioId, $comunicadoId, $accion === 'ocultar', $usuario, $nombre)) {
            intraResponder(['ok' => false, 'error' => 'No pudimos guardar el cambio. Probá de nuevo.'], 409);
        }
        $k = IntranetInteraccion::comentario($comentarioId);
        intraResponder(['ok' => true, 'html' => $k ? intraComentarioHtml($k, $moderar, $usuario) : '',
                        'resumen' => intraResumen($comunicadoId, $usuario)]);

    default:
        intraResponder(['ok' => false, 'error' => 'Acción no válida.'], 400);
}
