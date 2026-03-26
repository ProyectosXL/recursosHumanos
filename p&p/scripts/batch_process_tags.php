<?php
/**
 * Script de procesamiento batch para generar tags y términos de glosario
 * Procesa todos los documentos sin tags usando la API de Hugging Face
 * 
 * Uso:
 *   php batch_process_tags.php
 *   php batch_process_tags.php --limit=5
 *   php batch_process_tags.php --sector_id=2
 * 
 * @version 1.0.0
 * @date 2026-01-15
 */

// Configuración de error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Aumentar límite de tiempo de ejecución
set_time_limit(0);

// Incluir clases necesarias
require_once dirname(__FILE__, 4) . '/Class/conexion.php';
require_once dirname(__FILE__, 2) . '/Class/TagsGlosarioAPI.php';

echo "================================================================================\n";
echo "BATCH PROCESSOR - DocuGest Tags & Glossary (Hugging Face API)\n";
echo "================================================================================\n\n";

// Parse command line arguments
$limit = null;
$sector_id = null;

foreach ($argv as $arg) {
    if (strpos($arg, '--limit=') === 0) {
        $limit = (int)str_replace('--limit=', '', $arg);
    }
    if (strpos($arg, '--sector_id=') === 0) {
        $sector_id = (int)str_replace('--sector_id=', '', $arg);
    }
}

echo "Configuración:\n";
echo "- Límite: " . ($limit ? $limit : "Sin límite") . "\n";
echo "- Sector ID: " . ($sector_id ? $sector_id : "Todos") . "\n";
echo "- Delay entre docs: 2 segundos\n\n";

// Conectar a la base de datos
$conexion = new Conexion();
$cid = $conexion->conectar('central');

if (!$cid) {
    die("ERROR: No se pudo conectar a la base de datos\n");
}

// Construir query para obtener documentos sin tags
$sql = "SELECT id, titulo, sector_id, ruta_archivo 
        FROM Politicas_Procedimientos 
        WHERE (tags IS NULL OR tags = '')";

if ($sector_id) {
    $sql .= " AND sector_id = $sector_id";
}

$sql .= " ORDER BY id ASC";

if ($limit) {
    $sql = "SELECT TOP $limit id, titulo, sector_id, ruta_archivo 
            FROM Politicas_Procedimientos 
            WHERE (tags IS NULL OR tags = '')";
    
    if ($sector_id) {
        $sql .= " AND sector_id = $sector_id";
    }
    
    $sql .= " ORDER BY id ASC";
}

echo "📋 Obteniendo documentos...\n";
$stmt = sqlsrv_query($cid, $sql);

if ($stmt === false) {
    die("ERROR: Error en la consulta: " . print_r(sqlsrv_errors(), true) . "\n");
}

// Obtener todos los documentos
$documentos = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $documentos[] = $row;
}

$total_docs = count($documentos);
echo "✓ $total_docs documentos encontrados\n\n";

if ($total_docs === 0) {
    echo "No hay documentos para procesar.\n";
    exit(0);
}

// Obtener glosario existente (una sola vez)
echo "📚 Obteniendo glosario existente...\n";
$sql_glosario = "SELECT termino FROM Glosario";
$stmt_glosario = sqlsrv_query($cid, $sql_glosario);
$glosario_existente = [];

if ($stmt_glosario !== false) {
    while ($row = sqlsrv_fetch_array($stmt_glosario, SQLSRV_FETCH_ASSOC)) {
        $glosario_existente[] = $row['termino'];
    }
}

echo "✓ " . count($glosario_existente) . " términos en el glosario\n\n";

// Inicializar API client
$api = new TagsGlosarioAPI();

// Verificar que la API esté disponible
echo "🔍 Verificando API de Hugging Face...\n";
if (!$api->healthCheck()) {
    echo "⚠️  ADVERTENCIA: El API no responde. El Space puede estar dormido.\n";
    echo "   El primer request puede tomar hasta 2 minutos mientras el Space despierta.\n\n";
} else {
    echo "✓ API disponible\n\n";
}

// Contadores
$procesados = 0;
$tags_generados = 0;
$terminos_nuevos = 0;
$errores = 0;

