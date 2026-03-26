$(document).ready(function() {

    // Verificar si es Casa Central y mostrar selector de sector
    const isCasaCentral = $('#numSucursal').text().trim() === 'Casa Central';
    if (isCasaCentral) {
        $('#sectorSelector').show();
    }
    let nroSucursal = document.querySelector('#numSucursal').getAttribute('attr-numSucursal');

    const table = $('#empleadosTable').DataTable({
        ajax: {
            url: 'Controller/getEmpleadosGrupo.php?sucursal=' + nroSucursal,
            type: 'GET',
            data: function(d) {
                return {
                    ...d,
                    sector: $('#sector').val()
                };
            },
            dataSrc: function(json) {
                // Asegurar que siempre devolvemos un array
                return json.data || [];
            }
        },
        columns: [
            { data: 'NRO_LEGAJO' },
            { data: 'APELLIDO_Y_NOMBRE' },
            {
                data: 'IMPORTE',
                render: function(data) {
                    if (data && data !== '0') {
                        return `<input type="text" class="form-control importe-input" value="$ ${data}" disabled>`;
                    }
                    return `<input type="text" class="form-control importe-input" value="$ 0">`;
                }
            }
        ],
        language: {
            "sProcessing": "Procesando...",
            "sLengthMenu": "Mostrar MENU registros",
            "sZeroRecords": "No se encontraron resultados",
            "sEmptyTable": "Ningún dato disponible en esta tabla",
            "sInfo": "Mostrando registros del START al END de un total de TOTAL registros",
            "sInfoEmpty": "Mostrando registros del 0 al 0 de un total de 0 registros",
            "sInfoFiltered": "(filtrado de un total de MAX registros)",
            "sSearch": "Buscar:",
            "sLoadingRecords": "Cargando..."
        },
        processing: true,
        pageLength: 50,
        order: [[1, 'asc']]
    });

    $('#sector').on('change', function() {
        table.ajax.reload();
    });

    table.on('draw', function() {
        $('.importe-input').each(function() {
            let inputValue = $(this).val().replace(/\$|\s|,/g, '').trim(); // Eliminamos el signo $ y espacios
    
            new Cleave(this, {
                numeral: true,
                numeralThousandsGroupStyle: 'thousand',
                prefix: '$ ',
                numeralDecimalScale: 0
            });
    
            if (inputValue !== '' && parseInt(inputValue) > 0) {
                $(this).prop('disabled', true);
            } else {
                $(this).prop('disabled', false).val('$ 0'); // Valor por defecto cuando es 0 o vacío
            }
        });
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

const cargarAnticipoGrupo = () => {
    let valid = false;
    let dataToSend = [];

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
        return;
    }    

    $('#empleadosTable tbody tr').each(function () {
        let row = $(this);
        let legajo = row.find('td:first').text().trim();
        let nombre = row.find('td:eq(1)').text().trim();
        let input = row.find('.importe-input:not(:disabled)');
        let valor = input.val() ? input.val().replace(/\$|\s|,/g, '').trim() : '';

        if (valor !== '' && parseInt(valor) > 0) {
            valid = true;
            dataToSend.push({
                legajo: legajo,
                nombre: nombre,
                importe: valor
            });

            input.prop('disabled', true);
        }
    });

    if (!valid) {
        Swal.fire({
            icon: 'warning',
            title: 'Validación',
            text: 'Debe haber al menos un registro con un importe válido antes de enviar.'
        });
        return;
    }
  
    // confirmar accion
    Swal.fire({
        title: 'Confirmar',
        text: '¿Desea guardar los anticipos?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí',
        cancelButtonText: 'No'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'Controller/guardarAnticipo.php',
                type: 'POST',
                data: JSON.stringify(dataToSend),
                contentType: 'application/json',
                success: function (response) {
                    Swal.fire({
                        icon: response.success ? 'success' : 'error',
                        title: response.success ? 'Éxito' : 'Error',
                        text: response.message
                    }).then(() => {
                        if (response.success) location.reload();
                    });
                },
                error: function () {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Hubo un problema al guardar los datos'
                    });
                }
            });
        }else{
            Swal.fire({
                icon: 'info',
                title: 'Operación cancelada',
                text: 'No se guardaron los anticipos'
            });

            $('#empleadosTable tbody tr').each(function () {
                let row = $(this);
                let input = row.find('.importe-input:disabled');
                input.prop('disabled', false);
            });
            
        }
    });
   
};
