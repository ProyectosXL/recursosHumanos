<?php
require_once __DIR__ . '/../Class/AccesoDocuGest.php';
AccesoDocuGest::exigirAdmin(AccesoDocuGest::JSON);

require_once __DIR__ . '/../Class/RespuestaJson.php';
require_once __DIR__ . '/../Class/Politica.php';

// Datos de un sector para editarlo (Configuración, solo Admin)
$id = (int)($_GET['id'] ?? 0);
$sector = $id > 0 ? (new Politica())->obtenerSectorPorId($id) : false;
if (!$sector) {
    responderError('El sector no existe');
}
responderJson(['status' => 'success', 'sector' => [
    'id'          => (int)$sector['id'],
    'nombre'      => $sector['nombre'],
    'descripcion' => $sector['descripcion'] ?? '',
    'icono'       => $sector['icono'] ?? 'fa-folder',
]]);
