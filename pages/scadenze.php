<?php
/**
 * Pagina: Scadenzario
 */
define('APP_INIT', true);
require_once __DIR__ . '/../php/bootstrap.php';
Auth::require();
if (!Auth::can('pm_scadenze.read')) {
    header('Location: ' . APP_URL . '/pages/dashboard.php');
    exit;
}

$pageTitle  = 'Scadenzario';
$activeMenu = 'pm_scadenze';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/sidebar.php';
?>

<div class="container-fluid px-4 py-3">

  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h1 class="h4 mb-1 fw-bold">Scadenzario</h1>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
          <li class="breadcrumb-item"><a href="<?= APP_URL ?>/pages/dashboard.php">Dashboard</a></li>
          <li class="breadcrumb-item active">Scadenzario</li>
        </ol>
      </nav>
    </div>
    <?php if (Auth::can('pm_scadenze.create')): ?>
    <button class="btn btn-primary" id="btnNuovaScadenza">
      <i class="bi bi-calendar-plus me-2"></i>Nuova Scadenza
    </button>
    <?php endif; ?>
  </div>

  <!-- KPI urgency cards -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm border-start border-4 border-danger">
        <div class="card-body d-flex align-items-center gap-3">
          <i class="bi bi-alarm-fill text-danger fs-3"></i>
          <div>
            <small class="text-muted">Scadute</small>
            <h4 class="mb-0 fw-bold text-danger" id="kpiScadute">—</h4>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm border-start border-4 border-warning">
        <div class="card-body d-flex align-items-center gap-3">
          <i class="bi bi-exclamation-triangle-fill text-warning fs-3"></i>
          <div>
            <small class="text-muted">Critiche (≤7gg)</small>
            <h4 class="mb-0 fw-bold text-warning" id="kpiCritiche">—</h4>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm border-start border-4 border-info">
        <div class="card-body d-flex align-items-center gap-3">
          <i class="bi bi-calendar-check text-info fs-3"></i>
          <div>
            <small class="text-muted">Urgenti (≤15gg)</small>
            <h4 class="mb-0 fw-bold text-info" id="kpiUrgenti">—</h4>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm border-start border-4 border-success">
        <div class="card-body d-flex align-items-center gap-3">
          <i class="bi bi-calendar2-check text-success fs-3"></i>
          <div>
            <small class="text-muted">Totale Attive</small>
            <h4 class="mb-0 fw-bold text-success" id="kpiTotale">—</h4>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filtri -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
      <div class="row g-2 align-items-center">
        <div class="col-12 col-md-4">
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
            <input type="search" class="form-control border-start-0" id="searchInput"
                   placeholder="Cerca descrizione, commessa...">
          </div>
        </div>
        <div class="col-6 col-md-2">
          <select class="form-select form-select-sm" id="filterUrgenza">
            <option value="">Tutte le urgenze</option>
            <option value="scaduta">Scadute</option>
            <option value="critica">Critiche (≤7gg)</option>
            <option value="urgente">Urgenti (≤15gg)</option>
            <option value="normale">Normali</option>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <select class="form-select form-select-sm" id="filterTipo">
            <option value="">Tutti i tipi</option>
            <option value="CONTRATTUALE">Contrattuale</option>
            <option value="NORMATIVA">Normativa</option>
            <option value="DOCUMENTALE">Documentale</option>
            <option value="PAGAMENTO">Pagamento</option>
            <option value="COLLAUDO">Collaudo</option>
            <option value="ALTRO">Altro</option>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <select class="form-select form-select-sm" id="filterCommessa">
            <option value="">Tutte le pm_commesse</option>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <select class="form-select form-select-sm" id="filterStato">
            <option value="ATTIVA">Solo attive</option>
            <option value="">Tutte</option>
            <option value="COMPLETATA">Completate</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  <!-- Vista calendario (mini) + lista -->
  <div class="row g-3">
    <!-- Timeline / lista -->
    <div class="col-12">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <span class="fw-semibold">Scadenze</span>
          <div class="btn-group btn-group-sm" id="viewToggle">
            <button class="btn btn-outline-secondary active" id="btnViewList" title="Lista">
              <i class="bi bi-list-ul"></i>
            </button>
            <button class="btn btn-outline-secondary" id="btnViewTimeline" title="Timeline">
              <i class="bi bi-calendar3"></i>
            </button>
          </div>
        </div>
        <div id="viewList">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-3">Urgenza</th>
                  <th>Descrizione / Commessa</th>
                  <th>Tipo</th>
                  <th>Data Scadenza</th>
                  <th>Giorni</th>
                  <th>Responsabile</th>
                  <th>Stato</th>
                  <th class="text-end pe-3">Azioni</th>
                </tr>
              </thead>
              <tbody id="scadenzeBody">
                <tr><td colspan="8" class="text-center py-5 text-muted">
                  <div class="spinner-border spinner-border-sm me-2"></div>Caricamento...
                </td></tr>
              </tbody>
            </table>
          </div>
        </div>
        <div id="viewTimeline" class="d-none p-3">
          <div id="timelineWrap"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Paginazione -->
  <div class="mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <small class="text-muted" id="paginationInfo"></small>
    <nav><ul class="pagination pagination-sm mb-0" id="pagination"></ul></nav>
  </div>
