<?php
require_once __DIR__ . '/../Class/AccesoDocuGest.php';
AccesoDocuGest::exigirAdmin(AccesoDocuGest::JSON);

require_once __DIR__ . '/../Class/RespuestaJson.php';
require_once __DIR__ . '/../Class/Politica.php';

// Cuántos documentos tiene un sector (antes de eliminarlo; solo Admin)
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    responderError('Sector no especificado');
}
$cantidad = (new Politica())->contarDocumentosSector($id);
responderJson([
    'status'       => 'success',
    'hasDocuments' => ($cantidad['activos'] + $cantidad['inactivos']) > 0,
    'activos'      => $cantidad['activos'],
    'inactivos'    => $cantidad['inactivos'],
]);
