<?php
// Class/Cliente.php

require_once __DIR__.'/Conexion.php';

class Cliente {

    private $cid;

    public function __construct() {
        try {
            $conexion = new Conexion();
            $this->cid = $conexion->conexionLakerbis();
        } catch (Exception $e) {
            error_log($e->getMessage());
            die("Error crítico: No se pudo conectar a la base de datos de clientes.");
        }
    }

    private function ejecutarConsulta($sql, $params = []) {
        $stmt = odbc_prepare($this->cid, $sql);
        if (!$stmt) {
            throw new Exception("Error al preparar la consulta: " . odbc_errormsg($this->cid));
        }

        if (!odbc_execute($stmt, $params)) {
            throw new Exception("Error al ejecutar la consulta: " . odbc_errormsg($this->cid));
        }

        $data = [];
        while ($v = odbc_fetch_object($stmt)) {
            $data[] = $v;
        }
        return $data;
    }

    public function listarEmpleados($cod) {
        $sql = "
            SELECT 
                COD_CLIENT AS Codigo, 
                NOM_COM AS Nombre 
            FROM SJ_VIEW_GVA14 
            WHERE COD_CLIENT LIKE ? 
            ORDER BY COD_CLIENT DESC
        ";
        return $this->ejecutarConsulta($sql, [$cod.'%']);
    }

    public function traerDatosEmpleados($codClient) {
        $sql = "SELECT * FROM SJ_VIEW_GVA14 WHERE COD_CLIENT = ?";
        return $this->ejecutarConsulta($sql, [$codClient]);
    }
    
    public function consultarClientes($filtros = []) {
        $sql = "
            SELECT 
                COD_CLIENT AS Codigo, 
                NOM_COM AS Nombre,
                CUIT AS Cuit,
                PORC_DESC AS PorcentajeDescuento,
                FECHA_ALTA AS FechaAlta,
                CASE WHEN FECHA_INHA = '1800-01-01' THEN 1 ELSE 0 END AS Habilitado
            FROM SJ_VIEW_GVA14
        ";

        $whereConditions = [];
        $params = [];

        if (!empty($filtros['tipo'])) {
            $whereConditions[] = "COD_CLIENT LIKE ?";
            $params[] = $filtros['tipo'] . '%';
        }
        
        if (isset($filtros['estado']) && $filtros['estado'] !== '') {
            if ($filtros['estado'] == '1') {
                $whereConditions[] = "FECHA_INHA = '1800-01-01'";
            } else {
                $whereConditions[] = "FECHA_INHA <> '1800-01-01'";
            }
        }

        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(' AND ', $whereConditions);
        }
        
        $sql .= " ORDER BY COD_CLIENT DESC";
        
        return $this->ejecutarConsulta($sql, $params);
    }
    
    public function cambiarEstadoCliente($codigoCliente, $habilitar) {
        $fechaInha = $habilitar ? '1800-01-01' : date('Y-m-d');
        $sql = "UPDATE GVA14 SET FECHA_INHA = ? WHERE COD_CLIENT = ?";
        $stmt = odbc_prepare($this->cid, $sql);
        if (!$stmt) {
            error_log("Error al preparar la consulta de actualización de estado: " . odbc_errormsg($this->cid));
            return false;
        }
        if (odbc_execute($stmt, [$fechaInha, $codigoCliente])) {
            return true;
        } else {
            error_log("Error al ejecutar la actualización de estado para $codigoCliente: " . odbc_errormsg($this->cid));
            return false;
        }
    }
    public function existeDni($dni) {
        // Si el DNI está vacío o es '0', no lo consideramos para la validación.
        if (empty($dni) || $dni == '0') {
            return false;
        }
        
        $sql = "SELECT COUNT(*) AS total FROM GVA14 WHERE CUIT = ?";
        
        $stmt = odbc_prepare($this->cid, $sql);
        if (!$stmt) {
            throw new Exception("Error al preparar la consulta para verificar DNI: " . odbc_errormsg($this->cid));
        }
        
        if (!odbc_execute($stmt, [$dni])) {
            throw new Exception("Error al ejecutar la consulta para verificar DNI: " . odbc_errormsg($this->cid));
        }

        $result = odbc_fetch_array($stmt);
        return ($result && $result['total'] > 0);
    }
    public function obtenerResumenDescuentos($tipoCliente, $fechaDesde, $fechaHasta) {
    $sql = "
        SELECT 
            COD_CLIENT, 
            NOMBRE_CLI, 
            COUNT(*) AS CantidadBonificaciones,
            CAST(SUM(IMPORTE_BO) AS FLOAT) AS TotalBonificado,
            CAST(AVG(PORC_BONIF) AS FLOAT) AS PromedioPorcentaje
        FROM CTA02 
        WHERE 
            COD_CLIENT LIKE ? 
            AND CAST(FECHA_EMIS AS DATE) BETWEEN ? AND ?
            AND PORC_BONIF > 0
        GROUP BY 
            COD_CLIENT, 
            NOMBRE_CLI
        ORDER BY 
            CantidadBonificaciones DESC
    ";
    
    // El '%' se añade aquí para que la consulta preparada funcione correctamente.
    $params = [$tipoCliente . '%', $fechaDesde, $fechaHasta];
    
    return $this->ejecutarConsulta($sql, $params);
}

public function obtenerEmpleadosExcedidos($fechaDesde, $fechaHasta, $limite) {
    $sql = "
        SELECT 
            COD_CLIENT, 
            NOMBRE_CLI, 
            COUNT(*) AS CantidadBonificaciones,
            CAST(SUM(IMPORTE_BO) AS FLOAT) AS TotalBonificado,
            CAST(AVG(PORC_BONIF) AS FLOAT) AS PromedioPorcentaje
        FROM CTA02 
        WHERE 
            COD_CLIENT LIKE 'E%' 
            AND CAST(FECHA_EMIS AS DATE) BETWEEN ? AND ?
            AND PORC_BONIF > 0
        GROUP BY 
            COD_CLIENT, 
            NOMBRE_CLI
        HAVING
            COUNT(*) > ?
        ORDER BY 
            CantidadBonificaciones DESC
    ";
    
    $params = [$fechaDesde, $fechaHasta, $limite];
    
    return $this->ejecutarConsulta($sql, $params);
}
    public function obtenerClientesParaBusqueda($tipoCliente) {
        $sql = "
            SELECT 
                CUIT, 
                NOM_COM 
            FROM GVA14 
            WHERE 
                COD_CLIENT LIKE ? 
                AND CUIT IS NOT NULL 
                AND CUIT <> '0' 
                AND RTRIM(CUIT) <> ''
            ORDER BY 
                NOM_COM ASC
        ";
        
        $params = [$tipoCliente . '%'];
        
        $stmt = odbc_prepare($this->cid, $sql);
        if (!$stmt || !odbc_execute($stmt, $params)) {
            throw new Exception("Error al obtener la lista de clientes: " . odbc_errormsg($this->cid));
        }

        $data = [];
        while ($v = odbc_fetch_object($stmt)) {
            $data[] = $v;
        }
        return $data;
    }


}