<?php
/**
 * Estructura XL - punto de entrada de las pantallas de recursosHumanos/estructura
 *
 * Carga las rutas y el bootstrap del hub (sesión, Auth, ConexionApps), igual que la Intranet.
 * Por qué es seguro para las pantallas públicas (Guía, Contactos, Internos): el bootstrap del hub
 * sólo arranca la sesión y carga clases; el único que redirige al login es Auth::exigir(), y esas
 * pantallas no lo llaman. Así pueden leer la sesión del hub (para dibujar o no la administración)
 * sin obligar a loguearse a los locales.
 */

require_once __DIR__ . '/rutas.php';
require_once XL_HUB_DIR . '/Class/bootstrap.php';
