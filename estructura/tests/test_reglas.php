<?php
/** Mail, celular, WhatsApp, color, referente y ficha completa. */
require_once __DIR__ . '/../Class/Reglas.php';

prueba('celular: los formatos de siempre dan 5491123456789', function () {
    foreach (array('11 2345 6789', '11-2345-6789', '(11) 2345 6789', '1123456789') as $c) {
        $r = Reglas::limpiarCelular($c);
        igual(null, $r['error'], $c);
        igual('5491123456789', substr((string)Reglas::linkWhatsapp($r['digitos']), -13), $c);
    }
    igual('https://web.whatsapp.com/send?phone=5491123456789', Reglas::linkWhatsapp('1123456789'));
});

prueba('celular: "011 15 2345 6789" se rechaza con el motivo', function () {
    $r = Reglas::limpiarCelular('011 15 2345 6789');
    igual(null, $r['digitos']);
    verdadero(strpos($r['error'], 'el 0') !== false && strpos($r['error'], '15') !== false, $r['error']);
});

prueba('celular: con 0 o con 15 de más, cada uno con su motivo', function () {
    $r = Reglas::limpiarCelular('011 2345 6789');
    verdadero(strpos($r['error'], 'Sobra el 0') === 0, (string)$r['error']);
    $r = Reglas::limpiarCelular('11 15 2345 6789');
    verdadero(strpos($r['error'], 'Sobra el 15') === 0, (string)$r['error']);
    $r = Reglas::limpiarCelular('351 15 234 5678');
    verdadero(strpos($r['error'], 'Sobra el 15') === 0, (string)$r['error']);
});

prueba('celular: un número de 8 dígitos (o 11) se rechaza', function () {
    $r = Reglas::limpiarCelular('2345 6789');
    igual(null, $r['digitos']);
    verdadero(strpos($r['error'], 'Tiene 8 dígitos') === 0, (string)$r['error']);
    $r = Reglas::limpiarCelular('123');
    verdadero($r['error'] !== null);
    $r = Reglas::limpiarCelular('11 2345 67890');
    verdadero($r['error'] !== null);
});

prueba('celular: vacío es válido (es opcional) y se formatea para mostrar', function () {
    igual(array('digitos' => '', 'error' => null), Reglas::limpiarCelular('  '));
    igual('11 2345 6789', Reglas::formatearCelular('1123456789'));
    igual('351 234 5678', Reglas::formatearCelular('3512345678'));
    igual(null, Reglas::linkWhatsapp(''));
});

prueba('mail: uno válido pasa, "abastecimiento@" no', function () {
    igual(null, Reglas::validarMail('abastecimiento@xl.com.ar'));
    igual(null, Reglas::validarMail(''));
    verdadero(Reglas::validarMail('abastecimiento@') !== null);
    verdadero(Reglas::validarMail('abastecimiento@xl') !== null);
    verdadero(Reglas::validarMail('abas tecimiento@xl.com.ar') !== null);
});

prueba('color: hex válido, con o sin #; lo demás no', function () {
    igual('#2F5FB3', Reglas::normalizarColor('#2f5fb3'));
    igual('#2F5FB3', Reglas::normalizarColor('2F5FB3'));
    igual(null, Reglas::normalizarColor('azul'));
    igual(null, Reglas::normalizarColor('#12345'));
    foreach (Reglas::PALETA as $c) {
        igual($c, Reglas::normalizarColor($c));
    }
});

prueba('referente: no puede ser una persona y "equipo" a la vez', function () {
    verdadero(Reglas::validarReferente(59, true) !== null);
    igual(null, Reglas::validarReferente(59, false));
    igual(null, Reglas::validarReferente(null, true));
    igual(null, Reglas::validarReferente(null, false));
});

$personas = array(
    59 => array('nombre' => 'Jessica Farias', 'activo' => false),
    71 => array('nombre' => 'Mariana Blanco', 'activo' => true),
);

prueba('referente: inactivo en Internos se muestra "A definir" y avisa en la administración', function () use ($personas) {
    igual(null, Reglas::textoReferente(59, false, 'Abastecimiento', $personas));
    igual('Mariana Blanco', Reglas::textoReferente(71, false, 'Abastecimiento', $personas));
    igual('Equipo de Innovación', Reglas::textoReferente(null, true, 'Innovación', $personas));
    igual(null, Reglas::textoReferente(null, false, 'Sistemas', $personas));
    igual('Jessica Farias ya no está activa en Internos', Reglas::avisoPersona(59, $personas));
    igual(null, Reglas::avisoPersona(71, $personas));
    verdadero(Reglas::avisoPersona(999, $personas) !== null);
});

$completa = array('resuelve' => 'Pedidos', 'mail_sector' => 'a@xl.com.ar', 'celular_sector' => '1123456789', 'horario' => 'L a V 9 a 18');

prueba('ficha completa: completa', function () use ($completa, $personas) {
    $temas = array(array('id_referente' => 71, 'referente_equipo' => 0), array('id_referente' => null, 'referente_equipo' => 1));
    igual(array('completa' => true, 'texto' => 'Completa'), Reglas::fichaCompleta($completa, $temas, $personas));
});

prueba('ficha completa: falta celular y horario', function () use ($completa, $personas) {
    $area = $completa;
    $area['celular_sector'] = '';
    $area['horario'] = null;
    $r = Reglas::fichaCompleta($area, array(array('id_referente' => 71, 'referente_equipo' => 0)), $personas);
    igual(array('completa' => false, 'texto' => 'Falta celular y horario'), $r);
});

prueba('ficha completa: temas sin referente', function () use ($completa, $personas) {
    $temas = array_fill(0, 6, array('id_referente' => null, 'referente_equipo' => 0));
    igual('6 temas sin referente', Reglas::fichaCompleta($completa, $temas, $personas)['texto']);
    igual('1 tema sin referente', Reglas::fichaCompleta($completa, array($temas[0]), $personas)['texto']);
});

prueba('ficha completa: referente inactivo en Internos', function () use ($completa, $personas) {
    $temas = array(array('id_referente' => 59, 'referente_equipo' => 0), array('id_referente' => 71, 'referente_equipo' => 0));
    igual(array('completa' => false, 'texto' => '1 referente inactivo en Internos'), Reglas::fichaCompleta($completa, $temas, $personas));
});

prueba('ficha completa: varias faltas juntas y gerente o jefe no cuentan', function () use ($personas) {
    $r = Reglas::fichaCompleta(array('resuelve' => 'x', 'mail_sector' => '', 'celular_sector' => '', 'horario' => ''),
        array(array('id_referente' => null, 'referente_equipo' => 0)), $personas);
    igual('Falta mail, celular y horario · 1 tema sin referente', $r['texto']);
    verdadero(Reglas::fichaCompleta(array('resuelve' => 'x', 'mail_sector' => 'a@b.com', 'celular_sector' => '1123456789', 'horario' => 'h'), array(), $personas)['texto'] === 'Sin temas');
});
