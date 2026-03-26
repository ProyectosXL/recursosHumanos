// js/main.js

document.addEventListener('DOMContentLoaded', function() {
    
    /**
     * Función genérica para añadir un filtro de búsqueda a una lista.
     */
    const addSearchFilter = (inputId, listId, itemClass) => {
        const searchInput = document.getElementById(inputId);
        if (searchInput) {
            searchInput.addEventListener('keyup', function() {
                const filter = searchInput.value.toUpperCase();
                const items = document.querySelectorAll(`#${listId} .${itemClass}`);
                
                items.forEach(item => {
                    const label = item.querySelector('label');
                    if (label && label.innerText.toUpperCase().indexOf(filter) > -1) {
                        item.style.display = "";
                    } else {
                        item.style.display = "none";
                    }
                });
            });
        }
    };

    // Aplicar filtros a las listas si existen en la página actual
    addSearchFilter('clienteSearch', 'clientes-list', 'cliente-item');
    addSearchFilter('sucursalSearch', 'sucursales-list', 'sucursal-item');

    
    // --- LÓGICA MEJORADA PARA VALIDAR Y HABILITAR EL BOTÓN DE ENVÍO ---
    const clienteSelect = document.getElementById('cliente'); // Usado en actualizar.php
    const clientesCheckboxes = document.querySelectorAll('input[name="clientes[]"]'); // Usado en clientes.php
    const sucursalesCheckboxes = document.querySelectorAll('input[name="dsn[]"]');
    const submitBtn = document.getElementById('submitBtn');

    function validateForm() {
        if (!submitBtn) return;

        // Comprobar si hay un cliente seleccionado (funciona para <select> y checkboxes)
        let clienteSeleccionado = false;
        if (clienteSelect) { // Si es un <select>
            clienteSeleccionado = clienteSelect.value !== "";
        } else if (clientesCheckboxes.length > 0) { // Si son checkboxes
            clienteSeleccionado = Array.from(clientesCheckboxes).some(cb => cb.checked);
        }

        // Comprobar si hay una sucursal seleccionada
        const algunaSucursalSeleccionada = Array.from(sucursalesCheckboxes).some(cb => cb.checked);
        
        // Habilitar o deshabilitar el botón
        submitBtn.disabled = !(clienteSeleccionado && algunaSucursalSeleccionada);
    }

    // Añadir los "listeners" para que la validación se ejecute con cada cambio
    if (clienteSelect) {
        clienteSelect.addEventListener('change', validateForm);
    }
    if (clientesCheckboxes.length > 0) {
        clientesCheckboxes.forEach(checkbox => checkbox.addEventListener('change', validateForm));
    }
    if (sucursalesCheckboxes.length > 0) {
        sucursalesCheckboxes.forEach(checkbox => checkbox.addEventListener('change', validateForm));
    }

    
    // --- LÓGICA PARA LOS CHECKBOXES DE SELECCIÓN RÁPIDA DE SUCURSALES ---
    const addRegionCheckboxListener = (checkboxId, regionName) => {
        const checkbox = document.getElementById(checkboxId);
        if (!checkbox) return;
        
        checkbox.addEventListener('change', function() {
            document.querySelectorAll('#sucursales-list input[type="checkbox"]').forEach(sucursalCheckbox => {
                if (sucursalCheckbox.getAttribute('region') === regionName) {
                    sucursalCheckbox.checked = this.checked;
                }
            });
            validateForm(); // Re-validar el formulario después de un cambio masivo
        });
    };

    const todosCheckbox = document.getElementById('checkTodos');
    if (todosCheckbox) {
        todosCheckbox.addEventListener('change', function() {
            document.querySelectorAll('#sucursales-list input[type="checkbox"]').forEach(x => {
                x.checked = this.checked;
            });
            validateForm();
        });
    }
    
    addRegionCheckboxListener('checkBsas', 'AMBA');
    addRegionCheckboxListener('checkRosario', 'ROSARIO');
    addRegionCheckboxListener('checkMardel', 'MARDEL');

    // --- LÓGICA PARA MOSTRAR ESTADO DE CARGA AL ENVIAR EL FORMULARIO ---
    const form = document.getElementById('pedidos');

    if (form && submitBtn) {
        form.addEventListener('submit', function() {
            // Deshabilitar el botón para prevenir múltiples envíos
            submitBtn.disabled = true;

            // Ocultar el texto y mostrar el spinner
            const btnText = submitBtn.querySelector('.btn-text');
            const spinner = submitBtn.querySelector('.spinner-border');

            if (btnText && spinner) {
                btnText.textContent = 'Enviando...'; // Cambia el texto
                spinner.style.display = 'inline-block'; // Muestra el spinner
            }
        });
    }


});