
// upload-form.js
// Funciones para el formulario de carga de documentos

// Inicializar el manejo de carga de archivos
function initFileUpload() {
    const fileInput = document.getElementById('docFile');
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const fileUploadBtn = document.getElementById('fileUploadBtn');
    
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

// Función para formatear el tamaño del archivo
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Función para eliminar el archivo seleccionado
function removeFile() {
    const fileInput = document.getElementById('docFile');
    const fileInfo = document.getElementById('fileInfo');
    const fileUploadBtn = document.getElementById('fileUploadBtn');
    
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

// Inicializar el manejo de etiquetas
function initTagsInput() {
    const tagsInput = document.getElementById('docTags');
    const tagsContainer = document.getElementById('tagsContainer');
    
    if (!tagsInput || !tagsContainer) return;
    
    // Procesar las etiquetas al escribir una coma o presionar Enter
    tagsInput.addEventListener('keyup', function(e) {
        if (e.key === ',' || e.key === 'Enter') {
            e.preventDefault();
            
            // Obtener el valor y eliminar la coma final
            let value = this.value.trim();
            if (value.endsWith(',')) {
                value = value.slice(0, -1).trim();
            }
            
            if (value) {
                addTagFromInput(value);
                this.value = '';
            }
        }
    });
    
    // También procesar al perder el foco
    tagsInput.addEventListener('blur', function() {
        let value = this.value.trim();
        if (value.endsWith(',')) {
            value = value.slice(0, -1).trim();
        }
        
        if (value) {
            addTagFromInput(value);
            this.value = '';
        }
    });
}

// Agregar una etiqueta desde una sugerencia
function addTag(tag) {
    const tagsInput = document.getElementById('docTags');
    if (!tagsInput) return;
    
    const currentTags = tagsInput.getAttribute('data-tags') || '';
    
    // Verificar si la etiqueta ya existe
    if (!currentTags.split(',').includes(tag)) {
        addTagFromInput(tag);
    }
}

// Agregar una etiqueta desde el input
function addTagFromInput(tag) {
    const tagsInput = document.getElementById('docTags');
    const tagsContainer = document.getElementById('tagsContainer');
    
    if (!tagsInput || !tagsContainer) return;
    
    const currentTags = tagsInput.getAttribute('data-tags') || '';
    
    // Verificar que la etiqueta no esté vacía
    if (!tag || tag === '') return;
    
    // Crear un array de etiquetas actuales
    let tagsArray = currentTags ? currentTags.split(',') : [];
    
    // Verificar si la etiqueta ya existe
    if (tagsArray.includes(tag)) return;
    
    // Añadir la nueva etiqueta
    tagsArray.push(tag);
    
    // Actualizar el atributo data-tags
    tagsInput.setAttribute('data-tags', tagsArray.join(','));
    
    // Actualizar el campo oculto con todas las etiquetas
    tagsInput.value = tagsArray.join(', ');
    
    // Crear y mostrar el elemento visual de la etiqueta
    const tagElement = document.createElement('div');
    tagElement.className = 'tag';
    tagElement.innerHTML = tag + ' <i class="fas fa-times" onclick="removeTag(\'' + tag + '\')"></i>';
    tagsContainer.appendChild(tagElement);
}

// Eliminar una etiqueta
function removeTag(tag) {
    const tagsInput = document.getElementById('docTags');
    const tagsContainer = document.getElementById('tagsContainer');
    
    if (!tagsInput || !tagsContainer) return;
    
    const currentTags = tagsInput.getAttribute('data-tags') || '';
    
    // Crear un array de etiquetas actuales
    let tagsArray = currentTags ? currentTags.split(',') : [];
    
    // Filtrar la etiqueta que se va a eliminar
    tagsArray = tagsArray.filter(t => t !== tag);
    
    // Actualizar el atributo data-tags
    tagsInput.setAttribute('data-tags', tagsArray.join(','));
    
    // Actualizar el campo oculto con todas las etiquetas
    tagsInput.value = tagsArray.join(', ');
    
    // Eliminar todos los elementos de etiqueta y volver a crearlos
    tagsContainer.innerHTML = '';
    tagsArray.forEach(t => {
        const tagElement = document.createElement('div');
        tagElement.className = 'tag';
        tagElement.innerHTML = t + ' <i class="fas fa-times" onclick="removeTag(\'' + t + '\')"></i>';
        tagsContainer.appendChild(tagElement);
    });
}

// Inicializar la validación del formulario
function initFormValidation() {
    const uploadForm = document.getElementById('uploadForm');
    
    if (!uploadForm) return;
    
    uploadForm.addEventListener('submit', function(e) {
        let isValid = true;
        
        // Validar título
        const titleInput = document.getElementById('docTitle');
        if (titleInput && !titleInput.value.trim()) {
            markFieldAsError(titleInput, 'El título es obligatorio');
            isValid = false;
        } else if (titleInput) {
            clearFieldError(titleInput);
        }
        
        // Validar tipo de documento
        const typeSelect = document.getElementById('docType');
        if (typeSelect && !typeSelect.value) {
            markFieldAsError(typeSelect, 'Seleccione un tipo de documento');
            isValid = false;
        } else if (typeSelect) {
            clearFieldError(typeSelect);
        }
        
        // Validar sector
        const sectorSelect = document.getElementById('docSector');
        if (sectorSelect && !sectorSelect.value) {
            markFieldAsError(sectorSelect, 'Seleccione un sector');
            isValid = false;
        } else if (sectorSelect) {
            clearFieldError(sectorSelect);
        }
        
        // Validar archivo
        const fileInput = document.getElementById('docFile');
        if (fileInput && (!fileInput.files || !fileInput.files[0])) {
            const formGroup = fileInput.closest('.form-group');
            if (formGroup) {
                markFieldAsError(formGroup, 'Seleccione un archivo PDF');
            }
            isValid = false;
        } else if (fileInput) {
            const formGroup = fileInput.closest('.form-group');
            if (formGroup) {
                clearFieldError(formGroup);
            }
        }
        
        if (!isValid) {
            e.preventDefault();
            showNotification('Por favor, corrija los errores en el formulario', 'error');
        }
    });
}

// Marcar un campo como error
function markFieldAsError(field, message) {
    const formGroup = field.closest('.form-group');
    
    if (!formGroup) return;
    
    formGroup.classList.add('has-error');
    
    // Verificar si ya existe un mensaje de error
    let errorMessage = formGroup.querySelector('.error-message');
    if (!errorMessage) {
        errorMessage = document.createElement('span');
        errorMessage.className = 'error-message';
        formGroup.appendChild(errorMessage);
    }
    
    errorMessage.textContent = message;
}

// Limpiar el error de un campo
function clearFieldError(field) {
    const formGroup = field.closest('.form-group');
    
    if (!formGroup) return;
    
    formGroup.classList.remove('has-error');
    
    // Eliminar mensaje de error si existe
    const errorMessage = formGroup.querySelector('.error-message');
    if (errorMessage) {
        errorMessage.remove();
    }
}

// Funciones para manejar sectores en el formulario
function loadSectors() {
    const sectorSelect = document.getElementById('docSector');
    
    if (!sectorSelect) return;
    
    // En una implementación real, esto cargaría los sectores desde la base de datos
    fetch('Controller/obtener_sectores.php')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Limpiar opciones existentes
                sectorSelect.innerHTML = '<option value="" disabled selected>Seleccione un sector</option>';
                
                // Añadir los sectores
                data.sectores.forEach(sector => {
                    const option = document.createElement('option');
                    option.value = sector.id;
                    option.textContent = sector.nombre;
                    sectorSelect.appendChild(option);
                });
            } else {
                console.error('Error al cargar sectores:', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
}

// Función para previsualizar el PDF
function previewPdf() {
    const fileInput = document.getElementById('docFile');
    
    if (fileInput && fileInput.files && fileInput.files[0]) {
        const file = fileInput.files[0];
        
        // Crear un objeto URL para el archivo
        const fileUrl = URL.createObjectURL(file);
        
        // Abrir la previsualización en una ventana modal
        openPdfViewer('Previsualización: ' + file.name, fileUrl);
    } else {
        showNotification('Seleccione un archivo PDF para previsualizar', 'warning');
    }
}