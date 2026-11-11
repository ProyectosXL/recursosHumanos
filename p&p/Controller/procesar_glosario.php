<?php
require_once __DIR__ . '/../Class/AccesoDocuGest.php';
AccesoDocuGest::exigirAdmin(AccesoDocuGest::JSON);

require_once __DIR__ . '/../Class/RespuestaJson.php';
require_once __DIR__ . '/../Class/Politica.php';

// Alta de un término del glosario (Configuración, solo Admin)
exigirPost();
responderResultado((new Politica())->guardarTerminoGlosario([
    'termino'    => $_POST['termino'] ?? '',
    'definicion' => $_POST['definicion'] ?? '',
    'sector_id'  => $_POST['sector_id'] ?? '',
]), 'Término agregado al glosario');
