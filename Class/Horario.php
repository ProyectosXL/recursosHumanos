<?php

class Horario
{
    
    private $cid;
    private $cid_central;


    
    function __construct()
    {

        require_once $_SERVER['DOCUMENT_ROOT'].'/administracion/Class/Conexion.php';
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

    public function traerVendedores($nroLegajo = null){

        $sql = "SELECT * FROM RO_T_LEGAJOS_PERSONAL 
        ";

        if($nroLegajo != null){
            $sql .= " WHERE NRO_LEGAJO = $nroLegajo";
        }

        $stmt = sqlsrv_query( $this->cid_central, $sql );

        $rows = array();

        while( $v = sqlsrv_fetch_array( $stmt) ) {
            $rows[] = $v;
        }

        return $rows;  

    }

    public function editarEmpleado ( $nroLegajo, $direccion, $piso, $depto, $localidad, $codPostal, $sucursalAsignada, $tareaFuente, $email, $telefonoM, $telefonoE) {


        $sql = "UPDATE RO_T_LEGAJOS_PERSONAL SET  DOMICILIO = '$direccion', LOCALIDAD = '$localidad',
        CODIGO_POSTAL = '$codPostal', NUM_SUCURSAL = '$sucursalAsignada',  EMAIL = '$email', TELEFONO = '$telefonoM',
        PISO = '$piso', DEPTO = '$depto', TAREA_HABITUAL = '$tareaFuente' , TELEFONO2 = '$telefonoE'
        WHERE NRO_LEGAJO = '$nroLegajo'";
        
     
        $stmt = sqlsrv_query( $this->cid_central, $sql );

        return true;
    }

    public function traerNuevoNroLegajo () {
        $sql = "SELECT MAX(NRO_LEGAJO)+1 NRO_LEGAJO FROM RO_T_LEGAJOS_PERSONAL";

        $stmt = sqlsrv_query( $this->cid_central, $sql );

        $rows = array();

        while( $v = sqlsrv_fetch_array( $stmt) ) {
            $rows[] = $v;
        }

        return $rows;
    }

    public function traerSucursales () {
            
            $sql = "SELECT NRO_SUCURSAL, DESC_SUCURSAL, COD_CLIENT FROM LAKERBIS.LOCALES_LAKERS.DBO.SUCURSALES_LAKERS WHERE CANAL = 'PROPIOS' OR CANAL = 'EXTERIOR' AND HABILITADO = 1
            UNION ALL
            SELECT NRO_SUCURSAL, DESC_SUCURSAL, COD_CLIENT FROM LAKERBIS.LOCALES_LAKERS.DBO.SUCURSALES_LAKERS WHERE NRO_SUCURSAL = '16' or COD_CLIENT = 'GTCENT' ORDER BY  DESC_SUCURSAL";
    
            $stmt = sqlsrv_query( $this->cid_central, $sql );
    
            $rows = array();
    
            while( $v = sqlsrv_fetch_array( $stmt) ) {
                $rows[] = $v;
            }
    
            return $rows;  
    }

    public function cambiarEstado ($nroLegajo, $estado) {


        $sql = "UPDATE RO_T_LEGAJOS_PERSONAL SET HABILITADO = '$estado 'WHERE NRO_LEGAJO = $nroLegajo";

        $stmt = sqlsrv_query( $this->cid_central, $sql );

        return true;
        
    }

    public function crearEmpleado($stringParaSql, $legajo) {
        $nroLegajo = 0;

        switch ($legajo) {
            
            case 1:
                $min = 13000;
                $max = 13999;
                break;
            
            case 2:
                $min = 10000;
                $max = 10999;
                break;
            
            case 3:
                $min = 14000;
                $max = 14999;
                break;
            
            default:
           
                break;
        }
        
        $nroLegajo = "(SELECT CASE WHEN MAX(NRO_LEGAJO) IS NOT NULL THEN (MAX(NRO_LEGAJO) +1) ELSE $min END AS MAX_LEGAJO FROM RO_T_LEGAJOS_PERSONAL WHERE NRO_LEGAJO BETWEEN $min AND $max)";
   

        $cadenaSinPrimerCaracter = substr($stringParaSql, 1);
        $cadenaFinal = "($nroLegajo,".$cadenaSinPrimerCaracter;

        
        $sql = "SET DATEFORMAT YMD 
        INSERT INTO RO_T_LEGAJOS_PERSONAL (NRO_LEGAJO,NRO_DOCUMENTO, APELLIDO, NOMBRE,APELLIDO_Y_NOMBRE, COD_VENDEDOR, DOMICILIO, PISO, DEPTO, PAIS, LOCALIDAD, CODIGO_POSTAL,NUM_SUCURSAL ,TAREA_HABITUAL , TIPO_CONTRATO, FECHA_INGRESO,EMAIL,TELEFONO, TELEFONO2, HABILITADO, CONTRASEÑA) VALUES $cadenaFinal";

        $stmt = sqlsrv_query( $this->cid_central, $sql );

        return true;
    }

    public function traerHorarios() {

        $sql = "SELECT * FROM FU_HORARIOS";

        $stmt = sqlsrv_query( $this->cid_central, $sql );

        $rows = array();

        while( $v = sqlsrv_fetch_array( $stmt) ) {
            $rows[] = $v;
        }

        return $rows;

    }

    public function crearHorario ($nombre, $horaaDeEntrada, $horaDeSalida, $totalHoras, $horarioDosDias, $contabilizado, $color){

        $sql = "INSERT INTO FU_HORARIOS (NOMBRE, INICIA, TERMINA, TOTAL_HORAS, DOS_DIAS, CONTABILIZA, COLOR) VALUES ('$nombre', '$horaaDeEntrada', '$horaDeSalida', '$totalHoras', '$horarioDosDias', '$contabilizado', '$color')";
  
        $stmt = sqlsrv_query( $this->cid_central, $sql );

        return true;

    }

    public function eliminarHorario ($id) {
            
            $sql = "DELETE FROM FU_HORARIOS WHERE ID = $id";
    
            $stmt = sqlsrv_query( $this->cid_central, $sql );
    
            return true;
    }

    public function editarHorario ($id, $nombre, $horaaDeEntrada, $horaDeSalida, $totalHoras, $horarioDosDias, $contabilizado, $color) {
            
            $sql = "UPDATE FU_HORARIOS SET NOMBRE = '$nombre', INICIA = '$horaaDeEntrada', TERMINA = '$horaDeSalida', TOTAL_HORAS = '$totalHoras', DOS_DIAS = '$horarioDosDias', CONTABILIZA = '$contabilizado', COLOR = '$color' WHERE ID = $id";
    
            $stmt = sqlsrv_query( $this->cid_central, $sql );
    
            return true;
    }

    public function updatePassword ($newPassword, $nroLegajo) {
        
        $sql = "UPDATE RO_T_LEGAJOS_PERSONAL SET CONTRASEÑA = '$newPassword' WHERE NRO_LEGAJO = $nroLegajo";
    
        $stmt = sqlsrv_query( $this->cid_central, $sql );

        return true;
    }
}