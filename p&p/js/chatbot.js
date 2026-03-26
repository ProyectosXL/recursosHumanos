/**
 * Chatbot RAG - Asistente Virtual DocuGest
 * Integración con el servicio RAG de Python para búsquedas en lenguaje natural
 */

// Configuración
const RAG_API_URL = 'https://cfedetrejo-docugest-unified.hf.space';
let chatbotOpen = false;
let chatHistory = [];

// Estado de wake-up del HF Space
let ragWakingUp = false;
let ragWakeRetryCount = 0;
const RAG_WAKE_MAX_RETRIES = 15;        // 15 intentos
const RAG_WAKE_RETRY_INTERVAL = 20000;  // 20 segundos entre reintentos (~5 min máx)
let ragWakeRetryTimer = null;

// Inicializar chatbot al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    verificarServicioRAG();
    configurarInputChatbot();
});

/**
 * Muestra un overlay de carga sobre el chatbot
 */
function mostrarOverlayCarga() {
    let overlay = document.getElementById('chatbot-loading-overlay');
    
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'chatbot-loading-overlay';
        overlay.className = 'chatbot-loading-overlay';
        
        const details = window.ragInitDetails || [];
        let mensaje = 'Inicializando el asistente virtual DocuGest...';
        let submensaje = '';
        let detallesHTML = '';
        
        // Buscar detalles específicos de indexación
        const detalle_iniciando = details.find(d => d.toLowerCase().includes('iniciando servicio'));
        const detalle_indexacion = details.find(d => d.toLowerCase().includes('indexación') || d.toLowerCase().includes('indexando'));
        const detalle_pdfs = details.find(d => d.toLowerCase().includes('pdfs en carpeta'));
        const detalle_faltantes = details.find(d => d.toLowerCase().includes('documentos sin indexar:'));
        
        if (detalle_iniciando) {
            mensaje = '🚀 Iniciando bot...';
            submensaje = 'Iniciando servicio RAG (10-15 segundos)';
        } else if (detalle_indexacion || detalle_faltantes) {
            mensaje = '📚 Indexando documentos...';
            if (detalle_pdfs) {
                submensaje = detalle_pdfs;
            } else {
                submensaje = 'Cargando documentos faltantes en el sistema...';
            }
            
            // Mostrar qué documentos se están indexando
            if (detalle_faltantes) {
                detallesHTML = `<div class="loading-details">${detalle_faltantes}</div>`;
            }
        } else {
            submensaje = 'Configurando el sistema...';
        }
        
        overlay.innerHTML = `
            <div class="loading-content">
                <div class="loading-spinner"></div>
                <div class="loading-message">${mensaje}</div>
                <div class="loading-submessage">${submensaje}</div>
                ${detallesHTML}
            </div>
        `;
        
        const chatContainer = document.getElementById('chatbot-container');
        if (chatContainer) {
            chatContainer.appendChild(overlay);
        }
    }
}

/**
 * Oculta el overlay de carga
 */
function ocultarOverlayCarga() {
    const overlay = document.getElementById('chatbot-loading-overlay');
    if (overlay) {
        overlay.remove();
    }
}

/**
 * Verifica el estado del servicio RAG
 */
