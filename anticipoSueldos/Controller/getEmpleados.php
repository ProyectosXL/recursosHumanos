
<?php
header('Content-Type: application/json');

require_once '../../Class/Anticipo.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if(strlen($search) < 3) {
    echo json_encode([]);
    exit;
}

$anticipo = new Anticipo();
$empleados = $anticipo->traerEmpleadosIndividual($search);

echo json_encode($empleados);