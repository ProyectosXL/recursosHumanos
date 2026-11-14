/*
 * Administrar internos (ABM) - migrado desde comercial/recepcion/js/listado.js.
 *
 * Permisos: window.ESTRUCTURA.abm = {crear, editar, eliminar} sale de Permisos::abmInternos(),
 * la misma regla que valida ajax.php. Acá sólo se esconden los botones: el servidor es el que manda.
 * Los nombres van en atributos data-* (no dentro de onclick="..."): un apellido con apóstrofo
 * (D'Almeida) cortaba el onclick y el botón no hacía nada.
 */
const ABM = (window.ESTRUCTURA && window.ESTRUCTURA.abm) || { crear: false, editar: false, eliminar: false };
const CSRF = (window.ESTRUCTURA && window.ESTRUCTURA.csrf) || '';

function esc(valor) {
    return String(valor == null ? '' : valor)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

// Event Listeners
$(document).ready(function() {
    cargarDatos();
    initializeModal();
    let timeoutId;
    $('#searchInput').on('input', function() {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => {
            cargarDatos();
        }, 300);
    });
    $('#verInactivos').on('change', function() {
        cargarDatos();
    });
    $('#tableBody').on('click', '[data-accion="estado"]', function() {
        toggleStatus($(this).data('nombre'), $(this).data('nuevo'));
    });
    $('#tableBody').on('click', '[data-accion="editar"]', function() {
        editarRegistro($(this).data('nombre'));
    });
});


let currentData = [];

function createTableRow(empleado) {
    const inactivo = empleado.inactivo == 1;
    const nombre = esc(empleado.nombre_apellido);
    const estado = `<i class="bi ${inactivo ? 'bi-x-circle' : 'bi-check-circle'} me-1"></i>${inactivo ? 'Inactivo' : 'Activo'}`;
    return `
        <tr class="${inactivo ? 'fila-inactiva' : ''}">
            <td>${esc(empleado.interno) || '-'}</td>
            <td>
                <div class="fw-semibold">${nombre}</div>
            </td>
            <td>${esc(empleado.sector) || '-'}</td>
            <td>
                ${empleado.mail ? 
                    `<a href="mailto:${esc(empleado.mail)}" class="text-decoration-none text-primary">
                        ${esc(empleado.mail)}
                    </a>` : 
                    '-'
                }
            </td>
            <td>${esc(empleado.celular) || '-'}</td>
            <td class="text-center">
                ${ABM.eliminar ? `
                <button type="button" class="btn ${inactivo ? 'btn-danger' : 'btn-success'} btn-status"
                        data-accion="estado" data-nombre="${nombre}" data-nuevo="${inactivo ? 0 : 1}"
                        aria-label="${inactivo ? 'Reactivar' : 'Desactivar'} a ${nombre}"
                        style="width: 100px;">
                    ${estado}
                </button>` : `<span class="badge ${inactivo ? 'bg-danger' : 'bg-success'}">${estado}</span>`}
            </td>
            <td class="text-end">
                ${ABM.editar ? `
                <button type="button" class="btn btn-edit" data-accion="editar" data-nombre="${nombre}"
                        style="width: 100px;">
                    <i class="bi bi-pencil-square me-1"></i>
                    Editar
                </button>` : ''}
            </td>
        </tr>
    `;
}


function cargarDatos() {
    $('#loadingState').removeClass('d-none');
    $('#emptyState').addClass('d-none');
    $('#tableBody').empty();

    $.ajax({
        url: window.ESTRUCTURA.internosAjax,
        method: 'POST',
        data: {
            // listarAbm: como 'buscar' (mismo filtro por nombre), pero puede traer a los desactivados
            action: 'listarAbm',
            busqueda: $('#searchInput').val() || '',
            incluirInactivos: $('#verInactivos').is(':checked') ? 1 : 0
        },
        success: function(response) {
            $('#loadingState').addClass('d-none');
            
            if (Array.isArray(response) && response.length > 0) {
                currentData = response;
                let html = '';
                response.forEach(empleado => {
                    html += createTableRow(empleado);
                });
                $('#tableBody').html(html);
            } else {
                $('#emptyState').removeClass('d-none');
                if (response && response.success === false) {
                    mostrarNotificacion(response.message, 'error');
                }
            }
        },
        error: function(xhr, status, error) {
            $('#loadingState').addClass('d-none');
            console.error('Error:', error);
            mostrarNotificacion('Error al cargar el listado', 'error');
        }
    });
}

