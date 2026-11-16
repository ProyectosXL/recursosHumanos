/**
 * ASISTENTE IA DOCUGEST 2.0 - MOTOR NATIVO PHP + SSE STREAMING
 * Conectado de forma segura a api/chat_stream.php con sesión Hub/Gestionusuarios y Token CSRF.
 * Sin dependencias externas ni tiempos de espera en frío.
 */

let chatbotOpen = false;
let isSendingMessage = false;
let docuGestConversationId = sessionStorage.getItem('docugest_conv_id') || ('conv_' + Date.now());
sessionStorage.setItem('docugest_conv_id', docuGestConversationId);

function getDocuGestConfig() {
    return window.DOCUGEST_CONFIG || {
        csrfToken: '',
        apiUrl: 'api/chat_stream.php',
        reindexUrl: 'api/reindex_documents.php',
        usuario: {}
    };
}

document.addEventListener('DOMContentLoaded', function () {
    initChatbot();
});

function initChatbot() {
    updateChatbotStatus('online', 'En línea · Motor IA Activo');

    const input = document.getElementById('chatbot-input');
    if (input) {
        input.addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendChatbotMessage();
            }
        });
    }
}

function toggleChatbot() {
    const container = document.getElementById('chatbot-container');
    const toggleBtn = document.getElementById('chatbot-toggle-btn');
    const badge = document.getElementById('chatbot-badge');
    if (!container || !toggleBtn) return;

    chatbotOpen = !chatbotOpen;

    if (chatbotOpen) {
        container.classList.add('open');
        toggleBtn.classList.add('hidden');
        if (badge) badge.style.display = 'none';
        updateChatbotStatus('online', 'En línea · Motor IA Activo');
        setTimeout(() => {
            const inp = document.getElementById('chatbot-input');
            if (inp) inp.focus();
        }, 200);
    } else {
        container.classList.remove('open');
        toggleBtn.classList.remove('hidden');
    }
}

function updateChatbotStatus(status, text) {
    const icon = document.getElementById('chatbot-status-icon');
    const textEl = document.getElementById('chatbot-status-text');
    if (icon) icon.className = 'status-dot ' + status;
    if (textEl) textEl.textContent = text;
}

async function resetChatbotConversation() {
    const cfg = getDocuGestConfig();
    try {
        await fetch(cfg.apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': cfg.csrfToken || ''
            },
            body: JSON.stringify({
                action: 'clear',
                conversation_id: docuGestConversationId
            })
        });
    } catch (e) {
        console.warn('No se pudo limpiar la conversación en servidor:', e);
    }

    docuGestConversationId = 'conv_' + Date.now();
    sessionStorage.setItem('docugest_conv_id', docuGestConversationId);

    const messagesContainer = document.getElementById('chatbot-messages');
    if (messagesContainer) {
        messagesContainer.innerHTML = `
            <div class="chatbot-message bot">
                <div class="message-avatar"><i class="fas fa-headset"></i></div>
                <div class="message-content">
                    <p>👋 ¡Hola! Iniciamos una nueva conversación.</p>
                    <p>Consultame sobre cualquier política, procedimiento, instructivo o término del glosario.</p>
                </div>
            </div>
        `;
    }
}

