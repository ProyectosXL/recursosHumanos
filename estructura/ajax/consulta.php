<?php
/**
 * Estructura XL - acciones públicas de consulta (sin login)
 *
 *   POST accion=registrar  texto, area   Registra una búsqueda de la Guía de Consultas.
 *   GET  accion=reglas                   Mapa de tildes y palabras vacías (tests/paridad.html).
 *
 * Registrar NUNCA le avisa nada al usuario: si falla, responde ok=false y el error va al log de
 * PHP; la Guía sigue andando igual. Como no pide login, se protege así:
 *   - sólo POST; el texto se corta a 200 caracteres;
 *   - la regla de cuándo registrar (Reglas::debeRegistrarBusqueda) se vuelve a aplicar acá contra
 *     la última búsqueda registrada en esta sesión;
 *   - tope de REGISTROS_POR_VENTANA registros por sesión cada VENTANA_SEGUNDOS, para que nadie
 *     llene la tabla con un script. Una persona escribiendo nunca llega.
 */
require_once __DIR__ . '/../Class/bootstrap.php';
require_once __DIR__ . '/../Class/Reglas.php';
require_once __DIR__ . '/../Class/Directorio.php';

const REGISTROS_POR_VENTANA = 30;
const VENTANA_SEGUNDOS = 300;

header('Content-Type: application/json; charset=utf-8');

$accion = isset($_REQUEST['accion']) ? (string)$_REQUEST['accion'] : '';

if ($accion === 'reglas') {
    echo json_encode(Reglas::configJs(), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($accion !== 'registrar' || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(400);
    echo json_encode(array('ok' => false));
    exit;
}

try {
    $texto = isset($_POST['texto']) ? (string)$_POST['texto'] : '';
    $idArea = (isset($_POST['area']) && ctype_digit((string)$_POST['area'])) ? (int)$_POST['area'] : null;

    $ultima = isset($_SESSION['estructura_ultima_busqueda']) ? $_SESSION['estructura_ultima_busqueda'] : '';
    if (!Reglas::debeRegistrarBusqueda($texto, $ultima)) {
        echo json_encode(array('ok' => true, 'registrada' => false));
        exit;
    }

    $ahora = time();
    $ventana = isset($_SESSION['estructura_registros']) ? $_SESSION['estructura_registros'] : array();
    $ventana = array_values(array_filter($ventana, function ($t) use ($ahora) { return $t > $ahora - VENTANA_SEGUNDOS; }));
    if (count($ventana) >= REGISTROS_POR_VENTANA) {
        error_log('estructura: tope de registros de búsqueda alcanzado en la sesión ' . session_id());
        echo json_encode(array('ok' => false));
        exit;
    }

    // Quién busca: la sesión de XL Gestión (o del hub) si existe. Sin consultar la base:
    // registrar tiene que ser liviano.
    $usuario = null;
    foreach (array('username', 'nodo_usuario_activo', 'usuario') as $k) {
        if (!empty($_SESSION[$k]) && is_string($_SESSION[$k])) {
            $usuario = $_SESSION[$k];
            break;
        }
    }
    if ($usuario === null && !empty($_SESSION['fp_auth_user']['username'])) {
        $usuario = (string)$_SESSION['fp_auth_user']['username'];
    }
    // XL Gestión guarda el COD_CLIENT del local como $_SESSION['codClient']
    $codClient = !empty($_SESSION['codClient']) ? trim((string)$_SESSION['codClient']) : null;

    $dir = new Directorio();
    $estado = $dir->estado();
    if (!$estado['ok']) {
        echo json_encode(array('ok' => false));
        exit;
    }
    $dir->registrarBusqueda($texto, $idArea, $usuario, $codClient);

    $ventana[] = $ahora;
    $_SESSION['estructura_registros'] = $ventana;
    $_SESSION['estructura_ultima_busqueda'] = Reglas::normalizarBusqueda($texto);
    echo json_encode(array('ok' => true, 'registrada' => true));
} catch (Throwable $e) {
    error_log('estructura: no se pudo registrar la búsqueda: ' . $e->getMessage());
    echo json_encode(array('ok' => false));
}
