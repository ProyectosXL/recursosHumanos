/*
 * Intranet XL - carga de un comunicado (recursosHumanos/intranet/admin.php)
 *
 * 1. Imágenes y PDF: cada archivo se sube apenas se elige o se suelta, de a uno
 *    (recursosHumanos/intranet/archivo_subir.php), y queda en el formulario como campos archivos[kN][...]:
 *    el orden en pantalla es el orden en que se guardan. Si un archivo no cumple, se rechaza solo
 *    ese con el motivo y los demás siguen. Se reordenan arrastrando o con los botones de flecha
 *    (teclado y celular). En imágenes: portada (estrella) y epígrafe; en PDF: nombre para mostrar.
 *    Zona: [data-archivos] con data-tipo (IMAGEN|PDF), data-max, data-peso-max, data-url y
 *    data-items (JSON con los que ya tiene el comunicado o los que vuelven tras un error).
 * 2. Esquema: muestra los campos que corresponden a cada esquema.
 * 3. Barra de formato: marcas sencillas en el texto.
 * 4. Vista previa: ventana que pide el comunicado armado al servidor (sin guardar nada).
 */
(function () {
    'use strict';

    let clave = 0; // para nombres únicos de campos: archivos[k1][id], archivos[k2][id]...
    const FORMATOS_IMAGEN = ['image/jpeg', 'image/png', 'image/webp'];
    const MAX_EPIGRAFE = 150;
    const MAX_NOMBRE_PDF = 120;

    function el(tag, clase, texto) {
        const n = document.createElement(tag);
        if (clase) n.className = clase;
        if (texto !== undefined) n.textContent = texto;
        return n;
    }

    function oculto(nombre, valor) {
        const i = el('input');
        i.type = 'hidden';
        i.name = nombre;
        i.value = valor;
        return i;
    }

    function boton(clase, html, etiqueta) {
        const b = el('button', clase);
        b.type = 'button';
        b.innerHTML = html;
        if (etiqueta) {
            b.title = etiqueta;
            b.setAttribute('aria-label', etiqueta);
        }
        return b;
    }

    function pesoTexto(bytes) {
        if (bytes >= 1048576) return (bytes / 1048576).toFixed(1).replace('.', ',') + ' MB';
        return Math.max(1, Math.round(bytes / 1024)) + ' KB';
    }

    function conArchivos(ev) {
        return ev.dataTransfer && Array.prototype.indexOf.call(ev.dataTransfer.types || [], 'Files') !== -1;
    }

    // ==================== Imágenes y PDF ====================
    function iniciar(zona) {
        const tipo = zona.dataset.tipo;
        const esImagen = tipo === 'IMAGEN';
        const max = parseInt(zona.dataset.max, 10);
        const pesoMax = parseInt(zona.dataset.pesoMax, 10);
        const form = zona.closest('form');
        const input = zona.querySelector('input[type="file"]');
        const elegir = zona.querySelector('.intra-archivos-elegir');
        const soltar = zona.querySelector('[data-soltar]');
        const lista = zona.querySelector('.intra-archivos-lista');
        const errores = zona.querySelector('.intra-archivos-errores');
        const tope = zona.querySelector('.intra-archivos-tope');
        const textoTope = tope.textContent.trim();
        let cola = Promise.resolve();
        let arrastrado = null;

        function items() {
            return Array.from(lista.querySelectorAll('.intra-archivo'));
        }

        function ocupados() {
            return items().length;
        }

        // Una sola portada: si no hay (la primera que se sube, o se quitó la que era), la primera
        function asegurarPortada() {
            if (!esImagen) return;
            const todos = items();
            if (todos.length && !todos.some(function (li) { return li.querySelector('[name$="[portada]"]').value === '1'; })) {
                marcarPortada(todos[0]);
            }
        }

        function marcarPortada(elegida) {
            items().forEach(function (li) {
                const es = li === elegida;
                li.querySelector('[name$="[portada]"]').value = es ? '1' : '0';
                li.classList.toggle('es-portada', es);
                const b = li.querySelector('.intra-portada');
                b.setAttribute('aria-pressed', es ? 'true' : 'false');
                b.innerHTML = es
                    ? '<i class="fa-solid fa-star"></i> Portada'
                    : '<i class="fa-regular fa-star"></i> Usar como portada';
            });
        }

        // Flechas: deshabilitadas en los extremos
        function actualizarFlechas() {
            const todos = items();
            todos.forEach(function (li, i) {
                li.querySelector('[data-mover="-1"]').disabled = i === 0;
                li.querySelector('[data-mover="1"]').disabled = i === todos.length - 1;
            });
        }

        function actualizar() {
            const n = ocupados();
            elegir.disabled = n >= max;
            tope.hidden = n < max;
            soltar.classList.toggle('lleno', n >= max);
            asegurarPortada();
            actualizarFlechas();
            // Aviso para el selector de esquema (vuelve a «Solo texto» si se quitan todas las imágenes)
            zona.dispatchEvent(new CustomEvent('intra:cambio', { detail: { cantidad: n } }));
        }

        zona.quitarTodos = function () {
            items().forEach(function (li) { li.remove(); });
            actualizar();
        };

        function error(nombre, motivo) {
            const li = el('li');
            li.appendChild(el('strong', '', nombre + ': '));
            li.appendChild(document.createTextNode(motivo));
            errores.appendChild(li);
        }

        function mover(li, paso) {
            const destino = paso < 0 ? li.previousElementSibling : li.nextElementSibling;
            if (!destino || !destino.classList.contains('intra-archivo')) return;
            if (paso < 0) lista.insertBefore(li, destino); else lista.insertBefore(destino, li);
            actualizarFlechas();
            const b = li.querySelector('[data-mover="' + paso + '"]');
            (b && !b.disabled ? b : li.querySelector('[data-mover="' + (-paso) + '"]')).focus();
        }

        function agregar(a, reemplazar) {
            const k = 'k' + (++clave);
            const li = el('li', 'intra-archivo' + (esImagen ? ' intra-archivo-img' : ''));
            li.draggable = true;
            li.dataset.id = a.id;
            li.appendChild(oculto('archivos[' + k + '][id]', a.id));

            const acciones = el('div', 'intra-archivo-acciones');
            const izq = boton('intra-btn-mini', '<i class="fa-solid ' + (esImagen ? 'fa-arrow-left' : 'fa-arrow-up') + '"></i>',
                esImagen ? 'Mover antes' : 'Subir');
            izq.dataset.mover = '-1';
            const der = boton('intra-btn-mini', '<i class="fa-solid ' + (esImagen ? 'fa-arrow-right' : 'fa-arrow-down') + '"></i>',
                esImagen ? 'Mover después' : 'Bajar');
            der.dataset.mover = '1';
            [izq, der].forEach(function (b) {
                b.addEventListener('click', function () { mover(li, parseInt(b.dataset.mover, 10)); });
            });
            const ver = el('a', 'intra-btn-mini');
            ver.href = a.url;
            ver.target = '_blank';
            ver.rel = 'noopener';
            ver.title = 'Ver';
            ver.setAttribute('aria-label', 'Ver ' + a.nombre_original);
            ver.innerHTML = '<i class="fa-regular fa-eye"></i>';
            const quitar = boton('intra-btn-mini text-danger', '<i class="fa-solid fa-trash-can"></i>', 'Quitar ' + a.nombre_original);
            quitar.addEventListener('click', function () {
                li.remove();
                actualizar();
            });
            acciones.append(izq, der, ver, quitar);

            if (esImagen) {
                li.appendChild(oculto('archivos[' + k + '][portada]', a.es_portada ? '1' : '0'));
                const foto = el('div', 'intra-archivo-foto');
                const img = el('img');
                img.src = a.url_mini;
                img.alt = '';
                img.draggable = false;
                foto.appendChild(img);
                const portada = boton('intra-portada', '');
                portada.addEventListener('click', function () { marcarPortada(li); });
                foto.appendChild(portada);
                li.appendChild(foto);

                const epigrafe = el('input', 'form-control form-control-sm');
                epigrafe.name = 'archivos[' + k + '][epigrafe]';
                epigrafe.maxLength = MAX_EPIGRAFE;
                epigrafe.placeholder = 'Epígrafe (opcional)';
                epigrafe.value = a.epigrafe || '';
                epigrafe.setAttribute('aria-label', 'Epígrafe de ' + a.nombre_original);
                li.appendChild(epigrafe);
                li.appendChild(el('div', 'intra-archivo-peso', a.pesos));
                li.appendChild(acciones);
            } else {
                const ico = el('div', 'intra-archivo-icono');
                ico.innerHTML = '<i class="fa-regular fa-file-pdf"></i>';
                li.appendChild(ico);
                const datos = el('div', 'intra-archivo-datos');
                const nombre = el('input', 'form-control form-control-sm');
                nombre.name = 'archivos[' + k + '][nombre]';
                nombre.maxLength = MAX_NOMBRE_PDF;
                nombre.required = true;
                nombre.value = a.nombre || '';
                nombre.setAttribute('aria-label', 'Nombre para mostrar de ' + a.nombre_original);
                datos.appendChild(nombre);
                datos.appendChild(el('div', 'intra-archivo-nombre', a.nombre_original + ' · ' + a.pesos));
                li.appendChild(datos);
                li.appendChild(acciones);
            }

            // Reordenar arrastrando (dentro de la misma lista)
            li.addEventListener('dragstart', function (ev) {
                if (ev.target.closest('input')) { ev.preventDefault(); return; }
                arrastrado = li;
                li.classList.add('arrastrando');
                ev.dataTransfer.effectAllowed = 'move';
                ev.dataTransfer.setData('text/plain', String(a.id));
            });
            li.addEventListener('dragend', function () {
                li.classList.remove('arrastrando');
                arrastrado = null;
                actualizarFlechas();
            });

            if (reemplazar) {
                lista.replaceChild(li, reemplazar);
            } else {
                lista.appendChild(li);
            }
            if (esImagen && a.es_portada) marcarPortada(li);
            else if (esImagen) li.querySelector('.intra-portada').innerHTML = '<i class="fa-regular fa-star"></i> Usar como portada';
            actualizar();
        }

        lista.addEventListener('dragover', function (ev) {
            if (!arrastrado || arrastrado.parentNode !== lista) return;
            ev.preventDefault();
            const sobre = ev.target.closest('.intra-archivo');
            if (!sobre || sobre === arrastrado) return;
            const r = sobre.getBoundingClientRect();
            const antes = esImagen ? ev.clientX < r.left + r.width / 2 : ev.clientY < r.top + r.height / 2;
            lista.insertBefore(arrastrado, antes ? sobre : sobre.nextSibling);
        });

        function subir(archivo) {
            if (ocupados() >= max) {
                error(archivo.name, textoTope + '.');
                return Promise.resolve();
            }
            // Controles rápidos antes de subir; el servidor vuelve a validar todo
            if (esImagen && archivo.type && FORMATOS_IMAGEN.indexOf(archivo.type) === -1) {
                error(archivo.name, 'Formato no permitido: subí JPG, PNG o WEBP.');
                return Promise.resolve();
            }
            if (!esImagen && archivo.type && archivo.type !== 'application/pdf') {
                error(archivo.name, 'El archivo no es un PDF.');
                return Promise.resolve();
            }
            if (archivo.size > pesoMax) {
                error(archivo.name, 'Pesa ' + pesoTexto(archivo.size) + ': el máximo es ' + pesoTexto(pesoMax) + '.');
                return Promise.resolve();
            }

            const datos = new FormData();
            datos.append('csrf', form.querySelector('[name="csrf"]').value);
            datos.append('lote', form.querySelector('[name="lote"]').value);
            datos.append('tipo', tipo);
            datos.append('ocupados', String(ocupados()));
            datos.append('archivo', archivo);

            const espera = el('li', 'intra-archivo-subiendo');
            espera.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> ';
            espera.appendChild(document.createTextNode('Subiendo ' + archivo.name + '…'));
            lista.appendChild(espera);

            return fetch(zona.dataset.url, { method: 'POST', body: datos, credentials: 'same-origin' })
                .then(function (r) {
                    return r.json().catch(function () {
                        return { ok: false, error: 'El servidor no respondió bien. Probá de nuevo.' };
                    });
                })
                .catch(function () {
                    return { ok: false, error: 'No hay conexión con el servidor. Probá de nuevo.' };
                })
                .then(function (r) {
                    if (r.ok) {
                        agregar(r.archivo, espera);
                    } else {
                        espera.remove();
                        error(archivo.name, r.error || 'No se pudo subir.');
                    }
                });
        }

        // De a uno: así el tope se respeta y una falla no corta a las demás
        function encolar(archivos) {
            errores.innerHTML = '';
            Array.from(archivos).forEach(function (f) {
                cola = cola.then(function () { return subir(f); });
            });
        }

        elegir.addEventListener('click', function () { input.click(); });
        input.addEventListener('change', function () {
            const elegidos = Array.from(input.files);
            input.value = '';
            encolar(elegidos);
        });

        // Arrastrar archivos desde la computadora a cualquier parte de la zona
        zona.addEventListener('dragover', function (ev) {
            if (!conArchivos(ev)) return;
            ev.preventDefault();
            ev.dataTransfer.dropEffect = ocupados() >= max ? 'none' : 'copy';
            soltar.classList.add('sobre');
        });
        zona.addEventListener('dragleave', function (ev) {
            if (!zona.contains(ev.relatedTarget)) soltar.classList.remove('sobre');
        });
        zona.addEventListener('drop', function (ev) {
            if (!conArchivos(ev)) return;
            ev.preventDefault();
            soltar.classList.remove('sobre');
            encolar(ev.dataTransfer.files);
        });

        let iniciales = [];
        try {
            iniciales = JSON.parse(zona.dataset.items || '[]');
        } catch (e) { /* sin archivos previos */ }
        iniciales.forEach(function (a) { agregar(a); });
        actualizar();
    }

    // Un archivo soltado fuera de las zonas no tiene que abrirse en el navegador (y perder lo cargado)
    window.addEventListener('dragover', function (ev) { if (conArchivos(ev)) ev.preventDefault(); });
    window.addEventListener('drop', function (ev) { if (conArchivos(ev)) ev.preventDefault(); });

    document.querySelectorAll('[data-archivos]').forEach(iniciar);
    iniciarEsquemas();
    iniciarFormato();
    iniciarVistaPrevia();
    iniciarAviso();

    // "Mostrar el aviso hasta" solo con la casilla del aviso marcada
    function iniciarAviso() {
        const casilla = document.getElementById('f_aviso');
        const bloque = document.getElementById('bloqueAvisoHasta');
        if (!casilla || !bloque) return;
        casilla.addEventListener('change', function () {
            bloque.hidden = !casilla.checked;
        });
    }

    // No guardar mientras haya archivos subiendo: quedarían afuera
    document.querySelectorAll('form').forEach(function (form) {
        if (!form.querySelector('[data-archivos]')) return;
        form.addEventListener('submit', function (ev) {
            if (form.querySelector('.intra-archivo-subiendo')) {
                ev.preventDefault();
                alert('Esperá a que terminen de subir los archivos.');
            }
        });
    });

    // ==================== Esquema ====================
    // Muestra los campos según el esquema: imágenes solo si las usa, segundo texto en CENTRO,
    // tope de 300 en FLYER. Si se quitan todas las imágenes, vuelve a «Solo texto» (y si después
    // se sube una, vuelve al esquema que tenía).
    function iniciarEsquemas() {
        const radios = Array.from(document.querySelectorAll('input[type="radio"][name="esquema"]'));
        const texto = document.getElementById('f_texto');
        if (!radios.length || !texto) return;
        const etiqueta = document.getElementById('lbl_texto');
        const inferior = document.getElementById('bloqueTextoInferior');
        const avisoFlyer = document.getElementById('avisoFlyer');
        const nota = document.getElementById('notaEsquema');
        const zonaImg = document.getElementById('zonaImagenes');
        const maxNormal = texto.getAttribute('maxlength');
        const MAX_FLYER = 300;
        const ETIQUETAS = { CENTRO: 'Texto antes de la imagen', FLYER: 'Texto (opcional, hasta ' + MAX_FLYER + ' caracteres)' };

        function actual() {
            const r = radios.find(function (x) { return x.checked; });
            return r ? r.value : 'SIN_IMAGEN';
        }
        function elegir(valor) {
            radios.forEach(function (r) { r.checked = r.value === valor; });
        }
        function imagenes() {
            return zonaImg ? zonaImg.querySelectorAll('.intra-archivo').length : 0;
        }

        let anterior = actual();
        let ultimoConImagen = anterior !== 'SIN_IMAGEN' ? anterior : 'ARRIBA';
        let mantenerZona = false; // tras quitar la última imagen, la zona sigue a la vista para subir otra

        function revisarFlyer() {
            const largo = texto.value.replace(/\r\n/g, '\n').length;
            avisoFlyer.hidden = !(actual() === 'FLYER' && largo > MAX_FLYER);
        }

        function aplicar() {
            const e = actual();
            etiqueta.textContent = ETIQUETAS[e] || 'Texto';
            if (inferior) inferior.hidden = e !== 'CENTRO';
            texto.dataset.max = e === 'FLYER' ? MAX_FLYER : maxNormal;
            texto.dispatchEvent(new Event('input'));
            if (zonaImg) zonaImg.hidden = e === 'SIN_IMAGEN' && imagenes() === 0 && !mantenerZona;
            revisarFlyer();
        }

        radios.forEach(function (r) {
            r.addEventListener('change', function () {
                const e = actual();
                if (e === 'SIN_IMAGEN' && imagenes() > 0) {
                    const n = imagenes();
                    if (!window.confirm('Con «Solo texto» no se muestran imágenes. ¿Quitar ' + (n === 1 ? 'la imagen cargada' : 'las ' + n + ' imágenes cargadas') + '?')) {
                        elegir(anterior);
                        return;
                    }
                    zonaImg.quitarTodos();
                }
                if (e !== 'SIN_IMAGEN') ultimoConImagen = e;
                mantenerZona = false;
                if (nota) nota.hidden = true;
                anterior = e;
                aplicar();
            });
        });

        if (zonaImg) {
            zonaImg.addEventListener('intra:cambio', function (ev) {
                const n = ev.detail.cantidad;
                const e = actual();
                if (n === 0 && e !== 'SIN_IMAGEN') {
                    ultimoConImagen = e;
                    elegir('SIN_IMAGEN');
                    anterior = 'SIN_IMAGEN';
                    mantenerZona = true;
                    if (nota) nota.hidden = false;
                } else if (n > 0 && e === 'SIN_IMAGEN' && mantenerZona) {
                    elegir(ultimoConImagen);
                    anterior = ultimoConImagen;
                    mantenerZona = false;
                    if (nota) nota.hidden = true;
                }
                aplicar();
            });
        }

        texto.addEventListener('input', revisarFlyer);
        aplicar();
    }

    // ==================== Barra de formato ====================
    // Inserta marcas sencillas en el texto: **negrita**, *cursiva*, "- ", "1. " y [texto](url).
    function iniciarFormato() {
        document.querySelectorAll('[data-formato-para]').forEach(function (barra) {
            const campo = document.getElementById(barra.dataset.formatoPara);
            if (!campo) return;
            barra.addEventListener('click', function (ev) {
                const b = ev.target.closest('[data-formato]');
                if (b) aplicarFormato(campo, b.dataset.formato);
            });
        });
    }

    function aplicarFormato(campo, tipo) {
        const v = campo.value;
        let ini = campo.selectionStart;
        let fin = campo.selectionEnd;
        const sel = v.slice(ini, fin);
        let nuevo;
        let selIni;
        let selFin;

        if (tipo === 'negrita' || tipo === 'cursiva') {
            const marca = tipo === 'negrita' ? '**' : '*';
            // Sin espacios adentro de las marcas: "** hola **" no se reconoce
            const recorte = sel.match(/^(\s*)([\s\S]*?)(\s*)$/);
            const t = recorte[2] || (tipo === 'negrita' ? 'texto en negrita' : 'texto en cursiva');
            nuevo = recorte[1] + marca + t + marca + recorte[3];
            selIni = ini + recorte[1].length + marca.length;
            selFin = selIni + t.length;
        } else if (tipo === 'vinetas' || tipo === 'numerada') {
            // Se aplica a las líneas completas de la selección (o a la línea actual)
            ini = v.lastIndexOf('\n', ini - 1) + 1;
            const corte = v.indexOf('\n', fin > ini && v[fin - 1] === '\n' ? fin - 1 : fin);
            fin = corte === -1 ? v.length : corte;
            const lineas = v.slice(ini, fin).split('\n').map(function (l, i) {
                const limpia = l.replace(/^(-\s+|\d{1,3}\.\s+)/, '');
                return (tipo === 'vinetas' ? '- ' : (i + 1) + '. ') + limpia;
            });
            nuevo = lineas.join('\n');
            selIni = ini;
            selFin = ini + nuevo.length;
        } else if (tipo === 'link') {
            let url = window.prompt('Dirección del link (https://… o /ruta/interna):', 'https://');
            if (url === null) return;
            url = url.trim().replace(/\s/g, '%20');
            if (!/^(https?:\/\/.+|\/(?!\/).*)$/i.test(url)) {
                window.alert('El link tiene que empezar con https://, http:// o /.');
                return;
            }
            const t = (sel.trim() || 'texto del link').replace(/[\[\]]/g, '');
            nuevo = '[' + t + '](' + url + ')';
            selIni = ini + 1;
            selFin = selIni + t.length;
        } else {
            return;
        }

        campo.focus();
        campo.setRangeText(nuevo, ini, fin, 'end');
        campo.setSelectionRange(selIni, selFin);
        campo.dispatchEvent(new Event('input'));
    }

    // ==================== Vista previa ====================
    // Manda el formulario tal como está a recursosHumanos/intranet/vista_previa.php (permiso y CSRF; no guarda)
    // y muestra lo que devuelve: las mismas plantillas que la pantalla real.
    function iniciarVistaPrevia() {
        const modalEl = document.getElementById('modalPrevia');
        const form = document.getElementById('formComunicado');
        if (!modalEl || !form || !window.bootstrap) return;
        const cuerpoIntranet = document.getElementById('previaIntranetCuerpo');
        const cuerpoHub = document.getElementById('previaHubCuerpo');
        const cargando = document.getElementById('previaCargando');
        const faltan = document.getElementById('previaFaltan');
        const tabIntranet = document.getElementById('tabPreviaIntranet');
        const tabAviso = document.getElementById('tabPreviaAvisoItem');
        const cuerpoAviso = document.getElementById('previaAvisoCuerpo');
        let pedido = 0;

        function revisar() {
            if (window.IntraComunicados) IntraComunicados.revisarCortes(modalEl);
        }

        function mensaje(texto) {
            cuerpoIntranet.innerHTML = '';
            const p = el('div', 'alert alert-danger', texto);
            cuerpoIntranet.appendChild(p);
        }

        modalEl.addEventListener('show.bs.modal', function () {
            const este = ++pedido;
            if (window.IntraComunicados) IntraComunicados.liberar(modalEl);
            cuerpoIntranet.innerHTML = '';
            cuerpoHub.innerHTML = '';
            if (cuerpoAviso) cuerpoAviso.innerHTML = '';
            if (tabAviso) tabAviso.hidden = true;
            faltan.hidden = true;
            cargando.hidden = false;
            bootstrap.Tab.getOrCreateInstance(tabIntranet).show();

            if (form.querySelector('.intra-archivo-subiendo')) {
                cargando.hidden = true;
                mensaje('Esperá a que terminen de subir los archivos y volvé a abrir la vista previa.');
                return;
            }

            const datos = new FormData(form);
            fetch(form.dataset.previa, { method: 'POST', body: datos, credentials: 'same-origin' })
                .then(function (r) {
                    return r.json().catch(function () {
                        return { ok: false, error: 'El servidor no respondió bien. Probá de nuevo.' };
                    });
                })
                .catch(function () {
                    return { ok: false, error: 'No hay conexión con el servidor. Probá de nuevo.' };
                })
                .then(function (r) {
                    if (este !== pedido) return; // se cerró y se volvió a abrir
                    cargando.hidden = true;
                    if (!r.ok) {
                        mensaje(r.error || 'No se pudo armar la vista previa.');
                        return;
                    }
                    cuerpoIntranet.innerHTML = r.intranet;
                    cuerpoHub.innerHTML = r.hub;
                    // Tercera solapa solo si está marcado "Mostrar como aviso al entrar al hub"
                    if (tabAviso && cuerpoAviso) {
                        cuerpoAviso.innerHTML = r.aviso || '';
                        tabAviso.hidden = !r.aviso;
                    }
                    faltan.innerHTML = '';
                    if (r.faltan && r.faltan.length) {
                        const p = el('div');
                        p.appendChild(el('strong', '', 'Para publicarlo todavía falta: '));
                        p.appendChild(document.createTextNode(r.faltan.join(' ')));
                        faltan.appendChild(p);
                    }
                    // Datos de plantilla entre corchetes: se resaltan en la vista y se listan acá
                    if (r.datos && r.datos.length) {
                        const p = el('div');
                        p.appendChild(el('strong', '', 'Datos a completar (resaltados): '));
                        p.appendChild(document.createTextNode(r.datos.join(', ')));
                        faltan.appendChild(p);
                    }
                    faltan.hidden = !faltan.childNodes.length;
                    if (window.IntraComunicados) IntraComunicados.iniciar(modalEl);
                    requestAnimationFrame(revisar);
                });
        });

        modalEl.addEventListener('shown.bs.modal', revisar);
        modalEl.addEventListener('shown.bs.tab', revisar);
        modalEl.addEventListener('hidden.bs.modal', function () {
            pedido++;
            if (window.IntraComunicados) IntraComunicados.liberar(modalEl);
            cuerpoIntranet.innerHTML = '';
            cuerpoHub.innerHTML = '';
            if (cuerpoAviso) cuerpoAviso.innerHTML = '';
        });

        // En la vista previa, la columna del hub y la ventana del aviso no navegan ni cierran nada
        [cuerpoHub, cuerpoAviso].forEach(function (cuerpo) {
            if (!cuerpo) return;
            cuerpo.addEventListener('click', function (ev) {
                if (ev.target.closest('a, [data-aviso-entendido], [data-aviso-siguiente]')) ev.preventDefault();
            });
        });
    }
})();