async function sendChatbotMessage() {
    if (isSendingMessage) return;

    const input = document.getElementById('chatbot-input');
    const question = input ? input.value.trim() : '';
    if (!question) return;

    const cfg = getDocuGestConfig();
    isSendingMessage = true;

    const sendBtn = document.getElementById('chatbot-send-btn');
    if (sendBtn) sendBtn.disabled = true;
    if (input) {
        input.disabled = true;
        input.value = '';
        input.style.height = 'auto';
    }

    addChatbotMessage(question, 'user');
    const typingId = showTypingIndicator('Analizando políticas y procedimientos...');
    updateChatbotStatus('loading', 'Razonando sobre documentos...');

    try {
        const response = await fetch(cfg.apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'text/event-stream',
                'X-CSRF-Token': cfg.csrfToken || ''
            },
            body: JSON.stringify({
                action: 'query',
                question: question,
                conversation_id: docuGestConversationId,
                stream: true
            })
        });

        if (response.status === 401 || response.status === 403) {
            removeTypingIndicator(typingId);
            const errJson = await response.json().catch(() => ({}));
            addChatbotMessage(
                '⚠️ ' + (errJson.message || 'Tu sesión expiró o falta validación de seguridad. Recargá la página.'),
                'bot'
            );
            updateChatbotStatus('offline', 'Sesión expirada');
            return;
        }

        const contentType = response.headers.get('Content-Type') || '';
        let finalPayload = null;

        if (contentType.includes('text/event-stream') && response.body) {
            const reader = response.body.getReader();
            const decoder = new TextDecoder('utf-8');
            let buffer = '';

            while (true) {
                const { value, done } = await reader.read();
                if (done) break;
                buffer += decoder.decode(value, { stream: true });

                const frames = buffer.split('\n\n');
                buffer = frames.pop() || '';

                for (const frame of frames) {
                    const lines = frame.split('\n');
                    let eventName = 'message';
                    let dataLine = '';

                    for (const line of lines) {
                        if (line.startsWith('event:')) {
                            eventName = line.slice(6).trim();
                        } else if (line.startsWith('data:')) {
                            dataLine += line.slice(5).trim();
                        }
                    }

                    if (!dataLine) continue;
                    let parsed = null;
                    try {
                        parsed = JSON.parse(dataLine);
                    } catch (e) {
                        continue;
                    }

                    if (eventName === 'thought' && parsed.message) {
                        updateTypingIndicatorText(typingId, parsed.message);
                    } else if (eventName === 'tool_call' && parsed.tool) {
                        updateTypingIndicatorText(typingId, 'Consultando: ' + formatToolLabel(parsed.tool) + '...');
                    } else if (eventName === 'done') {
                        finalPayload = parsed;
                    } else if (eventName === 'error') {
                        throw new Error(parsed.message || 'Error en el servidor IA');
                    }
                }
            }
        } else {
            finalPayload = await response.json();
        }

        removeTypingIndicator(typingId);

        if (finalPayload && finalPayload.status === 'success' && finalPayload.answer) {
            addChatbotMessage(
                finalPayload.answer,
                'bot',
                finalPayload.sources || [],
                {
                    model: finalPayload.model || '',
                    cached: !!finalPayload.cached,
                    elapsedMs: finalPayload.elapsed_ms || 0
                }
            );
            updateChatbotStatus('online', 'En línea · Motor IA Activo');
        } else {
            addChatbotMessage(
                '❌ ' + ((finalPayload && finalPayload.message) || 'No se recibió una respuesta válida del servidor.'),
                'bot'
            );
            updateChatbotStatus('online', 'En línea · Motor IA Activo');
        }
    } catch (err) {
        console.error('Error en chat DocuGest 2.0:', err);
        removeTypingIndicator(typingId);
        addChatbotMessage(
            '❌ Ocurrió un error al consultar el asistente (' + (err.message || 'error de red') + '). Por favor intentá nuevamente.',
            'bot'
        );
        updateChatbotStatus('online', 'En línea · Motor IA Activo');
    } finally {
        isSendingMessage = false;
        if (sendBtn) sendBtn.disabled = false;
        if (input) {
            input.disabled = false;
            input.focus();
        }
    }
}

function formatToolLabel(toolName) {
    const map = {
        buscar_documentos_y_secciones: 'índice de documentos',
        leer_documento_completo_o_seccion: 'secciones del documento',
        consultar_glosario_corporativo: 'glosario corporativo',
        obtener_historial_y_metadatos_documento: 'historial de versiones'
    };
    return map[toolName] || toolName;
}

