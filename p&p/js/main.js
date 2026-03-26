
// main.js
// Archivo principal para el sistema de gestión de políticas y procedimientos

// Variables globales
let currentDocument = null;  // Almacena información del documento actual en visualización

// Inicialización al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar componentes
    if (typeof initSidebar === 'function') initSidebar();
    if (typeof initSearch === 'function') initSearch();
    if (typeof initPdfViewer === 'function') initPdfViewer();
    if (typeof initGlossary === 'function') initGlossary();
    if (typeof initFileUpload === 'function') initFileUpload();
    if (typeof initTagsInput === 'function') initTagsInput();
    if (typeof initFormValidation === 'function') initFormValidation();
    if (typeof checkUrlTab === 'function') checkUrlTab();
    
    // Verificar si hay mensajes de estado en la URL
    checkUrlMessages();
    
    // Inicializar tooltips y popovers si se usan
    initTooltips();
    
    // Cargar datos
    loadSampleData();
    
    // Cerrar los modales si el usuario hace clic fuera de ellos
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
            // Restaurar el scroll del body siempre que se cierre cualquier modal
            document.body.style.overflow = '';
            document.body.classList.remove('no-scroll');
        }
    };
    
    // Actualizar estadísticas cada 5 minutos
    setInterval(updateStatistics, 5 * 60 * 1000);
});

// ===== Funciones de inicialización de la interfaz =====

function initSidebar() {
    // Manejar la navegación del menú lateral
    document.querySelectorAll('.sidebar-menu a').forEach(link => {
        link.addEventListener('click', function(e) {
            // Eliminar la clase active de todos los enlaces
            document.querySelectorAll('.sidebar-menu a').forEach(l => l.classList.remove('active'));
            
            // Añadir la clase active al enlace clickeado
            this.classList.add('active');
            
            // Cerrar el menú móvil si está abierto
            if (window.innerWidth <= 576) {
                closeMobileMenu();
            }
        });
    });
    
    // Configurar menú móvil
    initMobileMenu();
}

function initMobileMenu() {
    const mobileToggle = document.getElementById('mobileMenuToggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (!mobileToggle) return;
    
    // Crear overlay si no existe
    let overlay = document.querySelector('.sidebar-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
        
        overlay.addEventListener('click', closeMobileMenu);
    }
    
    // Agregar botón de cerrar en el sidebar para móviles
    if (window.innerWidth <= 576) {
        const logo = sidebar.querySelector('.logo');
        let closeBtn = logo.querySelector('.mobile-close-btn');
        
        if (!closeBtn) {
            closeBtn = document.createElement('button');
            closeBtn.className = 'mobile-close-btn';
            closeBtn.innerHTML = '<i class="fas fa-times"></i>';
            closeBtn.setAttribute('aria-label', 'Cerrar menú');
            logo.appendChild(closeBtn);
            
            closeBtn.addEventListener('click', closeMobileMenu);
        }
    }
    
    // Toggle del menú
    mobileToggle.addEventListener('click', function() {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
        document.body.classList.toggle('no-scroll');
    });
}

function closeMobileMenu() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    
    if (sidebar) sidebar.classList.remove('active');
    if (overlay) overlay.classList.remove('active');
    document.body.classList.remove('no-scroll');
}

// Reinicializar el menú móvil cuando cambie el tamaño de ventana
window.addEventListener('resize', function() {
    if (window.innerWidth > 576) {
        closeMobileMenu();
    }
});

// ===== Manejo del formulario de subida de documentos =====
function handleUploadSubmit(event) {
    // Prevenir el envío normal del formulario
    event.preventDefault();
    
    // Validar que haya un archivo seleccionado
    const fileInput = document.getElementById('docFile');
    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
        showNotification('Por favor seleccione un archivo PDF', 'error');
        return false;
    }
    
    // Mostrar el modal INMEDIATAMENTE
    showAutoTagsModal();
    
    // Preparar datos del formulario
    const formData = new FormData(event.target);
    
    // Enviar el formulario mediante AJAX en segundo plano
    fetch('Controller/procesar_documento.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        // El documento se procesó correctamente
        // No hacer nada aquí, el modal ya está mostrándose
        console.log('Documento enviado correctamente para procesamiento');
        
        // Opcional: limpiar el formulario después de enviar
        setTimeout(() => {
            event.target.reset();
            removeFile();
        }, 1000);
    })
    .catch(error => {
        console.error('Error al subir documento:', error);
        // Si hay error, cerrar el modal y mostrar notificación
        closeAutoTagsModal();
        showNotification('Error al subir el documento. Por favor intente nuevamente.', 'error');
    });
    
    return false;
}

// ===== Funciones de gestión de la navegación por pestañas =====

