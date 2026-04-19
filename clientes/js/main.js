// js/main.js

document.addEventListener('DOMContentLoaded', function () {

    // ================================================================
    // Filtro de búsqueda genérico por texto
    // ================================================================
    function addSearchFilter(inputId, listId, itemClass) {
        var searchInput = document.getElementById(inputId);
        if (!searchInput) return;

        searchInput.addEventListener('keyup', function () {
            var filter = this.value.toUpperCase();
            document.querySelectorAll('#' + listId + ' .' + itemClass).forEach(function (item) {
                // Soporta tanto label anidado (estructura Bootstrap) como item directo
                var textEl = item.querySelector('label') || item;
                var visible = textEl.innerText.toUpperCase().indexOf(filter) > -1;
                item.style.display = visible ? '' : 'none';
            });
        });
    }

    addSearchFilter('clienteSearch',  'clientes-list',   'cliente-item');
    addSearchFilter('sucursalSearch', 'sucursales-list', 'sucursal-item');


    // ================================================================
    // Referencias DOM — Pantalla Enviar a Sucursales (clientes.php)
    // ================================================================
    var clienteSelect        = document.getElementById('cliente');
    var clientesCheckboxes   = document.querySelectorAll('input[name="clientes[]"]');
    var sucursalesCheckboxes = document.querySelectorAll('input[name="dsn[]"]');
    var submitBtn            = document.getElementById('submitBtn');
    var form                 = document.getElementById('pedidos');
    var clientesCounter      = document.getElementById('clientes-counter');
    var sucursalesCounter    = document.getElementById('sucursales-counter');
    var resumenOps           = document.getElementById('resumen-ops');
    var btnHint              = document.getElementById('btn-hint');


    // ================================================================
    // Contadores de selección
    // ================================================================
    function updateCounters() {
        if (clientesCheckboxes.length > 0 && clientesCounter) {
            var sel = Array.from(clientesCheckboxes).filter(function (cb) { return cb.checked; }).length;
            clientesCounter.textContent = sel + ' seleccionados de ' + clientesCheckboxes.length;
            clientesCounter.classList.toggle('counter-badge--active', sel > 0);
        }
        if (sucursalesCheckboxes.length > 0 && sucursalesCounter) {
            var sel = Array.from(sucursalesCheckboxes).filter(function (cb) { return cb.checked; }).length;
            sucursalesCounter.textContent = sel + ' seleccionadas de ' + sucursalesCheckboxes.length;
            sucursalesCounter.classList.toggle('counter-badge--active', sel > 0);
        }
    }


    // ================================================================
    // Resumen de operaciones
    // ================================================================
    function updateSummary() {
        if (!resumenOps) return;

        var nCli = Array.from(clientesCheckboxes).filter(function (cb) { return cb.checked; }).length;
        var nSuc = Array.from(sucursalesCheckboxes).filter(function (cb) { return cb.checked; }).length;

        if (nCli === 0 || nSuc === 0) {
            resumenOps.style.display = 'none';
            return;
        }

        var nOps   = nCli * nSuc;
        var textEl = resumenOps.querySelector('.resumen-text');
        if (textEl) {
            textEl.textContent =
                'Vas a enviar '
                + nCli + (nCli === 1 ? ' cliente' : ' clientes')
                + ' a '
                + nSuc + (nSuc === 1 ? ' sucursal' : ' sucursales')
                + ' ('
                + nOps + (nOps === 1 ? ' operación' : ' operaciones')
                + ').';
        }
        resumenOps.style.display = '';
    }


    // ================================================================
    // Hint del botón cuando está deshabilitado
    // ================================================================
    function updateBtnHint(clienteSel, sucSel) {
        if (!btnHint) return;
        btnHint.style.display = (clienteSel && sucSel) ? 'none' : '';
    }


    // ================================================================
    // Validación general del formulario
    // ================================================================
    function validateForm() {
        if (!submitBtn) return;

        var clienteSeleccionado = false;
        if (clienteSelect) {
            clienteSeleccionado = clienteSelect.value !== '';
        } else if (clientesCheckboxes.length > 0) {
            clienteSeleccionado = Array.from(clientesCheckboxes).some(function (cb) { return cb.checked; });
        }

        var sucursalSeleccionada = Array.from(sucursalesCheckboxes).some(function (cb) { return cb.checked; });

        submitBtn.disabled = !(clienteSeleccionado && sucursalSeleccionada);

        updateCounters();
        updateSummary();
        updateBtnHint(clienteSeleccionado, sucursalSeleccionada);
    }

    // Listeners individuales
    if (clienteSelect) {
        clienteSelect.addEventListener('change', validateForm);
    }

    clientesCheckboxes.forEach(function (cb) {
        cb.addEventListener('change', validateForm);
    });

    sucursalesCheckboxes.forEach(function (cb) {
        cb.addEventListener('change', function () {
            syncChipStates();
            validateForm();
        });
    });


    // ================================================================
    // Chips de región — selección rápida de sucursales
    // ================================================================
    function syncChipStates() {
        document.querySelectorAll('.region-chip').forEach(function (chip) {
            var region    = chip.dataset.region;
            var targetCbs = region === 'TODOS'
                ? Array.from(sucursalesCheckboxes)
                : Array.from(sucursalesCheckboxes).filter(function (cb) {
                    return cb.getAttribute('region') === region;
                  });

            var allChecked = targetCbs.length > 0 && targetCbs.every(function (cb) { return cb.checked; });
            chip.classList.toggle('region-chip--active', allChecked);
        });
    }

    document.querySelectorAll('.region-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
            var region      = this.dataset.region;
            var willActivate = !this.classList.contains('region-chip--active');

            var targetCbs = region === 'TODOS'
                ? Array.from(sucursalesCheckboxes)
                : Array.from(sucursalesCheckboxes).filter(function (cb) {
                    return cb.getAttribute('region') === region;
                  });

            targetCbs.forEach(function (cb) { cb.checked = willActivate; });

            syncChipStates();
            validateForm();
        });
    });


    // ================================================================
    // Estado de envío — spinner + bloqueo visual
    // (los inputs NO se deshabilitan para preservar los valores del POST)
    // ================================================================
    function startSending() {
        if (!submitBtn) return;

        submitBtn.disabled = true;

        var spinner   = document.getElementById('btn-spinner');
        var btnIcon   = document.getElementById('btn-icon');
        var btnTextEl = submitBtn.querySelector('.btn-text');

        if (spinner)   spinner.style.display = 'inline-block';
        if (btnIcon)   btnIcon.style.display  = 'none';
        if (btnTextEl) btnTextEl.textContent  = 'Enviando… esto puede tardar';
        if (btnHint)   btnHint.style.display  = 'none';

        // Bloqueo visual — no usamos disabled en inputs para no perder valores del POST
        if (form) {
            form.style.pointerEvents = 'none';
            form.style.opacity       = '0.7';
        }
    }


    // ================================================================
    // Confirmación con SweetAlert2 antes de enviar
    // ================================================================
    if (form && submitBtn) {
        form.addEventListener('submit', function (e) {

            // Sin SweetAlert2: comportamiento original
            if (typeof Swal === 'undefined') {
                startSending();
                return;
            }

            e.preventDefault();

            var nCli   = Array.from(clientesCheckboxes).filter(function (cb) { return cb.checked; }).length;
            var nSuc   = Array.from(sucursalesCheckboxes).filter(function (cb) { return cb.checked; }).length;
            var nOps   = nCli * nSuc;
            var accent = submitBtn.dataset.accent === 'ref' ? '#059669' : '#4f46e5';

            Swal.fire({
                title: '¿Confirmás el envío?',
                html:
                    '<div style="font-size:.9rem;line-height:1.85;text-align:left;">'
                    + '<div><strong>Clientes seleccionados:</strong> ' + nCli + '</div>'
                    + '<div><strong>Sucursales destino:</strong> ' + nSuc + '</div>'
                    + '<div><strong>Total de operaciones:</strong> ' + nOps + '</div>'
                    + '<div class="mt-2" style="color:#78350f;font-size:.82rem;">'
                    + '<i class="bi bi-clock me-1"></i>Esta operación puede tardar varios minutos.'
                    + '</div></div>',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, enviar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: accent,
                cancelButtonColor: '#6c757d',
                reverseButtons: true,
                focusCancel: true,
            }).then(function (result) {
                if (!result.isConfirmed) return;
                startSending();
                form.submit();
            });
        });
    }


    // ================================================================
    // Inicialización
    // ================================================================
    validateForm(); // inicializa contadores, resumen y estado del botón

});
