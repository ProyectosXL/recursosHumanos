
// En mostrarDatos.js
function cargarDatosPeriodo() {
    $.ajax({
        url: 'Controller/getPeriodoActual.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            console.log('Respuesta del servidor:', response);
            
            if (response.success && response.periodo) {
                // Asegurarnos que los elementos existen antes de actualizarlos
                const fechaDeposito = $('#fechaDeposito');
                const vigDesde = $('#vigDesde');
                const vigHasta = $('#vigHasta');

                if (fechaDeposito.length) {
                    fechaDeposito.text(response.fechaAnticipo || '');
                    console.log('Fecha depósito actualizada:', response.fechaAnticipo);
                }

                if (vigDesde.length) {
                    vigDesde.text(response.vigDesde || '');
                    console.log('Vigencia desde actualizada:', response.vigDesde);
                }

                if (vigHasta.length) {
                    vigHasta.text(response.vigHasta || '');
                    console.log('Vigencia hasta actualizada:', response.vigHasta);
                }
            } else {
                console.log('No se encontraron datos del período o la respuesta no fue exitosa');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error al cargar datos del período:', {
                status: status,
                error: error,
                response: xhr.responseText
            });
        }
    });
}

$(document).ready(function() {
    cargarDatosPeriodo();
    // Initialize Select2
    $('#empleadoSelect').select2({
        ajax: {
            url: 'Controller/getEmpleados.php',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    search: params.term
                };
            },
            processResults: function (data) {
                return {
                    results: data.map(function(item) {
                        return {
                            id: item.NRO_LEGAJO,
                            text: item.APELLIDO_Y_NOMBRE,
                            dni: item.NRO_DOCUMENTO
                        };
                    })
                };
            },
            cache: true
        },
        minimumInputLength: 3,
        language: {
            inputTooShort: function() {
                return "Ingrese al menos 3 caracteres";
            },
            searching: function() {
                return "Buscando...";
            },
            noResults: function() {
                return "No se encontraron resultados";
            }
        },
        placeholder: 'Buscar empleado...',
        allowClear: true
    });

    // Initialize DataTable
    const table = $('#registrosTable').DataTable({
        language: {
            "sProcessing":     "Procesando...",
            "sLengthMenu":     "Mostrar _MENU_ registros",
            "sZeroRecords":    "No se encontraron resultados",
            "sEmptyTable":     "Ningún dato disponible en esta tabla",
            "sInfo":           "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
            "sInfoEmpty":      "Mostrando registros del 0 al 0 de un total de 0 registros",
            "sInfoFiltered":   "(filtrado de un total de _MAX_ registros)",
            "sInfoPostFix":    "",
            "sSearch":         "Buscar:",
            "sUrl":           "",
            "sInfoThousands":  ",",
            "sLoadingRecords": "Cargando...",
            "oPaginate": {
                "sFirst":    "Primero",
                "sLast":     "Último",
                "sNext":     "Siguiente",
                "sPrevious": "Anterior"
            },
            "oAria": {
                "sSortAscending":  ": Activar para ordenar la columna de manera ascendente",
                "sSortDescending": ": Activar para ordenar la columna de manera descendente"
            },
            "buttons": {
                "copy": "Copiar",
                "colvis": "Visibilidad"
            }
        },
        ordering: false,
        searching: false,
        paging: false,
        info: false,
        columnDefs: [
            { width: "10%", targets: 0 }, // Legajo
            { width: "30%", targets: 1 }, // Nombre y Apellido
            { width: "25%", targets: 2 }, // DNI
            { width: "20%", targets: 3 }, // Importe
            { width: "10%", targets: 4 }  // Acciones
        ]
    });

    // Handle employee selection
    $('#empleadoSelect').on('select2:select', function(e) {
        const data = e.params.data;

        // Manejar el evento click para eliminar fila
        $('#registrosTable tbody').on('click', '.delete-row', function() {
            const row = $(this).closest('tr');
            table.row(row).remove().draw(false);
        });

        // Add new row to table
        const newRow = table.row.add([
            data.id,
            data.text,
            `<div class="input-group">
                <input type="text" class="form-control dni-input" placeholder="Ingrese DNI" style="min-width: 150px;">
                <button class="btn btn-primary btn-sm validate-dni">
                    <i class="fas fa-check me-1"></i>Validar
                </button>
                <div class="invalid-feedback">DNI incorrecto</div>
            </div>`,
            `<input type="text" class="form-control importe-input" placeholder="$0" disabled>`,
            `<button type="button" class="btn btn-danger btn-sm delete-row">
                <i class="fas fa-trash-alt"></i>
            </button>`
        ]).draw().node();

        // Agregar el manejador para la validación del DNI
        $('#registrosTable').on('click', '.validate-dni', function() {
            const row = $(this).closest('tr');
            const dniInput = row.find('.dni-input');
            const importeInput = row.find('.importe-input');
            const dniValue = dniInput.val().trim();
            const legajo = table.row(row).data()[0];
            
            // Agregar el spinner mientras valida
            const button = $(this);
            const originalContent = button.html();
            button.html('<i class="fas fa-spinner fa-spin"></i> Validando...').prop('disabled', true);

            // Llamada AJAX para validar el DNI
            $.ajax({
                url: 'Controller/validarDNI.php',
                method: 'POST',
                data: {
                    dni: dniValue,
                    legajo: legajo
                },
                success: function(response) {
                    if (response.valid) {
                        dniInput.removeClass('is-invalid').addClass('is-valid');
                        importeInput.prop('disabled', false);
                        // Inicializar Cleave.js para el importe una vez habilitado
                        new Cleave(importeInput[0], {
                            numeral: true,
                            numeralThousandsGroupStyle: 'thousand',
                            prefix: '$ ',
                            numeralDecimalScale: 0
                        });
                    } else {
                        dniInput.removeClass('is-valid').addClass('is-invalid');
                        importeInput.prop('disabled', true).val('');
                        row.find('.invalid-feedback').show();
                    }
                },
                error: function() {
                    dniInput.addClass('is-invalid');
                    importeInput.prop('disabled', true).val('');
                    row.find('.invalid-feedback').text('Error al validar DNI').show();
                },
                complete: function() {
                    button.html(originalContent).prop('disabled', false);
                }
            });
        });
    });
});