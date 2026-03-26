
// Agregar estas funciones al final de reporteAnticipos.js

// Funcionalidad del botón de ayuda
function inicializarAyuda() {
    // Manejar el botón de imprimir ayuda
    $('#printHelp').on('click', function() {
        imprimirAyuda();
    });

    // Agregar tooltips a elementos de la interfaz
    agregarTooltipsAyuda();

    // Manejar atajos de teclado para ayuda
    $(document).on('keydown', function(e) {
        // F1 para abrir ayuda
        if (e.key === 'F1') {
            e.preventDefault();
            $('#helpModal').modal('show');
        }
        // Ctrl + H para ayuda rápida
        if (e.ctrlKey && e.key === 'h') {
            e.preventDefault();
            mostrarAyudaRapida();
        }
    });

    // Agregar indicador de ayuda contextual
    agregarIndicadoresAyuda();
}

// Función para imprimir la ayuda
function imprimirAyuda() {
    const contenidoAyuda = document.getElementById('helpModal').cloneNode(true);
    
    // Crear una nueva ventana para imprimir
    const ventanaImpresion = window.open('', '_blank');
    
    ventanaImpresion.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Ayuda - Reporte de Anticipos</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
            <style>
                @media print {
                    .modal { position: static !important; }
                    .modal-dialog { margin: 0 !important; max-width: 100% !important; }
                    .modal-content { border: none !important; box-shadow: none !important; }
                    .modal-header { background-color: #0dcaf0 !important; color: white !important; }
                    .btn-close { display: none !important; }
                    .modal-footer { display: none !important; }
                    .tab-content .tab-pane { display: block !important; page-break-after: always; }
                    .nav-tabs { display: none !important; }
                }
                body { font-size: 12px; }
                h6 { margin-top: 1rem; margin-bottom: 0.5rem; }
            </style>
        </head>
        <body>
            ${contenidoAyuda.outerHTML}
        </body>
        </html>
    `);
    
    ventanaImpresion.document.close();
    
    // Esperar a que cargue y luego imprimir
    setTimeout(() => {
        ventanaImpresion.print();
        ventanaImpresion.close();
    }, 1000);
}

// Agregar tooltips de ayuda a elementos
function agregarTooltipsAyuda() {
    // Tooltip para el selector de período
    $('#periodFilter').attr({
        'data-bs-toggle': 'tooltip',
        'data-bs-placement': 'bottom',
        'title': 'Seleccione un período específico o "Todos los períodos" para ver todos los datos'
    });

    // Tooltip para la búsqueda
    $('#searchBox').attr({
        'data-bs-toggle': 'tooltip',
        'data-bs-placement': 'bottom',
        'title': 'Busque por nombre, apellido, legajo o DNI. Use F1 para más ayuda.'
    });

    // Tooltip para botones de exportación
    $('[data-bs-original-title]').tooltip();
    
    // Inicializar tooltips de Bootstrap
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// Mostrar ayuda rápida con Ctrl+H
function mostrarAyudaRapida() {
    Swal.fire({
        title: '<i class="fas fa-question-circle text-info me-2"></i>Ayuda Rápida',
        html: `
            <div class="text-start">
                <h6><i class="fas fa-keyboard text-primary me-2"></i>Atajos de Teclado:</h6>
                <ul class="list-unstyled">
                    <li><kbd>F1</kbd> - Abrir ayuda completa</li>
                    <li><kbd>Ctrl + H</kbd> - Esta ayuda rápida</li>
                    <li><kbd>Ctrl + E</kbd> - Exportar a Excel</li>
                    <li><kbd>Ctrl + P</kbd> - Exportar a PDF</li>
                </ul>
                
                <h6><i class="fas fa-mouse text-success me-2"></i>Acciones Rápidas:</h6>
                <ul class="list-unstyled">
                    <li>• Haga clic en cualquier columna para ordenar</li>
                    <li>• Use el campo "Buscar" para filtrar datos</li>
                    <li>• Cambie el período para ver otros meses</li>
                </ul>

                <h6><i class="fas fa-lightbulb text-warning me-2"></i>Tips:</h6>
                <ul class="list-unstyled">
                    <li>• Se muestra automáticamente el último período con datos</li>
                    <li>• Los indicadores de color muestran el estado de los períodos</li>
                    <li>• Puede exportar solo los datos filtrados</li>
                </ul>
            </div>
        `,
        width: 600,
        confirmButtonText: '<i class="fas fa-book me-2"></i>Ver Ayuda Completa',
        showCancelButton: true,
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cerrar',
        customClass: {
            confirmButton: 'btn btn-info',
            cancelButton: 'btn btn-secondary'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            $('#helpModal').modal('show');
        }
    });
}

// Agregar indicadores visuales de ayuda
function agregarIndicadoresAyuda() {
    // Agregar icono de ayuda pequeño a elementos importantes
    const elementosConAyuda = [
        {
            selector: '.filters-section',
            texto: 'Presione F1 para ayuda sobre filtros'
        },
        {
            selector: '.dt-buttons',
            texto: 'Ctrl+E para Excel, Ctrl+P para PDF'
        }
    ];

    elementosConAyuda.forEach(item => {
        const elemento = $(item.selector);
        if (elemento.length > 0) {
            elemento.attr({
                'data-bs-toggle': 'tooltip',
                'data-bs-placement': 'top',
                'title': item.texto
            });
        }
    });
}

// Manejar atajos de teclado para exportación
function manejarAtajosExportacion() {
    $(document).on('keydown', function(e) {
        // Ctrl + E para Excel
        if (e.ctrlKey && e.key === 'e') {
            e.preventDefault();
            const excelButton = $('.buttons-excel');
            if (excelButton.length > 0) {
                excelButton[0].click();
                mostrarNotificacion('Exportando a Excel...', 'info');
            }
        }
        
        // Ctrl + P para PDF (interceptar el print del navegador)
        if (e.ctrlKey && e.key === 'p') {
            e.preventDefault();
            const pdfButton = $('.buttons-pdf');
            if (pdfButton.length > 0) {
                pdfButton[0].click();
                mostrarNotificacion('Exportando a PDF...', 'info');
            }
        }
    });
}

// Mostrar notificaciones de ayuda
function mostrarNotificacion(mensaje, tipo = 'info', duracion = 3000) {
    const iconos = {
        'info': 'fas fa-info-circle',
        'success': 'fas fa-check-circle',
        'warning': 'fas fa-exclamation-triangle',
        'error': 'fas fa-times-circle'
    };

    const colores = {
        'info': 'bg-info',
        'success': 'bg-success', 
        'warning': 'bg-warning',
        'error': 'bg-danger'
    };

    // Crear toast de notificación
    const toast = $(`
        <div class="toast align-items-center text-white ${colores[tipo]} border-0" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 9999;">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="${iconos[tipo]} me-2"></i>${mensaje}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `);

    $('body').append(toast);
    
    // Mostrar toast
    const bsToast = new bootstrap.Toast(toast[0], { delay: duracion });
    bsToast.show();
    
    // Remover del DOM después de ocultarse
    toast[0].addEventListener('hidden.bs.toast', function() {
        toast.remove();
    });
}

// Tour guiado para nuevos usuarios
function iniciarTourGuiado() {
    const pasos = [
        {
            elemento: '#periodFilter',
            titulo: 'Selector de Período',
            texto: 'Aquí puede cambiar el período a mostrar. Por defecto se muestra el último con datos.'
        },
        {
            elemento: '#searchBox',
            titulo: 'Búsqueda',
            texto: 'Use este campo para buscar empleados específicos por nombre, legajo o DNI.'
        },
        {
            elemento: '.dt-buttons',
            titulo: 'Exportación',
            texto: 'Estos botones le permiten descargar los datos en Excel o PDF.'
        },
        {
            elemento: '[data-bs-target="#periodosModal"]',
            titulo: 'Administrar Períodos',
            texto: 'Aquí puede configurar las fechas de anticipo y vigencias para cada período.'
        }
    ];

    let pasoActual = 0;

    function mostrarPaso(indice) {
        if (indice >= pasos.length) {
            Swal.fire({
                icon: 'success',
                title: '¡Tour Completado!',
                text: 'Ya conoce las funciones principales. Use F1 para acceder a la ayuda completa.',
                timer: 3000
            });
            return;
        }

        const paso = pasos[indice];
        const elemento = $(paso.elemento);

        if (elemento.length > 0) {
            // Resaltar elemento
            elemento.addClass('highlight-tour');
            
            Swal.fire({
                title: paso.titulo,
                text: paso.texto,
                showCancelButton: true,
                confirmButtonText: indice < pasos.length - 1 ? 'Siguiente' : 'Finalizar',
                cancelButtonText: 'Saltar Tour',
                allowOutsideClick: false,
                customClass: {
                    confirmButton: 'btn btn-primary',
                    cancelButton: 'btn btn-secondary'
                }
            }).then((result) => {
                elemento.removeClass('highlight-tour');
                
                if (result.isConfirmed) {
                    mostrarPaso(indice + 1);
                }
            });
        } else {
            mostrarPaso(indice + 1);
        }
    }

    mostrarPaso(0);
}

// CSS para el tour guiado
const tourStyles = `
<style>
.highlight-tour {
    position: relative;
    z-index: 1000;
    box-shadow: 0 0 20px rgba(0, 123, 255, 0.8) !important;
    border: 2px solid #007bff !important;
    border-radius: 0.375rem !important;
    animation: pulse-highlight 2s infinite;
}

@keyframes pulse-highlight {
    0% { box-shadow: 0 0 20px rgba(0, 123, 255, 0.8); }
    50% { box-shadow: 0 0 30px rgba(0, 123, 255, 1); }
    100% { box-shadow: 0 0 20px rgba(0, 123, 255, 0.8); }
}
</style>
`;

// Agregar estilos del tour al head
$('head').append(tourStyles);

// Inicializar toda la funcionalidad de ayuda cuando el documento esté listo
$(document).ready(function() {
    // Esperar a que el sistema principal esté cargado
    setTimeout(() => {
        inicializarAyuda();
        manejarAtajosExportacion();
        
        // Mostrar tour guiado si es la primera visita (opcional)
        const primeraVisita = localStorage.getItem('reporteAnticipos_primeraVisita');
        if (!primeraVisita) {
            setTimeout(() => {
                Swal.fire({
                    title: '¡Bienvenido!',
                    text: '¿Le gustaría hacer un tour rápido por las funciones principales?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, mostrar tour',
                    cancelButtonText: 'No, continuar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        iniciarTourGuiado();
                    }
                    localStorage.setItem('reporteAnticipos_primeraVisita', 'true');
                });
            }, 2000);
        }
    }, 1000);
});