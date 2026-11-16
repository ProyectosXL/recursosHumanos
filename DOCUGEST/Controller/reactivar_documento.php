<?php
/**
 * Reactiva un documento dado de baja (solo Admin). POST: id.
 * Vuelve a estado 'activo' y se avisa al servicio de IA para que lo indexe de nuevo.
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

responderResultado((new Politica())->reactivarDocumento($id), 'Documento reactivado');
