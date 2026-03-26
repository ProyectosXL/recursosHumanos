$(document).ready(function () {
    traerVendedores();

    $('#inputBusqueda').on('input', function() {
        filtrarTabla($(this).val().toLowerCase());
    });


    $.ajax({
        url: '../Controller/HorarioController.php?accion=sucursales',
        type: 'GET',
        success: function(response) {
    
            localStorage.setItem('sucursales', response)
        }
    })


});


const traerVendedores = () => {

    $.ajax({
        url : '../Controller/HorarioController.php?accion=traerVendedores',
        method : 'GET',
        dataType : 'json',
        success: function(data){
            localStorage.setItem("empleados", JSON.stringify(data));
            rellenarTableBody();
        }

    })
}


const rellenarTableBody = () => {

    obj = JSON.parse(localStorage.getItem("empleados"));

    let tablaDetalle = document.querySelector("#tableBody");

    tablaDetalle.innerHTML = "";
 
 
    for (let x = 0; x < obj.length; x++) {

      const tr=document.createElement('tr');
      tr.id = "trBodyDetalle";
      const td1=document.createElement('td');
      td1.classList.add("noExport");
      const td2=document.createElement('td');
      const td3=document.createElement('td');
      const td4=document.createElement('td');

      const td5=document.createElement('td');
      const td6=document.createElement('td');
      const td7=document.createElement('td');
      const td8=document.createElement('td');
 
      const td9=document.createElement('td');
      td9.classList.add("noExport");
      td9.hidden = true;
      
      const td10=document.createElement('td');
      td10.hidden = true;
      td10.classList.add("noExport");

      td2.style.textAlign = "left";
      td3.style.textAlign = "left";
      td4.style.textAlign = "left";
      td5.style.textAlign = "left";
      td6.style.textAlign = "left";



      let checkbox1 = document.createElement("input");
      checkbox1.type = "checkbox";
      checkbox1.id = "checkEmpleado";
  
        let button8 = document.createElement("a");
        button8.setAttribute("href", "verEmpleado.php?nroLegajo="+obj[x]['NRO_LEGAJO']);
        button8.innerHTML = '<i class="fa-solid fa-eye"></i>';
        // button8.className = "btn btn-primary";
        
        button8.style.color = "black";
        button8.style.border = "none";
    //   const text1=document.createTextNode(obj[x]['COD_ARTICULO']);
      const text2=document.createTextNode(obj[x]['NRO_LEGAJO']);
      const text3=document.createTextNode(obj[x]['APELLIDO']);
      const text4=document.createTextNode(obj[x]['NOMBRE']);
      const text5=document.createTextNode(obj[x]['TAREA_HABITUAL']);
      let text6 = '';
      if(obj[x]['LOCALIDAD'] != null){

        text6 = document.createTextNode(obj[x]['LOCALIDAD'].toUpperCase());

      }else{
          text6=document.createTextNode(obj[x]['LOCALIDAD']);
      }
      let sucursales = localStorage.getItem('sucursales')

      let descSucursal = '';
      if (obj[x]['NUM_SUCURSAL'] != null) {
          let sucursalEncontrada = JSON.parse(sucursales).find(sucursal => sucursal.NRO_SUCURSAL == obj[x]['NUM_SUCURSAL']);
          if (sucursalEncontrada) {
              descSucursal = sucursalEncontrada['COD_CLIENT'];
          }
      }

      const text7=document.createTextNode(descSucursal);
      const text9=document.createTextNode(obj[x]['COD_VENDEDOR']);
      const text10=document.createTextNode(obj[x]['HABILITADO']);




      td1.appendChild(checkbox1);
      td2.appendChild(text2);
      td3.appendChild(text3);
      td4.appendChild(text4);
      td5.appendChild(text5);
      td6.appendChild(text6);
      td7.appendChild(text7);
      td8.appendChild(button8);
      td9.appendChild(text9);
      td10.appendChild(text10);
      
    

      tr.appendChild(td1);
      tr.appendChild(td2);
      tr.appendChild(td3);
      tr.appendChild(td4);
      tr.appendChild(td5);
      tr.appendChild(td6);
      tr.appendChild(td7);
      tr.appendChild(td8);
      tr.appendChild(td9);
      tr.appendChild(td10);

      
      tablaDetalle.appendChild(tr);
    }
 

}



