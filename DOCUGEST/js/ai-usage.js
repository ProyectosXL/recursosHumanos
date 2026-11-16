/**
 * Monitor de Consumo y Auditoría IA para usuarios con rol Control Total.
 */
(function () {
    let currentPeriod = 'month';
    let initialized = false;

    function esc(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function fmtNum(n) {
        return Number(n || 0).toLocaleString('es-AR');
    }

    function fmtUsd(n) {
        const val = Number(n || 0);
        return 'US$ ' + val.toFixed(4);
    }

    function formatActionBadge(action) {
        const map = {
            chat: { label: 'Consulta IA', cls: 'ai-badge-blue' },
            chat_cache: { label: 'Caché 0 tokens', cls: 'ai-badge-green' },
            ingestion_ocr: { label: 'OCR Multimodal', cls: 'ai-badge-purple' },
            ingestion_tags: { label: 'Tags / Índice IA', cls: 'ai-badge-orange' },
            fallback_local: { label: 'Fallback Local', cls: 'ai-badge-gray' },
        };
        const meta = map[action] || { label: esc(action || 'Evento'), cls: 'ai-badge-gray' };
        return `<span class="ai-badge ${meta.cls}">${meta.label}</span>`;
    }

    async function loadAiUsageStats(period) {
        const container = document.getElementById('ai-usage-content');
        if (!container) return;

        if (period) {
            currentPeriod = period;
        }

        document.querySelectorAll('.ai-period-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.period === currentPeriod);
        });

        const urlBase = (window.DOCUGEST_CONFIG && window.DOCUGEST_CONFIG.usageUrl) || 'api/usage_stats.php';
        const statusEl = document.getElementById('aiUsageRefreshStatus');
        if (statusEl) {
            statusEl.textContent = 'Actualizando métricas...';
        }

        try {
            const resp = await fetch(`${urlBase}?period=${encodeURIComponent(currentPeriod)}&limit=60`, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            });
            const json = await resp.json();
            if (!resp.ok || json.status !== 'success' || !json.data) {
                throw new Error(json.message || 'No se pudieron obtener las métricas');
            }

            renderAiUsageStats(json.data);
            if (statusEl) {
                const now = new Date();
                statusEl.textContent = `Actualizado ${now.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}`;
            }
        } catch (err) {
            if (statusEl) {
                statusEl.textContent = 'Error: ' + err.message;
            }
        }
    }

    function renderAiUsageStats(data) {
        const s = data.summary || {};

        setText('aiKpiRequests', fmtNum(s.total_requests));
        setText('aiKpiRequestsSub', `${fmtNum(s.api_calls)} API · ${fmtNum(s.cache_hits)} Caché`);

        setText('aiKpiTokens', fmtNum(s.total_tokens));
        setText('aiKpiTokensSub', `${fmtNum(s.prompt_tokens)} in · ${fmtNum(s.output_tokens)} out`);

        setText('aiKpiCacheRate', `${Number(s.cache_hit_rate || 0).toFixed(1)}%`);
        setText('aiKpiSavedTokens', `~${fmtNum(s.saved_tokens_est)} tokens ahorrados`);

        setText('aiKpiCost', fmtUsd(s.estimated_cost_usd));
        setText('aiKpiLatency', `${fmtNum(s.avg_elapsed_ms)} ms promedio`);

        renderDailyChart(data.daily_series || []);
        renderUsersTable(data.by_user || []);
        renderModelsTable(data.by_model || [], data.by_key_role || []);
        renderRecentEvents(data.recent_events || []);
    }

    function setText(id, val) {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
    }

    function renderDailyChart(series) {
        const el = document.getElementById('aiUsageDailyChart');
        if (!el) return;
        if (!series.length) {
            el.innerHTML = '<div class="ai-empty">Aún no hay actividad registrada en el período seleccionado.</div>';
            return;
        }

        const maxTokens = Math.max(1, ...series.map(d => Number(d.total_tokens || 0)));
        const maxReqs = Math.max(1, ...series.map(d => Number(d.requests || 0)));

        el.innerHTML = `<div class="ai-bars-wrap">` + series.map(d => {
            const pct = Number(d.total_tokens || 0) > 0
                ? Math.max(8, Math.round((Number(d.total_tokens || 0) / maxTokens) * 100))
                : Math.max(6, Math.round((Number(d.requests || 0) / maxReqs) * 35));
            const shortDate = String(d.date || '').slice(5);
            return `
                <div class="ai-bar-col" title="${esc(d.date)}: ${fmtNum(d.requests)} consultas · ${fmtNum(d.total_tokens)} tokens · ${fmtUsd(d.cost_usd)}">
                    <div class="ai-bar-val">${d.total_tokens > 0 ? fmtNum(d.total_tokens) : fmtNum(d.requests) + 'c'}</div>
                    <div class="ai-bar-track">
                        <div class="ai-bar-fill" style="height:${pct}%"></div>
                    </div>
                    <div class="ai-bar-lbl">${esc(shortDate)}</div>
                </div>
            `;
        }).join('') + `</div>`;
    }

    function renderUsersTable(users) {
        const el = document.getElementById('aiUsageUsersBody');
        if (!el) return;
        if (!users.length) {
            el.innerHTML = '<tr><td colspan="6" class="ai-empty-cell">Sin consultas registradas todavía.</td></tr>';
            return;
        }

        el.innerHTML = users.map(u => `
            <tr>
                <td>
                    <strong>${esc(u.nombre_completo || u.username)}</strong>
                    <div class="ai-subcell">@${esc(u.username)}</div>
                </td>
                <td>${esc(u.sector_nombre || '—')}</td>
                <td class="num">${fmtNum(u.requests)}</td>
                <td class="num">${fmtNum(u.cache_hits)}</td>
                <td class="num"><strong>${fmtNum(u.total_tokens)}</strong></td>
                <td class="num">${fmtUsd(u.cost_usd)}</td>
            </tr>
        `).join('');
    }

    function renderModelsTable(models, keys) {
        const el = document.getElementById('aiUsageModelsBody');
        if (el) {
            if (!models.length) {
                el.innerHTML = '<tr><td colspan="5" class="ai-empty-cell">Sin datos de modelos en este período.</td></tr>';
            } else {
                el.innerHTML = models.map(m => `
                    <tr>
                        <td><code>${esc(m.model)}</code></td>
                        <td class="num">${fmtNum(m.calls)}</td>
                        <td class="num">${fmtNum(m.prompt_tokens)}</td>
                        <td class="num">${fmtNum(m.output_tokens)}</td>
                        <td class="num"><strong>${fmtUsd(m.cost_usd)}</strong></td>
                    </tr>
                `).join('');
            }
        }

        const keyEl = document.getElementById('aiUsageKeysPills');
        if (keyEl) {
            if (!keys.length) {
                keyEl.innerHTML = '<span class="ai-subcell">Sin llamadas externas registradas todavía.</span>';
            } else {
                keyEl.innerHTML = keys.map(k => `
                    <span class="ai-key-pill">
                        <i class="fas fa-key"></i>
                        <strong>${esc(k.key_role)}</strong>: ${fmtNum(k.calls)} llamadas (${fmtNum(k.total_tokens)} tok)
                    </span>
                `).join('');
            }
        }
    }

    function renderRecentEvents(events) {
        const el = document.getElementById('aiUsageRecentBody');
        if (!el) return;
        if (!events.length) {
            el.innerHTML = '<tr><td colspan="7" class="ai-empty-cell">No hay eventos recientes en este período.</td></tr>';
            return;
        }

        el.innerHTML = events.map(ev => {
            const dt = String(ev.ts || '').replace('T', ' ').slice(0, 19);
            return `
                <tr>
                    <td class="nowrap">${esc(dt)}</td>
                    <td>
                        <strong>${esc(ev.nombre_completo || ev.username)}</strong>
                        <div class="ai-subcell">${esc(ev.sector_nombre || '')}</div>
                    </td>
                    <td>${formatActionBadge(ev.action)}</td>
                    <td><code>${esc(ev.model)}</code></td>
                    <td class="num">${fmtNum(ev.total_tokens)}</td>
                    <td class="num nowrap">${fmtNum(ev.elapsed_ms)} ms</td>
                    <td class="ai-preview-cell" title="${esc(ev.preview)}">${esc(ev.preview || '—')}</td>
                </tr>
            `;
        }).join('');
    }

    function hookTabObserver() {
        if (initialized) return;
        initialized = true;

        document.querySelectorAll('.ai-period-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                loadAiUsageStats(btn.dataset.period || 'month');
            });
        });

        const origShowTab = window.showTab;
        if (typeof origShowTab === 'function') {
            window.showTab = function (tabId) {
                origShowTab(tabId);
                if (tabId === 'ai-usage') {
                    loadAiUsageStats();
                }
            };
        }

        const usagePanel = document.getElementById('ai-usage-content');
        if (usagePanel && usagePanel.style.display === 'block') {
            loadAiUsageStats();
        }
    }

    window.loadAiUsageStats = loadAiUsageStats;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hookTabObserver);
    } else {
        hookTabObserver();
    }
})();
