/*
 * Administración de estructura - utilidades comunes (listado de áreas, ficha e importación).
 * Las acciones van a ajax/admin.php, que valida permiso, CSRF y datos: acá sólo se arma el envío
 * y se muestra lo que responde el servidor.
 */
(function (global) {
    'use strict';

    var CFG = global.ESTRUCTURA || {};

    /**
     * POST a ajax/admin.php. Resuelve siempre con el JSON del servidor (aunque sea un error HTTP),
     * o con {ok:false, mensaje} si no hubo respuesta: el que llama decide qué mostrar.
     */
    function enviar(accion, datos) {
        var cuerpo = new FormData();
        cuerpo.append('accion', accion);
        cuerpo.append('csrf', CFG.csrf || '');
        Object.keys(datos || {}).forEach(function (k) {
            var v = datos[k];
            cuerpo.append(k, (v !== null && typeof v === 'object') ? JSON.stringify(v) : String(v));
        });
        return fetch(CFG.ajaxAdmin, { method: 'POST', body: cuerpo, credentials: 'same-origin' })
            .then(function (r) {
                return r.json().catch(function () { return { ok: false, mensaje: 'Respuesta inválida del servidor (' + r.status + ').' }; });
            })
            .catch(function () { return { ok: false, mensaje: 'No hubo respuesta del servidor. Revisá la conexión y probá de nuevo.' }; });
    }

    function esc(valor) {
        return String(valor == null ? '' : valor)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    /** Muestra un mensaje en el aviso de la pantalla (#admMensaje) y lo lleva a la vista. */
    function mensaje(texto, tipo) {
        var el = document.getElementById('admMensaje');
        if (!el) { global.alert(texto); return; }
        el.textContent = texto;
        el.className = 'est-aviso' + (tipo === 'error' ? ' est-aviso--error' : tipo === 'ok' ? ' est-aviso--ok' : '');
        el.hidden = false;
        el.scrollIntoView({ block: 'nearest' });
    }

    global.EstructuraAdmin = { enviar: enviar, esc: esc, mensaje: mensaje };
})(window);
