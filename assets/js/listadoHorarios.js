const popUpCrearHorario = () => {
    Swal.fire({
        html: `
        <div class="popup-container" style="overflow-x: hidden;">
            <div class="popup-header" style="background-color:#59b9e2;color:white; display: flex; justify-content: space-between;">
                <div class="Comic" style="margin-left:5px"><strong>Nuevo Horario</strong></div>
                <div class="Comic" onclick="cerrarPopup()" style="margin-right:5px">
                    X
                </div>
            </div>
            <div style="border: 1px solid grey; height:130px; margin-top:5px;">
                <div style="margin-top:10px;">
                    <div style="margin-left:10px;"> 
                        <input type="text" style="width: 320px; margin-right:10px; height: 30px;" placeholder="Nombre" id="nombreCrear">
                        <span style="height: 30px;font-size:15px" id="calendarioCrear">
                            Color en Calendario:   &nbsp;<span class="colorPickSelector" id="colorCrear"></span>
                         </span>
                    </div>
                </div>
                <div class="row" style="margin-top:10px;">
                    <div class="col-4 mt-4">
                        <div class="inputOverflow" style="border:solid 1px grey; height: 42.22222px;margin-left:20px;width:100%">
                            <div class="row" style="font-size:13px">
                                <div class="col-8" style="text-align:left">
                                    Hora de entrada
                                </div>
                                <div class="col-2" style="font-size:13px" onclick="sumarHora('hora_entrada', 'totalHoras')">
                                    <i class="bi bi-caret-up"></i>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-8" id="hora_entrada">
                                    9:00
                                </div>
                                <div class="col-2" style="font-size:13px" onclick="restarHora('hora_entrada', 'totalHoras')">
                                    <i class="bi bi-caret-down"></i>
                                </div>  
                            </div>
                        </div>
                    </div>
                    <div class="col-4 mt-4">
                        <div class="inputOverflow" style="border:solid 1px grey; height: 42.22222px;margin-left:20px;width:100%">
                            <div class="row" style="font-size:13px">
                                <div class="col-8" style="text-align:left">
                                    Hora de Salida
                                </div>
                                <div class="col-2" style="font-size:13px" onclick="sumarHora('hora_salida', 'totalHoras')">
                                    <i class="bi bi-caret-up"></i>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-8" id="hora_salida">
                                    9:00
                                </div>
                                <div class="col-2" style="font-size:13px" onclick="restarHora('hora_salida', 'totalHoras')">
                                    <i class="bi bi-caret-down"></i>
                                </div>  
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <strong style="font-size:13px">Total Horas</strong>
                        <div class="inputOverflow" style="border:solid 1px grey; height: 42.22222px;margin-left:20px;width:120px;display: flex; justify-content: center; align-items: center;color:#4ba7ff" id="totalHoras"> 
                    
                        </div>
                    </div>
                </div>
            </div>
            <div style="border: 1px solid grey; height:130px; margin-top:5px;">
                <div style="margin-top:10px;text-align:left;margin-left:20px">
                       <strong> Horario de dos dias </strong>
                </div>
                <div class="row" style="margin-top:10px;">
                    <div class="col-5">
                        <div class="inputOverflow" style="border:solid 1px grey; height: 42.22222px;margin-left:20px;width:100%">
                            <div class="row" style="font-size:13px">
                                <div class="col-8 ml-2" style="text-align:left">
                                    Horario de dos dias
                                </div>
                                <div class="col-2" style="font-size:13px" onclick="cambiarHoraDosDias()">
                                    <i class="bi bi-caret-up"></i>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-8 ml-2" id="horaDosDias">
                                    SI
                                </div>
                                <div class="col-2" style="font-size:13px" onclick="cambiarHoraDosDias()">
                                    <i class="bi bi-caret-down"></i>
                                </div>  
                            </div>
                        </div>
                    </div>
                    <div class="col-5">
                        <div class="inputOverflow" style="border:solid 1px grey; height: 42.22222px;margin-left:20px;width:100%">
                            <div class="row" style="font-size:13px">
                                <div class="col-8 ml-2" style="text-align:left">
                                    Contabilizado
                                </div>
                                <div class="col-2" style="font-size:13px" onclick="cambiarContabilizado()">
                                    <i class="bi bi-caret-up"></i>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-8 ml-2" id="contabilizado">
                                    Primer dia
                                </div>
                                <div class="col-2" style="font-size:13px" onclick="cambiarContabilizado()">
                                    <i class="bi bi-caret-down"></i>
                                </div>  
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>`,
        customClass: {
            popup: 'custom-popup-class'
        },
        heightAuto: false, // Desactiva la altura automática,
        width: '600px', // Establece el ancho del cuadro de diálogo
        showCancelButton: true,
        showConfirmButton: true,
        cancelButtonText: 'Guardar',
        confirmButtonText: 'Cancelar',
        cancelButtonClass: 'btn btn-danger',
        confirmButtonClass: 'btn btn-success'
    })


    document.querySelector(".swal2-actions").style.width = "100%";
    document.querySelector(".swal2-confirm.swal2-styled").style.marginLeft= '50%'

    document.querySelector(".swal2-cancel.swal2-styled").setAttribute("onclick","guardarHorario()");


    document.querySelector(".swal2-confirm.swal2-styled").style.marginLeft= '30%'
    document.querySelector(".swal2-confirm.swal2-styled").textContent = 'Cancelar'
    document.querySelector(".swal2-confirm.swal2-styled").style.backgroundColor = '#b8b9be'
    document.querySelector(".swal2-confirm.swal2-styled").style.borderRadius= '20px'
    
    document.querySelector(".swal2-cancel.swal2-styled").textContent = 'Guardar'
    document.querySelector(".swal2-cancel.swal2-styled").style.borderRadius= '20px'
    document.querySelector(".swal2-cancel.swal2-styled").style.backgroundColor = '#1890ff'


    $(".colorPickSelector").colorPick({
        'initialColor': '#3498db',
        'allowRecent': true,
        'recentMax': 5,
        'allowCustomColor': false,
        'palette': ["#1abc9c", "#16a085", "#2ecc71", "#27ae60", "#3498db", "#2980b9", "#9b59b6", "#8e44ad", "#34495e", "#2c3e50", "#f1c40f", "#f39c12", "#e67e22", "#d35400", "#e74c3c", "#c0392b", "#ecf0f1", "#bdc3c7", "#95a5a6", "#7f8c8d"],
        'onColorSelected': function() {
            this.element.css({'backgroundColor': this.color, 'color': this.color});
        }
    });
    $(".colorPickSelector").css({
        'position': 'absolute',
    });
    
}



