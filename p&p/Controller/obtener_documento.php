<?php
/**
 * Datos de un documento para el visor (cualquier usuario logueado).
 * Suma una vista. Los dados de baja solo los ve Admin.
 */
require_once __DIR__ . '/../Class/AccesoDocuGest.php';
AccesoDocuGest::exigirLogin(AccesoDocuGest::JSON);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../Class/Politica.php';

try {
    $politicaObj = new Politica();

    $documento_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($documento_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID de documento no especificado']);
        exit;
    }

    // Los documentos dados de baja solo los ve Admin
    $documento = $politicaObj->obtenerPorId($documento_id, false, !AccesoDocuGest::esAdmin());

    if (!$documento) {
        echo json_encode(['status' => 'error', 'message' => 'El documento no existe o fue dado de baja']);
        exit;
    }

    // Registrar la visualización (incrementa el contador una vez)
    $politicaObj->registrarAcceso($documento_id, 'vista', AccesoDocuGest::usuarioId());

    $fechaVersion = $documento['fecha_ultima_version'] ?? null;
    if (empty($fechaVersion)) {
        $fechaVersion = $documento['fecha_creacion'] ?? ($documento['fecha_actualizacion'] ?? null);
    }

    // Sin rutas de archivo: el PDF se pide a Controller/ver_documento.php?id=
    echo json_encode([
        'status' => 'success',
        'documento' => [
            'id'                  => (int)$documento['id'],
            'titulo'              => $documento['titulo'],
            'tipo'                => $documento['tipo'],
            'sector_nombre'       => $documento['sector_nombre'],
            'descripcion'         => $documento['descripcion'] ?? '',
            'version'             => trim((string)($documento['version'] ?? '')) ?: '1.0',
            'fecha_creacion'      => Politica::formatearFecha($documento['fecha_creacion'] ?? null),
            'fecha_actualizacion' => Politica::formatearFecha($fechaVersion),
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('obtener_documento: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error en el servidor']);
}
exit;
