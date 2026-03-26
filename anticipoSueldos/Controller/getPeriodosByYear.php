

<?php
// Controller/getPeriodosByYear.php (Versión final con corrección SQL)
header('Content-Type: application/json');

try {
    require_once '../../Class/Anticipo.php';
    $anticipo = new Anticipo();
    
    $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
    
    if ($year < 2000 || $year > 2100) {
        throw new Exception('Año inválido');
    }
    
    // Usar DATEADD para restar 3 horas directamente en SQL
    $sql = "SELECT 
                ID,
                PERIODO,
                CONVERT(varchar, FECHA_ANTICIPO, 23) as FECHA_ANTICIPO,
                FORMAT(DATEADD(HOUR, -3, VIG_DESDE), 'yyyy-MM-ddTHH:mm') as VIG_DESDE,
                FORMAT(DATEADD(HOUR, -3, VIG_HASTA), 'yyyy-MM-ddTHH:mm') as VIG_HASTA
            FROM RO_T_FECHA_ANTICIPOS 
            WHERE YEAR(FECHA_ANTICIPO) = ?
            ORDER BY CAST(SUBSTRING(PERIODO, 1, CHARINDEX('-', PERIODO) - 1) AS INT)";
    
    $result = $anticipo->obtenerTodos($sql, array($year));
    
    $periodos = array();
    foreach ($result as $row) {
        $periodo = array(
            'ID' => isset($row['ID']) ? $row['ID'] : null,
            'PERIODO' => isset($row['PERIODO']) ? $row['PERIODO'] : '',
            'FECHA_ANTICIPO' => isset($row['FECHA_ANTICIPO']) ? $row['FECHA_ANTICIPO'] : '',
            'VIG_DESDE' => isset($row['VIG_DESDE']) ? $row['VIG_DESDE'] : '',
            'VIG_HASTA' => isset($row['VIG_HASTA']) ? $row['VIG_HASTA'] : ''
        );
        $periodos[] = $periodo;
    }
    
    echo json_encode($periodos);

} catch (Exception $e) {
    error_log("Error en getPeriodosByYear.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'success' => false
    ]);
}