</div>

<!-- ======================================================
     MODAL SCADENZA
====================================================== -->
<div class="modal fade" id="scadenzaModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="scadenzaModalLabel">
          <i class="bi bi-calendar-plus me-2"></i>Nuova Scadenza
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="scadenzaForm" novalidate>
          <input type="hidden" id="scadenzaId">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Commessa <span class="text-danger">*</span></label>
              <select class="form-select" id="fCommessa" name="commessa_id" required>
                <option value="">— Seleziona —</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Tipo <span class="text-danger">*</span></label>
              <select class="form-select" name="tipo" required>
                <option value="CONTRATTUALE">Contrattuale</option>
                <option value="NORMATIVA">Normativa</option>
                <option value="DOCUMENTALE">Documentale</option>
                <option value="PAGAMENTO">Pagamento</option>
                <option value="COLLAUDO">Collaudo</option>
                <option value="ALTRO">Altro</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Descrizione <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="descrizione" required
                     placeholder="Descrizione della scadenza...">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Data Scadenza <span class="text-danger">*</span></label>
              <input type="date" class="form-control" name="data_scadenza" id="fDataScadenza" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Promemoria (giorni prima)</label>
              <input type="number" class="form-control" name="promemoria_giorni" min="0" max="365"
                     placeholder="7" value="7">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Responsabile</label>
              <select class="form-select" name="responsabile_id" id="fResponsabile">
                <option value="">— Seleziona —</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Note</label>
              <textarea class="form-control" name="note" rows="2" placeholder="Note aggiuntive..."></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Stato</label>
              <select class="form-select" name="stato" id="fStatoSc">
                <option value="ATTIVA">Attiva</option>
                <option value="COMPLETATA">Completata</option>
                <option value="ANNULLATA">Annullata</option>
              </select>
            </div>
          </div>
          <div id="formErrors" class="mt-3"></div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
        <button type="button" class="btn btn-primary" id="btnSalvaScadenza">
          <i class="bi bi-check-lg me-1"></i>Salva
        </button>
      </div>
    </div>
  </div>
</div>

<?php
$commessaIdFilter = sanitizeInt($_GET['commessa_id'] ?? null, 1);
$inlineScript = <<<'JS'
let currentPage = 1;
let searchTimer = null;
const PRESET_COMMESSA_ID = __COMMESSA_ID__;

