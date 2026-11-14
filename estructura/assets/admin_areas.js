/*
 * Administrar áreas - acciones del listado y del panel de búsquedas sin resultado.
 * Desactivar y reactivar piden confirmación (data-confirmar). Después de una acción se recarga
 * la página: el listado (orden, ficha completa, última actualización) lo arma el servidor.
 */
(function () {
    'use strict';

    var A = window.EstructuraAdmin;

    document.addEventListener('click', function (ev) {
        var b = ev.target.closest('button[data-accion]');
        if (!b || b.disabled) { return; }
        var accion = b.getAttribute('data-accion');
        if (['moverArea', 'desactivarArea', 'reactivarArea', 'descartarBusqueda'].indexOf(accion) === -1) { return; }

        var confirmar = b.getAttribute('data-confirmar');
        if (confirmar && !window.confirm(confirmar)) { return; }

        var datos = {};
        if (b.hasAttribute('data-id')) { datos.id = b.getAttribute('data-id'); }
        if (b.hasAttribute('data-direccion')) { datos.direccion = b.getAttribute('data-direccion'); }
        if (b.hasAttribute('data-normalizado')) { datos.normalizado = b.getAttribute('data-normalizado'); }

        b.disabled = true;
        A.enviar(accion, datos).then(function (r) {
            if (!r.ok) {
                b.disabled = false;
                A.mensaje(r.mensaje || 'No se pudo hacer el cambio.', 'error');
                return;
            }
            if (accion === 'descartarBusqueda') {
                // Descartada: sale del panel sin recargar
                var li = b.closest('li');
                if (li) { li.remove(); }
                return;
            }
            window.location.reload();
        });
    });
})();
