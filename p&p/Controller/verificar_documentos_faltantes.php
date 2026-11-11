<?php
// Acceso DocuGest: login nomás (lo llama el Asistente IA de cualquier usuario)
require_once __DIR__ . '/../Class/AccesoDocuGest.php';
AccesoDocuGest::exigirLogin(AccesoDocuGest::JSON);

/**
 * Endpoint para verificar qué documentos de PRODUCCION
 * no están indexados en el servicio RAG por su título.
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
    
    // 2. Obtener lista de documentos desde PRODUCCION
    $source_base_url = rtrim($config->getIndexSourceUrl(), '/');
    $listar_url = $source_base_url . '/api/listar_documentos.php';

    $ch = curl_init($listar_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $listar_response = curl_exec($ch);
    $listar_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($listar_http_code !== 200) {
        $response['mensaje'] = "No se pudo obtener listado de producción";
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    $listar_data = json_decode($listar_response, true);
    if (!$listar_data || !($listar_data['success'] ?? false) || !isset($listar_data['documentos'])) {
        $response['mensaje'] = "Listado de producción inválido";
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    $documentos_produccion = $listar_data['documentos'];
    $response['total_pdfs'] = count($documentos_produccion);
    
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
    foreach ($documentos_produccion as $doc_prod) {
        $titulo_documento = trim((string)($doc_prod['titulo'] ?? ''));
        if ($titulo_documento === '') {
            continue;
        }
        $encontrado = false;
        
        // Buscar por coincidencia de título (flexible)
        foreach ($documentos_indexados as $titulo_indexado => $chunks) {
            // Normalizar título indexado para comparación
            $titulo_indexado_normalizado = strtolower(trim($titulo_indexado));
            $titulo_archivo_normalizado = strtolower($titulo_documento);
            
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
                'id' => (int)($doc_prod['id'] ?? 0),
                'filename' => $doc_prod['archivo_nombre'] ?? '',
                'titulo_esperado' => $titulo_documento
            ];
        }
    }
    
    $response['success'] = true;
    $response['indexados'] = count($documentos_produccion) - count($documentos_faltantes);
    $response['faltantes'] = count($documentos_faltantes);
    $response['documentos_faltantes'] = $documentos_faltantes;
    $response['mensaje'] = count($documentos_faltantes) > 0 
        ? "Se encontraron " . count($documentos_faltantes) . " documentos sin indexar"
        : "Todos los documentos están indexados";
    
} catch (Exception $e) {
    $response['mensaje'] = "Error: " . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
