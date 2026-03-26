<?php
/**
 * Búsqueda avanzada en documentos
 * Busca en contenido de PDFs y en tags
 */

// Deshabilitar mostrar errores en HTML
ini_set('display_errors', 0);
error_reporting(0);

// Configurar header JSON primero
header('Content-Type: application/json; charset=utf-8');

// Función para enviar respuesta JSON y terminar
function sendJsonResponse($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Capturar cualquier error fatal
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && ($error['type'] === E_ERROR || $error['type'] === E_PARSE)) {
        error_log("Error fatal en buscar_documentos.php: " . print_r($error, true));
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'status' => 'error',
            'message' => 'Error interno del servidor',
            'resultados' => []
        ], JSON_UNESCAPED_UNICODE);
    }
});

try {
    require_once __DIR__ . '/../../../class/conexion.php';
} catch (Exception $e) {
    sendJsonResponse([
        'status' => 'error',
        'message' => 'Error al cargar archivos necesarios: ' . $e->getMessage(),
        'resultados' => []
    ]);
}

// Obtener término de búsqueda
$searchTerm = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($searchTerm) || strlen($searchTerm) < 2) {
    sendJsonResponse([
        'status' => 'error',
        'message' => 'El término de búsqueda debe tener al menos 2 caracteres',
        'resultados' => []
    ]);
}

