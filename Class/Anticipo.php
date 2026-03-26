
<?php

date_default_timezone_set('America/Argentina/Buenos_Aires');

class Anticipo
{
    
    private $cid;
    private $cid_central;


    
    function __construct()
    {
        date_default_timezone_set('America/Argentina/Buenos_Aires'); 
        require_once 'C:\xampp\htdocs\administracion\Class\conexion.php';
        $this->cid = new Conexion();
        $this->cid_central = $this->cid->conectar('central');
      

    } 


    private function retornarArray($sqlEnviado, $db = 'central'){

        $sql = $sqlEnviado;

        $cid_central = $this->cid->conectar($db);

        $stmt = sqlsrv_query( $cid_central, $sql );

        $rows = array();

        while( $v = sqlsrv_fetch_array( $stmt) ) {
            $rows[] = $v;
        }

        return $rows;  

    }

    public function traerEmpleadosGrupo($sector, $nroSucursal) {
        $sql = "SELECT 
        a.NRO_LEGAJO,
        a.APELLIDO_Y_NOMBRE,
        COALESCE(d.IMPORTE, 0) AS IMPORTE
        FROM RO_V_LEGAJO_GRUPOS a
        LEFT JOIN RO_T_DETALLE_ANTICIPOS d ON a.NRO_LEGAJO = d.NRO_LEGAJO
        AND MONTH(d.FECHA_CARGA) = MONTH(GETDATE())
        AND YEAR(d.FECHA_CARGA) = YEAR(GETDATE())
        WHERE a.SECTOR LIKE ?  AND 
        a.NRO_SUCURS = ?
        ORDER BY a.APELLIDO_Y_NOMBRE";
    
        $params = array($sector, $nroSucursal);
    
        $stmt = sqlsrv_query($this->cid_central, $sql, $params);
    
        if ($stmt === false) {
            return array();
        }
    
        $rows = array();
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $rows[] = $row;
        }
    
