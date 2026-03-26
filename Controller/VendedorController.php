<?php 
    $accion = $_GET['accion'];
        
    include_once '../Class/Vendedor.php';
    $vendedor = new Vendedor();

    switch ($accion) {
        case 'traerSucursales':
            traerSucursales($vendedor);
            break;
        
        case 'crearGrupo':
            crearGrupo($vendedor);
            break;
        
        case 'traerGrupos':
            traerGrupos($vendedor);
            break;
        
        case 'borrarGrupo':
            borrarGrupo($vendedor);
            break;
        
        case 'traerLocalesPorGrupo':
            traerLocalesPorGrupo($vendedor);
            break;
        
        case 'editarGrupo':
            editarGrupo($vendedor);
            break;
        
        case 'altaVendedores':
            altaVendedores($vendedor);
            break;
        
        case 'bajaVendedores':
            bajaVendedores($vendedor);
            break;
        
        case 'cambiarEntorno':
            cambiarEntorno();
            break;
        
        
        case 'traerVendedoresPorSucursal':
            traerVendedoresPorSucursal($vendedor);
            break;
        
        case 'guardarGestionVendedores':
            guardarGestionVendedores($vendedor);
            break;
        

        default:
            # code...
            break;
    }
    


    function traerSucursales($vendedor){
 
        $result = $vendedor->traerSucursales();
        $result = json_encode($result);

        echo $result;
    }


    function crearGrupo ($vendedor) {
        $nombreGrupo = $_POST['nombreGrupo'];
        $localesPorGrupo = $_POST['sucursales'];
      
        $result = $vendedor->crearGrupoEnc($nombreGrupo);

        if ($result == true) {
            $result = $vendedor->crearGrupoDet($localesPorGrupo);
        }

        
        echo $result;
    }

    function traerGrupos($vendedor){
        
        $result = $vendedor->traerGrupos();
        $result = json_encode($result);

        echo $result;
    } 

    function borrarGrupo($vendedor){

        $nombreGrupo = $_POST['nombreGrupo'];

        $result = $vendedor->borrarGrupo($nombreGrupo);

        echo $result;

    }

    function traerLocalesPorGrupo($vendedor){

        $nombreGrupo = $_POST['grupos'];
    

        $result = $vendedor->traerLocalesPorGrupo($nombreGrupo);
        $result = json_encode($result);

        echo $result;

    }

    function editarGrupo ($vendedor) {

        $nombreGrupo = $_POST['nombreGrupo'];
        $localesPorGrupo = $_POST['sucursales'];

  
        $result = $vendedor->editarGrupo($nombreGrupo, $localesPorGrupo);

        
        echo $result;
    }

    function altaVendedores ($vendedor) {

        $sucursalesPorHabilitar = $_POST['sucursalesPorHabilitar'];
        $vendedoresPorHabilitar = $_POST['vendedoresPorHabilitar'];
        $respuestaPorLocal = [];

        foreach ($sucursalesPorHabilitar as $key => $sucursal) {

            $conexion = $vendedor->localConexion($sucursal);
            if($conexion == true) {

                foreach ($vendedoresPorHabilitar as $key => $vendedorHabilitar) {
    
                   $codVendedor = $vendedorHabilitar[0];
                   $nombre  = $vendedorHabilitar[1];
    
                   $result = $vendedor->habilitarVendedorPorSucursal($codVendedor, $nombre);
    
                }

                $respuestaPorLocal[] = [$sucursal, 'ok'];

            }else{

                $respuestaPorLocal[] = [$sucursal, 'error'];

            }



        }

        echo json_encode($respuestaPorLocal);
    }

    function bajaVendedores ($vendedor) {

        $sucursalesPorHabilitar = $_POST['sucursalesPorHabilitar'];
        $vendedoresPorHabilitar = $_POST['vendedoresPorHabilitar'];
        $respuestaPorLocal = [];

        foreach ($sucursalesPorHabilitar as $key => $sucursal) {

            $conexion = $vendedor->localConexion($sucursal);

            if($conexion == true) {

                foreach ($vendedoresPorHabilitar as $key => $vendedorHabilitar) {
    
                   $codVendedor = $vendedorHabilitar[0];
                   $nombre  = $vendedorHabilitar[1];
    
                   $result = $vendedor->inhabilitarVendedorPorSucursal($codVendedor, $nombre);
    
                }

                $respuestaPorLocal[] = [$sucursal, 'ok'];

            }else{

                $respuestaPorLocal[] = [$sucursal, 'error'];

            }



        }

        echo json_encode($respuestaPorLocal);
    }

    function cambiarEntorno () {
        session_start();

        $entorno = $_POST['entorno'];

        $_SESSION['entorno'] = $entorno;

        echo 'ok';
    }

    function traerVendedoresPorSucursal ($vendedor){

        $sucursal = $_POST['sucursal'];
        $filtroHabilitados = $_POST['filtroHabilitados'];

        $conexion = $vendedor->localConexion($sucursal);

        
        if($conexion == true) {
                
            $result = $vendedor->traerVendedoresPorSucursal($filtroHabilitados);

            echo json_encode($result);
            
        }else{

            echo false;

        }


    }


    function guardarGestionVendedores($vendedor) {

        $stringParaSqlHabilita = $_POST['stringParaSqlHabilita'];
        $stringParaSqlDeshabilita = $_POST['stringParaSqlDeshabilita'];
        $sucursal = $_POST['sucursal'];


        $conexion = $vendedor->localConexion($sucursal);

        $result = $vendedor->guardarGestionVendedores($stringParaSqlHabilita, $stringParaSqlDeshabilita);

        return $result;
    }


?>