<?php
/**
 * Estructura XL - rutas (ÚNICO lugar con URLs y carpetas de estructura)
 *
 * Por qué un solo archivo: Internos se mudó desde comercial/recepcion y las URLs ya cambiaron
 * una vez; si vuelven a cambiar (o se mueve la carpeta de fotos en el servidor) se toca sólo
 * esto. Las vistas y el JS nunca escriben una ruta a mano: el JS las recibe de PHP
 * (window.ESTRUCTURA, ver partials/config_js.php).
 *
 * Igual que intranet/Class/rutas.php: estructura vive en recursosHumanos pero usa la sesión,
 * Auth y ConexionApps del hub (sistemas/hub). Sin efectos: sólo define constantes.
 */

if (!defined('ESTRUCTURA_DIR')) {
    define('ESTRUCTURA_DIR', dirname(__DIR__));                 // .../recursosHumanos/estructura
    define('ESTRUCTURA_URL', '/recursosHumanos/estructura');

    // recursosHumanos/Class: conexión del repo (conectar('central') = donde está RO_T_INTERNOS_PERSONAL)
    define('ESTRUCTURA_RRHH_CLASS_DIR', dirname(__DIR__, 2) . '/Class');

    // Pantallas
    define('ESTRUCTURA_URL_GUIA', ESTRUCTURA_URL . '/guia/');
    define('ESTRUCTURA_URL_CONTACTOS', ESTRUCTURA_URL . '/contactos/');
    define('ESTRUCTURA_URL_INTERNOS', ESTRUCTURA_URL . '/internos/');
    define('ESTRUCTURA_URL_INTERNOS_ABM', ESTRUCTURA_URL . '/internos/listado.php');
    define('ESTRUCTURA_URL_ADMIN', ESTRUCTURA_URL . '/admin/');
    define('ESTRUCTURA_URL_ADMIN_AREA', ESTRUCTURA_URL . '/admin/area.php');

    // Endpoints AJAX
    define('ESTRUCTURA_URL_INTERNOS_AJAX', ESTRUCTURA_URL . '/internos/ajax.php');
    define('ESTRUCTURA_URL_AJAX_CONSULTA', ESTRUCTURA_URL . '/ajax/consulta.php');
    define('ESTRUCTURA_URL_AJAX_ADMIN', ESTRUCTURA_URL . '/ajax/admin.php');

    define('ESTRUCTURA_URL_ASSETS', ESTRUCTURA_URL . '/assets');

    // Fotos de Internos. La carpeta NO está en git (.gitignore): vive sólo en el servidor y
    // Ramiro la mueve desde comercial/recepcion/fotos. Mientras no esté, nadie tiene foto.
    define('ESTRUCTURA_FOTOS_DIR', ESTRUCTURA_DIR . '/internos/fotos');
    define('ESTRUCTURA_FOTOS_URL', ESTRUCTURA_URL . '/internos/fotos/');

    // Ícono de las pestañas: el mismo que usan la Intranet y DocuGest (recursosHumanos no tiene uno propio)
    define('ESTRUCTURA_URL_ICONO', '/sistemas/assets/css/icono.jpg');

    // "‹ Volver a XL Gestión" del menú lateral
    define('ESTRUCTURA_URL_XLGESTION', '/sistemas/index.php');

    if (!defined('XL_HUB_DIR')) {
        $estructuraRaiz = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
        if ($estructuraRaiz === '' || !is_dir($estructuraRaiz . '/sistemas/hub')) {
            // Ejecución sin servidor web (tests/run.php): htdocs está tres niveles arriba de estructura/Class
            $estructuraRaiz = dirname(__DIR__, 3);
        }
        define('XL_HUB_DIR', $estructuraRaiz . '/sistemas/hub');
        unset($estructuraRaiz);
    }
}
