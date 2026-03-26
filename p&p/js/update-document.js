
// update-document.js
// Funciones para actualizar documentos existentes

// Inicializar el manejo de actualización de documentos
function initUpdateForm() {
    // Inicializar el selector de documentos
    const documentSelect = document.getElementById('documentSelect');
    if (documentSelect) {
        // Resetear la selección al cargar la página
        documentSelect.value = '';
        
        // Ocultar formularios y detalles
        document.getElementById('documentDetails').style.display = 'none';
        document.getElementById('updateForm').style.display = 'none';
    }
    
    // Inicializar campos de archivo para la actualización
    initUpdateFileUpload();
}

// Cargar los detalles del documento seleccionado
function loadDocumentDetails(documentId) {
    if (!documentId) return;
    
    // Mostrar indicador de carga
    showNotification('Cargando información del documento...', 'info');
    
    // Realizar petición para obtener detalles del documento
    fetch('Controller/obtener_documento_detalles.php?id=' + documentId)
        .then(response => response.text())
        .then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Error al parsear JSON:', text);
                throw new Error('La respuesta no es un JSON válido');
            }
        })
        .then(data => {
            if (data.status === 'success' && data.documento) {
                // Mostrar el área de detalles
                document.getElementById('documentDetails').style.display = 'block';
                
                // Actualizar los campos con la información del documento
                document.getElementById('docDetailTitle').textContent = data.documento.titulo || 'No disponible';
                document.getElementById('docDetailType').textContent = data.documento.tipo === 'politica' ? 'Política' : 'Procedimiento';
                document.getElementById('docDetailSector').textContent = data.documento.sector_nombre || 'No disponible';
                document.getElementById('docDetailVersion').textContent = data.documento.version || '1.0';
                document.getElementById('docDetailDate').textContent = data.documento.fecha_actualizacion || 'No disponible';
                
                // Mostrar formulario de actualización
                document.getElementById('updateForm').style.display = 'block';
                
                // Establecer valores iniciales en el formulario
                document.getElementById('updateDocId').value = documentId;
                document.getElementById('updateVersion').value = getNextVersion(data.documento.version || '1.0');
                document.getElementById('updateDesc').value = '';
                document.getElementById('updateTags').value = data.documento.tags || '';
                
                // Si hay tags, cargarlas visualmente
                if (data.documento.tags) {
                    loadTagsFromString(data.documento.tags, 'updateTagsContainer', 'updateTags');
                }
                
                // Desplazarse al formulario
                document.getElementById('updateForm').scrollIntoView({ behavior: 'smooth' });
            } else {
                showNotification('Error: ' + (data.message || 'No se pudo cargar la información del documento'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error al cargar la información del documento', 'error');
        });
}

// Función para calcular la siguiente versión
function getNextVersion(currentVersion) {
    // Si no hay versión, empezar con 1.0
    if (!currentVersion) return '1.0';
    
    // Separar por puntos
    const versionParts = currentVersion.split('.');
    
    // Si solo tiene un número, incrementar en 1
    if (versionParts.length === 1) {
        return (parseInt(versionParts[0]) + 1).toString();
    }
    
    // Si tiene formato x.y, incrementar el segundo número
    if (versionParts.length >= 2) {
        const major = parseInt(versionParts[0]);
        const minor = parseInt(versionParts[1]) + 1;
        return `${major}.${minor}`;
    }
    
    // Fallback
    return '1.0';
}

// Inicializar el manejo de carga de archivos para actualización
function initUpdateFileUpload() {
    const fileInput = document.getElementById('updateDocFile');
    const fileInfo = document.getElementById('updateFileInfo');
    const fileName = document.getElementById('updateFileName');
    const fileSize = document.getElementById('updateFileSize');
    const fileUploadBtn = document.getElementById('updateFileUploadBtn');
    
    if (!fileInput || !fileInfo || !fileName || !fileSize || !fileUploadBtn) return;
    
    // Manejar la selección de archivo
    fileInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const file = this.files[0];
            
            // Verificar que es un PDF
            if (file.type !== 'application/pdf') {
                showNotification('Solo se permiten archivos PDF', 'error');
                this.value = '';
                return;
            }
            
            // Verificar tamaño (10MB máximo)
            if (file.size > 10 * 1024 * 1024) {
                showNotification('El archivo excede el tamaño máximo permitido (10MB)', 'error');
                this.value = '';
                return;
            }
            
            // Mostrar información del archivo
            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
            fileInfo.classList.add('show');
            fileUploadBtn.style.borderColor = '#2ecc71';
            fileUploadBtn.classList.add('has-file');
        }
    });
    
    // Efecto visual al arrastrar el archivo
    fileUploadBtn.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.style.borderColor = '#3498db';
        this.style.backgroundColor = 'rgba(52, 152, 219, 0.1)';
    });
    
    fileUploadBtn.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.style.borderColor = '#ddd';
        this.style.backgroundColor = '#f8f9fa';
    });
    
    fileUploadBtn.addEventListener('drop', function(e) {
        e.preventDefault();
        this.style.borderColor = '#ddd';
        this.style.backgroundColor = '#f8f9fa';
        
        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            
            // Disparar el evento change para que se procese el archivo
            const event = new Event('change');
            fileInput.dispatchEvent(event);
        }
    });
    
    // Abrir el selector de archivos al hacer clic en el botón
    fileUploadBtn.addEventListener('click', function() {
        fileInput.click();
    });
}

