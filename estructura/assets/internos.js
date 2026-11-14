/*
 * Internos (Directorio de Personal) - vista pública.
 * Migrado desde comercial/recepcion/js/interno.js: hace lo mismo (búsqueda, sector, tarjetas y
 * lista, fotos, copiar mail, exportar a Excel con SheetJS). Cambios:
 *   - el sector sale del <select> que está arriba de los resultados (antes, una lista en la barra lateral);
 *   - la búsqueda inicial sale del input, que PHP llena con ?q= ("Ver en Internos" de la Guía);
 *   - la URL del ajax llega de PHP (window.ESTRUCTURA) y la foto la resuelve el servidor;
 *   - los textos se escapan antes de armar el HTML, y el mail va en data-* (no dentro de onclick).
 */
let timeoutId;
const EXCEL_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;charset=UTF-8';
const EXCEL_EXTENSION = '.xlsx';

function esc(valor) {
    return String(valor == null ? '' : valor)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function getInitials(name) {
    if (!name || name === 'Sin Nombre') return 'SN';
    return name.split(' ')
        .filter(word => word)
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

function sectorElegido() {
    return $('#sectorSelect').val() || '';
}

function vistaActual() {
    return $('.btn-toggle.active').data('view');
}

function initializeComponents() {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
        bootstrap.Tooltip.getOrCreateInstance(el);
    });
}

function filterTable(searchValue) {
    const valor = searchValue.toLowerCase();
    $('#listContainer tr').each(function () {
        $(this).toggle($(this).text().toLowerCase().includes(valor));
    });
}

function exportToExcel() {
    try {
        const table = document.getElementById('empleadosTable');
        const ws = XLSX.utils.table_to_sheet(table);
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
        const fecha = new Date().toISOString().slice(0, 10);
        saveAs(data, `internos_${fecha}${EXCEL_EXTENSION}`);
        mostrarNotificacion('Datos exportados exitosamente', 'success');
    } catch (error) {
        console.error('Error al exportar:', error);
        mostrarNotificacion('Error al exportar los datos', 'error');
    }
}

function botonCopiar(mail) {
    return `<button type="button" class="btn btn-link btn-sm p-0 btn-copiar" data-mail="${esc(mail)}"
                    data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="Copiar email"
                    aria-label="Copiar el mail ${esc(mail)}">
                <i class="bi bi-clipboard" aria-hidden="true"></i>
            </button>`;
}