function showTab(tabId) {
    // Ocultar todos los contenidos de tabs
    document.querySelectorAll('.tab-content').forEach(content => {
        content.style.display = 'none';
    });
    
    // Eliminar la clase active de todos los tabs
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Mostrar el contenido del tab seleccionado
    document.getElementById(tabId + '-content').style.display = 'block';
    
    // Añadir clase active al tab seleccionado
    const activeTab = document.querySelector(`.tab[onclick="showTab('${tabId}')"]`);
    if (activeTab) {
        activeTab.classList.add('active');
    }
    
    // Activar enlace en el menú lateral
    document.querySelectorAll('.sidebar-menu a').forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('onclick') && link.getAttribute('onclick').includes(tabId)) {
            link.classList.add('active');
        }
    });
}

// ===== Funciones de carga de datos =====

function loadSampleData() {
    // En una implementación real, cargaríamos datos desde una API o base de datos
    console.log('Cargando datos de ejemplo...');
    
    // Simular carga de datos con un pequeño retraso
    setTimeout(() => {
        // Aquí se podrían cargar datos dinámicamente
        console.log('Datos cargados correctamente');
    }, 500);
}

// ===== Funciones de utilidad =====

function showNotification(message, type = 'info') {
    // Crear un elemento de notificación
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas ${type === 'info' ? 'fa-info-circle' : type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
            <span>${message}</span>
        </div>
        <button class="close-notification">&times;</button>
    `;
    
    // Añadir al DOM
    const notificationContainer = document.querySelector('.notification-container');
    if (!notificationContainer) {
        // Crear contenedor si no existe
        const container = document.createElement('div');
        container.className = 'notification-container';
        document.body.appendChild(container);
        container.appendChild(notification);
    } else {
        notificationContainer.appendChild(notification);
    }
    
    // Configurar eliminación automática
    setTimeout(() => {
        notification.classList.add('fade-out');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 4000);
    
    // Configurar cierre manual
    notification.querySelector('.close-notification').addEventListener('click', () => {
        notification.classList.add('fade-out');
        setTimeout(() => {
            notification.remove();
        }, 300);
    });
}

// Verificar y mostrar mensajes de estado en la URL
function checkUrlMessages() {
    const urlParams = new URLSearchParams(window.location.search);
    const mensaje = urlParams.get('mensaje');
    const texto = urlParams.get('texto');
    const autoTags = urlParams.get('auto_tags');
    
    if (mensaje && texto) {
        let tipo = 'info';
        switch (mensaje) {
            case 'success':
                tipo = 'success';
                break;
            case 'error':
                tipo = 'error';
                break;
            case 'warning':
                tipo = 'warning';
                break;
        }
        
        // Mostrar notificación
        showNotification(decodeURIComponent(texto), tipo);
        
        // Si es un documento subido con tags automáticos, mostrar modal
        if (autoTags === '1' && tipo === 'success') {
            setTimeout(() => {
                showAutoTagsModal();
            }, 500); // Delay para que aparezca después de la notificación
        }
    }
}

// Inicializar tooltips (si se implementan)
function initTooltips() {
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    tooltipElements.forEach(element => {
        element.addEventListener('mouseover', function() {
            const tooltipText = this.getAttribute('data-tooltip');
            
            if (tooltipText) {
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                tooltip.textContent = tooltipText;
                
                document.body.appendChild(tooltip);
                
                const rect = this.getBoundingClientRect();
                tooltip.style.top = (rect.top - tooltip.offsetHeight - 10) + 'px';
                tooltip.style.left = (rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2)) + 'px';
                
                tooltip.classList.add('show');
                
                this.addEventListener('mouseout', function() {
                    tooltip.remove();
                });
            }
        });
    });
}

// Formatear fecha
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

// Verificar si un elemento está visible en el viewport
function isElementInViewport(el) {
    const rect = el.getBoundingClientRect();
    
    return (
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
        rect.right <= (window.innerWidth || document.documentElement.clientWidth)
    );
}

// Función para actualizar estadísticas de uso
function updateStatistics() {
    fetch('actualizar_estadisticas.php')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                console.log('Estadísticas actualizadas correctamente');
            }
        })
        .catch(error => {
            console.error('Error al actualizar estadísticas:', error);
        });
}

// Verificar si hay parámetros en la URL que indiquen la pestaña a mostrar
function checkUrlTab() {
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');
    
    if (tab) {
        showTab(tab);
    }
}

// ===== Funciones para el modal de tags automáticos =====

function showAutoTagsModal() {
    const modal = document.getElementById('autoTagsModal');
    if (modal) {
        modal.style.display = 'flex';
        
        // Limpiar la URL de parámetros después de mostrar el modal
        const url = new URL(window.location);
        url.searchParams.delete('mensaje');
        url.searchParams.delete('texto');
        url.searchParams.delete('auto_tags');
        window.history.replaceState({}, '', url);
    }
}

function closeAutoTagsModal() {
    const modal = document.getElementById('autoTagsModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// ===== Función para filtrar todos los documentos =====
function filterAllDocuments() {
    const sectorVal = document.getElementById('sectorFilterAll')?.value || '';
    const tipoVal   = document.getElementById('typeFilterAll')?.value   || '';
    let url = 'index.php?tab=all-documents';
    if (sectorVal) url += '&sector=' + encodeURIComponent(sectorVal);
    if (tipoVal)   url += '&tipo='   + encodeURIComponent(tipoVal);
    window.location.href = url;
}