try {
    // Conectar a la base de datos
    $conexionObj = new Conexion();
    $conexion = $conexionObj->conectar('central');
    
    if (!$conexion) {
        throw new Exception('Error al conectar con la base de datos');
    }
    
    // Obtener todos los documentos con sus tags
    $sql = "SELECT id, titulo, sector_id, tipo, tags, ruta_archivo, archivo_nombre 
            FROM Politicas_Procedimientos 
            ORDER BY titulo ASC";
    
    $stmt = sqlsrv_query($conexion, $sql);
    
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        error_log("Error SQL en buscar_documentos: " . print_r($errors, true));
        throw new Exception('Error al consultar documentos');
    }
    
    $resultados = [];
    $searchTermLower = mb_strtolower($searchTerm, 'UTF-8');
    $todosDocumentos = [];

    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $todosDocumentos[] = $row;
    }

    // Buscar en título + tags para todos los documentos
    foreach ($todosDocumentos as $row) {
        $coincidencias = [];
        $puntuacion = 0;

        // --- Coincidencia en título ---
        $tituloLower = mb_strtolower($row['titulo'], 'UTF-8');
        if (strpos($tituloLower, $searchTermLower) !== false) {
            $tituloResaltado = preg_replace(
                '/(' . preg_quote($searchTerm, '/') . ')/iu',
                '<strong>$1</strong>',
                htmlspecialchars($row['titulo'])
            );
            $coincidencias[] = [
                'tipo' => 'titulo',
                'texto' => $tituloResaltado
            ];
            $puntuacion += 20; // Máxima prioridad para coincidencia en título
        }

        // --- Coincidencia en tags ---
        if (!empty($row['tags'])) {
            $tags = array_map('trim', explode(',', $row['tags']));
            $hayTagCoincidente = false;
            $tagsHtml = [];

            foreach ($tags as $tag) {
                $tagLower = mb_strtolower($tag, 'UTF-8');
                if (strpos($tagLower, $searchTermLower) !== false) {
                    $tagResaltado = preg_replace(
                        '/(' . preg_quote($searchTerm, '/') . ')/iu',
                        '<strong>$1</strong>',
                        htmlspecialchars($tag)
                    );
                    $tagsHtml[] = $tagResaltado;
                    $hayTagCoincidente = true;
                    $puntuacion += 10;
                } else {
                    $tagsHtml[] = htmlspecialchars($tag);
                }
            }

            if ($hayTagCoincidente) {
                $coincidencias[] = [
                    'tipo' => 'tag',
                    'texto' => 'Tags: ' . implode(', ', $tagsHtml)
                ];
            }
        }

        // Solo agregar si tiene al menos una coincidencia
        if (!empty($coincidencias)) {
            $resultados[] = [
                'id'          => $row['id'],
                'titulo'      => $row['titulo'],
                'tipo'        => $row['tipo'],
                'archivo'     => $row['archivo_nombre'],
                'coincidencias' => $coincidencias,
                'puntuacion'  => $puntuacion
            ];
        }
    }
    
    // BÚSQUEDA EN CONTENIDO DE PDF DESHABILITADA TEMPORALMENTE
    // La extracción de texto de PDFs es muy lenta sin herramientas especializadas
    // TODO: Implementar búsqueda full-text con indexación o usar herramientas como pdftotext
    /*
    // 2. Solo si hay pocos resultados, buscar en contenido de PDFs (lento)
    // Limitar a los primeros 10 documentos para evitar timeouts
    if (count($resultados) < 5) {
        $todosDocumentos = array_merge($documentosConTags, $documentosSinTags);
        $limite = min(10, count($todosDocumentos));
        
        for ($i = 0; $i < $limite; $i++) {
            $row = $todosDocumentos[$i];
            
            // Saltar si ya tiene coincidencias en tags
            $yaEnResultados = false;
            foreach ($resultados as $resultado) {
                if ($resultado['id'] === $row['id']) {
                    $yaEnResultados = true;
                    break;
                }
            }
            if ($yaEnResultados) continue;
            
            // Buscar en contenido del PDF
            if (!empty($row['ruta_archivo']) && file_exists($row['ruta_archivo'])) {
                try {
                    // Extraer texto del PDF
                    $contenidoPdf = extraerTextoPDF($row['ruta_archivo']);
                    
                    if (!empty($contenidoPdf)) {
                        $contenidoLower = mb_strtolower($contenidoPdf, 'UTF-8');
                        
                        if (strpos($contenidoLower, $searchTermLower) !== false) {
                            // Encontrar contexto alrededor de las coincidencias (máximo 2)
                            $fragmentos = extraerFragmentos($contenidoPdf, $searchTerm, 2);
                            $coincidencias = [];
                            $puntuacion = 0;
                            
                            foreach ($fragmentos as $fragmento) {
                                $coincidencias[] = [
                                    'tipo' => 'contenido',
                                    'texto' => $fragmento
                                ];
                                $puntuacion += 5;
                            }
                            
                            if (!empty($coincidencias)) {
                                $resultados[] = [
                                    'id' => $row['id'],
                                    'titulo' => $row['titulo'],
                                    'tipo' => $row['tipo'],
                                    'archivo' => $row['archivo_nombre'],
                                    'coincidencias' => $coincidencias,
                                    'puntuacion' => $puntuacion
                                ];
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Si falla la extracción, continuar
                    error_log("Error extrayendo PDF {$row['id']}: " . $e->getMessage());
                }
            }
        }
    }
    */
    
    // Ordenar por puntuación descendente
    usort($resultados, function($a, $b) {
        return $b['puntuacion'] - $a['puntuacion'];
    });
    
    sqlsrv_close($conexion);
    
    sendJsonResponse([
        'status' => 'success',
        'resultados' => $resultados,
        'total' => count($resultados),
        'termino' => $searchTerm
    ]);
    
} catch (Exception $e) {
    error_log("Error en búsqueda avanzada: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    if (isset($conexion)) {
        sqlsrv_close($conexion);
    }
    
    sendJsonResponse([
        'status' => 'error',
        'message' => 'Error al procesar la búsqueda',
        'resultados' => []
    ]);
}

/**
 * Extraer texto de un PDF
 */
