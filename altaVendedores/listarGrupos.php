<?php 
   
   
?>

<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Grupo vendedores</title>
        <?php 
            require_once $_SERVER['DOCUMENT_ROOT'].'/administracion/assets/css/css.php';
        ?>
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
            
        </style>
    </head>

    <body>
        
      
        <input type="file" name="archivos[]" id="archivos" multiple accept=".pdf, .jpg, .png" style="display: none;" />
        <div id="carruselImagenes" class="modal fade" tabindex="-1" aria-hidden="true" style="margin-left:10%;max-width:80%"></div>
        <div id="nroSucursal" hidden></div>

        <div class="alert alert-secondary">
            <div class="page-wrapper bg-secondary p-b-100 pt-2 font-robo">
                <div class="wrapper wrapper--w880"><div style="color:white; text-align:center"><h6>Grupos alta vendedores</h6></div>
                    <div class="card card-1">
                        <div id="periodo" hidden></div>
                        <div class="row" style="margin-left:50px; margin-top:30px">
                            <h3><strong><img src="../assets/images/price-tag.png" alt=""  style="margin-right: 20px;width: 30px;height: 30px;"> Grupos - Alta vendedores </strong></h3>
                        </div>
                        <div>
                            <button class="btn btn-success" style="margin-left:50px; margin-top: 2rem;" onclick="window.location.href='crearGrupo.php'">Crear Grupo <i class="bi bi-check2-square"></i></button>
                        </div>

                        <table class="table table-striped table-bordered table-sm table-hover" id="tablaGrupos" style="width: 60%; height:100px; margin-left:50px" cellspacing="0" data-page-length="100">
                            <thead class="thead-dark" style="">
                                <tr>
                                    <th style="text-align:center;" >FECHA CREACION</th>
                                    <th style="text-align:center;" >NOMBRE GRUPO</th>
                                    <th style="text-align:center;" ></th>
                                    <th style="text-align:center;"></th>
                                </tr>
                            </thead>
                            <tbody id="bodyGrupos">
                                
                            </tbody>
            
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php 
            require_once $_SERVER['DOCUMENT_ROOT'].'/administracion/assets/js/js.php';
        ?>
    </body>

</html>

<!-- <script src="js/gastosTesoreria.js"></script> -->

