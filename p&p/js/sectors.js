
// sectors.js
// Funciones para la gestión de sectores

// Inicializar la gestión de sectores
function initSectors() {
    // Inicializar formulario de nuevo sector si existe
    const newSectorBtn = document.getElementById('newSectorBtn');
    if (newSectorBtn) {
        newSectorBtn.addEventListener('click', function() {
            document.getElementById('newSectorForm').style.display = 'block';
        });
    }
    
    const cancelSectorBtn = document.getElementById('cancelSectorBtn');
    if (cancelSectorBtn) {
        cancelSectorBtn.addEventListener('click', function() {
            document.getElementById('newSectorForm').style.display = 'none';
        });
    }
    
    // Inicializar vista previa de iconos de sector
    const sectorIcon = document.getElementById('sectorIcon');
    if (sectorIcon) {
        sectorIcon.addEventListener('change', function() {
            updateIconPreview(this.value);
        });
    }
}

// Actualizar la vista previa del icono seleccionado
function updateIconPreview(iconClass) {
    const iconPreview = document.getElementById('iconPreview');
    if (iconPreview) {
        iconPreview.innerHTML = `<i class="fas ${iconClass}"></i>`;
    }
}

// Editar un sector existente
function editSector(sectorId) {
    // En una implementación real, esto cargaría los datos del sector
    fetch('Controller/obtener_sector.php?id=' + sectorId)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const sector = data.sector;
                
                // Mostrar el formulario de edición
                document.getElementById('newSectorForm').style.display = 'block';
                document.getElementById('sectorFormTitle').textContent = 'Editar Sector';
                
                // Establecer los valores del formulario
                document.getElementById('sectorId').value = sector.id;
                document.getElementById('sectorName').value = sector.nombre;
                document.getElementById('sectorDesc').value = sector.descripcion || '';
                
                const iconSelect = document.getElementById('sectorIcon');
                if (iconSelect) {
                    iconSelect.value = sector.icono || 'fa-folder';
                    updateIconPreview(sector.icono || 'fa-folder');
                }
                
                // Cambiar el botón de envío
                const submitBtn = document.querySelector('#sectorForm button[type="submit"]');
                if (submitBtn) {
                    submitBtn.textContent = 'Actualizar Sector';
                }
                
                // Desplazarse al formulario
                document.getElementById('newSectorForm').scrollIntoView({ behavior: 'smooth' });
            } else {
                showNotification('Error al cargar el sector: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error al procesar la solicitud', 'error');
        });
}

// Confirmar eliminación de un sector
function confirmDeleteSector(sectorId) {
    if (confirm('¿Está seguro que desea eliminar este sector? Esta acción puede afectar a los documentos asociados.')) {
        // Enviar solicitud de eliminación
        deleteSector(sectorId);
    }
}

// Eliminar un sector
function deleteSector(sectorId) {
    fetch('Controller/eliminar_sector.php?id=' + sectorId)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showNotification('Sector eliminado correctamente', 'success');
                
                // Recargar la página después de un breve retraso
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showNotification('Error al eliminar el sector: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error al procesar la solicitud', 'error');
        });
}

// Guardar un nuevo sector o actualizar uno existente
function saveSector(formData) {
    // Verificar si es una edición o un nuevo sector
    const sectorId = document.getElementById('sectorId')?.value;
    
    const url = sectorId 
        ? 'Controller/actualizar_sector.php' 
        : 'Controller/procesar_sector.php';
    
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showNotification(
                sectorId ? 'Sector actualizado correctamente' : 'Sector creado correctamente', 
                'success'
            );
            
            // Recargar la página después de un breve retraso
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showNotification('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error al procesar la solicitud', 'error');
    });
}

// Verificar si un sector tiene documentos asociados
function checkSectorDocuments(sectorId) {
    return new Promise((resolve, reject) => {
        fetch('Controller/verificar_documentos_sector.php?id=' + sectorId)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    resolve(data.hasDocuments);
                } else {
                    reject(new Error(data.message));
                }
            })
            .catch(error => {
                reject(error);
            });
    });
}