const popUpEditarHorario = (div) => {

    let nombre = div.parentElement.querySelectorAll("td")[0].textContent;
    let horaEntrada = div.parentElement.querySelectorAll("td")[1].textContent;
    let horaSalida = div.parentElement.querySelectorAll("td")[2].textContent;
    let horarioDosDias = div.parentElement.querySelectorAll("td")[3].textContent;
    let id = div.parentElement.querySelectorAll("td")[5].textContent;
    let totalHoras = div.parentElement.querySelectorAll("td")[6].textContent;
    let contabilizado = div.parentElement.querySelectorAll("td")[7].textContent;
    let color = div.parentElement.querySelectorAll("td")[8].textContent;

    
    Swal.fire({
        html: `
        <div class="popup-container" style="overflow-x: hidden;">
            <div class="popup-header" style="background-color:#ef8e85;color:white; display: flex; justify-content: space-between;">
                <div class="Comic" style="margin-left:5px"><strong>Modificar Horario</strong></div>
                <div class="Comic" onclick="cerrarPopup()" style="margin-right:5px">
                    X
                </div>
            </div>
            <div style="border: 1px solid grey; height:130px; margin-top:5px;">
                <div style="margin-top:10px;">
                    <div style="margin-left:10px;"> 
                        <input type="text" style="width: 320px; margin-right:10px; height: 30px;" placeholder="Nombre" id="nombreEditar" value='${nombre}'>
                        <span style="height: 30px;font-size:15px" id="calendarioEditar">
                            Color en Calendario:   &nbsp;<span class="colorPickSelector" id="colorEditar"></span>
                        </span>
                     
                    </div>
                </div>
                <div class="row" style="margin-top:10px;">
                    <div class="col-4 mt-4">
                        <div class="inputOverflow" style="border:solid 1px grey; height: 42.22222px;margin-left:20px;width:100%">
                            <div class="row" style="font-size:13px">
                                <div class="col-8" style="text-align:left">
                                    Hora de entrada
                                </div>
                                <div class="col-2" style="font-size:13px" onclick="sumarHora('hora_entrada_e', 'totalHorasE')">
                                    <i class="bi bi-caret-up"></i>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-8" id="hora_entrada_e">
                                    ${horaEntrada}
                                </div>
                                <div class="col-2" style="font-size:13px" onclick="restarHora('hora_entrada_e', 'totalHorasE')">
                                    <i class="bi bi-caret-down"></i>
                                </div>  
                            </div>
                        </div>
                    </div>
                    <div class="col-4 mt-4">
                        <div class="inputOverflow" style="border:solid 1px grey; height: 42.22222px;margin-left:20px;width:100%">
                            <div class="row" style="font-size:13px">
                                <div class="col-8" style="text-align:left">
                                    Hora de Salida
                                </div>
                                <div class="col-2" style="font-size:13px" onclick="sumarHora('hora_salida_e', 'totalHorasE')">
                                    <i class="bi bi-caret-up"></i>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-8" id="hora_salida_e">
                                   ${horaSalida}
                                </div>
                                <div class="col-2" style="font-size:13px" onclick="restarHora('hora_salida_e', 'totalHorasE')">
                                    <i class="bi bi-caret-down"></i>
                                </div>  
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <strong style="font-size:13px">Total Horas</strong>
                        <div class="inputOverflow" style="border:solid 1px grey; height: 42.22222px;margin-left:20px;width:120px;display: flex; justify-content: center; align-items: center;color:#4ba7ff" id="totalHorasE"> 
                            ${totalHoras}
                        </div>
                    </div>
                </div>
            </div>
            <div style="border: 1px solid grey; height:130px; margin-top:5px;">
                <div style="margin-top:10px;text-align:left;margin-left:20px">
                       <strong> Horario de dos dias </strong>
                </div>
                <div class="row" style="margin-top:10px;">
                    <div class="col-5">
                        <div class="inputOverflow" style="border:solid 1px grey; height: 42.22222px;margin-left:20px;width:100%">
                            <div class="row" style="font-size:13px">
                                <div class="col-8 ml-2" style="text-align:left">
                                    Horario de dos dias
                                </div>
                                <div class="col-2" style="font-size:13px" onclick="cambiarHoraDosDias('horaDosDiasE')">
                                    <i class="bi bi-caret-up"></i>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-8 ml-2" id="horaDosDiasE">
                                    ${horarioDosDias}
                                </div>
                                <div class="col-2" style="font-size:13px" onclick="cambiarHoraDosDias('horaDosDiasE')">
                                    <i class="bi bi-caret-down"></i>
                                </div>  
                            </div>
                        </div>
                    </div>
                    <div class="col-5">
                        <div class="inputOverflow" style="border:solid 1px grey; height: 42.22222px;margin-left:20px;width:100%">
                            <div class="row" style="font-size:13px">
                                <div class="col-8 ml-2" style="text-align:left">
                                    Contabilizado
                                </div>
                            <div class="col-2" style="font-size:13px" onclick="cambiarContabilizado('contabilizadoE')">
                                    <i class="bi bi-caret-up"></i>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-8 ml-2" id="contabilizadoE">
                                    ${contabilizado}
                                </div>
                            <div class="col-2" style="font-size:13px" onclick="cambiarContabilizado('contabilizadoE')">
                                    <i class="bi bi-caret-down"></i>
                                </div>  
                            </div>
                            <div id="idEditar" hidden>${id}</div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>`,
        customClass: {
            popup: 'custom-popup-class'
        },
        heightAuto: false, 
        width: '600px', 
        showCancelButton: true,
        showConfirmButton: true,
        showDenyButton: true,
        cancelButtonText: 'Guardar',
        confirmButtonText: 'Cancelar',
        cancelButtonClass: 'btn btn-danger',
        confirmButtonClass: 'btn btn-danger'
    })


    document.querySelector(".swal2-actions").style.width = "100%";

    document.querySelector(".swal2-deny.swal2-styled").style.width = '16%'
    document.querySelector(".swal2-deny.swal2-styled").style.borderRadius = '20px'
    document.querySelector(".swal2-deny.swal2-styled").style.backgroundColor = '#b8b9be'
    document.querySelector(".swal2-deny.swal2-styled").textContent = 'Cancelar'

    document.querySelector(".swal2-confirm.swal2-styled").style.marginLeft= '30%'
    document.querySelector(".swal2-confirm.swal2-styled").textContent = 'Eliminar'
    document.querySelector(".swal2-confirm.swal2-styled").style.backgroundColor = '#e65244'
    document.querySelector(".swal2-confirm.swal2-styled").style.borderRadius= '20px'
    document.querySelector(".swal2-confirm.swal2-styled").setAttribute("onclick",`eliminarHorario(${id})`);
    
    document.querySelector(".swal2-cancel.swal2-styled").textContent = 'Guardar'
    document.querySelector(".swal2-cancel.swal2-styled").style.borderRadius= '20px'
    document.querySelector(".swal2-cancel.swal2-styled").style.backgroundColor = '#1890ff'
    document.querySelector(".swal2-cancel.swal2-styled").setAttribute("onclick","editarHorario()");
    

    $(".colorPickSelector").colorPick({
        'initialColor': '#3498db',
        'allowRecent': true,
        'recentMax': 5,
        'allowCustomColor': false,
        'palette': ["#1abc9c", "#16a085", "#2ecc71", "#27ae60", "#3498db", "#2980b9", "#9b59b6", "#8e44ad", "#34495e", "#2c3e50", "#f1c40f", "#f39c12", "#e67e22", "#d35400", "#e74c3c", "#c0392b", "#ecf0f1", "#bdc3c7", "#95a5a6", "#7f8c8d"],
        'onColorSelected': function() {
            this.element.css({'backgroundColor': this.color, 'color': this.color});
        }
    });
    $(".colorPickSelector").css({
        'position': 'absolute',
    });
    document.getElementById("colorEditar").style.backgroundColor = color;
}



