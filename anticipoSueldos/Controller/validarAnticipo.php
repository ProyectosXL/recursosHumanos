<?php
// Controller/validarAnticipo.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('America/Argentina/Buenos_Aires');

header('Content-Type: application/json');

try {
    if (!isset($_POST['registros'])) {
        throw new Exception('No se recibieron datos para validar');
    }

    $registrosJson = $_POST['registros'];
    $registros = json_decode($registrosJson, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Error al decodificar los datos: ' . json_last_error_msg());
    }

    require_once '../../Class/Anticipo.php';
    $anticipo = new Anticipo();

    // Obtener el período vigente usando el método de la clase
    $periodoData = $anticipo->obtenerPeriodoActual();
    
    if (!$periodoData || empty($periodoData['PERIODO'])) {
        throw new Exception('No hay un período vigente para cargar anticipos');
    }

    $periodoVigente = isset($periodoData['PERIODO']) ? $periodoData['PERIODO'] : null;

if (!$periodoVigente) {
    throw new Exception('El período vigente no está definido.');
}

foreach ($registros as $registro) {
    if (!isset($registro['legajo'], $registro['nombre'])) {
        throw new Exception('Faltan datos requeridos en el registro.');
    }
    // Verificación de anticipo usando el nuevo método del modelo
    
    if ($anticipo->existeAnticipo($registro['legajo'], $periodoVigente)) {
        $detalleAnticipo = $anticipo->obtenerAnticipoDetalle($registro['legajo'], $periodoVigente);
        throw new Exception(
            "Ya existe un anticipo registrado para el empleado:<br>" .
            "Legajo: " . htmlspecialchars($registro['legajo']) . "<br>" .
            "Nombre: " . htmlspecialchars($registro['nombre']) . "<br>" .
            "Período: " . htmlspecialchars($periodoVigente) . "<br>" .
            "Importe: " . '$' . number_format($detalleAnticipo['IMPORTE'], 2, ',', '.') . "<br>" .
            "Fecha de carga: " . $detalleAnticipo['FECHA_CARGA']->format('d/m/Y H:i') . "<br>"
        );
    }
}

    $response = array(
        'success' => true,
        'message' => 'Validación exitosa',
        'periodo' => $periodoData['PERIODO'],
        'fechaAnticipo' => $periodoData['FECHA_ANTICIPO']->format('d/m/Y'),
        'vigDesde' => $periodoData['VIG_DESDE']->format('d/m/Y H:i'),
        'vigHasta' => $periodoData['VIG_HASTA']->format('d/m/Y H:i')
    );

    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error en validación: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