// Cargar documentos por sector
function loadSectorDocuments(sectorId) {
    // Cambiar a la pestaña de documentos
    showTab('documents');
    
    // Actualizar el filtro de sector
    const sectorFilter = document.getElementById('sectorFilter');
    if (sectorFilter) {
        sectorFilter.value = sectorId;
    }
    
    // Cargar los documentos filtrados
    fetch('Controller/obtener_documentos.php?sector=' + sectorId)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Actualizar la lista de documentos
                const documentsList = document.getElementById('documentsList');
                if (documentsList) {
                    // Limpiar la lista actual
                    documentsList.innerHTML = '';
                    
                    if (data.documentos.length === 0) {
                        // Mostrar mensaje de no hay documentos
                        documentsList.innerHTML = `
                            <li class="no-documents">
                                <p>No hay documentos disponibles para este sector.</p>
                            </li>
                        `;
                    } else {
                        // Mostrar los documentos encontrados
                        data.documentos.forEach(doc => {
                            const docItem = document.createElement('li');
                            docItem.className = 'policy-item';
                            
                            // Formatear la fecha para mostrar
                            const fecha = new Date(doc.fecha_actualizacion);
                            const fechaFormateada = fecha.toLocaleDateString('es-ES');
                            
                            docItem.innerHTML = `
                                <div class="policy-info">
                                    <div class="policy-icon">
                                        <i class="fas fa-file-pdf"></i>
                                    </div>
                                    <div class="policy-details">
                                        <h4>${doc.titulo}</h4>
                                        <p>Sector: ${doc.sector_nombre} - Actualizado: ${fechaFormateada}</p>
                                    </div>
                                </div>
                                <div class="policy-actions">
                                    <button class="action-btn view" onclick="viewDocument(${doc.id})">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="action-btn download" onclick="downloadDocument(${doc.id})">
                                        <i class="fas fa-download"></i>
                                    </button>
                                    <button class="action-btn star" data-id="${doc.id}">
                                        <i class="far fa-star"></i>
                                    </button>
                                </div>
                            `;
                            
                            documentsList.appendChild(docItem);
                        });
                    }
                }
            } else {
                showNotification('Error al cargar documentos: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error al procesar la solicitud', 'error');
        });
}

// Mostrar la estadística de documentos por sector
function showSectorStats() {
    fetch('Controller/obtener_estadisticas_sector.php')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const statsContainer = document.getElementById('sectorStats');
                if (statsContainer) {
                    // Limpiar contenedor
                    statsContainer.innerHTML = '';
                    
                    // Crear gráfico o lista de estadísticas
                    let html = '<ul class="stats-list">';
                    
                    data.stats.forEach(stat => {
                        const percentage = (stat.documentos / data.total) * 100;
                        
                        html += `
                            <li class="stats-item">
                                <div class="stats-info">
                                    <h4>${stat.nombre}</h4>
                                    <div class="stats-bar">
                                        <div class="stats-progress" style="width: ${percentage}%"></div>
                                    </div>
                                </div>
                                <div class="stats-count">${stat.documentos} documentos</div>
                            </li>
                        `;
                    });
                    
                    html += '</ul>';
                    statsContainer.innerHTML = html;
                }
            } else {
                console.error('Error al cargar estadísticas:', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
}

// Exportar documentos de un sector
function exportSectorDocuments(sectorId, format = 'pdf') {
    showNotification('Preparando exportación de documentos...', 'info');
    
    // En una implementación real, esto generaría un informe de documentos por sector
    const url = `Controller/exportar_documentos.php?sector=${sectorId}&format=${format}`;
    
    // Redirigir a la URL de descarga
    window.location.href = url;
}

// Obtener todos los sectores
function getAllSectors() {
    return new Promise((resolve, reject) => {
        fetch('Controller/obtener_sectores.php')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    resolve(data.sectores);
                } else {
                    reject(new Error(data.message));
                }
            })
            .catch(error => {
                reject(error);
            });
    });
}

// Inicializar selectores de sector
function initSectorSelectors() {
    getAllSectors()
        .then(sectores => {
            // Actualizar todos los selectores de sector en la página
            document.querySelectorAll('select[data-type="sector-selector"]').forEach(select => {
                // Guardar la opción seleccionada actual si existe
                const currentValue = select.value;
                
                // Limpiar opciones existentes excepto la primera (placeholder)
                const firstOption = select.querySelector('option:first-child');
                select.innerHTML = '';
                if (firstOption) {
                    select.appendChild(firstOption);
                }
                
                // Añadir opciones de sector
                sectores.forEach(sector => {
                    const option = document.createElement('option');
                    option.value = sector.id;
                    option.textContent = sector.nombre;
                    select.appendChild(option);
                });
                
                // Restaurar el valor seleccionado si existía
                if (currentValue) {
                    select.value = currentValue;
                }
            });
        })
        .catch(error => {
            console.error('Error al cargar sectores:', error);
        });
}