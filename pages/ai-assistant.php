<?php
/**
 * Pagina: AI Assistant
 */
define('APP_INIT', true);
require_once __DIR__ . '/../php/bootstrap.php';
Auth::require();
if (!Auth::can('ai.use')) {
    header('Location: ' . APP_URL . '/pages/dashboard.php');
    exit;
}

$pageTitle  = 'AI Assistant';
$activeMenu = 'ai';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/sidebar.php';
?>

<style>
.ai-chat-wrapper {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 160px);
    min-height: 500px;
}
.ai-chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: .5rem;
    scroll-behavior: smooth;
}
.ai-bubble {
    max-width: 85%;
    padding: .75rem 1rem;
    border-radius: 1rem;
    margin-bottom: .75rem;
    line-height: 1.6;
}
.ai-bubble.user {
    background: var(--bs-primary);
    color: #fff;
    margin-left: auto;
    border-bottom-right-radius: .25rem;
}
.ai-bubble.assistant {
    background: #fff;
    border: 1px solid #e9ecef;
    border-bottom-left-radius: .25rem;
    box-shadow: 0 1px 3px rgba(0,0,0,.08);
}
.ai-bubble.assistant h5,
.ai-bubble.assistant h6 {
    font-size: .9rem;
    font-weight: 700;
    margin-top: .75rem;
}
.ai-bubble.assistant ul,
.ai-bubble.assistant ol {
    padding-left: 1.25rem;
    margin: .5rem 0;
}
.ai-bubble.assistant code {
    background: #f1f3f5;
    border-radius: .25rem;
    padding: .1rem .3rem;
    font-size: .85em;
}
.ai-bubble.assistant table {
    font-size: .85rem;
    width: 100%;
    border-collapse: collapse;
    margin: .5rem 0;
}
.ai-bubble.assistant table th,
.ai-bubble.assistant table td {
    border: 1px solid #dee2e6;
    padding: .3rem .5rem;
}
.ai-bubble.assistant table th {
    background: #f8f9fa;
}
.ai-typing-indicator span {
    display: inline-block;
    width: 8px; height: 8px;
    background: #adb5bd;
    border-radius: 50%;
    margin: 0 2px;
    animation: typing 1.2s infinite;
}
.ai-typing-indicator span:nth-child(2) { animation-delay: .2s; }
.ai-typing-indicator span:nth-child(3) { animation-delay: .4s; }
@keyframes typing { 0%,60%,100% { transform: translateY(0); } 30% { transform: translateY(-6px); } }
.ai-suggestion-chip {
    cursor: pointer;
    transition: all .15s;
}
.ai-suggestion-chip:hover {
    background: var(--bs-primary) !important;
    color: #fff !important;
    border-color: var(--bs-primary) !important;
}
.ai-context-card {
    border-left: 4px solid var(--bs-primary);
}
</style>

