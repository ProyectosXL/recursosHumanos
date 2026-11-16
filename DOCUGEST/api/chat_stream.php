<?php
/**
 * Endpoint seguro de consulta al Asistente IA DocuGest 2.0 (100% PHP Nativo).
 * - Exige sesión activa del Hub + perfil de W:\Gestionusuarios.
 * - Exige token CSRF válido (X-CSRF-Token).
 * - Libera el bloqueo de sesión antes de invocar al agente.
 * - Soporta streaming SSE (Accept: text/event-stream o stream=true) y JSON directo.
 */
require_once __DIR__ . '/../Class/AccesoDocuGest.php';
AccesoDocuGest::exigirLogin(AccesoDocuGest::JSON);
AccesoDocuGest::exigirCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawBody = file_get_contents('php://input');
$input = is_string($rawBody) && $rawBody !== '' ? json_decode($rawBody, true) : null;
if (!is_array($input)) {
    $input = $_POST;
}

$action = trim((string)($input['action'] ?? 'query'));
$userProfile = AccesoDocuGest::perfilUsuario();
$conversationId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($input['conversation_id'] ?? 'default')) ?: 'default';

require_once __DIR__ . '/../core/Agent/DocuGestAgentRuntime.php';

// Acción rápida para limpiar hilo conversacional del usuario
if ($action === 'clear') {
    session_write_close();
    $memory = new \DocuGest\Engine\DocuGestMemoryManager((string)($userProfile['username'] ?? 'usuario'));
    $memory->clearConversation($conversationId);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'success', 'message' => 'Conversación reiniciada'], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawQuestion = (string)($input['question'] ?? ($input['query'] ?? ''));
$question = AccesoDocuGest::sanitizarPregunta($rawQuestion, 1500);

if (mb_strlen($question, 'UTF-8') < 2) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Ingresá una pregunta válida'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Liberar lock de sesión PHP inmediatamente antes de ejecutar el runtime agéntico
session_write_close();

$wantsStream = !empty($input['stream'])
    || str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'text/event-stream');

$runtime = new \DocuGest\Agent\DocuGestAgentRuntime();

if ($wantsStream) {
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache, no-transform');
    header('X-Accel-Buffering: no');

    $emitSse = static function (string $event, array $data): void {
        echo "event: {$event}\n";
        echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
        @ob_flush();
        @flush();
    };

    try {
        $result = $runtime->handleQuery($question, $userProfile, $conversationId, $emitSse);
        $emitSse('done', $result);
    } catch (Throwable $e) {
        $emitSse('error', ['status' => 'error', 'message' => 'Error procesando la consulta: ' . $e->getMessage()]);
    }
    exit;
}

header('Content-Type: application/json; charset=utf-8');
try {
    $result = $runtime->handleQuery($question, $userProfile, $conversationId, null);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Error procesando la consulta: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