// Función para eliminar el archivo seleccionado en la actualización
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

// Cargar etiquetas desde una cadena
function loadTagsFromString(tagsString, containerId, inputId) {
    const tagsContainer = document.getElementById(containerId);
    const tagsInput = document.getElementById(inputId);
    
    if (!tagsContainer || !tagsInput || !tagsString) return;
    
    // Limpiar el contenedor
    tagsContainer.innerHTML = '';
    
    // Separar etiquetas
    const tagsArray = tagsString.split(',').map(tag => tag.trim()).filter(tag => tag);
    
    // Actualizar el atributo data-tags
    tagsInput.setAttribute('data-tags', tagsArray.join(','));
    
    // Crear elementos visuales
    tagsArray.forEach(tag => {
        const tagElement = document.createElement('div');
        tagElement.className = 'tag';
        tagElement.innerHTML = tag + ' <i class="fas fa-times" onclick="removeUpdateTag(\'' + tag + '\', \'' + containerId + '\', \'' + inputId + '\')"></i>';
        tagsContainer.appendChild(tagElement);
    });
}

// Eliminar una etiqueta en la actualización
function removeUpdateTag(tag, containerId, inputId) {
    const tagsContainer = document.getElementById(containerId);
    const tagsInput = document.getElementById(inputId);
    
    if (!tagsContainer || !tagsInput) return;
    
    const currentTags = tagsInput.getAttribute('data-tags') || '';
    
    // Crear un array de etiquetas actuales
    let tagsArray = currentTags ? currentTags.split(',') : [];
    
    // Filtrar la etiqueta que se va a eliminar
    tagsArray = tagsArray.filter(t => t !== tag);
    
    // Actualizar el atributo data-tags
    tagsInput.setAttribute('data-tags', tagsArray.join(','));
    
    // Actualizar el campo de entrada con todas las etiquetas
    tagsInput.value = tagsArray.join(', ');
    
    // Volver a cargar las etiquetas visuales
    loadTagsFromString(tagsArray.join(','), containerId, inputId);
}

// Cancelar la actualización
function cancelUpdate() {
    // Ocultar formularios y detalles
    document.getElementById('documentDetails').style.display = 'none';
    document.getElementById('updateForm').style.display = 'none';
    
    // Resetear la selección
    const documentSelect = document.getElementById('documentSelect');
    if (documentSelect) {
        documentSelect.value = '';
    }
    
    // Limpiar el archivo
    removeUpdateFile();
}

// Función para mostrar el historial de versiones
function showVersionHistory(documentId) {
    if (!documentId) return;
    
    const modal = document.getElementById('versionHistoryModal');
    modal.style.display = 'block';
    
    const content = document.getElementById('versionHistoryContent');
    content.innerHTML = '<p>Cargando historial de versiones...</p>';
    
    // Cargar el historial desde el servidor
    fetch('Controller/obtener_historial_versiones.php?id=' + documentId)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                let html = '';
                
                if (data.historial.length === 0) {
                    html = '<p>No hay historial de versiones disponible</p>';
                } else {
                    html = '<div class="version-timeline">';
                    
                    data.historial.forEach(version => {
                        html += `
                            <div class="version-item">
                                <div class="version-dot"></div>
                                <div class="version-content">
                                    <h4>Versión ${version.version}</h4>
                                    <p class="version-date">${version.fecha}</p>
                                    <p class="version-user">${version.usuario}</p>
                                    <p class="version-desc">${version.descripcion || 'Sin descripción'}</p>
                                </div>
                            </div>
                        `;
                    });
                    
                    html += '</div>';
                }
                
                content.innerHTML = html;
            } else {
                content.innerHTML = '<p>Error al cargar el historial: ' + (data.message || 'Error desconocido') + '</p>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            content.innerHTML = '<p>Error al cargar el historial. Por favor, inténtelo de nuevo más tarde.</p>';
        });
}

// Función para cerrar el modal de historial
function closeVersionHistory() {
    document.getElementById('versionHistoryModal').style.display = 'none';
}