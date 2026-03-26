<?php
session_start();

require_once '../config.php';
require_once ROOT_PATH . 'Class/Cliente.php';
require_once ROOT_PATH . 'Class/Conexion.php';
// ===== NUEVO: Incluimos la clase Local para obtener las sucursales =====
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
        'tipo' => 'danger', 
        'mensaje' => 'Error: El porcentaje de descuento para referidos no puede ser mayor a 35%.'
    ];
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}

try {
    $cliente = new Cliente();
    
    if ($cliente->existeDni($dni)) {
        $_SESSION['feedback'] = [
            'tipo' => 'danger', 
            'mensaje' => 'Error al crear el cliente: El DNI/CUIT "' . htmlspecialchars($dni) . '" ya está registrado.'
        ];
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit();
    }
    
    $conexion = new Conexion();
    $cid = $conexion->conexionLakerbis();
    
    // Inicia transacción en la base central
    odbc_autocommit($cid, false);

    $sqlInsertar = "INSERT INTO GVA14 (COD_CLIENT, COD_PROVIN, COD_TRANSP, COD_VENDED, COD_ZONA, COND_VTA, CUIT, CUPO_CREDI, EXPORTA, FECHA_ALTA, IVA_L, LOCALIDAD, N_IMPUESTO, NOM_COM, NRO_LISTA, OBSERVACIO, PERMITE_IS, PORC_DESC, RAZON_SOCI, TIPO_DOC, ID_CATEGORIA_IVA, ID_GVA14) VALUES (?, '01', '**', 'ZZ', '**', 1, ?, 10000, 1, ?, 'S', '96', '', ?, 20, 'CARGADO POR APP WEB', 1, ?, ?, '96', 2, (SELECT MAX(ID_GVA14) + 1 FROM GVA14))";
    $paramsGva14 = [$codClient, $dni, $hoy, $nombre, $porcDesc, $nombre];
    $stmt2 = odbc_prepare($cid, $sqlInsertar);
    if (!$stmt2 || !odbc_execute($stmt2, $paramsGva14)) {
        throw new Exception("Error al insertar en GVA14: " . odbc_errormsg($cid));
    }

    $sqlClasificador = "INSERT INTO GVA14ITC (CODE, IDFOLDER, CODEA) VALUES (?, (SELECT IDFOLDER FROM GVA14FLD WHERE DESCRIP = 'LOCALES'), ?)";
    $stmt3 = odbc_prepare($cid, $sqlClasificador);
    if (!$stmt3 || !odbc_execute($stmt3, [$codClient, $codClient])) {
        throw new Exception("Error al insertar en GVA14ITC: " . odbc_errormsg($cid));
    }

    // Confirma la transacción en la base central
    odbc_commit($cid);
    
    // ===== INICIO: LÓGICA DE ENVÍO AUTOMÁTICO A SUCURSALES =====
    
    $resultadosEnvio = ['exito' => [], 'error' => []];
    $mensajeCreacion = '¡Referido ' . htmlspecialchars($codClient) . ' creado con éxito en la base central!';
    
    // 1. Obtener los datos completos del cliente recién creado
    $datosClienteArray = $cliente->traerDatosEmpleados($codClient);
    if (empty($datosClienteArray)) {
        throw new Exception("No se pudieron recuperar los datos del cliente recién creado para el envío.");
    }
    $datosCliente = $datosClienteArray[0];

    // 2. Obtener la lista de todas las sucursales activas
    $localObj = new Local();
    $sucursales = $localObj->listarLocales();

    // 3. Iterar y enviar a cada sucursal
    foreach ($sucursales as $sucursal) {
        $cidSucursal = null;
        $dbNameSucursal = $sucursal->DbName; // Para los mensajes de error
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
            
            odbc_autocommit($cidSucursal, false);

            // Reutilizamos la lógica de procesar.php para insertar si no existe
            
            // --- GVA14 ---
            $sqlCheckGva14 = "SELECT COUNT(*) AS total FROM GVA14 WHERE COD_CLIENT = ?";
            $stmtCheckGva14 = odbc_prepare($cidSucursal, $sqlCheckGva14);
            if (!odbc_execute($stmtCheckGva14, [$codClient])) throw new Exception("Error al verificar GVA14.");
            $resultGva14 = odbc_fetch_array($stmtCheckGva14);
            
            if ($resultGva14 && $resultGva14['total'] == 0) {
                $sqlInsertarGva14 = "INSERT INTO GVA14 (COD_CLIENT, COD_PROVIN, COD_TRANSP, COD_VENDED, COD_ZONA, COND_VTA, CUIT, CUPO_CREDI, DIR_COM, DOMICILIO, E_MAIL, EXPORTA, FECHA_ALTA, GRUPO_EMPR, IVA_L, LOCALIDAD, N_IMPUESTO, NOM_COM, NRO_LISTA, OBSERVACIO, PERMITE_IS, PORC_DESC, RAZON_SOCI, TELEFONO_1, TELEFONO_2, TIPO, TIPO_DOC, ID_CATEGORIA_IVA, ID_GVA14) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, (SELECT MAX(ID_GVA14) + 1 FROM GVA14))";
                $paramsGva14Sucursal = [$codClient, $datosCliente->COD_PROVIN, '**', $datosCliente->COD_VENDED, '**', $datosCliente->COND_VTA, $datosCliente->CUIT, $datosCliente->CUPO_CREDI, $datosCliente->DIR_COM, $datosCliente->DOMICILIO, $datosCliente->E_MAIL, $datosCliente->EXPORTA, $datosCliente->FECHA_ALTA, $datosCliente->GRUPO_EMPR, 'S', '96', $datosCliente->N_IMPUESTO, $datosCliente->NOM_COM, $datosCliente->NRO_LISTA, $datosCliente->OBSERVACIO, 1, $datosCliente->PORC_DESC, $datosCliente->RAZON_SOCI, $datosCliente->TELEFONO_1, $datosCliente->TELEFONO_2, $datosCliente->TIPO, $datosCliente->TIPO_DOC, $datosCliente->ID_CATEGORIA_IVA];
                $stmtInsertGva14 = odbc_prepare($cidSucursal, $sqlInsertarGva14);
                if (!odbc_execute($stmtInsertGva14, $paramsGva14Sucursal)) throw new Exception("Error al insertar en GVA14.");
            }

            // --- GVA14ITC ---
            $sqlCheckItc = "SELECT COUNT(*) AS total FROM GVA14ITC WHERE CODE = ?";
            $stmtCheckItc = odbc_prepare($cidSucursal, $sqlCheckItc);
            if (!odbc_execute($stmtCheckItc, [$codClient])) throw new Exception("Error al verificar GVA14ITC.");
            $resultItc = odbc_fetch_array($stmtCheckItc);

            if ($resultItc && $resultItc['total'] == 0) {
                $sqlClasificadorSucursal = "INSERT INTO GVA14ITC (CODE, IDFOLDER, CODEA) VALUES (?, (SELECT IDFOLDER FROM GVA14FLD WHERE DESCRIP = 'LOCALES'), ?)";
                $stmtInsertItc = odbc_prepare($cidSucursal, $sqlClasificadorSucursal);
                if (!odbc_execute($stmtInsertItc, [$codClient, $codClient])) throw new Exception("Error al insertar en GVA14ITC.");
            }

            // --- DIRECCION_ENTREGA ---
            $sqlCheckDir = "SELECT COUNT(*) AS total FROM DIRECCION_ENTREGA WHERE COD_CLIENTE = ?";
            $stmtCheckDir = odbc_prepare($cidSucursal, $sqlCheckDir);
            if (!odbc_execute($stmtCheckDir, [$codClient])) throw new Exception("Error al verificar DIRECCION_ENTREGA.");
            $resultDir = odbc_fetch_array($stmtCheckDir);

            if ($resultDir && $resultDir['total'] == 0) {
                $sqlInsertDir = "INSERT INTO [dbo].[DIRECCION_ENTREGA] ([ID_DIRECCION_ENTREGA],[COD_DIRECCION_ENTREGA],[COD_CLIENTE],[DIRECCION],[COD_PROVINCIA],[LOCALIDAD],[HABITUAL],[CODIGO_POSTAL],[TELEFONO1],[TELEFONO2],[TOMA_IMPUESTO_HABITUAL],[FILLER],[OBSERVACIONES],[AL_FIJ_IB3],[ALI_ADI_IB],[ALI_FIJ_IB],[IB_L],[IB_L3],[II_IB3],[LIB],[PORC_L],[HABILITADO],[HORARIO_ENTREGA],[ENTREGA_LUNES],[ENTREGA_MARTES],[ENTREGA_MIERCOLES],[ENTREGA_JUEVES],[ENTREGA_VIERNES],[ENTREGA_SABADO],[ENTREGA_DOMINGO],[CONSIDERA_IVA_BASE_CALCULO_IIBB],[CONSIDERA_IVA_BASE_CALCULO_IIBB_ADIC],[WEB_ADDRESS_ID]) VALUES ((SELECT MAX(ID_DIRECCION_ENTREGA)+1 FROM DIRECCION_ENTREGA), 'PRINCIPAL', ?, '', '', '', 'S', '', '', '', 'N', '', '', 0, 0, 0, 0, 0, 0, 'N', 0, 'S', '', 'N', 'N', 'N', 'N', 'N', 'N', 'N', 'N', 'N', 0)";
                $stmtInsertDir = odbc_prepare($cidSucursal, $sqlInsertDir);
                if (!odbc_execute($stmtInsertDir, [$codClient])) throw new Exception("Error al insertar en DIRECCION_ENTREGA.");
            }
            
            odbc_commit($cidSucursal);
            $resultadosEnvio['exito'][] = "Enviado a " . htmlspecialchars($dbNameSucursal);

        } catch (Exception $e) {
            if ($cidSucursal) odbc_rollback($cidSucursal);
            $resultadosEnvio['error'][] = "Fallo en " . htmlspecialchars($dbNameSucursal) . ": " . $e->getMessage();
            error_log("Fallo de envío para $codClient en sucursal $dbNameSucursal: " . $e->getMessage());
        }
    }
    // ===== FIN: LÓGICA DE ENVÍO AUTOMÁTICO =====

    // ===== NUEVO: Construir el mensaje de feedback consolidado =====
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
        'tipo' => !empty($resultadosEnvio['error']) ? 'warning' : 'success', 
        'mensaje' => $mensajeFeedback
    ];
    
    $urlDestino = (strpos($codClient, 'AS') === 0) ? '../index_directores.php' : '../index.php';
    header("Location: " . $urlDestino);
    exit();

} catch (Exception $e) {
    if (isset($cid) && $cid) odbc_rollback($cid);
    error_log("Error en procesar_crear.php: " . $e->getMessage());
    $_SESSION['feedback'] = [
        'tipo' => 'danger', 
        'mensaje' => 'Ocurrió un error al crear el cliente. La operación ha sido cancelada. Detalle: ' . htmlspecialchars($e->getMessage())
    ];
    $urlDestino = (strpos($codClient, 'AS') === 0) ? '../index_directores.php' : '../index.php';
    header("Location: " . $urlDestino);
    exit();
}