        return $rows;
    }
    

    public function traerEmpleadosIndividual($search = ''){
        $sql = "SELECT TOP 10 NRO_LEGAJO, NRO_DOCUMENTO, APELLIDO_Y_NOMBRE 
                FROM [TANGO-SUELDOS].LAKERS_CORP_SA.DBO.RO_V_LEGAJO
                WHERE APELLIDO_Y_NOMBRE LIKE ?
                ORDER BY APELLIDO_Y_NOMBRE";
        
        $params = array("%$search%");
        $stmt = sqlsrv_query($this->cid_central, $sql, $params);
        
        if($stmt === false) {
            return array();
        }
    
        $rows = array();
        while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $rows[] = $row;
        }
    
        return $rows;
    }

    public function existeAnticipo($legajo, $periodo) {
        $sql = "SELECT COUNT(*) as total 
                FROM RO_T_DETALLE_ANTICIPOS 
                WHERE NRO_LEGAJO = ? AND PERIODO = ?";
        
        $params = array($legajo, $periodo);
        $stmt = sqlsrv_query($this->cid_central, $sql, $params);
    
        if ($stmt === false) {
            throw new Exception('Error al verificar duplicados: ' . json_encode(sqlsrv_errors()));
        }
    
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    
        if ($row === false || !isset($row['total'])) {
            throw new Exception('No se pudo obtener el resultado de la consulta de anticipos.');
        }
    
        return $row['total'] > 0;
    }

    public function validarDNI($dni, $legajo) {

        $sql = "SELECT COUNT(*) as total 
                FROM [TANGO-SUELDOS].LAKERS_CORP_SA.DBO.RO_V_LEGAJO 
                WHERE NRO_DOCUMENTO = ? AND NRO_LEGAJO = ?";
        
        $params = array($dni, $legajo);
        $stmt = sqlsrv_query($this->cid_central, $sql, $params);
        
        if ($stmt === false) {
            return false;
        }
    
        $row = sqlsrv_fetch_array($stmt);
        return $row['total'] > 0;
    }

    public function traerDNI($legajo) {

        $sql = "SELECT NRO_DOCUMENTO
                FROM [TANGO-SUELDOS].LAKERS_CORP_SA.DBO.RO_V_LEGAJO 
                WHERE NRO_LEGAJO = ?";
        
        $params = array($legajo);
        $stmt = sqlsrv_query($this->cid_central, $sql, $params);
        
        if ($stmt === false) {
            return false;
        }
    
        $row = sqlsrv_fetch_array($stmt);
        return $row['NRO_DOCUMENTO'];
    }

    public function obtenerValor($sql, $params = array()) {
        try {
            $stmt = sqlsrv_query($this->cid_central, $sql, $params);
            
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                error_log("Error SQL: " . print_r($errors, true));
                throw new Exception("Error en la consulta SQL");
            }
            
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_NUMERIC);
            return $row ? $row[0] : null;
        } catch (Exception $e) {
            error_log("Error en obtenerValor: " . $e->getMessage());
            throw $e;
        }
    }

    public function beginTransaction() {
        if (sqlsrv_begin_transaction($this->cid_central) === false) {
            throw new Exception("Error al iniciar la transacción");
        }
    }

    public function commit() {
        if (sqlsrv_commit($this->cid_central) === false) {
            throw new Exception("Error al confirmar la transacción");
        }
    }

    public function rollback() {
        if (sqlsrv_rollback($this->cid_central) === false) {
            throw new Exception("Error al revertir la transacción");
        }
    }

    public function ejecutar($sql, $params = array()) {
        $stmt = sqlsrv_query($this->cid_central, $sql, $params);
        if ($stmt === false) {
            throw new Exception("Error al ejecutar la consulta: " . print_r(sqlsrv_errors(), true));
        }
        return true;
    }

    public function obtenerPeriodoActual()
    {
        $sql = "SELECT PERIODO, FECHA_ANTICIPO, VIG_DESDE, VIG_HASTA 
                FROM RO_T_FECHA_ANTICIPOS 
                WHERE GETDATE() BETWEEN VIG_DESDE AND VIG_HASTA";
        
        $stmt = sqlsrv_query($this->cid_central, $sql);
        
        if ($stmt === false) {
            throw new Exception("Error al consultar el período");
        }
        
        return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    }

     // Método para obtener los períodos
     public function obtenerPeriodos()
        {
                $sql = "SELECT PERIODO, COUNT(*) as TOTAL_REGISTROS
                FROM RO_T_DETALLE_ANTICIPOS 
                GROUP BY PERIODO
                HAVING COUNT(*) > 0
                ORDER BY 
                    CAST(SUBSTRING(PERIODO, CHARINDEX('-', PERIODO) + 1, LEN(PERIODO)) AS INT) DESC,
                    CAST(SUBSTRING(PERIODO, 1, CHARINDEX('-', PERIODO) - 1) AS INT) DESC";
                    
        $stmt = sqlsrv_query($this->cid_central, $sql);
        
        if ($stmt === false) {
            throw new Exception("Error al obtener períodos con datos: " . print_r(sqlsrv_errors(), true));
        }
        
        $periodos = array();
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $periodos[] = array(
                'PERIODO' => $row['PERIODO'],
                'TOTAL_REGISTROS' => $row['TOTAL_REGISTROS']
            );
        }
        
        return $periodos;
     }
 
     // Método para obtener los anticipos
     public function obtenerAnticipos($start, $length, $search = '', $periodo = '')
    {
        $sql = "SELECT a.NRO_LEGAJO,
                    a.APELLIDO_Y_NOMBRE,
                    a.DNI,
                    a.PERIODO,
                    CONVERT(decimal(10,2), a.IMPORTE) as IMPORTE,
                    FORMAT(a.FECHA_CARGA, 'dd/MM/yyyy HH:mm') as FECHA_CARGA,
                    b.DESC_DEPARTAMENTO
            FROM RO_T_DETALLE_ANTICIPOS a
            LEFT JOIN [TANGO-SUELDOS].LAKERS_CORP_SA.DBO.RO_V_LEGAJO b ON a.NRO_LEGAJO = b.NRO_LEGAJO
            WHERE 1=1";
        
        $params = array();
        
        if (!empty($periodo)) {
            $sql .= " AND a.PERIODO = ?";
            $params[] = $periodo;
        }
        
        if (!empty($search)) {
            $sql .= " AND (a.APELLIDO_Y_NOMBRE LIKE ? OR CAST(a.DNI as VARCHAR) LIKE ? OR CAST(a.NRO_LEGAJO as VARCHAR) LIKE ?)";
            $searchParam = '%' . $search . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        $sql .= " ORDER BY a.FECHA_CARGA DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
        $params[] = (int)$start;
        $params[] = (int)$length;
        
        $stmt = sqlsrv_query($this->cid_central, $sql, $params);
        
        if ($stmt === false) {
            throw new Exception("Error al obtener anticipos: " . print_r(sqlsrv_errors(), true));
        }
        
        $data = array();
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $data[] = $row;
        }
        
        return $data;
    }
     // Método para contar total de registros
     public function contarAnticipos($search = '', $periodo = '')
     {
         $sql = "SELECT COUNT(*) as total FROM RO_T_DETALLE_ANTICIPOS WHERE 1=1";
         $params = array();
         
         if (!empty($periodo)) {
             $sql .= " AND PERIODO = ?";
             $params[] = $periodo;
         }
         
         if (!empty($search)) {
             $sql .= " AND (APELLIDO_Y_NOMBRE LIKE ? OR CAST(DNI as VARCHAR) LIKE ? OR CAST(NRO_LEGAJO as VARCHAR) LIKE ?)";
             $searchParam = '%' . $search . '%';
             $params[] = $searchParam;
             $params[] = $searchParam;
             $params[] = $searchParam;
         }
         
         $stmt = sqlsrv_query($this->cid_central, $sql, $params);
         
         if ($stmt === false) {
             throw new Exception("Error al contar anticipos: " . print_r(sqlsrv_errors(), true));
         }
         
         $row = sqlsrv_fetch_array($stmt);
         return $row['total'];
     }

    public function obtenerPeriodoAnticipo()
    {
        $sql = "SELECT TOP 1 PERIODO, 
                       FORMAT(FECHA_ANTICIPO, 'dd/MM/yyyy') as FECHA_ANTICIPO,
                       FORMAT(VIG_DESDE, 'dd/MM/yyyy HH:mm') as VIG_DESDE,
                       FORMAT(VIG_HASTA, 'dd/MM/yyyy HH:mm') as VIG_HASTA
                FROM RO_T_FECHA_ANTICIPOS 
                WHERE MONTH(FECHA_ANTICIPO) = MONTH(GETDATE()) 
                AND YEAR(FECHA_ANTICIPO) = YEAR(GETDATE())";
    
        $stmt = sqlsrv_query($this->cid_central, $sql);
    
        if ($stmt === false) {
            throw new Exception("Error al ejecutar la consulta: " . print_r(sqlsrv_errors(), true));
        }
    
        return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    }
 
    public function obtenerAnticipoDetalle($legajo, $periodo)
    {
        $sql = "SELECT NRO_LEGAJO, PERIODO, IMPORTE, FECHA_CARGA 
                FROM RO_T_DETALLE_ANTICIPOS 
                WHERE NRO_LEGAJO = ? AND PERIODO = ?";
        
        $params = array($legajo, $periodo);
        $stmt = sqlsrv_query($this->cid_central, $sql, $params);
        
        if ($stmt === false) {
            throw new Exception("Error al obtener detalle de anticipo: " . print_r(sqlsrv_errors(), true));
        }
        
        return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    }

    public function getFechasPorPeriodo($periodoActual)
    {
        
        $sql = "SELECT VIG_DESDE, VIG_HASTA FROM RO_T_FECHA_ANTICIPOS WHERE PERIODO = '$periodoActual'";

        $stmt = sqlsrv_query($this->cid_central, $sql);

        if ($stmt === false) {
            throw new Exception("Error al obtener fechas por periodo: " . print_r(sqlsrv_errors(), true));
        }

        $rows = array();
        while( $v = sqlsrv_fetch_array( $stmt, SQLSRV_FETCH_ASSOC ) ) {
            $rows['fechaDesde'] = $v['VIG_DESDE']->format('Y-m-d');
            $rows['fechaHasta'] = $v['VIG_HASTA']->format('Y-m-d');
        }
        return $rows;  
        
    }

    public function getEmails($periodoActual)
    {
        $sql = "SELECT EMAIL FROM RO_T_DESTINATARIOS_MAIL WHERE TIPO_NOTIFICACION = 'ANTICIPO_SUELDOS' AND ACTIVO = 1";

        $stmt = sqlsrv_query($this->cid_central, $sql);

        if ($stmt === false) {
            throw new Exception("Error al obtener emails por periodo: " . print_r(sqlsrv_errors(), true));
        }

        $rows = array();
        while( $v = sqlsrv_fetch_array( $stmt, SQLSRV_FETCH_ASSOC ) ) {
            $rows[] = $v['EMAIL'];
        }
        return $rows;  
    }

        public function obtenerTodos($sql, $params = array()) {
        $stmt = sqlsrv_query($this->cid_central, $sql, $params);
        
        if ($stmt === false) {
            throw new Exception("Error en la consulta: " . print_r(sqlsrv_errors(), true));
        }
        
        $rows = array();
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $rows[] = $row;
        }
        
        sqlsrv_free_stmt($stmt);
        return $rows;
    }

}