echo "🚀 Iniciando procesamiento...\n";
echo "================================================================================\n\n";

// Procesar cada documento
foreach ($documentos as $index => $doc) {
    $num = $index + 1;
    $id = $doc['id'];
    $titulo = $doc['titulo'];
    $ruta = $doc['ruta_archivo'];
    
    echo "[$num/$total_docs] Procesando: $titulo (ID: $id)\n";
    
    // Validar que el archivo existe
    if (!file_exists($ruta)) {
        echo "   ❌ Archivo no encontrado: $ruta\n";
        $errores++;
        continue;
    }
    
    try {
        // 1. GENERAR TAGS
        echo "   🏷️  Generando tags...\n";
        $tags_array = $api->generarTags($ruta, 10);
        
        if (count($tags_array) > 0) {
            $tags_string = implode(', ', $tags_array);
            
            // Actualizar en BD
            $sql_update = "UPDATE Politicas_Procedimientos 
                           SET tags = ? 
                           WHERE id = ?";
            $params = [$tags_string, $id];
            $stmt_update = sqlsrv_query($cid, $sql_update, $params);
            
            if ($stmt_update === false) {
                echo "   ⚠️  Error actualizando tags en BD\n";
            } else {
                echo "   ✓ Tags generados: " . substr($tags_string, 0, 60) . "...\n";
                $tags_generados += count($tags_array);
            }
        } else {
            echo "   ⚠️  No se generaron tags\n";
        }
        
        // 2. DETECTAR TÉRMINOS GLOSARIO
        echo "   📚 Detectando términos para glosario...\n";
        $terminos = $api->detectarTerminosGlosario($ruta, $glosario_existente);
        
        if (count($terminos) > 0) {
            $insertados = 0;
            
            foreach ($terminos as $termino_obj) {
                // Verificar duplicado
                $sql_check = "SELECT COUNT(*) as count FROM Glosario 
                             WHERE LOWER(termino) = LOWER(?)";
                $stmt_check = sqlsrv_query($cid, $sql_check, [$termino_obj['termino']]);
                
                if ($stmt_check !== false) {
                    $row_check = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);
                    
                    if ($row_check['count'] == 0) {
                        // Insertar
                        $definicion = isset($termino_obj['definicion']) ? $termino_obj['definicion'] : '';
                        
                        $sql_insert = "INSERT INTO Glosario (termino, definicion, sector_id, fecha_creacion)
                                       VALUES (?, ?, ?, GETDATE())";
                        $params_insert = [$termino_obj['termino'], $definicion, $doc['sector_id']];
                        $stmt_insert = sqlsrv_query($cid, $sql_insert, $params_insert);
                        
                        if ($stmt_insert !== false) {
                            $insertados++;
                            $glosario_existente[] = $termino_obj['termino'];
                        }
                    }
                }
            }
            
            if ($insertados > 0) {
                echo "   ✓ $insertados nuevos términos agregados al glosario\n";
                $terminos_nuevos += $insertados;
            } else {
                echo "   ℹ️  No se insertaron términos nuevos (ya existían)\n";
            }
        } else {
            echo "   ℹ️  No se detectaron términos\n";
        }
        
        $procesados++;
        echo "   ✅ Completado\n\n";
        
        // Sleep entre documentos (no saturar API)
        if ($num < $total_docs) {
            echo "   ⏳ Esperando 2 segundos...\n\n";
            sleep(2);
        }
        
    } catch (Exception $e) {
        echo "   ❌ Error: " . $e->getMessage() . "\n\n";
        $errores++;
    }
}

// Resumen final
echo "================================================================================\n";
echo "RESUMEN FINAL\n";
echo "================================================================================\n";
echo "Total documentos: $total_docs\n";
echo "Procesados exitosamente: $procesados\n";
echo "Errores: $errores\n\n";

echo "TAGS:\n";
echo "  Total tags generados: $tags_generados\n\n";

echo "GLOSARIO:\n";
echo "  Nuevos términos insertados: $terminos_nuevos\n\n";

echo "================================================================================\n";
echo "✅ Procesamiento completado\n";
echo "================================================================================\n";

// Cerrar conexión
sqlsrv_close($cid);
?>
