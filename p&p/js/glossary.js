
// glossary.js
// Funciones para la gestión del glosario de términos

// Inicializar la funcionalidad del glosario
function initGlossary() {
    const glossarySearch = document.getElementById('glossarySearch');
    if (glossarySearch) {
        // Limpiar el campo de búsqueda al abrir el modal
        document.querySelector('.sidebar-menu a[onclick="showGlossary()"]').addEventListener('click', function() {
            glossarySearch.value = '';
            // Resetear la visualización de los elementos del glosario
            document.querySelectorAll('.glossary-item').forEach(item => {
                item.style.display = 'block';
            });
            document.querySelectorAll('.glossary-letter').forEach(letter => {
                letter.style.display = 'block';
            });
            document.querySelector('.no-results-message').style.display = 'none';
        });
        
        // Manejar la búsqueda
        glossarySearch.addEventListener('input', function() {
            const searchTerm = this.value.trim().toLowerCase();
            filterGlossaryItems(searchTerm);
        });
        
        // Permitir cerrar con la tecla Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('glossaryModal').style.display === 'block') {
                closeGlossary();
            }
        });
    }
    
    // Inicializar formulario de nuevo término si existe
    const newTermBtn = document.getElementById('newTermBtn');
    if (newTermBtn) {
        newTermBtn.addEventListener('click', function() {
            document.getElementById('newTermForm').style.display = 'block';
        });
    }
    
    const cancelTermBtn = document.getElementById('cancelTermBtn');
    if (cancelTermBtn) {
        cancelTermBtn.addEventListener('click', function() {
            document.getElementById('newTermForm').style.display = 'none';
        });
    }
}

// Mostrar el modal del glosario
function showGlossary() {
    const glossaryModal = document.getElementById('glossaryModal');
    glossaryModal.style.display = 'block';
    
    // Enfocar el campo de búsqueda automáticamente
    setTimeout(() => {
        const glossarySearch = document.getElementById('glossarySearch');
        if (glossarySearch) {
            glossarySearch.focus();
        }
    }, 300);
    
    // Prevenir el scroll del body cuando el modal está abierto
    document.body.style.overflow = 'hidden';
}

// Cerrar el modal del glosario
function closeGlossary() {
    document.getElementById('glossaryModal').style.display = 'none';
    
    // Restaurar el scroll del body
    document.body.style.overflow = '';
}

// Filtrar términos del glosario según búsqueda
function filterGlossaryItems(searchTerm) {
    const glossaryItems = document.querySelectorAll('.glossary-item');
    const glossaryLetters = document.querySelectorAll('.glossary-letter');
    
    // Normalizar el término de búsqueda (quitar acentos y convertir a minúsculas)
    const normalizedSearchTerm = searchTerm.toLowerCase()
        .normalize("NFD").replace(/[\u0300-\u036f]/g, "");
    
    // Ocultar/mostrar términos del glosario basados en la búsqueda
    let visibleItemsCount = 0;
    
    glossaryItems.forEach(item => {
        const term = item.querySelector('h4').textContent.toLowerCase()
            .normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        const description = item.querySelector('p').textContent.toLowerCase()
            .normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        
        if (term.includes(normalizedSearchTerm) || description.includes(normalizedSearchTerm)) {
            item.style.display = 'block';
            visibleItemsCount++;
        } else {
            item.style.display = 'none';
        }
    });
    
    // Mostrar/ocultar encabezados de letra según si hay términos visibles
    glossaryLetters.forEach(letter => {
        const nextSibling = letter.nextElementSibling;
        let hasVisibleItems = false;
        
        if (nextSibling && nextSibling.classList.contains('glossary-list')) {
            hasVisibleItems = Array.from(nextSibling.children).some(item => item.style.display !== 'none');
        }
        
        letter.style.display = hasVisibleItems ? 'block' : 'none';
    });
    
    // Mostrar mensaje si no hay resultados
    const noResultsMsg = document.querySelector('.no-results-message');
    if (noResultsMsg) {
        noResultsMsg.style.display = visibleItemsCount === 0 ? 'block' : 'none';
    }
    
    // Desplazar al primer resultado visible si hay alguno
    if (visibleItemsCount > 0 && searchTerm.length > 0) {
        const firstVisibleItem = Array.from(glossaryItems).find(item => item.style.display !== 'none');
        if (firstVisibleItem) {
            // Desplazar suavemente al primer elemento visible
            setTimeout(() => {
                firstVisibleItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }, 100);
        }
    }
}

