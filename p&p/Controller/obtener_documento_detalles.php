<?php
/**
 * Datos de un documento para la pestaña Actualizar (nivel Editar).
 * Solo documentos activos. No incluye rutas de archivos.
 */
require_once __DIR__ . '/../Class/AccesoDocuGest.php';
AccesoDocuGest::exigirEditar(AccesoDocuGest::JSON);

require_once __DIR__ . '/../Class/Politica.php';

header('Content-Type: application/json; charset=utf-8');

$documento_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($documento_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'ID de documento no especificado']);
    exit;
}

$politicaObj = new Politica();
$documento = $politicaObj->obtenerPorId($documento_id, false, true);

if (!$documento) {
    echo json_encode(['status' => 'error', 'message' => 'El documento no existe o fue dado de baja']);
    exit;
}

$fechaVersion = $documento['fecha_ultima_version'] ?? null;
if (empty($fechaVersion)) {
    $fechaVersion = $documento['fecha_creacion'] ?? ($documento['fecha_actualizacion'] ?? null);
}

echo json_encode([
    'status' => 'success',
    'documento' => [
        'id'                  => (int)$documento['id'],
        'titulo'              => $documento['titulo'],
        'tipo'                => $documento['tipo'],
        'sector_id'           => (int)$documento['sector_id'],
        'sector_nombre'       => $documento['sector_nombre'],
        'descripcion'         => $documento['descripcion'] ?? '',
        'tags'                => $documento['tags'] ?? '',
        'version'             => trim((string)($documento['version'] ?? '')) ?: '1.0',
        'fecha_actualizacion' => Politica::formatearFecha($fechaVersion),
    ],
], JSON_UNESCAPED_UNICODE);
exit;