const filtrarTabla = (textoBusqueda) => {
    if($('#tableBody tr').length > 0){
        $('#tableBody tr').each(function() {
            console.log("entro")
            // Obtener el texto de todas las celdas en la fila y convertirlo a minúsculas
            let textoFila = $(this).text().toLowerCase();
            console.log(textoFila);
            // Mostrar la fila si el texto de la fila incluye el texto de búsqueda, de lo contrario, ocultarla
            $(this).toggle(textoFila.indexOf(textoBusqueda) > -1);
        });
    }else{
        console.log($('#cuadrado'))
        $('.cuadrado').each(function() {
            // let textoFila = $(this).text().toLowerCase().trim();
            let nroLegajo = $(this).find("#cuadradoIdLegajo").text().toLowerCase().trim()
            let nombreCompleto = $(this).find("#cuadradoNombre").text().toLowerCase().trim()
            let tipoVendedor = $(this).find("#cuadradoTipoVendedor").text().toLowerCase().trim()
            
            let textoFila = nroLegajo + " " + nombreCompleto + " " + tipoVendedor;
            

            $(this).toggle(textoFila.indexOf(textoBusqueda) > -1);
        })
    }


}


// Función para aplicar los filtros
function aplicarFiltros() {
    let filtroFiltro = $('#selectFiltro').val().toLowerCase();
    let filtroSucursal = $('#selectSucursal').val().toLowerCase();
    let filtroLocalidad = $('#selectLocalidad').val().toLowerCase();

    if ($('#tableBody tr').length > 0) {
        $('#tableBody tr').each(function() {
            let textoFiltro = $(this).find('td:eq(4)').text().toLowerCase();
            let textoSucursal = $(this).find('td:eq(6)').text().toLowerCase();
            let textoLocalidad = $(this).find('td:eq(5)').text().toLowerCase();

            let mostrarFila = true;

            console.log(textoFiltro,filtroFiltro)

            // Comparación del filtroFiltro
            if (filtroFiltro !== '%' && (textoFiltro !== filtroFiltro)) {
                mostrarFila = false;
            }
            

            // Comparación del filtroSucursal
            if (filtroSucursal !== '%' && textoSucursal !== filtroSucursal) {
                mostrarFila = false;
            }

            // Comparación del filtroLocalidad
            if (filtroLocalidad !== '%' && textoLocalidad !== filtroLocalidad) {
                mostrarFila = false;
            }

            if (mostrarFila) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    } else {
        $('.cuadrado').each(function() {
            let textoFiltro = $(this).find("#cuadradoV").text().toLowerCase().trim();
            let textoSucursal = $(this).find("#cuadradoSucursal").text().toLowerCase().trim();
            let textoLocalidad = $(this).find("#cuadradoLocalidad").text().toLowerCase().trim();

            let mostrarFila = true;

            // Comparación del filtroFiltro
            if (filtroFiltro !== '%') {
                let regexFiltro = new RegExp(filtroFiltro, 'i'); // 'i' indica que ignore mayúsculas y minúsculas
                if (!regexFiltro.test(textoFiltro)) {
                    mostrarFila = false;
                }
            }

            // Comparación del filtroSucursal
            if (filtroSucursal !== '%' && textoSucursal !== filtroSucursal) {
                mostrarFila = false;
            }

            // Comparación del filtroLocalidad
            if (filtroLocalidad !== '%' && textoLocalidad !== filtroLocalidad) {
                mostrarFila = false;
            }

            if (mostrarFila) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    }
}


// Agregar controlador de eventos al cambio de valor de los select
$('#selectFiltro, #selectSucursal, #selectLocalidad').on('change', aplicarFiltros);

// Aplicar filtros al cargar la página
$(document).ready(aplicarFiltros);




function cambiarFormatoCuadricula(button) {
    
    button.innerHTML = '<i class="bi bi-grid-3x3-gap-fill" style="color:white"></i>'

    button.setAttribute("onclick", "cambiarFormatoTabla(this)");


    let tdPrincipal = document.querySelector("#tdPrincipal").innerHTML = '';

    let allT = localStorage.getItem("empleados");
    allT = JSON.parse(allT);

    const grupos = [];
    const tamanoGrupo = 3;
    for (let i = 0; i < allT.length; i += tamanoGrupo) {
        grupos.push(allT.slice(i, i + tamanoGrupo));
    }

    grupos.forEach(grupo => {
        let html = `
            <div class="row ml-2" style="width:96%; display: flex; flex-wrap: nowrap;margin-bottom:20px">
        `;
        
        grupo.forEach(td => {
            
            let sucursales = localStorage.getItem('sucursales')

            let descSucursal = '';

            if (td['NRO_SUCURS'] != null) {
                let sucursalEncontrada = JSON.parse(sucursales).find(sucursal => sucursal.NRO_SUCURSAL == td['NRO_SUCURS']);
                if (sucursalEncontrada) {
                    descSucursal = sucursalEncontrada['COD_CLIENT'];
                }
            }
       
            let estado = '';
            if (td['HABILITADO'] == "S") {
                estado = '<span style="color:green">Activo</span>';
            } else {
                estado = '<span style="color:red">Inactivo</span>';
            }

            html += `
                <div class="col-4 ml-2 cuadrado" id="cuadrado">
                    <div class="row">
                        <div class="row ml-2" style="width:100%">
                            <div class="col" style="text-align:left;color:#5095e3" id="cuadradoIdLegajo"><strong>${td['NRO_LEGAJO']}</strong></div>
                            <div class="col"></div>
                            <div class="col"><a href='verEmpleado.php?nroLegajo=${td['NRO_LEGAJO']}' style="color:black"><i class="bi bi-three-dots"></i></a></div>
                        </div>
                        <div class="row ml-2">
                            <div>
                                <img src="../assets/images/pruebafoto.png" alt="" style="height: auto; width: 85%;max-width:80%">
                            </div>
                            <div class="row ml-2" style="width:98%">
                                <div class="col" style="color:#5095e3" id="cuadradoNombre"><strong>${td['APELLIDO']} ${td['NOMBRE']}</strong></div>
                            </div>
                            <div class="row ml-2" style="width:98%;display: flex; flex-wrap: nowrap;">
                                <div class="col" style="text-align:left">
                                    <img src="../assets/images/Brillo1.png" alt="" style="height: 40px; width: 40px;margin-left:10%"> 
                                   <span id="cuadradoTipoVendedor"> ${td['COD_VENDEDOR']} - <span id="cuadradoV">${td['TAREA_HABITUAL']}</span></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row ml-2" style="border-top:solid 1px grey;width:99%">
                        <div class="row" style="width:99%">
                         
                            <div class="col"><br></div>
                      
                        </div>
                        <div class="row" style="width:99%">
                          
                            <div class="col"><br></div>
                        
                        </div>
                        <div class="row" style="width:99%">
                       
                            <div class="col" style="text-align:center"><strong>Estado:<span id="cuadradoEstado">${estado}</span></strong></div>
                            <div hidden id="cuadradoSucursal">${descSucursal}</div>
                  
                        </div>
                    </div>
                </div>
            `;
        });

        html += `
            </div>
        `;

        document.querySelector("#tdPrincipal").innerHTML += html;
    });

    return 1;
}

function cambiarFormatoTabla(button){
   
    let allT = localStorage.getItem("empleados");

    document.querySelector("#tdPrincipal").innerHTML = `
    <table style="width:100%" id="tableEmpleados">
    <thead >
      <tr style="background-color:#59b9e2;color:white">
        <th> 
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
        <th>Accion</th>
      </tr>
    </thead>
    <tbody id="tableBody">
      <tr>
        
      </tr>
    </tbody>
  </table> 
  `;

    rellenarTableBody(allT);
    button.innerHTML = '<i class="fa-regular fa-address-card" style="color:white"></i>'
    button.setAttribute("onclick", "cambiarFormatoCuadricula(this)");
    return 1;
}
// Llamar a la función para crear el div
// crearDiv();
const checkAll = (div) =>{
    let allCheck = document.querySelectorAll("#checkEmpleado");
 
    allCheck.forEach(element => {
        if(div.checked){
            element.checked = true;
        }else{
            element.checked = false;
        }
    });

}