<?php
/**
 * Estructura XL - acciones de administración (requieren el permiso estructura.administrar)
 *
 * Toda acción pasa primero por Permisos::autorizarAdmin(): sin sesión del hub, sin permiso, sin
 * POST o sin CSRF responde error y no toca nada, aunque se la llame directo. La validación manda
 * acá: el JS de las pantallas sólo explica y bloquea botones.
 *
 *   guardarFicha              datos (JSON de la ficha)              → ok | errores por campo
 *   desactivarArea / reactivarArea   id
 *   moverArea                 id, direccion (subir | bajar)
 *   reactivarTema             id
 *   descartarBusqueda         normalizado
 *   previsualizarImportacion  filas (JSON), archivo                 → plan (sin guardar nada)
 *   confirmarImportacion      filas (JSON), archivo                 → vuelve a planificar y aplica
 *   plantilla                                                        → filas con los datos actuales
 */
require_once __DIR__ . '/../Class/bootstrap.php';
require_once __DIR__ . '/../Class/Permisos.php';
require_once __DIR__ . '/../Class/Directorio.php';

header('Content-Type: application/json; charset=utf-8');

function adminResponder($datos, $codigo = 200)
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

$accion = isset($_REQUEST['accion']) ? (string)$_REQUEST['accion'] : '';
$idParam = isset($_POST['id']) && ctype_digit((string)$_POST['id']) ? (int)$_POST['id'] : null;

$rechazo = Permisos::autorizarAdmin(
    $accion,
    Auth::check(),
    Permisos::puedeEditarArea($idParam),
    Permisos::csrfValido(isset($_POST['csrf']) ? $_POST['csrf'] : null),
    isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : ''
);
if ($rechazo !== null) {
    adminResponder(array('ok' => false, 'mensaje' => $rechazo[1]), $rechazo[0]);
}

$usuario = (string)Auth::username();

try {
    $dir = new Directorio();
    $estado = $dir->estado();
    if (!$estado['ok']) {
        adminResponder(array('ok' => false, 'mensaje' => $estado['sin_conexion']
            ? 'No se pudo leer la base de datos.'
            : 'Falta correr estructura/sql/02_directorio_tablas.sql.'), 503);
    }

    /** JSON de un parámetro POST, o error 400 */
    $json = function ($clave) {
        $v = isset($_POST[$clave]) ? json_decode((string)$_POST[$clave], true) : null;
        if (!is_array($v)) {
            adminResponder(array('ok' => false, 'mensaje' => 'Datos incompletos.'), 400);
        }
        return $v;
    };
    $sinMotivo = function ($motivo) {
        adminResponder($motivo === null ? array('ok' => true) : array('ok' => false, 'mensaje' => $motivo), $motivo === null ? 200 : 409);
    };

    switch ($accion) {
        case 'guardarFicha':
            $r = $dir->guardarFicha($json('datos'), $usuario);
            adminResponder($r, $r['ok'] ? 200 : 422);
            break;

        case 'desactivarArea':
        case 'reactivarArea':
            if ($idParam === null) {
                adminResponder(array('ok' => false, 'mensaje' => 'Falta el área.'), 400);
            }
            $sinMotivo($dir->cambiarEstadoArea($idParam, $accion === 'reactivarArea', $usuario));
            break;

        case 'moverArea':
            $direccion = isset($_POST['direccion']) ? (string)$_POST['direccion'] : '';
            if ($idParam === null || !in_array($direccion, array('subir', 'bajar'), true)) {
                adminResponder(array('ok' => false, 'mensaje' => 'Datos incompletos.'), 400);
            }
            $sinMotivo($dir->moverArea($idParam, $direccion, $usuario));
            break;

        case 'reactivarTema':
            if ($idParam === null) {
                adminResponder(array('ok' => false, 'mensaje' => 'Falta el tema.'), 400);
            }
            $sinMotivo($dir->reactivarTema($idParam, $usuario));
            break;

        case 'descartarBusqueda':
            $norm = isset($_POST['normalizado']) ? trim((string)$_POST['normalizado']) : '';
            if ($norm === '') {
                adminResponder(array('ok' => false, 'mensaje' => 'Falta la búsqueda.'), 400);
            }
            $dir->descartarBusqueda(mb_substr($norm, 0, 200, 'UTF-8'), $usuario);
            adminResponder(array('ok' => true));
            break;

        case 'previsualizarImportacion':
        case 'confirmarImportacion':
            require_once __DIR__ . '/../Class/Importacion.php';
            $filas = $json('filas');
            $archivo = isset($_POST['archivo']) ? trim((string)$_POST['archivo']) : '';
            // Siempre se planifica de nuevo contra el estado actual: no se confía en la vista previa
            $plan = Importacion::planificar($filas, $dir->areasCompletas(), $dir->personas());
            if ($accion === 'previsualizarImportacion' || $plan['errores']) {
                adminResponder(array('ok' => !$plan['errores'], 'plan' => $plan, 'confirmada' => false));
            }
            $resumen = $dir->aplicarImportacion($plan, $archivo !== '' ? $archivo : 'sin nombre', $usuario);
            adminResponder(array('ok' => true, 'plan' => $plan, 'confirmada' => true, 'resumen' => $resumen));
            break;

        case 'plantilla':
            require_once __DIR__ . '/../Class/Importacion.php';
            $activas = array_values(array_filter($dir->areas(true), function ($a) { return $a['activa']; }));
            adminResponder(array('ok' => true, 'filas' => Importacion::filasPlantilla($activas, $dir->personas())));
            break;
    }
} catch (Throwable $e) {
    error_log('estructura/ajax/admin.php (' . $accion . '): ' . $e->getMessage());
    adminResponder(array('ok' => false, 'mensaje' => 'No se pudo guardar. No se cambió nada; probá de nuevo y, si sigue, avisá a Innovación.'), 500);
}
