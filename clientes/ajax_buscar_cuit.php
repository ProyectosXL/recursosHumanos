<?php
header('Content-Type: application/json; charset=utf-8');
set_time_limit(180);
ini_set('max_execution_time', 180);

require_once 'config.php';
require_once ROOT_PATH . 'Class/Conexion.php';

function limpiarTexto($texto) {
    if ($texto === null || $texto === '') return '';
    $t = preg_replace('/[[:cntrl:]]/', '', $texto);
    if (function_exists('mb_check_encoding') && mb_check_encoding($t, 'UTF-8')) return $t;
    return function_exists('mb_convert_encoding') ? mb_convert_encoding($t, 'UTF-8', 'ISO-8859-1') : $t;
}

$cuit  = preg_replace('/[^0-9]/', '', trim($_GET['cuit'] ?? ''));
$canal = trim($_GET['canal'] ?? 'PROPIOS');

if (strlen($cuit) < 7 || strlen($cuit) > 11) {
    echo json_encode(['ok' => false, 'error' => 'DNI/CUIT inválido.']);
    exit;
}

$encontrado   = [];
$noEncontrado = [];
$errores      = [];

try {
    /* ── 1. Obtener lista de sucursales desde lakerbis ──────────── */
    $conexionModel = new Conexion();
    $cid           = $conexionModel->conexionLakerbis();

    $stmt = sqlsrv_query(
        $cid,
        "SELECT A.NRO_SUCURSAL, A.DESC_SUCURSAL, B.CONEXION_DNS, B.BASE_NOMBRE,
                B.USUARIO_DNS, B.CLAVE_DNS
         FROM   SUCURSAL          A
         INNER JOIN SUCURSALES_LAKERS B ON A.NRO_SUCURSAL = B.NRO_SUCURSAL
         WHERE  B.HABILITADO   = 1
           AND  B.CANAL        = ?
           AND  B.CONEXION_DNS IS NOT NULL
           AND  B.BASE_NOMBRE  IS NOT NULL
         ORDER BY A.NRO_SUCURSAL",
        [$canal]
    );

    if ($stmt === false) {
        throw new Exception("Error al obtener sucursales: " . Conexion::errorMsg());
    }

    $sucursales = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $sucursales[] = $row;
    }
    sqlsrv_free_stmt($stmt);
    sqlsrv_close($cid);

    error_log("[ajax_buscar_cuit] CUIT=$cuit CANAL=$canal | Sucursales a consultar: " . count($sucursales));

    /* ── 2. Consultar cada sucursal individualmente ──────────────── */
    foreach ($sucursales as $suc) {
        $nro   = (int)$suc['NRO_SUCURSAL'];
        $desc  = limpiarTexto($suc['DESC_SUCURSAL'] ?? '');
        $dns   = trim($suc['CONEXION_DNS']);   // "host" o "host,port"
        $bbdd  = trim($suc['BASE_NOMBRE']);
        $user  = $suc['USUARIO_DNS'] ?? DB_USER_SERVIDOR;
        $pass  = $suc['CLAVE_DNS']   ?? DB_PASS_SERVIDOR;
        $label = "$nro - $desc";

        /* Parsear host y puerto para el pre-check TCP */
        $partes = explode(',', $dns);
        $tcpHost = trim($partes[0]);
        $tcpPort = isset($partes[1]) ? (int)trim($partes[1]) : 1433;

        /* Pre-check TCP: 1.5 s — descarta hosts inalcanzables rápido */
        $socket = @fsockopen($tcpHost, $tcpPort, $errno, $errstr, 1.5);
        if ($socket === false) {
            $errores[] = "$label: Sin red ($errstr)";
            continue;
        }
        fclose($socket);

        /* Conexión SQLSRV con LoginTimeout corto */
        try {
            $branchCid = (new Conexion())->conexionSucursalPorHost(
                $dns, $bbdd, $user, $pass, 5
            );

            $branchStmt = sqlsrv_query(
                $branchCid,
                "SELECT TOP 1 CUIT FROM GVA14 WHERE CUIT = ?",
                [$cuit]
            );

            if ($branchStmt === false) {
                $errores[] = "$label: " . Conexion::errorMsg();
            } elseif (sqlsrv_fetch($branchStmt) !== false) {
                $encontrado[] = $label;
            } else {
                $noEncontrado[] = $label;
            }

            if ($branchStmt !== false) sqlsrv_free_stmt($branchStmt);
            sqlsrv_close($branchCid);

        } catch (Exception $e) {
            $errores[] = "$label: " . $e->getMessage();
        }
    }

    error_log("[ajax_buscar_cuit] Completado — Enc=" . count($encontrado)
        . " NoEnc=" . count($noEncontrado) . " Err=" . count($errores));

} catch (Exception $e) {
    error_log("[ajax_buscar_cuit] ERROR GENERAL: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}

echo json_encode([
    'ok'           => true,
    'encontrado'   => $encontrado,
    'noEncontrado' => $noEncontrado,
    'errores'      => $errores,
], JSON_UNESCAPED_UNICODE);
