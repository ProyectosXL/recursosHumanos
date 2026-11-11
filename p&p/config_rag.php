<?php
/**
 * Configuración centralizada del servicio RAG.
 * Detecta automáticamente el entorno (local vs producción).
 */

class RagConfig {
    private static $instance = null;
    private $config = [];
    
    private function __construct() {
        $this->detectEnvironment();

        // Secretos: se leen del .env de recursosHumanos (el mismo que usa Conexion), nunca del código
        $this->config['hf_api_token'] = self::env('HF_TOKEN');
        $this->config['docugest_api_token'] = self::env('DOCUGEST_API_TOKEN');
    }

    /**
     * Lee una clave del entorno o del .env de recursosHumanos. Devuelve '' si no está.
     */
    public static function env($clave) {
        $valor = getenv($clave);
        if ($valor !== false && trim($valor) !== '') {
            return trim($valor);
        }
        if (isset($_ENV[$clave]) && trim((string)$_ENV[$clave]) !== '') {
            return trim((string)$_ENV[$clave]);
        }

        static $archivo = null;
        if ($archivo === null) {
            $archivo = [];
            $ruta = dirname(__DIR__) . '/.env';
            if (is_readable($ruta)) {
                foreach (file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
                    $linea = trim($linea);
                    if ($linea === '' || $linea[0] === '#' || strpos($linea, '=') === false) {
                        continue;
                    }
                    list($k, $v) = explode('=', $linea, 2);
                    $archivo[trim($k)] = trim(trim($v), "\"'");
                }
            }
        }
        return $archivo[$clave] ?? '';
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function detectEnvironment() {
        // Detectar si estamos en producción o local
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $is_production = (strpos($host, 'app.xl.com.ar') !== false);
        
        if ($is_production) {
            // ============================================================================
            // CONFIGURACIÓN DE PRODUCCIÓN
            // El servicio RAG corre en el MISMO servidor en localhost:8000
            // ============================================================================
            
            $this->config = [
                'environment' => 'production',
                'rag_service_url' => 'https://cfedetrejo-docugest-unified.hf.space', // HF Space
                'rag_enabled' => true,
                'auto_start' => false, // HF Space se maneja solo
                'auto_index' => true, // Auto-indexar si ChromaDB está vacío
                'documentos_path' => __DIR__ . '/documentos',
                'base_url' => 'https://app.xl.com.ar/recursosHumanos/p&p',
                'index_source_url' => 'https://app.xl.com.ar/recursosHumanos/p&p',
                'timeout' => 60,
                'retry_attempts' => 3,
                'hf_space_id'  => 'cfedetrejo/docugest-unified',
                // Rutas de Python en el servidor (ajustar según instalación)
                'python_path' => '/usr/bin/python3', // Linux: ruta típica de Python 3
                'use_system_python' => true // Usar Python del sistema en lugar de conda
            ];
            
        } else {
            // ============================================================================
            // CONFIGURACIÓN DE DESARROLLO/LOCAL
            // ============================================================================
            
            $this->config = [
                'environment' => 'local',
                'rag_service_url' => 'https://cfedetrejo-docugest-unified.hf.space',
                'rag_enabled' => true,
                'auto_start' => false, // En local también usamos HF Space
                'auto_index' => true, // Auto-indexar si hay pocos chunks
                'documentos_path' => __DIR__ . '/documentos',
                'base_url' => 'http://localhost/recursosHumanos/p&p',
                'index_source_url' => 'https://app.xl.com.ar/recursosHumanos/p&p',
                'timeout' => 60,
                'retry_attempts' => 2,
                'python_path' => 'C:\\Users\\cfede\\miniconda3\\envs\\rag-docugest\\python.exe',
                'use_system_python' => false
            ];
        }
    }
    
    public function get($key, $default = null) {
        return $this->config[$key] ?? $default;
    }
    
    public function isProduction() {
        return $this->config['environment'] === 'production';
    }
    
    public function isRagEnabled() {
        return $this->config['rag_enabled'];
    }
    
    public function getRagServiceUrl($endpoint = '') {
        $base = rtrim($this->config['rag_service_url'], '/');
        return $endpoint ? $base . '/' . ltrim($endpoint, '/') : $base;
    }
    
    public function shouldAutoStart() {
        return $this->config['auto_start'];
    }
    
    public function shouldAutoIndex() {
        return $this->config['auto_index'];
    }
    
    public function getTimeout() {
        return $this->config['timeout'];
    }
    
    public function getRetryAttempts() {
        return $this->config['retry_attempts'];
    }
    
    public function getDocumentosPath() {
        return $this->config['documentos_path'];
    }
    
    public function getBaseUrl() {
        return $this->config['base_url'];
    }

    public function getIndexSourceUrl() {
        return $this->config['index_source_url'] ?? $this->config['base_url'];
    }
    
    public function getHfApiToken() {
        $token = $this->config['hf_api_token'] ?? '';
        if ($token === '') {
            error_log('DocuGest: HF_TOKEN no está definido en el .env; no se puede despertar el Space de Hugging Face.');
            return null;
        }
        return $token;
    }

    /** Token compartido para api/ (header X-DocuGest-Token). '' si no está definido. */
    public function getDocugestApiToken() {
        return $this->config['docugest_api_token'] ?? '';
    }

    public function getHfSpaceId() {
        return $this->config['hf_space_id'] ?? null;
    }

    public function toArray() {
        return $this->config;
    }
}