function extraerTextoPDF($rutaArchivo) {
    // Método 1: Intentar con Python + PyMuPDF (mejor opción si está disponible)
    $pythonScript = dirname(__FILE__) . '/../scripts/extract_pdf_text.py';
    
    if (file_exists($pythonScript) && function_exists('exec')) {
        $escapedPath = escapeshellarg($rutaArchivo);
        $output = [];
        $returnVar = 0;
        
        // Intentar con python3 primero, luego python
        exec("python3 \"$pythonScript\" $escapedPath 2>&1", $output, $returnVar);
        
        if ($returnVar !== 0 || empty($output)) {
            exec("python \"$pythonScript\" $escapedPath 2>&1", $output, $returnVar);
        }
        
        if ($returnVar === 0 && !empty($output)) {
            return implode("\n", $output);
        }
    }
    
    // Método 2: PHP puro - extracción básica
    return extraerTextoPDFPhp($rutaArchivo);
}

/**
 * Extraer texto de PDF usando PHP puro (método básico)
 */
function extraerTextoPDFPhp($rutaArchivo) {
    $content = @file_get_contents($rutaArchivo);
    
    if ($content === false) {
        return '';
    }
    
    // Decodificar contenido del PDF
    $texto = '';
    
    // Buscar objetos de texto en el PDF
    // Los PDFs almacenan texto entre paréntesis o en formato hexadecimal
    
    // Método 1: Extraer texto entre paréntesis
    if (preg_match_all('/\(([^)]*)\)/s', $content, $matches)) {
        foreach ($matches[1] as $match) {
            // Decodificar secuencias de escape
            $match = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $match);
            $texto .= $match . ' ';
        }
    }
    
    // Método 2: Buscar contenido en streams de texto
    if (preg_match_all('/stream\s*\n(.*?)\nendstream/s', $content, $streams)) {
        foreach ($streams[1] as $stream) {
            // Intentar descomprimir si está comprimido
            $decompressed = @gzuncompress($stream);
            if ($decompressed !== false) {
                $stream = $decompressed;
            }
            
            // Extraer texto visible
            if (preg_match_all('/\[(.*?)\]/s', $stream, $textMatches)) {
                foreach ($textMatches[1] as $textMatch) {
                    $texto .= $textMatch . ' ';
                }
            }
            
            if (preg_match_all('/\(([^)]*)\)/s', $stream, $textMatches)) {
                foreach ($textMatches[1] as $textMatch) {
                    $texto .= $textMatch . ' ';
                }
            }
        }
    }
    
    // Limpiar el texto extraído
    $texto = preg_replace('/[^\x20-\x7E\xA0-\xFF\n\r\t]/u', '', $texto);
    $texto = preg_replace('/\s+/', ' ', $texto);
    $texto = trim($texto);
    
    return $texto;
}

/**
 * Extraer fragmentos de texto alrededor de las coincidencias
 */
function extraerFragmentos($texto, $searchTerm, $maxFragmentos = 3) {
    $fragmentos = [];
    $searchTermLower = mb_strtolower($searchTerm, 'UTF-8');
    $textoLower = mb_strtolower($texto, 'UTF-8');
    
    $offset = 0;
    $contador = 0;
    
    while ($contador < $maxFragmentos && ($pos = mb_strpos($textoLower, $searchTermLower, $offset)) !== false) {
        // Extraer contexto (100 caracteres antes y después)
        $inicio = max(0, $pos - 100);
        $longitud = min(mb_strlen($texto) - $inicio, 200 + mb_strlen($searchTerm));
        
        $fragmento = mb_substr($texto, $inicio, $longitud);
        
        // Limpiar el fragmento
        $fragmento = preg_replace('/\s+/', ' ', $fragmento);
        $fragmento = trim($fragmento);
        
        // Agregar puntos suspensivos si es necesario
        if ($inicio > 0) {
            $fragmento = '...' . $fragmento;
        }
        if ($inicio + $longitud < mb_strlen($texto)) {
            $fragmento = $fragmento . '...';
        }
        
        // Resaltar el término encontrado (case insensitive)
        $fragmentoResaltado = preg_replace(
            '/(' . preg_quote($searchTerm, '/') . ')/iu',
            '<strong>$1</strong>',
            htmlspecialchars($fragmento)
        );
        
        $fragmentos[] = $fragmentoResaltado;
        
        $offset = $pos + mb_strlen($searchTerm);
        $contador++;
    }
    
    return $fragmentos;
}
?>
