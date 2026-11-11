<?php
// Acceso: login del hub + nivel Editar
require_once __DIR__ . '/../Class/AccesoDocuGest.php';
AccesoDocuGest::exigirEditar(AccesoDocuGest::JSON);

// Incluir la clase Politica
require_once __DIR__ . '/../Class/Politica.php';

// Configurar encabezados para respuesta JSON
header('Content-Type: application/json');

// Crear instancia de la clase
$politicaObj = new Politica();

// Verificar que se ha proporcionado un ID
if (isset($_GET['id']) && !empty($_GET['id'])) {
    
    $documento_id = (int)$_GET['id'];
    
    // Obtener historial de versiones
    $historial = $politicaObj->obtenerHistorialVersiones($documento_id);
    
    // Devolver respuesta exitosa
    echo json_encode([
        'status' => 'success',
        'historial' => $historial
    ]);
    
} else {
    // ID no proporcionado
    echo json_encode([
        'status' => 'error',
        'message' => 'ID de documento no especificado'
    ]);
}
exit;