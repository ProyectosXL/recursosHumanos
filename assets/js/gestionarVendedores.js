const traerVendedores = () => {

    let sucursal = document.querySelector("#selectSucursal").value
    let filtroHabilitados = document.querySelector("#filtroHabilitados").value
 
    
    document.querySelector("#spanSucursal").textContent = $('#selectSucursal option:selected').attr('attr-name');
    $('#tablaVendedores').DataTable().destroy();

    let trVendedor = document.querySelectorAll("#trVendedor")


    trVendedor.forEach(vendedor => {

        vendedor.querySelectorAll("td")[2].querySelector("input").checked = false
    })
    

    $.ajax({
        type: "POST",
        url: "../Controller/VendedorController.php?accion=traerVendedoresPorSucursal",
        data: {
            sucursal: sucursal,
            filtroHabilitados: filtroHabilitados
        },
        success: function (response) {
            
            if(response == false){
                Swal.fire({
                    icon: "error",
                    title: "Local sin conexion",
                    confirmButtonText: "Cerrar",
                })
            }else{
                data = JSON.parse(response)
                let tabla = document.querySelector("#bodyVendedores")
                tabla.innerHTML = '';
           
                data.forEach(element => {
                  
                    let row = document.createElement("tr")

                    row.setAttribute("id","trVendedor");
                    let checked = (element['INHABILITA'] != 0) ? '' : 'checked'
                    let textHtml =  `
                    <td>${element['COD_VENDED']}</td>
                    <td>${element['NOMBRE_VEN']}</td>
                    <td><input type="checkbox" style="width: 20px;height: 20px;" ${checked}></td>`;

                    row.innerHTML = textHtml;
                    tabla.appendChild(row)
                });

               
               
            }
            activarDatatable();
        }
    });

  

}

const marcarTodos = (check) => {

    $('#tablaVendedores').DataTable().destroy();

    let trVendedor = document.querySelectorAll("#trVendedor")

    trVendedor.forEach(vendedor => {

        if(check.checked == true){

            vendedor.querySelectorAll("td")[2].querySelector("input").checked = true
        }else{
            vendedor.querySelectorAll("td")[2].querySelector("input").checked = false

        }
    })
    
    activarDatatable();

} 


const cambiarEntorno = (t) =>{


    let entorno = 'central';

    if(t.getAttribute("data-off") == "ARG" ){
        entorno = 'central';
    }else{
        entorno = 'uy';
    }

    console.log(entorno)

    $.ajax({
    url: "../Controller/vendedorController.php?accion=cambiarEntorno",
    method: "POST",
    data : {entorno: entorno},
    success: function (data) {
        location.reload();
    }
    });
}


const guardar = () => {

    $('#tablaVendedores').DataTable().destroy();

    let stringParaSqlHabilita = "(";
    let stringParaSqlDeshabilita = "(";
    let sucursal = document.querySelector("#selectSucursal").value
   
    if(sucursal == "0"){
        
        Swal.fire({
            icon: 'warning',
            title: 'Atención!',
            text: 'Debe seleccionar una sucursal'
        })
        return 1
    }


    let trVendedor = document.querySelectorAll("#trVendedor")

    trVendedor.forEach(element => {
        
        if(element.querySelectorAll("td")[2].querySelector("input").checked == true){

            stringParaSqlHabilita += "'" + element.querySelector("td").textContent + "',"
            
        }else{
            stringParaSqlDeshabilita += "'"+ element.querySelector("td").textContent + "',"
        }
    });

    stringParaSqlHabilita = (stringParaSqlHabilita.length > 1) ?  stringParaSqlHabilita.slice(0, -1) + ")" : "('')"
    
    stringParaSqlDeshabilita = (stringParaSqlDeshabilita.length > 1) ? stringParaSqlDeshabilita.slice(0, -1) + ")" : "('')";

    $.ajax({
        url: "../Controller/vendedorController.php?accion=guardarGestionVendedores",
        method: "POST",
        data : {
            sucursal: sucursal,
            stringParaSqlHabilita: stringParaSqlHabilita,
            stringParaSqlDeshabilita: stringParaSqlDeshabilita
        },
        success: function (data) {
            
            
            Swal.fire({
                icon: 'success',
                title: 'Completado!',
                text: 'Se han guardado correctamente los cambios '
            }).then((result) => {
                location.reload();
            })
        }
        });
    
}


const activarDatatable = () =>{
    document.querySelector("#colBusquedaRapida").innerHTML = '';

    $('#tablaVendedores').DataTable({
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

            "sSearch": "",
            "sSearchPlaceholder" : "Sobre cualquier campo"
            

        },
    });

    let filtro = document.querySelector(".dataTables_filter")
    let nuevoLugar = document.querySelector("#colBusquedaRapida")
    nuevoLugar.appendChild(filtro)
    document.querySelector("#busquedaRapida").hidden = false
}