/*
 * Estructura XL - normalización y búsqueda de la Guía de Consultas (lado navegador)
 *
 * Es la MISMA regla que estructura/Class/Reglas.php, escrita en JS para que el resultado aparezca
 * mientras se escribe. No tiene datos propios: el mapa de tildes y las palabras vacías llegan de
 * PHP (Reglas::configJs() → window.ESTRUCTURA.reglas), así no hay dos listas que se separen.
 * Los mismos casos se prueban en PHP (tests/run.php) y acá (tests/paridad.html).
 *
 * Uso: const n = EstructuraNormalizar.crear(window.ESTRUCTURA.reglas);
 */
(function (global) {
    'use strict';

    function crear(reglas) {
        var tildes = reglas.tildes || {};
        var vacias = {};
        (reglas.palabrasVacias || []).forEach(function (p) { vacias[p] = true; });
        var minBusqueda = reglas.minBusqueda;
        var minRegistro = reglas.minRegistro;

        /** Reglas::clave(): minúsculas, sin tildes, separadores a un espacio, sin bordes. */
        function clave(texto) {
            var s = String(texto == null ? '' : texto).toLowerCase();
            s = s.replace(/[^\u0000-\u007f]/g, function (c) { return tildes.hasOwnProperty(c) ? tildes[c] : c; });
            return s.replace(/[^a-z0-9]+/g, ' ').trim();
        }

        /** Reglas::palabras(): palabras útiles (sin las vacías). */
        function palabras(texto) {
            var c = clave(texto);
            if (c === '') { return []; }
            return c.split(' ').filter(function (p) { return !vacias[p]; });
        }

        function normalizar(texto) { return palabras(texto).join(' '); }

        function caracteresUtiles(texto) { return normalizar(texto).replace(/ /g, '').length; }

        function textoBuscable(tema, palabrasClave, area) {
            return normalizar(tema + ' ' + (palabrasClave || '') + ' ' + area);
        }

        /** Todas las palabras de la consulta contenidas en el texto del tema ("imprim" ⊂ "imprime"). */
        function coincide(palabrasConsulta, texto) {
            if (!palabrasConsulta.length) { return false; }
            return palabrasConsulta.every(function (p) { return texto.indexOf(p) !== -1; });
        }

        /**
         * Reglas::buscar(). Cada tema necesita id_area, tema, palabras_clave y area; si trae
         * _texto (textoBuscable ya calculado) lo usa, para no normalizar 60 temas por tecla.
         */
        function buscar(consulta, temas, idArea) {
            if (caracteresUtiles(consulta) < minBusqueda) { return []; }
            var ps = palabras(consulta);
            return temas.filter(function (t) {
                if (idArea !== null && idArea !== undefined && Number(t.id_area) !== Number(idArea)) { return false; }
                var texto = t._texto !== undefined ? t._texto : textoBuscable(t.tema, t.palabras_clave, t.area);
                return coincide(ps, texto);
            });
        }

        /** Reglas::debeRegistrarBusqueda() */
        function debeRegistrar(texto, ultimaNormalizada) {
            if (caracteresUtiles(texto) < minRegistro) { return false; }
            return normalizar(texto) !== String(ultimaNormalizada == null ? '' : ultimaNormalizada);
        }

        return {
            clave: clave, palabras: palabras, normalizar: normalizar, caracteresUtiles: caracteresUtiles,
            textoBuscable: textoBuscable, coincide: coincide, buscar: buscar, debeRegistrar: debeRegistrar,
            minBusqueda: minBusqueda, minRegistro: minRegistro
        };
    }

    global.EstructuraNormalizar = { crear: crear };
})(window);
