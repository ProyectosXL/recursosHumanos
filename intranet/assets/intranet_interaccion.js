/*
 * Intranet XL - reacciones y comentarios de los comunicados (recursosHumanos/intranet/partials/comunicado_interaccion.php)
 * Se usa en la intranet y en el aviso al entrar al hub. Habla con recursosHumanos/intranet/interaccion.php.
 *
 * Reacciones: se marcan al instante (sin esperar al servidor). Tocar la misma la deshace; tocar
 * otra la cambia. Si el servidor falla, todo vuelve a como estaba y aparece un aviso. Si responde
 * bien, la pantalla toma sus contadores (pueden haber cambiado por reacciones de otros).
 * "Ver quiénes reaccionaron": despliega la lista debajo de las reacciones (para todos).
 * Comentarios: publicar (sin recargar), "Ver todos", borrar el propio (con confirmación) y, para
 * los responsables de carga, ocultar y volver a mostrar.
 * Los bloques con data-ejemplo (vista previa de la carga) no hacen nada.
 */
(function () {
    'use strict';

    const AVISO_REACCION = 'No pudimos registrar tu reacción. Probá de nuevo';

    function enviar(bloque, datos) {
        const f = new FormData();
        f.append('csrf', bloque.dataset.csrf || '');
        f.append('comunicado', bloque.dataset.id);
        Object.keys(datos).forEach(function (k) { f.append(k, datos[k]); });
        return fetch(bloque.dataset.url, { method: 'POST', body: f, credentials: 'same-origin' })
            .then(function (r) {
                return r.json().catch(function () {
                    return { ok: false, error: 'El servidor no respondió bien. Probá de nuevo.' };
                });
            })
            .catch(function () {
                return { ok: false, error: 'No hay conexión con el servidor. Probá de nuevo.' };
            });
    }

    function usable(bloque) {
        return bloque && !bloque.hasAttribute('data-ejemplo') && bloque.dataset.url;
    }

    // ==================== Reacciones ====================

    function leerReacciones(bloque) {
        const estado = { cantidades: {}, mia: null };
        bloque.querySelectorAll('[data-reaccion]').forEach(function (b) {
            estado.cantidades[b.dataset.reaccion] = parseInt(b.querySelector('.com-reaccion-n').textContent, 10) || 0;
            if (b.getAttribute('aria-pressed') === 'true') estado.mia = b.dataset.reaccion;
        });
        return estado;
    }

    function pintarReacciones(bloque, estado) {
        let totalReacciones = 0;
        Object.keys(estado.cantidades).forEach(function (k) { totalReacciones += estado.cantidades[k] || 0; });
        const abrir = bloque.querySelector('[data-com-quienes]');
        if (abrir) {
            abrir.hidden = totalReacciones === 0;
            if (totalReacciones === 0) cerrarQuienes(bloque);
        }
        bloque.querySelectorAll('[data-reaccion]').forEach(function (b) {
            const tipo = b.dataset.reaccion;
            const n = estado.cantidades[tipo] || 0;
            const mia = estado.mia === tipo;
            b.querySelector('.com-reaccion-n').textContent = n;
            b.classList.toggle('es-mia', mia);
            b.setAttribute('aria-pressed', mia ? 'true' : 'false');
            b.title = b.dataset.nombre + ': ' + n;
            b.setAttribute('aria-label', b.dataset.nombre + ': ' + n);
        });
    }

    function avisar(bloque, texto) {
        const aviso = bloque.querySelector('.com-inter-aviso');
        if (!aviso) return;
        aviso.textContent = texto;
        aviso.hidden = false;
        clearTimeout(aviso._timer);
        aviso._timer = setTimeout(function () { aviso.hidden = true; }, 6000);
    }

    function reaccionar(boton) {
        const bloque = boton.closest('[data-com-inter]');
        if (!usable(bloque)) return;
        const tipo = boton.dataset.reaccion;
        const antes = leerReacciones(bloque);
        const despues = { cantidades: Object.assign({}, antes.cantidades), mia: antes.mia };

        if (antes.mia === tipo) {
            despues.cantidades[tipo] = Math.max(0, despues.cantidades[tipo] - 1);
            despues.mia = null;
        } else {
            if (antes.mia) despues.cantidades[antes.mia] = Math.max(0, despues.cantidades[antes.mia] - 1);
            despues.cantidades[tipo] = (despues.cantidades[tipo] || 0) + 1;
            despues.mia = tipo;
        }
        pintarReacciones(bloque, despues);

        // Toques seguidos: cada uno se manda en orden, y solo el último decide qué queda en pantalla
        const turno = (bloque._turno || 0) + 1;
        bloque._turno = turno;
        bloque._cola = (bloque._cola || Promise.resolve()).then(function () {
            return enviar(bloque, { accion: 'reaccionar', tipo: tipo }).then(function (r) {
                if (r.ok && r.resumen) {
                    if (bloque._turno === turno) {
                        pintarReacciones(bloque, { cantidades: r.resumen.reacciones, mia: r.resumen.mia });
                        // Si la lista de quiénes está abierta, se actualiza con el cambio
                        const panel = bloque.querySelector('[data-com-quienes-panel]');
                        if (panel && !panel.hidden) cargarQuienes(bloque);
                    }
                } else {
                    pintarReacciones(bloque, antes);
                    avisar(bloque, AVISO_REACCION);
                }
            });
        });
    }

    // ==================== Quiénes reaccionaron ====================
    // Se pide al abrir (siempre fresca) y se despliega debajo de las reacciones: así funciona
    // también dentro del aviso emergente, que ya es una ventana.

    function pedirQuienes(url, csrf, id) {
        const f = new FormData();
        f.append('csrf', csrf || '');
        f.append('comunicado', id);
        f.append('accion', 'quienes');
        return fetch(url, { method: 'POST', body: f, credentials: 'same-origin' })
            .then(function (r) {
                return r.json().catch(function () {
                    return { ok: false, error: 'El servidor no respondió bien. Probá de nuevo.' };
                });
            })
            .catch(function () {
                return { ok: false, error: 'No hay conexión con el servidor. Probá de nuevo.' };
            });
    }

    function mostrarError(contenedor, texto) {
        contenedor.innerHTML = '';
        const d = document.createElement('div');
        d.className = 'com-quienes-vacio text-danger';
        d.textContent = texto;
        contenedor.appendChild(d);
    }

    function cargarQuienes(bloque) {
        const panel = bloque.querySelector('[data-com-quienes-panel]');
        const pedido = (panel._pedido || 0) + 1;
        panel._pedido = pedido;
        if (!panel.innerHTML.trim()) panel.textContent = 'Cargando…';
        pedirQuienes(bloque.dataset.url, bloque.dataset.csrf, bloque.dataset.id).then(function (r) {
            if (panel._pedido !== pedido || panel.hidden) return;
            if (r.ok) panel.innerHTML = r.html;
            else mostrarError(panel, r.error || 'No pudimos cargar la lista. Probá de nuevo.');
        });
    }

    function cerrarQuienes(bloque) {
        const panel = bloque.querySelector('[data-com-quienes-panel]');
        const abrir = bloque.querySelector('[data-com-quienes]');
        if (!panel) return;
        panel.hidden = true;
        panel.innerHTML = '';
        panel._pedido = (panel._pedido || 0) + 1;
        if (abrir) {
            abrir.setAttribute('aria-expanded', 'false');
            abrir.textContent = 'Ver quiénes reaccionaron';
        }
    }

    function alternarQuienes(boton) {
        const bloque = boton.closest('[data-com-inter]');
        if (!usable(bloque)) return;
        const panel = bloque.querySelector('[data-com-quienes-panel]');
        if (!panel.hidden) {
            cerrarQuienes(bloque);
            return;
        }
        panel.hidden = false;
        boton.setAttribute('aria-expanded', 'true');
        boton.textContent = 'Ocultar quiénes reaccionaron';
        cargarQuienes(bloque);
    }

    // Para el listado de la carga (ventana con la lista)
    window.IntraInteraccion = { pedirQuienes: pedirQuienes, mostrarError: mostrarError };

    // ==================== Comentarios ====================

    function total(bloque, n) {
        const t = bloque.querySelector('[data-com-total]');
        if (t && typeof n === 'number') t.textContent = n;
    }

    function errorComentario(form, texto) {
        const e = form.querySelector('.com-comentar-error');
        e.textContent = texto || '';
        e.hidden = !texto;
    }

    function actualizarCampo(form) {
        const campo = form.querySelector('textarea');
        const max = parseInt(campo.getAttribute('maxlength'), 10);
        const n = campo.value.replace(/\r\n/g, '\n').length;
        const cont = form.querySelector('[data-com-contador]');
        cont.textContent = n + ' / ' + max;
        cont.classList.toggle('excedido', n > max);
        form.querySelector('[type="submit"]').disabled = form._enviando || campo.value.trim() === '' || n > max;
    }

    function verTodos(bloque) {
        bloque.querySelectorAll('[data-plegado]').forEach(function (li) {
            li.hidden = false;
            li.removeAttribute('data-plegado');
        });
        const b = bloque.querySelector('[data-com-ver-todos]');
        if (b) b.remove();
    }

    function comentar(form) {
        const bloque = form.closest('[data-com-inter]');
        if (!usable(bloque) || form._enviando) return;
        const campo = form.querySelector('textarea');
        const texto = campo.value.trim();
        if (texto === '') {
            errorComentario(form, 'Escribí tu comentario.');
            return;
        }
        errorComentario(form, '');
        form._enviando = true;
        actualizarCampo(form);
        enviar(bloque, { accion: 'comentar', texto: texto }).then(function (r) {
            form._enviando = false;
            if (r.ok) {
                const lista = bloque.querySelector('[data-com-lista]');
                lista.insertAdjacentHTML('beforeend', r.html);
                campo.value = '';
                if (r.resumen) total(bloque, r.resumen.comentarios);
            } else {
                errorComentario(form, r.error || 'No pudimos publicar tu comentario. Probá de nuevo.');
            }
            actualizarCampo(form);
        });
    }

    function accionComentario(boton) {
        const bloque = boton.closest('[data-com-inter]');
        const li = boton.closest('[data-comentario]');
        if (!usable(bloque) || !li || li._enviando) return;
        const accion = boton.dataset.accionComentario;
        if (accion === 'borrar' && !window.confirm('¿Borrar tu comentario? Deja de verse para todos.')) return;

        li._enviando = true;
        boton.disabled = true;
        enviar(bloque, { accion: accion, comentario: li.dataset.comentario }).then(function (r) {
            li._enviando = false;
            boton.disabled = false;
            if (!r.ok) {
                window.alert(r.error || 'No pudimos guardar el cambio. Probá de nuevo.');
                return;
            }
            if (r.resumen) total(bloque, r.resumen.comentarios);
            if (accion === 'borrar') {
                li.remove();
                // Siguen a la vista los 3 más recientes: si había plegados, aparece el anterior
                const plegados = bloque.querySelectorAll('[data-plegado]');
                if (plegados.length) {
                    const ultimo = plegados[plegados.length - 1];
                    ultimo.hidden = false;
                    ultimo.removeAttribute('data-plegado');
                    if (plegados.length === 1) {
                        const b = bloque.querySelector('[data-com-ver-todos]');
                        if (b) b.remove();
                    }
                }
            } else if (r.html) {
                li.insertAdjacentHTML('afterend', r.html);
                li.remove();
            }
        });
    }

    // ==================== Eventos (delegados: sirven también para lo que se agrega después) ====================

    document.addEventListener('click', function (ev) {
        if (!ev.target.closest) return;
        const reaccion = ev.target.closest('[data-com-inter] [data-reaccion]');
        if (reaccion) {
            reaccionar(reaccion);
            return;
        }
        const quienes = ev.target.closest('[data-com-inter] [data-com-quienes]');
        if (quienes) {
            alternarQuienes(quienes);
            return;
        }
        const todos = ev.target.closest('[data-com-ver-todos]');
        if (todos) {
            verTodos(todos.closest('[data-com-inter]'));
            return;
        }
        const acc = ev.target.closest('[data-com-inter] [data-accion-comentario]');
        if (acc) accionComentario(acc);
    });

    document.addEventListener('submit', function (ev) {
        const form = ev.target.closest && ev.target.closest('[data-com-comentar]');
        if (!form) return;
        ev.preventDefault();
        comentar(form);
    });

    document.addEventListener('input', function (ev) {
        const form = ev.target.closest && ev.target.closest('[data-com-comentar]');
        if (form && ev.target.matches('textarea')) {
            errorComentario(form, '');
            actualizarCampo(form);
        }
    });

    // Ctrl + Enter publica (Enter solo hace un salto de línea, que se respeta)
    document.addEventListener('keydown', function (ev) {
        if (ev.key !== 'Enter' || !(ev.ctrlKey || ev.metaKey)) return;
        const form = ev.target.closest && ev.target.closest('[data-com-comentar]');
        if (form && ev.target.matches('textarea')) {
            ev.preventDefault();
            comentar(form);
        }
    });
})();
