<?php
// ajax_editar_dni.php
header('Content-Type: application/json');

require_once 'config.php';
require_once ROOT_PATH . 'Class/Cliente.php';
require_once ROOT_PATH . 'Class/Local.php';
require_once ROOT_PATH . 'Class/Conexion.php';

$response = ['success' => false, 'message' => ''];

$input = json_decode(file_get_contents('php://input'), true);
$codigoCliente = isset($input['codigo_cliente']) ? trim($input['codigo_cliente']) : '';
$nuevoDni      = isset($input['nuevo_dni']) ? trim($input['nuevo_dni']) : '';

if (empty($codigoCliente)) {
    $response['message'] = 'El código de cliente es obligatorio.';
    echo json_encode($response);
    exit();
}

try {
    $cliente = new Cliente();

    // --- PASO 1: Actualizar la base de datos central ---
    if ($cliente->actualizarDniCliente($codigoCliente, $nuevoDni)) {
        $response['success'] = true;
        $mensajeFinal = 'DNI / CUIT actualizado correctamente en la central.';

        // --- PASO 2: Sincronizar en sucursales ---
        $localObj = new Local();
        $conexion = new Conexion();
        $sucursales = $localObj->listarLocales();

        $resultadosSync = ['exito' => 0, 'error' => 0, 'detalles_error' => []];

        foreach ($sucursales as $sucursal) {
            $cidSucursal = null;
            try {
                if (empty($sucursal->UsuarioDns) || empty($sucursal->ClaveDns)) {
                    throw new Exception("Credenciales no definidas para {$sucursal->DbName}");
                }

                $cidSucursal = $conexion->conexionSucursalPorHost(
                    $sucursal->Host,
                    $sucursal->DbName,
                    $sucursal->UsuarioDns,
                    $sucursal->ClaveDns
                );

                $sqlUpdateSucursal = "UPDATE GVA14 SET CUIT = ? WHERE COD_CLIENT = ?";
                $stmt = sqlsrv_query($cidSucursal, $sqlUpdateSucursal, [$nuevoDni, $codigoCliente]);
                if ($stmt === false) {
                    throw new Exception("Error al ejecutar UPDATE en {$sucursal->DbName}: " . Conexion::errorMsg());
                }

                $resultadosSync['exito']++;

            } catch (Exception $e) {
                $resultadosSync['error']++;
                $resultadosSync['detalles_error'][] = $e->getMessage();
                error_log("Error al sincronizar DNI/CUIT para $codigoCliente en {$sucursal->DbName}: " . $e->getMessage());
            }
        }

        // --- PASO 3: Mensaje detallado ---
        if (!empty($sucursales)) {
            $mensajeFinal .= "<br><br><strong>Sincronización con sucursales:</strong>";
            $mensajeFinal .= "<br>✓ Éxitos: " . $resultadosSync['exito'];
            if ($resultadosSync['error'] > 0) {
                $mensajeFinal .= "<br>✗ Fallos: " . $resultadosSync['error'];
            }
        }

        $response['message'] = $mensajeFinal;
        $response['nuevo_dni'] = $nuevoDni;

    } else {
        $response['message'] = 'No se pudo actualizar el DNI / CUIT en la base de datos central.';
    }
} catch (Exception $e) {
    $response['message'] = 'Error del servidor: ' . $e->getMessage();
    error_log("Error en ajax_editar_dni.php: " . $e->getMessage());
}

echo json_encode($response);
