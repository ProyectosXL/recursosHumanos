<?php
/**
 * Estructura XL - punto de entrada de las pantallas de recursosHumanos/estructura
 *
 * Carga las rutas y el bootstrap del hub (sesión, Auth, ConexionApps), igual que la Intranet.
 * Por qué es seguro para las pantallas públicas (Guía, Contactos, Internos): el bootstrap del hub
 * sólo arranca la sesión y carga clases; el único que redirige al login es Auth::exigir(), y esas
 * pantallas no lo llaman. Así pueden leer la sesión del hub (para dibujar o no la administración)
 * sin obligar a loguearse a los locales.
 *
 * ORDEN IMPORTANTE: antes del hub se carga el .env con el DotEnv de recursosHumanos.
 * Estructura usa dos conexiones: la del hub (ConexionApps, para Auth) y la del repo (Conexion,
 * conectar('central'), para Internos y el Directorio). ConexionApps deja las variables sólo en
 * $_ENV; después DotEnv saltea las que ya están en $_ENV y no las pasa a putenv(), pero Conexion
 * las lee con getenv(): si Auth corría primero, conectar('central') quedaba sin usuario ni clave
 * (pasaba en toda acción que valida permisos y después lee Internos). Cargando DotEnv primero,
 * las dos clases encuentran lo que buscan.
 */

require_once __DIR__ . '/rutas.php';

require_once ESTRUCTURA_RRHH_CLASS_DIR . '/classEnv.php';
try {
    (new DotEnv(DotEnv::archivo()))->listVars();
} catch (Throwable $e) {
    // Sin .env no hay base: cada pantalla lo va a mostrar al conectar; no cortamos acá
    error_log('estructura: no se pudo cargar el .env: ' . $e->getMessage());
}

require_once XL_HUB_DIR . '/Class/bootstrap.php';
