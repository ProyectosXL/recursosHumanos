<?php
// Acceso: cualquier usuario logueado en el hub
require_once __DIR__ . '/../Class/AccesoDocuGest.php';
AccesoDocuGest::exigirLogin(AccesoDocuGest::JSON);

// Configurar encabezados para respuesta JSON
header('Content-Type: application/json');

require_once __DIR__ . '/../Class/Politica.php';

try {
    // Crear instancia de la clase
    $politicaObj = new Politica();

    // Verificar que se ha proporcionado un ID
    if (isset($_GET['id']) && !empty($_GET['id'])) {
        $documento_id = (int)$_GET['id'];
        
        // Obtener información del documento sin incrementar vista (parámetro en false)
        // Los documentos dados de baja solo los ve Admin
        $documento = $politicaObj->obtenerPorId($documento_id, false, !AccesoDocuGest::esAdmin());
        
        if ($documento) {
            // Registrar la visualización (esto ya incrementa el contador una vez)
            $politicaObj->registrarAcceso($documento_id, 'vista', AccesoDocuGest::usuarioId());
            
            // Formatear las fechas correctamente
            if (isset($documento['fecha_creacion']) && is_object($documento['fecha_creacion'])) {
                // Si es un objeto DateTime de SQL Server
                if (isset($documento['fecha_creacion']->date)) {
                    $documento['fecha_creacion'] = date('d/m/Y', strtotime($documento['fecha_creacion']->date));
                } else {
                    $documento['fecha_creacion'] = date('d/m/Y'); // Fecha actual si no se puede convertir
                }
            } else if (is_string($documento['fecha_creacion'])) {
                $documento['fecha_creacion'] = date('d/m/Y', strtotime($documento['fecha_creacion']));
            }
            
            if (isset($documento['fecha_actualizacion']) && is_object($documento['fecha_actualizacion'])) {
                // Si es un objeto DateTime de SQL Server
                if (isset($documento['fecha_actualizacion']->date)) {
                    $documento['fecha_actualizacion'] = date('d/m/Y', strtotime($documento['fecha_actualizacion']->date));
                } else {
                    $documento['fecha_actualizacion'] = date('d/m/Y'); // Fecha actual si no se puede convertir
                }
            } else if (is_string($documento['fecha_actualizacion'])) {
                $documento['fecha_actualizacion'] = date('d/m/Y', strtotime($documento['fecha_actualizacion']));
            }
            
            // Devolver respuesta exitosa
            echo json_encode([
                'status' => 'success',
                'documento' => $documento
            ]);
        } else {
            // Documento no encontrado
            echo json_encode([
                'status' => 'error',
                'message' => 'Documento no encontrado'
            ]);
        }
    } else {
        // ID no proporcionado
        echo json_encode([
            'status' => 'error',
            'message' => 'ID de documento no especificado'
        ]);
    }
} catch (Exception $e) {
    // Capturar cualquier error y devolverlo como JSON en lugar de HTML
    echo json_encode([
        'status' => 'error',
        'message' => 'Error en el servidor: ' . $e->getMessage()
    ]);
}
exit;