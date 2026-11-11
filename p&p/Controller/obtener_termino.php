<?php
require_once __DIR__ . '/../Class/AccesoDocuGest.php';
AccesoDocuGest::exigirAdmin(AccesoDocuGest::JSON);

require_once __DIR__ . '/../Class/RespuestaJson.php';
require_once __DIR__ . '/../Class/Politica.php';

// Datos de un término para editarlo (Configuración, solo Admin)
$id = (int)($_GET['id'] ?? 0);
$termino = $id > 0 ? (new Politica())->obtenerTerminoPorId($id) : false;
if (!$termino) {
    responderError('El término no existe');
}
responderJson(['status' => 'success', 'termino' => [
    'id'         => (int)$termino['id'],
    'termino'    => $termino['termino'],
    'definicion' => $termino['definicion'],
    'sector_id'  => $termino['sector_id'] !== null ? (int)$termino['sector_id'] : null,
]]);
