<?php

class Vendedor
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

    public function traerSucursales(){

        $db = 'central';

        $sql = " 
        SELECT NRO_SUCURSAL, DESC_SUCURSAL FROM [LAKERBIS].locales_lakers.dbo.SUCURSALES_LAKERS WHERE CANAL IN ('PROPIOS') 
        AND NRO_SUC_MADRE IS NULL AND HABILITADO = 1;
        ";

        if(isset($_SESSION['entorno'] ) && $_SESSION['entorno'] == 'uy'){

            $sql = "SELECT NRO_SUCURSAL, DESC_SUCURSAL FROM SUCURSALES_LAKERS WHERE CANAL = 'EXTERIOR'";
            $db = 'locales';
        }

        $rows = $this->retornarArray($sql, $db);

        return $rows;

    }  

    public function crearGrupoEnc($nombreGrupo){

        $sql = "INSERT INTO FU_GRUPOS_VENDEDORES (NOMBRE, CREATED_AT, UPDATED_AT ) VALUES ('$nombreGrupo', GETDATE(), GETDATE())";

        $stmt = sqlsrv_query( $this->cid_central, $sql );

        if( $stmt === false ) {
            die( print_r( sqlsrv_errors(), true));
        }

        return true;
    }
      

    public function traerGrupos () {
        
        $sql = "SELECT * FROM FU_GRUPOS_VENDEDORES";

        $rows = $this->retornarArray($sql);

        return $rows;

    }


    public function crearGrupoDet($localesPorGrupo){

        $sql = "INSERT INTO FU_LOCALES_POR_GRUPOS_VENDEDORES (NRO_SUCURSAL, NOMBRE_GRUPO,  CREATED_AT, UPDATED_AT ) VALUES $localesPorGrupo";
  
        $stmt = sqlsrv_query( $this->cid_central, $sql );

        if( $stmt === false ) {
            die( print_r( sqlsrv_errors(), true));
        }

        return true;
    }
      

    public function traerVendedores () {
    
        // Use the simplified query from GVA23 table
        $sql = "SELECT COD_VENDED, NOMBRE_VEN, INHABILITA FROM GVA23 WHERE INHABILITA = 0 ORDER BY NOMBRE_VEN";
    
        // Determine the database based on the environment
        $db = isset($_SESSION['entorno']) ? $_SESSION['entorno'] : 'central';
    
        $rows = $this->retornarArray($sql, $db);
    
        return $rows;

    }
    
    public function traerVendedoresPorSucursal ($filtroHabilitados) {
        
        $sql = "SELECT COD_VENDED, NOMBRE_VEN, INHABILITA FROM GVA23 WHERE INHABILITA like '$filtroHabilitados' ORDER BY NOMBRE_VEN";
      
        $db = isset($_SESSION['entorno'] ) ? $_SESSION['entorno'] : '';

        $cid = $this->cid->conectar($db);

        $stmt = sqlsrv_query( $cid, $sql );

        $rows = array();

        while( $v = sqlsrv_fetch_array( $stmt) ) {
            $rows[] = $v;
        }

        return $rows;  

       
    }

    public function borrarGrupo ($grupo) {

        $sql = "DELETE FROM FU_GRUPOS_VENDEDORES WHERE NOMBRE = '$grupo'";

        $stmt = sqlsrv_query( $this->cid_central, $sql );

        if( $stmt === false ) {
            die( print_r( sqlsrv_errors(), true));
        }

        $sql2 = "DELETE FROM FU_LOCALES_POR_GRUPOS_VENDEDORES WHERE NOMBRE_GRUPO = '$grupo'";

        $stmt2 = sqlsrv_query( $this->cid_central, $sql2 );

        if( $stmt2 === false ) {
            die( print_r( sqlsrv_errors(), true));
        }

        return true;

    }

    public function traerLocalesPorGrupo ($nombre) {

        $sql = "SELECT * FROM FU_LOCALES_POR_GRUPOS_VENDEDORES where NOMBRE_GRUPO in ('$nombre')";
   

        $rows = $this->retornarArray($sql);

        return $rows;

    }

    public function editarGrupo ($nombreGrupo, $sucursales){

        $sql ="DELETE FROM FU_LOCALES_POR_GRUPOS_VENDEDORES WHERE NOMBRE_GRUPO = '$nombreGrupo'";

        $stmt = sqlsrv_query( $this->cid_central, $sql );

        if( $stmt === false ) {
            die( print_r( sqlsrv_errors(), true));
        }

        $sql = "INSERT INTO FU_LOCALES_POR_GRUPOS_VENDEDORES (NRO_SUCURSAL, NOMBRE_GRUPO,  CREATED_AT, UPDATED_AT ) VALUES $sucursales";

        $stmt = sqlsrv_query( $this->cid_central, $sql );

        if( $stmt === false ) {
            die( print_r( sqlsrv_errors(), true));
        }

        return true;

    }

    
    public function localConexion($num_suc){
    
        require_once $_SERVER['DOCUMENT_ROOT'].'/administracion/Class/Conexion.php';


        $cid = new Conexion();
        $cidLocales = $cid->conectar('locales');

        $sql_buscar_local = "SELECT TOP 1 * FROM SUCURSALES_LAKERS WHERE NRO_SUCURSAL = $num_suc";
  
      
        if($cidLocales == false){
            return false;
        }

        $stmt = sqlsrv_query( $cidLocales, $sql_buscar_local );

        
        if( $stmt === false ) {
            return false;
        }

        $datos = array();
    
        while($v=sqlsrv_fetch_array($stmt)){
    
            $_SESSION['conexion_dns'] = $v['CONEXION_DNS'];
            $_SESSION['base_nombre'] = $v['BASE_NOMBRE'];
    
        }
        

        $cidConexionFinal =  $cid->conectar('');

        if(!$cidConexionFinal){
            return false;
        }

        return true;
    }

    public function guardarGestionVendedores($stringHabilita, $stringDeshabilita){

        $cid = new Conexion();
        $cid_local = $cid->conectar('');

        $sql = "UPDATE GVA23 
        SET INHABILITA = CASE 
                            WHEN COD_VENDED IN $stringHabilita THEN 0
                            WHEN COD_VENDED IN $stringDeshabilita THEN 1
                            ELSE INHABILITA 
                         END";
                  
         
        $stmt = sqlsrv_query( $cid_local, $sql );

        return true;

    }

    public function habilitarVendedorPorSucursal($cod, $nombre){

        require_once $_SERVER['DOCUMENT_ROOT'].'/administracion/Class/Conexion.php';

        $cid = new Conexion();
        
        $db = isset($_SESSION['entorno'] ) ? $_SESSION['entorno'] : '';
        
        if($db == 'central'){
            $db = '';
        }
   
        $cidLocal = $cid->conectar($db);
        
        if($cidLocal == false){
            return false;
        }

        if (strlen($nombre) > 30) {
            $nombreLimite = $nombre;
            $posGuion = strpos($nombre, '-');
            
            if ($posGuion !== false) {
                $nombreParte = substr($nombre, 0, $posGuion);
                $numeroParte = substr($nombre, $posGuion);
    
                $maxNombreLength = 30 - strlen($numeroParte);
    
                if (strlen($nombreParte) > $maxNombreLength) {
                    $nombreParte = substr($nombreParte, 0, $maxNombreLength);
                }

                $nombreLimite = $nombreParte . $numeroParte;
            } else {
                $nombreLimite = substr($nombre, 0, 30);
            }
        } else {
            $nombreLimite = $nombre;
        }


        $sqlInsertaVended = 
        "
        IF NOT EXISTS (SELECT * FROM GVA23 WHERE COD_VENDED = '".$cod."')
        BEGIN
            INSERT INTO GVA23 (COD_VENDED, NOMBRE_VEN, PORC_COMIS, INHABILITA, TIPO_DOC, COD_GVA23)
            VALUES('".$cod."', '".$nombreLimite."', 1, 0, 99, '".$cod."')
        END
        ELSE 
        BEGIN
            UPDATE GVA23 SET INHABILITA = 0 WHERE COD_VENDED = '".$cod."'
        END
        ";

        $stmt = sqlsrv_query( $cidLocal, $sqlInsertaVended );

        if( $stmt === false ) {
            return false;
        }

        return true;


    }

    public function inhabilitarVendedorPorSucursal($cod, $nombre){

        require_once $_SERVER['DOCUMENT_ROOT'].'/administracion/Class/Conexion.php';

        $cid = new Conexion();

        $db = isset($_SESSION['entorno'] ) ? $_SESSION['entorno'] : '';
        
        if($db == 'central'){
            $db = '';
        }

        $cidLocal = $cid->conectar($db);


        $sqlInsertaVended = 
        "
        IF EXISTS (SELECT * FROM GVA23 WHERE COD_VENDED = '".$cod."')
        BEGIN
            UPDATE GVA23 SET INHABILITA = 1 WHERE COD_VENDED = '".$cod."'
        END

        ";

        
        $stmt = sqlsrv_query( $cidLocal, $sqlInsertaVended );

        if( $stmt === false ) {
            die( print_r( sqlsrv_errors(), true));
        }

        return true;


    }

 
}    
