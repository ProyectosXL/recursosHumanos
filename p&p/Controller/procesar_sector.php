<?php
require_once __DIR__ . '/../Class/AccesoDocuGest.php';
AccesoDocuGest::exigirAdmin(AccesoDocuGest::JSON);

require_once __DIR__ . '/../Class/RespuestaJson.php';
require_once __DIR__ . '/../Class/Politica.php';

// Alta (sin id) y modificación (con id) de sectores (Configuración, solo Admin)
exigirPost();
$datos = [
    'nombre'      => $_POST['nombre'] ?? '',
    'descripcion' => $_POST['descripcion'] ?? '',
    'icono'       => $_POST['icono'] ?? 'fa-folder',
];
$id = (int)($_POST['id'] ?? 0);
$politicaObj = new Politica();
if ($id > 0) {
    responderResultado($politicaObj->actualizarSector($id, $datos), 'Sector actualizado');
}
responderResultado($politicaObj->guardarSector($datos), 'Sector creado');
