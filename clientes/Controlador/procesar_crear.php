<?php
session_start();

require_once '../config.php';
require_once ROOT_PATH . 'Class/Cliente.php';
require_once ROOT_PATH . 'Class/Conexion.php';
require_once ROOT_PATH . 'Class/Local.php';

$codClient = filter_input(INPUT_POST, 'codClient', FILTER_UNSAFE_RAW);
$nombre    = filter_input(INPUT_POST, 'nombre', FILTER_UNSAFE_RAW);
$dni       = filter_input(INPUT_POST, 'dni', FILTER_UNSAFE_RAW) ?? '0';
$porcDesc  = filter_input(INPUT_POST, 'porcDesc', FILTER_VALIDATE_FLOAT) ?? 0.0;
$hoy       = date("Y-m-d");

if (empty($codClient) || empty($nombre)) {
    die("Error: El código de cliente y el nombre son obligatorios.");
}

if (strpos($codClient, 'AS') === 0 && $porcDesc > 35) {
    $_SESSION['feedback'] = [
        'tipo'    => 'danger',
        'mensaje' => 'Error: El porcentaje de descuento para referidos no puede ser mayor a 35%.',
    ];
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}

try {
    $cliente = new Cliente();

    if ($cliente->existeDni($dni)) {
        $_SESSION['feedback'] = [
            'tipo'    => 'danger',
            'mensaje' => 'Error al crear el cliente: El DNI/CUIT "' . htmlspecialchars($dni) . '" ya está registrado.',
        ];
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }

    $conexion = new Conexion();
    $cid      = $conexion->conexionLakerbis();

    sqlsrv_begin_transaction($cid);

    $sqlInsertar  = "INSERT INTO GVA14 (COD_CLIENT, COD_PROVIN, COD_TRANSP, COD_VENDED, COD_ZONA, COND_VTA, CUIT, CUPO_CREDI, EXPORTA, FECHA_ALTA, IVA_L, LOCALIDAD, N_IMPUESTO, NOM_COM, NRO_LISTA, OBSERVACIO, PERMITE_IS, PORC_DESC, RAZON_SOCI, TIPO_DOC, ID_CATEGORIA_IVA, ID_GVA14) VALUES (?, '01', '**', 'ZZ', '**', 1, ?, 10000, 1, ?, 'S', '96', '', ?, 20, 'CARGADO POR APP WEB', 1, ?, ?, '96', 2, (SELECT MAX(ID_GVA14) + 1 FROM GVA14))";
    $paramsGva14  = [$codClient, $dni, $hoy, $nombre, $porcDesc, $nombre];
    $stmt2 = sqlsrv_query($cid, $sqlInsertar, $paramsGva14);
    if ($stmt2 === false) throw new Exception("Error al insertar en GVA14: " . Conexion::errorMsg());

    $sqlClasificador = "INSERT INTO GVA14ITC (CODE, IDFOLDER, CODEA) VALUES (?, (SELECT IDFOLDER FROM GVA14FLD WHERE DESCRIP = 'LOCALES'), ?)";
    $stmt3 = sqlsrv_query($cid, $sqlClasificador, [$codClient, $codClient]);
    if ($stmt3 === false) throw new Exception("Error al insertar en GVA14ITC: " . Conexion::errorMsg());

    sqlsrv_commit($cid);

    // ===== ENVÍO AUTOMÁTICO A SUCURSALES (solo Referidos — código AS*) =====
    $resultadosEnvio  = ['exito' => [], 'error' => []];
    $esReferido       = strpos($codClient, 'AS') === 0;
    $mensajeCreacion  = '¡' . ($esReferido ? 'Referido' : 'Empleado') . ' ' . htmlspecialchars($codClient) . ' creado con éxito en la base central!';

    if (!$esReferido) {
        // Empleados se envían manualmente desde "Enviar a Sucursales"
        $_SESSION['feedback'] = ['tipo' => 'success', 'mensaje' => $mensajeCreacion];
        header("Location: ../index.php");
        exit();
    }

    $datosClienteArray = $cliente->traerDatosEmpleados($codClient);
    if (empty($datosClienteArray)) {
        throw new Exception("No se pudieron recuperar los datos del cliente recién creado para el envío.");
    }
    $datosCliente = $datosClienteArray[0];

    $localObj   = new Local();
    $sucursales = $localObj->listarLocales();

    foreach ($sucursales as $sucursal) {
        $cidSucursal    = null;
        $dbNameSucursal = $sucursal->DbName;
        try {
            if (empty($sucursal->UsuarioDns) || empty($sucursal->ClaveDns)) {
                throw new Exception("Credenciales no definidas.");
            }

            $cidSucursal = $conexion->conexionSucursalPorHost(
                $sucursal->Host,
                $sucursal->DbName,
                $sucursal->UsuarioDns,
                $sucursal->ClaveDns
            );

            sqlsrv_begin_transaction($cidSucursal);

            // --- GVA14 ---
            $stmtCheckGva14 = sqlsrv_query($cidSucursal, "SELECT COUNT(*) AS total FROM GVA14 WHERE COD_CLIENT = ?", [$codClient]);
            if ($stmtCheckGva14 === false) throw new Exception("Error al verificar GVA14.");
            $resultGva14 = sqlsrv_fetch_array($stmtCheckGva14, SQLSRV_FETCH_ASSOC);

            if ($resultGva14 && $resultGva14['total'] == 0) {
                $sqlInsertarGva14   = "INSERT INTO GVA14 (COD_CLIENT, COD_PROVIN, COD_TRANSP, COD_VENDED, COD_ZONA, COND_VTA, CUIT, CUPO_CREDI, DIR_COM, DOMICILIO, E_MAIL, EXPORTA, FECHA_ALTA, GRUPO_EMPR, IVA_L, LOCALIDAD, N_IMPUESTO, NOM_COM, NRO_LISTA, OBSERVACIO, PERMITE_IS, PORC_DESC, RAZON_SOCI, TELEFONO_1, TELEFONO_2, TIPO, TIPO_DOC, ID_CATEGORIA_IVA, ID_GVA14) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, (SELECT MAX(ID_GVA14) + 1 FROM GVA14))";
                $paramsGva14Sucursal = [$codClient, $datosCliente->COD_PROVIN, '**', $datosCliente->COD_VENDED, '**', $datosCliente->COND_VTA, $datosCliente->CUIT, $datosCliente->CUPO_CREDI, $datosCliente->DIR_COM, $datosCliente->DOMICILIO, $datosCliente->E_MAIL, $datosCliente->EXPORTA, $datosCliente->FECHA_ALTA, $datosCliente->GRUPO_EMPR, 'S', '96', $datosCliente->N_IMPUESTO, $datosCliente->NOM_COM, $datosCliente->NRO_LISTA, $datosCliente->OBSERVACIO, 1, $datosCliente->PORC_DESC, $datosCliente->RAZON_SOCI, $datosCliente->TELEFONO_1, $datosCliente->TELEFONO_2, $datosCliente->TIPO, $datosCliente->TIPO_DOC, $datosCliente->ID_CATEGORIA_IVA];
                $stmtInsertGva14 = sqlsrv_query($cidSucursal, $sqlInsertarGva14, $paramsGva14Sucursal);
                if ($stmtInsertGva14 === false) throw new Exception("Error al insertar en GVA14.");
            }

            // --- GVA14ITC ---
            $stmtCheckItc = sqlsrv_query($cidSucursal, "SELECT COUNT(*) AS total FROM GVA14ITC WHERE CODE = ?", [$codClient]);
            if ($stmtCheckItc === false) throw new Exception("Error al verificar GVA14ITC.");
            $resultItc = sqlsrv_fetch_array($stmtCheckItc, SQLSRV_FETCH_ASSOC);

            if ($resultItc && $resultItc['total'] == 0) {
                $stmtInsertItc = sqlsrv_query($cidSucursal, "INSERT INTO GVA14ITC (CODE, IDFOLDER, CODEA) VALUES (?, (SELECT IDFOLDER FROM GVA14FLD WHERE DESCRIP = 'LOCALES'), ?)", [$codClient, $codClient]);
                if ($stmtInsertItc === false) throw new Exception("Error al insertar en GVA14ITC.");
            }

            // --- DIRECCION_ENTREGA ---
            $stmtCheckDir = sqlsrv_query($cidSucursal, "SELECT COUNT(*) AS total FROM DIRECCION_ENTREGA WHERE COD_CLIENTE = ?", [$codClient]);
            if ($stmtCheckDir === false) throw new Exception("Error al verificar DIRECCION_ENTREGA.");
            $resultDir = sqlsrv_fetch_array($stmtCheckDir, SQLSRV_FETCH_ASSOC);

            if ($resultDir && $resultDir['total'] == 0) {
                $sqlInsertDir  = "INSERT INTO [dbo].[DIRECCION_ENTREGA] ([ID_DIRECCION_ENTREGA],[COD_DIRECCION_ENTREGA],[COD_CLIENTE],[DIRECCION],[COD_PROVINCIA],[LOCALIDAD],[HABITUAL],[CODIGO_POSTAL],[TELEFONO1],[TELEFONO2],[TOMA_IMPUESTO_HABITUAL],[FILLER],[OBSERVACIONES],[AL_FIJ_IB3],[ALI_ADI_IB],[ALI_FIJ_IB],[IB_L],[IB_L3],[II_IB3],[LIB],[PORC_L],[HABILITADO],[HORARIO_ENTREGA],[ENTREGA_LUNES],[ENTREGA_MARTES],[ENTREGA_MIERCOLES],[ENTREGA_JUEVES],[ENTREGA_VIERNES],[ENTREGA_SABADO],[ENTREGA_DOMINGO],[CONSIDERA_IVA_BASE_CALCULO_IIBB],[CONSIDERA_IVA_BASE_CALCULO_IIBB_ADIC],[WEB_ADDRESS_ID]) VALUES ((SELECT MAX(ID_DIRECCION_ENTREGA)+1 FROM DIRECCION_ENTREGA), 'PRINCIPAL', ?, '', '', '', 'S', '', '', '', 'N', '', '', 0, 0, 0, 0, 0, 0, 'N', 0, 'S', '', 'N', 'N', 'N', 'N', 'N', 'N', 'N', 'N', 'N', 0)";
                $stmtInsertDir = sqlsrv_query($cidSucursal, $sqlInsertDir, [$codClient]);
                if ($stmtInsertDir === false) throw new Exception("Error al insertar en DIRECCION_ENTREGA.");
            }

            sqlsrv_commit($cidSucursal);
            $resultadosEnvio['exito'][] = "Enviado a " . htmlspecialchars($dbNameSucursal);

        } catch (Exception $e) {
            if ($cidSucursal) sqlsrv_rollback($cidSucursal);
            $resultadosEnvio['error'][] = "Fallo en " . htmlspecialchars($dbNameSucursal) . ": " . $e->getMessage();
            error_log("Fallo de envío para $codClient en sucursal $dbNameSucursal: " . $e->getMessage());
        }
    }
    // ===== FIN ENVÍO AUTOMÁTICO =====

    $mensajeFeedback = $mensajeCreacion;
    if (!empty($resultadosEnvio['exito']) || !empty($resultadosEnvio['error'])) {
        $mensajeFeedback .= "<br><br><strong>Resultado del envío a sucursales:</strong>";
        if (!empty($resultadosEnvio['exito'])) {
            $mensajeFeedback .= "<br><small>✓ Éxitos: " . implode(', ', $resultadosEnvio['exito']) . "</small>";
        }
        if (!empty($resultadosEnvio['error'])) {
            $mensajeFeedback .= "<br><small>✗ Errores: " . implode(', ', $resultadosEnvio['error']) . "</small>";
        }
    }

    $_SESSION['feedback'] = [
        'tipo'    => !empty($resultadosEnvio['error']) ? 'warning' : 'success',
        'mensaje' => $mensajeFeedback,
    ];

    $urlDestino = (strpos($codClient, 'AS') === 0) ? '../index_directores.php' : '../index.php';
    header("Location: " . $urlDestino);
    exit();

} catch (Exception $e) {
    if (isset($cid) && $cid) sqlsrv_rollback($cid);
    error_log("Error en procesar_crear.php: " . $e->getMessage());
    $_SESSION['feedback'] = [
        'tipo'    => 'danger',
        'mensaje' => 'Ocurrió un error al crear el cliente. La operación ha sido cancelada. Detalle: ' . htmlspecialchars($e->getMessage()),
    ];
    $urlDestino = (strpos($codClient, 'AS') === 0) ? '../index_directores.php' : '../index.php';
    header("Location: " . $urlDestino);
    exit();
}
