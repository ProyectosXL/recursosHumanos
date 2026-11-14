
// Variables globales
let timeoutId;
// Rutas desde PHP (partials/config_js.php): no se escriben a mano en el JS
const BASE_IMAGE_PATH = window.ESTRUCTURA.fotosUrl;
const EXCEL_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;charset=UTF-8';
const EXCEL_EXTENSION = '.xlsx';

// Funciones de utilidad
function getInitials(name) {
    if (!name || name === 'Sin Nombre') return 'SN';
    return name.split(' ')
        .map(word => word[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

function isNewEmployee(fecha_modif) {
    if (!fecha_modif) return false;
    const fechaModif = new Date(fecha_modif);
    const hoy = new Date();
    const diferenciaDias = Math.floor((hoy - fechaModif) / (1000 * 60 * 60 * 24));
    return diferenciaDias <= 7;
}

// Inicialización de componentes
function initializeComponents() {
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(tooltip => {
        new bootstrap.Tooltip(tooltip);
    });
}

// Funciones de tabla
function filterTable(searchValue) {
    const $rows = $('#listContainer tr');
    searchValue = searchValue.toLowerCase();
    
    $rows.each(function() {
        const $row = $(this);
        const text = $row.text().toLowerCase();
        $row.toggle(text.includes(searchValue));
    });
}

function exportToExcel() {
    try {
        const table = document.getElementById('empleadosTable');
        const ws = XLSX.utils.table_to_sheet(table);
        
        // Ajustar anchos de columna
        ws['!cols'] = [
            { wch: 30 }, // Nombre y Apellido
            { wch: 20 }, // Sector
            { wch: 10 }, // Interno
            { wch: 35 }, // Email
            { wch: 15 }  // Celular
        ];

        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Internos');

        const excelBuffer = XLSX.write(wb, { bookType: 'xlsx', type: 'array' });
        const data = new Blob([excelBuffer], { type: EXCEL_TYPE });
        
        const fecha = new Date().toISOString().slice(0,10);
        const fileName = `internos_${fecha}${EXCEL_EXTENSION}`;
        
        saveAs(data, fileName);
        mostrarNotificacion('Datos exportados exitosamente', 'success');
    } catch (error) {
        console.error('Error al exportar:', error);
        mostrarNotificacion('Error al exportar los datos', 'error');
    }
}

// Funciones de renderizado
function createProfileCard(empleado) {
    const nombre = empleado.nombre_apellido || 'Sin Nombre';
    const sector = empleado.sector || 'Sin Sector';
    const mail = empleado.mail || '';
    const interno = empleado.interno || '';
    const fotoUrl = `${BASE_IMAGE_PATH}${empleado.nombre_apellido.replace(" ", "_")}.jpg`;
    const esNuevo = isNewEmployee(empleado.fecha_modif) && empleado.inactivo == 0;

    return `
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="profile-card">
                ${esNuevo ? `
                    <div class="new-employee-badge" data-bs-toggle="tooltip" data-bs-placement="top" title="Nueva incorporación">
                        <i class="bi bi-star-fill"></i>
                        <span class="pulse"></span>
                    </div>
                ` : ''}
                <div class="profile-image">
                    <img src="${fotoUrl}" 
                         alt="${nombre}" 
                         onerror="this.onerror=null; this.src=''; this.parentNode.innerHTML='${getInitials(nombre)}';"
                         onload="this.classList.add('loaded')"
                         class="profile-img">
                </div>
                <h5 class="mb-3">${nombre}</h5>
                <p class="sector mb-2">
                    <i class="bi bi-building"></i> ${sector}
                </p>
                ${mail ? 
                    `<div class="email-container mb-2">
                        <a href="mailto:${mail}" class="text-decoration-none text-primary">
                            <i class="bi bi-envelope"></i> ${mail}
                        </a>
                        <button class="btn btn-link btn-sm p-0" 
                                onclick="event.preventDefault(); copiarEmail('${mail}');" 
                                data-bs-toggle="tooltip"
                                data-bs-placement="top"
                                data-bs-title="Copiar email">
                            <i class="bi bi-clipboard"></i>
                        </button>
                     </div>` : ''
                }
                ${interno ? 
                    `<p class="interno mb-3">
                        <i class="bi bi-telephone"></i> Int: ${interno}
                    </p>` : ''
                }
                ${mail ? 
                    `<a href="mailto:${mail}" class="contact-btn">
                        <i class="bi bi-send"></i> Contactar
                    </a>` : 
                    `<button class="contact-btn disabled" disabled>
                        <i class="bi bi-send"></i> Sin contacto
                    </button>`
                }
            </div>
        </div>
    `;
}

function renderListView(empleado) {
    const nombre = empleado.nombre_apellido || 'Sin Nombre';
    const sector = empleado.sector || 'Sin Sector';
    const interno = empleado.interno || '-';
    const mail = empleado.mail || '';
    const celular = empleado.celular || '-';
    const esNuevo = isNewEmployee(empleado.fecha_modif) && empleado.inactivo == 0;

    return `
        <tr class="${esNuevo ? 'new-employee-row' : ''}">
            <td>
                ${esNuevo ? '<i class="bi bi-star-fill text-warning me-2"></i>' : ''}
                ${nombre}
            </td>
            <td>${sector}</td>
            <td>${interno}</td>
            <td>
                ${mail ? 
                    `<div class="d-flex align-items-center gap-2">
                        <a href="mailto:${mail}" class="text-decoration-none text-primary">
                            ${mail}
                        </a>
                        <button class="btn btn-link btn-sm p-0" 
                                onclick="copiarEmail('${mail}')"
                                data-bs-toggle="tooltip"
                                data-bs-placement="top"
                                data-bs-title="Copiar email">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>` : 
                    '-'
                }
            </td>
            <td>${celular}</td>
        </tr>
    `;
}

// Funciones de manejo de vistas
function cambiarVista(vista) {
    const $cardsContainer = $('#cardsContainer');
    const $listView = $('#listView');
    
    if (vista === 'cards') {
        $listView.fadeOut(200, function() {
            $cardsContainer.fadeIn(200);
        });
    } else {
        $cardsContainer.fadeOut(200, function() {
            $listView.fadeIn(200);
        });
    }
}

// Funciones de datos y UI
function cargarDatos(busqueda = '', sector = '') {
    $.ajax({
        url: window.ESTRUCTURA.internosAjax,
        method: 'POST',
        data: {
            action: 'buscar',
            busqueda: busqueda,
            sector: sector
        },
        success: function(response) {
            if (Array.isArray(response)) {
                const currentView = $('.btn-toggle.active').data('view');
                let html = '';
                
                if (currentView === 'cards') {
                    response.forEach(empleado => {
                        html += createProfileCard(empleado);
                    });
                    $('#cardsContainer').html(html || '<div class="col-12 text-center"><p>No se encontraron resultados</p></div>');
                } else {
                    response.forEach(empleado => {
                        html += renderListView(empleado);
                    });
                    $('#listContainer').html(html || '<tr><td colspan="5" class="text-center">No se encontraron resultados</td></tr>');
                }
                
                cambiarVista(currentView);
                initializeComponents();
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX:', error);
            mostrarNotificacion('Error al cargar los datos', 'error');
        }
    });
}

function copiarEmail(email) {
    const button = document.querySelector(`[data-bs-title="Copiar email"]`);
    const tooltip = bootstrap.Tooltip.getInstance(button);
    
    if (tooltip) {
        tooltip.dispose();
    }

    try {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(email).then(() => {
                mostrarNotificacion('Email copiado al portapapeles', 'success');
            });
        } else {
            const tempInput = document.createElement('input');
            tempInput.style.position = 'absolute';
            tempInput.style.left = '-9999px';
            tempInput.value = email;
            document.body.appendChild(tempInput);
            tempInput.select();
            tempInput.setSelectionRange(0, 99999);
            document.execCommand('copy');
            document.body.removeChild(tempInput);
            mostrarNotificacion('Email copiado al portapapeles', 'success');
        }
    } catch (err) {
        mostrarNotificacion('No se pudo copiar el email', 'error');
    }

    new bootstrap.Tooltip(button);
}

function mostrarNotificacion(mensaje, tipo = 'success') {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });

    Toast.fire({
        icon: tipo,
        title: mensaje
    });
}

// Inicialización y eventos
$(document).ready(function() {
    cargarDatos();
    initializeComponents();
    
    const $searchInput = $('#searchInput');
    const $clearSearch = $('#clearSearch');
    const $tableSearchInput = $('#tableSearchInput');
    const $clearTableSearch = $('#clearTableSearch');

    // Toggle entre vistas
    $('.btn-toggle').click(function() {
        if (!$(this).hasClass('active')) {
            $('.btn-toggle').removeClass('active');
            $(this).addClass('active');
            cargarDatos($searchInput.val(), $('.list-group-item.active').data('sector') || '');
        }
    });

    // Búsqueda general
    $searchInput.on('input', function() {
        const searchValue = $(this).val();
        $clearSearch.toggle(searchValue.length > 0);
        
        if (timeoutId) {
            clearTimeout(timeoutId);
        }
        
        const selectedSector = $('.list-group-item.active').data('sector') || '';
        
        timeoutId = setTimeout(() => {
            cargarDatos(searchValue, selectedSector);
        }, 300);
    });

    // Limpiar búsqueda general
    $clearSearch.on('click', function() {
        $searchInput.val('');
        $(this).hide();
        const selectedSector = $('.list-group-item.active').data('sector') || '';
        cargarDatos('', selectedSector);
        $searchInput.focus();
    });

    // Búsqueda en tabla
    $tableSearchInput.on('input', function() {
        const searchValue = $(this).val();
        $clearTableSearch.toggle(searchValue.length > 0);
        filterTable(searchValue);
    });

    // Limpiar búsqueda en tabla
    $clearTableSearch.on('click', function() {
        $tableSearchInput.val('').focus();
        $(this).hide();
        filterTable('');
    });

    // Filtro por sector
    $('.list-group-item').click(function() {
        $('.list-group-item').removeClass('active');
        $(this).addClass('active');
        const searchValue = $searchInput.val();
        const selectedSector = $(this).data('sector') || '';
        cargarDatos(searchValue, selectedSector);
    });

    // Exportar a Excel
    $('#exportExcel').on('click', exportToExcel);
});