function cerrarPopup() {
    Swal.close(); 
}



function sumarHora(div, total = 'totalHoras') {
    let horaInput = document.getElementById(div);
    let hora = horaInput.textContent.split(":");
    let horaActual = parseInt(hora[0]);
    let nuevaHora = (horaActual + 1) % 24;
    horaInput.textContent = (nuevaHora < 10 ? "0" : "") + nuevaHora + ":00";

    calcularTotalHoras(total);

}

function restarHora(div, total = 'totalHoras') {
    let horaInput = document.getElementById(div);
    let hora = horaInput.textContent.split(":");
    let horaActual = parseInt(hora[0]);
    let nuevaHora = (horaActual - 1 + 24) % 24;
    horaInput.textContent = (nuevaHora < 10 ? "0" : "") + nuevaHora + ":00";
  
    calcularTotalHoras(total);
}

const calcularTotalHoras = (total) => {
    let div = document.getElementById(total);

    let horaEntrada = ''
    let horaSalida = ''
    if(total == 'totalHorasE'){

        horaEntrada = document.getElementById("hora_entrada_e").textContent.split(":");
        horaSalida = document.getElementById("hora_salida_e").textContent.split(":");

    }else{

        horaEntrada = document.getElementById("hora_entrada").textContent.split(":");
        horaSalida = document.getElementById("hora_salida").textContent.split(":");
   }
        

    let horaEntradaInt = parseInt(horaEntrada[0]);
    let horaSalidaInt = parseInt(horaSalida[0]);

    if (horaSalidaInt < horaEntradaInt) {
        horaSalidaInt += 24; // Suma 24 horas al valor de la hora de salida si es menor que la hora de entrada
    }

    let totalHoras = horaSalidaInt - horaEntradaInt;
    div.textContent = totalHoras;
}
 