<div class="container-fluid px-4 py-3">

  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h1 class="h4 mb-1 fw-bold">
        <i class="bi bi-robot me-2 text-primary"></i>AI Assistant
      </h1>
      <p class="text-muted small mb-0">
        Analisi intelligente delle commesse · Powered by Claude (<code>claude-sonnet-4-6</code>)
      </p>
    </div>
    <div class="d-flex gap-2 align-items-center">
      <select class="form-select form-select-sm" id="selectCommessa" style="max-width:280px">
        <option value="">— Nessuna commessa (analisi globale) —</option>
      </select>
      <button class="btn btn-outline-secondary btn-sm" id="btnClearChat">
        <i class="bi bi-trash me-1"></i>Pulisci
      </button>
    </div>
  </div>

  <div class="row g-3">
    <!-- Chat principale -->
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body p-0 d-flex flex-column">
          <div class="ai-chat-messages" id="chatMessages">
            <!-- Messaggio benvenuto -->
            <div class="ai-bubble assistant">
              <div class="d-flex align-items-start gap-2">
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:32px;height:32px;font-size:.8rem">
                  <i class="bi bi-robot"></i>
                </div>
                <div>
                  <p class="mb-2">Ciao! Sono il tuo assistente AI per la gestione delle commesse pubbliche. Posso aiutarti con:</p>
                  <ul class="mb-2">
                    <li><strong>Analisi di avanzamento</strong>: stato dei lavori, task in ritardo, KPI</li>
                    <li><strong>Rischi di progetto</strong>: identificazione e valutazione dei rischi</li>
                    <li><strong>SAL e contabilità</strong>: andamento finanziario, SPI, CPI</li>
                    <li><strong>Report narrativi</strong>: sintesi per RUP, DL e stazione appaltante</li>
                    <li><strong>Previsioni</strong>: stima date di completamento, fabbisogni finanziari</li>
                  </ul>
                  <p class="mb-0">Seleziona una commessa dal menu in alto (o lascia vuoto per analisi globale) e fai una domanda.</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Input -->
          <div class="border-top p-3">
            <!-- Suggestion chips -->
            <div class="d-flex flex-wrap gap-2 mb-2" id="suggestionChips">
              <span class="badge border border-primary text-primary ai-suggestion-chip py-2 px-3"
                    onclick="sendSuggestion('Analizza lo stato di avanzamento della commessa selezionata')">
                <i class="bi bi-graph-up me-1"></i>Analisi avanzamento
              </span>
              <span class="badge border border-warning text-warning ai-suggestion-chip py-2 px-3"
                    onclick="sendSuggestion('Identifica i principali rischi del progetto e proponi azioni correttive')">
                <i class="bi bi-exclamation-triangle me-1"></i>Analisi rischi
              </span>
              <span class="badge border border-success text-success ai-suggestion-chip py-2 px-3"
                    onclick="sendSuggestion('Genera un report narrativo completo per la stazione appaltante')">
                <i class="bi bi-file-text me-1"></i>Report narrativo
              </span>
              <span class="badge border border-info text-info ai-suggestion-chip py-2 px-3"
                    onclick="sendSuggestion('Analizza i SPI e CPI e prevedi la data di completamento')">
                <i class="bi bi-calendar-check me-1"></i>Previsione completamento
              </span>
              <span class="badge border border-secondary text-secondary ai-suggestion-chip py-2 px-3"
                    onclick="sendSuggestion('Quali sono le scadenze più urgenti e come gestirle?')">
                <i class="bi bi-alarm me-1"></i>Scadenze urgenti
              </span>
            </div>
            <div class="input-group">
              <textarea class="form-control" id="chatInput" rows="2"
                        placeholder="Scrivi una domanda o seleziona un'analisi suggerita..."
                        style="resize:none"></textarea>
              <button class="btn btn-primary" id="btnSend">
                <i class="bi bi-send-fill"></i>
              </button>
            </div>
            <small class="text-muted mt-1 d-block">
              <i class="bi bi-info-circle me-1"></i>
              L'AI analizza i dati reali della commessa selezionata. Le risposte sono indicative e non sostituiscono il giudizio professionale.
            </small>
          </div>
        </div>
      </div>
    </div>

    <!-- Pannello contestuale -->
    <div class="col-lg-4">
      <!-- Dati commessa corrente -->
      <div class="card border-0 shadow-sm mb-3 ai-context-card d-none" id="contextCard">
        <div class="card-header bg-white fw-semibold pb-0 border-0">
          <i class="bi bi-briefcase me-2 text-primary"></i>Contesto Corrente
        </div>
        <div class="card-body small" id="contextBody"></div>
      </div>

      <!-- Analisi rapide -->
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold border-0 pb-0">
          <i class="bi bi-lightning-charge me-2 text-warning"></i>Analisi Rapide
        </div>
        <div class="card-body">
          <div class="d-grid gap-2">
            <button class="btn btn-outline-primary btn-sm text-start" id="btnAnalisiCompleta">
              <i class="bi bi-clipboard-data me-2"></i>Analisi Completa Commessa
            </button>
            <button class="btn btn-outline-warning btn-sm text-start" id="btnAnalisiRischi">
              <i class="bi bi-shield-exclamation me-2"></i>Valutazione Rischi
            </button>
            <button class="btn btn-outline-success btn-sm text-start" id="btnGeneraReport">
              <i class="bi bi-file-earmark-text me-2"></i>Genera Report Formale
            </button>
            <button class="btn btn-outline-info btn-sm text-start" id="btnPrevisione">
              <i class="bi bi-graph-up-arrow me-2"></i>Previsione Evoluzione
            </button>
          </div>
        </div>
      </div>

      <!-- Storico chat -->
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold border-0 pb-0">
          <i class="bi bi-clock-history me-2 text-muted"></i>Sessione corrente
        </div>
        <div class="card-body">
          <p class="text-muted small mb-1">Domande inviate: <strong id="chatCount">0</strong></p>
          <p class="text-muted small mb-0">La sessione non viene salvata. Copia il testo che vuoi conservare.</p>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