function addChatbotMessage(text, sender, sources = [], meta = null) {
    const messagesContainer = document.getElementById('chatbot-messages');
    if (!messagesContainer) return;

    const messageDiv = document.createElement('div');
    messageDiv.className = 'chatbot-message ' + sender;

    const avatar = document.createElement('div');
    avatar.className = 'message-avatar';
    avatar.innerHTML = sender === 'user'
        ? '<i class="fas fa-user"></i>'
        : '<i class="fas fa-headset"></i>';

    const content = document.createElement('div');
    content.className = 'message-content';

    const textDiv = document.createElement('div');
    textDiv.className = 'message-text';
    textDiv.innerHTML = sender === 'bot'
        ? formatDocuGestMarkdown(text)
        : '<p>' + escapeHtml(text).replace(/\n/g, '<br>') + '</p>';
    content.appendChild(textDiv);

    if (sender === 'bot' && meta && (meta.elapsedMs || meta.cached)) {
        const metaBar = document.createElement('div');
        metaBar.className = 'chatbot-meta-bar';
        const parts = [];
        if (meta.cached) {
            parts.push('<span><i class="fas fa-bolt"></i> Caché instantánea</span>');
        }
        if (meta.elapsedMs) {
            parts.push('<span><i class="fas fa-clock"></i> ' + (meta.elapsedMs / 1000).toFixed(2) + 's</span>');
        }
        if (meta.model && !meta.cached) {
            parts.push('<span><i class="fas fa-microchip"></i> ' + escapeHtml(meta.model) + '</span>');
        }
        metaBar.innerHTML = parts.join('');
        content.appendChild(metaBar);
    }

    if (sender === 'bot' && Array.isArray(sources) && sources.length > 0) {
        const sourcesDiv = document.createElement('div');
        sourcesDiv.className = 'message-sources';
        sourcesDiv.innerHTML = '<div class="sources-header"><i class="fas fa-book-open"></i> Documentos fuente:</div>';

        sources.forEach(src => {
            const docId = parseInt(src.id, 10) || 0;
            const srcItem = document.createElement('div');
            srcItem.className = 'source-item';

            const infoHtml = `
                <div class="source-info">
                    <i class="fas fa-file-pdf"></i>
                    <span class="source-title">${escapeHtml(src.titulo || 'Documento')}</span>
                    <span class="source-sector">${escapeHtml(src.sector_nombre || '')}${src.version ? ' · v' + escapeHtml(src.version) : ''}</span>
                </div>
                <div class="source-actions">
                    <button class="source-view-btn" onclick="viewDocument(${docId})" title="Ver PDF">
                        <i class="fas fa-eye"></i>
                    </button>
                    <a class="source-download-btn" href="Controller/descargar_documento.php?id=${docId}" title="Descargar PDF">
                        <i class="fas fa-download"></i>
                    </a>
                </div>
            `;
            srcItem.innerHTML = infoHtml;
            sourcesDiv.appendChild(srcItem);
        });

        content.appendChild(sourcesDiv);
    }

    messageDiv.appendChild(avatar);
    messageDiv.appendChild(content);
    messagesContainer.appendChild(messageDiv);
    scrollChatToBottom();
}

/**
 * Formateador Markdown seguro con soporte de encabezados, listas, negritas, código y tablas Markdown.
 */
