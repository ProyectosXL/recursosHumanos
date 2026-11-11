
// sectors.js
// Funciones para la gestión de sectores (Configuración: solo Admin; el servidor valida el permiso)

// Inicializar la gestión de sectores
function initSectors() {
    const form = document.getElementById('sectorForm');
    if (!form) return; // Configuración no se renderizó para este usuario

    const newSectorBtn = document.getElementById('newSectorBtn');
    if (newSectorBtn) {
        newSectorBtn.addEventListener('click', function () {
            resetSectorForm();
            document.getElementById('newSectorForm').style.display = 'block';
            document.getElementById('sectorName').focus();
        });
    }

    const cancelSectorBtn = document.getElementById('cancelSectorBtn');
    if (cancelSectorBtn) {
        cancelSectorBtn.addEventListener('click', function () {
            resetSectorForm();
            document.getElementById('newSectorForm').style.display = 'none';
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!document.getElementById('sectorName').value.trim()) {
            showNotification('El nombre del sector es obligatorio', 'error');
            return;
        }
        saveSector(new FormData(form));
    });

    const sectorIcon = document.getElementById('sectorIcon');
    if (sectorIcon) {
        sectorIcon.addEventListener('change', function () {
            updateIconPreview(this.value);
        });
    }
}

// Dejar el formulario listo para un sector nuevo
function resetSectorForm() {
    const form = document.getElementById('sectorForm');
    if (!form) return;
    form.reset();
    document.getElementById('sectorId').value = '';
    document.getElementById('sectorFormTitle').textContent = 'Nuevo sector';
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) submitBtn.innerHTML = '<i class="fas fa-check"></i> Guardar';
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
    fetch('Controller/obtener_sector.php?id=' + encodeURIComponent(sectorId))
        .then(response => response.json())
        .then(data => {
            if (data.status !== 'success') {
                showNotification(data.message || 'No se pudo cargar el sector', 'error');
                return;
            }
            const sector = data.sector;

            document.getElementById('newSectorForm').style.display = 'block';
            document.getElementById('sectorFormTitle').textContent = 'Editar sector';
            document.getElementById('sectorId').value = sector.id;
            document.getElementById('sectorName').value = sector.nombre;
            document.getElementById('sectorDesc').value = sector.descripcion || '';

            const iconSelect = document.getElementById('sectorIcon');
            if (iconSelect) {
                iconSelect.value = sector.icono || 'fa-folder';
                if (!iconSelect.value) iconSelect.value = 'fa-folder';
                updateIconPreview(iconSelect.value);
            }

            const submitBtn = document.querySelector('#sectorForm button[type="submit"]');
            if (submitBtn) submitBtn.innerHTML = '<i class="fas fa-check"></i> Guardar cambios';

            document.getElementById('newSectorForm').scrollIntoView({ behavior: 'smooth' });
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error al procesar la solicitud', 'error');
        });
}

// Confirmar eliminación de un sector (no se puede si tiene documentos)
function confirmDeleteSector(sectorId) {
    const boton = document.querySelector(`.cfg-delete[onclick^="confirmDeleteSector(${Number(sectorId)})"]`);
    const nombre = boton ? boton.dataset.nombre : '';

    checkSectorDocuments(sectorId)
        .then(cantidad => {
            if (cantidad.activos > 0) {
                const n = cantidad.activos;
                showNotification(`No se puede eliminar: el sector tiene ${n} documento${n === 1 ? '' : 's'} activo${n === 1 ? '' : 's'}.`, 'error');
                return;
            }
            if (cantidad.inactivos > 0) {
                const n = cantidad.inactivos;
                showNotification(`No se puede eliminar: el sector tiene ${n} documento${n === 1 ? '' : 's'} dado${n === 1 ? '' : 's'} de baja.`, 'error');
                return;
            }
            return pedirConfirmacion({
                titulo: 'Eliminar sector',
                mensaje: `¿Querés eliminar el sector "${nombre}"? Los términos del glosario de este sector quedan sin sector.`,
                textoConfirmar: 'Eliminar',
                peligro: true
            }).then(respuesta => {
                if (respuesta.ok) deleteSector(sectorId);
            });
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification(error.message || 'Error al procesar la solicitud', 'error');
        });
}

// Eliminar un sector
function deleteSector(sectorId) {
    const formData = new FormData();
    formData.append('id', sectorId);

    fetch('Controller/eliminar_sector.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showNotification('Sector eliminado', 'success');
                setTimeout(() => window.location.href = 'index.php?tab=settings', 1200);
            } else {
                showNotification(data.message || 'No se pudo eliminar el sector', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error al procesar la solicitud', 'error');
        });
}

// Guardar un nuevo sector o actualizar uno existente (mismo controller: con id es modificación)
function saveSector(formData) {
    const esEdicion = !!document.getElementById('sectorId')?.value;

    fetch('Controller/procesar_sector.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showNotification(esEdicion ? 'Sector actualizado' : 'Sector creado', 'success');
                setTimeout(() => window.location.href = 'index.php?tab=settings', 1200);
            } else {
                showNotification(data.message || 'No se pudo guardar el sector', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error al procesar la solicitud', 'error');
        });
}

// Cantidad de documentos de un sector: {activos, inactivos}
function checkSectorDocuments(sectorId) {
    return fetch('Controller/verificar_documentos_sector.php?id=' + encodeURIComponent(sectorId))
        .then(response => response.json())
        .then(data => {
            if (data.status !== 'success') {
                throw new Error(data.message || 'No se pudo verificar el sector');
            }
            return { activos: data.activos || 0, inactivos: data.inactivos || 0 };
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