let empleadoModal;

function initializeModal() {
    // Inicializar el modal
    empleadoModal = new bootstrap.Modal(document.getElementById('empleadoModal'), {
        keyboard: false,
        backdrop: 'static'
    });
    
    // Cargar sectores
    cargarSectores();
    
    // Agregar event listeners
    document.getElementById('saveEmpleado').addEventListener('click', guardarEmpleado);
    document.getElementById('empleadoModal').addEventListener('hidden.bs.modal', resetearFormulario);
    // "Nuevo Registro" llama a nuevoRegistro() desde su onclick. Antes se le sumaba otro listener
    // con querySelector: si el usuario no podía crear el botón no existía y el JS tiraba un error.
}
function nuevoRegistro() {
    // Limpiar el formulario
    resetearFormulario();
    
    // Asegurar que los campos estén habilitados
    document.getElementById('nombreApellido').readOnly = false;
    document.getElementById('interno').readOnly = false;
    
    // Establecer modo nuevo
    document.getElementById('modoEdicion').value = '0';
    document.getElementById('nombreApellidoOriginal').value = '';
    document.getElementById('modalTitle').textContent = 'Nuevo Empleado';

    // Asegurarse de que el formulario esté limpio
    const form = document.getElementById('empleadoForm');
    form.reset();
    form.classList.remove('was-validated');

    // Mostrar el modal
    empleadoModal.show();
}

