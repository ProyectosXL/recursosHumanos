<?php
// ajax_toggle_estado.php
header('Content-Type: application/json');

require_once 'config.php';
require_once ROOT_PATH . 'Class/Cliente.php';
// ===== NUEVO: Incluimos las clases para conectar con sucursales =====
require_once ROOT_PATH . 'Class/Local.php';
require_once ROOT_PATH . 'Class/Conexion.php';

$response = ['success' => false, 'message' => ''];

$input = json_decode(file_get_contents('php://input'), true);
$codigoCliente = $input['codigo_cliente'] ?? '';
$nuevoEstado = $input['nuevo_estado'] ?? null;

if (empty($codigoCliente) || is_null($nuevoEstado)) {
    $response['message'] = 'Datos insuficientes.';
    echo json_encode($response);
    exit();
}

try {
    $cliente = new Cliente();
    $habilitar = filter_var($nuevoEstado, FILTER_VALIDATE_BOOLEAN);

    // --- PASO 1: Actualizar la base de datos central ---
    if ($cliente->cambiarEstadoCliente($codigoCliente, $habilitar)) {
        $response['success'] = true;
        $mensajeFinal = 'Estado actualizado en la central.';

        // --- PASO 2: Si la actualización central fue exitosa, replicar en sucursales ---
        $localObj = new Local();
        $conexion = new Conexion();
        $sucursales = $localObj->listarLocales();
        
        $resultadosSync = ['exito' => 0, 'error' => 0, 'detalles_error' => []];
        $fechaInha = $habilitar ? '1800-01-01' : date('Y-m-d');

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
                
                $sqlUpdateSucursal = "UPDATE GVA14 SET FECHA_INHA = ? WHERE COD_CLIENT = ?";
                $stmt = odbc_prepare($cidSucursal, $sqlUpdateSucursal);
                
                if (!$stmt || !odbc_execute($stmt, [$fechaInha, $codigoCliente])) {
                    throw new Exception("Error al ejecutar UPDATE en {$sucursal->DbName}: " . odbc_errormsg($cidSucursal));
                }
                
                $resultadosSync['exito']++;

            } catch (Exception $e) {
                $resultadosSync['error']++;
                $resultadosSync['detalles_error'][] = $e->getMessage();
                error_log("Error al sincronizar estado para $codigoCliente en {$sucursal->DbName}: " . $e->getMessage());
            }
        }

        // --- PASO 3: Construir el mensaje de respuesta detallado ---
        if (!empty($sucursales)) {
            $mensajeFinal .= "<br><br><strong>Sincronización con sucursales:</strong>";
            $mensajeFinal .= "<br>✓ Éxitos: " . $resultadosSync['exito'];
            if ($resultadosSync['error'] > 0) {
                $mensajeFinal .= "<br>✗ Fallos: " . $resultadosSync['error'];
            }
        }
        $response['message'] = $mensajeFinal;

    } else {
        $response['message'] = 'No se pudo actualizar el estado en la base de datos central.';
    }
} catch (Exception $e) {
    $response['message'] = 'Error del servidor: ' . $e->getMessage();
    error_log("Error en ajax_toggle_estado.php: " . $e->getMessage());
}

echo json_encode($response);