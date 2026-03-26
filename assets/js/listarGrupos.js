$(document).ready(function () {

    traerGrupos();

    document.querySelector(".col-sm-12.col-md-7").classList.add("col-md-8");
    document.querySelector(".col-sm-12.col-md-7").classList.remove("col-md-7");
    document.querySelector("#tablaGrupos_filter").querySelector("input").style.marginRight = '4rem';
    
});

const traerGrupos = () => {
    $.ajax({
        url: '../Controller/VendedorController.php?accion=traerGrupos',
        method: 'GET',
        dataType: 'json',
        success: function (data) {
            let array = data
          
            // // Obtén una referencia al elemento select


          
            let tableBody = document.querySelector("#bodyGrupos");

            // $('#tablaSucursales').DataTable().destroy();
            // Limpiar el contenido actual de la tabla
            tableBody.innerHTML = "";
        
            // Recorrer los datos y agregar filas a la tabla
            
            array.forEach(element => {
                let row = tableBody.insertRow();
                let cell1 = row.insertCell(0);
                let cell2 = row.insertCell(1);
                let cell3 = row.insertCell(2);
                let cell4 = row.insertCell(3);
                
                var fecha = new Date( element.CREATED_AT.date);

           

                // Obtener día, mes y año
                var dia = fecha.getUTCDate();
                var mes = fecha.getUTCMonth() + 1; // Los meses van de 0 a 11, por lo que sumamos 1
                var anio = fecha.getUTCFullYear();

                // Formatear la fecha
                var fechaFormateada = dia + '/' + mes + '/' + anio;
                
                cell1.innerHTML = fechaFormateada;
                cell1.style.textAlign = 'center'
                cell2.innerHTML = element.NOMBRE;
                cell2.style.textAlign = 'center'
                // cell4.innerHTML = '';
                cell3.innerHTML = `<a href='editarGrupo.php?nombreGrupo=${element.NOMBRE}'><button type='button' class='btn btn-warning' style='width: 40px;height: 34px'><i class='bi bi-pencil-square' style='color:white'></i></button></a>`;
                cell4.innerHTML = `<button type='button' class='btn btn-danger' style='width: 40px;height: 34px' onclick="borrar(this)"><i class='bi bi-trash3' style='color:white'></i></button>`;
                
            });

        }
    })


}
$('#tablaGrupos').DataTable({
    "bLengthChange": false,
    "language": {
        "lengthMenu": "mostrar _MENU_ registros",
        "info": "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
        "paginate": {
            "next": "",
            "previous": ""
        }
    },
 
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
document.querySelector("#tablaGrupos_filter").parentElement.parentElement.style.marginTop = '30px'
let a = document.querySelector("#tablaGrupos_filter")
document.querySelector("#tablaGrupos_filter").parentElement.parentElement.childNodes[0].append(a)
document.querySelector("#tablaGrupos_filter").parentElement.parentElement.childNodes[0].classList.replace('col-md-6','col-md-7')



const borrar = (div) => {

    let grupo = div.parentElement.parentElement.querySelectorAll("td")[1].textContent
    Swal.fire({
        icon: "warning",
        title: "Desea eliminar el grupo?",
        showDenyButton: true,
        confirmButtonText: "Eliminar",
        denyButtonText: 'Cancelar'
        }).then((result) => {
        /* Read more about isConfirmed, isDenied below */
        if (result.isConfirmed) {
            $.ajax({ 
                url: '../Controller/VendedorController.php?accion=borrarGrupo',
                method: 'POST',
                data: {
                    nombreGrupo:grupo,
                    
                },
                success: function (data) {
                    Swal.fire("Grupo eliminado correctamente!", "", "success").then((result)=>{

                        location.reload()
                    })
                }
            })
       
        } else if (result.isDenied) {
        Swal.fire("El grupo no fue eliminado", "", "info");
        }
        });
    
}