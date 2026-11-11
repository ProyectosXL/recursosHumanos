<?php
require_once __DIR__ . '/../Class/AccesoDocuGest.php';
AccesoDocuGest::exigirAdmin(AccesoDocuGest::JSON);

require_once __DIR__ . '/../Class/RespuestaJson.php';
require_once __DIR__ . '/../Class/Politica.php';

// Modificación de un término del glosario (Configuración, solo Admin)
exigirPost();
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    responderError('Término no especificado');
}
responderResultado((new Politica())->actualizarTerminoGlosario($id, [
    'termino'    => $_POST['termino'] ?? '',
    'definicion' => $_POST['definicion'] ?? '',
    'sector_id'  => $_POST['sector_id'] ?? '',
]), 'Término actualizado');
