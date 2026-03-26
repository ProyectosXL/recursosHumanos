
<?php
// Controller/getPeriodos.php - Versión actualizada
header('Content-Type: application/json');

try {
    require_once '../../Class/Anticipo.php';
    $anticipo = new Anticipo();
    
    if (method_exists($anticipo, 'obtenerPeriodosConDatos')) {
        $periodos = $anticipo->obtenerPeriodosConDatos();
    } else {
        $periodos = $anticipo->obtenerPeriodos();
    }
    
    // Verificar que tenemos períodos y están ordenados correctamente
    if (!empty($periodos)) {
        error_log("Períodos devueltos (primeros 3): " . json_encode(array_slice($periodos, 0, 3)));
        error_log("Último período con datos será: " . $periodos[0]['PERIODO']);
    } else {
        error_log("No se encontraron períodos con datos");
    }
    
    echo json_encode($periodos);

} catch (Exception $e) {
    error_log("Error en getPeriodos.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
