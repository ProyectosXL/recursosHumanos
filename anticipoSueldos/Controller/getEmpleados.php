
<?php
session_start();
header('Content-Type: application/json');
ob_start();

try {
    require_once '../../Class/Anticipo.php';

    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    if (strlen($search) < 3) {
        ob_clean();
        echo json_encode([]);
        exit;
    }

    $anticipo = new Anticipo();
    $empleados = $anticipo->traerEmpleadosIndividual($search);

    ob_clean();
    echo json_encode($empleados);

} catch (\Throwable $e) {
    ob_clean();
    error_log("Error en getEmpleados: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

ob_end_flush();