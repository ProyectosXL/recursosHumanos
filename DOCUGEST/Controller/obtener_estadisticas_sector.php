<?php
require_once __DIR__ . '/../Class/AccesoDocuGest.php';
AccesoDocuGest::exigirEditar(AccesoDocuGest::JSON);

require_once __DIR__ . '/../Class/RespuestaJson.php';
require_once __DIR__ . '/../Class/Politica.php';

// Documentos activos por sector (Estadísticas: Editar y Admin)
$stats = (new Politica())->obtenerEstadisticasSectores();
$total = 0;
$salida = [];
foreach ($stats as $fila) {
    $total += (int)$fila['documentos'];
    $salida[] = ['id' => (int)$fila['id'], 'nombre' => $fila['nombre'], 'documentos' => (int)$fila['documentos']];
}
responderJson(['status' => 'success', 'stats' => $salida, 'total' => $total]);
