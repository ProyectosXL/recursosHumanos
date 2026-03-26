<?php

require_once '../Class/Horario.php';

$horario = new Horario();
$json_data = file_get_contents('../assets/localidadesArgentina1.json');
$uyJson_data = file_get_contents('../assets/localidadesUruguay1.json');

$dataUy = json_decode($uyJson_data, true);
$dataArg = json_decode($json_data, true);



$sucursales = $horario->traerSucursales();

$dataArg = $dataArg[0]['sinonimos'];
$dataUy = $dataUy[0]['sinonimos'];


?>
<!DOCTYPE html>
    <html lang="en">
    <head>

        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Control Horario</title>
       
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


  </style>

    </head>

    <body>

                 
    
        <div class="table-responsive" id="tableIndex">
            <table class="table table-hover table-condensed table-striped text-center" style="width: 60%;border: solid 1px;margin-left:20%" cellspacing="0" data-page-length="100">
                <thead class="" style="font-size: small;">
                    <th scope="col" style="width: 6%;text-align:left;font-size:25px">
                    <div class="row">
                        <div class="col-6">

                            <strong>Listado de Empleados</strong>
                        </div>
                        <div class="col" style="text-align: right;">

                            
                            <button style="margin-right:4px;height:42px;background-color:#3f6fad; border: none;outline: none;" onclick="cambiarFormatoCuadricula(this)"><i class="fa-regular fa-address-card" style="color:white"></i>
                            <a href="crearEmpleado.php"><button style="margin-right:4px;height:42px;background-color:#49d793; border: none;outline: none;" ><i class="bi bi-plus-lg" style="color:white"></i></a>
                            <button style="margin-right:4px;height:42px;background-color:#59b9e2; border: none;outline: none;" onclick="exportTable()"><i class="bi bi-printer-fill" style="color:white"></i>
                            <button style="height:42px;background-color:##f0f0f0; border: none;outline: none;"><i class="bi bi-three-dots-vertical" style="color:black"></i>
                        </div>
                        
                    </div>
                        <div style="border:solid 1px grey; border-radius: 10px;margin-top:30px;height:40px;width:100%;margin-left:5px" class ="row">
                    
                            <input type="text" placeholder="Busqueda" id="inputBusqueda" style="margin-top:4px;margin-left:2%;border:solid 1px grey;width:22%;height:30px; font-size: 14px;">
                            <select name="selectFiltro" id="selectFiltro" style="border: solid 1px grey; width: 15%; height: 30px; margin-top: 4px; margin-left: 5px;font-size: 14px;">
                            <option value="%">Todos</option>
                            <option value="ENCARGADA">Encargada</option>
                            <option value="ENCARGADO">Encargado</option>
                            <option value="SUB ENCARGADO">Sub Encargado</option>
                            <option value="SUB ENCARGADA">Sub Encargada</option>
                            <option value="CAJERO">Cajero</option>
                            <option value="CAJERA">Cajera</option>
                            <option value="VENDEDOR">Vendedor</option>
                            <option value="VENDEDORA">Vendedora</option>
                
                            </select>
                            <select name="selectLocalidad" id="selectLocalidad" style="border:solid 1px grey;width:15%;height:30px;margin-top:4px;margin-left:5px;font-size: 14px;" >
                            <option value="%">Todos</option>
                            <optgroup label="Argentina">
                            <?php 
                            
                            foreach ($dataArg as  $value) {
                                
                                echo "<option value='".$value."'>".$value."</option>";

                            }
                            ?>
                            </optgroup>
                            <optgroup label="Uruguay">
                            <?php
                            foreach ($dataUy as  $value) {
                                
                                echo "<option value='".$value."'>".$value."</option>";

                            }
                            ?>
                            </optgroup>
                            </select>
                            <select name="selectSucursal" id="selectSucursal" style="border:solid 1px grey;width:15%;height:30px;margin-top:4px;margin-left:5px; font-size: 14px;" >
                            <option value="%">Todos</option>
                            <?php 
                                foreach ($sucursales as $sucursal) {
                                    echo "<option value='".$sucursal['COD_CLIENT']."'>".$sucursal['DESC_SUCURSAL']."</option>";
                                }
                            ?>
                            </select>                           
                    
                        </div>
                    </th>
                    
                </thead>

                <tbody >
                
                    <tr>

                        <td style="background-color:white" id="tdPrincipal">
                        <table style="width:100%" id="tableEmpleados">
                            <thead>
                                <tr style="background-color:#59b9e2;color:white">
                                    <th class="noExport"> 
                                        <input type="checkbox" id="miCheckbox" class="custom-checkbox" onclick="checkAll(this)">
                                        <label for="miCheckbox" class="custom-checkbox-label"></label>
                                    </th>
                                    <th style="position: relative; white-space: nowrap;">
                                        <div style="display: flex; justify-content: space-between;">
                                            <span>Legajo</span>
                                            <span style="margin-left: 10px;">
                                                <i class="bi-arrow-down" onclick="ordenarPor(this)"></i>
                                            </span>
                                        </div>
                                    </th>
                                    <th style="position: relative; white-space: nowrap;">
                                        <div style="display: flex; justify-content: space-between;">
                                            <span>Apellido</span>
                                            <span style="margin-left: 10px;">
                                                <i class="bi-arrow-down" onclick="ordenarPor(this)"></i>
                                            </span>
                                        </div>
                                    </th>
                                    <th style="position: relative; white-space: nowrap;">
                                        <div style="display: flex; justify-content: space-between;">
                                            <span>Nombre</span>
                                            <span style="margin-left: 10px;">
                                                <i class="bi-arrow-down" onclick="ordenarPor(this)"></i>
                                            </span>
                                        </div>
                                    </th>
                                    <th style="position: relative; white-space: nowrap;">
                                        <div style="display: flex; justify-content: space-between;">
                                            <span>Cargo</span>
                                            <span style="margin-left: 10px;">
                                                <i class="bi-arrow-down" onclick="ordenarPor(this)"></i>
                                            </span>
                                        </div>
                                    </th>
                                    <th style="position: relative; white-space: nowrap;">
                                        <div style="display: flex; justify-content: space-between;">
                                            <span>Localidad</span>
                                            <span style="margin-left: 10px;">
                                                <i class="bi-arrow-down" onclick="ordenarPor(this)"></i>
                                            </span>
                                        </div>
                                    </th>
                                    <th style="position: relative; white-space: nowrap;">
                                        <div style="display: flex; justify-content: space-between;">
                                            <span>Local</span>
                                            <span style="margin-left: 10px;">
                                                <i class="bi-arrow-down" onclick="ordenarPor(this)"></i>
                                            </span>
                                        </div>
                                    </th>
                                    <th class="noExport">Accion</th>
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <tr>
                                    <!-- Contenido de las filas -->
                                </tr>
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

        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.2/xlsx.full.min.js"></script>
        <script>
       
        </script>


        
    </body>

    <script>
        
    const ordenarPor = (flecha) => {
        
        // Cambiar las clases de la flecha
        if (flecha.classList.contains("bi-arrow-up")) {
        flecha.classList.remove("bi-arrow-up");
        flecha.classList.add("bi-arrow-down");
        } else {
            flecha.classList.remove("bi-arrow-down");
            flecha.classList.add("bi-arrow-up");
        }

        // Obtener la columna asociada a la flecha
        let columna = flecha.closest("th") 


        // Obtener la tabla y las filas de datos
        let table = document.getElementById("tableEmpleados");
        let tbody = table.getElementsByTagName("tbody")[0];
        let rows = Array.from(tbody.getElementsByTagName("tr"));

        // Determinar el índice de la columna
        let columnIndex = 0;
    
        if (table.rows.length > 0) {
            columnIndex = Array.from(table.rows[0].cells).findIndex(cell => cell.textContent === columna.textContent);
        }

        // Obtener la dirección de ordenamiento actual de la columna
        let currentOrder = columna.dataset.order || "asc";

        // Cambiar la dirección de ordenamiento para la próxima vez que se haga clic
        columna.dataset.order = currentOrder === "asc" ? "desc" : "asc";

        // Ordenar las filas según la columna y la dirección de ordenamiento

        rows.sort((a, b) => {
            let aValue = a.cells[columnIndex].textContent.trim();
            let bValue = b.cells[columnIndex].textContent.trim();

            // Convertir los valores a números si la columna es numérica
            if (!isNaN(aValue) && !isNaN(bValue)) {
                aValue = parseFloat(aValue);
                bValue = parseFloat(bValue);
            }

            if (currentOrder === "asc") {
                return aValue > bValue ? 1 : aValue < bValue ? -1 : 0;
            } else {
                return aValue < bValue ? 1 : aValue > bValue ? -1 : 0;
            }
        });

        // Vaciar el cuerpo de la tabla
        while (tbody.firstChild) {
            tbody.removeChild(tbody.firstChild);
        }

        // Agregar las filas ordenadas de nuevo al cuerpo de la tabla
        rows.forEach(row => tbody.appendChild(row));
    }

    const exportTable = () =>{

        $("#tableEmpleados").table2excel({
            // exclude CSS class
            exclude: ".noExport",
            name: "excel Document ",
            filename: "Excel", //do not include extension
            fileext: ".xlsx" // file extension
        });
    }
    </script>
    </html>