function cargarSectores() {
    // Devuelve la promesa: editarRegistro() espera a los sectores con .then() (antes devolvía undefined)
    return $.ajax({
        url: window.ESTRUCTURA.internosAjax,
        method: 'POST',
        data: { action: 'getSectores' },
        success: function(response) {
            console.log('Sectores recibidos:', response); // Para debug
            
            if (Array.isArray(response)) {
                const sectorSelect = document.getElementById('sector');
                sectorSelect.innerHTML = '<option value="">Seleccionar...</option>';
                
                response.forEach(sector => {
                    if (sector) { // Verificar que el sector no esté vacío
                        sectorSelect.innerHTML += `
                            <option value="${esc(sector)}">${esc(sector)}</option>
                        `;
                    }
                });
            } else {
                console.error('La respuesta no es un array:', response);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error al cargar sectores:', error);
        }
    });
}

function validarFormulario() {
    const form = document.getElementById('empleadoForm');
    const interno = document.getElementById('interno').value.trim();
    const nombreApellido = document.getElementById('nombreApellido').value.trim();
    const sector = document.getElementById('sector').value.trim();
    const mail = document.getElementById('email').value.trim();

    // Validar interno
    if (!interno || !/^\d+$/.test(interno)) {
        mostrarNotificacion('El número de interno es requerido y debe contener solo números', 'error');
        return false;
    }

    // Validar nombre y apellido
    if (!nombreApellido) {
        mostrarNotificacion('El nombre y apellido son requeridos', 'error');
        return false;
    }

    // Validar sector
    if (!sector) {
        mostrarNotificacion('Debe seleccionar un sector', 'error');
        return false;
    }

    // Validar mail
    if (!mail) {
        mostrarNotificacion('Debe cargar un mail válido', 'error');
        return false;
    }

    return true;
}


function editarRegistro(nombre_apellido) {
    console.log('Iniciando edición para:', nombre_apellido); // Debug

    $.ajax({
        url: window.ESTRUCTURA.internosAjax,
        method: 'POST',
        data: {
            action: 'getEmpleado',
            nombre_apellido: nombre_apellido
        },
        success: function(response) {
            if (response.success) {
                const empleado = response.data;
                
                // Debug
                console.log('Datos del empleado:', empleado);

                // Establecer modo edición
                const modoEdicionInput = document.getElementById('modoEdicion');
                const nombreOriginalInput = document.getElementById('nombreApellidoOriginal');
                
                modoEdicionInput.value = '1';
                nombreOriginalInput.value = empleado.nombre_apellido;

                // Debug
                console.log('Modo edición:', modoEdicionInput.value);
                console.log('Nombre original:', nombreOriginalInput.value);

                // Llenar el resto del formulario
                document.getElementById('modalTitle').textContent = 'Editar Empleado';
                document.getElementById('interno').value = empleado.interno;
                document.getElementById('nombreApellido').value = empleado.nombre_apellido;
                document.getElementById('email').value = empleado.mail || '';
                document.getElementById('celular').value = empleado.celular || '';
                document.getElementById('nombreApellido').readOnly = true;

                // Cargar y seleccionar sector
                const sectorSelect = document.getElementById('sector');
                if (sectorSelect.options.length > 1) {
                    sectorSelect.value = empleado.sector;
                } else {
                    cargarSectores().then(() => {
                        sectorSelect.value = empleado.sector;
                    });
                }

                // Mostrar modal
                empleadoModal.show();
            } else {
                mostrarNotificacion('Error al cargar los datos del empleado', 'error');
            }
        },
        error: function() {
            mostrarNotificacion('Error al cargar los datos del empleado', 'error');
        }
    });
}

let isSubmitting = false;

function guardarEmpleado() {
    if (isSubmitting) return;
    if (!validarFormulario()) return;

    const form = document.getElementById('empleadoForm');
    const formData = new FormData(form);
    const datos = Object.fromEntries(formData);
    
    // Asegurarnos de que el modo edición se envía correctamente
    datos.action = 'guardar';
    datos.csrf = CSRF;
    datos.modoEdicion = document.getElementById('modoEdicion').value;
    datos.nombreApellidoOriginal = document.getElementById('nombreApellidoOriginal').value;

    console.log('Datos a enviar:', datos); // Para debug

    isSubmitting = true;

    Swal.fire({
        title: datos.modoEdicion === '1' ? 'Actualizando...' : 'Guardando...',
        text: 'Por favor espere',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    $.ajax({
        url: window.ESTRUCTURA.internosAjax,
        method: 'POST',
        data: datos,
        success: function(response) {
            console.log('Respuesta del servidor:', response); // Para debug
            
            Swal.close();
            isSubmitting = false;

            if (response.success) {
                empleadoModal.hide();
                mostrarNotificacion(response.message);
                cargarDatos();
                resetearFormulario();
            } else {
                mostrarNotificacion(response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error:', error);
            Swal.close();
            isSubmitting = false;
            mostrarNotificacion('Error al guardar el empleado', 'error');
        }
    });
}

function resetearFormulario() {
    const form = document.getElementById('empleadoForm');
    form.reset();
    
    // Resetear modo edición
    document.getElementById('modoEdicion').value = '0';
    document.getElementById('nombreApellidoOriginal').value = '';
    
    // Habilitar campos para nuevo registro
    document.getElementById('nombreApellido').readOnly = false;
    document.getElementById('interno').readOnly = false;
    
    // Limpiar select de sector
    const sectorSelect = document.getElementById('sector');
    sectorSelect.value = '';
    
    // Actualizar título
    document.getElementById('modalTitle').textContent = 'Nuevo Empleado';
    
    // Limpiar validaciones
    form.classList.remove('was-validated');
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

function confirmarAccion(titulo, texto, callback) {
    Swal.fire({
        title: titulo,
        text: texto,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#0D5C63',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Confirmar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed && callback) {
            callback();
        }
    });
}

function resetValidation() {
    const form = document.getElementById('empleadoForm');
    form.classList.remove('was-validated');
    [...form.elements].forEach(element => {
        element.classList.remove('is-invalid');
    });
}

function toggleStatus(nombre_apellido, newStatus) {
    Swal.fire({
        title: `¿Está seguro de ${newStatus == 0 ? 'habilitar' : 'inhabilitar'} este empleado?`,
        // Ya no dice "No podrá ser revertido": con "Ver también las personas desactivadas" se reactiva
        text: newStatus == 0 ? 'Vuelve a aparecer en Internos.' : 'Deja de aparecer en Internos. Se puede reactivar desde este listado.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#0D5C63',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Confirmar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: window.ESTRUCTURA.internosAjax,
                method: 'POST',
                data: {
                    action: 'toggleStatus',
                    csrf: CSRF,
                    nombre_apellido: nombre_apellido,
                    status: newStatus
                },
                success: function(response) {
                    if (response.success) {
                        mostrarNotificacion(response.message);
                        cargarDatos();
                    } else {
                        mostrarNotificacion(response.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    mostrarNotificacion('Error al cambiar el estado del empleado', 'error');
                }
            });
        }
    });
}
