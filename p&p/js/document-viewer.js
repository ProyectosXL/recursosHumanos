
//document-viewer.js

// Función para visualizar un documento por su ID
function viewDocument(documentId) {
    // Mostrar un indicador de carga
    showNotification('Cargando documento...', 'info');
    
    // Realizar petición para obtener la información del documento
    fetch('Controller/obtener_documento.php?id=' + documentId)
        .then(response => {
            // Verificar si la respuesta es válida
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor: ' + response.status);
            }
            return response.text(); // Primero obtenemos el texto
        })
        .then(text => {
            try {
                return JSON.parse(text); // Intentamos parsearlo como JSON
            } catch (e) {
                console.error('Error al parsear JSON:', text);
                throw new Error('La respuesta no es un JSON válido');
            }
        })
        .then(data => {
            if (data.status === 'success' && data.documento) {
                // Procesar el documento
                currentDocument = data.documento;
                
                // Establecer título
                document.getElementById('pdfTitle').textContent = currentDocument.titulo || 'Documento sin título';
                
                // Preparar ruta al PDF
                const pdfPath = 'documentos/' + currentDocument.archivo_nombre;
                
                // Mostrar información del documento y visor básico
                const pdfViewer = document.getElementById('pdfViewer');
                pdfViewer.innerHTML = `
                    <div style="padding: 20px; text-align: center;">
                        <h4>Visualizando: ${currentDocument.titulo || 'Documento sin título'}</h4>
                        <p>Sector: ${currentDocument.sector_nombre || 'No especificado'}</p>
                        <p>Fecha: ${currentDocument.fecha_actualizacion || 'No disponible'}</p>
                        <div class="pdf-container">
                            <object data="${pdfPath}" type="application/pdf" width="100%" height="500px">
                                <p>Tu navegador no puede mostrar PDFs directamente. 
                                <a href="${pdfPath}" target="_blank">Haz clic aquí para descargar el PDF</a>.</p>
                            </object>
                        </div>
                        <div class="pdf-controls">
                            <a href="${pdfPath}" target="_blank" class="pdf-btn">
                                <i class="fas fa-external-link-alt"></i> Abrir en nueva ventana
                            </a>
                            <a href="Controller/descargar_documento.php?id=${documentId}" class="pdf-btn">
                                <i class="fas fa-download"></i> Descargar
                            </a>
                        </div>
                    </div>
                `;
                
                // Mostrar el modal
                document.getElementById('pdfViewerModal').style.display = 'block';
            } else {
                showNotification('Error: ' + (data.message || 'No se pudo cargar el documento'), 'error');
                fallbackDocumentView(documentId);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error al cargar el documento: ' + error.message, 'error');
            fallbackDocumentView(documentId);
        });
}

// Función de reserva para mostrar algo cuando falla la carga
function fallbackDocumentView(documentId) {
    // Datos simulados para cuando falla la carga
    const documentTitle = "Documento #" + documentId;
    
    // Establecer título
    document.getElementById('pdfTitle').textContent = documentTitle;
    
    // Cargar contenido de reserva
    const pdfViewer = document.getElementById('pdfViewer');
    pdfViewer.innerHTML = `
        <div style="padding: 20px; text-align: center;">
            <h4>Error al cargar: ${documentTitle}</h4>
            <p>No se pudo obtener información detallada del documento.</p>
            <p>Por favor, verifica la conexión con el servidor o contacta al administrador.</p>
            <div class="pdf-placeholder" style="color: #e74c3c; font-size: 48px; margin: 30px 0;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
        </div>
    `;
    
    // Mostrar el modal
    document.getElementById('pdfViewerModal').style.display = 'block';
}

// Función para renderizar el PDF usando PDF.js
let pdfDoc = null;
let pageNum = 1;
let pageRendering = false;
let pageNumPending = null;
let scale = 1.5;

