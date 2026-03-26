<?php
/**
 * Endpoint para verificar qué documentos PDF de la carpeta /documentos
 * no están indexados en el servicio RAG por su título
 */

header('Content-Type: application/json');

// Configuración
require_once __DIR__ . '/../config_rag.php';
$config = RagConfig::getInstance();

$response = [
    'success' => false,
    'total_pdfs' => 0,
    'indexados' => 0,
    'faltantes' => 0,
    'documentos_faltantes' => [],
    'mensaje' => ''
];

try {
    // Si RAG no está habilitado, salir
    if (!$config->isRagEnabled()) {
        $response['success'] = true;
        $response['mensaje'] = "Servicio RAG deshabilitado";
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    // 1. Verificar si el servicio RAG está corriendo
    $rag_url = $config->getRagServiceUrl('/health');
    $ch = curl_init($rag_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
    $health_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        $response['mensaje'] = "Servicio RAG no está disponible";
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    // 2. Obtener lista de PDFs en la carpeta /documentos
    $documentos_dir = dirname(__FILE__, 2) . '/documentos/';
    $pdfs = glob($documentos_dir . '*.pdf');
    $response['total_pdfs'] = count($pdfs);
    
    // Extraer títulos de archivos (sin extensión ni timestamp)
    $titulos_archivos = [];
    foreach ($pdfs as $pdf_path) {
        $filename = basename($pdf_path, '.pdf');
        // Remover timestamp (números al final antes de la extensión)
        $titulo_limpio = preg_replace('/-\d+$/', '', $filename);
        // Normalizar: convertir guiones a espacios y capitalizar
        $titulo_normalizado = ucwords(str_replace('-', ' ', $titulo_limpio));
        $titulos_archivos[$pdf_path] = [
            'filename' => basename($pdf_path),
            'titulo_limpio' => $titulo_limpio,
            'titulo_normalizado' => $titulo_normalizado
        ];
    }
    
    // 3. Obtener documentos indexados desde el servicio RAG
    $stats_url = $config->getRagServiceUrl('/api/stats');
    $ch = curl_init($stats_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $stats_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        $response['mensaje'] = "No se pudo obtener estadísticas del RAG";
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    $stats_data = json_decode($stats_response, true);
    $documentos_indexados = $stats_data['documents_by_title'] ?? [];
    
    // 4. Comparar títulos de archivos con documentos indexados
    $documentos_faltantes = [];
    foreach ($titulos_archivos as $pdf_path => $info) {
        $encontrado = false;
        
        // Buscar por coincidencia de título (flexible)
        foreach ($documentos_indexados as $titulo_indexado => $chunks) {
            // Normalizar título indexado para comparación
            $titulo_indexado_normalizado = strtolower(trim($titulo_indexado));
            $titulo_archivo_normalizado = strtolower(trim($info['titulo_normalizado']));
            
            // Comparar versiones normalizadas
            if ($titulo_indexado_normalizado === $titulo_archivo_normalizado ||
                strpos($titulo_indexado_normalizado, $titulo_archivo_normalizado) !== false ||
                strpos($titulo_archivo_normalizado, $titulo_indexado_normalizado) !== false) {
                $encontrado = true;
                break;
            }
        }
        
        if (!$encontrado) {
            $documentos_faltantes[] = [
                'ruta' => $pdf_path,
                'filename' => $info['filename'],
                'titulo_esperado' => $info['titulo_normalizado']
            ];
        }
    }
    
    $response['success'] = true;
    $response['indexados'] = count($pdfs) - count($documentos_faltantes);
    $response['faltantes'] = count($documentos_faltantes);
    $response['documentos_faltantes'] = $documentos_faltantes;
    $response['mensaje'] = count($documentos_faltantes) > 0 
        ? "Se encontraron " . count($documentos_faltantes) . " documentos sin indexar"
        : "Todos los documentos están indexados";
    
} catch (Exception $e) {
    $response['mensaje'] = "Error: " . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
