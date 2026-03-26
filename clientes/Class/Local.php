<?php
// Class/Local.php

require_once __DIR__.'/Conexion.php';

class Local {
    private $cid;

    public function __construct() {
        try {
            $conexion = new Conexion();
            $this->cid = $conexion->conexionLakerbis(); 
        } catch (Exception $e) {
            error_log($e->getMessage());
            die("Error crítico: No se pudo conectar a la base de datos de sucursales.");
        }
    }

    private function traerDatos($sql){
        $result=odbc_exec($this->cid,$sql) or die(exit("Error en odbc_exec: ".odbc_errormsg($this->cid)));
        $data = [];
        while($v=odbc_fetch_object($result)){
            $data[] = $v;
        };
        return $data;
    }

    public function listarLocales(){
        $sql="
            SELECT 
                A.NRO_SUCURSAL, 
                A.DESC_SUCURSAL, 
                B.CONEXION_DNS AS Host,
                B.BASE_NOMBRE AS DbName,
                B.USUARIO_DNS AS UsuarioDns,
                B.CLAVE_DNS AS ClaveDns,
                CASE 
                    WHEN C.LOCALIDAD = 'ROSARIO' THEN 'ROSARIO'
                    WHEN C.LOCALIDAD = 'MAR DEL PLATA' THEN 'MARDEL'
                    ELSE 'AMBA'
                END AS LOCALIDAD
            FROM SUCURSAL A 
            INNER JOIN SUCURSALES_LAKERS B ON A.NRO_SUCURSAL = B.NRO_SUCURSAL
            LEFT JOIN GVA14 C ON A.NRO_SUCURSAL = C.N_IMPUESTO
            WHERE A.NRO_SUCURSAL BETWEEN 2 AND 300
            AND B.CONEXION_DNS IS NOT NULL
            AND B.BASE_NOMBRE IS NOT NULL
            AND B.HABILITADO = 1 
            AND B.CANAL = 'PROPIOS'
            GROUP BY A.NRO_SUCURSAL, A.DESC_SUCURSAL, B.CONEXION_DNS, B.BASE_NOMBRE, B.USUARIO_DNS, B.CLAVE_DNS, C.LOCALIDAD
            ORDER BY A.NRO_SUCURSAL
        ";
        return $this->traerDatos($sql);
    }
}