function formatDocuGestMarkdown(raw) {
    const text = String(raw || '').replace(/\r\n/g, '\n');
    const lines = text.split('\n');
    let html = '';
    let i = 0;

    const inlineFormat = (line) => {
        let s = escapeHtml(line);
        s = s.replace(/`([^`]+)`/g, '<code>$1</code>');
        s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        s = s.replace(/\*([^*]+)\*/g, '<em>$1</em>');
        return s;
    };

    while (i < lines.length) {
        const line = lines[i];
        const trimmed = line.trim();

        // Tablas Markdown
        if (trimmed.startsWith('|') && i + 1 < lines.length && /^\|[\s:|-]+\|$/.test(lines[i + 1].trim())) {
            const headers = trimmed.split('|').slice(1, -1).map(c => c.trim());
            html += '<table class="dg-md-table"><thead><tr>' +
                headers.map(h => '<th>' + inlineFormat(h) + '</th>').join('') +
                '</tr></thead><tbody>';
            i += 2;
            while (i < lines.length && lines[i].trim().startsWith('|')) {
                const cols = lines[i].trim().split('|').slice(1, -1).map(c => c.trim());
                html += '<tr>' + cols.map(c => '<td>' + inlineFormat(c) + '</td>').join('') + '</tr>';
                i++;
            }
            html += '</tbody></table>';
            continue;
        }

        // Encabezados
        const headingMatch = trimmed.match(/^(#{1,4})\s+(.+)$/);
        if (headingMatch) {
            html += '<h4 class="dg-md-heading">' + inlineFormat(headingMatch[2]) + '</h4>';
            i++;
            continue;
        }

        // Lista con viñetas
        if (/^[-*•]\s+/.test(trimmed)) {
            html += '<ul>';
            while (i < lines.length && /^[-*•]\s+/.test(lines[i].trim())) {
                html += '<li>' + inlineFormat(lines[i].trim().replace(/^[-*•]\s+/, '')) + '</li>';
                i++;
            }
            html += '</ul>';
            continue;
        }

        // Lista numerada
        if (/^\d+[\.\)]\s+/.test(trimmed)) {
            html += '<ol>';
            while (i < lines.length && /^\d+[\.\)]\s+/.test(lines[i].trim())) {
                html += '<li>' + inlineFormat(lines[i].trim().replace(/^\d+[\.\)]\s+/, '')) + '</li>';
                i++;
            }
            html += '</ol>';
            continue;
        }

        if (trimmed === '') {
            i++;
            continue;
        }

        // Párrafo
        const paraLines = [];
        while (
            i < lines.length &&
            lines[i].trim() !== '' &&
            !/^#{1,4}\s+/.test(lines[i].trim()) &&
            !/^[-*•]\s+/.test(lines[i].trim()) &&
            !/^\d+[\.\)]\s+/.test(lines[i].trim()) &&
            !lines[i].trim().startsWith('|')
        ) {
            paraLines.push(inlineFormat(lines[i].trim()));
            i++;
        }
        html += '<p>' + paraLines.join('<br>') + '</p>';
    }

    return html || '<p>' + inlineFormat(text) + '</p>';
}

function showTypingIndicator(initialStatus) {
    const messagesContainer = document.getElementById('chatbot-messages');
    const id = 'typing-' + Date.now();
    if (!messagesContainer) return id;

    const typingDiv = document.createElement('div');
    typingDiv.className = 'chatbot-message bot typing';
    typingDiv.id = id;
    typingDiv.innerHTML = `
        <div class="message-avatar"><i class="fas fa-headset"></i></div>
        <div class="message-content">
            <div class="typing-indicator"><span></span><span></span><span></span></div>
            <div class="typing-status-text" style="font-size:11.5px; color:#64748b; margin-top:6px;">${escapeHtml(initialStatus || 'Procesando...')}</div>
        </div>
    `;
    messagesContainer.appendChild(typingDiv);
    scrollChatToBottom();
    return id;
}

function updateTypingIndicatorText(id, text) {
    const el = document.getElementById(id);
    if (!el) return;
    const statusEl = el.querySelector('.typing-status-text');
    if (statusEl) {
        statusEl.textContent = text;
    }
}

function removeTypingIndicator(id) {
    const el = document.getElementById(id);
    if (el) el.remove();
}

function scrollChatToBottom() {
    const messagesContainer = document.getElementById('chatbot-messages');
    if (messagesContainer) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = String(text ?? '');
    return div.innerHTML;
}

/**
 * Recompila el índice documental desde la pestaña Configuración (solo Editar/Admin).
 */
async function recompilarIndiceDocuGest(enrichAi) {
    const cfg = getDocuGestConfig();
    const btnQuick = document.getElementById('btnReindexQuick');
    const btnAi = document.getElementById('btnReindexAi');
    if (btnQuick) btnQuick.disabled = true;
    if (btnAi) btnAi.disabled = true;

    if (typeof showNotification === 'function') {
        showNotification(enrichAi
            ? 'Re-indexando documentos y enriqueciendo tags/glosario con IA...'
            : 'Re-compilando índice local de documentos...', 'info');
    }

    try {
        const res = await fetch(cfg.reindexUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': cfg.csrfToken || ''
            },
            body: JSON.stringify({ enrich_ai: !!enrichAi })
        });
        const data = await res.json();
        if (data.status === 'success') {
            const setEl = (id, val) => { const e = document.getElementById(id); if (e) e.textContent = val; };
            setEl('idxDocsCount', data.document_count ?? 0);
            setEl('idxSectionsCount', data.section_count ?? 0);
            setEl('idxGlossaryCount', data.glossary_count ?? 0);
            setEl('idxCompiledAt', new Date().toLocaleString('es-AR'));
            if (typeof showNotification === 'function') {
                showNotification(
                    `Índice compilado: ${data.document_count} documentos, ${data.section_count} secciones (${data.elapsed_ms} ms).`,
                    'success'
                );
            }
        } else {
            throw new Error(data.message || 'Error al compilar índice');
        }
    } catch (e) {
        if (typeof showNotification === 'function') {
            showNotification('Error al re-indexar: ' + e.message, 'error');
        }
    } finally {
        if (btnQuick) btnQuick.disabled = false;
        if (btnAi) btnAi.disabled = false;
    }
}
