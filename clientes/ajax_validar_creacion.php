<?php
header('Content-Type: application/json; charset=utf-8');

require_once 'config.php';
require_once ROOT_PATH . 'Class/Cliente.php';

$tipo   = trim($_GET['tipo']   ?? '');
$codigo = trim($_GET['codigo'] ?? '');
$dni    = trim($_GET['dni']    ?? '');

if (!in_array($tipo, ['E', 'AS'], true)) {
    echo json_encode(['error' => 'Tipo de cliente no válido']);
    exit;
}

if (!preg_match('/^[0-9]{1,4}$/', $codigo)) {
    echo json_encode(['error' => 'Código no válido']);
    exit;
}

$codCompleto    = $tipo . str_pad($codigo, 4, '0', STR_PAD_LEFT);
$dniSanitizado  = preg_replace('/\D/', '', $dni);

try {
    $cliente = new Cliente();

    $codigoDisponible = $cliente->codigoDisponible($codCompleto);

    $dniDisponible = true;
    $lenDni = strlen($dniSanitizado);
    if ($lenDni >= 7 && $lenDni <= 11) {
        $dniDisponible = !$cliente->existeDni($dniSanitizado);
    }

    echo json_encode([
        'codigo_disponible' => $codigoDisponible,
        'dni_disponible'    => $dniDisponible,
        'codigo_completo'   => $codCompleto,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en ajax_validar_creacion.php: " . $e->getMessage());
    echo json_encode(['error' => 'Error al validar. Intentá de nuevo.']);
}