document.addEventListener('DOMContentLoaded', () => {
    loadSelectOptions();
    loadScadenze();
    initFilters();
    initForm();

    if (PRESET_COMMESSA_ID) {
        document.getElementById('filterCommessa').value = PRESET_COMMESSA_ID;
    }

    document.getElementById('btnViewList').addEventListener('click', () => {
        document.getElementById('viewList').classList.remove('d-none');
        document.getElementById('viewTimeline').classList.add('d-none');
        document.getElementById('btnViewList').classList.add('active');
        document.getElementById('btnViewTimeline').classList.remove('active');
    });
    document.getElementById('btnViewTimeline').addEventListener('click', () => {
        document.getElementById('viewTimeline').classList.remove('d-none');
        document.getElementById('viewList').classList.add('d-none');
        document.getElementById('btnViewTimeline').classList.add('active');
        document.getElementById('btnViewList').classList.remove('active');
        renderTimeline();
    });
});

let _cachedScadenze = [];

async function loadSelectOptions() {
    try {
        // Commesse per filtro
        const res = await API.get('/api/pm_commesse.php?per_page=200&sort_by=codice');
        const list = res.data || [];
        const selF = document.getElementById('filterCommessa');
        const selC = document.getElementById('fCommessa');
        list.forEach(c => {
            const opt = `<option value="${c.id}">${escapeHtml(c.codice_commessa)} — ${escapeHtml(c.oggetto.substring(0,40))}</option>`;
            selF.insertAdjacentHTML('beforeend', opt);
            selC.insertAdjacentHTML('beforeend', opt);
        });
        // Utenti per responsabile
        const resU = await API.get('/api/pm_utenti.php?per_page=200');
        const pm_utenti = resU.data || [];
        const selR = document.getElementById('fResponsabile');
        pm_utenti.forEach(u => {
            selR.insertAdjacentHTML('beforeend',
                `<option value="${u.id}">${escapeHtml(u.cognome)} ${escapeHtml(u.nome)}</option>`);
        });
    } catch(e) {}
}

async function loadScadenze(page = 1) {
    currentPage = page;
    const search  = document.getElementById('searchInput').value.trim();
    const urgenza = document.getElementById('filterUrgenza').value;
    const tipo    = document.getElementById('filterTipo').value;
    const commId  = document.getElementById('filterCommessa').value;
    const stato   = document.getElementById('filterStato').value;

    const params = new URLSearchParams({
        page, per_page: 20,
        ...(search  && { search }),
        ...(urgenza && { urgenza }),
        ...(tipo    && { tipo }),
        ...(commId  && { commessa_id: commId }),
        ...(stato   && { stato }),
        sort: 'data_scadenza',
    });

    try {
        const res = await API.get('/api/pm_scadenze.php?' + params.toString());
        const list = res.data || [];
        _cachedScadenze = list;

        // KPI
        const meta = res.meta || {};
        document.getElementById('kpiScadute').textContent  = meta.scadute ?? '—';
        document.getElementById('kpiCritiche').textContent = meta.critiche ?? '—';
        document.getElementById('kpiUrgenti').textContent  = meta.urgenti ?? '—';
        document.getElementById('kpiTotale').textContent   = meta.total ?? '—';

        renderTable(list);
        renderPagination(meta.current_page || 1, meta.last_page || 1, 'paginazione', loadScadenze);
        document.getElementById('paginationInfo').textContent =
            `${meta.from ?? 0}–${meta.to ?? 0} di ${meta.total ?? 0} pm_scadenze`;
    } catch(e) {
        document.getElementById('scadenzeBody').innerHTML =
            `<tr><td colspan="8" class="text-danger text-center py-4">${escapeHtml(e.message)}</td></tr>`;
    }
}

