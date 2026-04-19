<?php

require_once '../Class/Vendedor.php';

session_start();

$vendedor = new Vendedor();

$grupos = $vendedor->traerGrupos();
$sucursales = $vendedor->traerSucursales();
$vendedores = $vendedor->traerVendedores();


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
        <title>Alta vendedores</title>
        <?php 
            require_once '../assets/css/css.php';
        ?>
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        </link>
        <style>

        .dataTables_filter {
            text-align: left;
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

        .loading {
            position: fixed;
            left: 0px;
            top: 0px;
            width: 100%;
            height: 100%;
            z-index: 9999;
            background: url('../assets/images/g0R9.gif') 50% 50% no-repeat rgb(0, 0, 0);
            background-size: 25%;
            opacity: .8;
        }
        
        </style>
        
    </head>
    
    <body>

        <div class="alert alert-secondary">
            <div class="page-wrapper bg-secondary p-b-100 pt-2 font-robo">
                <div class="wrapper wrapper--w680"><div style="color:white; text-align:center"><h6>Segmentacion de Clientes</h6></div>
                    <div class="card card-1">
                        <div class="row" style = "height:845px;width:100%;margin-left:10px;margin-top:5px;margin-bottom:5px">
                        <div id="boxLoading"></div>
                            <div class="col-3" style="border:solid 1px;">
                                <form action="">
                                 
                           
                                    <div class="row">

                                        <label style="margin-left:20px;margin-top:20px"><i class="bi bi-people-fill"></i> <strong>Grupos</strong></label>

                                        <select id="selectGrupo" name="selectGrupo[]" style="width:100%;margin-left:20px" class="js-states form-control select2-hidden-accessible" multiple="" data-select2-id="selectBanco" tabindex="-1" aria-hidden="true">
                                           
                                            <?php 
                                                foreach ($grupos as  $grupo) {
                                                    $locales = $vendedor->traerLocalesPorGrupo($grupo['NOMBRE']);
                                                    $stringLocales = '';
                                                    foreach ($locales as $key => $local) {
                                                        $stringLocales .= $local['NRO_SUCURSAL'].',';
                                                    }


                                                    echo '<option value="'.$grupo['NOMBRE'].'?'.$stringLocales.'" >'.$grupo['NOMBRE'].'</option>';
                                                }
                                            ?>

                                        </select>
                                    </div>

                                    <div class="row" style="margin-top:5px">

                                        <label style="margin-left:20px"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-building-fill" viewBox="0 0 16 16"><path d="M3 0a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h3v-3.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 .5.5V16h3a1 1 0 0 0 1-1V1a1 1 0 0 0-1-1zm1 2.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5M4 5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM7.5 5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5m2.5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM4.5 8h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5m2.5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5"/></svg> Sucursales</label>

                                        <select id="selectSucursal" name="selectSucursal[]" style="width:100%;margin-left:20px" class="js-states form-control select2-hidden-accessible" multiple="" onchange="" data-select2-id="selectRubro" tabindex="-1" aria-hidden="true">
                                                
                                            <?php 
                                                foreach ($sucursales as  $sucursal) {

                                                    echo '<option value="'.$sucursal['NRO_SUCURSAL'].'">'.$sucursal['DESC_SUCURSAL'].'</option>';
                                                }
                                            ?>

                                        </select>
                                    </div>

                                </form>
                            </div>

                            <div class="col"  style="border:solid 1px;margin-left:20px;margin-right:20px">
                            <div class="col-12" style="text-align:right; margin-top:0.5rem;">

                                        <a href="http://192.168.0.13:8000/" style="display:inline-block;">
                                            <img src="../image/home-button.png" style="width:50px;height:45px;margin-right:40rem;transition: transform 0.3s;" title="Menú" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                                        </a> 
                                <!-- <input type="checkbox" checked data-toggle="toggle" data-on="ARG" data-off="UY" class="custom-toggle" style="color:black; font-size: 0;" onchange="cambiarEntorno(this)" id="checkEntorno" <?= $_SESSION['CHECKED']  ?>> -->
                                    <input type="checkbox" checked data-toggle="toggle" data-on="<?= $dataOnValue ?>" data-off="<?= $dataOffValue ?>" class="custom-toggle" style="color:black; font-size: 0;" onchange="cambiarEntorno(this)" id="checkEntorno" >
                                </div>
                                    <div class="row" style="margin-left:50px;margin-top: 10px">  
                                        <h3><img src="../assets/images/price-tag.png" alt=""  style="margin-right: 20px;width: 30px;height: 30px;"><strong>Alta vendedores sucursales</strong></h3>
                                    </div>
                            
                                    <div class="row" style="margin-left:5px;margin-right:30px;margin-top:30px;">

                                        <div class="table-responsive" id="tableIndex">
                                            <div class="table-wrapper" id="">
                                                <table class="table table-hover table-condensed table-striped text-center" id="tablaClientes" style="width: 80%;" cellspacing="0" data-page-length="10">
                                                    
                                                    <thead class="thead-dark" style="font-size: small;">
                                                        <th scope="col" style="width: 6%">COD. VENDEDOR</th>
                                                        <th scope="col" style="width: 15%">NOMBRE VENDEDOR</th>
                                                        <th scope="col" style="width: 8%">SELECCIONAR</th>

                                                    </thead>

                                                    <tbody id="tableVb" style="font-size: small;">
                                                        <?php 
                                                            foreach ($vendedores as $key => $vendedor) {
                                
                                                                echo '<tr>';
                                                                    echo '<td>'.$vendedor['COD_VENDED'].'</td>';
                                                                    echo '<td>'.$vendedor['NOMBRE_VEN'].'</td>';
                                                                    echo '<td><input type="checkbox" ></td>';
                                                                echo '</tr>';
                                                            }
                                                        ?>
                                                    </tbody>

                                                </table>
                                            </div>
                                        </div>

                                    </div>
                        
                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php 
        require_once '../assets/js/js.php';
        require_once 'modal.php';
    ?>

            <link href="https://gitcdn.github.io/bootstrap-toggle/2.2.2/css/bootstrap-toggle.min.css" rel="stylesheet">
        <script src="https://gitcdn.github.io/bootstrap-toggle/2.2.2/js/bootstrap-toggle.min.js"></script>
    </body>

    </html>
    <script>    

        $(document).ready( function () {

        $('#tablaClientes').DataTable({
            "bLengthChange": false,
            
            "bInfo": false,
            "aaSorting": false,
            'columnDefs': [
                {
                    "targets": "_all", 
                    "className": "text-center",
                    "sortable": false,
             
                },
            ],
            "oLanguage": {
  
                "sSearch": "Busqueda rapida:",
                "sSearchPlaceholder" : "Sobre cualquier campo"
                

            },
        });
        $("#tablaClientes_filter").append(`<button class="btn btn-success btn_exportar" style="margin-bottom:4px;margin-left:10px;height:40px;margin-right:5px" onclick ="ejecutarAccion('altaVendedores')"> Habilitar <i class="bi bi-plus-circle-fill"></i></button><button class="btn btn-danger btn_exportar" style="margin-bottom:4px;margin-left:10px;height:40px;margin-right:5px" onclick ="ejecutarAccion('bajaVendedores')"> Inhabilitar <i class="bi bi-dash-circle-fill"></i></button>`);
        
        $('.dataTables_filter input[type="search"]').css(
            {'height':'40px'}
        );
        $('[data-toggle="tooltip"]').tooltip()

        let filtro = document.querySelector(".dataTables_filter")
        let nuevoLugar = document.querySelector(".dataTables_filter").parentElement.parentElement.childNodes[0]
        console.log( document.querySelector(".dataTables_filter").parentElement.parentElement.childNodes[0])
        nuevoLugar.appendChild(filtro)
        document.querySelector("#tablaClientes_paginate").style.width="65%"

        document.querySelector(".toggle").style.width="40px"
        document.querySelector(".toggle-on").style.fontSize="0"
        document.querySelector(".toggle-off").style.fontSize="0"

        document.querySelector(".toggle.btn.btn-primary").style.marginRight="22%"
        document.querySelector("#tablaClientes_filter").parentElement.classList.remove("col-md-6")

        document.querySelector("#tablaClientes_filter").parentElement.classList.add("col-md-8")
        
        
    } );

    document.querySelector("#select2-selectGrupo-container").parentElement.style.height="100px"
    document.querySelector("#select2-selectGrupo-container").parentElement.style.marginLeft="10px"
    document.querySelector("#select2-selectGrupo-container").parentElement.style.marginRight="10px"

    document.querySelector("#select2-selectSucursal-container").parentElement.style.height="100px"
    document.querySelector("#select2-selectSucursal-container").parentElement.style.marginLeft="10px"
    document.querySelector("#select2-selectSucursal-container").parentElement.style.marginRight="10px"



    // document.querySelector("#selectBanco").selectedOptions[0].value
    </script>
