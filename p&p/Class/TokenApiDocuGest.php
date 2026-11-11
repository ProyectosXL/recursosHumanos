<?php
/**
 * Token compartido para la API servidor a servidor de DocuGest (p&p/api/).
 *
 * La clave DOCUGEST_API_TOKEN vive en el .env de recursosHumanos y como secreto
 * del Space de Hugging Face. Los llamadores la mandan en el header X-DocuGest-Token.
 *
 * Transición sin cortes: si DOCUGEST_API_TOKEN no está definido en el .env,
 * la API sigue abierta (solo documentos activos) y deja una advertencia en el log.
 */

require_once __DIR__ . '/../config_rag.php';

if (!class_exists('TokenApiDocuGest')) {
    class TokenApiDocuGest
    {
        const HEADER = 'X-DocuGest-Token';

        private static function esperado()
        {
            return (string)RagConfig::getInstance()->getDocugestApiToken();
        }

        /** Corta con HTTP 401 si el token falta o no coincide (solo si está configurado). */
        public static function exigir()
        {
            $esperado = self::esperado();
            if ($esperado === '') {
                error_log('DocuGest api: DOCUGEST_API_TOKEN no está definido en el .env; la API queda abierta (solo documentos activos).');
                return;
            }

            $recibido = $_SERVER['HTTP_X_DOCUGEST_TOKEN'] ?? '';
            if (!is_string($recibido) || $recibido === '' || !hash_equals($esperado, $recibido)) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => 'No autorizado']);
                exit;
            }
        }

        /** Headers para curl (CURLOPT_HTTPHEADER) al llamar a la API. */
        public static function headersCurl()
        {
            $token = self::esperado();
            return $token !== '' ? [self::HEADER . ': ' . $token] : [];
        }
    }
}
