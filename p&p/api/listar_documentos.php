<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once '../Class/Politica.php';

try {
    $politicaObj = new Politica();
    $documentos = $politicaObj->obtenerTodos();
    
    $respuesta = [];
    foreach ($documentos as $doc) {
        $respuesta[] = [
            'id' => (int)$doc['id'],
            'titulo' => $doc['titulo'],
            'archivo_nombre' => $doc['archivo_nombre'],
            'sector' => $doc['sector_nombre'],
            'tipo' => $doc['tipo'],
            'ruta_archivo' => $doc['ruta_archivo']
        ];
    }
    
    echo json_encode(['success' => true, 'documentos' => $respuesta]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}