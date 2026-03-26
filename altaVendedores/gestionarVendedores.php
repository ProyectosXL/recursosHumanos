<?php 
    session_start();
   require_once '../Class/Vendedor.php';
   $vendedor = new Vendedor();
   $sucursales = $vendedor->traerSucursales();
//    $vendedores = $vendedor->traerVendedores();
 
    
    if(isset($_SESSION['entorno']) && $_SESSION['entorno'] == 'central'){
        $checked = 'checked';
    }else{
        $checked = '';
    }
        


    $checkedValue = isset($_SESSION['entorno']) ? $_SESSION['entorno'] : 'central';
    $dataOnValue = ($checkedValue === 'uy') ? 'UY' : 'ARG';
    $dataOffValue = ($checkedValue === 'uy') ? 'ARG' : 'UY';
    $imageOn = ($checkedValue === 'central') ? '../assets/images/bandera_con_sol__55757_std.jpg' : '../assets/images/UY.png';
    $imageOff = ($checkedValue === 'central') ? '../assets/images/UY.png' : '../assets/images/bandera_con_sol__55757_std.jpg';

?>

<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Listar Grupos</title>
        <?php 
            require_once $_SERVER['DOCUMENT_ROOT'].'/administracion/assets/css/css.php';
        ?>
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            .dataTables_length{
                margin-left:50px
            }
            .dataTables_info{
                margin-left:50px
            }
            #tablaArticulos_paginate{
                margin-right:42px 
            }

            
            .toggle-on {
                background-image: url('<?= $imageOn ?>');
                background-size: contain;
                background-repeat: no-repeat;
                height: 60px;
                width: 60px;
            }

            .toggle-off {
                background-image: url('<?= $imageOff ?>');
                background-size: contain;
                background-repeat: no-repeat;
                height: 60px;
                width: 60px;
            }
            
        </style>
    </head>

    <body>
        
      
        <div id="carruselImagenes" class="modal fade" tabindex="-1" aria-hidden="true" style="margin-left:10%;max-width:80%"></div>

        <div class="alert alert-secondary">
            <div class="page-wrapper bg-secondary p-b-100 pt-2 font-robo">
                <div class="wrapper wrapper--w880"><div style="color:white; text-align:center"><h6>Gestionar vendedores</h6></div>
                    <div class="card card-1">
                        <div id="periodo" hidden></div>
                        <div class="row" style="margin-left:50px; margin-top:30px">
                        <div class="col-10">
                        <a href="http://192.168.0.13:8000/" style="display:inline-block;">
                            <img src="../image/home-button.png" style="width:50px;height:45px;margin-right:-1rem;transition: transform 0.3s;" title="Menú" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                        </a>
                                <h3 ><strong><img src="../assets/images/price-tag.png" alt=""  style="margin-right: 20px;width: 30px;height: 30px;"> Gestion vendedores por sucursal -  <span id="spanSucursal"></span></strong></h3>

                        </div>
                        <div class="col-2" style="">

                            <input type="checkbox" checked data-toggle="toggle" data-on="<?= $dataOnValue ?>" data-off="<?= $dataOffValue ?>" class="custom-toggle" style="color:black; font-size: 0;" onchange="cambiarEntorno(this)" id="checkEntorno" >
                        </div>


                        </div>
                        <div class="inline-container" style="margin-left:50px;width: 80%; display: inline-block;margin-bottom:20px">
                         
                                
                                Sucursal:
                                <select name="selectSucursal" id="selectSucursal" onchange="traerVendedores()" style="width: 20%;height: 32px;margin-right:5%"  class="form-select">
                                    
                                    <?php
                                        if(!isset($_SESSION['selectSucursal']) ){
                                            echo '<option value="0" disabled selected>Seleccione una sucursal</option>';
                                        }

                                        foreach ($sucursales as $key => $value) {

                                    ?>

                                            <option value="<?= $value['NRO_SUCURSAL'] ?>" attr-name="<?= $value['DESC_SUCURSAL'] ?>" ><?= $value['DESC_SUCURSAL'] ?></option>
                                    <?php
                                        }
                                    ?>
                                </select>
                                
                                Estado:
                                <select name="filtroHabilitados" id="filtroHabilitados" onchange="traerVendedores()" style="width: 20%;height: 32px;margin-right:5%"  class="form-select">
                                        <option value="%">Todos</option>
                                        <option value="0">Habilitados</option>
                                        <option value="1">Inhabilitados</option>
                                </select>
                                <span id="busquedaRapida" hidden> Búsqueda rápida: <div id="colBusquedaRapida" style="display: inline-block;"></div></span>

                          
                        </div>
                        <div class="inline-container" style="margin-left:50px;width: 60%; display: inline-block;margin-bottom:2%">
                                <div id="" style="display: inline-block;margin-left:60%">
                                        <input type="checkbox" onchange="marcarTodos(this)" style="width: 16px;height: 16px;"> Todos
                                </div>
                                <div id="" style="display: inline-block;margin-left:10%">
                                        <button class="btn btn-primary" value="guardar" onclick="guardar()">Guardar <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-floppy" viewBox="0 0 16 16"><path d="M11 2H9v3h2z"/><path d="M1.5 0h11.586a1.5 1.5 0 0 1 1.06.44l1.415 1.414A1.5 1.5 0 0 1 16 2.914V14.5a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 0 14.5v-13A1.5 1.5 0 0 1 1.5 0M1 1.5v13a.5.5 0 0 0 .5.5H2v-4.5A1.5 1.5 0 0 1 3.5 9h9a1.5 1.5 0 0 1 1.5 1.5V15h.5a.5.5 0 0 0 .5-.5V2.914a.5.5 0 0 0-.146-.353l-1.415-1.415A.5.5 0 0 0 13.086 1H13v4.5A1.5 1.5 0 0 1 11.5 7h-7A1.5 1.5 0 0 1 3 5.5V1H1.5a.5.5 0 0 0-.5.5m3 4a.5.5 0 0 0 .5.5h7a.5.5 0 0 0 .5-.5V1H4zM3 15h10v-4.5a.5.5 0 0 0-.5-.5h-9a.5.5 0 0 0-.5.5z"/></svg></button>
                                </div>

                        </div>

                        <div class="alert alert-info" style="width: 60%; height:50px; margin-left:50px;" >Los registros tildados corresponden a los vendedores que se encuentran habilitados de acuerdo a la sucursal filtrada</div>
                 
                        <table class="table table-striped table-bordered table-sm table-hover" id="tablaVendedores" style="width: 60%; height:100px; margin-left:50px;" cellspacing="0" data-page-length="50">
                            <thead class="thead-dark" style="position: sticky; top: 0; z-index: 10;">
                                <tr>
                                    <th style="text-align:center" >COD VENDEDOR</th>
                                    <th style="text-align:center" >NOMBRE VENDEDOR</th>
                                    <th style="text-align:center" ></th>
                                    
                                </tr>
                            </thead>
                            <tbody id="bodyVendedores">
                                   
                            </tbody>
            
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php 
            require_once $_SERVER['DOCUMENT_ROOT'].'/administracion/assets/js/js.php';
            
        ?>
        <link href="https://gitcdn.github.io/bootstrap-toggle/2.2.2/css/bootstrap-toggle.min.css" rel="stylesheet">
        <script src="https://gitcdn.github.io/bootstrap-toggle/2.2.2/js/bootstrap-toggle.min.js"></script>
        
    </body>

</html>

<script>
       
        $(document).ready( function () {
           

            $('[data-toggle="tooltip"]').tooltip()
            document.querySelector(".toggle").style.width="40px"
            document.querySelector(".toggle-on").style.fontSize="0"
            document.querySelector(".toggle-off").style.fontSize="0"

            document.querySelector(".toggle.btn.btn-primary").style.marginRight="22%"
        })
</script>
<!-- <script src="js/gastosTesoreria.js"></script> -->

