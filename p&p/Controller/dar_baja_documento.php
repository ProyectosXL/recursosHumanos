<?php
/**
 * Da de baja un documento (solo Admin). POST: id, motivo (obligatorio).
 * No borra archivos: el documento pasa a estado 'inactivo' y sale del servicio de IA.
 */
require_once __DIR__ . '/../Class/AccesoDocuGest.php';
AccesoDocuGest::exigirAdmin(AccesoDocuGest::JSON);

require_once __DIR__ . '/../Class/RespuestaJson.php';
require_once __DIR__ . '/../Class/Politica.php';

exigirPost();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    responderError('Documento no especificado');
}

responderResultado(
    (new Politica())->eliminarDocumento($id, $_POST['motivo'] ?? ''),
    'Documento dado de baja'
);
