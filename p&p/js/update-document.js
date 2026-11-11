// update-document.js
// Pestaña "Actualizar documento": nueva versión del PDF e historial (Editar y Admin).
// Los permisos se validan en el servidor; esto solo arma la pantalla.

// Datos del documento elegido (versión actual, tipo, etc.)
let updateDocumentoActual = null;

// Inicializar el manejo de actualización de documentos
function initUpdateForm() {
    const documentSelect = document.getElementById('documentSelect');
    if (!documentSelect) return; // El usuario no tiene nivel Editar: la pestaña no existe

    documentSelect.value = '';
    document.getElementById('documentDetails').style.display = 'none';
    document.getElementById('updateForm').style.display = 'none';

    documentSelect.addEventListener('change', function () {
        if (this.value) {
            loadDocumentDetails(this.value);
        } else {
            cancelUpdate();
        }
    });

    const tagsInput = document.getElementById('updateTags');
    if (tagsInput) {
        tagsInput.addEventListener('input', function () {
            loadTagsFromString(this.value, 'updateTagsContainer', 'updateTags');
        });
    }

    const form = document.getElementById('updateVersionForm');
    if (form) {
        form.addEventListener('submit', validarFormularioActualizacion);
    }

    initUpdateFileUpload();

    // Volver con un documento elegido (?tab=update&doc=ID, por ejemplo después de un error)
    const docParam = new URLSearchParams(window.location.search).get('doc');
    if (docParam) {
        openUpdateDocument(docParam);
    }
}

// Abrir la pestaña Actualizar con un documento ya elegido (botón "Actualizar" de las listas)
function openUpdateDocument(documentId) {
    const documentSelect = document.getElementById('documentSelect');
    if (!documentSelect) return;

    showTab('update');
    documentSelect.value = String(documentId);
    if (documentSelect.value !== String(documentId)) {
        showNotification('El documento no está disponible para actualizar', 'error');
        return;
    }
    loadDocumentDetails(documentId);
}

// Cargar los detalles del documento seleccionado
function loadDocumentDetails(documentId) {
    if (!documentId) return;

    fetch('Controller/obtener_documento_detalles.php?id=' + encodeURIComponent(documentId))
        .then(response => response.json())
        .then(data => {
            if (data.status !== 'success' || !data.documento) {
                showNotification(data.message || 'No se pudo cargar la información del documento', 'error');
                return;
            }

            const doc = data.documento;
            updateDocumentoActual = doc;

            document.getElementById('documentDetails').style.display = 'block';
            document.getElementById('docDetailTitle').textContent = doc.titulo || 'No disponible';
            document.getElementById('docDetailType').textContent = doc.tipo === 'politica' ? 'Política' : 'Procedimiento';
            document.getElementById('docDetailSector').textContent = doc.sector_nombre || 'No disponible';
            document.getElementById('docDetailVersion').textContent = doc.version || '1.0';
            document.getElementById('docDetailDate').textContent = doc.fecha_actualizacion || 'No disponible';

            // Plantilla de procedimiento (solo si el documento es un procedimiento)
            const plantilla = document.getElementById('updatePlantillaBox');
            if (plantilla) {
                plantilla.style.display = doc.tipo === 'procedimiento' ? 'flex' : 'none';
            }

            // Formulario de nueva versión
            document.getElementById('updateForm').style.display = 'block';
            document.getElementById('updateDocId').value = doc.id;
            document.getElementById('updateVersion').value = getNextVersion(doc.version || '1.0');
            document.getElementById('updateVersion').dataset.actual = doc.version || '1.0';
            document.getElementById('updateCurrentVersion').textContent = doc.version || '1.0';
            document.getElementById('updateDesc').value = '';
            document.getElementById('updateTags').value = doc.tags || '';
            loadTagsFromString(doc.tags || '', 'updateTagsContainer', 'updateTags');
            removeUpdateFile();

            // Edición de datos (si existe en la pantalla)
            if (typeof cargarFormularioEdicion === 'function') {
                cargarFormularioEdicion(doc);
            }

            showVersionHistory(doc.id);

            document.getElementById('documentDetails').scrollIntoView({ behavior: 'smooth' });
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error al cargar la información del documento', 'error');
        });
}

// Función para calcular la siguiente versión (1.0 -> 1.1, 2 -> 3)
function getNextVersion(currentVersion) {
    if (!currentVersion) return '1.0';

    const versionParts = String(currentVersion).split('.');

    if (versionParts.length === 1) {
        const n = parseInt(versionParts[0], 10);
        return isNaN(n) ? '1.0' : (n + 1).toString();
    }

    const major = parseInt(versionParts[0], 10);
    const minor = parseInt(versionParts[1], 10);
    if (isNaN(major) || isNaN(minor)) return '1.0';
    return `${major}.${minor + 1}`;
}

