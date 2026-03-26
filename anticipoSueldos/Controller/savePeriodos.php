
<?php
// Controller/savePeriodos_datetime_objects.php
header('Content-Type: application/json');

try {
    require_once '../../Class/Anticipo.php';
    $anticipo = new Anticipo();
    
    if (!isset($_POST['periodos'])) {
        throw new Exception('No se recibieron datos de períodos');
    }
    
    $periodos = json_decode($_POST['periodos'], true);
    
    if (!$periodos || !is_array($periodos)) {
        throw new Exception('Formato de datos inválido');
    }
    
    // Usar reflexión para acceder a la conexión privada
    $reflection = new ReflectionClass($anticipo);
    $property = $reflection->getProperty('cid_central');
    $property->setAccessible(true);
    $cid_central = $property->getValue($anticipo);
    
    // Iniciar transacción manualmente
    if (sqlsrv_begin_transaction($cid_central) === false) {
        throw new Exception("Error al iniciar transacción");
    }
    
    foreach ($periodos as $periodo) {
        if (!isset($periodo['periodo'], $periodo['fecha_anticipo'], $periodo['vig_desde'], $periodo['vig_hasta'])) {
            throw new Exception('Datos de período incompletos');
        }
        
        $periodo_key = $periodo['periodo'];
        $fecha_anticipo = $periodo['fecha_anticipo'];
        $vig_desde = $periodo['vig_desde'];
        $vig_hasta = $periodo['vig_hasta'];
        
        error_log("=== PROCESANDO PERÍODO {$periodo_key} ===");
        error_log("Datos originales: Anticipo={$fecha_anticipo}, Desde={$vig_desde}, Hasta={$vig_hasta}");
        
        try {
            // Crear objetos DateTime para enviar directamente a SQL Server
            $fecha_anticipo_dt = new DateTime($fecha_anticipo);
            
            $vig_desde_dt = new DateTime($vig_desde);            
            $vig_hasta_dt = new DateTime($vig_hasta);
            
            error_log("Objetos DateTime creados:");
            error_log("  - Anticipo: " . $fecha_anticipo_dt->format('Y-m-d H:i:s'));
            error_log("  - Desde: " . $vig_desde_dt->format('Y-m-d H:i:s'));
            error_log("  - Hasta: " . $vig_hasta_dt->format('Y-m-d H:i:s'));
            
        } catch (Exception $e) {
            throw new Exception("Error al crear objetos DateTime para período {$periodo_key}: " . $e->getMessage());
        }
        
        // Validaciones básicas (usando fechas originales)
        $vig_desde_validation = new DateTime($vig_desde);
        $vig_hasta_validation = new DateTime($vig_hasta);
        $fecha_anticipo_validation = new DateTime($fecha_anticipo . ' 23:59:59');
        
        if ($vig_hasta_validation <= $vig_desde_validation) {
            throw new Exception("La fecha hasta debe ser posterior a la fecha desde en período {$periodo_key}");
        }
        
        if ($vig_desde_validation > $fecha_anticipo_validation || $vig_hasta_validation > $fecha_anticipo_validation) {
            throw new Exception("Las fechas de vigencia no pueden ser superiores a la fecha del anticipo en período {$periodo_key}");
        }
        
        // Verificar si existe el período
        $sql_check = "SELECT COUNT(*) as count FROM RO_T_FECHA_ANTICIPOS WHERE PERIODO = ?";
        $stmt_check = sqlsrv_query($cid_central, $sql_check, array($periodo_key));
        
        if ($stmt_check === false) {
            $errors = sqlsrv_errors();
            throw new Exception("Error al verificar período existente: " . print_r($errors, true));
        }
        
        $row = sqlsrv_fetch_array($stmt_check);
        $existe = $row['count'] > 0;
        sqlsrv_free_stmt($stmt_check);
        
        if ($existe) {
            // Actualizar - usar objetos DateTime directamente
            $sql = "UPDATE RO_T_FECHA_ANTICIPOS 
                    SET FECHA_ANTICIPO = ?, VIG_DESDE = ?, VIG_HASTA = ?
                    WHERE PERIODO = ?";
            $params = array($fecha_anticipo_dt, $vig_desde_dt, $vig_hasta_dt, $periodo_key);
        } else {
            // Insertar - usar objetos DateTime directamente  
            $sql = "INSERT INTO RO_T_FECHA_ANTICIPOS 
                    (PERIODO, FECHA_ANTICIPO, VIG_DESDE, VIG_HASTA) 
                    VALUES (?, ?, ?, ?)";
            $params = array($periodo_key, $fecha_anticipo_dt, $vig_desde_dt, $vig_hasta_dt);
        }
        
        error_log("Ejecutando SQL: {$sql}");
        error_log("Con " . count($params) . " parámetros (objetos DateTime)");
        
        $stmt = sqlsrv_query($cid_central, $sql, $params);
        
        if ($stmt === false) {
            $errors = sqlsrv_errors();
            error_log("ERROR SQL:");
            foreach ($errors as $error) {
                error_log("  SQLSTATE: " . $error['SQLSTATE']);
                error_log("  Código: " . $error['code']);
                error_log("  Mensaje: " . $error['message']);
            }
            throw new Exception("Error SQL en período {$periodo_key}: " . print_r($errors, true));
        }
        
        sqlsrv_free_stmt($stmt);
        error_log("✓ Período {$periodo_key} guardado exitosamente");
    }
    
    // Confirmar transacción
    if (sqlsrv_commit($cid_central) === false) {
        throw new Exception("Error al confirmar transacción");
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Períodos guardados correctamente',
        'count' => count($periodos)
    ]);
    
} catch (Exception $e) {
    // Rollback de la transacción
    if (isset($cid_central)) {
        sqlsrv_rollback($cid_central);
    }
    
    error_log("ERROR FINAL: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>