async function verificarServicioRAG() {
    const statusIcon = document.getElementById('chatbot-status-icon');
    const statusText = document.getElementById('chatbot-status-text');
    
    // Verificar si el sistema está inicializándose
    if (window.ragInitializing) {
        console.log('🔄 Sistema aún inicializándose...');
        
        statusIcon.className = 'status-dot loading';
        statusText.textContent = '⏳ Iniciando bot...';
        
        // Mostrar overlay de carga
        mostrarOverlayCarga();
        
        // Reintentar después de 2 segundos (máximo 5 veces = 10 segundos total)
        if (!window.ragVerifyAttempts) window.ragVerifyAttempts = 0;
        window.ragVerifyAttempts++;
        
        if (window.ragVerifyAttempts < 5) {
            setTimeout(() => verificarServicioRAG(), 2000);
        } else {
            // Forzar finalización después de 5 intentos
            console.warn('⚠️ Timeout: Forzando finalización de inicialización');
            window.ragInitializing = false;
            window.ragVerifyAttempts = 0;
            ocultarOverlayCarga();
            // Llamar de nuevo para verificar el estado real
            setTimeout(() => verificarServicioRAG(), 100);
        }
        return;
    }
    
    // Resetear contador de intentos y ocultar overlay
    window.ragVerifyAttempts = 0;
    ocultarOverlayCarga();
    
    try {
        // Timeout más largo si estamos esperando que despierte el HF Space
        const controller = new AbortController();
        const timeoutDuration = ragWakingUp ? 30000 : 10000;
        const timeoutId = setTimeout(() => controller.abort(), timeoutDuration);
        
        const response = await fetch(`${RAG_API_URL}/health`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            },
            signal: controller.signal
        });
        
        clearTimeout(timeoutId);
        
        if (response.ok) {
            const data = await response.json();
            
            // --- Si estábamos en modo wake-up, notificar éxito ---
            if (ragWakingUp) {
                ragWakingUp = false;
                ragWakeRetryCount = 0;
                if (ragWakeRetryTimer) { clearTimeout(ragWakeRetryTimer); ragWakeRetryTimer = null; }
                
                // Rehabilitar input
                const inp = document.getElementById('chatbot-input');
                const btn = document.getElementById('chatbot-send-btn');
                if (inp) { inp.disabled = false; inp.placeholder = 'Escribe tu pregunta aquí...'; }
                if (btn) btn.disabled = false;
                
                agregarMensajeSistema(
                    '✅ ¡Asistente listo!',
                    'El servicio se ha iniciado correctamente. Ya puedes hacer tus consultas.'
                );
            }
            
            // Verificar documentos faltantes (sin esperar)
            verificarDocumentosFaltantes(data.total_chunks).catch(err => 
                console.warn('Error verificando documentos faltantes:', err)
            );
            
            statusIcon.className = 'status-dot online';
            
            // Mostrar información detallada
            if (data.total_chunks === 0) {
                statusText.textContent = 'En línea - Sin documentos indexados';
            } else {
                const docCount = data.total_documentos || data.total_documents || Math.floor(data.total_chunks / 10);
                statusText.textContent = `En línea - ${docCount} documentos (${data.total_chunks} chunks)`;
            }
            
            console.log('✅ Servicio RAG conectado correctamente');
        } else {
            throw new Error('Servicio no disponible (HTTP ' + response.status + ')');
        }
    } catch (error) {
        // =====================================================================
        //  WAKE-UP LOOP: Si el HF Space está dormido, reintentar periódicamente
        // =====================================================================
        if (!ragWakingUp) {
            // Primera falla → activar modo wake-up
            ragWakingUp = true;
            ragWakeRetryCount = 0;
            
            agregarMensajeSistema(
                '💤 El asistente está despertando…',
                'El servicio estaba inactivo y se está reiniciando automáticamente. '
                + 'Esto puede tomar entre 1 y 3 minutos. Por favor, esperá un momento.'
            );
            
            // Deshabilitar input mientras despierta
            const inp = document.getElementById('chatbot-input');
            const btn = document.getElementById('chatbot-send-btn');
            if (inp) { inp.disabled = true; inp.placeholder = 'Esperando al asistente…'; }
            if (btn) btn.disabled = true;
        }
        
        ragWakeRetryCount++;
        
        statusIcon.className = 'status-dot waking';
        statusText.textContent = `Despertando… (${ragWakeRetryCount}/${RAG_WAKE_MAX_RETRIES})`;
        
        if (error.name === 'AbortError') {
            console.warn(`⏱️ Timeout health-check (intento ${ragWakeRetryCount})`);
        } else {
            console.warn(`🔄 HF Space no responde (intento ${ragWakeRetryCount}):`, error.message);
        }
        
        if (ragWakeRetryCount < RAG_WAKE_MAX_RETRIES) {
            ragWakeRetryTimer = setTimeout(() => verificarServicioRAG(), RAG_WAKE_RETRY_INTERVAL);
        } else {
            // Se agotaron los reintentos
            ragWakingUp = false;
            ragWakeRetryCount = 0;
            statusIcon.className = 'status-dot offline';
            statusText.textContent = 'Sin conexión';
            
            const inp = document.getElementById('chatbot-input');
            const btn = document.getElementById('chatbot-send-btn');
            if (inp) { inp.disabled = false; inp.placeholder = 'Escribe tu pregunta aquí...'; }
            if (btn) btn.disabled = false;
            
            agregarMensajeSistema(
                '❌ No se pudo conectar',
                'No fue posible conectarse con el asistente después de varios intentos. '
                + 'Intenta recargar la página más tarde.'
            );
        }
    }
}

/**
 * Verifica si hay documentos faltantes por indexar
 */
async function verificarDocumentosFaltantes(chunksActuales) {
    try {
        const response = await fetch('Controller/verificar_documentos_faltantes.php');
        if (response.ok) {
            const data = await response.json();
            if (data.success && data.faltantes > 0) {
                console.warn(`⚠️ Documentos sin indexar: ${data.faltantes} de ${data.total_pdfs}`);
                // No mostrar mensaje al usuario automáticamente, solo en consola
            }
        }
    } catch (error) {
        console.error('Error al verificar documentos faltantes:', error);
    }
}

