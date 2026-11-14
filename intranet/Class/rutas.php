<?php
/**
 * Intranet XL - rutas compartidas con el hub
 *
 * La Intranet vive en recursosHumanos/intranet, pero usa la sesión, los permisos (Auth),
 * la conexión (ConexionApps) y la barra superior del hub (sistemas/hub).
 * Sin efectos: solo define constantes. Lo cargan Intranet.php y Class/bootstrap.php.
 */

if (!defined('INTRANET_DIR')) {
    define('INTRANET_DIR', dirname(__DIR__));        // .../recursosHumanos/intranet
    define('INTRANET_URL', '/recursosHumanos/intranet');

    $intranetRaiz = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
    if ($intranetRaiz === '' || !is_dir($intranetRaiz . '/sistemas/hub')) {
        // Ejecución sin servidor web: htdocs es tres niveles arriba de intranet/Class
        $intranetRaiz = dirname(__DIR__, 3);
    }
    define('XL_HUB_DIR', $intranetRaiz . '/sistemas/hub');
    unset($intranetRaiz);
}
