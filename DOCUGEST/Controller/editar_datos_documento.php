<?php
/**
 * Edita los datos de un documento sin cambiar el PDF (nivel Editar).
 * POST: documento_id, titulo, tipo, sector_id, descripcion, tags
 * No cambia la versión. Queda en el historial como "Edición de datos".
 */
require_once __DIR__ . '/../Class/AccesoDocuGest.php';
AccesoDocuGest::exigirEditar(AccesoDocuGest::PAGINA);

require_once __DIR__ . '/../Class/Politica.php';

function volverConMensaje($tipo, $texto, $parametros = []) {
    header('Location: ../index.php?' . http_build_query(array_merge(['mensaje' => $tipo, 'texto' => $texto], $parametros)));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$documento_id = isset($_POST['documento_id']) ? (int)$_POST['documento_id'] : 0;
if ($documento_id <= 0) {
    volverConMensaje('error', 'Elegí el documento que querés editar', ['tab' => 'update']);
}

$resultado = (new Politica())->editarDatosDocumento($documento_id, [
    'titulo'      => $_POST['titulo'] ?? '',
    'tipo'        => $_POST['tipo'] ?? '',
    'sector_id'   => $_POST['sector_id'] ?? 0,
    'descripcion' => $_POST['descripcion'] ?? '',
    'tags'        => $_POST['tags'] ?? '',
]);

if ($resultado['status'] === 'success') {
    $tab = ($resultado['tipo'] ?? '') === 'procedimiento' ? 'procedures' : 'policies';
    volverConMensaje('success', 'Datos del documento actualizados', ['tab' => $tab]);
}

volverConMensaje('error', $resultado['message'], ['tab' => 'update', 'doc' => $documento_id]);