const cambiarContabilizado = (div = 'contabilizado') => {
    let contabilizado = document.getElementById(div);
    
    if(contabilizado.textContent.trim() == "Primer dia"){
        contabilizado.textContent = "Segundo dia"
    }else{
        contabilizado.textContent = "Primer dia"
    }
}

const cambiarHoraDosDias = (div = 'horaDosDias') => {
    let horaDosDias = document.getElementById(div);
   

    if(horaDosDias.textContent.trim() == "SI"){
        horaDosDias.textContent = "NO"
    }else{
        horaDosDias.textContent = "SI"
    }
}


const guardarHorario = () =>{

    let nombre = document.getElementById("nombreCrear").value.trim();

    let horaDeEntrada = document.getElementById("hora_entrada").textContent.trim();
    let horaDeSalida = document.getElementById("hora_salida").textContent.trim();
    let totalHoras = document.getElementById("totalHoras").textContent.trim();
    
    if(parseInt(totalHoras) <= 0 || totalHoras == ''){
        Swal.fire({
            icon: 'error',
            title: 'El total de horas no puede ser menor a 1',
            showConfirmButton: false,
            timer: 1500
        })
        return 1
    }


    let horarioDosDias = document.getElementById("horaDosDias").textContent.trim();

    if(parseInt(totalHoras) > 12){
        Swal.fire({
            icon: 'error',
            title: 'El total de horas no puede ser mayor a 12 hs',
            showConfirmButton: false,
            timer: 1500
        })
        return 1
    }

    let contabilizado = document.getElementById("contabilizado").textContent.trim();

    let color = document.querySelector("#colorCrear").style.backgroundColor;

    $.ajax({
        url: '../Controller/HorarioController.php?accion=crearHorario',
        method: 'POST',
        data: {
            nombre,
            horaDeEntrada,
            horaDeSalida,
            totalHoras,
            horarioDosDias,
            contabilizado,
            color
        },
        success: (response) => {

            if(response.trim() == 1){
                Swal.fire({
                    icon: 'success',
                    title: 'Horario creado con éxito',
                    showConfirmButton: false,
                    timer: 1500
                })
                setTimeout(() => {
                    location.reload();
                }, 1500);
            }else{
                Swal.fire({
                    icon: 'error',
                    title: 'Error al crear el horario',
                    showConfirmButton: false,
                    timer: 1500
                })
            }
        }
    });


}


