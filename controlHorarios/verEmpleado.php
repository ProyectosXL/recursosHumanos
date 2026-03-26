<?php 
    require_once "../Class/Horario.php";
    $horario = new Horario();

    $empleado = $horario->traerVendedores($_GET['nroLegajo']);
    $empleado = $empleado[0];  
    $sucursales =  $horario->traerSucursales();
    
    $sucursal = "";
    $nroSucursal = "";
    foreach ($sucursales as $suc) {
            if($suc['NRO_SUCURSAL'] == $empleado['NUM_SUCURSAL']){
                $sucursal = $suc['COD_CLIENT']."-".$suc['DESC_SUCURSAL'];
                $nroSucursal = $suc['NRO_SUCURSAL'];
            }
    }

?>
<!DOCTYPE html>
    <html lang="en">
    <head>

        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Editar Empleado</title>
       
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
            .inputOverflow {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }



  </style>

    </head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>
    <body>

            <div class="table-responsive" id="tableIndex">
                <table class="table text-center" style="width: 70%;border: solid 1px;margin-left:20%;height:700px" cellspacing="0" data-page-length="100">
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
                                        <div class="mt-1" style="width:100%;height:125%;border:1px solid grey; border-radius: 8px;">
                                        <div style="margin-top: 25px;">
                                            <img src="../assets/images/pruebafoto.png" alt="" style="height: 115px; width: 100%;">
                                        </div> 
                                            <div style="color:#5095e3;height:10%;border-bottom:1px solid grey"><strong><?= $empleado['APELLIDO'] ?> <?= $empleado['NOMBRE'] ?></strong></div>
                                            <div class='ml-2' style="text-align:left;color:grey"><strong>Informacion</strong></div>
                                            <div class='ml-2 mt-2' style="text-align:left;color:grey;font-size:13px">
                                                <div>
                                                    Legajo
                                                </div>
                                                <div><strong><?= $empleado['NRO_LEGAJO'] ?></strong></div>
                                            </div>
                                            <div class='ml-2 mt-2' style="text-align:left;color:grey;font-size:13px">
                                                <div>
                                                    Cargo
                                                </div>
                                                <div><strong><?= $empleado['TAREA_HABITUAL'] ?></strong></div>
                                            </div>
                                            <div class='ml-2 mt-2' style="text-align:left;color:grey;font-size:13px">
                                                <div>
                                                    Codigo Vendedor
                                                </div>
                                                <div><strong><?= $empleado['COD_VENDEDOR'] ?></strong></div>
                                            </div>
                                            <div class='ml-2 mt-2' style="text-align:left;color:grey;font-size:13px">
                                                <div>
                                                    Sucursal
                                                </div>
                                                <div><strong><?= $sucursal ?></strong></div>
                                            </div>
                                            

                                        </div>
                                        
                                    </div>
                                    <div class="col"  style="height:500px">
                                        <div class="mt-1" style="width:100%;height:125%;border:1px solid grey; border-radius: 8px;">
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
                                                            <input class="form-check-input" type="checkbox" role="switch"  id="flexSwitchCheckDefault" style="margin-left: 5px;" <?= ($empleado['HABILITADO'] == 'S') ? 'checked' : ''  ?> disabled>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row ml-2 mt-3" style="width:98%;">
                                                <div class="col-2 mr-1 inputOverflow" style="background-color:#dbdbdb;height:45px;font-size:12px;" id="nroLegajo"><span style="color:#969396">Nro. legajo</span> <br><?= $empleado['NRO_LEGAJO'] ?></div>
                                                <div class="col-3 mr-1 inputOverflow" style="background-color:#dbdbdb;height:45px;font-size:12px;text-align:left"><span style="color:#969396">Apellidos </span> <br> <?= $empleado['APELLIDO'] ?></div>
                                                <div class="col-3 mr-1 inputOverflow" style="background-color:#dbdbdb;height:45px;font-size:12px;text-align:left"><span style="color:#969396">Nombres </span> <br> <?= $empleado['NOMBRE'] ?></div>
                                                <div class="col-2 mr-1 inputOverflow" style="background-color:#dbdbdb;height:45px;font-size:12px;text-align:left"><span style="color:#969396">Nro. Documento </span> <br> <?= $empleado['NRO_DOCUMENTO'] ?></div>
                                                <div class="inputOverflow" style="background-color:#dbdbdb; border:1px solid #dbdbdb;height:45px;width:14%;font-size:12px;text-align:left" id="codVendedor"><span style="color:#969396">Cod. vend</span> <br> <span style="margin-left:35%;margin-top:10%"><?= $empleado['COD_VENDEDOR'] ?></span></div>
                                            </div>
                                            <div class="row ml-2 mt-3" style="width:98%;">
                                                <div class="col-5 mr-1 inputOverflow" style="background-color:#dbdbdb; border:1px solid #dbdbdb;height:45px;font-size:12px;text-align:left; overflow: hidden; white-space: nowrap; text-overflow: ellipsis;" attr-realValue="<?= $empleado['DOMICILIO'] ?>" attr-title="Direccion"  id="direccion">
                                                    <span style="color:#969396">Dirección</span> <br><?= $empleado['DOMICILIO'] ?>
                                                </div>

                                                <div class="col-1 mr-1 inputOverflow" style="background-color:#dbdbdb; border:1px solid #dbdbdb;height:45px;font-size:12px;text-align:left" attr-realValue="1" attr-title="Piso" id="piso"><span style="color:#969396">Piso </span> <br> <span style="margin-left:35%;margin-top:10%"> <?= $empleado['PISO'] ?></span></div>
                                                <div class="col-1 mr-1 inputOverflow" style="background-color:#dbdbdb; border:1px solid #dbdbdb;height:45px;font-size:12px;text-align:left" attr-realValue="2" attr-title="Depto." id="depto"><span style="color:#969396">Depto. </span> <br><span style="margin-left:35%;margin-top:10%">  <?= $empleado['DEPTO'] ?></span></div>
                                                <div class="col-3 mr-1 inputOverflow" style="background-color:#dbdbdb; border:1px solid #dbdbdb;height:45px;font-size:12px;text-align:left" attr-realValue="<?= $empleado['LOCALIDAD'] ?>" attr-title="Localidad" id="localidad"><span style="color:#969396">Localidad </span> <br> <?= $empleado['LOCALIDAD'] ?></div>
                                                <div class="inputOverflow" style="background-color:#dbdbdb; border:1px solid #dbdbdb;height:45px;width:14%;font-size:12px;text-align:left" attr-realValue="<?= $empleado['CODIGO_POSTAL'] ?>" attr-title="Cod. postal" id="codPostal"><span style="color:#969396">Cod. postal</span> <br> <span style="margin-left:35%;margin-top:10%"><?= $empleado['CODIGO_POSTAL'] ?></span></div>
                                            </div>
                                            <div class="row ml-2 mt-3" style="width:98%;">
                                                <div class="col-5 mr-1 inputOverflow" style="background-color:#dbdbdb; border:1px solid #dbdbdb;height:45px;font-size:12px;text-align:left" attr-realValue="<?= $nroSucursal?>" attr-title="Sucursal asignada"  id="sucursalAsignada"><span style="color:#969396">Sucursal asignada</span> <br><?= $sucursal ?></div>
                                                <div class="col-3 mr-1 inputOverflow" style="background-color:#dbdbdb; border:1px solid #dbdbdb;height:45px;font-size:12px;text-align:left" attr-realValue="<?= $empleado['TAREA_HABITUAL'] ?>" id="tareaFuente" attr-title="Tarea fuente"><span style="color:#969396">Tarea fuente </span> <br> <span style="margin-left:30%;margin-top:10%"><?= $empleado['TAREA_HABITUAL'] ?></span></div>
                                                <div class="col-3 mr-1 inputOverflow" style="background-color:#dbdbdb; background-color:#dbdbdb;height:45px;font-size:12px;text-align:left"><span style="color:#969396">Pais </span> <br> <span style="margin-left:30%;margin-top:10%"  id="pais"><?= $empleado['PAIS'] ?></span></div>
                                            </div>
                                            <div class="row ml-2 mt-3" style="width:98%;">
                                                <div class="col-3 mr-1 inputOverflow" style="background-color:#dbdbdb;height:45px;font-size:12px;text-align:left" id="tipoDeContrato"><span style="color:#969396">Tipo de contrato</span> <br><?= $empleado['TIPO_CONTRATO'] ?></div>
                                                <div class="col-3 mr-1 inputOverflow" style="background-color:#dbdbdb;height:45px;font-size:12px;text-align:left" id="fechaIngreso"><span style="color:#969396">Fecha de ingreso </span> <br> <?= $empleado['FECHA_INGRESO']->format('Y-m-d') ?><span style="margin-left:30%;margin-top:10%"></span></div>
                                            </div>

                                            <div style="color:grey;font-family: Arial;text-align:left;margin-left:10px;margin-top:10px;">
                                                <div class="row mt-4">
                                                    <div class="col-9">Contacto</div>
                                                </div>
                                            </div>
                                            <div class="row ml-2 mt-3" style="width:98%;">
                                                <div class="col-4 mr-1 inputOverflow" style="background-color:#dbdbdb; border:1px solid #dbdbdb;height:45px;font-size:12px;text-align:left" id="email" attr-realValue="<?= $empleado['EMAIL']?>" attr-title="Correo electronico"><span style="color:#969396">Correo electronico</span> <br><?= $empleado['EMAIL'] ?></div>
                                                <div class="col-3 mr-1 inputOverflow" style="background-color:#dbdbdb; border:1px solid #dbdbdb;height:45px;font-size:12px;text-align:left;white-space: nowrap;"  id="telefonoM" attr-realValue="<?= $empleado['TELEFONO'] ?>" attr-title="Telefono movil"><span style="color:#969396">Telefono movil1 </span> <br> <span style="margin-left:30%;margin-top:10%"><?= $empleado['TELEFONO'] ?></span></div>
                                                <div class="col-3 mr-1 inputOverflow" style="background-color:#dbdbdb; border:1px solid #dbdbdb;height:45px;font-size:12px;text-align:left;white-space: nowrap;"  id="telefonoE" attr-realValue="<?= $empleado['TELEFONO2'] ?>" attr-title="Telefono de emergencia"><span style="color:#969396">Telefono de emergencia </span> <br> <span style="margin-left:30%;margin-top:10%"><?= $empleado['TELEFONO2'] ?></span></div>
                                            </div>
                                            
                                            <div style="text-align:right; padding: 10px;margin-top:5%">
                                              <a href="verControlHorario.php"><button class="btn" style="background-color:#dbdbdb" >cerrar</button></a>                                              
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
