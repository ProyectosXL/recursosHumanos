
// search.js
// Funciones de búsqueda para el sistema de gestión de políticas y procedimientos

// Inicializar la búsqueda
function initSearch() {
    const searchInput = document.querySelector('.search-input');
    
    if (!searchInput) return;
    
    // Variable para debounce
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.trim();
        
        // Limpiar timeout anterior
        clearTimeout(searchTimeout);
        
        if (searchTerm.length === 0) {
            // Si está vacío, cerrar popup de búsqueda y limpiar glosario
            closeSearchResults();
            searchGlossary('');
            return;
        }
        
        if (searchTerm.length < 2) {
            // No buscar con menos de 2 caracteres
            return;
        }
        
        // Debounce: esperar 500ms después de dejar de escribir
        searchTimeout = setTimeout(() => {
            // Solo búsqueda en glosario (no en documentos recientes)
            searchGlossary(searchTerm.toLowerCase());
            
            // Búsqueda avanzada en contenido y tags (AJAX) - popup separado
            searchAdvanced(searchTerm);
        }, 500);
    });
    
    // Implementar búsqueda avanzada con teclas de acceso rápido
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            clearTimeout(searchTimeout);
            const searchTerm = this.value.trim();
            if (searchTerm.length >= 2) {
                // Solo búsqueda avanzada (popup), no en documentos recientes
                searchAdvanced(searchTerm);
            }
        }
    });
}

// Buscar en documentos
function searchDocuments(searchTerm, advancedSearch = false) {
    // Filtrado dinámico de documentos
    const allDocumentItems = document.querySelectorAll('.policy-item');
    let matchCount = 0;
    
    allDocumentItems.forEach(item => {
        const title = item.querySelector('h4')?.textContent.toLowerCase() || '';
        const details = item.querySelector('p')?.textContent.toLowerCase() || '';
        
        // Si no hay término de búsqueda, mostrar todos los documentos
        if (searchTerm.length === 0) {
            item.style.display = 'flex';
            item.style.backgroundColor = '';
            return;
        }
        
        // Buscar coincidencias en título y detalles
        if (title.includes(searchTerm) || details.includes(searchTerm)) {
            item.style.display = 'flex';
            
            // Destacar resultados solo si hay búsqueda activa
            if (searchTerm.length >= 2) {
                item.style.backgroundColor = '#f0f9ff';
                setTimeout(() => {
                    item.style.backgroundColor = '';
                }, 2000);
            }
            
            matchCount++;
        } else {
            // Ocultar elementos que no coinciden
            item.style.display = 'none';
        }
    });
    
    // Log para debugging (sin notificaciones molestas)
    if (searchTerm.length >= 2) {
        console.log(`Búsqueda: "${searchTerm}" - ${matchCount} resultados encontrados`);
    }
}

// Buscar en el glosario
function searchGlossary(searchTerm) {
    // Buscar en el glosario
    const glossaryItems = document.querySelectorAll('.glossary-item');
    
    glossaryItems.forEach(item => {
        const term = item.querySelector('h4').textContent.toLowerCase();
        const description = item.querySelector('p').textContent.toLowerCase();
        
        if (term.includes(searchTerm) || description.includes(searchTerm)) {
            // Resaltar temporalmente los términos encontrados
            item.style.backgroundColor = '#f0f9ff';
            setTimeout(() => {
                item.style.backgroundColor = '';
            }, 2000);
        }
    });
}

// Funciones para aplicar filtros combinados
function applyFilters() {
    const sectorFilter = document.getElementById('sectorFilter')?.value || '';
    const typeFilter = document.getElementById('typeFilter')?.value || '';
    const dateFilter = document.getElementById('dateFilter')?.value || '';
    
    let url = 'index.php?';
    
    if (sectorFilter) {
        url += 'sector=' + sectorFilter + '&';
    }
    
    if (typeFilter) {
        url += 'tipo=' + typeFilter + '&';
    }
    
    if (dateFilter) {
        url += 'fecha=' + dateFilter + '&';
    }
    
    // Eliminar el último '&' si existe
    if (url.endsWith('&')) {
        url = url.slice(0, -1);
    }
    
    window.location.href = url;
}

// Función para reiniciar filtros
function resetFilters() {
    window.location.href = 'index.php';
}

// Filtrar políticas por sector
function filterPolicies() {
    const sectorId = document.getElementById('sectorFilterPolicies')?.value || '';
    window.location.href = 'index.php?tipo=politica&sector=' + sectorId;
}

// Filtrar procedimientos por sector
function filterProcedures() {
    const sectorId = document.getElementById('sectorFilterProcedures')?.value || '';
    window.location.href = 'index.php?tipo=procedimiento&sector=' + sectorId;
}

// Filtrar documentos por sector específico
function filterBySection(sectorId) {
    window.location.href = 'index.php?sector=' + sectorId + '&tab=all-documents';
}

// Buscar dentro de un PDF
function searchInPdf() {
    // Simular búsqueda dentro del PDF
    const searchTerm = prompt("Introducir término a buscar en el documento:");
    if (searchTerm && searchTerm.trim() !== '') {
        showNotification(`Buscando "${searchTerm}" en el documento actual.`, 'info');
    }
}