const eliminarHorario = (id) => {
    $.ajax({
        url: '../Controller/HorarioController.php?accion=eliminarHorario',
        method: 'POST',
        data: {
            id
        },
        success: (response) => {
            if(response.trim() == 1){
                Swal.fire({
                    icon: 'success',
                    title: 'Horario eliminado con éxito',
                    showConfirmButton: false,
                    timer: 1500
                })
                setTimeout(() => {
                    location.reload();
                }, 1500);
            }else{
                Swal.fire({
                    icon: 'error',
                    title: 'Error al eliminar el horario',
                    showConfirmButton: false,
                    timer: 1500
                })
            }
        }
    })
}


const editarHorario = () => {
    let nombre = document.getElementById("nombreEditar").value.trim();
    let horaDeEntrada = document.getElementById("hora_entrada_e").textContent.trim();
    let horaDeSalida = document.getElementById("hora_salida_e").textContent.trim();
    let totalHoras = document.getElementById("totalHorasE").textContent.trim();
    let horarioDosDias = document.getElementById("horaDosDiasE").textContent.trim();
    let contabilizado = document.getElementById("contabilizadoE").textContent.trim();
    let color = document.querySelector("#colorEditar").style.backgroundColor;
    let id = document.querySelector("#idEditar").textContent.trim();

        
    if(parseInt(totalHoras) <= 0 || totalHoras == ''){
        Swal.fire({
            icon: 'error',
            title: 'El total de horas no puede ser menor a 1',
            showConfirmButton: false,
            timer: 1500
        })
        return 1
    }

    if(parseInt(totalHoras) > 12){
        Swal.fire({
            icon: 'error',
            title: 'El total de horas no puede ser mayor a 12 hs',
            showConfirmButton: false,
            timer: 1500
        })
        return 1
    }

    $.ajax({
        url: '../Controller/HorarioController.php?accion=editarHorario',
        method: 'POST',
        data: {
            nombre,
            horaDeEntrada,
            horaDeSalida,
            totalHoras,
            horarioDosDias,
            contabilizado,
            color,
            id
        },
        success: (response) => {
            if(response.trim() == 1){
                Swal.fire({
                    icon: 'success',
                    title: 'Horario editado con éxito',
                    showConfirmButton: false,
                    timer: 1500
                })
                setTimeout(() => {
                    location.reload();
                }, 1500);
            }else{
                Swal.fire({
                    icon: 'error',
                    title: 'Error al editar el horario',
                    showConfirmButton: false,
                    timer: 1500
                })
            }
        }
    })
}