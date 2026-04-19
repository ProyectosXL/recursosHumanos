<?php
session_start();

require_once '../config.php';
require_once ROOT_PATH . 'Class/Cliente.php';
require_once ROOT_PATH . 'Class/Conexion.php';

$codClientes    = $_POST['clientes'] ?? [];
$sucursalesInfo = $_POST['dsn'] ?? [];

if (empty($codClientes) || empty($sucursalesInfo)) {
    $_SESSION['feedback'] = ['tipo' => 'danger', 'mensaje' => 'Error: Debe seleccionar al menos un cliente y una sucursal.'];
    $cod_tipo = $_POST['cod_tipo_cliente'] ?? '';
    $redirect_to = !empty($cod_tipo) ? "../clientes.php?cod=" . urlencode($cod_tipo) : "../index.php";
    header("Location: " . $redirect_to);
    exit();
}

$resultados = ['exito' => [], 'error' => []];
$clienteObj = new Cliente();
$conexion   = new Conexion();

foreach ($codClientes as $codCliente) {
    try {
        $datosClienteArray = $clienteObj->traerDatosEmpleados($codCliente);
        if (empty($datosClienteArray)) throw new Exception("No se encontraron datos para el cliente " . htmlspecialchars($codCliente));
        $datosCliente = $datosClienteArray[0];

        foreach ($sucursalesInfo as $infoSucursal) {
            $cid = null;
            try {
                $partesInfo    = explode('|', $infoSucursal);
                $hostInfo      = $partesInfo[0];
                $dbNameSucursal = $partesInfo[1];
                $userSucursal  = $partesInfo[2];
                $passSucursal  = $partesInfo[3];

                if (empty($userSucursal) || empty($passSucursal)) {
                    throw new Exception("Credenciales no definidas para la sucursal.");
                }

                $partesConexion = explode(',', $hostInfo);
                $hostSucursal   = $partesConexion[0];
                $puertoSucursal = isset($partesConexion[1]) ? ',' . $partesConexion[1] : '';
                $hostCompleto   = $hostSucursal . $puertoSucursal;

                $cid = $conexion->conexionSucursalPorHost($hostCompleto, $dbNameSucursal, $userSucursal, $passSucursal);

                sqlsrv_begin_transaction($cid);

                // --- GVA14 ---
                $sqlCheckGva14  = "SELECT COUNT(*) AS total FROM GVA14 WHERE COD_CLIENT = ?";
                $stmtCheckGva14 = sqlsrv_query($cid, $sqlCheckGva14, [$codCliente]);
                if ($stmtCheckGva14 === false) throw new Exception("Error al verificar GVA14: " . Conexion::errorMsg());
                $resultGva14 = sqlsrv_fetch_array($stmtCheckGva14, SQLSRV_FETCH_ASSOC);

                if ($resultGva14 && $resultGva14['total'] == 0) {
                    $sqlInsertarGva14 = "INSERT INTO GVA14 (COD_CLIENT, COD_PROVIN, COD_TRANSP, COD_VENDED, COD_ZONA, COND_VTA, CUIT, CUPO_CREDI, DIR_COM, DOMICILIO, E_MAIL, EXPORTA, FECHA_ALTA, GRUPO_EMPR, IVA_L, LOCALIDAD, N_IMPUESTO, NOM_COM, NRO_LISTA, OBSERVACIO, PERMITE_IS, PORC_DESC, RAZON_SOCI, TELEFONO_1, TELEFONO_2, TIPO, TIPO_DOC, ID_CATEGORIA_IVA, ID_GVA14) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, (SELECT MAX(ID_GVA14) + 1 FROM GVA14))";
                    $paramsGva14 = [$codCliente, $datosCliente->COD_PROVIN, '**', $datosCliente->COD_VENDED, '**', $datosCliente->COND_VTA, $datosCliente->CUIT, $datosCliente->CUPO_CREDI, $datosCliente->DIR_COM, $datosCliente->DOMICILIO, $datosCliente->E_MAIL, $datosCliente->EXPORTA, $datosCliente->FECHA_ALTA, $datosCliente->GRUPO_EMPR, 'S', '96', $datosCliente->N_IMPUESTO, $datosCliente->NOM_COM, $datosCliente->NRO_LISTA, $datosCliente->OBSERVACIO, 1, $datosCliente->PORC_DESC, $datosCliente->RAZON_SOCI, $datosCliente->TELEFONO_1, $datosCliente->TELEFONO_2, $datosCliente->TIPO, $datosCliente->TIPO_DOC, $datosCliente->ID_CATEGORIA_IVA];
                    $stmt2 = sqlsrv_query($cid, $sqlInsertarGva14, $paramsGva14);
                    if ($stmt2 === false) throw new Exception("Error al insertar en GVA14: " . Conexion::errorMsg());
                }

                // --- GVA14ITC ---
                $sqlCheckItc  = "SELECT COUNT(*) AS total FROM GVA14ITC WHERE CODE = ?";
                $stmtCheckItc = sqlsrv_query($cid, $sqlCheckItc, [$codCliente]);
                if ($stmtCheckItc === false) throw new Exception("Error al verificar GVA14ITC: " . Conexion::errorMsg());
                $resultItc = sqlsrv_fetch_array($stmtCheckItc, SQLSRV_FETCH_ASSOC);

                if ($resultItc && $resultItc['total'] == 0) {
                    $sqlClasificador = "INSERT INTO GVA14ITC (CODE, IDFOLDER, CODEA) VALUES (?, (SELECT IDFOLDER FROM GVA14FLD WHERE DESCRIP = 'LOCALES'), ?)";
                    $stmt3 = sqlsrv_query($cid, $sqlClasificador, [$codCliente, $codCliente]);
                    if ($stmt3 === false) throw new Exception("Error al insertar en GVA14ITC: " . Conexion::errorMsg());
                }

                // --- DIRECCION_ENTREGA ---
                $sqlCheckDir  = "SELECT COUNT(*) AS total FROM DIRECCION_ENTREGA WHERE COD_CLIENTE = ?";
                $stmtCheckDir = sqlsrv_query($cid, $sqlCheckDir, [$codCliente]);
                if ($stmtCheckDir === false) throw new Exception("Error al verificar DIRECCION_ENTREGA: " . Conexion::errorMsg());
                $resultDir = sqlsrv_fetch_array($stmtCheckDir, SQLSRV_FETCH_ASSOC);

                if ($resultDir && $resultDir['total'] == 0) {
                    $sqlInsertDir = "
                        INSERT INTO [dbo].[DIRECCION_ENTREGA]
                        ([ID_DIRECCION_ENTREGA],[COD_DIRECCION_ENTREGA],[COD_CLIENTE],[DIRECCION],[COD_PROVINCIA],[LOCALIDAD],[HABITUAL],[CODIGO_POSTAL],[TELEFONO1],[TELEFONO2],[TOMA_IMPUESTO_HABITUAL]
                        ,[FILLER],[OBSERVACIONES],[AL_FIJ_IB3],[ALI_ADI_IB],[ALI_FIJ_IB],[IB_L],[IB_L3],[II_IB3],[LIB],[PORC_L],[HABILITADO],[HORARIO_ENTREGA],[ENTREGA_LUNES],[ENTREGA_MARTES]
                        ,[ENTREGA_MIERCOLES],[ENTREGA_JUEVES],[ENTREGA_VIERNES],[ENTREGA_SABADO],[ENTREGA_DOMINGO],[CONSIDERA_IVA_BASE_CALCULO_IIBB],[CONSIDERA_IVA_BASE_CALCULO_IIBB_ADIC],[WEB_ADDRESS_ID])
                        VALUES
                        (
                        (SELECT MAX(ID_DIRECCION_ENTREGA)+1 FROM DIRECCION_ENTREGA), 'PRINCIPAL', ?, '', '', '', 'S', '', '', '', 'N',
                        '', '', 0, 0, 0, 0, 0, 0, 'N', 0, 'S', '', 'N', 'N', 'N', 'N', 'N', 'N', 'N', 'N', 'N', 0
                        )
                    ";
                    $stmt4 = sqlsrv_query($cid, $sqlInsertDir, [$codCliente]);
                    if ($stmt4 === false) throw new Exception("Error al insertar en DIRECCION_ENTREGA: " . Conexion::errorMsg());
                }

                sqlsrv_commit($cid);
                $resultados['exito'][] = "Cliente " . htmlspecialchars($codCliente) . " replicado en " . htmlspecialchars($dbNameSucursal);

            } catch (Exception $e) {
                if ($cid) sqlsrv_rollback($cid);
                $resultados['error'][] = "Fallo para cliente " . htmlspecialchars($codCliente) . " en sucursal (" . htmlspecialchars($dbNameSucursal) . ") (" . $e->getMessage() . ")";
                error_log("Fallo al procesar sucursal $infoSucursal para cliente $codCliente: " . $e->getMessage());
            }
        }
    } catch (Exception $e) {
        $resultados['error'][] = "Fallo general para cliente " . htmlspecialchars($codCliente) . ": " . $e->getMessage();
    }
}

$mensaje = "";
if (!empty($resultados['exito'])) {
    $mensaje .= "<strong>Operaciones exitosas:</strong><br>" . implode('<br>', $resultados['exito']) . "<br><br>";
}
if (!empty($resultados['error'])) {
    $mensaje .= "<strong>Operaciones fallidas:</strong><br>" . implode('<br>', $resultados['error']);
}

$_SESSION['feedback'] = [
    'tipo'    => !empty($resultados['error']) ? 'warning' : 'success',
    'mensaje' => $mensaje,
];

header("Location: ../index.php");
exit();
