
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
    
    // Configuración (solo Admin): alta / edición de términos
    initTermForm();
}

// Formulario de términos y buscador de la lista de Configuración
function initTermForm() {
    const form = document.getElementById('termForm');
    if (!form) return; // Configuración no se renderizó para este usuario

    const newTermBtn = document.getElementById('newTermBtn');
    if (newTermBtn) {
        newTermBtn.addEventListener('click', function () {
            resetTermForm();
            document.getElementById('newTermForm').style.display = 'block';
            document.getElementById('termName').focus();
        });
    }

    const cancelTermBtn = document.getElementById('cancelTermBtn');
    if (cancelTermBtn) {
        cancelTermBtn.addEventListener('click', function () {
            resetTermForm();
            document.getElementById('newTermForm').style.display = 'none';
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const termino = document.getElementById('termName').value.trim();
        const definicion = document.getElementById('termDefinition').value.trim();
        const sectorId = document.getElementById('termSector').value;
        const termId = document.getElementById('termId').value;

        if (!termino || !definicion) {
            showNotification('El término y la definición son obligatorios', 'error');
            return;
        }
        if (termId) {
            editGlossaryTerm(termId, termino, definicion, sectorId);
        } else {
            addGlossaryTerm(termino, definicion, sectorId);
        }
    });

    const filtro = document.getElementById('cfgTermFilter');
    if (filtro) {
        filtro.addEventListener('input', function () {
            const buscado = this.value.trim().toLowerCase();
            document.querySelectorAll('#cfgTermList .cfg-term-item').forEach(item => {
                item.style.display = !buscado || (item.dataset.buscar || '').includes(buscado) ? '' : 'none';
            });
        });
    }
}

// Dejar el formulario listo para un término nuevo
function resetTermForm() {
    const form = document.getElementById('termForm');
    if (!form) return;
    form.reset();
    document.getElementById('termId').value = '';
    document.getElementById('termFormTitle').textContent = 'Nuevo término';
}

// Recargar Configuración después de guardar (el glosario se arma en el servidor para todos)
function recargarConfiguracion() {
    setTimeout(() => window.location.href = 'index.php?tab=settings', 1200);
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
    const formData = new FormData();
    formData.append('termino', term);
    formData.append('definicion', definition);
    if (sectorId) {
        formData.append('sector_id', sectorId);
    }

    fetch('Controller/procesar_glosario.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showNotification('Término agregado al glosario', 'success');
                recargarConfiguracion();
            } else {
                showNotification(data.message || 'No se pudo guardar el término', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error al procesar la solicitud', 'error');
        });
}

// Editar un término existente
function editGlossaryTerm(termId, term, definition, sectorId = null) {
    const formData = new FormData();
    formData.append('id', termId);
    formData.append('termino', term);
    formData.append('definicion', definition);
    formData.append('sector_id', sectorId || '');

    fetch('Controller/actualizar_glosario.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showNotification('Término actualizado', 'success');
                recargarConfiguracion();
            } else {
                showNotification(data.message || 'No se pudo actualizar el término', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error al procesar la solicitud', 'error');
        });
}

// Eliminar un término del glosario (con confirmación propia)
function deleteGlossaryTerm(termId) {
    const boton = document.querySelector(`.cfg-delete[onclick^="deleteGlossaryTerm(${Number(termId)})"]`);
    const nombre = boton ? boton.dataset.nombre : '';

    pedirConfirmacion({
        titulo: 'Eliminar término',
        mensaje: `¿Querés eliminar "${nombre}" del glosario?`,
        textoConfirmar: 'Eliminar',
        peligro: true
    }).then(respuesta => {
        if (!respuesta.ok) return;

        const formData = new FormData();
        formData.append('id', termId);

        fetch('Controller/eliminar_glosario.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showNotification('Término eliminado', 'success');
                    recargarConfiguracion();
                } else {
                    showNotification(data.message || 'No se pudo eliminar el término', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error al procesar la solicitud', 'error');
            });
    });
}

// Cargar un término en el formulario de Configuración para editarlo
function loadEditGlossaryForm(termId) {
    fetch('Controller/obtener_termino.php?id=' + encodeURIComponent(termId))
        .then(response => response.json())
        .then(data => {
            if (data.status !== 'success') {
                showNotification(data.message || 'No se pudo cargar el término', 'error');
                return;
            }
            const term = data.termino;

            document.getElementById('newTermForm').style.display = 'block';
            document.getElementById('termFormTitle').textContent = 'Editar término';
            document.getElementById('termId').value = term.id;
            document.getElementById('termName').value = term.termino;
            document.getElementById('termDefinition').value = term.definicion;
            document.getElementById('termSector').value = term.sector_id || '';

            document.getElementById('newTermForm').scrollIntoView({ behavior: 'smooth' });
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