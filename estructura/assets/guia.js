/*
 * Guía de Consultas - búsqueda en el navegador y registro de búsquedas.
 *
 * La búsqueda usa EstructuraNormalizar (la misma regla que Reglas.php) sobre las áreas y los
 * temas que vinieron en la página (window.ESTRUCTURA.guia): el resultado aparece mientras se
 * escribe, sin ir al servidor por cada tecla.
 *
 * Registro (base para el chatbot de la etapa 2): se registra cuando el usuario DEJA de escribir
 * (1,5 s sin teclear, Enter o un chip de ejemplo), nunca una por tecla. No se registra si quedan
 * menos de 3 caracteres útiles ni si es igual a la última registrada en esta pantalla. Si el
 * registro falla, la pantalla no dice nada ni se traba: el error queda en el log de PHP.
 */
(function () {
    'use strict';

    var CFG = window.ESTRUCTURA || {};
    var DATOS = CFG.guia || { areas: [], temas: [] };
    var n = EstructuraNormalizar.crear(CFG.reglas || {});
    var ESPERA_REGISTRO_MS = 1500;

    var areasPorId = {};
    DATOS.areas.forEach(function (a) { areasPorId[a.id] = a; });
    // El texto buscable de cada tema se calcula una vez, no por tecla
    DATOS.temas.forEach(function (t) { t._texto = n.textoBuscable(t.tema, t.palabras_clave, t.area); });

    var $q = document.getElementById('q');
    var $form = document.getElementById('guiaForm');
    var $borrar = document.getElementById('guiaBorrar');
    var $areas = document.getElementById('guiaAreas');
    var $resultados = document.getElementById('guiaResultados');
    var $resumen = document.getElementById('guiaResumen');
    var $lista = document.getElementById('guiaLista');
    var $sinResultados = document.getElementById('guiaSinResultados');
    var $fichas = document.getElementById('guiaFichas');

    var areaElegida = null;
    var ultimaRegistrada = '';
    var timerRegistro = null;

    function esc(valor) {
        return String(valor == null ? '' : valor)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function leerAreaElegida() {
        var chip = $areas ? $areas.querySelector('[aria-pressed="true"]') : null;
        areaElegida = chip && chip.getAttribute('data-area') ? Number(chip.getAttribute('data-area')) : null;
    }

    function tarjeta(t) {
        var a = areasPorId[t.id_area] || {};
        var contacto = '';
        if (t.interno) {
            contacto += '<span><i class="bi bi-telephone" aria-hidden="true"></i> Int. ' + esc(t.interno) + '</span>';
        }
        if (t.mail_persona) {
            contacto += '<a href="mailto:' + esc(t.mail_persona) + '"><i class="bi bi-envelope" aria-hidden="true"></i> ' + esc(t.mail_persona) + '</a>';
        }
        var ref = t.referente !== null
            ? '<div class="res-valor">' + esc(t.referente) + '</div>'
              + (contacto ? '<div class="res-contacto">' + contacto + '</div>' : '')
            : '<div class="res-valor est-pendiente">A definir</div>';
        var mail = a.mail
            ? '<a href="mailto:' + esc(a.mail) + '">' + esc(a.mail) + '</a>'
            : '<span class="est-pendiente">Mail de sector a dar de alta</span>';
        var acciones = '';
        if (a.whatsapp) {
            acciones += '<a class="est-btn est-btn--icono res-wa" href="' + esc(a.whatsapp) + '" target="_blank" rel="noopener"'
                + ' aria-label="WhatsApp de ' + esc(a.nombre) + ': ' + esc(a.celular) + '" title="WhatsApp ' + esc(a.celular) + '">'
                + '<i class="bi bi-whatsapp" aria-hidden="true"></i></a>';
        }
        if (t.persona) {
            acciones += '<a class="est-btn" href="' + esc(CFG.internos + '?q=' + encodeURIComponent(t.persona)) + '">Ver en Internos</a>';
        }
        return '<article class="res">'
            + '<div class="res-tema">'
            +   '<div class="res-area"><span class="est-punto" style="background:' + esc(a.color) + '"></span>' + esc(t.area) + '</div>'
            +   '<h2>' + esc(t.tema) + '</h2>'
            + '</div>'
            + '<div class="res-dato"><div class="est-etiqueta">Referente</div>' + ref + '</div>'
            + '<div class="res-dato"><div class="est-etiqueta">Mail de sector</div><div class="res-valor res-mail">' + mail + '</div></div>'
            + (acciones ? '<div class="res-acciones">' + acciones + '</div>' : '')
            + '</article>';
    }

    function mostrarFichas() {
        Array.prototype.forEach.call($fichas.querySelectorAll('.ficha'), function (f) {
            f.hidden = areaElegida !== null && Number(f.getAttribute('data-area')) !== areaElegida;
        });
    }

    function render() {
        var texto = $q.value;
        var hayTexto = texto.trim() !== '';
        $borrar.hidden = !hayTexto;
        $resultados.hidden = !hayTexto;
        $fichas.hidden = hayTexto;

        if (!hayTexto) {
            $lista.innerHTML = '';
            mostrarFichas();
            return;
        }
        if (n.caracteresUtiles(texto) < n.minBusqueda) {
            $resumen.textContent = 'Escribí un poco más…';
            $lista.innerHTML = '';
            $sinResultados.hidden = true;
            return;
        }
        var res = n.buscar(texto, DATOS.temas, areaElegida);
        $resumen.textContent = res.length === 1 ? '1 resultado' : res.length + ' resultados';
        $lista.innerHTML = res.map(tarjeta).join('');
        $sinResultados.hidden = res.length > 0;
    }

    /** Deja la búsqueda en la URL: recargar o compartir el link abre lo mismo. */
    function actualizarUrl() {
        var params = new URLSearchParams();
        if ($q.value.trim() !== '') { params.set('q', $q.value.trim()); }
        if (areaElegida !== null) { params.set('area', String(areaElegida)); }
        var qs = params.toString();
        history.replaceState(null, '', location.pathname + (qs ? '?' + qs : '') + location.hash);
    }

    function registrar() {
        clearTimeout(timerRegistro);
        var texto = $q.value;
        if (!n.debeRegistrar(texto, ultimaRegistrada)) { return; }
        ultimaRegistrada = n.normalizar(texto);
        var cuerpo = new URLSearchParams();
        cuerpo.set('accion', 'registrar');
        cuerpo.set('texto', texto);
        if (areaElegida !== null) { cuerpo.set('area', String(areaElegida)); }
        try {
            fetch(CFG.ajaxConsulta, { method: 'POST', body: cuerpo, credentials: 'same-origin', keepalive: true })
                .catch(function () { /* a propósito: si falla, la pantalla no avisa */ });
        } catch (e) { /* idem */ }
    }

    function programarRegistro() {
        clearTimeout(timerRegistro);
        timerRegistro = setTimeout(registrar, ESPERA_REGISTRO_MS);
    }

    $q.addEventListener('input', function () {
        render();
        actualizarUrl();
        programarRegistro();
    });

    $form.addEventListener('submit', function (ev) {
        ev.preventDefault();   // Enter: no recarga, registra ya
        registrar();
    });

    $borrar.addEventListener('click', function () {
        clearTimeout(timerRegistro);
        $q.value = '';
        render();
        actualizarUrl();
        $q.focus();
    });

    Array.prototype.forEach.call(document.querySelectorAll('[data-ejemplo]'), function (b) {
        b.addEventListener('click', function () {
            $q.value = b.getAttribute('data-ejemplo');
            render();
            actualizarUrl();
            registrar();
        });
    });

    if ($areas) {
        $areas.addEventListener('click', function (ev) {
            var chip = ev.target.closest('[data-area]');
            if (!chip) { return; }
            Array.prototype.forEach.call($areas.querySelectorAll('[data-area]'), function (c) {
                c.setAttribute('aria-pressed', c === chip ? 'true' : 'false');
            });
            leerAreaElegida();
            render();
            actualizarUrl();
        });
    }

    /*
     * Contacto del referente en las fichas. El popover se abre solo con hover (mouse) y con foco
     * de teclado (CSS); el clic/toque lo deja fijo (aria-expanded), que es lo que usa el celular.
     * Escape o un clic afuera lo cierran; .persona--cerrada lo tapa aunque siga el hover o el
     * foco, hasta que el mouse o el foco salen de esa persona.
     */
    function cerrarPopovers(excepto) {
        Array.prototype.forEach.call($fichas.querySelectorAll('.persona-nombre[aria-expanded="true"]'), function (b) {
            if (b !== excepto) { b.setAttribute('aria-expanded', 'false'); }
        });
    }
    $fichas.addEventListener('click', function (ev) {
        var b = ev.target.closest('.persona-nombre');
        cerrarPopovers(b);
        if (b) {
            b.closest('.persona').classList.remove('persona--cerrada');
            b.setAttribute('aria-expanded', b.getAttribute('aria-expanded') === 'true' ? 'false' : 'true');
        }
    });
    document.addEventListener('click', function (ev) {
        if (!ev.target.closest('.persona')) { cerrarPopovers(null); }
    });
    document.addEventListener('keydown', function (ev) {
        if (ev.key !== 'Escape') { return; }
        cerrarPopovers(null);
        var p = document.activeElement && document.activeElement.closest('.persona');
        if (p) {
            p.classList.add('persona--cerrada');
            p.querySelector('.persona-nombre').focus();
        }
        Array.prototype.forEach.call($fichas.querySelectorAll('.persona:hover'), function (h) { h.classList.add('persona--cerrada'); });
    });
    Array.prototype.forEach.call($fichas.querySelectorAll('.persona'), function (p) {
        p.addEventListener('mouseleave', function () { p.classList.remove('persona--cerrada'); });
        p.addEventListener('focusout', function (ev) {
            if (!p.contains(ev.relatedTarget)) { p.classList.remove('persona--cerrada'); }
        });
    });

    leerAreaElegida();
    render();
    // Abrir con ?q= (la tarjeta del inicio de XL Gestión) cuenta como una búsqueda terminada
    if ($q.value.trim() !== '') { registrar(); }
})();
