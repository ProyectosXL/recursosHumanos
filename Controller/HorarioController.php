<?php 
    $accion = $_GET['accion'];
        
    include_once '../Class/Horario.php';
    $horario = new Horario();

 

    switch ($accion) {
        case 'traerVendedores':
            traerVendedores($horario);
            break;

        case 'sucursales':
            sucursales($horario);
            break;

        case 'editarEmpleado':
            editarEmpleado($horario);
            break;

        case 'cambiarEstado':
            cambiarEstado($horario);
            break;


        case 'crearEmpleado':
            crearEmpleado($horario);
            break;

        case 'crearHorario':
            crearHorario($horario);
            break;

        case 'eliminarHorario':
            eliminarHorario($horario);
            break;

        case 'editarHorario':
            editarHorario($horario);
            break;

        }


        

        
        function traerVendedores ($horario) {

            $data = $horario->traerVendedores();

            echo json_encode($data);


        }
        function sucursales ($horario) {

            $data = $horario->traerSucursales();

            echo json_encode($data);

        }


        function editarEmpleado($horario) {

            $nroLegajo = $_POST['nroLegajo'];
            $direccion = $_POST['direccion'];
            $piso = $_POST['piso'];
            $depto = $_POST['depto'];
            $localidad = $_POST['localidad'];
            $codPostal = $_POST['codPostal'];
            $sucursalAsignada = $_POST['sucursalAsignada'];
            $tareaFuente = $_POST['tareaFuente'];
            $email = $_POST['email'];
            $telefonoM = $_POST['telefonoM'];
            $telefonoE = $_POST['telefonoE'];
    
    
            $result = $horario->editarEmpleado($nroLegajo, $direccion, $piso, $depto, $localidad, $codPostal, $sucursalAsignada, $tareaFuente, $email, $telefonoM, $telefonoE);
    
            return $result;

        }


        function cambiarEstado($horario) {

            $nroLegajo = $_POST['nroLegajo'];
            $estado = $_POST['estado'];
   
            $result = $horario->cambiarEstado($nroLegajo, $estado);
        
            echo $result;
        }

        function crearEmpleado ($horario) {

            $stringParaSql = $_POST['stringParaSql'];
            $nroLegajo = $_POST['legajo'];

            $result = $horario->crearEmpleado($stringParaSql, $nroLegajo);


            echo $result;
        }

        function crearHorario ($horario) {

            $nombre = $_POST['nombre'];
            $horaaDeEntrada = $_POST['horaDeEntrada'];
            $horaDeSalida = $_POST['horaDeSalida'];
            $totalHoras = $_POST['totalHoras'];
            $horarioDosDias = $_POST['horarioDosDias'];
            $contabilizado = $_POST['contabilizado'];
            $color = $_POST['color'];

            $result = $horario->crearHorario($nombre, $horaaDeEntrada, $horaDeSalida, $totalHoras, $horarioDosDias, $contabilizado, $color);

            echo $result;
        }

        function eliminarHorario ($horario) {
                
            $id = $_POST['id'];

            $result = $horario->eliminarHorario($id);

            echo $result;

        }

        function editarHorario ($horario) {

            $id = $_POST['id'];
            $nombre = $_POST['nombre'];
            $horaaDeEntrada = $_POST['horaDeEntrada'];
            $horaDeSalida = $_POST['horaDeSalida'];
            $totalHoras = $_POST['totalHoras'];
            $horarioDosDias = $_POST['horarioDosDias'];
            $contabilizado = $_POST['contabilizado'];
            $color = $_POST['color'];

            $result = $horario->editarHorario($id, $nombre, $horaaDeEntrada, $horaDeSalida, $totalHoras, $horarioDosDias, $contabilizado, $color);

            echo $result;
        }