// Formato de versión: 1, 1.1, 2.0.3
function esVersionValida(version) {
    return /^\d{1,4}(\.\d{1,4}){0,3}$/.test(version);
}

// -1 si a < b, 0 si son iguales, 1 si a > b (compara número por número)
function compararVersiones(a, b) {
    const pa = String(a).split('.').map(n => parseInt(n, 10) || 0);
    const pb = String(b).split('.').map(n => parseInt(n, 10) || 0);
    const largo = Math.max(pa.length, pb.length);
    for (let i = 0; i < largo; i++) {
        const x = pa[i] || 0;
        const y = pb[i] || 0;
        if (x !== y) return x > y ? 1 : -1;
    }
    return 0;
}

// Validación antes de enviar (el servidor valida lo mismo)
function validarFormularioActualizacion(event) {
    const fileInput = document.getElementById('updateDocFile');
    const version = document.getElementById('updateVersion').value.trim();
    const actual = document.getElementById('updateVersion').dataset.actual || '1.0';
    const motivo = document.getElementById('updateDesc').value.trim();

    let error = '';
    if (!fileInput.files || fileInput.files.length === 0) {
        error = 'Elegí el PDF nuevo';
    } else if (!esVersionValida(version)) {
        error = 'La versión tiene que ser un número, por ejemplo 1.1';
    } else if (esVersionValida(actual) && compararVersiones(version, actual) <= 0) {
        error = 'La versión nueva tiene que ser mayor que la actual (' + actual + ')';
    } else if (motivo === '') {
        error = 'Contá el motivo del cambio';
    }

    if (error) {
        event.preventDefault();
        showNotification(error, 'error');
        return false;
    }

    const boton = document.getElementById('updateSubmitBtn');
    if (boton) {
        boton.disabled = true;
        boton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subiendo...';
    }
    return true;
}

// Inicializar el manejo de carga de archivos para actualización
function initUpdateFileUpload() {
    const fileInput = document.getElementById('updateDocFile');
    const fileInfo = document.getElementById('updateFileInfo');
    const fileName = document.getElementById('updateFileName');
    const fileSize = document.getElementById('updateFileSize');
    const fileUploadBtn = document.getElementById('updateFileUploadBtn');

    if (!fileInput || !fileInfo || !fileName || !fileSize || !fileUploadBtn) return;

    fileInput.addEventListener('change', function () {
        if (this.files && this.files[0]) {
            const file = this.files[0];

            if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
                showNotification('Solo se permiten archivos PDF', 'error');
                this.value = '';
                return;
            }

            if (file.size > 10 * 1024 * 1024) {
                showNotification('El archivo supera el tamaño máximo (10 MB)', 'error');
                this.value = '';
                return;
            }

            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
            fileInfo.classList.add('show');
            fileUploadBtn.style.borderColor = '#2ecc71';
            fileUploadBtn.classList.add('has-file');
        }
    });

    fileUploadBtn.addEventListener('dragover', function (e) {
        e.preventDefault();
        this.style.borderColor = '#3498db';
        this.style.backgroundColor = 'rgba(52, 152, 219, 0.1)';
    });

    fileUploadBtn.addEventListener('dragleave', function (e) {
        e.preventDefault();
        this.style.borderColor = '#ddd';
        this.style.backgroundColor = '#f8f9fa';
    });

    fileUploadBtn.addEventListener('drop', function (e) {
        e.preventDefault();
        this.style.borderColor = '#ddd';
        this.style.backgroundColor = '#f8f9fa';

        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            fileInput.dispatchEvent(new Event('change'));
        }
    });

    fileUploadBtn.addEventListener('click', function () {
        fileInput.click();
    });
}

// Quitar el archivo seleccionado
function removeUpdateFile() {
    const fileInput = document.getElementById('updateDocFile');
    const fileInfo = document.getElementById('updateFileInfo');
    const fileUploadBtn = document.getElementById('updateFileUploadBtn');

    if (fileInput) {
        fileInput.value = '';
        if (fileInfo) fileInfo.classList.remove('show');
        if (fileUploadBtn) {
            fileUploadBtn.style.borderColor = '#ddd';
            fileUploadBtn.style.backgroundColor = '#f8f9fa';
            fileUploadBtn.classList.remove('has-file');
        }
    }
}

// Mostrar etiquetas como chips a partir del texto separado por comas
function loadTagsFromString(tagsString, containerId, inputId) {
    const tagsContainer = document.getElementById(containerId);
    const tagsInput = document.getElementById(inputId);

    if (!tagsContainer || !tagsInput) return;

    tagsContainer.innerHTML = '';
    const tagsArray = String(tagsString || '').split(',').map(tag => tag.trim()).filter(tag => tag);
    tagsInput.setAttribute('data-tags', tagsArray.join(','));

    tagsArray.forEach(tag => {
        const tagElement = document.createElement('div');
        tagElement.className = 'tag';
        tagElement.appendChild(document.createTextNode(tag + ' '));
        const quitar = document.createElement('i');
        quitar.className = 'fas fa-times';
        quitar.title = 'Quitar etiqueta';
        quitar.addEventListener('click', () => removeUpdateTag(tag, containerId, inputId));
        tagElement.appendChild(quitar);
        tagsContainer.appendChild(tagElement);
    });
}

