<?php
header('Content-Type: application/json');
require_once '../../Class/Anticipo.php';

try {
    $anticipo = new Anticipo();

    // Obtener y validar los datos enviados desde la solicitud
    $inputJSON = file_get_contents('php://input');
    $registros = json_decode($inputJSON, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($registros)) {
        throw new Exception('Error al procesar los datos recibidos');
    }

    $valido = false;
    foreach ($registros as $registro) {
        $importe = str_replace(['$', ',', ' '], '', $registro['importe']);
        if (!empty($importe) && floatval($importe) > 0) {
            $valido = true;
            break;
        }
    }

    if (!$valido) {
        echo json_encode([
            'success' => false,
            'message' => 'Debe haber al menos un registro con un importe válido antes de enviar.'
        ]);
        exit;
    }

    // Procesar y guardar anticipos
    foreach ($registros as $registro) {
        $anticipo->ejecutar(
            "INSERT INTO RO_T_DETALLE_ANTICIPOS (NRO_LEGAJO, IMPORTE, FECHA_CARGA) VALUES (?, ?, GETDATE())",
            [$registro['legajo'], floatval($importe)]
        );
    }

    echo json_encode([
        'success' => true,
        'message' => 'Anticipos guardados correctamente'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
