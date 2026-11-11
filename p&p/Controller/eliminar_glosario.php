<?php
require_once __DIR__ . '/../Class/AccesoDocuGest.php';
AccesoDocuGest::exigirAdmin(AccesoDocuGest::JSON);

require_once __DIR__ . '/../Class/RespuestaJson.php';
require_once __DIR__ . '/../Class/Politica.php';

// Elimina un término del glosario (Configuración, solo Admin)
exigirPost();
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    responderError('Término no especificado');
}
responderResultado((new Politica())->eliminarTerminoGlosario($id), 'Término eliminado');
