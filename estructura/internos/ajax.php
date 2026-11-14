<?php
/**
 * Internos - acciones AJAX (migrado desde comercial/recepcion/ajax/interno_ajax.php).
 *
 * Permisos: el original no validaba nada en el servidor; cualquiera, sin login, podía guardar o
 * desactivar llamando a esta URL. Ahora cada acción pide el permiso de la tabla
 * Permisos::permisoAccionInternos() (la regla de siempre del ABM) y las que escriben piden CSRF.
 * buscar y getSectores siguen públicas: las usa la vista de Internos, que no pide login.
 *
 * Abre la sesión (antes no) porque la necesita para saber quién llama. Efecto a cuidar: el filtro
 * de "Direccion" de getSectores() depende de la sesión; acá se llama sin filtro para que el
 * selector del ABM siga trayendo todos los sectores, como siempre.
 */
require_once __DIR__ . '/../Class/bootstrap.php';
require_once __DIR__ . '/../Class/Interno.php';
require_once __DIR__ . '/../Class/Permisos.php';

header('Content-Type: application/json');

/** Corta con un error JSON (sin debug_info: no se le muestra la traza a quien no tiene permiso). */
function internosAjaxRechazar($codigo, $mensaje)
{
    http_response_code($codigo);
    echo json_encode(array('success' => false, 'message' => $mensaje));
    exit;
}

$action = isset($_POST['action']) ? (string)$_POST['action'] : '';

$requerido = Permisos::permisoAccionInternos($action, $_POST);
if ($requerido !== null) {
    if (!Auth::check()) {
        internosAjaxRechazar(401, 'Tu sesión terminó. Volvé a ingresar.');
    }
    if (!Permisos::alcanzaPermisoInternos($requerido, Permisos::abmInternos())) {
        internosAjaxRechazar(403, 'Tu usuario no tiene permiso para esta acción.');
    }
}
if (Permisos::accionInternosEscribe($action) && !Permisos::csrfValido(isset($_POST['csrf']) ? $_POST['csrf'] : null)) {
    internosAjaxRechazar(403, 'La página quedó vieja. Recargala y probá de nuevo.');
}

try {
    $interno = new Interno();

    switch($action) {
        case 'guardar':
            $datos = [
                'interno' => $_POST['interno'] ?? null,
                'nombre_apellido' => $_POST['nombre_apellido'] ?? '',
                'sector' => $_POST['sector'] ?? '',
                'mail' => $_POST['mail'] ?? '',
                'celular' => $_POST['celular'] ?? '',
                'modoEdicion' => $_POST['modoEdicion'] ?? '',
                'nombreApellidoOriginal' => $_POST['nombreApellidoOriginal'] ?? ''
            ];
            $response = $interno->guardarEmpleado($datos);
            break;

        case 'getSectores':
            $response = $interno->getSectores(false);
            break;

        case 'buscar':
            $busqueda = $_POST['busqueda'] ?? '';
            $sector = $_POST['sector'] ?? '';
            $response = $interno->buscarInternos($busqueda, $sector);
            break;

        case 'listarAbm':
            $response = $interno->listarParaAbm($_POST['busqueda'] ?? '', !empty($_POST['incluirInactivos']));
            break;

        case 'toggleStatus':
            $nombre_apellido = $_POST['nombre_apellido'] ?? null;
            $status = $_POST['status'] ?? null;

            if ($nombre_apellido === null || $status === null) {
                throw new Exception('Parámetros incompletos');
            }
            $response = $interno->toggleStatus($nombre_apellido, $status);
            break;

        case 'getEmpleado':
            $nombre_apellido = $_POST['nombre_apellido'] ?? null;
            if (!$nombre_apellido) {
                throw new Exception('Nombre y apellido requerido');
            }
            $response = $interno->getEmpleado($nombre_apellido);
            break;

        default:
            throw new Exception('Acción no válida');
    }

    echo json_encode($response);

} catch (Exception $e) {
    // El detalle (archivo, línea, traza) va al log, no a la respuesta: el original lo devolvía
    error_log("Error en internos/ajax.php: " . $e->getMessage());
    echo json_encode(array('success' => false, 'message' => $e->getMessage()));
}