function renderPDF(pdfUrl) {
    // Cargar el documento PDF
    pdfjsLib.getDocument(pdfUrl).promise.then(function(pdf) {
        pdfDoc = pdf;
        document.getElementById('totalPages').textContent = pdf.numPages;
        
        // Habilitar botones de navegación si hay más de una página
        if (pdf.numPages > 1) {
            document.getElementById('nextPage').disabled = false;
        }
        
        // Renderizar la primera página
        renderPage(pageNum);
    }).catch(function(error) {
        console.error('Error al cargar el PDF:', error);
        document.getElementById('pdfViewer').innerHTML = `
            <div class="pdf-error">
                <i class="fas fa-exclamation-triangle"></i>
                <p>Error al cargar el documento PDF. ${error.message}</p>
            </div>
        `;
    });
    
    // Configurar eventos para los botones de navegación
    document.getElementById('prevPage').addEventListener('click', onPrevPage);
    document.getElementById('nextPage').addEventListener('click', onNextPage);
}

// Renderizar una página específica
function renderPage(num) {
    pageRendering = true;
    
    // Actualizar indicador de página actual
    document.getElementById('currentPage').textContent = num;
    
    // Habilitar/deshabilitar botones de navegación según la página actual
    document.getElementById('prevPage').disabled = (num <= 1);
    document.getElementById('nextPage').disabled = (num >= pdfDoc.numPages);
    
    // Obtener la página del documento
    pdfDoc.getPage(num).then(function(page) {
        const canvas = document.getElementById('pdfCanvas');
        const ctx = canvas.getContext('2d');
        
        // Calcular dimensiones del canvas según la escala
        const viewport = page.getViewport({ scale: scale });
        canvas.height = viewport.height;
        canvas.width = viewport.width;
        
        // Renderizar el PDF en el canvas
        const renderContext = {
            canvasContext: ctx,
            viewport: viewport
        };
        
        const renderTask = page.render(renderContext);
        
        // Esperar a que termine la renderización
        renderTask.promise.then(function() {
            pageRendering = false;
            
            // Si hay una petición pendiente, renderizar esa página
            if (pageNumPending !== null) {
                renderPage(pageNumPending);
                pageNumPending = null;
            }
            
            // Ocultar el indicador de carga
            const loadingElement = document.querySelector('.pdf-loading');
            if (loadingElement) {
                loadingElement.style.display = 'none';
            }
        });
    });
}

// Funciones para navegar entre páginas
function queueRenderPage(num) {
    if (pageRendering) {
        pageNumPending = num;
    } else {
        renderPage(num);
    }
}

function onPrevPage() {
    if (pageNum <= 1) return;
    pageNum--;
    queueRenderPage(pageNum);
}

function onNextPage() {
    if (pageNum >= pdfDoc.numPages) return;
    pageNum++;
    queueRenderPage(pageNum);
}

// Buscar en el PDF
function searchInPdf() {
    // Implementación básica para buscar en el PDF
    const searchTerm = prompt("Introducir término a buscar en el documento:");
    if (!searchTerm || searchTerm.trim() === '') return;
    
    showNotification(`Buscando "${searchTerm}" en el documento...`, 'info');
    
    // En una implementación completa, aquí usaríamos la API de búsqueda de PDF.js
    // Por ahora, simplemente mostramos un mensaje
    setTimeout(() => {
        alert(`Función de búsqueda: En una implementación completa, aquí se buscaría "${searchTerm}" en el documento PDF.`);
    }, 1000);
}

// Función para cerrar el visor de PDF
function closePdfViewer() {
    // Obtener el modal
    const modal = document.getElementById('pdfViewerModal');
    
    // Ocultar el modal
    if (modal) {
        modal.style.display = 'none';
    }
    
    // Limpiar el contenido del visor para liberar memoria
    const pdfViewer = document.getElementById('pdfViewer');
    if (pdfViewer) {
        pdfViewer.innerHTML = '<p>Visor de PDF cargando...</p>';
    }
    
    // Resetear variables si estamos usando PDF.js
    pdfDoc = null;
    pageNum = 1;
    pageRendering = false;
    pageNumPending = null;
}

// Función para descargar un documento por su ID
function downloadDocument(documentId) {
    // Mostrar un indicador de carga
    showNotification('Preparando descarga...', 'info');
    
    // Redirigir al script de descarga con el ID del documento
    window.location.href = 'Controller/descargar_documento.php?id=' + documentId;
}