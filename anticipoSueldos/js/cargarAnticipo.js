

// cargarAnticipo.js

$(document).ready(function() {
    $('#adelantoForm').on('submit', async function(e) {
        e.preventDefault();
        
      
    });
});

const obtenerFechaDesdeTexto = (idElemento) => {
    const elemento = document.getElementById(idElemento);
    if (!elemento) {
        console.error(`Error: No se encontró el elemento ${idElemento}`);
        return null;
    }
    const textoFecha = elemento.textContent.trim();
    if (!textoFecha) {
        console.error(`Error: El elemento ${idElemento} no tiene texto`);
        return null;
    }
    console.log(`${idElemento} actualizado:`, textoFecha);
    
    const partes = textoFecha.split(' ');
    const fechaPartes = partes[0].split('/');
    if (fechaPartes.length !== 3) {
        console.error(`Error en formato de fecha: ${textoFecha}`);
        return null;
    }
    
    return new Date(`${fechaPartes[2]}-${fechaPartes[1]}-${fechaPartes[0]}T${partes[1] || '00:00'}:00`);
};

const cargarAnticipo = () => {


    const formData = [];
    let hasErrors = false;
    const table = $('#registrosTable').DataTable();
    
    // Recolectar datos de la tabla
    table.rows().every(function() {
        const rowData = this.data();
        const row = $(this.node());
        const importe = row.find('.importe-input').val();
        const dni = row.find('.dni-input').val().trim();
        
        if (!row.find('.dni-input').hasClass('is-valid')) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Debe validar el DNI antes de continuar'
            });
            hasErrors = true;
            return false;
        }
        
        if (!importe || importe === '$0') {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Debe ingresar un importe válido'
            });
            hasErrors = true;
            return false;
        }

        formData.push({
            legajo: rowData[0],
            nombre: rowData[1],
            dni: dni,
            importe: importe
        });
    });

    if (hasErrors || formData.length === 0) {
        if (formData.length === 0) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No hay registros para procesar'
            });
        }
        return;
    }

    // Mostrar spinner
    // Swal.fire({
    //     title: 'Procesando',
    //     text: 'Por favor espere...',
    //     allowOutsideClick: false,
    //     allowEscapeKey: false,
    //     showConfirmButton: false,
    //     didOpen: () => {
    //       //  Swal.showLoading();
    //     }
    // });
    
    try {
        
        const fechaInicio = obtenerFechaDesdeTexto('vigDesde');
        const fechaFin = obtenerFechaDesdeTexto('vigHasta');
        const fechaDeposito = obtenerFechaDesdeTexto('fechaDeposito');
        const fechaActual = new Date();
        
        if (!fechaInicio || !fechaFin || !fechaDeposito) {
            console.error("Error: No se pudieron obtener correctamente las fechas.");
            Swal.fire({
                icon: 'error',
                title: 'Error de fecha',
                text: 'No se pudieron obtener las fechas correctamente. Verifique el formato.'
            });
            return;
        }

        if (fechaActual < fechaInicio || fechaActual > fechaFin) {
            
            Swal.fire({
                icon: 'warning',
                title: 'Período cerrado',
                text: 'El período para solicitar anticipos se encuentra cerrado'
            });

            return 1;
        }    

        // Primera validación
        $.ajax({
            url: 'Controller/validarAnticipo.php',
            method: 'POST',
            dataType: 'json',
            data: { registros: JSON.stringify(formData) },
            success: function (data) {

                // confirmar accion
                Swal.fire({
                    title: 'Confirmar acción',
                    text: '¿Desea confirmar el registro de los anticipos?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sí',
                    cancelButtonText: 'No'
                }).then((result) => {
                    if (result.isConfirmed) {
                        guardarAnticipo(formData);
                    }
                });

            },
            error: function (error) {
                let validacionResponse = error
                console.log('Respuesta validación:', validacionResponse.responseJSON);
        
                if (!validacionResponse.responseJSON.success) {
                    let errorTitle = 'Error de Validación';
                    let errorMessage = validacionResponse.responseJSON.message || 'Error desconocido';
                    
                    // Verificar si es un anticipo duplicado
                    if (validacionResponse.responseJSON.message && validacionResponse.responseJSON.message.includes('Ya existe un anticipo')) {
                        errorTitle = 'Anticipo Duplicado';
                        // El mensaje ya viene formateado del servidor, solo reemplazamos los saltos de línea
                        errorMessage = validacionResponse.responseJSON.message.replace(/\n/g, '<br>');
                    }
                    
                    Swal.fire({
                        icon: 'error',
                        title: errorTitle,
                        html: errorMessage,
                        confirmButtonText: 'Entendido'
                    });
                    return;
                }
            }
        });

        
    } catch (error) {
        console.error('Error completo:', error);
        
        let errorTitle = 'Error';
        let errorMessage = '';
        
        // Intentar obtener el mensaje del error
        if (error.responseJSON && error.responseJSON.message) {
            errorMessage = error.responseJSON.message;
        } else if (error.message) {
            errorMessage = error.message;
        } else {
            errorMessage = 'Ya existe un anticipo para el empleado en el periodo actual';
        }
        
        // Formatear el mensaje si es un anticipo duplicado
        if (errorMessage.includes('Ya existe un anticipo')) {
            errorTitle = 'Anticipo Duplicado';
            errorMessage = errorMessage.replace(/\n/g, '<br>');
        }
        
         Swal.fire({
            icon: 'error',
            title: errorTitle,
            html: errorMessage,
            confirmButtonText: 'Entendido'
        });
    } 
    
}

const guardarAnticipo = (formData) =>{
    $.ajax({
        url: 'Controller/guardarAnticipo.php',
        method: 'POST',
        dataType: 'json',
        data: { registros: JSON.stringify(formData) },
        success: function (saveResponse) {
           
            if (saveResponse.success) {
                Swal.fire({
                   icon: 'success',
                   title: 'Éxito',
                   text: 'Los anticipos fueron registrados correctamente',
                   confirmButtonText: 'Aceptar'
               }).then((result) => {
                   location.reload();
               });
           }
        },
        error: function (error) {
            console.log(error);
            let text = error.responseJSON.message || 'Error al guardar los datos';

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: text,
                confirmButtonText: 'Aceptar'
            });
            

            throw new Error(error.responseText.message || 'Error al guardar los datos');
        }
        });
}