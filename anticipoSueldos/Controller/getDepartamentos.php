<?php
// Controller/getDepartamentos.php
header('Content-Type: application/json');

try {
    require_once '../../Class/Anticipo.php';
    $anticipo = new Anticipo();

    $departamentos = $anticipo->obtenerDepartamentos();

    echo json_encode($departamentos);

} catch (Exception $e) {
    error_log("Error en getDepartamentos.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([]);
}
?>
