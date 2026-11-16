<?php
/**
 * Endpoint / CLI de re-indexación y compilación del Motor de Conocimiento DocuGest 2.0 (100% PHP).
 * - Por HTTP: exige nivel Editar o Admin + Token CSRF.
 * - Por CLI: ejecuta compilación completa (opcional flag --enrich-ai).
 */
if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/../Class/AccesoDocuGest.php';
    AccesoDocuGest::exigirEditar(AccesoDocuGest::JSON);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        AccesoDocuGest::exigirCsrf();
    }
    session_write_close();
    header('Content-Type: application/json; charset=utf-8');
} else {
    require_once __DIR__ . '/../core/Config/DocuGestEnv.php';
}

require_once __DIR__ . '/../core/Engine/DocuGestKnowledgeEngine.php';

@set_time_limit(300);

$enrichAi = false;
if (PHP_SAPI === 'cli') {
    $enrichAi = in_array('--enrich-ai', $argv ?? [], true);
} else {
    $rawBody = file_get_contents('php://input');
    $input = is_string($rawBody) && $rawBody !== '' ? json_decode($rawBody, true) : $_POST;
    $enrichAi = !empty($input['enrich_ai']) || !empty($_GET['enrich_ai']);
}

try {
    $engine = \DocuGest\Engine\DocuGestKnowledgeEngine::getInstance();
    $res = $engine->compileAllActiveDocuments($enrichAi);
    unset($res['index']);

    if (PHP_SAPI === 'cli') {
        echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    } else {
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    if (PHP_SAPI !== 'cli') {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    } else {
        fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}
