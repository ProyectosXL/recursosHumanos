
<?php
// Controller/validarDNI.php
header('Content-Type: application/json');

require_once '../../Class/Anticipo.php';

if (!isset($_POST['dni']) || !isset($_POST['legajo'])) {
    echo json_encode(['valid' => false, 'message' => 'Faltan datos']);
    exit;
}

$dni = trim($_POST['dni']);
$legajo = trim($_POST['legajo']);

$anticipo = new Anticipo();
$isValid = $anticipo->validarDNI($dni, $legajo);

echo json_encode(['valid' => $isValid]);