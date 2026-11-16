<?php
/**
 * Respuestas JSON de los controllers de DocuGest.
 */
if (!function_exists('responderJson')) {
    function responderJson(array $datos, $httpCode = 200) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
        exit;
    }

    function responderError($mensaje, $httpCode = 200) {
        responderJson(['status' => 'error', 'message' => $mensaje], $httpCode);
    }

    /** Corta con 405 si el pedido no es POST */
    function exigirPost() {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            responderError('Método no permitido', 405);
        }
    }

    /** Pasa el resultado de Politica (status/message) a JSON */
    function responderResultado(array $resultado, $mensajeOk) {
        if (($resultado['status'] ?? '') === 'success') {
            responderJson(['status' => 'success', 'message' => $mensajeOk]);
        }
        responderError($resultado['message'] ?? 'No se pudo completar la operación');
    }
}
