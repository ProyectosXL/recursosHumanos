<?php
/** Importación del listado en Excel (Importacion::planificar), sin base. */
require_once __DIR__ . '/../Class/Importacion.php';

$ENC = array_values(Importacion::COLUMNAS);
$PERSONAS = array(
    59 => array('nombre' => 'Jessica Farias', 'activo' => true),
    71 => array('nombre' => 'Mariana Blanco', 'activo' => true),
    76 => array('nombre' => 'Martin Becker', 'activo' => true),
    29 => array('nombre' => 'Lucas Navarro', 'activo' => true),
    90 => array('nombre' => 'Persona Inactiva', 'activo' => false),
);

/** Fila con las 12 columnas en el orden de la plantilla */
function filaImp($area, $tema, array $otros = array())
{
    $f = array_fill_keys(array_keys(Importacion::COLUMNAS), '');
    $f['area'] = $area;
    $f['tema'] = $tema;
    return array_values(array_merge($f, $otros));
}

/** Estado "después de importar" armado a mano: Abastecimiento con Faltantes y Stock, Logística con Entregas */
function estadoImp()
{
    return array(
        array('id' => 1, 'nombre' => 'Abastecimiento', 'activa' => true, 'orden' => 1, 'color' => '#2F5FB3', 'resuelve' => 'Pedidos',
            'mail_sector' => 'abastecimiento@xl.com.ar', 'celular_sector' => '', 'horario' => '', 'id_gerente' => 76, 'id_jefe' => null,
            'temas' => array(
                array('id' => 11, 'tema' => 'Faltantes', 'orden' => 1, 'id_referente' => 59, 'referente_equipo' => false, 'palabras_clave' => 'me falta'),
                array('id' => 12, 'tema' => 'Stock', 'orden' => 2, 'id_referente' => 71, 'referente_equipo' => false, 'palabras_clave' => 'sin stock'),
            ),
            'temas_quitados' => array(array('id' => 13, 'tema' => 'Viejo'))),
        array('id' => 2, 'nombre' => 'Logística', 'activa' => true, 'orden' => 2, 'color' => '#1F7A8C', 'resuelve' => '',
            'mail_sector' => '', 'celular_sector' => '', 'horario' => '', 'id_gerente' => null, 'id_jefe' => 29,
            'temas' => array(array('id' => 21, 'tema' => 'Entregas', 'orden' => 1, 'id_referente' => 29, 'referente_equipo' => false, 'palabras_clave' => 'entrega')),
            'temas_quitados' => array()),
        array('id' => 3, 'nombre' => 'Prueba', 'activa' => false, 'orden' => 9, 'color' => '#5B5BA8', 'resuelve' => '',
            'mail_sector' => '', 'celular_sector' => '', 'horario' => '', 'id_gerente' => null, 'id_jefe' => null,
            'temas' => array(), 'temas_quitados' => array()),
    );
}

function accionesImp(array $lista)
{
    $r = array();
    foreach ($lista as $x) {
        $r[] = $x['accion'] . ':' . (isset($x['tema']) ? $x['area'] . ' › ' . $x['tema'] : $x['nombre']);
    }
    return $r;
}

prueba('importación: un archivo nuevo crea las áreas y los temas', function () use ($ENC, $PERSONAS) {
    $plan = Importacion::planificar(array($ENC,
        filaImp('Abastecimiento', 'Faltantes', array('orden' => '1', 'color' => '#2F5FB3', 'mail' => 'abastecimiento@xl.com.ar',
            'gerente' => 'Martín Becker', 'referente' => 'Jessica Farias', 'palabras' => 'me falta, faltante')),
        filaImp('Abastecimiento', 'Stock', array('referente' => 'Mariana Blanco')),
        filaImp('Innovación', 'Soporte', array('referente' => 'Equipo')),
    ), array(), $PERSONAS);
    igual(array(), $plan['errores']);
    igual(array('crear:Abastecimiento', 'crear:Innovación'), accionesImp($plan['areas']));
    igual(array('crear:Abastecimiento › Faltantes', 'crear:Abastecimiento › Stock', 'crear:Innovación › Soporte'), accionesImp($plan['temas']));
    igual(76, $plan['areas'][0]['valores']['id_gerente'], 'gerente resuelto sin tildes');
    igual(59, $plan['temas'][0]['valores']['id_referente']);
    igual(true, $plan['temas'][2]['valores']['referente_equipo']);
    verdadero($plan['areas'][1]['valores']['color'] !== null, 'área sin color toma uno de la paleta');
    igual(array('areas_crear' => 2, 'areas_actualizar' => 0, 'temas_crear' => 3, 'temas_actualizar' => 0), $plan['resumen']);
});

