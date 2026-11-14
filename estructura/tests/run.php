<?php
/**
 * Estructura XL - pruebas de la lógica pura (sin base, sin servidor web, sin dependencias).
 *
 *     php estructura/tests/run.php
 *
 * Por qué así y no PHPUnit: recursosHumanos no tiene suite y no se agregan librerías. Cada
 * archivo tests/test_*.php llama a prueba('nombre', function () { ... }) con las aserciones de
 * abajo. Sale con código 1 si algo falla, para poder usarlo antes de cada commit.
 * La paridad del normalizador JS se prueba en tests/paridad.html (no hay Node en los equipos).
 */

if (PHP_SAPI !== 'cli') {
    // También hay un .htaccess que lo bloquea: las pruebas no se corren desde el navegador
    http_response_code(404);
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', '1');
set_error_handler(function ($nivel, $mensaje, $archivo, $linea) {
    // Un aviso de PHP en la lógica pura es un error: en el servidor terminaría en el log
    throw new ErrorException($mensaje, 0, $nivel, $archivo, $linea);
});

$GLOBALS['pruebas'] = array('ok' => 0, 'fallas' => array(), 'actual' => '');

function prueba($nombre, $fn)
{
    $GLOBALS['pruebas']['actual'] = $nombre;
    try {
        $fn();
    } catch (Throwable $e) {
        falla('excepción: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');
    }
}

function falla($detalle)
{
    $GLOBALS['pruebas']['fallas'][] = $GLOBALS['pruebas']['actual'] . ' → ' . $detalle;
}

function verdadero($condicion, $detalle = 'se esperaba verdadero')
{
    if ($condicion) {
        $GLOBALS['pruebas']['ok']++;
    } else {
        falla($detalle);
    }
}

function igual($esperado, $obtenido, $detalle = '')
{
    if ($esperado === $obtenido) {
        $GLOBALS['pruebas']['ok']++;
    } else {
        falla(($detalle !== '' ? $detalle . ': ' : '') . 'esperado ' . var_export($esperado, true)
            . ', obtenido ' . var_export($obtenido, true));
    }
}

foreach (glob(__DIR__ . '/test_*.php') as $archivo) {
    require $archivo;
}

$r = $GLOBALS['pruebas'];
foreach ($r['fallas'] as $f) {
    echo "FALLA  $f\n";
}
echo sprintf("\n%d aserciones correctas, %d fallas\n", $r['ok'], count($r['fallas']));
exit(count($r['fallas']) ? 1 : 0);
