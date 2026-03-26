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
                'base_url' => 'https://app.xl.com.ar/administracion/recursosHumanos/p&p',
                'timeout' => 60,
                'retry_attempts' => 3,
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
                'rag_service_url' => 'http://localhost:8001',
                'rag_enabled' => true,
                'auto_start' => true, // Auto-iniciar en local
                'auto_index' => true, // Auto-indexar si hay pocos chunks
                'documentos_path' => __DIR__ . '/documentos',
                'base_url' => 'http://localhost/administracion/recursosHumanos/p&p',
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
    
    public function toArray() {
        return $this->config;
    }
}