// Eliminar una etiqueta
function removeUpdateTag(tag, containerId, inputId) {
    const tagsInput = document.getElementById(inputId);
    if (!tagsInput) return;

    const tagsArray = (tagsInput.getAttribute('data-tags') || '')
        .split(',')
        .filter(t => t && t !== tag);

    tagsInput.value = tagsArray.join(', ');
    loadTagsFromString(tagsInput.value, containerId, inputId);
}

// Cancelar la actualización
function cancelUpdate() {
    document.getElementById('documentDetails').style.display = 'none';
    document.getElementById('updateForm').style.display = 'none';
    updateDocumentoActual = null;

    const documentSelect = document.getElementById('documentSelect');
    if (documentSelect) {
        documentSelect.value = '';
    }

    removeUpdateFile();
}

// Historial de versiones (versión, fecha, usuario y motivo). Sin enlaces a PDF anteriores.
function showVersionHistory(documentId) {
    const content = document.getElementById('versionHistoryContent');
    if (!documentId || !content) return;

    content.innerHTML = '<p class="version-empty">Cargando historial...</p>';

    fetch('Controller/obtener_historial_versiones.php?id=' + encodeURIComponent(documentId))
        .then(response => response.json())
        .then(data => {
            if (data.status !== 'success') {
                content.innerHTML = '<p class="version-empty">' + escapeHtml(data.message || 'No se pudo cargar el historial') + '</p>';
                return;
            }

            if (!data.historial || data.historial.length === 0) {
                content.innerHTML = '<p class="version-empty">Todavía no hay cambios registrados para este documento.</p>';
                return;
            }

            let html = '<div class="version-timeline">';
            data.historial.forEach(item => {
                const esVersion = item.tipo_cambio === 'version';
                const titulo = esVersion
                    ? 'Versión ' + escapeHtml(item.version)
                    : escapeHtml(item.tipo_texto) + (item.version ? ' · versión ' + escapeHtml(item.version) : '');
                html += `
                    <div class="version-item tipo-${escapeHtml(item.tipo_cambio)}">
                        <div class="version-dot"></div>
                        <div class="version-content">
                            <h4>${titulo}</h4>
                            <p class="version-date"><i class="far fa-calendar-alt"></i> ${escapeHtml(item.fecha || '')}</p>
                            <p class="version-user"><i class="far fa-user"></i> ${escapeHtml(item.usuario || 'Sistema')}</p>
                            <p class="version-desc">${escapeHtml(item.descripcion || 'Sin motivo')}</p>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            content.innerHTML = html;
        })
        .catch(error => {
            console.error('Error:', error);
            content.innerHTML = '<p class="version-empty">No se pudo cargar el historial. Probá de nuevo más tarde.</p>';
        });
}

// ===== Editar datos (sin cambiar el PDF ni la versión) =====

// Alternar entre "Subir versión nueva" y "Editar datos"
function mostrarOpcionUpdate(opcion) {
    const esDatos = opcion === 'datos';
    const cardVersion = document.getElementById('updateVersionCard');
    const cardDatos = document.getElementById('updateDataCard');
    if (cardVersion) cardVersion.style.display = esDatos ? 'none' : 'block';
    if (cardDatos) cardDatos.style.display = esDatos ? 'block' : 'none';
    document.querySelectorAll('.update-option').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.opcion === opcion);
    });
}

// Completar el formulario de edición con los datos actuales
function cargarFormularioEdicion(doc) {
    const form = document.getElementById('editDataForm');
    if (!form) return;

    document.getElementById('editDocId').value = doc.id;
    document.getElementById('editTitulo').value = doc.titulo || '';
    document.getElementById('editTipo').value = doc.tipo || 'politica';
    document.getElementById('editSector').value = String(doc.sector_id || '');
    document.getElementById('editDescripcion').value = doc.descripcion || '';
    document.getElementById('editTags').value = doc.tags || '';
    loadTagsFromString(doc.tags || '', 'editTagsContainer', 'editTags');

    if (!form.dataset.iniciado) {
        form.dataset.iniciado = '1';
        document.getElementById('editTags').addEventListener('input', function () {
            loadTagsFromString(this.value, 'editTagsContainer', 'editTags');
        });
        form.addEventListener('submit', function (event) {
            if (!document.getElementById('editTitulo').value.trim()) {
                event.preventDefault();
                showNotification('El título es obligatorio', 'error');
                return;
            }
            const boton = document.getElementById('editDataSubmitBtn');
            if (boton) {
                boton.disabled = true;
                boton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            }
        });
    }

    mostrarOpcionUpdate('version');
}
