
<?php
// Iniciar sesión para gestión de usuarios (si se implementa)
session_start();

// Incluir la clase Politica
require_once '../Class/Politica.php';

// Configurar encabezados para respuesta JSON
header('Content-Type: application/json');

// Crear instancia de la clase
$politicaObj = new Politica();

// Verificar que se ha proporcionado un ID
if (isset($_GET['id']) && !empty($_GET['id'])) {
    
    $documento_id = (int)$_GET['id'];
    
    // Obtener información del documento
    $documento = $politicaObj->obtenerPorId($documento_id);
    
    if ($documento) {
        // Formatear fechas si es necesario
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
exit;