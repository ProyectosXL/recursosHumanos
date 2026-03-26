<?php
/**
 * Script de indexación automática de PDFs desde carpeta /documentos
 * 
 * Este script escanea la carpeta /documentos, encuentra todos los PDFs
 * y los indexa automáticamente en el servicio RAG.
 * 
 * Uso: Ejecutar directamente desde navegador
 */

// Configuración
set_time_limit(600); // 10 minutos máximo
ini_set('memory_limit', '512M');

// URL del servicio RAG
$RAG_SERVICE_URL = 'http://localhost:8000';

// Ruta a la carpeta de documentos
$DOCUMENTOS_DIR = dirname(__FILE__, 2) . '/documentos/';

// Función para enviar al servicio RAG
function indexarPDFDirecto($archivo_path, $rag_url) {
    $webhook_url = $rag_url . '/webhook/document-uploaded';
    
    // Extraer información del nombre del archivo
    $filename = basename($archivo_path);
    $titulo = pathinfo($filename, PATHINFO_FILENAME);
    
    // Generar un document_id único basado en el hash del archivo
    $document_id = abs(crc32($archivo_path));
    
    // Preparar datos para el webhook
    $payload = [
        'document_id' => $document_id,
        'titulo' => $titulo,
        'tipo' => 'politica', // Por defecto
        'sector_nombre' => 'General',
        'ruta_archivo' => $archivo_path,
        'descripcion' => 'Documento indexado automáticamente',
        'tags' => '',
        'fecha_creacion' => date('Y-m-d')
    ];
    
    // Configurar cURL
    $ch = curl_init($webhook_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen(json_encode($payload))
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // Ejecutar y capturar respuesta
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($http_code === 200) {
        return ['success' => true, 'response' => json_decode($response, true)];
    } else {
        return ['success' => false, 'error' => $error ?: "HTTP $http_code", 'response' => $response];
    }
}

// Buscar todos los PDFs en la carpeta
$pdfs = glob($DOCUMENTOS_DIR . '*.pdf');
$total = count($pdfs);
$exitosos = 0;
$fallidos = 0;
$errores = [];

?>
<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Indexación Automática de PDFs</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1000px;
            margin: 40px auto;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 32px;
        }
        .subtitle {
            color: #7f8c8d;
            margin-bottom: 30px;
            font-size: 16px;
        }
        .folder-info {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 30px;
            border-radius: 6px;
        }
        .folder-info code {
            background: #e9ecef;
            padding: 2px 8px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .stat-card.total {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .stat-card.success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }
        .stat-card.error {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
            color: white;
        }
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 8px;
        }
        .stat-label {
            font-size: 14px;
            opacity: 0.95;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .progress-container {
            background: #e9ecef;
            border-radius: 12px;
            height: 40px;
            margin-bottom: 30px;
            overflow: hidden;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }
        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            transition: width 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 16px;
        }
        .log-container {
            max-height: 450px;
            overflow-y: auto;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #dee2e6;
        }
        .log-item {
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        .log-item.success {
            background: #d4edda;
            border-left: 4px solid #28a745;
        }
        .log-item.error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        .log-icon {
            font-size: 20px;
        }
        .log-text {
            flex: 1;
            font-size: 14px;
        }
        .log-text strong {
            color: #495057;
        }
        .log-text em {
            color: #6c757d;
            font-style: normal;
        }
        .btn {
            display: inline-block;
            padding: 14px 28px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 25px;
            transition: all 0.3s;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🚀 Indexación Automática de PDFs</h1>
        <p class='subtitle'>Servicio RAG - DocuGest</p>
        
        <div class='folder-info'>
            <strong>📁 Carpeta:</strong> <code><?php echo $DOCUMENTOS_DIR; ?></code><br>
            <strong>📄 PDFs encontrados:</strong> <?php echo $total; ?>
        </div>
        
        <div class='stats'>
            <div class='stat-card total'>
                <div class='stat-number'><?php echo $total; ?></div>
                <div class='stat-label'>Total PDFs</div>
            </div>
            <div class='stat-card success'>
                <div class='stat-number' id='success-count'>0</div>
                <div class='stat-label'>Indexados</div>
            </div>
            <div class='stat-card error'>
                <div class='stat-number' id='error-count'>0</div>
                <div class='stat-label'>Errores</div>
            </div>
        </div>
        
        <div class='progress-container'>
            <div class='progress-bar' id='progress-bar' style='width: 0%'>0%</div>
        </div>
        
        <div class='log-container' id='log-container'>

<?php

if ($total === 0) {
    echo "<div class='log-item error'>
            <span class='log-icon'>⚠️</span>
            <span class='log-text'><strong>No se encontraron archivos PDF en la carpeta /documentos</strong></span>
          </div>";
} else {
    // Procesar cada PDF
    foreach ($pdfs as $index => $pdf_path) {
        $numero = $index + 1;
        $filename = basename($pdf_path);
        
        // Verificar que el archivo existe
        if (!file_exists($pdf_path)) {
            echo "<div class='log-item error'>
                    <span class='log-icon'>❌</span>
                    <span class='log-text'><strong>[$numero/$total]</strong> $filename - <em>Archivo no encontrado</em></span>
                  </div>";
            echo "<script>
                    document.getElementById('error-count').textContent = " . (++$fallidos) . ";
                    document.getElementById('progress-bar').style.width = '" . (($numero / $total) * 100) . "%';
                    document.getElementById('progress-bar').textContent = '" . round(($numero / $total) * 100) . "%';
                    document.getElementById('log-container').scrollTop = document.getElementById('log-container').scrollHeight;
                  </script>";
            flush();
            ob_flush();
            continue;
        }
        
        // Enviar al servicio RAG
        $resultado = indexarPDFDirecto($pdf_path, $RAG_SERVICE_URL);
        
        if ($resultado['success']) {
            $chunks = isset($resultado['response']['chunks_count']) ? $resultado['response']['chunks_count'] : 0;
            echo "<div class='log-item success'>
                    <span class='log-icon'>✅</span>
                    <span class='log-text'><strong>[$numero/$total]</strong> $filename - <em>$chunks chunks indexados</em></span>
                  </div>";
            $exitosos++;
        } else {
            $error_msg = htmlspecialchars($resultado['error']);
            echo "<div class='log-item error'>
                    <span class='log-icon'>❌</span>
                    <span class='log-text'><strong>[$numero/$total]</strong> $filename - <em>Error: $error_msg</em></span>
                  </div>";
            $fallidos++;
            $errores[] = ['documento' => $filename, 'error' => $resultado['error']];
        }
        
        // Actualizar estadísticas y progreso
        echo "<script>
                document.getElementById('success-count').textContent = $exitosos;
                document.getElementById('error-count').textContent = $fallidos;
                document.getElementById('progress-bar').style.width = '" . (($numero / $total) * 100) . "%';
                document.getElementById('progress-bar').textContent = '" . round(($numero / $total) * 100) . "%';
                document.getElementById('log-container').scrollTop = document.getElementById('log-container').scrollHeight;
              </script>";
        
        flush();
        ob_flush();
        
        // Pequeña pausa para no saturar el servicio
        usleep(200000); // 200ms
    }
}

echo "      </div>
        
        <h3 style='margin-top: 30px; color: #2c3e50;'>📊 Resumen Final</h3>
        <ul style='background: #f8f9fa; padding: 20px; border-radius: 8px; list-style: none;'>
            <li style='margin-bottom: 10px;'><strong>📁 Carpeta escaneada:</strong> $DOCUMENTOS_DIR</li>
            <li style='margin-bottom: 10px;'><strong>📄 Total procesados:</strong> $total archivos PDF</li>
            <li style='color: #27ae60; margin-bottom: 10px;'><strong>✅ Exitosos:</strong> $exitosos</li>
            <li style='color: #e74c3c; margin-bottom: 10px;'><strong>❌ Fallidos:</strong> $fallidos</li>
            <li><strong>📈 Tasa de éxito:</strong> " . ($total > 0 ? round(($exitosos / $total) * 100, 2) : 0) . "%</li>
        </ul>";

if (!empty($errores)) {
    echo "<h4 style='color: #e74c3c; margin-top: 20px;'>⚠️ Detalles de Errores</h4>
          <ul style='background: #fff3cd; padding: 20px; border-radius: 8px; border-left: 4px solid #ffc107;'>";
    foreach ($errores as $error) {
        echo "<li style='margin-bottom: 8px;'><strong>" . htmlspecialchars($error['documento']) . ":</strong> " . htmlspecialchars($error['error']) . "</li>";
    }
    echo "</ul>";
}

echo "  <div style='text-align: center;'>
            <a href='../index.php' class='btn'>← Volver al inicio</a>
        </div>
    </div>
</body>
</html>";
?>
