<?php
/**
 * Sube una versión nueva del PDF de un documento (nivel Editar).
 * POST: documento_id, archivo (PDF), version, motivo, tags
 */
require_once __DIR__ . '/../Class/AccesoDocuGest.php';
AccesoDocuGest::exigirEditar(AccesoDocuGest::PAGINA);

require_once __DIR__ . '/../Class/Politica.php';

function volverConMensaje($tipo, $texto, $parametros = []) {
    $query = array_merge(['mensaje' => $tipo, 'texto' => $texto], $parametros);
    header('Location: ../index.php?' . http_build_query($query));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

// Si el archivo supera post_max_size, PHP descarta todo el POST
if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    volverConMensaje('error', 'El archivo supera el tamaño máximo (10 MB)', ['tab' => 'update']);
}

$documento_id = isset($_POST['documento_id']) ? (int)$_POST['documento_id'] : 0;
if ($documento_id <= 0) {
    volverConMensaje('error', 'Elegí el documento que querés actualizar', ['tab' => 'update']);
}

$datos = [
    'version' => trim((string)($_POST['version'] ?? '')),
    'motivo'  => trim((string)($_POST['motivo'] ?? '')),
    'tags'    => trim((string)($_POST['tags'] ?? '')),
];

$politicaObj = new Politica();
$resultado = $politicaObj->actualizarDocumento($documento_id, $datos, $_FILES['archivo'] ?? null);

if ($resultado['status'] === 'success') {
    // Volver a la lista del tipo de documento
    $tab = ($resultado['tipo'] ?? '') === 'procedimiento' ? 'procedures' : 'policies';
    volverConMensaje('success', 'Documento actualizado a la versión ' . $resultado['version'], ['tab' => $tab]);
}

volverConMensaje('error', $resultado['message'], ['tab' => 'update', 'doc' => $documento_id]);
