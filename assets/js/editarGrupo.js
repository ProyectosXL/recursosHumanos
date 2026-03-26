$(document).ready(function () {

traerLocales();
document.querySelector("#tableIndex").style.height= "620px";

});

const traerLocales = () => {
$.ajax({
    url: '../Controller/VendedorController.php?accion=traerSucursales',
    method: 'GET',
    dataType: 'json',
    success: function (data) {
        let array = data;
        let tableBody = document.querySelector("#bodyAsignar");
        let grupo = document.querySelector("#grupo").textContent
        $('#tablaClientes').DataTable().destroy();
        // Limpiar el contenido actual de la tabla
        tableBody.innerHTML = "";
          
        $.ajax({
            url: '../Controller/VendedorController.php?accion=traerLocalesPorGrupo',
            method: 'POST',
            data: {
                // codPromocion:cadena,
                grupos:grupo
            },
            success: function (response) {

                response = JSON.parse(response)
                1
                let tablaAgregados = document.querySelector("#bodyAsignado")
                 // Recorrer los datos y agregar filas a la tabla
                 let agregados = []
                array.forEach(element => {

                   
                    
                    response.forEach(e => {
                        
                        if(e.NRO_SUCURSAL == element.NRO_SUCURSAL){

                            let row = tablaAgregados.insertRow();
                            let cell1 = row.insertCell(0);
                            cell1.innerHTML = element.NRO_SUCURSAL +' - '+element.DESC_SUCURSAL;
                            cell1.setAttribute("onclick", "marcarQuitar(this)");
                            agregados.push(element.NRO_SUCURSAL)
                            
                        }

                        
                       
                    });
              
                    if(!(agregados.includes(element.NRO_SUCURSAL))){

                        let row = tableBody.insertRow();
                        let cell1 = row.insertCell(0);
                        cell1.innerHTML = element.NRO_SUCURSAL +' - '+element.DESC_SUCURSAL;
                        cell1.setAttribute("onclick", "marcarAgregar(this)");
                    }
                   
                
                });
            }
        })
       


    }

});
};



const agregar = () => {

allSucursalesAgregar = document.querySelectorAll('.marcadoAgregar')
let tablaAgregados = document.querySelector("#bodyAsignado")

allSucursalesAgregar.forEach(element => {
    
    element.classList.remove('marcadoAgregar')
    element.setAttribute("onclick","marcarQuitar(this)")
    tr = element.parentElement
    tablaAgregados.append(tr);


});

ordernarTabla('tablaAsignado')

}


const quitar = () => {

allSucursalesQuitar = document.querySelectorAll('.marcadoQuitar')
let tablaAgregados = document.querySelector("#bodyAsignar")

allSucursalesQuitar.forEach(element => {
    
    element.classList.remove('marcadoQuitar')
    element.setAttribute("onclick","marcarAgregar(this)")
    tr = element.parentElement
    tablaAgregados.append(tr);


});

ordernarTabla('tablaAsignar')


}


const ordernarTabla = (tabla) => {

var filas = $(`#${tabla} tbody > tr`).get();
filas.sort(function (a, b) {
  var numA = parseInt($(a).find('td').text().split(' ')[0]);
  var numB = parseInt($(b).find('td').text().split(' ')[0]);
  return numA - numB;
});
// Reorganizar las filas en la tabla
$.each(filas, function (index, fila) {
  $(`#${tabla}`).append(fila);
});

}

const marcarAgregar = (div) => {

if(div.getAttribute("class") == 'marcadoAgregar'){

    div.classList.remove("marcadoAgregar");

}else{

    div.classList.add("marcadoAgregar");

}
}

const marcarQuitar = (div) => {

if(div.getAttribute("class") == 'marcadoQuitar'){

    div.classList.remove("marcadoQuitar");

}else{

    div.classList.add("marcadoQuitar");

}
}


const actualizar = () =>{

let error = false ;
let tablaAsignado = document.querySelectorAll("#bodyAsignado tr")

let array = []

if(tablaAsignado.length < 1){
    Swal.fire({
        icon: 'warning',
        title: 'Atención!',
        text: 'Debe incluir al menos una sucursal'
    })
        error = true
}

if(!error){

    let nombreGrupo = document.querySelector("#grupo").textContent

    stringParaSql = ''

    tablaAsignado.forEach(element => {

        stringParaSql += "('"+element.querySelector("td").textContent.split(' ')[0]+"', '"+nombreGrupo+"',GETDATE(),GETDATE()),"
    });

    stringParaSql = stringParaSql.slice(0, -1);


    $.ajax({ 
        url: '../Controller/VendedorController.php?accion=editarGrupo',
        method: 'POST',
        data: {
            nombreGrupo:nombreGrupo,
            sucursales:stringParaSql
        },
        success: function (data) {
            
            Swal.fire({
                icon: 'success',
                title: 'Grupo editado correctamente!',
                showConfirmButton: false,
                timer: 2500
            }).then(function () {
                window.location = 'listarGrupos.php'
            }); 

        }

    });

    
}

}