// Añadir un nuevo término al glosario
function addGlossaryTerm(term, definition, sectorId = null) {
    // En una implementación real, esto haría una solicitud AJAX para guardar el término
    const formData = new FormData();
    formData.append('termino', term);
    formData.append('definicion', definition);
    if (sectorId) {
        formData.append('sector_id', sectorId);
    }
    
    fetch('Controller/procesar_glosario.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showNotification('Término agregado correctamente al glosario', 'success');
            // Recargar la página o actualizar la lista de términos
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showNotification('Error al guardar el término: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error al procesar la solicitud', 'error');
    });
}

// Editar un término existente
function editGlossaryTerm(termId, term, definition, sectorId = null) {
    // En una implementación real, esto haría una solicitud AJAX para actualizar el término
    const formData = new FormData();
    formData.append('id', termId);
    formData.append('termino', term);
    formData.append('definicion', definition);
    if (sectorId) {
        formData.append('sector_id', sectorId);
    }
    
    fetch('Controller/actualizar_glosario.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showNotification('Término actualizado correctamente', 'success');
            // Recargar la página o actualizar la lista de términos
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showNotification('Error al actualizar el término: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error al procesar la solicitud', 'error');
    });
}

// Eliminar un término del glosario
function deleteGlossaryTerm(termId) {
    if (confirm('¿Está seguro que desea eliminar este término del glosario?')) {
        // En una implementación real, esto haría una solicitud AJAX para eliminar el término
        fetch('Controller/eliminar_glosario.php?id=' + termId)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showNotification('Término eliminado correctamente', 'success');
                    // Recargar la página o actualizar la lista de términos
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showNotification('Error al eliminar el término: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error al procesar la solicitud', 'error');
            });
    }
}

// Cargar el formulario de edición de un término
function loadEditGlossaryForm(termId) {
    // En una implementación real, esto cargaría los datos del término para editar
    fetch('Controller/obtener_termino.php?id=' + termId)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const term = data.termino;
                
                // Mostrar el formulario de edición
                const editForm = document.getElementById('editTermForm');
                editForm.style.display = 'block';
                
                // Completar los campos del formulario
                document.getElementById('editTermId').value = term.id;
                document.getElementById('editTermName').value = term.termino;
                document.getElementById('editTermDefinition').value = term.definicion;
                
                const sectorSelect = document.getElementById('editTermSector');
                if (sectorSelect) {
                    sectorSelect.value = term.sector_id || '';
                }
                
                // Desplazarse hasta el formulario
                editForm.scrollIntoView({ behavior: 'smooth' });
            } else {
                showNotification('Error al cargar el término: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error al procesar la solicitud', 'error');
        });
}

// Exportar el glosario a un archivo
function exportGlossary(format = 'pdf') {
    // Mostrar notificación de preparación
    showNotification('Preparando exportación del glosario...', 'info');
    
    // En una implementación real, esto generaría un archivo para descargar
    setTimeout(() => {
        // Simular finalización de la exportación
        if (format === 'pdf') {
            window.location.href = 'Controller/exportar_glosario.php?format=pdf';
        } else if (format === 'excel') {
            window.location.href = 'Controller/exportar_glosario.php?format=excel';
        } else {
            showNotification('Formato de exportación no soportado', 'error');
        }
    }, 1000);
}

// Imprimir el glosario
function printGlossary() {
    showNotification('Preparando glosario para impresión...', 'info');
    
    // Crear una versión para imprimir del glosario
    const printWindow = window.open('', '_blank');
    
    // Obtener contenido del glosario
    const glossaryContent = document.querySelector('.glossary-content');
    
    if (printWindow && glossaryContent) {
        // Crear documento para imprimir
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Glosario - Sistema de Gestión de Políticas y Procedimientos</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; }
                    h1 { text-align: center; margin-bottom: 30px; }
                    .glossary-letter { 
                        font-size: 24px; 
                        font-weight: bold; 
                        color: #3498db; 
                        border-bottom: 2px solid #3498db; 
                        margin: 20px 0 10px; 
                        padding-bottom: 5px; 
                    }
                    .glossary-item { margin-bottom: 15px; }
                    .glossary-item h4 { font-size: 16px; margin-bottom: 5px; }
                    .glossary-item p { margin-left: 15px; }
                    @media print {
                        body { font-size: 12pt; }
                        .glossary-letter { font-size: 18pt; }
                        .glossary-item h4 { font-size: 14pt; }
                        .glossary-item p { font-size: 12pt; }
                    }
                </style>
            </head>
            <body>
                <h1>Glosario de Términos</h1>
                ${glossaryContent.innerHTML}
            </body>
            </html>
        `);
        
        // Cerrar el documento y activar la impresión
        printWindow.document.close();
        setTimeout(() => {
            printWindow.print();
        }, 500);
    } else {
        showNotification('No se pudo preparar el glosario para impresión', 'error');
    }
}