$inlineScript = <<<'JS'
let chatCount   = 0;
let isTyping    = false;
let _commessaId = null;

document.addEventListener('DOMContentLoaded', () => {
    loadCommesse();
    document.getElementById('btnSend').addEventListener('click', sendMessage);
    document.getElementById('chatInput').addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    });
    document.getElementById('btnClearChat').addEventListener('click', clearChat);
    document.getElementById('selectCommessa').addEventListener('change', function() {
        _commessaId = this.value ? parseInt(this.value) : null;
        updateContextCard();
    });

    // Analisi rapide
    document.getElementById('btnAnalisiCompleta').addEventListener('click', () =>
        sendAnalysis('completa', 'Analisi completa commessa richiesta...'));
    document.getElementById('btnAnalisiRischi').addEventListener('click', () =>
        sendAnalysis('rischi', 'Valutazione rischi richiesta...'));
    document.getElementById('btnGeneraReport').addEventListener('click', () =>
        sendAnalysis('report', 'Generazione report formale...'));
    document.getElementById('btnPrevisione').addEventListener('click', () =>
        sendAnalysis('previsione', 'Calcolo previsione evoluzione...'));
});

async function loadCommesse() {
    const res = await API.get('/api/commesse.php?per_page=200&sort_by=codice&stato=IN_ESECUZIONE');
    const sel = document.getElementById('selectCommessa');
    (res.data || []).forEach(c => {
        sel.insertAdjacentHTML('beforeend',
            `<option value="${c.id}">${escapeHtml(c.codice_commessa)} — ${escapeHtml(c.oggetto.substring(0,50))}</option>`);
    });
}

async function updateContextCard() {
    const card = document.getElementById('contextCard');
    if (!_commessaId) {
        card.classList.add('d-none');
        return;
    }
    card.classList.remove('d-none');
    document.getElementById('contextBody').innerHTML =
        '<div class="text-center py-2"><div class="spinner-border spinner-border-sm"></div></div>';
    try {
        const res = await API.get('/api/commesse.php?id=' + _commessaId);
        const c = res.data;
        const avanz = parseFloat(c.percentuale_avanzamento || 0);
        const barCls = avanz >= 80 ? 'bg-success' : avanz >= 40 ? 'bg-primary' : 'bg-warning';
        document.getElementById('contextBody').innerHTML = `
            <div class="fw-semibold mb-1">${escapeHtml(c.oggetto ?? '')}</div>
            <div class="mb-2">
                <div class="d-flex justify-content-between small text-muted">
                    <span>Avanzamento</span><span>${avanz.toFixed(1)}%</span>
                </div>
                <div class="progress" style="height:6px">
                    <div class="progress-bar ${barCls}" style="width:${avanz}%"></div>
                </div>
            </div>
            <dl class="row small mb-0">
                <dt class="col-6 text-muted">Importo</dt>
                <dd class="col-6">${Format.euro(c.importo_contrattuale)}</dd>
                <dt class="col-6 text-muted">Stato</dt>
                <dd class="col-6">${Format.badgeStato(c.stato)}</dd>
                <dt class="col-6 text-muted">Scostamento</dt>
                <dd class="col-6 ${parseInt(c.scostamento_giorni)>0?'text-danger':'text-success'}">${parseInt(c.scostamento_giorni)||0} gg</dd>
                <dt class="col-6 text-muted">RUP</dt>
                <dd class="col-6">${escapeHtml(c.rup_nominativo??'—')}</dd>
            </dl>`;
    } catch(e) {
        document.getElementById('contextBody').innerHTML = '<p class="text-danger small mb-0">' + escapeHtml(e.message) + '</p>';
    }
}

