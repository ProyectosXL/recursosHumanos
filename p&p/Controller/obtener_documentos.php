<?php
require_once __DIR__ . '/../Class/AccesoDocuGest.php';
AccesoDocuGest::exigirLogin(AccesoDocuGest::JSON);

require_once __DIR__ . '/../Class/RespuestaJson.php';
require_once __DIR__ . '/../Class/Politica.php';

// Documentos activos (opcional: ?sector=ID) para cualquier usuario logueado
$sectorId = (int)($_GET['sector'] ?? 0);
$filtros = $sectorId > 0 ? ['sector_id' => $sectorId] : [];
$documentos = (new Politica())->obtenerTodos($filtros) ?: [];
$salida = [];
foreach ($documentos as $doc) {
    $fecha = $doc['fecha_ultima_version'] ?? null;
    if (empty($fecha)) {
        $fecha = $doc['fecha_creacion'] ?? ($doc['fecha_actualizacion'] ?? null);
    }
    $salida[] = [
        'id'                  => (int)$doc['id'],
        'titulo'              => $doc['titulo'],
        'tipo'                => $doc['tipo'],
        'sector_id'           => (int)$doc['sector_id'],
        'sector_nombre'       => $doc['sector_nombre'],
        'version'             => trim((string)($doc['version'] ?? '')) ?: '1.0',
        'fecha_actualizacion' => Politica::formatearFecha($fecha, 'Y-m-d'),
    ];
}
responderJson(['status' => 'success', 'documentos' => $salida]);
