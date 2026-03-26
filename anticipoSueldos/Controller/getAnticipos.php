
<?php
// Controller/getAnticipos.php
header('Content-Type: application/json');

try {
    require_once '../../Class/Anticipo.php';
    $anticipo = new Anticipo();
    
    // Parámetros de DataTables
    $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
    $search = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
    $periodo = isset($_POST['periodo']) ? $_POST['periodo'] : '';
    $departamento = isset($_POST['departamento']) ? $_POST['departamento'] : '';
    
    // Parámetros de ordenamiento
    $orderColumnIndex = isset($_POST['order'][0]['column']) ? intval($_POST['order'][0]['column']) : 5;
    $orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'desc';
    
    // Mapeo de índices de columnas a nombres de campos
    $columns = ['NRO_LEGAJO', 'APELLIDO_Y_NOMBRE', 'DNI', 'PERIODO', 'IMPORTE', 'FECHA_CARGA', 'DESC_DEPARTAMENTO'];
    $orderColumn = isset($columns[$orderColumnIndex]) ? $columns[$orderColumnIndex] : 'FECHA_CARGA';
    
    error_log("getAnticipos - Start: $start, Length: $length, Search: '$search', Periodo: '$periodo', Departamento: '$departamento', Order: $orderColumn $orderDir");
    
    // Obtener datos usando los métodos de la clase
    $data = $anticipo->obtenerAnticipos($start, $length, $search, $periodo, $orderColumn, $orderDir, $departamento);
    $total = $anticipo->contarAnticipos('', $periodo, $departamento);
    $filtered = $anticipo->contarAnticipos($search, $periodo, $departamento);
    
    error_log("getAnticipos - Total: $total, Filtered: $filtered, Data count: " . count($data));
    
    // Respuesta para DataTables
    $response = array(
        "draw" => isset($_POST['draw']) ? intval($_POST['draw']) : 1,
        "recordsTotal" => $total,
        "recordsFiltered" => $filtered,
        "data" => $data
    );
    
    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error en getAnticipos.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'data' => array(),
        'recordsTotal' => 0,
        'recordsFiltered' => 0
    ]);
}
?>