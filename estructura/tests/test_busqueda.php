<?php
/**
 * Normalización y búsqueda de la Guía de Consultas, con los casos compartidos con el JS
 * (casos_compartidos.json + fixture_directorio.json). tests/paridad.html corre los mismos en el
 * navegador: si los dos pasan, PHP y JS dan lo mismo.
 */
require_once __DIR__ . '/../Class/Reglas.php';

$casos = json_decode(file_get_contents(__DIR__ . '/casos_compartidos.json'), true);
$fixture = json_decode(file_get_contents(__DIR__ . '/fixture_directorio.json'), true);

prueba('normalización: casos compartidos con el JS', function () use ($casos) {
    foreach ($casos['normalizar'] as $c) {
        igual($c['normalizado'], Reglas::normalizarBusqueda($c['texto']), json_encode($c['texto'], JSON_UNESCAPED_UNICODE));
    }
});

prueba('normalización: "Me FALTA mercadería" y "me falta mercaderia" dan lo mismo', function () {
    igual(Reglas::normalizarBusqueda('me falta mercaderia'), Reglas::normalizarBusqueda('Me FALTA mercadería'));
});

prueba('normalización: las palabras vacías se quitan', function () {
    foreach (Reglas::PALABRAS_VACIAS as $p) {
        igual('pedido', Reglas::normalizarBusqueda($p . ' pedido'), 'palabra vacía "' . $p . '"');
    }
});

prueba('caracteres útiles: casos compartidos con el JS', function () use ($casos) {
    foreach ($casos['caracteresUtiles'] as $c) {
        igual($c['caracteres'], Reglas::caracteresUtiles($c['texto']), $c['texto']);
    }
});

prueba('búsqueda: casos compartidos con el JS', function () use ($casos, $fixture) {
    foreach ($casos['busqueda'] as $c) {
        $res = array();
        foreach (Reglas::buscar($c['consulta'], $fixture['temas'], $c['area']) as $t) {
            $res[] = $t['area'] . ' › ' . $t['tema'];
        }
        igual($c['esperados'], $res, '"' . $c['consulta'] . '"' . ($c['area'] ? ' en el área ' . $c['area'] : ''));
    }
});

prueba('búsqueda: con un área elegida sólo aparecen temas de esa área', function () use ($fixture) {
    foreach (array(1, 2, 3, 4, 5, 6) as $idArea) {
        // "a" + algo común: buscamos por el nombre de cada área para que haya resultados en todas
        foreach (Reglas::buscar('de', $fixture['temas'], $idArea) as $t) {
            igual($idArea, $t['id_area']);
        }
        foreach (Reglas::buscar('ca', $fixture['temas'], $idArea) as $t) {
            igual($idArea, $t['id_area']);
        }
    }
});

prueba('búsqueda: una coincidencia parcial cuenta ("imprim" encuentra Impresoras)', function () {
    verdadero(Reglas::coincide(array('imprim'), Reglas::textoBuscable('Impresoras', 'impresora, no imprime, ticket', 'Sistemas')));
    verdadero(!Reglas::coincide(array('imprim', 'xyz'), Reglas::textoBuscable('Impresoras', 'impresora, no imprime, ticket', 'Sistemas')));
});

prueba('registro de búsquedas: no registra con menos de 3 caracteres útiles', function () {
    verdadero(!Reglas::debeRegistrarBusqueda('pc', ''));
    verdadero(!Reglas::debeRegistrarBusqueda('no me la', ''));
    verdadero(!Reglas::debeRegistrarBusqueda('   ', ''));
    verdadero(Reglas::debeRegistrarBusqueda('caja', ''));
});

prueba('registro de búsquedas: no registra dos veces seguidas el mismo texto', function () {
    $ultima = Reglas::normalizarBusqueda('No imprime');
    verdadero(!Reglas::debeRegistrarBusqueda('no imprime', $ultima));
    verdadero(!Reglas::debeRegistrarBusqueda('NO IMPRIME!', $ultima));
    verdadero(Reglas::debeRegistrarBusqueda('no imprime el ticket', $ultima));
});