function createProfileCard(empleado) {
    const nombre = empleado.nombre_apellido || 'Sin Nombre';
    const sector = empleado.sector || 'Sin Sector';
    const mail = empleado.mail || '';
    const interno = empleado.interno || '';
    // La foto la resuelve el servidor (Interno::fotoUrl): si no hay, no se pide nada y no hay 404
    const fotoUrl = empleado.foto || '';
    const esNuevo = isNewEmployee(empleado.fecha_modif) && empleado.inactivo == 0;
    const iniciales = esc(getInitials(nombre));

    return `
        <div class="col-md-6 col-xl-4 mb-4">
            <div class="profile-card">
                ${esNuevo ? `
                    <div class="new-employee-badge" data-bs-toggle="tooltip" data-bs-placement="top" title="Nueva incorporación">
                        <i class="bi bi-star-fill" aria-hidden="true"></i>
                        <span class="visually-hidden">Nueva incorporación</span>
                        <span class="pulse"></span>
                    </div>
                ` : ''}
                <div class="profile-image" aria-hidden="true">
                    ${fotoUrl ? `<img src="${esc(fotoUrl)}" alt=""
                         onerror="this.onerror=null; this.parentNode.textContent='${iniciales}';"
                         onload="this.classList.add('loaded')"
                         class="profile-img">` : iniciales}
                </div>
                <h2 class="profile-nombre">${esc(nombre)}</h2>
                <p class="sector mb-2">
                    <i class="bi bi-building" aria-hidden="true"></i> ${esc(sector)}
                </p>
                ${mail ? `
                    <div class="email-container mb-2">
                        <a href="mailto:${esc(mail)}">
                            <i class="bi bi-envelope" aria-hidden="true"></i> ${esc(mail)}
                        </a>
                        ${botonCopiar(mail)}
                    </div>` : ''
                }
                ${interno ? `
                    <p class="interno mb-3">
                        <i class="bi bi-telephone" aria-hidden="true"></i> Int: ${esc(interno)}
                    </p>` : ''
                }
                ${mail ?
                    `<a href="mailto:${esc(mail)}" class="contact-btn">
                        <i class="bi bi-send" aria-hidden="true"></i> Contactar
                    </a>` :
                    `<button type="button" class="contact-btn disabled" disabled>
                        <i class="bi bi-send" aria-hidden="true"></i> Sin contacto
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
                ${esNuevo ? '<i class="bi bi-star-fill text-warning me-2" title="Nueva incorporación"></i>' : ''}
                ${esc(nombre)}
            </td>
            <td>${esc(sector)}</td>
            <td>${esc(interno)}</td>
            <td>
                ${mail ? `
                    <div class="d-flex align-items-center gap-2">
                        <a href="mailto:${esc(mail)}">${esc(mail)}</a>
                        ${botonCopiar(mail)}
                    </div>` : '-'
                }
            </td>
            <td>${esc(celular)}</td>
        </tr>
    `;
}

function cambiarVista(vista) {
    const $cardsContainer = $('#cardsContainer');
    const $listView = $('#listView');
    if (vista === 'cards') {
        $listView.fadeOut(200, function () { $cardsContainer.fadeIn(200); });
    } else {
        $cardsContainer.fadeOut(200, function () { $listView.fadeIn(200); });
    }
}

function cargarDatos() {
    $.ajax({
        url: window.ESTRUCTURA.internosAjax,
        method: 'POST',
        data: {
            action: 'buscar',
            busqueda: $('#searchInput').val() || '',
            sector: sectorElegido()
        },
        success: function (response) {
            if (!Array.isArray(response)) {
                return;
            }
            const currentView = vistaActual();
            let html = '';
            if (currentView === 'cards') {
                response.forEach(empleado => { html += createProfileCard(empleado); });
                $('#cardsContainer').html(html || '<div class="col-12"><div class="est-vacio">No se encontraron resultados</div></div>');
            } else {
                response.forEach(empleado => { html += renderListView(empleado); });
                $('#listContainer').html(html || '<tr><td colspan="5" class="text-center">No se encontraron resultados</td></tr>');
                filterTable($('#tableSearchInput').val() || '');
            }
            cambiarVista(currentView);
            initializeComponents();
        },
        error: function (xhr, status, error) {
            console.error('Error AJAX:', error);
            mostrarNotificacion('Error al cargar los datos', 'error');
        }
    });
}

function copiarEmail(email, button) {
    const tooltip = button ? bootstrap.Tooltip.getInstance(button) : null;
    if (tooltip) {
        tooltip.hide();
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
}

function mostrarNotificacion(mensaje, tipo = 'success') {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });
    Toast.fire({ icon: tipo, title: mensaje });
}

$(document).ready(function () {
    // Primera carga con lo que haya en el buscador (?q= lo deja escrito desde PHP)
    cargarDatos();

    const $searchInput = $('#searchInput');
    const $clearSearch = $('#clearSearch');
    const $tableSearchInput = $('#tableSearchInput');
    const $clearTableSearch = $('#clearTableSearch');

    $('.btn-toggle').on('click', function () {
        if (!$(this).hasClass('active')) {
            $('.btn-toggle').removeClass('active').attr('aria-pressed', 'false');
            $(this).addClass('active').attr('aria-pressed', 'true');
            cargarDatos();
        }
    });

    $searchInput.on('input', function () {
        $clearSearch.toggle($(this).val().length > 0);
        clearTimeout(timeoutId);
        timeoutId = setTimeout(cargarDatos, 300);
    });

    $clearSearch.on('click', function () {
        $searchInput.val('');
        $(this).hide();
        cargarDatos();
        $searchInput.focus();
    });

    $tableSearchInput.on('input', function () {
        const searchValue = $(this).val();
        $clearTableSearch.toggle(searchValue.length > 0);
        filterTable(searchValue);
    });

    $clearTableSearch.on('click', function () {
        $tableSearchInput.val('').focus();
        $(this).hide();
        filterTable('');
    });

    $('#sectorSelect').on('change', cargarDatos);

    $(document).on('click', '.btn-copiar', function (ev) {
        ev.preventDefault();
        copiarEmail($(this).data('mail'), this);
    });

    $('#exportExcel').on('click', exportToExcel);
});
