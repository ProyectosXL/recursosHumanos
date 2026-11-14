/*
 * Intranet XL - comportamiento de la tarjeta de comunicado (recursosHumanos/intranet/partials/comunicado_tarjeta.php)
 * Se usa en la intranet, en el aviso al entrar al hub y en la vista previa de la carga.
 *
 * Carrusel (más de una imagen): fundido suave, pasa solo cada 6 s, se frena con el mouse encima,
 * se desliza con el dedo. No avanza solo si el equipo tiene "reducir movimiento". Bootstrap ya no
 * avanza mientras la pestaña está oculta o el carrusel no se ve (por ejemplo, en una ventana
 * cerrada); igual se frena y se retoma explícitamente al cambiar de pestaña.
 *
 * IntraComunicados.iniciar(raiz) arranca los carruseles dentro de raiz (para contenido que se
 * agrega después, como la vista previa).
 */
(function () {
    'use strict';

    const INTERVALO = 6000;

    function reducirMovimiento() {
        return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function iniciar(raiz) {
        if (!window.bootstrap) return;
        const reducir = reducirMovimiento();
        (raiz || document).querySelectorAll('[data-com-carrusel]').forEach(function (el) {
            if (el.dataset.comIniciado) return;
            el.dataset.comIniciado = '1';
            const car = bootstrap.Carousel.getOrCreateInstance(el, {
                interval: reducir ? false : INTERVALO,
                ride: reducir ? false : 'carousel',
                pause: 'hover',
                touch: true,
                wrap: true,
                keyboard: true
            });
            if (!reducir) car.cycle();
        });
    }

    document.addEventListener('visibilitychange', function () {
        if (!window.bootstrap || reducirMovimiento()) return;
        document.querySelectorAll('[data-com-carrusel]').forEach(function (el) {
            const car = bootstrap.Carousel.getInstance(el);
            if (!car) return;
            if (document.hidden) car.pause(); else car.cycle();
        });
    });

    // "Leer más": se muestra solo donde el texto plegable quedó cortado
    function revisarCortes(raiz) {
        (raiz || document).querySelectorAll('.com-card').forEach(function (t) {
            if (t.hidden || t.classList.contains('abierto')) return;
            const texto = t.querySelector('.com-plegable');
            const leer = t.querySelector('.com-leer');
            if (leer) leer.hidden = !texto || texto.scrollHeight <= texto.clientHeight + 2;
        });
    }

    document.addEventListener('click', function (ev) {
        const b = ev.target.closest && ev.target.closest('.com-leer');
        if (!b) return;
        const t = b.closest('.com-card');
        const abierto = t.classList.toggle('abierto');
        b.textContent = abierto ? 'Leer menos' : 'Leer más';
    });

    // Quita los carruseles de un bloque que se va a reemplazar (vista previa)
    function liberar(raiz) {
        if (!window.bootstrap) return;
        (raiz || document).querySelectorAll('[data-com-carrusel]').forEach(function (el) {
            const car = bootstrap.Carousel.getInstance(el);
            if (car) car.dispose();
        });
    }

    window.IntraComunicados = { iniciar: iniciar, revisarCortes: revisarCortes, liberar: liberar };

    // ==================== Aviso al entrar al hub ====================
    // Una vez por día y por navegador: se guarda en localStorage qué avisos (id + fecha de última
    // modificación) ya se mostraron hoy. No es por usuario: en los locales el usuario es compartido.
    // Si localStorage no está disponible, el aviso se muestra en cada entrada, sin errores.
    const CLAVE_VISTOS = 'intra_avisos_vistos';

    function hoyLocal() {
        const d = new Date();
        const dos = function (n) { return (n < 10 ? '0' : '') + n; };
        return d.getFullYear() + '-' + dos(d.getMonth() + 1) + '-' + dos(d.getDate());
    }

    function avisosVistos() {
        try {
            const v = JSON.parse(window.localStorage.getItem(CLAVE_VISTOS) || 'null');
            return v && v.dia === hoyLocal() && Array.isArray(v.vistos) ? v.vistos : [];
        } catch (e) {
            return [];
        }
    }

    function marcarAviso(clave) {
        try {
            const vistos = avisosVistos();
            if (vistos.indexOf(clave) === -1) vistos.push(clave);
            window.localStorage.setItem(CLAVE_VISTOS, JSON.stringify({ dia: hoyLocal(), vistos: vistos }));
        } catch (e) { /* sin localStorage: se vuelve a mostrar en la próxima entrada */ }
    }

    function iniciarAvisos() {
        const modalEl = document.getElementById('avisoHub');
        if (!modalEl || !window.bootstrap) return;

        const vistos = avisosVistos();
        const pasos = Array.from(modalEl.querySelectorAll('[data-aviso-paso]')).filter(function (p) {
            if (vistos.indexOf(p.dataset.clave) !== -1) {
                p.remove();
                return false;
            }
            return true;
        });
        if (!pasos.length) {
            modalEl.remove();
            return;
        }

        const contador = modalEl.querySelector('[data-aviso-contador]');
        const siguiente = modalEl.querySelector('[data-aviso-siguiente]');
        const entendido = modalEl.querySelector('[data-aviso-entendido]');
        const ver = modalEl.querySelector('[data-aviso-ver]');
        const cuerpo = modalEl.querySelector('.modal-body');
        let actual = 0;

        function mostrar(n) {
            actual = n;
            pasos.forEach(function (p, k) { p.hidden = k !== n; });
            contador.hidden = pasos.length < 2;
            contador.textContent = (n + 1) + ' de ' + pasos.length;
            siguiente.hidden = n >= pasos.length - 1;
            entendido.hidden = !siguiente.hidden;
            ver.href = pasos[n].dataset.link;
            marcarAviso(pasos[n].dataset.clave); // ya se vio: hoy no vuelve a aparecer
            iniciar(pasos[n]);
            cuerpo.scrollTop = 0;
        }

        siguiente.addEventListener('click', function () {
            mostrar(actual + 1);
            (siguiente.hidden ? entendido : siguiente).focus();
        });
        modalEl.addEventListener('hidden.bs.modal', function () { liberar(modalEl); });

        mostrar(0);
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciarAvisos);
    } else {
        iniciarAvisos();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { iniciar(document); });
    } else {
        iniciar(document);
    }
})();
