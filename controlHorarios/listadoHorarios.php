<?php

require_once '../Class/Horario.php';

$horario = new Horario();
$json_data = file_get_contents('../assets/localidadesArgentina1.json');
$uyJson_data = file_get_contents('../assets/localidadesUruguay1.json');

$dataUy = json_decode($uyJson_data, true);
$dataArg = json_decode($json_data, true);



$sucursales = $horario->traerSucursales();
$horarios = $horario->traerHorarios();

$dataArg = $dataArg[0]['sinonimos'];
$dataUy = $dataUy[0]['sinonimos'];


?>
<!DOCTYPE html>
    <html lang="en">
    <head>

        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Listado Horarios</title>
       
        <?php 
            require_once "../assets/css/css.php";
        ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.11.0/dist/sweetalert2.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
        
        <link rel="stylesheet" href="css/colorPick.css">
        <!-- OPTIONAL DARK THEME -->
        <script src="//code.jquery.com/jquery.min.js"></script>

        <link rel="stylesheet" href="css/colorPick.dark.theme.css">
 

 <style>

           .Comic {
            font-family: "Comic Sans MS", cursive, sans-serif;
           }     

           .inputOverflow {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .colorPickSelector {
                border-radius: 5px;
                width: 15px;
                height: 15px;
                margin-top: 10px;
                cursor: pointer;
                -webkit-transition: all linear .2s;
                -moz-transition: all linear .2s;
                -ms-transition: all linear .2s;
                -o-transition: all linear .2s;
                transition: all linear .2s;
            }
            

.colorPickSelector:hover { transform: scale(1.1); }

  </style>

    </head>

    <body>

                 
    
        <div class="table-responsive" id="tableIndex">
            <table class="table table-hover table-condensed table-striped text-center" style="width: 60%;border: solid 1px;margin-left:20%" cellspacing="0" data-page-length="100">
                <thead class="" style="font-size: small;">
                    <th scope="col" style="width: 6%;text-align:left;font-size:25px">
                    <div class="row">
                        <div class="col-8">

                            <strong>Listado de Horarios</strong>
                            <br>
                            <span style="font-size:16px; white-space: nowrap;">
                                seleccione un horario pulsando doble click en un elemento de la lista, o seleccione ver/editar
                            </span>
                        </div>
                        <div class="col" style="text-align: right;">

                                <button style="margin-right:4px;height:42px;background-color:#49d793; border: none;outline: none;" onclick ="popUpCrearHorario()"><i class="bi bi-plus-lg" style="color:white"></i>
                           
                        </div>
                    </div>
                        <div style="border:solid 1px grey; border-radius: 10px;margin-top:30px;height:40px;width:100%;margin-left:5px" class ="row">
                    
                            <input type="text" placeholder="Busqueda" id="inputBusqueda" style="margin-top:4px;margin-left:2%;border:solid 1px grey;width:22%;height:30px; font-size: 14px;">
                            <input type="text" placeholder="Busqueda" id="inputBusquedaInicia" style="margin-top:4px;margin-left:1%;border:solid 1px grey;width:12%;height:30px; font-size: 14px;">
                            <input type="text" placeholder="Busqueda" id="inputBusquedaTermina" style="margin-top:4px;margin-left:1%;border:solid 1px grey;width:12%;height:30px; font-size: 14px;">
                         
                           
                            <select name="selectDosDias" id="selectDosDias" style="border:solid 1px grey;width:15%;height:30px;margin-top:4px;margin-left:5px; font-size: 14px;" >
                                <option value="%">Todos</option>
                                <option value="SI">SI</option>
                                <option value="NO">NO</option>
                            </select>                           
                    
                        </div>
                    </th>
                    
                </thead>

                <tbody >
                
                    <tr>

                        <td style="background-color:white" id="tdPrincipal">
                        <table style="width:100%" id="tableHorarios">
                            <thead>
                                <tr style="background-color:#59b9e2;color:white">
                           
                                    <th style="position: relative; white-space: nowrap;">Nombre</th>
                                    <th style="position: relative; white-space: nowrap;">Inicia</th>
                                    <th style="position: relative; white-space: nowrap;">Termina</th>
                                    <th style="position: relative; white-space: nowrap;">Dos dias</th>
                                    <th style="position: relative; white-space: nowrap;">Accion</th>
                                   
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <?php 
                                    foreach ($horarios as  $horario) {
                                            
                                ?>
                                        <tr>
                                            <td><?= $horario['NOMBRE'] ?></td>
                                            <td><?= $horario['INICIA'] ?></td>
                                            <td><?= $horario['TERMINA'] ?></td>
                                            <td><?= $horario['DOS_DIAS'] ?></td>
                                            <td onclick="popUpEditarHorario(this)"><i class="bi bi-pencil"></i></td>
                                            <td hidden ><?= $horario['ID'] ?></td>
                                            <td hidden ><?= $horario['TOTAL_HORAS'] ?></td>
                                            <td hidden ><?= $horario['CONTABILIZA'] ?></td>
                                            <td hidden ><?= $horario['COLOR'] ?></td>
                                        </tr>
                                <?php 
                                    }
                                ?>
                            </tbody>
                        </table>

                        
                        </td>

                    </tr>

                    
                </tbody>
            </table>
        </div>

                   
         

        <?php 
            require_once "../assets/js/js.php"
        ?>
      <script src="https://cdn.jsdelivr.net/npm/jquery-table2excel@1.1/dist/jquery.table2excel.min.js"></script>
      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.11.0/dist/sweetalert2.all.min.js"></script>

        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.2/xlsx.full.min.js"></script>
        <script src="js/colorPick.js"></script>
        <script>
 
    
        </script>


        
    </body>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            let inputBusqueda = document.getElementById("inputBusqueda");
            let inputBusquedaInicia = document.getElementById("inputBusquedaInicia");
            let inputBusquedaTermina = document.getElementById("inputBusquedaTermina");
            let selectDosDias = document.getElementById("selectDosDias");

            inputBusqueda.addEventListener("input", function() {
                var filtro = inputBusqueda.value.toUpperCase();
                var tableBody = document.getElementById("tableBody");
                var rows = tableBody.getElementsByTagName("tr");

                for (var i = 0; i < rows.length; i++) {
                    var nombre = rows[i].getElementsByTagName("td")[0];
                    if (nombre) {
                        var nombreTexto = nombre.textContent || nombre.innerText;
                        if (nombreTexto.toUpperCase().indexOf(filtro) > -1) {
                            rows[i].style.display = "";
                        } else {
                            rows[i].style.display = "none";
                        }
                    }
                }
            });

            inputBusquedaInicia.addEventListener("input", function() {
                var filtro = inputBusquedaInicia.value.toUpperCase();
                var tableBody = document.getElementById("tableBody");
                var rows = tableBody.getElementsByTagName("tr");

                for (var i = 0; i < rows.length; i++) {
                    var inicia = rows[i].getElementsByTagName("td")[1];
                    if (inicia) {
                        var iniciaTexto = inicia.textContent || inicia.innerText;
                        if (iniciaTexto.toUpperCase().indexOf(filtro) > -1) {
                            rows[i].style.display = "";
                        } else {
                            rows[i].style.display = "none";
                        }
                    }
                }
            });


            selectDosDias.addEventListener("change", function() {
                var filtro = selectDosDias.value.toUpperCase();
                var tableBody = document.getElementById("tableBody");
                var rows = tableBody.getElementsByTagName("tr");

                for (var i = 0; i < rows.length; i++) {
                    var dosDias = rows[i].getElementsByTagName("td")[3];
                    if (dosDias) {
                        var dosDiasTexto = dosDias.textContent || dosDias.innerText;
                        if (filtro === "%" || dosDiasTexto.toUpperCase() === filtro) {
                            rows[i].style.display = "";
                        } else {
                            rows[i].style.display = "none";
                        }
                    }
                }
            });
        });


    </script>
    </html>
