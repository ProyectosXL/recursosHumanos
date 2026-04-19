
<?php
session_start();
header('Content-Type: application/json');
ob_start();

try {
    require_once '../../Class/Anticipo.php';
    $anticipo = new Anticipo();

    $sector = (isset($_GET['sector']) && $_GET['sector'] != '') ? $_GET['sector'] : '%';
    $sucursalParam = isset($_GET['sucursal']) ? $_GET['sucursal'] : '';
    $nroSucursal = ($sucursalParam == 'Casa Central') ? 1 : $sucursalParam;

    $empleados = $anticipo->traerEmpleadosGrupo($sector, $nroSucursal);

    foreach ($empleados as &$empleado) {
        $empleado['IMPORTE'] = isset($empleado['IMPORTE']) && $empleado['IMPORTE'] !== '' ? $empleado['IMPORTE'] : '0';
        $empleado['READONLY'] = ($empleado['IMPORTE'] > 0) ? 'readonly' : '';
    }

    ob_clean();
    echo json_encode(['data' => $empleados]);

} catch (\Throwable $e) {
    ob_clean();
    error_log("Error en getEmpleadosGrupo: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'data' => [],
        'error' => $e->getMessage()
    ]);
}

ob_end_flush();