<?php
// Llamada servidor a servidor (servicio de IA / trigger_refresh): exige X-DocuGest-Token
require_once __DIR__ . '/../Class/TokenApiDocuGest.php';
TokenApiDocuGest::exigir();

require_once __DIR__ . '/../Class/Politica.php';

try {
    if (!isset($_GET['id'])) {
        http_response_code(400);
        die(json_encode(['error' => 'ID requerido']));
    }
    
    $politicaObj = new Politica();
    // Solo documentos activos y siempre el PDF vigente (nunca versiones archivadas)
    $doc = $politicaObj->obtenerPorId((int)$_GET['id'], false, true);
    
    if (!$doc) {
        http_response_code(404);
        die(json_encode(['error' => 'Documento no encontrado']));
    }
    
    $archivo = dirname(__FILE__, 2) . '/documentos/' . basename($doc['archivo_nombre']);
    
    if (!file_exists($archivo)) {
        http_response_code(404);
        die(json_encode(['error' => 'Archivo no existe']));
    }
    
    header('Content-Type: application/pdf');
    header('Content-Length: ' . filesize($archivo));
    header('Content-Disposition: inline; filename="' . $doc['archivo_nombre'] . '"');
    readfile($archivo);
    
} catch (Exception $e) {
    http_response_code(500);
    die(json_encode(['error' => $e->getMessage()]));
}