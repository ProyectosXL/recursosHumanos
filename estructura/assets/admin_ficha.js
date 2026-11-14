/*
 * Ficha de un área - tabla de temas editable y guardado.
 *
 * La validación manda en el servidor (Class/Ficha.php): acá sólo se arma lo que se manda, se
 * explica lo que falta y se bloquea el botón mientras se guarda. Lo único que se frena antes de
 * enviar es una persona escrita que no está en la lista de Internos: sin su ID no hay nada que
 * mandar, y el motivo se ve al lado del campo.
 *
 * Personas: el campo muestra el nombre y se resuelve al ID de Internos por nombre (sin mayúsculas
 * ni tildes, con la misma clave que el servidor). Una persona ya guardada que hoy está inactiva
 * se conserva si no se toca el campo, con su aviso.
 */
(function () {
    'use strict';

    var CFG = window.ESTRUCTURA || {};
    var A = window.EstructuraAdmin;
    var n = EstructuraNormalizar.crear(CFG.reglas || {});
    var ficha = CFG.ficha || { temas: [] };

    var porClave = {};
    (CFG.personas || []).forEach(function (p) {
        var k = n.clave(p.nombre);
        (porClave[k] = porClave[k] || []).push(p.id);
    });

    var $form = document.getElementById('fichaForm');
    if (!$form) { return; }
    var $cuerpo = document.getElementById('temasCuerpo');
    var $vacio = document.getElementById('temasVacio');
    var $guardar = document.getElementById('guardarFicha');

    var clave = 0;
    var filas = (ficha.temas || []).map(function (t) {
        return {
            key: ++clave, id: t.id, tema: t.tema, equipo: !!t.equipo, palabras: t.palabras_clave || '',
            refTexto: t.referente ? t.referente.nombre : '',
            refGuardado: t.referente || null
        };
    });
    var quitar = [];

    /* ---------------------------------------------------------------- Personas */

    /** {id} | {id:null} (vacío) | {error} si el texto no es una persona activa de la lista */
    function resolverPersona(texto, guardada) {
        texto = (texto || '').trim();
        if (texto === '') { return { id: null }; }
        if (guardada && texto === guardada.nombre) { return { id: guardada.id }; }
        var ids = porClave[n.clave(texto)] || [];
        if (ids.length === 1) { return { id: ids[0] }; }
        if (ids.length > 1) { return { error: 'Hay más de una persona activa con ese nombre en Internos: avisá a quien carga Internos.' }; }
        return { error: 'Elegí una persona activa de Internos de la lista (o dejalo vacío).' };
    }

    function prepararPersona(input, guardada) {
        var aviso = document.getElementById(input.id + '_aviso');
        input.value = guardada ? guardada.nombre : '';
        function actualizarAviso() {
            var sigueIgual = guardada && input.value.trim() === guardada.nombre;
            aviso.textContent = sigueIgual && guardada.aviso ? '⚠ ' + guardada.aviso : '';
        }
        input.addEventListener('input', actualizarAviso);
        actualizarAviso();
    }

    var personasArea = {
        gerente: { input: document.getElementById('f_gerente'), guardada: ficha.gerente },
        jefe: { input: document.getElementById('f_jefe'), guardada: ficha.jefe }
    };
    prepararPersona(personasArea.gerente.input, ficha.gerente);
    prepararPersona(personasArea.jefe.input, ficha.jefe);

    /* ---------------------------------------------------------------- Tabla de temas */

    function leerFilas() {
        filas.forEach(function (f) {
            var tr = $cuerpo.querySelector('tr[data-key="' + f.key + '"]');
            if (!tr) { return; }
            f.tema = tr.querySelector('[data-campo="tema"]').value;
            f.refTexto = tr.querySelector('[data-campo="referente"]').value;
            f.equipo = tr.querySelector('[data-campo="equipo"]').checked;
            f.palabras = tr.querySelector('[data-campo="palabras"]').value;
        });
    }

    function filaHtml(f, i) {
        var e = A.esc;
        var nombre = f.tema.trim() || 'tema nuevo';
        var aviso = (!f.equipo && f.refGuardado && f.refGuardado.aviso && f.refTexto === f.refGuardado.nombre) ? '⚠ ' + f.refGuardado.aviso : '';
        return '<tr data-key="' + f.key + '">'
            + '<td><label class="visually-hidden" for="t_tema_' + f.key + '">Tema</label>'
            +   '<input type="text" class="form-control" id="t_tema_' + f.key + '" data-campo="tema" maxlength="150" value="' + e(f.tema) + '">'
            +   '<div class="invalid-feedback" data-error-tema="tema"></div></td>'
            + '<td class="adm-ref">'
            +   '<label class="visually-hidden" for="t_ref_' + f.key + '">Referente de ' + e(nombre) + '</label>'
            +   '<input type="text" class="form-control" id="t_ref_' + f.key + '" data-campo="referente" list="personasActivas" autocomplete="off"'
            +     ' placeholder="A definir" value="' + e(f.equipo ? '' : f.refTexto) + '"' + (f.equipo ? ' disabled' : '') + '>'
            +   '<div class="form-check">'
            +     '<input class="form-check-input" type="checkbox" id="t_eq_' + f.key + '" data-campo="equipo"' + (f.equipo ? ' checked' : '') + '>'
            +     '<label class="form-check-label" for="t_eq_' + f.key + '">Equipo del área</label>'
            +   '</div>'
            +   '<div class="adm-aviso-persona" aria-live="polite">' + e(aviso) + '</div>'
            +   '<div class="invalid-feedback" data-error-tema="referente"></div></td>'
            + '<td><label class="visually-hidden" for="t_pal_' + f.key + '">Palabras clave de ' + e(nombre) + '</label>'
            +   '<input type="text" class="form-control" id="t_pal_' + f.key + '" data-campo="palabras" maxlength="500" value="' + e(f.palabras) + '"'
            +     ' placeholder="me falta, no llegó">'
            +   '<div class="invalid-feedback" data-error-tema="palabras_clave"></div></td>'
            + '<td class="text-end"><div class="adm-acciones">'
            +   '<button type="button" class="est-btn est-btn--chico est-btn--icono" data-mover="-1" aria-label="Subir ' + e(nombre) + '"' + (i === 0 ? ' disabled' : '') + '><i class="bi bi-arrow-up" aria-hidden="true"></i></button>'
            +   '<button type="button" class="est-btn est-btn--chico est-btn--icono" data-mover="1" aria-label="Bajar ' + e(nombre) + '"' + (i === filas.length - 1 ? ' disabled' : '') + '><i class="bi bi-arrow-down" aria-hidden="true"></i></button>'
            +   '<button type="button" class="est-btn est-btn--chico est-btn--icono est-btn--peligro" data-quitar aria-label="Quitar ' + e(nombre) + '"><i class="bi bi-x-lg" aria-hidden="true"></i></button>'
            + '</div></td>'
            + '</tr>';
    }

    function render(enfocar) {
        $cuerpo.innerHTML = filas.map(filaHtml).join('');
        $vacio.hidden = filas.length > 0;
        if (enfocar) {
            var el = $cuerpo.querySelector(enfocar);
            if (el && !el.disabled) { el.focus(); }
        }
    }

    document.getElementById('agregarTema').addEventListener('click', function () {
        leerFilas();
        filas.push({ key: ++clave, id: null, tema: '', equipo: false, palabras: '', refTexto: '', refGuardado: null });
        render('tr[data-key="' + clave + '"] [data-campo="tema"]');
    });

    $cuerpo.addEventListener('click', function (ev) {
        var b = ev.target.closest('button');
        if (!b) { return; }
        var tr = b.closest('tr');
        var key = Number(tr.getAttribute('data-key'));
        var i = filas.findIndex(function (f) { return f.key === key; });
        leerFilas();
        if (b.hasAttribute('data-mover')) {
            var j = i + Number(b.getAttribute('data-mover'));
            if (j < 0 || j >= filas.length) { return; }
            var tmp = filas[i]; filas[i] = filas[j]; filas[j] = tmp;
            render('tr[data-key="' + key + '"] [data-mover="' + b.getAttribute('data-mover') + '"]');
        } else if (b.hasAttribute('data-quitar')) {
            var f = filas[i];
            if (f.id && !window.confirm('¿Quitar el tema "' + f.tema + '"? Deja de verse en la consulta al guardar. Se puede reactivar desde "Temas quitados".')) {
                return;
            }
            if (f.id) { quitar.push(f.id); }
            filas.splice(i, 1);
            render('#agregarTema');
        }
    });

    $cuerpo.addEventListener('change', function (ev) {
        if (ev.target.getAttribute('data-campo') === 'equipo') {
            // Persona o equipo, nunca las dos: marcar "Equipo" vacía y bloquea la persona
            var ref = ev.target.closest('td').querySelector('[data-campo="referente"]');
            ref.disabled = ev.target.checked;
            if (ev.target.checked) { ref.value = ''; }
        }
    });

    /* ---------------------------------------------------------------- Guardar */

    function limpiarErrores() {
        Array.prototype.forEach.call($form.querySelectorAll('.is-invalid'), function (el) {
            el.classList.remove('is-invalid');
            el.removeAttribute('aria-invalid');
        });
        Array.prototype.forEach.call($form.querySelectorAll('.invalid-feedback'), function (el) { el.textContent = ''; });
        document.getElementById('admMensaje').hidden = true;
    }

    function marcar(contenedor, selectorError, campoInput, texto) {
        var div = contenedor.querySelector(selectorError);
        if (div) { div.textContent = texto; div.style.display = 'block'; }
        if (campoInput) {
            campoInput.classList.add('is-invalid');
            campoInput.setAttribute('aria-invalid', 'true');
        }
    }

    var inputsArea = { nombre: 'f_nombre', resuelve: 'f_resuelve', mail: 'f_mail', celular: 'f_celular', horario: 'f_horario',
        id_gerente: 'f_gerente', id_jefe: 'f_jefe' };

    function mostrarErrores(errores, erroresTemas) {
        var primero = null;
        Object.keys(errores || {}).forEach(function (campo) {
            var input = inputsArea[campo] ? document.getElementById(inputsArea[campo]) : null;
            if (campo === 'general') { A.mensaje(errores[campo], 'error'); return; }
            marcar($form, '[data-error="' + campo + '"]', input, errores[campo]);
            primero = primero || input;
        });
        Object.keys(erroresTemas || {}).forEach(function (i) {
            var f = filas[Number(i)];
            var tr = f ? $cuerpo.querySelector('tr[data-key="' + f.key + '"]') : null;
            if (!tr) { return; }
            Object.keys(erroresTemas[i]).forEach(function (campo) {
                var input = tr.querySelector('[data-campo="' + (campo === 'palabras_clave' ? 'palabras' : campo) + '"]');
                marcar(tr, '[data-error-tema="' + campo + '"]', input, erroresTemas[i][campo]);
                primero = primero || input;
            });
        });
        if (primero) { primero.focus(); }
        A.mensaje('No se guardó nada: revisá los campos marcados.', 'error');
    }

    $form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        limpiarErrores();
        leerFilas();

        var errores = {};
        var erroresTemas = {};
        var gerente = resolverPersona(personasArea.gerente.input.value, personasArea.gerente.guardada);
        var jefe = resolverPersona(personasArea.jefe.input.value, personasArea.jefe.guardada);
        if (gerente.error) { errores.id_gerente = gerente.error; }
        if (jefe.error) { errores.id_jefe = jefe.error; }

        var temas = filas.map(function (f, i) {
            var ref = f.equipo ? { id: null } : resolverPersona(f.refTexto, f.refGuardado);
            if (ref.error) { erroresTemas[i] = { referente: ref.error }; }
            return { id: f.id, tema: f.tema, equipo: f.equipo, id_referente: ref.id || null, palabras_clave: f.palabras };
        });
        if (Object.keys(errores).length || Object.keys(erroresTemas).length) {
            mostrarErrores(errores, erroresTemas);
            return;
        }
        // Los quitados van al final: así el índice de cada fila visible coincide con el de los errores
        quitar.forEach(function (id) { temas.push({ id: id, quitar: true }); });

        var color = $form.querySelector('input[name="color"]:checked');
        var datos = {
            id: ficha.id,
            nombre: document.getElementById('f_nombre').value,
            color: color ? color.value : '',
            resuelve: document.getElementById('f_resuelve').value,
            mail: document.getElementById('f_mail').value,
            celular: document.getElementById('f_celular').value,
            horario: document.getElementById('f_horario').value,
            id_gerente: gerente.id,
            id_jefe: jefe.id,
            temas: temas
        };

        $guardar.disabled = true;
        $guardar.textContent = 'Guardando…';
        A.enviar('guardarFicha', { id: ficha.id || '', datos: datos }).then(function (r) {
            if (r.ok) {
                window.location.href = CFG.urlFicha + '?id=' + encodeURIComponent(r.id) + '&guardada=1';
                return;
            }
            $guardar.disabled = false;
            $guardar.textContent = 'Guardar ficha';
            if (r.errores || r.errores_temas) {
                mostrarErrores(r.errores, r.errores_temas);
            } else {
                A.mensaje(r.mensaje || 'No se pudo guardar.', 'error');
            }
        });
    });

    /* ---------------------------------------------------------------- Reactivar un tema quitado */

    document.addEventListener('click', function (ev) {
        var b = ev.target.closest('[data-reactivar-tema]');
        if (!b) { return; }
        if (!window.confirm('¿Reactivar este tema? Se agrega al final de la lista. Si hiciste otros cambios sin guardar, se pierden.')) { return; }
        b.disabled = true;
        A.enviar('reactivarTema', { id: b.getAttribute('data-reactivar-tema') }).then(function (r) {
            if (r.ok) { window.location.reload(); return; }
            b.disabled = false;
            A.mensaje(r.mensaje || 'No se pudo reactivar.', 'error');
        });
    });

    render();
})();
