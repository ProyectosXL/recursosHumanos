<?php

/**
 * API Client for DocuGest Tags & Glossary Generation (Hugging Face Space)
 * 
 * Handles communication with the Hugging Face Space API v3.0:
 * - ML Engineering architecture with 7 features
 * - 0 False Positives (100% corporativos rechazados)
 * - Automatic tag generation from PDF documents
 * - Glossary term detection with context
 * 
 * IMPORTANT: Hugging Face Spaces go to "sleep" after inactivity.
 * First request may timeout - automatic retry mechanism included.
 * 
 * @version 3.0.0
 * @date 2026-01-20
 */
class TagsGlosarioAPI
{
    /**
     * Base URL of the Hugging Face Space
     * Format: https://[USERNAME]-[SPACE-NAME].hf.space
     * 
     * Configurado para: cfedetrejo
     */
    private $api_url = 'https://cfedetrejo-docugest-unified.hf.space';
    
    /**
     * Timeout for API requests (in seconds)
     * Longer timeout for first request (Space may be sleeping)
     */
    private $timeout_first_request = 180;  // 3 minutes (HF Space wake-up)
    private $timeout_normal = 60;          // 1 minute
    
    /**
     * Maximum number of retry attempts
     */
    private $max_retries = 2;
    
    /**
     * Delay between retries (in seconds)
     */
    private $retry_delay = 30;
    
    
    /**
     * Constructor
     * 
     * @param string|null $api_url Optional custom API URL
     */
    public function __construct($api_url = null)
    {
        if ($api_url !== null) {
            $this->api_url = rtrim($api_url, '/');
        }
    }
    
    
    /**
     * Genera tags automáticos para un documento PDF
     * 
     * @param string $ruta_pdf Ruta completa al archivo PDF
     * @param int $top_n Número máximo de tags a generar (default: 10)
     * @return array Array de tags o array vacío si hay error
     * 
     * @example
     * $api = new TagsGlosarioAPI();
     * $tags = $api->generarTags('/path/to/document.pdf');
     * // Returns: ['Proveedor', 'COMPRA', 'Autorización', ...]
     */
    public function generarTags($ruta_pdf, $top_n = 10)
    {
        try {
            // Validar que el archivo existe
            if (!file_exists($ruta_pdf)) {
                error_log("TagsGlosarioAPI: Archivo no encontrado: $ruta_pdf");
                return [];
            }
            
            // Validar que es un PDF
            if (strtolower(pathinfo($ruta_pdf, PATHINFO_EXTENSION)) !== 'pdf') {
                error_log("TagsGlosarioAPI: El archivo no es PDF: $ruta_pdf");
                return [];
            }
            
            // Leer PDF y convertir a base64
            $pdf_content = file_get_contents($ruta_pdf);
            if ($pdf_content === false) {
                error_log("TagsGlosarioAPI: Error leyendo archivo: $ruta_pdf");
                return [];
            }
            
            $pdf_base64 = base64_encode($pdf_content);
            
            // Preparar payload para API v3.0
            $payload = [
                'contenido_base64' => $pdf_base64,
                'nombre_archivo' => basename($ruta_pdf),
                'sector' => null  // Opcional: puede enviarse si se conoce
            ];
            
            // Hacer request a la API v3.0 con retry automático
            $response = $this->makeRequestWithRetry(
                '/procesar',
                $payload,
                'POST'
            );
            
            // Validar respuesta del API v3.0
            if (!$response || !isset($response['tags'])) {
                error_log("TagsGlosarioAPI: Respuesta inválida de la API v3.0");
                return [];
            }
            
            // Extraer términos del array de tags (nuevo formato v3.0)
            $tags_array = [];
            foreach ($response['tags'] as $tag_obj) {
                if (isset($tag_obj['termino'])) {
                    $tags_array[] = $tag_obj['termino'];
                }
            }
            
            // Limitar a top_n
            $tags_array = array_slice($tags_array, 0, $top_n);
            
            // Log de éxito
            $tags_count = count($tags_array);
            $metadata = isset($response['metadata']) ? $response['metadata'] : [];
            $modelo_version = isset($metadata['modelo_version']) ? $metadata['modelo_version'] : 'unknown';
            error_log("TagsGlosarioAPI v$modelo_version: $tags_count tags generados para: " . basename($ruta_pdf));
            
            return $tags_array;
            
        } catch (Exception $e) {
            error_log("TagsGlosarioAPI::generarTags() Exception: " . $e->getMessage());
            return [];
        }
    }
    
    
    /**
     * Detecta términos técnicos para agregar al glosario
     * 
     * @param string $ruta_pdf Ruta completa al archivo PDF
     * @param array $glosario_existente Array de términos ya existentes en el glosario
     * @return array Array de términos con definiciones
     * 
     * @example
     * $api = new TagsGlosarioAPI();
     * $terminos = $api->detectarTerminosGlosario('/path/to/doc.pdf', ['Trello', 'SAP']);
     * // Returns: [
     * //   ['termino' => 'Excel', 'definicion' => '...', 'url' => '...'],
     * //   ...
     * // ]
     */
    public function detectarTerminosGlosario($ruta_pdf, $glosario_existente = [])
    {
        try {
            // Validar que el archivo existe
            if (!file_exists($ruta_pdf)) {
                error_log("TagsGlosarioAPI: Archivo no encontrado: $ruta_pdf");
                return [];
            }
            
            // Validar que es un PDF
            if (strtolower(pathinfo($ruta_pdf, PATHINFO_EXTENSION)) !== 'pdf') {
                error_log("TagsGlosarioAPI: El archivo no es PDF: $ruta_pdf");
                return [];
            }
            
            // Leer PDF y convertir a base64
            $pdf_content = file_get_contents($ruta_pdf);
            if ($pdf_content === false) {
                error_log("TagsGlosarioAPI: Error leyendo archivo: $ruta_pdf");
                return [];
            }
            
            $pdf_base64 = base64_encode($pdf_content);
            
            // Preparar payload para API v3.0
            $payload = [
                'contenido_base64' => $pdf_base64,
                'nombre_archivo' => basename($ruta_pdf),
                'sector' => null  // Opcional
            ];
            
            // Hacer request a la API v3.0 con retry automático
            $response = $this->makeRequestWithRetry(
                '/procesar',
                $payload,
                'POST'
            );
            
            // Validar respuesta del API v3.0
            if (!$response || !isset($response['glosario'])) {
                error_log("TagsGlosarioAPI: Respuesta inválida de la API v3.0");
                return [];
            }
            
            // Extraer términos del glosario (nuevo formato v3.1.3)
            $terminos_array = [];
            foreach ($response['glosario'] as $termino_obj) {
                // Filtrar términos que ya existen en el glosario
                $termino = $termino_obj['termino'];
                if (!in_array($termino, $glosario_existente)) {
                    // v3.1.3: Usar 'definicion' (Wikipedia), NO 'contexto' (documento)
                    $definicion = isset($termino_obj['definicion']) && !empty($termino_obj['definicion']) 
                        ? $termino_obj['definicion']  // Wikipedia (autoritativo)
                        : (isset($termino_obj['contexto']) ? $termino_obj['contexto'] : '');  // Fallback al contexto solo si no hay Wikipedia
                    
                    $terminos_array[] = [
                        'termino' => $termino,
                        'definicion' => $definicion,
                        'url' => null  // v3.0 no incluye URLs de Wikipedia por defecto
                    ];
                }
            }
            
            // Log de éxito
            $terminos_count = count($terminos_array);
            error_log("TagsGlosarioAPI v3.0: $terminos_count términos nuevos detectados para: " . basename($ruta_pdf));
            
            return $terminos_array;
            
        } catch (Exception $e) {
            error_log("TagsGlosarioAPI::detectarTerminosGlosario() Exception: " . $e->getMessage());
            return [];
        }
    }
    
    
    /**
     * Health check del API
     * 
     * @return bool True si el API responde correctamente
     */
    public function healthCheck()
    {
        try {
            $response = $this->makeRequest('/', null, 'GET', $this->timeout_normal);
            
            // v3.0 retorna version y status
            if ($response && isset($response['version']) && $response['version'] === '3.0.0') {
                error_log("TagsGlosarioAPI: Health check OK - v" . $response['version']);
                return true;
            }
            
            // Compatibilidad con versiones anteriores
            if ($response && isset($response['status']) && $response['status'] === 'ok') {
                error_log("TagsGlosarioAPI: Health check OK (legacy)");
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("TagsGlosarioAPI::healthCheck() Exception: " . $e->getMessage());
            return false;
        }
    }
    
    
    /**
     * Hace un request HTTP con retry automático
     * 
     * IMPORTANTE: El Space puede estar "dormido" en el primer request.
     * Si falla, espera y reintenta automáticamente.
     * 
     * @param string $endpoint Endpoint a llamar (e.g., '/generate-tags')
     * @param array|null $payload Datos a enviar (null para GET)
     * @param string $method Método HTTP (GET, POST)
     * @return array|null Respuesta decodificada o null si falla
     */
    private function makeRequestWithRetry($endpoint, $payload = null, $method = 'GET')
    {
        $attempts = 0;
        $last_error = null;
        
        while ($attempts < $this->max_retries) {
            $attempts++;
            
            // Usar timeout más largo en el primer intento (Space puede estar despertando)
            $timeout = ($attempts === 1) ? $this->timeout_first_request : $this->timeout_normal;
            
            try {
                $response = $this->makeRequest($endpoint, $payload, $method, $timeout);
                
                if ($response !== null) {
                    // Éxito - retornar respuesta
                    if ($attempts > 1) {
                        error_log("TagsGlosarioAPI: Request exitoso en intento $attempts");
                    }
                    return $response;
                }
                
            } catch (Exception $e) {
                $last_error = $e->getMessage();
                error_log("TagsGlosarioAPI: Intento $attempts falló: $last_error");
            }
            
            // Si no es el último intento, esperar antes de reintentar
            if ($attempts < $this->max_retries) {
                error_log("TagsGlosarioAPI: Esperando {$this->retry_delay}s antes de reintentar...");
                sleep($this->retry_delay);
            }
        }
        
        // Todos los intentos fallaron
        error_log("TagsGlosarioAPI: Falló después de {$this->max_retries} intentos. Último error: $last_error");
        return null;
    }
    
    
    /**
     * Hace un request HTTP a la API
     * 
     * @param string $endpoint Endpoint a llamar
     * @param array|null $payload Datos a enviar
     * @param string $method Método HTTP
     * @param int $timeout Timeout en segundos
     * @return array|null Respuesta decodificada o null si falla
     */
    private function makeRequest($endpoint, $payload = null, $method = 'GET', $timeout = 60)
    {
        $url = $this->api_url . $endpoint;
        
        $ch = curl_init();
        
        // Configuración básica
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        
        // Headers
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Método y payload
        if ($method === 'POST' && $payload !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }
        
        // Ejecutar request
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        
        curl_close($ch);
        
        // Manejar errores de cURL
        if ($response === false) {
            error_log("TagsGlosarioAPI: cURL error: $curl_error");
            return null;
        }
        
        // Validar código HTTP
        if ($http_code !== 200) {
            error_log("TagsGlosarioAPI: HTTP $http_code - Response: " . substr($response, 0, 200));
            return null;
        }
        
        // Decodificar respuesta JSON
        $decoded = json_decode($response, true);
        
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            error_log("TagsGlosarioAPI: JSON decode error: " . json_last_error_msg());
            return null;
        }
        
        return $decoded;
    }
    
    
    /**
     * Set custom API URL
     * 
     * @param string $url Nueva URL del API
     */
    public function setApiUrl($url)
    {
        $this->api_url = rtrim($url, '/');
    }
    
    
    /**
     * Get current API URL
     * 
     * @return string URL actual del API
     */
    public function getApiUrl()
    {
        return $this->api_url;
    }
}
