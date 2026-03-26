<?php
/**
 * Script para resetear ChromaDB y re-indexar todos los documentos.
 * Debe ejecutarse manualmente cuando se detectan problemas con el metadata.
 */

// Evitar timeout
set_time_limit(300); // 5 minutos
ini_set('max_execution_time', 300);

// Cargar configuración
require_once __DIR__ . '/../config_rag.php';
require_once __DIR__ . '/../Class/Politica.php';

$config = RagConfig::getInstance();

echo "====================================================================\n";
echo "RESETEO Y RE-INDEXACIÓN DE CHROMADB\n";
echo "====================================================================\n\n";

// 1. Resetear ChromaDB
echo "[1/3] Eliminando todos los chunks de ChromaDB...\n";
$reset_url = $config->getRagServiceUrl('/admin/reset-database');
$ch = curl_init($reset_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$reset_response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200) {
    $reset_data = json_decode($reset_response, true);
    echo "  ✓ " . $reset_data['mensaje'] . "\n";
    echo "  ✓ Chunks eliminados: " . $reset_data['chunks_eliminados'] . "\n\n";
} else {
    echo "  ✗ Error al resetear (código HTTP: $http_code)\n";
    echo "  Respuesta: $reset_response\n";
    exit(1);
}

// 2. Obtener documentos de la BD
echo "[2/3] Obteniendo documentos de la base de datos...\n";
$politicaObj = new Politica();
$documentos = $politicaObj->obtenerTodos();
echo "  ✓ Documentos encontrados: " . count($documentos) . "\n\n";

if (empty($documentos)) {
    echo "No hay documentos para indexar.\n";
    exit(0);
}

// 3. Indexar cada documento
echo "[3/3] Indexando documentos...\n";
$webhook_url = $config->getRagServiceUrl('/webhook/document-uploaded');
$documentos_path = $config->getDocumentosPath();

$exitosos = 0;
$fallidos = 0;

foreach ($documentos as $doc) {
    $archivo_path = $documentos_path . "/" . $doc["archivo"];
    
    // Verificar que el archivo existe
    if (!file_exists($archivo_path)) {
        echo "  ✗ {$doc['titulo']}: Archivo no encontrado\n";
        $fallidos++;
        continue;
    }
    
    // Preparar payload
    $data = [
        "document_id" => (int)$doc["id"],
        "ruta_archivo" => $archivo_path,
        "titulo" => $doc["titulo"],
        "sector_nombre" => $doc["sector_nombre"] ?? "General",
        "tipo" => $doc["tipo"]
    ];
    
    // Enviar al webhook
    $ch = curl_init($webhook_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $response_data = json_decode($response, true);
        echo "  ✓ {$doc['titulo']}: {$response_data['chunks_count']} chunks indexados\n";
        $exitosos++;
    } else {
        echo "  ✗ {$doc['titulo']}: Error HTTP $http_code\n";
        $fallidos++;
    }
    
    // Pausa breve entre indexaciones
    usleep(500000); // 0.5 segundos
}

echo "\n====================================================================\n";
echo "RESUMEN\n";
echo "====================================================================\n";
echo "  Documentos indexados exitosamente: $exitosos\n";
echo "  Documentos con errores: $fallidos\n";
echo "  Total procesado: " . ($exitosos + $fallidos) . "/" . count($documentos) . "\n";
echo "====================================================================\n";

