<?php
require_once __DIR__ . '/../Class/AccesoDocuGest.php';
AccesoDocuGest::exigirLogin(AccesoDocuGest::JSON);

require_once __DIR__ . '/../Class/RespuestaJson.php';
require_once __DIR__ . '/../Class/Politica.php';

// Contadores generales (main.js los pide cada 5 minutos). Solo documentos activos.
$e = (new Politica())->obtenerEstadisticas();
responderJson([
    'status'               => 'success',
    'total_documentos'     => (int)($e['total_documentos'] ?? 0),
    'total_politicas'      => (int)($e['total_politicas'] ?? 0),
    'total_procedimientos' => (int)($e['total_procedimientos'] ?? 0),
]);