async function sendMessage() {
    const input = document.getElementById('chatInput');
    const text  = input.value.trim();
    if (!text || isTyping) return;

    input.value = '';
    appendBubble(text, 'user');
    await callAI('domanda', text);
}

function sendSuggestion(text) {
    document.getElementById('chatInput').value = text;
    sendMessage();
}

async function sendAnalysis(tipo, displayText) {
    if (!_commessaId && tipo !== 'report') {
        UI.warning('Seleziona una commessa per utilizzare le analisi rapide');
        return;
    }
    appendBubble(displayText, 'user');
    await callAI(tipo, displayText);
}

async function callAI(tipo, userText) {
    if (isTyping) return;
    isTyping = true;
    chatCount++;
    document.getElementById('chatCount').textContent = chatCount;

    const typingEl = appendTypingIndicator();

    try {
        const payload = {
            tipo,
            domanda: userText,
            ...(  _commessaId && { commessa_id: _commessaId }),
        };
        const res = await API.post('/api/ai_assistant.php', payload);
        typingEl.remove();

        const analisi = res.analisi || res;
        renderAIResponse(analisi);
    } catch(e) {
        typingEl.remove();
        appendBubble(`⚠️ Errore: ${e.message}`, 'assistant');
    } finally {
        isTyping = false;
    }
}

function renderAIResponse(analisi) {
    const livello  = analisi.livello ?? 'INFO';
    const colMap   = { VERDE:'success', GIALLO:'warning', ARANCIO:'warning', ROSSO:'danger', INFO:'primary' };
    const iconMap  = { VERDE:'check-circle-fill', GIALLO:'exclamation-circle-fill',
                       ARANCIO:'exclamation-triangle-fill', ROSSO:'x-circle-fill', INFO:'robot' };
    const cls  = colMap[livello] ?? 'secondary';
    const icon = iconMap[livello] ?? 'robot';

    let html = `<div class="d-flex align-items-start gap-2">
        <div class="rounded-circle bg-${cls} text-white d-flex align-items-center justify-content-center flex-shrink-0"
             style="width:32px;height:32px;font-size:.8rem">
          <i class="bi bi-${icon}"></i>
        </div>
        <div class="flex-grow-1">`;

    // Badge livello
    if (livello !== 'INFO') {
        html += `<span class="badge bg-${cls} mb-2">${livello}</span> `;
    }

    // Testo principale (narrativo)
    if (analisi.narrativo) {
        html += formatMarkdown(analisi.narrativo);
    } else if (analisi.risposta) {
        html += formatMarkdown(analisi.risposta);
    } else if (analisi.sintesi) {
        html += `<p>${escapeHtml(analisi.sintesi)}</p>`;
    }

    // KPI Section
    if (analisi.kpi) {
        html += `<div class="row g-2 my-2">`;
        if (analisi.kpi.spi !== undefined) html += kpiCard('SPI', analisi.kpi.spi, analisi.kpi.spi >= 1 ? 'success' : 'warning');
        if (analisi.kpi.cpi !== undefined) html += kpiCard('CPI', analisi.kpi.cpi, analisi.kpi.cpi >= 1 ? 'success' : 'warning');
        if (analisi.kpi.avanzamento !== undefined) html += kpiCard('Avanzamento', analisi.kpi.avanzamento + '%', 'info');
        if (analisi.kpi.scostamento !== undefined) {
            const sc = parseInt(analisi.kpi.scostamento);
            html += kpiCard('Scostamento', sc + ' gg', sc > 0 ? 'danger' : 'success');
        }
        html += `</div>`;
    }

    // Rischi
    if (analisi.rischi?.length) {
        html += `<div class="mt-2"><strong>⚠️ Rischi identificati:</strong><ul class="mt-1">`;
        analisi.rischi.forEach(r => {
            html += `<li>${typeof r === 'string' ? escapeHtml(r) : escapeHtml(r.descrizione ?? String(r))}</li>`;
        });
        html += `</ul></div>`;
    }

    // Azioni correttive
    if (analisi.azioni_correttive?.length) {
        html += `<div class="mt-2"><strong>✅ Azioni raccomandate:</strong><ul class="mt-1">`;
        analisi.azioni_correttive.forEach(a => {
            html += `<li>${typeof a === 'string' ? escapeHtml(a) : escapeHtml(a.azione ?? String(a))}</li>`;
        });
        html += `</ul></div>`;
    }

    // Previsioni
    if (analisi.previsione) {
        html += `<div class="alert alert-info mt-2 mb-0 py-2 small">
            <i class="bi bi-graph-up-arrow me-1"></i>
            <strong>Previsione:</strong> ${escapeHtml(typeof analisi.previsione === 'string' ? analisi.previsione : JSON.stringify(analisi.previsione))}
        </div>`;
    }

    html += `</div></div>`;
    appendBubble(html, 'assistant', true);
    scrollBottom();
}