prueba('importación: un segundo archivo con un cambio actualiza sólo ese campo', function () use ($ENC, $PERSONAS) {
    $plan = Importacion::planificar(array($ENC,
        filaImp('Abastecimiento', 'Faltantes', array('horario' => 'Lunes a viernes de 9 a 18', 'mail' => 'abastecimiento@xl.com.ar', 'referente' => 'Jessica Farias')),
    ), estadoImp(), $PERSONAS);
    igual(array(), $plan['errores']);
    igual('actualizar', $plan['areas'][0]['accion']);
    igual(array(array('campo' => 'Horario', 'de' => '', 'a' => 'Lunes a viernes de 9 a 18')), $plan['areas'][0]['cambios']);
    igual(array('horario' => 'Lunes a viernes de 9 a 18'), $plan['areas'][0]['valores']);
    igual('igual', $plan['temas'][0]['accion']);
});

prueba('importación: una celda vacía no borra un dato existente', function () use ($ENC, $PERSONAS) {
    $plan = Importacion::planificar(array($ENC, filaImp('Abastecimiento', 'Faltantes')), estadoImp(), $PERSONAS);
    igual('igual', $plan['areas'][0]['accion'], 'mail, gerente y resuelve vacíos no se borran');
    igual('igual', $plan['temas'][0]['accion'], 'referente y palabras vacías no se borran');
});

prueba('importación: un área o un tema que no viene no se toca', function () use ($ENC, $PERSONAS) {
    $plan = Importacion::planificar(array($ENC, filaImp('Abastecimiento', 'Faltantes')), estadoImp(), $PERSONAS);
    igual(array('Logística'), $plan['no_incluidos']['areas']);
    igual(array('Abastecimiento › Stock'), $plan['no_incluidos']['temas']);
    igual(1, count($plan['areas']));
});

prueba('importación: un nombre que no está en Internos se importa vacío con un aviso', function () use ($ENC, $PERSONAS) {
    $plan = Importacion::planificar(array($ENC,
        filaImp('Administración', 'Compras', array('referente' => 'Nadie Nunca', 'gerente' => 'Persona Inactiva')),
        filaImp('Abastecimiento', 'Stock', array('referente' => 'Nadie Nunca')),
    ), estadoImp(), $PERSONAS);
    igual(array(), $plan['errores']);
    igual(null, $plan['temas'][0]['valores']['id_referente'], 'tema nuevo sin referente');
    igual('igual', $plan['temas'][1]['accion'], 'tema existente: no se cambia el referente que tenía');
    igual(null, $plan['areas'][0]['valores']['id_gerente']);
    $avisos = implode(' | ', array_map(function ($a) { return $a['motivo']; }, $plan['avisos']));
    verdadero(strpos($avisos, '"Nadie Nunca" no está en Internos') !== false, $avisos);
    verdadero(strpos($avisos, '"Persona Inactiva" está inactivo en Internos') !== false, $avisos);
});

prueba('importación: un mail inválido bloquea todo e indica la fila', function () use ($ENC, $PERSONAS) {
    $plan = Importacion::planificar(array($ENC,
        filaImp('Abastecimiento', 'Faltantes'),
        filaImp('Logística', 'Entregas', array('mail' => 'logistica@')),
    ), estadoImp(), $PERSONAS);
    igual(1, count($plan['errores']));
    igual(3, $plan['errores'][0]['fila']);
    verdadero(strpos($plan['errores'][0]['motivo'], 'logistica@') !== false);
});

