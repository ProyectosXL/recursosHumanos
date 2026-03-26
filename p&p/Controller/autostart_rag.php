<?php
/**
 * Script de auto-inicio del servicio RAG y auto-indexación de documentos.
 * Se ejecuta automáticamente al cargar el index.php del sistema.
 * Funciona tanto en local (Windows) como en producción (Linux).
 */

header('Content-Type: application/json');

// Cargar configuración
require_once __DIR__ . '/../config_rag.php';
$config = RagConfig::getInstance();

$response = [
    'success' => false,
    'servicio_iniciado' => false,
    'documentos_indexados' => false,
    'mensaje' => '',
    'detalles' => [],
    'environment' => $config->get('environment')
];

try {
    // Si RAG no está habilitado, salir
    if (!$config->isRagEnabled()) {
        $response['success'] = true;
        $response['mensaje'] = "Servicio RAG deshabilitado en configuración";
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    // ============================================================================
    // 1. VERIFICAR SI EL SERVICIO RAG ESTÁ CORRIENDO
    // ============================================================================
    
    $rag_url = $config->getRagServiceUrl('/health');
    $ch = curl_init($rag_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    $health_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    $servicio_corriendo = ($http_code === 200);
    
    // Detectar si el HF Space puede estar dormido (producción)
    if (!$servicio_corriendo && $config->isProduction()) {
        $response['detalles'][] = "HF Space posiblemente dormido (HTTP $http_code). El cliente JS reintentará automáticamente.";
        $response['hf_sleeping'] = true;
    }
    
    if (!$servicio_corriendo && $config->shouldAutoStart()) {
        $response['detalles'][] = "Servicio RAG no está corriendo, iniciando en segundo plano...";
        
        // ============================================================================
        // 2. INICIAR EL SERVICIO RAG (ADAPTA A WINDOWS O LINUX)
        // ============================================================================
        
        $rag_service_dir = realpath(__DIR__ . '/../rag-service');
        $is_windows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
        
        if ($is_windows) {
            // ==================== WINDOWS ====================
            $python_exe = $config->get('python_path');
            
            if (file_exists($python_exe) && is_dir($rag_service_dir)) {
                // Crear VBScript para ejecutar sin ventana
                $temp_vbs = tempnam(sys_get_temp_dir(), 'rag_') . '.vbs';
                $vbs_content = "Set WshShell = CreateObject(\"WScript.Shell\")\n";
                $vbs_content .= "WshShell.CurrentDirectory = \"" . $rag_service_dir . "\"\n";
                $vbs_content .= "WshShell.Run \"\"\"" . $python_exe . "\"\" -m uvicorn app.main:app --reload --port 8001\", 0, False\n";
                
                file_put_contents($temp_vbs, $vbs_content);
                
                // Ejecutar sin ventana
                exec("wscript //nologo \"$temp_vbs\" > nul 2>&1");
                
                $response['servicio_iniciado'] = true;
                $response['detalles'][] = "Servicio RAG iniciándose en Windows (10-15 segundos)";
            } else {
                $response['detalles'][] = "Error: Python no encontrado en $python_exe";
            }
            
        } else {
            // ==================== LINUX ====================
            $python_exe = $config->get('python_path', 'python3');
            
            if (is_dir($rag_service_dir)) {
                // Script de inicio para Linux (daemon simple)
                $start_script = $rag_service_dir . '/start_production.sh';
                
                // Crear script si no existe
                if (!file_exists($start_script)) {
                    $script_content = "#!/bin/bash\n";
                    $script_content .= "cd \"$rag_service_dir\"\n";
                    $script_content .= "$python_exe -m uvicorn app.main:app --host 0.0.0.0 --port 8001 > /dev/null 2>&1 &\n";
                    $script_content .= "echo \$! > /tmp/rag_service.pid\n";
                    
                    file_put_contents($start_script, $script_content);
                    chmod($start_script, 0755);
                }
                
                // Ejecutar en background
                exec("bash \"$start_script\" > /dev/null 2>&1 &");
                
                $response['servicio_iniciado'] = true;
                $response['detalles'][] = "Servicio RAG iniciándose en Linux (10-15 segundos)";
            } else {
                $response['detalles'][] = "Error: Directorio RAG no encontrado";
            }
        }
    } else {
        $response['servicio_iniciado'] = true;
        $response['detalles'][] = "Servicio RAG ya estaba corriendo";
        
        // ============================================================================
        // 3. AUTO-INDEXACIÓN INTELIGENTE - Verificar PDFs de carpeta /documentos por título
        // ============================================================================
        
        if ($config->shouldAutoIndex()) {
            // Esperar un momento para asegurar que el servicio esté listo
            sleep(2);
            
            $response['detalles'][] = "Verificando documentos en carpeta /documentos...";
            
            // Verificar documentos faltantes por título de archivo
            $verificar_url = 'http://localhost/administracion/recursosHumanos/p&p/Controller/verificar_documentos_faltantes.php';
            $ch = curl_init($verificar_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $verificar_response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code !== 200) {
                $response['detalles'][] = "Error al verificar documentos faltantes (HTTP $http_code)";
            } else {
                $verificar_data = json_decode($verificar_response, true);
                
                if ($verificar_data['success']) {
                    $total_pdfs = $verificar_data['total_pdfs'];
                    $indexados = $verificar_data['indexados'];
                    $faltantes = $verificar_data['faltantes'];
                    
                    $response['detalles'][] = "PDFs en carpeta: $total_pdfs | Indexados: $indexados | Faltantes: $faltantes";
                    
                    if ($faltantes > 0) {
                        // Mostrar qué documentos faltan
                        $nombres_faltantes = array_column($verificar_data['documentos_faltantes'], 'titulo_esperado');
                        $response['detalles'][] = "Documentos sin indexar: " . implode(', ', array_slice($nombres_faltantes, 0, 3)) . ($faltantes > 3 ? "... (+" . ($faltantes - 3) . " más)" : "");
                        
                        // Lanzar indexación en segundo plano
                        $indexer_url = 'http://localhost/administracion/recursosHumanos/p&p/Controller/indexar_pdfs_faltantes.php';
                        
                        $ch = curl_init($indexer_url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500);
                        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
                        
                        curl_exec($ch);
                        curl_close($ch);
                        
                        $response['documentos_indexados'] = true;
                        $response['detalles'][] = "Indexación de $faltantes documentos iniciada en segundo plano";
                    } else {
                        $response['documentos_indexados'] = false;
                        $response['detalles'][] = "Todos los documentos ya están indexados ✓";
                    }
                } else {
                    $response['detalles'][] = "Error al verificar documentos: " . $verificar_data['mensaje'];
                }
            }
        }
    }
    
    // ============================================================================
    // 4. RESULTADO FINAL
    // ============================================================================
    
    $response['success'] = true;
    $response['mensaje'] = $servicio_corriendo 
        ? "Sistema RAG operativo" 
        : "Sistema RAG iniciándose en segundo plano";
    
} catch (Exception $e) {
    $response['mensaje'] = "Error: " . $e->getMessage();
    $response['detalles'][] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
