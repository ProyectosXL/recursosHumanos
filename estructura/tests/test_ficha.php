<?php
/** Validación de la ficha de un área (Class/Ficha.php), sin base. */
require_once __DIR__ . '/../Class/Ficha.php';

$personas = array(
    59 => array('nombre' => 'Jessica Farias', 'activo' => true),
    71 => array('nombre' => 'Mariana Blanco', 'activo' => true),
    90 => array('nombre' => 'Persona Inactiva', 'activo' => false),
);
$otras = array(
    array('id' => 2, 'nombre' => 'Logística', 'activa' => true),
    array('id' => 7, 'nombre' => 'Prueba', 'activa' => false),
);
$base = array('nombre' => 'Abastecimiento', 'color' => '#2F5FB3', 'resuelve' => 'Pedidos', 'mail' => 'abastecimiento@xl.com.ar',
    'celular' => '11 2345 6789', 'horario' => 'L a V', 'id_gerente' => '', 'id_jefe' => '',
    'temas' => array(array('id' => null, 'tema' => 'Faltantes', 'id_referente' => 59, 'equipo' => false, 'palabras_clave' => 'me falta ,, faltante ')));

prueba('ficha: una ficha nueva válida pasa, limpia el celular y las palabras clave', function () use ($base, $otras, $personas) {
    $r = Ficha::validar($base, null, $otras, array(), $personas);
    verdadero($r['ok'], json_encode($r['errores'] + $r['errores_temas'], JSON_UNESCAPED_UNICODE));
    igual('1123456789', $r['datos']['celular_sector']);
    igual('me falta, faltante', $r['temas'][0]['palabras_clave']);
    igual(1, $r['temas'][0]['orden']);
});

prueba('ficha: un celular "123" no se guarda y dice por qué', function () use ($base, $otras, $personas) {
    $e = $base;
    $e['celular'] = '123';
    $r = Ficha::validar($e, null, $otras, array(), $personas);
    verdadero(!$r['ok'] && isset($r['errores']['celular']) && strpos($r['errores']['celular'], '10') !== false);
});

prueba('ficha: el nombre no se repite entre las activas, sin mayúsculas ni tildes', function () use ($base, $otras, $personas) {
    $e = $base;
    $e['nombre'] = 'LOGISTICA';
    $r = Ficha::validar($e, null, $otras, array(), $personas);
    verdadero(isset($r['errores']['nombre']));
});

prueba('ficha: un nombre de un área desactivada bloquea con "Existe desactivada"', function () use ($base, $otras, $personas) {
    $e = $base;
    $e['nombre'] = 'prueba';
    $r = Ficha::validar($e, null, $otras, array(), $personas);
    igual('Existe desactivada: reactivala desde el listado.', isset($r['errores']['nombre']) ? $r['errores']['nombre'] : null);
});

prueba('ficha: el referente no puede ser una persona y "equipo" a la vez', function () use ($base, $otras, $personas) {
    $e = $base;
    $e['temas'][0]['equipo'] = true;
    $r = Ficha::validar($e, null, $otras, array(), $personas);
    verdadero(isset($r['errores_temas'][0]['referente']));
});

prueba('ficha: temas repetidos o que coinciden con uno quitado no pasan', function () use ($base, $otras, $personas) {
    $e = $base;
    $e['temas'][] = array('id' => null, 'tema' => 'faltantes', 'id_referente' => '', 'equipo' => false, 'palabras_clave' => '');
    $r = Ficha::validar($e, null, $otras, array(), $personas);
    verdadero(isset($r['errores_temas'][1]['tema']));
    $r = Ficha::validar($base, null, $otras, array(array('id' => 5, 'tema' => 'Faltántes')), $personas);
    verdadero(isset($r['errores_temas'][0]['tema']) && strpos($r['errores_temas'][0]['tema'], 'quitado') !== false);
});

prueba('ficha: una persona inactiva nueva no se acepta; la que ya estaba guardada sí', function () use ($base, $otras, $personas) {
    $e = $base;
    $e['id_gerente'] = 90;
    $r = Ficha::validar($e, null, $otras, array(), $personas);
    verdadero(isset($r['errores']['id_gerente']) && strpos($r['errores']['id_gerente'], 'ya no está activa') !== false);

    $actual = array('id' => 1, 'id_gerente' => 90, 'id_jefe' => null, 'temas' => array(
        array('id' => 10, 'tema' => 'Faltantes', 'id_referente' => 90, 'referente_equipo' => false)));
    $e['temas'][0]['id'] = 10;
    $e['temas'][0]['id_referente'] = 90;
    $r = Ficha::validar($e, $actual, $otras, array(), $personas);
    verdadero($r['ok'], json_encode($r['errores'] + $r['errores_temas'], JSON_UNESCAPED_UNICODE));
});

prueba('ficha: quitar un tema guardado lo marca para baja lógica; un ID ajeno no se toca', function () use ($base, $otras, $personas) {
    $actual = array('id' => 1, 'id_gerente' => null, 'id_jefe' => null, 'temas' => array(
        array('id' => 10, 'tema' => 'Faltantes', 'id_referente' => 59, 'referente_equipo' => false)));
    $e = $base;
    $e['temas'] = array(array('id' => 10, 'tema' => 'Faltantes', 'quitar' => true), array('id' => 999, 'tema' => 'Ajeno'));
    $r = Ficha::validar($e, $actual, $otras, array(), $personas);
    igual(array(10), $r['quitar']);
    verdadero(isset($r['errores_temas'][1]['tema']));
});
