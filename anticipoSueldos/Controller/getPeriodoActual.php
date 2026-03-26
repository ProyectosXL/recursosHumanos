<?php
// Controller/getPeriodoActual.php

error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('America/Argentina/Buenos_Aires');

header('Content-Type: application/json');

try {
    require_once '../../Class/Anticipo.php';
    $anticipo = new Anticipo();

    // Obtener datos del período actual a través del método de la clase Anticipo
    $periodoData = $anticipo->obtenerPeriodoAnticipo();

    if ($periodoData) {
        $response = [
            'success' => true,
            'periodo' => $periodoData['PERIODO'],
            'fechaAnticipo' => $periodoData['FECHA_ANTICIPO'],
            'vigDesde' => $periodoData['VIG_DESDE'],
            'vigHasta' => $periodoData['VIG_HASTA']
        ];
    } else {
        $response = [
            'success' => false,
            'message' => 'No se encontraron datos del período actual.'
        ];
    }

    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error en getPeriodoActual: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener los datos: ' . $e->getMessage()
    ]);
}