function kpiCard(label, value, cls) {
    return `<div class="col-auto">
        <div class="border rounded text-center px-3 py-1">
            <div class="fw-bold text-${cls}">${value}</div>
            <div class="text-muted" style="font-size:.7rem">${label}</div>
        </div>
    </div>`;
}

function formatMarkdown(text) {
    // Very basic markdown → HTML
    return '<div>' + text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        .replace(/`(.+?)`/g, '<code>$1</code>')
        .replace(/^### (.+)$/gm, '<h6>$1</h6>')
        .replace(/^## (.+)$/gm, '<h5>$1</h5>')
        .replace(/^# (.+)$/gm, '<h5>$1</h5>')
        .replace(/^\- (.+)$/gm, '<li>$1</li>')
        .replace(/(<li>.*<\/li>\n?)+/g, m => `<ul>${m}</ul>`)
        .replace(/^\d+\. (.+)$/gm, '<li>$1</li>')
        .replace(/\n{2,}/g, '</p><p>')
        .replace(/\n/g, '<br>')
        + '</div>';
}

function appendBubble(content, role, isHtml = false) {
    const wrap = document.getElementById('chatMessages');
    const div  = document.createElement('div');
    div.className = 'ai-bubble ' + role;
    if (isHtml) div.innerHTML = content;
    else div.textContent = content;
    wrap.appendChild(div);
    scrollBottom();
}

function appendTypingIndicator() {
    const wrap = document.getElementById('chatMessages');
    const div  = document.createElement('div');
    div.className = 'ai-bubble assistant';
    div.innerHTML  = '<div class="ai-typing-indicator"><span></span><span></span><span></span></div>';
    wrap.appendChild(div);
    scrollBottom();
    return div;
}

function scrollBottom() {
    const wrap = document.getElementById('chatMessages');
    wrap.scrollTop = wrap.scrollHeight;
}

function clearChat() {
    const wrap = document.getElementById('chatMessages');
    wrap.innerHTML = '<div class="text-center text-muted py-4 small">Chat pulita. Inizia una nuova conversazione.</div>';
    chatCount = 0;
    document.getElementById('chatCount').textContent = 0;
}
JS;
include __DIR__ . '/../components/footer.php';
