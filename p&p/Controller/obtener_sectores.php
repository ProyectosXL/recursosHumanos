<?php
require_once __DIR__ . '/../Class/AccesoDocuGest.php';
AccesoDocuGest::exigirLogin(AccesoDocuGest::JSON);

require_once __DIR__ . '/../Class/RespuestaJson.php';
require_once __DIR__ . '/../Class/Politica.php';

// Lista de sectores para los desplegables (cualquier usuario logueado)
$sectores = (new Politica())->obtenerSectores() ?: [];
$salida = [];
foreach ($sectores as $s) {
    $salida[] = ['id' => (int)$s['id'], 'nombre' => $s['nombre'], 'icono' => $s['icono'] ?? 'fa-folder'];
}
responderJson(['status' => 'success', 'sectores' => $salida]);
