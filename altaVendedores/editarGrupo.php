<?php
    $nombreGrupo = $_GET['nombreGrupo'];

?>

    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Editar grupo</title>
        <?php 
            require_once $_SERVER['DOCUMENT_ROOT'].'/administracion/assets/css/css.php';
        ?>
        

        </link>
    </head>
    <style>
  

    </style>
    <body>

        <div class="alert alert-secondary">
            <div class="page-wrapper bg-secondary p-b-100 pt-2 font-robo">
                <div class="wrapper wrapper--w680"><div style="color:white; text-align:center"><h6>Editar grupo</h6></div>
                    <div class="card card-1">
                        <div style="margin-left:18%"><h3><strong><i class="bi bi-people-fill"></i> Editar grupo</strong></h3></div>
                        <div class="row" style="margin-left:18%">
                        <div hidden id="grupo"><?= $nombreGrupo ?> </div>
                            <div class="col-5 p-0"><?= $nombreGrupo ?></div>
                            <div class="col text-center" style="margin-right:140px" ><button class="btn btn-primary" style="width:120px" onclick="actualizar()">Guardar <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-floppy" viewBox="0 0 16 16"><path d="M11 2H9v3h2z"/><path d="M1.5 0h11.586a1.5 1.5 0 0 1 1.06.44l1.415 1.414A1.5 1.5 0 0 1 16 2.914V14.5a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 0 14.5v-13A1.5 1.5 0 0 1 1.5 0M1 1.5v13a.5.5 0 0 0 .5.5H2v-4.5A1.5 1.5 0 0 1 3.5 9h9a1.5 1.5 0 0 1 1.5 1.5V15h.5a.5.5 0 0 0 .5-.5V2.914a.5.5 0 0 0-.146-.353l-1.415-1.415A.5.5 0 0 0 13.086 1H13v4.5A1.5 1.5 0 0 1 11.5 7h-7A1.5 1.5 0 0 1 3 5.5V1H1.5a.5.5 0 0 0-.5.5m3 4a.5.5 0 0 0 .5.5h7a.5.5 0 0 0 .5-.5V1H4zM3 15h10v-4.5a.5.5 0 0 0-.5-.5h-9a.5.5 0 0 0-.5.5z"/></svg></button></div>
                        </div>
                        <div class="row" style = "height:46rem;width:100%;margin-left:10px;margin-top:10px;margin-bottom:5px">
                            
                        
                        <div class="col-2"></div>

                            <div class="col-3" style="border:solid 1px;">
                                <div style="margin-top:15px"><h3><i class="bi bi-building"></i> Sucursales sin asignar</h3></div>
                                <div class="table-responsive" id="tableIndex" style="margin-top:15px">
                                    <div class="table-wrapper" id="tableIndex">
                                        <table class="table table-hover table-condensed  text-center"  id="tablaAsignar" >
                                            
                                            <thead class="thead-dark" style="font-size: small;">
                                              
                                                <th scope="col" >Sucursal</th>

                                            </thead>

                                            <tbody id="bodyAsignar" style="font-size: small;">
                                                
                                            </tbody>

                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="col-1">
                            <div style="text-align:center"> 
                                <div style="margin-top:200%">
                                    <button type="button" class="btn " style="background-color:#9e9e9e;color:white;width: 116px;height: 39px;" onclick="agregar()">Agregar  ▷▷</button>
                                </div>
                         
                                <div style="margin-top:10%">
                                    <button type="button" class="btn " style="background-color:#9e9e9e;color:white;width: 116px;height: 39px;" onclick="quitar()">◁◁  Quitar</button>
                                </div>
                                </div>
                            </div>

                            <div class="col-3"  style="border:solid 1px;margin-left:20px;margin-right:20px">
                            <div style="margin-top:15px"><h3><i class="bi bi-check-circle"></i> Sucursales asignadas</h3></div>
                                <div class="table-responsive" id="tableIndex" style="margin-top:15px">
                                    <div class="table-wrapper" id="tableIndex">
                                        <table class="table table-hover table-condensed  text-center"  id="tablaAsignado" >
                                            
                                            <thead class="thead-dark" style="font-size: small;">
                                              
                                                <th scope="col" >Sucursal</th>

                                            </thead>

                                            <tbody id="bodyAsignado" style="font-size: small;">
                                                
                                            </tbody>

                                        </table>
                                    </div>
                                </div>
                               
                        
                            </div>
                            <div class="col-2"></div>
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php 

            require_once $_SERVER['DOCUMENT_ROOT'].'/administracion/assets/js/js.php';
        ?>
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
       
    </body>

    </html>
    <script>    
    

    $(document).ready(function () {

        $('#selectRangoEtario').select2();
        $('#selectPromocion').select2();

    });


    // document.querySelector("#selectBanco").selectedOptions[0].value
    </script>
