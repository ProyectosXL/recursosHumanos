<?php
/**
 * Despierta el HF Space llamando a la API de Hugging Face.
 * El token se lee del config server-side — nunca se expone al cliente.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config_rag.php';

$config = RagConfig::getInstance();

// En local no aplica
if (!$config->isProduction()) {
    echo json_encode(['success' => false, 'reason' => 'local']);
    exit;
}

$token    = $config->getHfApiToken();
$space_id = $config->getHfSpaceId();

if (!$token || !$space_id) {
    echo json_encode(['success' => false, 'reason' => 'missing_config']);
    exit;
}

// Llamar a la API de HF para reiniciar el Space
$url = "https://huggingface.co/api/spaces/{$space_id}/restart";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    error_log("wakeup_hf_space: curl error: $curl_error");
    echo json_encode(['success' => false, 'reason' => 'curl_error', 'detail' => $curl_error]);
    exit;
}

$success = ($http_code >= 200 && $http_code < 300);
error_log("wakeup_hf_space: HF API responded HTTP $http_code for space $space_id");

echo json_encode([
    'success'   => $success,
    'http_code' => $http_code,
    'message'   => $success ? 'Space reiniciado correctamente' : "HF API respondió HTTP $http_code"
]);
