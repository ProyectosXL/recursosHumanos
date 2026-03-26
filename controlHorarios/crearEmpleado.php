<?php 
    require_once "../Class/Horario.php";
    $horario = new Horario();

    // $empleado = $horario->traerVendedores($_GET['nroLegajo']);
    // $empleado = $empleado[0];  
    $nroLegajo = $horario->traerNuevoNroLegajo();
    $nroLegajo = $nroLegajo[0]['NRO_LEGAJO'];

    $sucursales =  $horario->traerSucursales();

?>
<!DOCTYPE html>
    <html lang="en">
    <head>

        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Crear Empleado</title>
       
        <?php 
            require_once "../assets/css/css.php";
        ?>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <style>
        /* Estilo para el checkbox personalizado */
        .custom-checkbox {
            /* Ocultar el checkbox nativo */
            position: absolute;
            opacity: 0;
        }

        /* Estilo para el cuadro del checkbox */
        .custom-checkbox-label {
            /* Tamaño del cuadro */
            width: 20px;
            height: 20px;
            /* Establecer el fondo celeste */
            background-color: #59b9e2;
            /* Establecer el borde blanco */
            border: 1px solid white;
            /* Alinear el cuadro al centro */
            display: inline-block;
            vertical-align: middle;
            /* Posición relativa para los elementos internos */
            position: relative;
        }

        /* Estilo para la tilde dentro del checkbox */
        .custom-checkbox:checked + .custom-checkbox-label::after {
            /* Contenido de la tilde */
            content: '\2714'; /* Caracter Unicode de la tilde */
            /* Estilo de la tilde */
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 14px; /* Tamaño de la tilde */
            color: white; /* Color de la tilde */
        }

        /* Estilo para la vista de cuadrícula */
        #cuadrado {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); /* Establece el ancho mínimo de cada elemento y el número de columnas según el ancho del contenedor */
            gap: 20px; /* Espacio entre elementos */
            height: 300px; /* Altura del contenedor */
            background-color: white;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
                }

            .bordeDiv {
                    border:1px solid #dbdbdb;
            }

        .required {
            color: red;
            font-weight: bold;
        }


  </style>

    </head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>
    <body>

            <div class="table-responsive" id="tableIndex">
                <table class="table text-center" style="width: 70%;border: solid 1px;margin-left:20%;height:750px" cellspacing="0" data-page-length="100">
                    <thead class="" style="font-size: small;">
                        <th scope="col" style="width: 6%;text-align:left;font-size:25px">
                            <div class="row">
                                <div class="col" style="color:grey; border-bottom:1px solid">

                                    <strong>Informacion del empleado</strong>
                                </div>
                                
                            </div>
                   
                        
                        </th>
                        
                    </thead>
                    <tbody >
                        <tr>
                            <td>
                                <div class="row">
                                    <div class="col-4" style="height:500px">
                                        
                                        <div class="mt-1" style="width:100%;height:130%;border:1px solid grey; border-radius: 8px;">
                                            <div style="height:30px;text-align:right;margin:10px" ><i class="bi bi-three-dots-vertical" style="color:black;font-size:25px"></i></div>
                                            
                                            <img src="../assets/images/pruebafoto.png" alt="" style="height: 115px; width: 100%;"> 
                                            <div style="color:#5095e3;height:10%;border-bottom:1px solid grey"><strong></strong></div>
                                            <div class='ml-2' style="text-align:left;color:grey"><strong>Informacion</strong></div>
                                            <div class='ml-2 mt-2' style="text-align:left;color:grey;font-size:13px">
                                                <!-- <div>
                                                    Legajo
                                                </div>
                                                <div><strong id="nroLegajo"><?= $nroLegajo ?></strong></div> -->
                                            </div>
                                            

                                        </div>
                                        
                                    </div>
                                    <div class="col"  style="height:500px">
                                    <div class="mt-1" style="width:100%;height:130%;border:1px solid grey; border-radius: 8px;">
                                        <div id="pestañas" style="width:100%;height:60px;border-bottom:1px solid grey;text-align:left">
                                                <div style="margin-left:20px;color:#95d6e8;width:100px;text-align:center;border-bottom:3px solid #95d6e8 ">
                                                    <div style="height:32px">
                                                        <i class="bi bi-person" style="font-size:30px;"></i>
                                                    </div>
                                                    <div style="font-size:10px">
                                                        informacion General
                                                    </div>
                                                </div>
                                            </div>
                                            <div style="color:grey;font-family: Arial;text-align:left;margin-left:10px;margin-top:10px;">
                                                <div class="row">
                                                    <div class="col-9">Datos Personales</div>
                                                    <div class="col-3">
                                                        <div class="form-check form-switch">
                                                            <label class="form-check-label" for="flexSwitchCheckChecked">Estado</label>
                                                            <input class="form-check-input" type="checkbox" role="switch" id="flexSwitchCheckDefault" style="margin-left: 5px;" >
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row ml-2 mt-3" style="width:98%;">
                                                <!-- <div class="col-2 mr-1" style="background-color:#dbdbdb;height:45px;font-size:12px;" id="nroLegajo"><span style="color:#969396">Nro. legajo</span> <br><span id="nroLegajoSpan"></span></div> -->
                                                <div class="col-3 mr-1 bordeDiv" style="height:45px;font-size:12px;text-align:left" attr-title="Apellidos" onclick="updateValue(this)" id="apellido"> <span style="color:#969396">Apellidos <span class="required">*</span></span> <br></div>
                                                <div class="col-3 mr-1 bordeDiv" style="height:45px;font-size:12px;text-align:left" attr-title="Nombres" onclick="updateValue(this)" id="nombres"><span style="color:#969396">Nombres <span class="required">*</span></span> <br> </div>
                                                <div class="col-2 mr-1 bordeDiv" style="height:45px;font-size:12px;text-align:left" attr-title="Nro. Documento" onclick="updateValue(this)" id="nroDocumento"><span style="color:#969396">Nro. Documento <span class="required">*</span></span> <br> </div>
                                                <div class="bordeDiv" style="height:45px;width:14%;font-size:12px;text-align:left" attr-title="Cod. vend" onclick="updateValue(this)" id="codVendedor"><span style="color:#969396">Cod. vend</span> <br> <span style="margin-left:35%;margin-top:10%"></span></div>
                                            </div>
                                            <div class="row ml-2 mt-3" style="width:98%;">
                                                <div class="col-5 mr-1 bordeDiv" style="height:45px;font-size:12px;text-align:left"  attr-title="Direccion" onclick="updateValue(this)" id="direccion"><span style="color:#969396">Direccion</span> <br></div>
                                                <div class="col-1 mr-1 bordeDiv" style="height:45px;font-size:12px;text-align:left"  attr-title="Piso" onclick="updateValue(this)" id="piso"><span style="color:#969396">Piso </span> <br> <span style="margin-left:35%;margin-top:10%"></span></div>
                                                <div class="col-1 mr-1 bordeDiv" style="height:45px;font-size:12px;text-align:left"  attr-title="Depto." onclick="updateValue(this)" id="depto"><span style="color:#969396">Depto. </span> <br><span style="margin-left:35%;margin-top:10%"> </span></div>
                                            </div>
                                             <div class="row ml-2 mt-3" style="width:98%;">
                                                <div class="col-3 mr-1 bordeDiv" style="height:45px;font-size:12px;text-align:left" attr-title="Pais"  onclick="updateValueSelectPais(this)" id="pais"><span style="color:#969396">Pais <span class="required">*</span> </span> <br> <span style="margin-left:30%;margin-top:10%"></span></div>
                                                <div class="col-3 mr-1" style="background-color:#dbdbdb;height:45px;font-size:12px;text-align:left"  attr-title="Localidad"  id="divLocalidad" id="localidad"><span style="color:#969396">Localidad <span class="required">*</span> </span> <br> </div>
                                                <div class="bordeDiv" style="height:45px;width:14%;font-size:12px;text-align:left"  attr-title="Cod. postal" onclick="updateValue(this)" id="codPostal"><span style="color:#969396">Cod. postal</span> <br> <span style="margin-left:35%;margin-top:10%"></span></div>
                                            </div>
                                            <div class="row ml-2 mt-3" style="width:98%;">
                                                <div class="col-5 mr-1 bordeDiv" style="height:45px;font-size:12px;text-align:left"  attr-title="Sucursal asignada" onclick="updateValueSelectSucursal(this)" id="sucursalAsignada"><span style="color:#969396">Sucursal asignada <span class="required">*</span></span> <br></div>
                                                <div class="col-3 mr-1 bordeDiv" style="height:45px;font-size:12px;text-align:left" attr-title="Tarea fuente"  onclick="updateValueSelectTareaFrecuente(this)"  id="tareaFuente"><span style="color:#969396">Tarea frecuente<span class="required">*</span></span> <br> <span style="margin-left:30%;margin-top:10%"></span></div>
                                            </div>
                                            <div class="row ml-2 mt-3" style="width:98%;">
                                                <div class="col-3 mr-1 bordeDiv" style="background-color:#dbdbdb;height:45px;font-size:12px;text-align:left" attr-title="Tipo de contrato"   id="tipoContrato"><span style="color:#969396" >Tipo de contrato <span class="required">*</span></span> <br></div>
                                                <div class="col-3 mr-1 bordeDiv" style="height:45px;font-size:12px;text-align:left" attr-title="Fecha de ingreso"  onclick="updateValueDate(this)" id="fechaIngreso"><span style="color:#969396">Fecha de ingreso  <span class="required">*</span></span> <br> <span style="margin-left:30%;margin-top:10%"></span></div>
                                            </div>

                                            <div style="color:grey;font-family: Arial;text-align:left;margin-left:10px;margin-top:10px;">
                                                <div class="row mt-4">
                                                    <div class="col-9">Contacto</div>
                                                </div>
                                            </div>
                                            <div class="row ml-2 mt-3" style="width:98%;">
                                                <div class="col-4 mr-1 bordeDiv" style="height:45px;font-size:12px;text-align:left"  attr-title="Correo electronico" onclick="updateValue(this)" id="email"><span style="color:#969396">Correo electronico</span> <br></div>
                                                <div class="col-3 mr-1 bordeDiv" style="height:45px;font-size:12px;text-align:left" attr-title="Telefono movil" onclick="updateValue(this)"  id="telefono"><span style="color:#969396">Telefono movil </span> <br> <span style="margin-left:30%;margin-top:10%"></span></div>
                                                <div class="col-3 mr-1 bordeDiv" style="height:45px;font-size:12px;text-align:left" attr-title="Telefono de emergencia" onclick="updateValue(this)" id="telefonoE"><span style="color:#969396">Telefono de emergencia </span> <br> <span style="margin-left:30%;margin-top:10%"></span></div>
                                            </div>
                                            
                                            
                                            <div style="text-align:right; padding: 10px;margin-top:5%">
                                                <a href="controlHorario.php" style="text-decoration: none;"><button class="btn" style="background-color:#dbdbdb" >Cerrar</button></a>
                                                <button class="btn btn-primary mr-2" style="margin-left: 10px;" onclick="guardaCambios()">Guardar Cambios</button>
                                            </div>
                                          
                                            
                                    
                                        </div>
                                    </div>
                                </div>
                            
                               
                           
                            </td>
                        </tr>
                    </tbody>
             
                </table>
            </div>
                

        <?php 
            require_once "../assets/js/js.php"
        ?>

        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.2/xlsx.full.min.js"></script>


        
    </body>

    </html>