prueba('importación: celular inválido, color no hex y fila sin área o sin tema bloquean', function () use ($ENC, $PERSONAS) {
    $plan = Importacion::planificar(array($ENC,
        filaImp('Abastecimiento', 'Faltantes', array('celular' => '011 15 2345 6789')),
        filaImp('Abastecimiento', 'Stock', array('color' => 'azul')),
        filaImp('', 'Huérfano'),
        filaImp('Logística', ''),
    ), estadoImp(), $PERSONAS);
    igual(array(2, 3, 4, 5), array_map(function ($e) { return $e['fila']; }, $plan['errores']));
});

prueba('importación: las mayúsculas y las tildes no generan duplicados ("Logistica" = "Logística")', function () use ($ENC, $PERSONAS) {
    $plan = Importacion::planificar(array($ENC, filaImp('LOGISTICA', 'entregas'), filaImp('logística', 'Entregas')), estadoImp(), $PERSONAS);
    igual(array('igual:Logística'), accionesImp($plan['areas']));
    igual(array('igual:Logística › Entregas'), accionesImp($plan['temas']));
    igual(1, $plan['omitidas'], 'la fila repetida se omite');
});

prueba('importación: un dato del área distinto en dos filas toma el de la primera y avisa', function () use ($ENC, $PERSONAS) {
    $plan = Importacion::planificar(array($ENC,
        filaImp('Logística', 'Entregas', array('horario' => 'De 9 a 18')),
        filaImp('Logística', 'Remitos', array('horario' => 'De 8 a 17')),
    ), estadoImp(), $PERSONAS);
    igual('De 9 a 18', $plan['areas'][0]['valores']['horario']);
    igual(1, count($plan['avisos']));
    igual(3, $plan['avisos'][0]['fila']);
});

prueba('importación: un área desactivada o un tema quitado con el mismo nombre bloquean', function () use ($ENC, $PERSONAS) {
    $plan = Importacion::planificar(array($ENC, filaImp('prueba', 'Algo'), filaImp('Abastecimiento', 'VIEJO')), estadoImp(), $PERSONAS);
    igual(2, count($plan['errores']));
    verdadero(strpos($plan['errores'][0]['motivo'], 'desactivada') !== false);
    verdadero(strpos($plan['errores'][1]['motivo'], 'quitado') !== false);
});

prueba('importación: las filas vacías se omiten y las columnas se reconocen en cualquier orden', function () use ($PERSONAS) {
    $plan = Importacion::planificar(array(
        array('Tema', 'Referente', 'AREA'),
        array('Remitos', 'Lucas Navarro', 'Logistica'),
        array('', '', ''),
    ), estadoImp(), $PERSONAS);
    igual(array(), $plan['errores']);
    igual(array('crear:Logística › Remitos'), accionesImp($plan['temas']));
    igual(29, $plan['temas'][0]['valores']['id_referente']);
    igual(2, $plan['temas'][0]['valores']['orden'], 'el tema nuevo va al final del área');
    igual(1, $plan['omitidas']);
});

prueba('importación: sin la columna Área o Tema no sigue', function () use ($PERSONAS) {
    $plan = Importacion::planificar(array(array('Nombre', 'Tema'), array('x', 'y')), estadoImp(), $PERSONAS);
    igual(1, count($plan['errores']));
    igual(1, $plan['errores'][0]['fila']);
});

prueba('importación: la plantilla con los datos actuales vuelve a subir sin cambios', function () use ($PERSONAS) {
    $estado = estadoImp();
    $activas = array_values(array_filter($estado, function ($a) { return $a['activa']; }));
    $filas = Importacion::filasPlantilla($activas, $PERSONAS);
    igual(array_values(Importacion::COLUMNAS), $filas[0]);
    igual(4, count($filas), 'una fila por tema activo');
    $plan = Importacion::planificar($filas, $estado, $PERSONAS);
    igual(array(), $plan['errores']);
    igual(array('areas_crear' => 0, 'areas_actualizar' => 0, 'temas_crear' => 0, 'temas_actualizar' => 0), $plan['resumen']);
});