function renderTable(list) {
    const tbody = document.getElementById('scadenzeBody');
    if (!list.length) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-muted">
            <i class="bi bi-calendar2-x fs-1 d-block mb-2"></i>Nessuna scadenza trovata</td></tr>`;
        return;
    }
    tbody.innerHTML = list.map(s => {
        const g = parseInt(s.giorni);
        let urgIcon, urgLabel, rowCls = '';
        if (g < 0) {
            urgIcon  = 'bi-alarm-fill text-danger';
            urgLabel = '<span class="badge bg-danger">SCADUTA</span>';
            rowCls   = 'table-danger';
        } else if (g <= 7) {
            urgIcon  = 'bi-exclamation-triangle-fill text-warning';
            urgLabel = '<span class="badge bg-warning text-dark">CRITICA</span>';
            rowCls   = 'table-warning';
        } else if (g <= 15) {
            urgIcon  = 'bi-clock-fill text-info';
            urgLabel = '<span class="badge bg-info text-dark">URGENTE</span>';
        } else {
            urgIcon  = 'bi-calendar-check text-success';
            urgLabel = '<span class="badge bg-success">NORMALE</span>';
        }
        return `<tr class="${rowCls}">
            <td class="ps-3">${urgLabel}</td>
            <td>
                <div class="fw-semibold">${escapeHtml(s.descrizione ?? '—')}</div>
                <small class="text-muted">${escapeHtml(s.commessa ?? '')} ${s.codice_commessa ? '('+escapeHtml(s.codice_commessa)+')' : ''}</small>
            </td>
            <td><span class="badge bg-light text-dark">${escapeHtml(s.tipo ?? '—')}</span></td>
            <td class="fw-semibold">${Format.date(s.data_scadenza)}</td>
            <td><i class="bi ${urgIcon} me-1"></i>${g < 0 ? Math.abs(g)+' gg fa' : g === 0 ? 'Oggi' : g+' gg'}</td>
            <td class="small text-muted">${escapeHtml(s.responsabile ?? '—')}</td>
            <td>${Format.badgeStato(s.stato ?? 'ATTIVA')}</td>
            <td class="text-end pe-3">
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary" onclick="openEdit(${s.id})" title="Modifica">
                        <i class="bi bi-pencil"></i>
                    </button>
                    ${s.stato === 'ATTIVA' ? `<button class="btn btn-outline-success" onclick="completa(${s.id})" title="Segna completata">
                        <i class="bi bi-check2"></i></button>` : ''}
                    <button class="btn btn-outline-danger" onclick="elimina(${s.id}, '${escapeHtml(s.descrizione?.substring(0,30) ?? '')}')">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </td>
        </tr>`;
    }).join('');
}

function renderTimeline() {
    const wrap = document.getElementById('timelineWrap');
    const sorted = [..._cachedScadenze].sort((a,b) =>
        new Date(a.data_scadenza) - new Date(b.data_scadenza));
    if (!sorted.length) {
        wrap.innerHTML = '<p class="text-muted text-center py-4">Nessuna scadenza</p>';
        return;
    }
    wrap.innerHTML = sorted.map(s => {
        const g = parseInt(s.giorni);
        const cls = g < 0 ? 'border-danger' : g <= 7 ? 'border-warning' : g <= 15 ? 'border-info' : 'border-success';
        const icon = g < 0 ? 'bi-alarm text-danger' : g <= 7 ? 'bi-exclamation-triangle text-warning' : 'bi-calendar-check text-success';
        return `<div class="d-flex gap-3 mb-3">
            <div class="text-center flex-shrink-0" style="width:60px">
                <div class="fw-bold small">${Format.date(s.data_scadenza).split('/').slice(0,2).join('/')}</div>
                <i class="bi ${icon} fs-5"></i>
            </div>
            <div class="card flex-grow-1 border-start border-3 ${cls} shadow-sm">
                <div class="card-body py-2">
                    <div class="fw-semibold">${escapeHtml(s.descrizione ?? '—')}</div>
                    <small class="text-muted">${escapeHtml(s.commessa ?? '')} · ${escapeHtml(s.tipo ?? '')} · ${escapeHtml(s.responsabile ?? '—')}</small>
                </div>
            </div>
        </div>`;
    }).join('');
}

function initFilters() {
    document.getElementById('searchInput').addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadScadenze(1), 350);
    });
    ['filterUrgenza','filterTipo','filterCommessa','filterStato'].forEach(id =>
        document.getElementById(id).addEventListener('change', () => loadScadenze(1)));
}

function initForm() {
    document.getElementById('btnNuovaScadenza')?.addEventListener('click', openCreate);
    document.getElementById('btnSalvaScadenza').addEventListener('click', salvaScadenza);
}

function openCreate() {
    document.getElementById('scadenzaId').value = '';
    document.getElementById('scadenzaModalLabel').innerHTML =
        '<i class="bi bi-calendar-plus me-2"></i>Nuova Scadenza';
    document.getElementById('scadenzaForm').reset();
    if (PRESET_COMMESSA_ID) {
        document.getElementById('fCommessa').value = PRESET_COMMESSA_ID;
    }
    document.getElementById('formErrors').innerHTML = '';
    // Default data = oggi + 30gg
    const d = new Date(); d.setDate(d.getDate()+30);
    document.getElementById('fDataScadenza').value = d.toISOString().split('T')[0];
    new bootstrap.Modal(document.getElementById('scadenzaModal')).show();
}

async function openEdit(id) {
    document.getElementById('scadenzaModalLabel').innerHTML =
        '<i class="bi bi-pencil me-2"></i>Modifica Scadenza';
    document.getElementById('formErrors').innerHTML = '';
    const modal = new bootstrap.Modal(document.getElementById('scadenzaModal'));
    modal.show();
    try {
        const res = await API.get('/api/pm_scadenze.php?id=' + id);
        const s = res.data;
        document.getElementById('scadenzaId').value = s.id;
        const form = document.getElementById('scadenzaForm');
        Object.entries(s).forEach(([k,v]) => {
            const el = form.querySelector(`[name="${k}"]`);
            if (el) el.value = v ?? '';
        });
        if (s.commessa_id) document.getElementById('fCommessa').value = s.commessa_id;
        if (s.responsabile_id) document.getElementById('fResponsabile').value = s.responsabile_id;
    } catch(e) { UI.error(e.message); }
}

async function salvaScadenza() {
    const form = document.getElementById('scadenzaForm');
    if (!form.checkValidity()) { form.classList.add('was-validated'); return; }
    const data = serializeForm(form);
    data.id = document.getElementById('scadenzaId').value || null;
    UI.showLoader();
    try {
        if (data.id) {
            await API.put('/api/pm_scadenze.php', data);
            UI.success('Scadenza aggiornata');
        } else {
            await API.post('/api/pm_scadenze.php', data);
            UI.success('Scadenza creata');
        }
        bootstrap.Modal.getInstance(document.getElementById('scadenzaModal')).hide();
        loadScadenze(currentPage);
    } catch(e) {
        if (e.errors) showFormErrors(e.errors, 'formErrors');
        else document.getElementById('formErrors').innerHTML =
            `<div class="alert alert-danger">${escapeHtml(e.message)}</div>`;
    } finally { UI.hideLoader(); }
}

async function completa(id) {
    const ok = await UI.confirm('Segnare questa scadenza come completata?');
    if (!ok) return;
    try {
        await API.put('/api/pm_scadenze.php', { id, stato: 'COMPLETATA' });
        UI.success('Scadenza completata');
        loadScadenze(currentPage);
    } catch(e) { UI.error(e.message); }
}

async function elimina(id, desc) {
    const ok = await UI.confirm(`Eliminare la scadenza <strong>${escapeHtml(desc)}</strong>?`);
    if (!ok) return;
    try {
        await API.delete('/api/pm_scadenze.php', { id });
        UI.success('Scadenza eliminata');
        loadScadenze(currentPage);
    } catch(e) { UI.error(e.message); }
}
JS;
$inlineScript = str_replace('__COMMESSA_ID__', $commessaIdFilter ?? 'null', $inlineScript);
include __DIR__ . '/../components/footer.php';
