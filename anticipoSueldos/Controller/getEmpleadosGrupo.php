
<?php
session_start();
header('Content-Type: application/json');

try {
    require_once '../../Class/Anticipo.php';
    $anticipo = new Anticipo();
    
    $sector = (isset($_GET['sector']) && $_GET['sector'] != '') ? $_GET['sector'] : '%';
    $nroSucursal = ($_GET['sucursal'] == 'Casa Central') ? 1 : $_GET['sucursal'];

    $empleados = $anticipo->traerEmpleadosGrupo($sector, $nroSucursal);

// Asignar un valor por defecto si no hay importe y convertirlo en solo lectura si ya tiene valor
foreach ($empleados as &$empleado) {
    $empleado['IMPORTE'] = isset($empleado['IMPORTE']) && $empleado['IMPORTE'] !== '' ? $empleado['IMPORTE'] : '0';
    $empleado['READONLY'] = ($empleado['IMPORTE'] > 0) ? 'readonly' : '';
}

echo json_encode([
    'data' => $empleados
]);


} catch (Exception $e) {
    echo json_encode([
        'data' => [],
        'error' => $e->getMessage()
    ]);
}