/**
 * Alterna la visibilidad del chatbot
 */
function toggleChatbot() {
    const container = document.getElementById('chatbot-container');
    const toggleBtn = document.getElementById('chatbot-toggle-btn');
    const badge = document.getElementById('chatbot-badge');
    const menuBtn = document.getElementById('chatbotMenuBtn');
    
    chatbotOpen = !chatbotOpen;
    
    if (chatbotOpen) {
        container.classList.add('open');
        toggleBtn.classList.add('hidden');
        badge.style.display = 'none';
        if (menuBtn) menuBtn.classList.add('active');
        
        // Focus en el input
        setTimeout(() => {
            document.getElementById('chatbot-input').focus();
        }, 300);
    } else {
        container.classList.remove('open');
        toggleBtn.classList.remove('hidden');
        if (menuBtn) menuBtn.classList.remove('active');
    }
}

/**
 * Configura el comportamiento del textarea del chatbot
 */
function configurarInputChatbot() {
    const input = document.getElementById('chatbot-input');
    
    if (!input) return;
    
    // Auto-resize del textarea
    input.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });
    
    // Enviar con Enter (Shift+Enter para nueva línea)
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendChatbotMessage();
        }
    });
}

/**
 * Agrega un mensaje de sistema al chat (notificaciones, estados)
 */
function agregarMensajeSistema(titulo, texto) {
    const messagesContainer = document.getElementById('chatbot-messages');
    if (!messagesContainer) return;
    
    const messageDiv = document.createElement('div');
    messageDiv.className = 'chatbot-message system';
    messageDiv.innerHTML = `
        <div class="message-content system-message">
            <p class="system-title">${titulo}</p>
            <p class="system-text">${texto}</p>
        </div>
    `;
    messagesContainer.appendChild(messageDiv);
    scrollToBottom();
}

/**
 * Envía un mensaje al chatbot
 */
async function sendChatbotMessage() {
    const input = document.getElementById('chatbot-input');
    const sendBtn = document.getElementById('chatbot-send-btn');
    const message = input.value.trim();
    
    if (!message) return;
    
    // Si el Space está despertando, avisar al usuario
    if (ragWakingUp) {
        agregarMensajeSistema(
            '⏳ Un momento…',
            'El asistente aún se está iniciando. Por favor esperá a que esté disponible.'
        );
        return;
    }
    
    // Agregar mensaje del usuario
    agregarMensajeUsuario(message);
    
    // Limpiar input
    input.value = '';
    input.style.height = 'auto';
    
    // Deshabilitar input mientras se procesa
    input.disabled = true;
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    // Mostrar indicador de escritura
    const typingId = mostrarIndicadorEscritura();
    
    try {
        // Enviar consulta al servicio RAG con historial de conversación
        const historial = chatHistory.slice(-5).map(h => ({
            pregunta: h.pregunta,
            respuesta: h.respuesta
        }));

        const response = await fetch(`${RAG_API_URL}/api/query`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                pregunta: message,
                top_k: 5,
                historial: historial
            })
        });
        
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({ detail: 'Error desconocido' }));
            
            // Manejo específico de error de cuota
            if (response.status === 429) {
                throw new Error('QUOTA_EXCEEDED: ' + (errorData.detail || 'Has excedido la cuota de la API'));
            }
            
            throw new Error(`Error del servidor: ${response.status} - ${errorData.detail || ''}`);
        }
        
        const data = await response.json();
        
        // Eliminar indicador de escritura
        eliminarIndicadorEscritura(typingId);
        
        // Agregar respuesta del bot
        agregarMensajeBot(data.respuesta, data.documentos_fuente);
        
        // Guardar en historial
        chatHistory.push({
            pregunta: message,
            respuesta: data.respuesta,
            documentos: data.documentos_fuente,
            timestamp: new Date()
        });
        
    } catch (error) {
        console.error('Error al consultar RAG:', error);
        eliminarIndicadorEscritura(typingId);
        
        // Mensaje personalizado según el tipo de error
        let errorMessage;
        if (error.message.includes('QUOTA_EXCEEDED')) {
            errorMessage = '⏳ Has alcanzado el límite de consultas de la API de Gemini. Por favor, espera unos minutos e intenta nuevamente.';
        } else if (error.message.includes('Failed to fetch')) {
            errorMessage = '🔌 No se pudo conectar con el servicio RAG. Verifica que esté funcionando.';
        } else {
            errorMessage = `❌ Error al procesar tu consulta: ${error.message}`;
        }
        
        agregarMensajeBot(errorMessage);
    } finally {
        // Rehabilitar input
        input.disabled = false;
        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
        input.focus();
    }
}

