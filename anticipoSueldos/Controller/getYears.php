
<?php
// Controller/getYears.php
header('Content-Type: application/json');

try {
    require_once '../../Class/Anticipo.php';
    $anticipo = new Anticipo();
    
    $sql = "SELECT DISTINCT YEAR(FECHA_ANTICIPO) as year 
            FROM RO_T_FECHA_ANTICIPOS 
            WHERE FECHA_ANTICIPO IS NOT NULL
            ORDER BY year DESC";
    
    $result = $anticipo->obtenerTodos($sql);
    
    $years = array();
    foreach ($result as $row) {
        if (isset($row['year']) && $row['year'] !== null) {
            $years[] = intval($row['year']);
        }
    }
    
    // Agregar años por defecto si no hay datos
    $currentYear = intval(date('Y'));
    $yearsToAdd = [
        $currentYear - 1,
        $currentYear,
        $currentYear + 1
    ];
    
    foreach ($yearsToAdd as $year) {
        if (!in_array($year, $years)) {
            $years[] = $year;
        }
    }
    
    // Ordenar años descendente y eliminar duplicados
    $years = array_unique($years);
    rsort($years);
    
    // Asegurar que devolvemos un array válido
    if (empty($years)) {
        $years = [$currentYear - 1, $currentYear, $currentYear + 1];
    }
    
    echo json_encode(array_values($years));

} catch (Exception $e) {
    error_log("Error en getYears.php: " . $e->getMessage());
    
    // Devolver años por defecto en caso de error
    $currentYear = intval(date('Y'));
    $defaultYears = [$currentYear - 1, $currentYear, $currentYear + 1];
    
    echo json_encode($defaultYears);
}