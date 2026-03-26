$('#selectGrupo').select2({});
 
$('#selectGrupo').on('select2:opening select2:closing', function( event ) {
var $searchfield = $(this).parent().find('.select2-search__field');
// $searchfield.prop('disabled', true);
});



$('#selectSucursal').select2();

$('#selectSucursal').on('select2:opening select2:closing', function( event ) {
var $searchfield = $(this).parent().find('.select2-search__field');
// $searchfield.prop('disabled', true);
});

const mostrarSpiner = () =>{

    let spinner = document.querySelector("#boxLoading");

    spinner.classList.add("loading");


}

$('#selectCategoria').select2();

$('#selectCategoria').on('select2:opening select2:closing', function( event ) {
var $searchfield = $(this).parent().find('.select2-search__field');
// $searchfield.prop('disabled', true);
});


$('#selectRangoEtario').select2();

$('#selectRangoEtario').on('select2:opening select2:closing', function( event ) {
var $searchfield = $(this).parent().find('.select2-search__field');
// $searchfield.prop('disabled', true);
});

const filtrarCategoria = () =>{

    let allOptions = Array.from(document.querySelector("#selectGrupo").selectedOptions);

    let arraySelected = [];
    allOptions.forEach((element,x )=> {
        arraySelected[x] = element.value;
    });

    let rubros = "";

    if(arraySelected.length != 0){

        rubros = JSON.stringify(arraySelected).replace("[", "(").replace("]", ")").replace(/"/g, "'");
    
    }

        $.ajax({

            url: '../Controller/ClienteController.php?accion=traerCategorias',
            type: 'POST',
            dataType: 'json',
            data: {rubros: rubros},
            success: function (data) {
                let html = '';
                data.forEach(element => {
                    html += `<option value="${element.RUBRO}-${element.CATEGORIA}">${element.CATEGORIA}</option>`;
                });
                $('#selectCategoria').html("");
                $('#selectCategoria').html(html);
                $('#selectCategoria').select2();
            }

        })

}

const exportTable = () =>{

    $(`#tablaClientes`).table2excel({
    // exclude CSS class
    exclude: ".noE  xl",
    name: "excel Document ",
    filename: "Excel", //do not include extension
    fileext: ".xlsx" // file extension
    });

    }

    const cambiarEntorno = (t) =>{


        let entorno = 'central';
    
        if(t.getAttribute("data-off") == "ARG" ){
            entorno = 'central';
        }else{
            entorno = 'uy';
        }


        $.ajax({
        url: "../Controller/vendedorController.php?accion=cambiarEntorno",
        method: "POST",
        data : {entorno: entorno},
        success: function (data) {
            location.reload();
        }
        });
    }


    const ejecutarAccion = (accion) =>{

        document.querySelector("#boxLoading").classList.add("loading")
        let selectGrupo = $('#selectGrupo').val();
        let selectSucursal = $('#selectSucursal').val();
        let listadoDeSucursales = [];
        let lsitadoDeVendedores = [];

        let allTr = document.querySelector("#tableVb").querySelectorAll("tr")

        allTr.forEach(function(tr) {

            if(tr.querySelectorAll("td")[2].querySelector("input").checked == true){

                lsitadoDeVendedores.push([tr.querySelector("td").textContent, tr.querySelectorAll("td")[1].textContent])
                
            }

        })

        if(selectSucursal.length == 0){
            selectGrupo.forEach(function(opcion) {

                let partes = opcion.split('?');
                partes = partes[1].slice(0, -1);
                let sucursales = partes.split(',')

                sucursales.forEach(element => {

                    if(!listadoDeSucursales.includes(element)){
                        
                        listadoDeSucursales.push(element)

                    }

                });

            });

        }else{
            selectSucursal.forEach(function(opcion) {
                if(!listadoDeSucursales.includes(opcion)){
                        
                    listadoDeSucursales.push(opcion)


                }
            });
        }
        if(lsitadoDeVendedores.length == 0) {
            
            document.querySelector("#boxLoading").classList.remove("loading")

            Swal.fire({
                icon: 'warning',
                title: 'Atención!',
                text: 'Debe seleccionar al menos un vendedor'
            })
            return 1

        }

        if(listadoDeSucursales.length == 0){

            document.querySelector("#boxLoading").classList.remove("loading")

            Swal.fire({
                icon: 'warning',
                title: 'Atención!',
                text: 'Debe seleccionar al menos un local'
            })

            return 1

        }
        
        $.ajax({
            type: "POST",
            url: "../Controller/VendedorController.php?accion="+accion,
            data: {
                sucursalesPorHabilitar: listadoDeSucursales,
                vendedoresPorHabilitar: lsitadoDeVendedores,
            },
            success: function (response) {
                let tabla = document.querySelector("#tableVendedoresBody")
                tabla.innerHTML = '';
                response = JSON.parse(response)
                let allOptions = document.querySelector("#selectSucursal").querySelectorAll("option")
                let arrayParaMostrar = [];
                document.querySelector("#boxLoading").classList.remove("loading")

                response.forEach(element => {
                    let  observacion = '';
                    let  icono = '';

                    if(accion == 'altaVendedores'){

                         observacion =( element[1] == 'ok') ? "Alta exitosa" : "Sin conexion";
                         icono  =( element[1] == 'ok') ? '<i class="bi bi-check-circle" style="color:green"></i>' : '<i class="bi bi-x-circle" style="color:red"></i>';

                    }else{
                        
                         observacion =( element[1] == 'ok') ? "Baja exitosa" : "Sin conexion";
                         icono  =( element[1] == 'ok') ? '<i class="bi bi-check-circle" style="color:green"></i>' : '<i class="bi bi-x-circle" style="color:red"></i>';

                    }


                    allOptions.forEach(sucursal => {
                        if(sucursal.value == element[0] ){
                            arrayParaMostrar.push([sucursal.textContent, observacion, icono])
                        }    

                    });
                    
                    
                });
            

                arrayParaMostrar.forEach(element => {
                    let row = document.createElement("tr")

                    let textHtml =  `
                    <td>${element[0]}</td>
                    <td>${element[1]}</td>
                    <td>${element[2]}</td>`;

                    row.innerHTML = textHtml;
                    tabla.appendChild(row)

                });
                $("#modalTemporadas").modal("toggle")
                setTimeout(() => {
                    // location.reload()
                }, '5000');
            }
        })

    }

    const inhabilitar = () =>{

        console.log("inhabilitar")

    }