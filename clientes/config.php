<?php
// config.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$autoloaderPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloaderPath)) {
    die("ERROR CRÍTICO EN CONFIG.PHP: No se encuentra el archivo 'autoload.php'.<br>Ruta esperada: " . realpath(__DIR__ . '/../') . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php' . "<br>Asegúrate de ejecutar 'composer install' en la carpeta 'recursosHumanos'.");
}
require_once $autoloaderPath;

// htdocs/.env es el principal (el mismo que usa sistemas); recursosHumanos/.env solo completa
// las claves que falten. createImmutable no pisa valores: gana el primer archivo.
$dotenvPaths = array_values(array_filter([
    dirname(__DIR__, 2) . '/',
    dirname(__DIR__) . '/',
], function ($dir) { return is_readable($dir . '.env'); }));
if (empty($dotenvPaths)) {
    die("ERROR CRÍTICO EN CONFIG.PHP: No se encuentra el archivo '.env'.<br>Debe estar en htdocs (principal) o en la carpeta 'recursosHumanos'.");
}

try {
    $dotenv = Dotenv\Dotenv::createImmutable($dotenvPaths, '.env', false);
    $dotenv->load();
} catch (\Throwable $th) {
    die('ERROR CRÍTICO EN CONFIG.PHP: El archivo .env existe pero no se pudo leer. Revisa que no tenga errores de sintaxis. Detalle: ' . $th->getMessage());
}

define('ROOT_PATH', __DIR__ . '/'); 
define('BASE_URL', '/recursosHumanos/clientes/');

define('DB_HOST_SERVIDOR', $_ENV['HOST_CENTRAL']);
define('DB_NAME_SERVIDOR', $_ENV['DATABASE_CENTRAL']);
define('DB_USER_SERVIDOR', $_ENV['USER']);
define('DB_PASS_SERVIDOR', $_ENV['PASS']);

define('DB_NAME_CENTRAL', $_ENV['DATABASE_CENTRAL']);

define('DB_HOST_LOCALES', $_ENV['HOST_LOCALES']);
define('DB_NAME_LOCALES', $_ENV['DATABASE_LOCALES']);
define('DB_USER_LOCALES', $_ENV['USER']);
define('DB_PASS_LOCALES', $_ENV['PASS_LOCALES']);

// Definiciones para el envío de correos
define('MAIL_HOST', $_ENV['MAIL_HOST']);
define('MAIL_PORT', $_ENV['MAIL_PORT']);
define('MAIL_USERNAME', $_ENV['MAIL_USERNAME']);
define('MAIL_PASSWORD', $_ENV['MAIL_PASSWORD']);
define('MAIL_FROM_ADDRESS', $_ENV['MAIL_FROM_ADDRESS']);
define('MAIL_FROM_NAME', $_ENV['MAIL_FROM_NAME']);
define('MAIL_TO_ADDRESS', $_ENV['MAIL_TO_ADDRESS']);

define('DB_DRIVER', '{SQL Server}');