// Búsqueda avanzada en contenido y tags (AJAX)
function searchAdvanced(searchTerm) {
    // Mostrar indicador de carga
    const searchResultsContainer = document.getElementById('searchResults');
    if (!searchResultsContainer) {
        createSearchResultsContainer();
    }
    
    const container = document.getElementById('searchResults');
    container.innerHTML = '<div class="search-loading"><i class="fas fa-spinner fa-spin"></i> Buscando en documentos...</div>';
    container.style.display = 'block';
    
    // Realizar búsqueda AJAX
    fetch(`Controller/buscar_documentos.php?q=${encodeURIComponent(searchTerm)}`)
        .then(response => {
            // Verificar si la respuesta es OK
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text(); // Primero obtener como texto
        })
        .then(text => {
            // Intentar parsear como JSON
            try {
                const data = JSON.parse(text);
                if (data.status === 'success') {
                    displaySearchResults(data.resultados, data.termino);
                } else {
                    container.innerHTML = `<div class="search-error"><i class="fas fa-exclamation-circle"></i> ${data.message || 'Error en la búsqueda'}</div>`;
                    setTimeout(() => {
                        container.style.display = 'none';
                    }, 3000);
                }
            } catch (e) {
                // Si no es JSON válido, mostrar el texto crudo para debug
                console.error('Respuesta no es JSON válido:', text.substring(0, 500));
                container.innerHTML = '<div class="search-error"><i class="fas fa-exclamation-circle"></i> Error del servidor</div>';
                setTimeout(() => {
                    container.style.display = 'none';
                }, 3000);
            }
        })
        .catch(error => {
            console.error('Error en búsqueda avanzada:', error);
            container.innerHTML = '<div class="search-error"><i class="fas fa-exclamation-circle"></i> Error al buscar en documentos</div>';
            setTimeout(() => {
                container.style.display = 'none';
            }, 3000);
        });
}

// Crear contenedor de resultados si no existe
function createSearchResultsContainer() {
    const container = document.createElement('div');
    container.id = 'searchResults';
    container.className = 'search-results-container';
    
    // Insertar después del input de búsqueda
    const searchInput = document.querySelector('.search-input');
    if (searchInput && searchInput.parentElement) {
        searchInput.parentElement.appendChild(container);
    }
}

// Mostrar resultados de búsqueda avanzada
function displaySearchResults(resultados, termino) {
    const container = document.getElementById('searchResults');
    
    if (!resultados || resultados.length === 0) {
        container.innerHTML = `
            <div class="search-no-results">
                <i class="fas fa-search"></i>
                <p>No se encontraron coincidencias para "<strong>${termino}</strong>"</p>
            </div>
        `;
        setTimeout(() => {
            container.style.display = 'none';
        }, 3000);
        return;
    }
    
    let html = `
        <div class="search-results-header">
            <h4><i class="fas fa-search"></i> Resultados para "${termino}"</h4>
            <span class="results-count">${resultados.length} documento${resultados.length !== 1 ? 's' : ''}</span>
            <button class="close-search-results" onclick="closeSearchResults()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="search-results-list">
    `;
    
    resultados.forEach(resultado => {
        // Separar los tipos de coincidencias para el header vs badges
        const tituloMatch = resultado.coincidencias.find(c => c.tipo === 'titulo');
        const otrasCoincidencias = resultado.coincidencias.filter(c => c.tipo !== 'titulo');

        // Si hay match en título, usar el texto resaltado como nombre del documento
        const tituloMostrado = tituloMatch
            ? tituloMatch.texto
            : resultado.titulo;

        html += `
            <div class="search-result-item">
                <div class="result-header">
                    <h5 onclick="viewDocument(${resultado.id})" style="cursor: pointer;">
                        <i class="fas fa-file-pdf"></i> ${tituloMostrado}
                    </h5>
                    <span class="result-type badge-${resultado.tipo}">${resultado.tipo}</span>
                </div>
        `;

        if (tituloMatch) {
            html += `<div class="match-source-row"><i class="fas fa-heading"></i><span>Coincidencia en t&iacute;tulo</span></div>`;
        }

        if (otrasCoincidencias.length > 0) {
            html += `<div class="result-matches">`;
            otrasCoincidencias.forEach(coincidencia => {
                if (coincidencia.tipo === 'tag') {
                    html += `
                        <div class="match-item match-tag">
                            <i class="fas fa-tag"></i>
                            <span>${coincidencia.texto}</span>
                        </div>
                    `;
                } else {
                    html += `
                        <div class="match-item match-content">
                            <i class="fas fa-quote-left"></i>
                            <span>${coincidencia.texto}</span>
                        </div>
                    `;
                }
            });
            html += `</div>`;
        }

        html += `</div>`;
    });
    
    html += '</div>';
    
    container.innerHTML = html;
    container.style.display = 'block';
}

// Cerrar resultados de búsqueda
function closeSearchResults() {
    const container = document.getElementById('searchResults');
    if (container) {
        container.style.display = 'none';
    }
}

// Cerrar resultados al hacer clic fuera
document.addEventListener('click', function(e) {
    const searchContainer = document.querySelector('.search-container');
    const searchResults = document.getElementById('searchResults');
    
    if (searchResults && searchResults.style.display === 'block') {
        // Si el clic no fue dentro del contenedor de búsqueda, cerrar resultados
        if (!searchContainer.contains(e.target)) {
            closeSearchResults();
        }
    }
});