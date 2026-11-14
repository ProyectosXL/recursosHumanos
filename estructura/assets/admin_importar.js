/*
 * Importar listado (Administrar áreas).
 *
 * El navegador lee el Excel con SheetJS y manda las filas tal cual a ajax/admin.php: TODA la
 * validación y la resolución las hace el servidor (Class/Importacion.php). Acá sólo se muestra
 * la vista previa que devuelve y se habilita "Confirmar importación" cuando no hay errores.
 * Al confirmar se mandan las mismas filas: el servidor vuelve a calcular todo antes de guardar.
 */
(function () {
    'use strict';

    var A = window.EstructuraAdmin;
    var e = A.esc;
    var $panel = document.getElementById('importar');
    if (!$panel) { return; }
    var $abrir = document.getElementById('abrirImportar');
    var $archivo = document.getElementById('archivoImportar');
    var $previa = document.getElementById('impPrevia');
    var $confirmar = document.getElementById('confirmarImportacion');
    var $cancelar = document.getElementById('cancelarImportacion');
    var actual = null;   // { filas, archivo } de la última vista previa sin errores

    function abrir(si) {
        $panel.hidden = !si;
        $abrir.setAttribute('aria-expanded', si ? 'true' : 'false');
        if (si) { $panel.scrollIntoView({ block: 'start' }); }
    }

    $abrir.addEventListener('click', function () { abrir($panel.hidden); });

    $cancelar.addEventListener('click', function () {
        actual = null;
        $archivo.value = '';
        $previa.innerHTML = '';
        $confirmar.disabled = true;
        abrir(false);
        $abrir.focus();
    });

    /* ---------------------------------------------------------------- Plantilla */

    document.getElementById('descargarPlantilla').addEventListener('click', function () {
        var b = this;
        b.disabled = true;
        A.enviar('plantilla', {}).then(function (r) {
            b.disabled = false;
            if (!r.ok) { A.mensaje(r.mensaje || 'No se pudo armar la plantilla.', 'error'); return; }
            var ws = XLSX.utils.aoa_to_sheet(r.filas);
            ws['!cols'] = [{ wch: 18 }, { wch: 7 }, { wch: 10 }, { wch: 45 }, { wch: 28 }, { wch: 16 }, { wch: 28 },
                { wch: 22 }, { wch: 22 }, { wch: 32 }, { wch: 22 }, { wch: 50 }];
            var wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Directorio');
            XLSX.writeFile(wb, 'directorio_plantilla_' + new Date().toISOString().slice(0, 10) + '.xlsx');
        });
    });

    /* ---------------------------------------------------------------- Vista previa */

    function lista(titulo, items, clase) {
        if (!items.length) { return ''; }
        return '<div><h3>' + e(titulo) + '</h3><ul' + (clase ? ' class="' + clase + '"' : '') + '>'
            + items.map(function (i) { return '<li>' + i + '</li>'; }).join('') + '</ul></div>';
    }

    function cambios(c) {
        return c.map(function (x) {
            return '<div class="adm-cambio">' + e(x.campo) + ': '
                + (x.de !== '' ? '<del>' + e(x.de) + '</del>' : '<span class="est-pendiente">vacío</span>')
                + ' → <ins>' + e(x.a) + '</ins></div>';
        }).join('');
    }

    function fila(n) { return n ? 'Fila ' + n + ': ' : ''; }

    function mostrarPlan(plan) {
        var r = plan.resumen;
        var html = '<div class="adm-resumen">'
            + '<span class="est-badge est-badge--off">' + r.areas_crear + ' áreas nuevas</span>'
            + '<span class="est-badge est-badge--off">' + r.areas_actualizar + ' áreas con cambios</span>'
            + '<span class="est-badge est-badge--off">' + r.temas_crear + ' temas nuevos</span>'
            + '<span class="est-badge est-badge--off">' + r.temas_actualizar + ' temas con cambios</span>'
            + '<span class="est-badge est-badge--off">' + plan.omitidas + ' filas omitidas</span>'
            + '</div>';

        if (plan.errores.length) {
            html += '<div class="est-aviso est-aviso--error"><strong>No se puede importar:</strong> corregí el archivo y volvé a subirlo. '
                + 'No se guardó nada.</div>';
            html += lista('Errores', plan.errores.map(function (x) { return e(fila(x.fila) + x.motivo); }), 'adm-lista-error');
        }
        html += lista('Avisos (no frenan la importación)', plan.avisos.map(function (x) { return e(fila(x.fila) + x.motivo); }), 'adm-lista-aviso');
        html += lista('Áreas que se crean', plan.areas.filter(function (a) { return a.accion === 'crear'; })
            .map(function (a) { return '<strong>' + e(a.nombre) + '</strong>'; }));
        html += lista('Áreas que se actualizan', plan.areas.filter(function (a) { return a.accion === 'actualizar'; })
            .map(function (a) { return '<strong>' + e(a.nombre) + '</strong>' + cambios(a.cambios); }));
        html += lista('Temas que se crean', plan.temas.filter(function (t) { return t.accion === 'crear'; })
            .map(function (t) { return e(t.area + ' › ' + t.tema); }));
        html += lista('Temas que se actualizan', plan.temas.filter(function (t) { return t.accion === 'actualizar'; })
            .map(function (t) { return '<strong>' + e(t.area + ' › ' + t.tema) + '</strong>' + cambios(t.cambios); }));

        var noInc = plan.no_incluidos.areas.map(function (a) { return 'Área ' + e(a); })
            .concat(plan.no_incluidos.temas.map(function (t) { return e(t); }));
        if (noInc.length) {
            html += '<details><summary>No incluidos en el archivo, no se tocan (' + noInc.length + ')</summary><ul><li>'
                + noInc.join('</li><li>') + '</li></ul></details>';
        }
        var hayCambios = r.areas_crear + r.areas_actualizar + r.temas_crear + r.temas_actualizar > 0;
        if (!plan.errores.length && !hayCambios) {
            html += '<div class="est-aviso">El archivo no trae cambios: no hay nada para importar.</div>';
        }
        $previa.innerHTML = html;
        return !plan.errores.length && hayCambios;
    }

    function leerExcel(archivo) {
        return new Promise(function (ok, mal) {
            var lector = new FileReader();
            lector.onload = function (ev) {
                try {
                    var wb = XLSX.read(new Uint8Array(ev.target.result), { type: 'array' });
                    var ws = wb.Sheets[wb.SheetNames[0]];
                    // raw:false: las celdas llegan como se ven (un celular numérico no pierde dígitos)
                    ok(XLSX.utils.sheet_to_json(ws, { header: 1, raw: false, defval: '' }));
                } catch (err) {
                    mal(err);
                }
            };
            lector.onerror = mal;
            lector.readAsArrayBuffer(archivo);
        });
    }

    $archivo.addEventListener('change', function () {
        actual = null;
        $confirmar.disabled = true;
        var archivo = $archivo.files[0];
        if (!archivo) { $previa.innerHTML = ''; return; }
        $previa.innerHTML = '<p class="est-etiqueta">Leyendo el archivo…</p>';
        leerExcel(archivo).then(function (filas) {
            return A.enviar('previsualizarImportacion', { filas: filas, archivo: archivo.name }).then(function (r) {
                if (!r.plan) { $previa.innerHTML = ''; A.mensaje(r.mensaje || 'No se pudo revisar el archivo.', 'error'); return; }
                if (mostrarPlan(r.plan)) {
                    actual = { filas: filas, archivo: archivo.name };
                    $confirmar.disabled = false;
                }
            });
        }).catch(function () {
            $previa.innerHTML = '<div class="est-aviso est-aviso--error">No se pudo leer el archivo. Tiene que ser un Excel (.xlsx) como la plantilla.</div>';
        });
    });

    /* ---------------------------------------------------------------- Confirmar */

    $confirmar.addEventListener('click', function () {
        if (!actual) { return; }
        $confirmar.disabled = true;
        $confirmar.textContent = 'Importando…';
        A.enviar('confirmarImportacion', actual).then(function (r) {
            $confirmar.textContent = 'Confirmar importación';
            if (r.ok && r.confirmada) {
                var s = r.resumen;
                $previa.innerHTML = '<div class="est-aviso est-aviso--ok" role="status"><strong>Importación guardada.</strong> '
                    + s.areas_crear + ' áreas nuevas, ' + s.areas_actualizar + ' áreas con cambios, '
                    + s.temas_crear + ' temas nuevos y ' + s.temas_actualizar + ' temas con cambios. '
                    + '<button type="button" class="est-btn est-btn--chico" onclick="location.reload()">Ver el listado actualizado</button></div>';
                actual = null;
                $archivo.value = '';
                return;
            }
            if (r.plan) {
                // Algo cambió desde la vista previa y ahora el archivo tiene errores: se muestra de nuevo
                mostrarPlan(r.plan);
                actual = null;
                return;
            }
            $confirmar.disabled = false;
            A.mensaje(r.mensaje || 'No se pudo importar. No se guardó nada.', 'error');
        });
    });
})();