/**
 * Agrega un mensaje del usuario al chat
 */
function agregarMensajeUsuario(texto) {
    const messagesContainer = document.getElementById('chatbot-messages');
    
    const messageDiv = document.createElement('div');
    messageDiv.className = 'chatbot-message user';
    
    messageDiv.innerHTML = `
        <div class="message-content">
            <p>${escapeHtml(texto)}</p>
        </div>
        <div class="message-avatar">
            <i class="fas fa-user"></i>
        </div>
    `;
    
    messagesContainer.appendChild(messageDiv);
    scrollToBottom();
}

/**
 * Agrega un mensaje del bot al chat
 */
function agregarMensajeBot(texto, documentosFuente = []) {
    const messagesContainer = document.getElementById('chatbot-messages');
    
    const messageDiv = document.createElement('div');
    messageDiv.className = 'chatbot-message bot';
    
    let html = `
        <div class="message-avatar">
            <i class="fas fa-robot"></i>
        </div>
        <div class="message-content">
            <p>${formatearRespuesta(texto)}</p>
    `;
    
    // Si hay documentos fuente, mostrarlos
    if (documentosFuente && documentosFuente.length > 0) {
        html += '<div class="message-sources">';
        html += '<p class="sources-title"><i class="fas fa-file-pdf"></i> Fuentes consultadas:</p>';
        html += '<ul class="sources-list">';
        
        documentosFuente.forEach(doc => {
            html += `
                <li class="source-item">
                    <a href="Controller/descargar_documento.php?id=${doc.document_id}" 
                       class="source-download-link" 
                       title="Descargar ${escapeHtml(doc.titulo)}">
                        <i class="fas fa-file-pdf"></i> ${escapeHtml(doc.titulo)}
                    </a>
                    <span class="source-sector">${escapeHtml(doc.sector)}</span>
                    <button class="source-view-btn" onclick="viewDocument(${doc.document_id})">
                        <i class="fas fa-eye"></i>
                    </button>
                </li>
            `;
        });
        
        html += '</ul></div>';
    }
    
    html += '</div>';
    
    messageDiv.innerHTML = html;
    messagesContainer.appendChild(messageDiv);
    scrollToBottom();
}

/**
 * Muestra indicador de que el bot está escribiendo
 */
function mostrarIndicadorEscritura() {
    const messagesContainer = document.getElementById('chatbot-messages');
    
    const typingDiv = document.createElement('div');
    const typingId = 'typing-' + Date.now();
    typingDiv.id = typingId;
    typingDiv.className = 'chatbot-message bot typing';
    
    typingDiv.innerHTML = `
        <div class="message-avatar">
            <i class="fas fa-robot"></i>
        </div>
        <div class="message-content">
            <div class="typing-indicator">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    `;
    
    messagesContainer.appendChild(typingDiv);
    scrollToBottom();
    
    return typingId;
}

/**
 * Elimina el indicador de escritura
 */
function eliminarIndicadorEscritura(typingId) {
    const typingDiv = document.getElementById(typingId);
    if (typingDiv) {
        typingDiv.remove();
    }
}

/**
 * Scroll automático al final del chat
 */
function scrollToBottom() {
    const messagesContainer = document.getElementById('chatbot-messages');
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

/**
 * Formatea la respuesta del bot (convierte markdown básico a HTML)
 */
function formatearRespuesta(texto) {
    let formatted = escapeHtml(texto);
    
    // Convertir **negrita** a <strong>
    formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    
    // Convertir saltos de línea dobles a párrafos
    formatted = formatted.replace(/\n\n/g, '</p><p>');
    
    // Convertir saltos de línea simples a <br>
    formatted = formatted.replace(/\n/g, '<br>');
    
    // Envolver en párrafo si no hay ya
    if (!formatted.includes('<p>')) {
        formatted = '<p>' + formatted + '</p>';
    }
    
    return formatted;
}

/**
 * Escapa HTML para prevenir XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Limpia el historial del chat
 */
function limpiarChat() {
    if (confirm('¿Estás seguro de que deseas limpiar el historial del chat?')) {
        const messagesContainer = document.getElementById('chatbot-messages');
        messagesContainer.innerHTML = `
            <div class="chatbot-message bot">
                <div class="message-avatar">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="message-content">
                    <p>Chat limpiado. ¿En qué puedo ayudarte?</p>
                </div>
            </div>
        `;
        chatHistory = [];
    }
}
