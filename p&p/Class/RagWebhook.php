<?php
/**
 * Clase para manejar la integración con el servicio RAG
 * 
 * Esta clase se encarga de enviar notificaciones al servicio RAG cuando
 * se crean, actualizan o eliminan documentos, sin modificar la funcionalidad
 * existente del sistema DocuGest.
 */

class RagWebhook
{
    private $rag_service_url;
    private $enabled;
    
    public function __construct($rag_service_url = 'https://cfedetrejo-docugest-unified.hf.space', $enabled = true)
    {
        $this->rag_service_url = $rag_service_url;
        $this->enabled = $enabled;
    }
    
    /**
     * Envía notificación de documento nuevo al servicio RAG
     * 
      * @param array $documento Datos del documento (debe incluir: id, titulo, tipo, sector_nombre, ruta_archivo)
     * @return array Resultado de la operación ['success' => bool, 'message' => string]
     */
    public function notificarDocumentoNuevo($documento)
    {
        if (!$this->enabled) {
            return ['success' => true, 'message' => 'Webhook deshabilitado'];
        }
        
        // Validar que existen los campos requeridos
        if (!isset($documento['id']) || !isset($documento['ruta_archivo'])) {
            return ['success' => false, 'message' => 'Faltan datos del documento'];
        }
        
        // Verificar que el archivo existe antes de notificar
        if (!file_exists($documento['ruta_archivo'])) {
            return ['success' => false, 'message' => 'El archivo no existe en el sistema'];
        }
        
        $webhook_url = $this->rag_service_url . '/api/index-base64';

        $pdf_bytes = @file_get_contents($documento['ruta_archivo']);
        if ($pdf_bytes === false || strlen($pdf_bytes) === 0) {
            return ['success' => false, 'message' => 'No se pudo leer el PDF para indexar'];
        }

        $tipo = isset($documento['tipo']) ? $documento['tipo'] : 'politica';
        if (!in_array($tipo, ['politica', 'procedimiento'])) {
            $tipo = 'politica';
        }

        // Payload push (evita que HF necesite acceder al filesystem del servidor PHP)
        $payload = [
            'document_id' => (int)$documento['id'],
            'titulo' => isset($documento['titulo']) ? $documento['titulo'] : ('Documento ' . (int)$documento['id']),
            'sector_nombre' => isset($documento['sector_nombre']) ? $documento['sector_nombre'] : (isset($documento['sector']) ? $documento['sector'] : 'General'),
            'tipo' => $tipo,
            'contenido_base64' => base64_encode($pdf_bytes)
        ];

        return $this->enviarWebhook($webhook_url, $payload, 'POST', 180);
    }
    
    /**
     * Envía notificación de actualización de documento al servicio RAG
     * 
     * @param int $document_id ID del documento actualizado
     * @param array $documento Datos completos del documento actualizado
     * @return array Resultado de la operación
     */
    public function notificarDocumentoActualizado($document_id, $documento)
    {
        if (!$this->enabled) {
            return ['success' => true, 'message' => 'Webhook deshabilitado'];
        }
        
        // Re-indexar es básicamente lo mismo que indexar por primera vez
        return $this->notificarDocumentoNuevo($documento);
    }
    
    /**
     * Envía notificación de eliminación de documento al servicio RAG
     * 
     * @param int $document_id ID del documento eliminado
     * @return array Resultado de la operación
     */
    public function notificarDocumentoEliminado($document_id)
    {
        if (!$this->enabled) {
            return ['success' => true, 'message' => 'Webhook deshabilitado'];
        }
        
        $webhook_url = $this->rag_service_url . '/api/document/' . urlencode((string)$document_id);

        return $this->enviarWebhook($webhook_url, null, 'DELETE', 30);
    }
    
    /**
     * Verifica la conectividad con el servicio RAG
     * 
     * @return array ['online' => bool, 'version' => string, 'message' => string]
     */
    public function verificarConectividad()
    {
        if (!$this->enabled) {
            return ['online' => false, 'message' => 'Webhook deshabilitado'];
        }
        
        $health_url = $this->rag_service_url . '/health';
        
        $ch = curl_init($health_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($http_code === 200 && $response) {
            $data = json_decode($response, true);
            return [
                'online' => true,
                'version' => isset($data['version']) ? $data['version'] : 'unknown',
                'chunks' => isset($data['total_chunks']) ? $data['total_chunks'] : 0,
                'message' => 'Servicio RAG operativo'
            ];
        } else {
            return [
                'online' => false,
                'message' => $error ?: "HTTP $http_code"
            ];
        }
    }
    
    /**
     * Método privado para enviar webhooks al servicio RAG
     * 
     * @param string $url URL del webhook
     * @param array $payload Datos a enviar
     * @return array Resultado de la operación
     */
    private function enviarWebhook($url, $payload = null, $method = 'POST', $timeout = 30)
    {
        $ch = curl_init($url);
        $method = strtoupper((string)$method);

        if ($method === 'POST') {
            $json_payload = json_encode($payload);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($json_payload)
            ]);
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($payload !== null) {
                $json_payload = json_encode($payload);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($json_payload)
                ]);
            }
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int)$timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($http_code >= 200 && $http_code < 300) {
            $response_data = json_decode($response, true);
            return [
                'success' => true,
                'message' => 'Webhook enviado exitosamente',
                'response' => $response_data
            ];
        } else {
            // El webhook falló, pero no debe impedir que el sistema funcione
            return [
                'success' => false,
                'message' => $error ?: "HTTP $http_code",
                'response' => $response
            ];
        }
    }
    
    /**
     * Habilita el envío de webhooks
     */
    public function habilitar()
    {
        $this->enabled = true;
    }
    
    /**
     * Deshabilita el envío de webhooks
     */
    public function deshabilitar()
    {
        $this->enabled = false;
    }
    
    /**
     * Verifica si los webhooks están habilitados
     * 
     * @return bool
     */
    public function estaHabilitado()
    {
        return $this->enabled;
    }
}
?>
