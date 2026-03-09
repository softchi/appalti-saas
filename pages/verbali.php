<?php
/**
 * Pagina: Verbali di Cantiere
 */
define('APP_INIT', true);
require_once __DIR__ . '/../php/bootstrap.php';
Auth::require();
if (!Auth::can('pm_verbali.read')) {
    header('Location: ' . APP_URL . '/pages/dashboard.php');
    exit;
}

$pageTitle  = 'Verbali';
$activeMenu = 'pm_verbali';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/sidebar.php';
?>

<div class="container-fluid px-4 py-3">

  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h1 class="h4 mb-1 fw-bold">Verbali di Cantiere</h1>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
          <li class="breadcrumb-item"><a href="<?= APP_URL ?>/pages/dashboard.php">Dashboard</a></li>
          <li class="breadcrumb-item active">Verbali</li>
        </ol>
      </nav>
    </div>
    <?php if (Auth::can('pm_verbali.create')): ?>
    <button class="btn btn-primary" id="btnNuovoVerbale">
      <i class="bi bi-journal-plus me-2"></i>Nuovo Verbale
    </button>
    <?php endif; ?>
  </div>

  <!-- Filtri -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
      <div class="row g-2 align-items-center">
        <div class="col-12 col-md-4">
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
            <input type="search" class="form-control border-start-0" id="searchInput"
                   placeholder="Cerca per oggetto, luogo...">
          </div>
        </div>
        <div class="col-6 col-md-2">
          <select class="form-select form-select-sm" id="filterTipo">
            <option value="">Tutti i tipi</option>
            <option value="CONSEGNA_LAVORI">Consegna Lavori</option>
            <option value="SOSPENSIONE">Sospensione</option>
            <option value="RIPRESA">Ripresa</option>
            <option value="VISITA_CANTIERE">Visita Cantiere</option>
            <option value="COLLAUDO">Collaudo</option>
            <option value="CONTABILITA">Contabilità</option>
            <option value="RIUNIONE">Riunione</option>
            <option value="ALTRO">Altro</option>
          </select>
        </div>
        <div class="col-6 col-md-3">
          <select class="form-select form-select-sm" id="filterCommessa">
            <option value="">Tutte le pm_commesse</option>
          </select>
        </div>
        <div class="col-6 col-md-auto">
          <input type="date" class="form-control form-control-sm" id="filterDataDa" title="Da data">
        </div>
        <div class="col-6 col-md-auto">
          <input type="date" class="form-control form-control-sm" id="filterDataA" title="A data">
        </div>
      </div>
    </div>
  </div>

  <!-- Lista pm_verbali -->
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="ps-3">N°</th>
              <th>Tipo</th>
              <th>Oggetto</th>
              <th>Commessa</th>
              <th>Data</th>
              <th>Luogo</th>
              <th>Redatto da</th>
              <th class="text-end pe-3">Azioni</th>
            </tr>
          </thead>
          <tbody id="verbaliBody">
            <tr><td colspan="8" class="text-center py-5 text-muted">
              <div class="spinner-border spinner-border-sm me-2"></div>Caricamento...
            </td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <small class="text-muted" id="paginationInfo"></small>
    <nav><ul class="pagination pagination-sm mb-0" id="pagination"></ul></nav>
  </div>
</div>

<!-- ======================================================
     MODAL VERBALE
====================================================== -->
<div class="modal fade" id="verbaleModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="verbaleModalLabel">
          <i class="bi bi-journal-plus me-2"></i>Nuovo Verbale
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="verbaleForm" novalidate>
          <input type="hidden" id="verbaleId">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Commessa <span class="text-danger">*</span></label>
              <select class="form-select" id="fVCommessa" name="commessa_id" required>
                <option value="">— Seleziona —</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Tipo Verbale <span class="text-danger">*</span></label>
              <select class="form-select" name="tipo" required>
                <option value="CONSEGNA_LAVORI">Consegna Lavori</option>
                <option value="SOSPENSIONE">Sospensione</option>
                <option value="RIPRESA">Ripresa</option>
                <option value="VISITA_CANTIERE">Visita Cantiere</option>
                <option value="COLLAUDO">Collaudo</option>
                <option value="CONTABILITA">Contabilità</option>
                <option value="RIUNIONE" selected>Riunione</option>
                <option value="ALTRO">Altro</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Oggetto <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="oggetto" required
                     placeholder="Oggetto del verbale...">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Data Verbale <span class="text-danger">*</span></label>
              <input type="date" class="form-control" name="data_verbale" id="fDataVerbale" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Ora Inizio</label>
              <input type="time" class="form-control" name="ora_inizio">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Ora Fine</label>
              <input type="time" class="form-control" name="ora_fine">
            </div>
            <div class="col-md-8">
              <label class="form-label fw-semibold">Luogo</label>
              <input type="text" class="form-control" name="luogo"
                     placeholder="es. Cantiere Via Roma, Sala Riunioni...">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Numero Verbale</label>
              <input type="number" class="form-control" name="numero_verbale" min="1"
                     placeholder="Auto se vuoto">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Partecipanti</label>
              <textarea class="form-control" name="partecipanti" rows="2"
                        placeholder="Elenco partecipanti (uno per riga o separati da virgola)..."></textarea>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Contenuto / Verbale</label>
              <textarea class="form-control font-monospace" name="contenuto" rows="8"
                        placeholder="Testo del verbale...&#10;&#10;Argomenti trattati:&#10;1. ...&#10;2. ...&#10;&#10;Deliberazioni:&#10;..."></textarea>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Prescrizioni / Note</label>
              <textarea class="form-control" name="prescrizioni" rows="3"
                        placeholder="Prescrizioni, note, azioni da intraprendere..."></textarea>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Stato</label>
              <select class="form-select" name="stato">
                <option value="BOZZA">Bozza</option>
                <option value="FIRMATO">Firmato</option>
                <option value="ARCHIVIATO">Archiviato</option>
              </select>
            </div>
          </div>
          <div id="formErrors" class="mt-3"></div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
        <button type="button" class="btn btn-primary" id="btnSalvaVerbale">
          <i class="bi bi-check-lg me-1"></i>Salva Verbale
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal visualizzazione verbale -->
<div class="modal fade" id="viewVerbaleModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewVerbaleTitle">Verbale</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="viewVerbaleBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
        <button class="btn btn-outline-primary" id="btnStampaVerbale" onclick="window.print()">
          <i class="bi bi-printer me-1"></i>Stampa
        </button>
        <button class="btn btn-primary" id="btnEditVerbale">
          <i class="bi bi-pencil me-1"></i>Modifica
        </button>
      </div>
    </div>
  </div>
</div>

<?php
$commessaIdFilter = sanitizeInt($_GET['commessa_id'] ?? null, 1);
$inlineScript = <<<JS
let currentPage = 1;
let searchTimer = null;
let viewingId   = null;
const PRESET_COMMESSA_ID = {$commessaIdFilter};

document.addEventListener('DOMContentLoaded', () => {
    loadSelectOptions();
    loadVerbali();
    initFilters();
    document.getElementById('btnNuovoVerbale')?.addEventListener('click', openCreate);
    document.getElementById('btnSalvaVerbale').addEventListener('click', salvaVerbale);
    document.getElementById('btnEditVerbale').addEventListener('click', () => {
        bootstrap.Modal.getInstance(document.getElementById('viewVerbaleModal')).hide();
        openEdit(viewingId);
    });
    if (PRESET_COMMESSA_ID) document.getElementById('filterCommessa').value = PRESET_COMMESSA_ID;
    // Set today as default date
    document.getElementById('fDataVerbale').value = new Date().toISOString().split('T')[0];
});

async function loadSelectOptions() {
    const res = await API.get('/api/pm_commesse.php?per_page=200&sort_by=codice');
    const list = res.data || [];
    [document.getElementById('filterCommessa'), document.getElementById('fVCommessa')].forEach(sel => {
        list.forEach(c => sel.insertAdjacentHTML('beforeend',
            `<option value="${c.id}">${escapeHtml(c.codice_commessa)} — ${escapeHtml(c.oggetto.substring(0,40))}</option>`));
    });
}

async function loadVerbali(page = 1) {
    currentPage = page;
    const search = document.getElementById('searchInput').value.trim();
    const tipo   = document.getElementById('filterTipo').value;
    const commId = document.getElementById('filterCommessa').value;
    const da     = document.getElementById('filterDataDa').value;
    const a      = document.getElementById('filterDataA').value;

    const params = new URLSearchParams({
        page, per_page: 15,
        ...(search && { search }),
        ...(tipo   && { tipo }),
        ...(commId && { commessa_id: commId }),
        ...(da     && { data_da: da }),
        ...(a      && { data_a: a }),
        sort: 'data_verbale_desc',
    });

    try {
        const res = await API.get('/api/pm_verbali.php?' + params.toString());
        renderTable(res.data || []);
        const meta = res.meta || {};
        renderPagination(meta.current_page || 1, meta.last_page || 1, 'paginazione', loadVerbali);
        document.getElementById('paginationInfo').textContent =
            `${meta.from ?? 0}–${meta.to ?? 0} di ${meta.total ?? 0} pm_verbali`;
    } catch(e) {
        document.getElementById('verbaliBody').innerHTML =
            `<tr><td colspan="8" class="text-danger text-center py-4">${escapeHtml(e.message)}</td></tr>`;
    }
}

const tipoColorMap = {
    CONSEGNA_LAVORI:'primary', SOSPENSIONE:'warning', RIPRESA:'success',
    VISITA_CANTIERE:'info', COLLAUDO:'dark', CONTABILITA:'secondary',
    RIUNIONE:'light', ALTRO:'light'
};

function renderTable(list) {
    const tbody = document.getElementById('verbaliBody');
    if (!list.length) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-muted">
            <i class="bi bi-journal-x fs-1 d-block mb-2"></i>Nessun verbale</td></tr>`;
        return;
    }
    tbody.innerHTML = list.map(v => {
        const tipoCls = tipoColorMap[v.tipo] ?? 'secondary';
        const tipoLabel = v.tipo?.replace(/_/g,' ') ?? '';
        return `<tr>
            <td class="ps-3 fw-semibold text-muted small">#${v.numero_verbale ?? '—'}</td>
            <td><span class="badge bg-${tipoCls} ${tipoCls==='light'?'text-dark':''}">${tipoLabel}</span></td>
            <td>
                <button class="btn btn-link p-0 text-start fw-semibold text-decoration-none"
                    onclick="viewVerbale(${v.id})">${escapeHtml(v.oggetto ?? '—')}</button>
            </td>
            <td class="small text-muted">${escapeHtml(v.codice_commessa ?? '—')}</td>
            <td>${Format.date(v.data_verbale)}</td>
            <td class="small text-muted">${escapeHtml(v.luogo ?? '—')}</td>
            <td class="small text-muted">${escapeHtml(v.redattore ?? '—')}</td>
            <td class="text-end pe-3">
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" onclick="viewVerbale(${v.id})" title="Visualizza">
                        <i class="bi bi-eye"></i>
                    </button>
                    <button class="btn btn-outline-secondary" onclick="openEdit(${v.id})" title="Modifica">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-outline-danger" onclick="elimina(${v.id},'${escapeHtml((v.oggetto??'').substring(0,30))}')">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </td>
        </tr>`;
    }).join('');
}

async function viewVerbale(id) {
    viewingId = id;
    const modal = new bootstrap.Modal(document.getElementById('viewVerbaleModal'));
    modal.show();
    document.getElementById('viewVerbaleBody').innerHTML =
        '<div class="text-center py-4"><div class="spinner-border"></div></div>';
    try {
        const res = await API.get('/api/pm_verbali.php?id=' + id);
        const v = res.data;
        document.getElementById('viewVerbaleTitle').textContent =
            `Verbale n. ${v.numero_verbale ?? '—'} — ${v.oggetto ?? ''}`;
        document.getElementById('viewVerbaleBody').innerHTML = `
            <div class="mb-3 d-flex flex-wrap gap-3 text-muted small">
                <span><i class="bi bi-calendar me-1"></i>${Format.date(v.data_verbale)}</span>
                ${v.ora_inizio ? `<span><i class="bi bi-clock me-1"></i>${v.ora_inizio}${v.ora_fine?' – '+v.ora_fine:''}</span>` : ''}
                ${v.luogo ? `<span><i class="bi bi-geo-alt me-1"></i>${escapeHtml(v.luogo)}</span>` : ''}
                <span><i class="bi bi-briefcase me-1"></i>${escapeHtml(v.codice_commessa ?? '')} ${escapeHtml(v.commessa_oggetto ?? '')}</span>
            </div>
            ${v.partecipanti ? `<div class="mb-3">
                <strong>Partecipanti:</strong>
                <p class="mb-0 text-muted">${escapeHtml(v.partecipanti)}</p>
            </div>` : ''}
            <div class="mb-3">
                <strong>Verbale:</strong>
                <pre class="bg-light rounded p-3 mt-1" style="white-space:pre-wrap;font-family:inherit">${escapeHtml(v.contenuto ?? '')}</pre>
            </div>
            ${v.prescrizioni ? `<div class="alert alert-warning mb-0">
                <strong>Prescrizioni:</strong><br>
                ${escapeHtml(v.prescrizioni)}
            </div>` : ''}`;
    } catch(e) {
        document.getElementById('viewVerbaleBody').innerHTML =
            `<p class="text-danger">${escapeHtml(e.message)}</p>`;
    }
}

function openCreate() {
    document.getElementById('verbaleId').value = '';
    document.getElementById('verbaleModalLabel').innerHTML =
        '<i class="bi bi-journal-plus me-2"></i>Nuovo Verbale';
    document.getElementById('verbaleForm').reset();
    document.getElementById('fDataVerbale').value = new Date().toISOString().split('T')[0];
    if (PRESET_COMMESSA_ID) document.getElementById('fVCommessa').value = PRESET_COMMESSA_ID;
    document.getElementById('formErrors').innerHTML = '';
    new bootstrap.Modal(document.getElementById('verbaleModal')).show();
}

async function openEdit(id) {
    document.getElementById('verbaleModalLabel').innerHTML =
        '<i class="bi bi-pencil me-2"></i>Modifica Verbale';
    document.getElementById('formErrors').innerHTML = '';
    const modal = new bootstrap.Modal(document.getElementById('verbaleModal'));
    modal.show();
    try {
        const res = await API.get('/api/pm_verbali.php?id=' + id);
        const v = res.data;
        document.getElementById('verbaleId').value = v.id;
        const form = document.getElementById('verbaleForm');
        Object.entries(v).forEach(([k,val]) => {
            const el = form.querySelector(`[name="${k}"]`);
            if (el) el.value = val ?? '';
        });
        if (v.commessa_id) document.getElementById('fVCommessa').value = v.commessa_id;
    } catch(e) { UI.error(e.message); }
}

async function salvaVerbale() {
    const form = document.getElementById('verbaleForm');
    if (!form.checkValidity()) { form.classList.add('was-validated'); return; }
    const data = serializeForm(form);
    data.id = document.getElementById('verbaleId').value || null;
    UI.showLoader();
    try {
        if (data.id) {
            await API.put('/api/pm_verbali.php', data);
            UI.success('Verbale aggiornato');
        } else {
            await API.post('/api/pm_verbali.php', data);
            UI.success('Verbale creato');
        }
        bootstrap.Modal.getInstance(document.getElementById('verbaleModal')).hide();
        loadVerbali(currentPage);
    } catch(e) {
        if (e.errors) showFormErrors(e.errors, 'formErrors');
        else document.getElementById('formErrors').innerHTML =
            `<div class="alert alert-danger">${escapeHtml(e.message)}</div>`;
    } finally { UI.hideLoader(); }
}

async function elimina(id, obj) {
    const ok = await UI.confirm(`Eliminare il verbale <strong>${escapeHtml(obj)}</strong>?`);
    if (!ok) return;
    try {
        await API.delete('/api/pm_verbali.php', { id });
        UI.success('Verbale eliminato');
        loadVerbali(currentPage);
    } catch(e) { UI.error(e.message); }
}

function initFilters() {
    document.getElementById('searchInput').addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadVerbali(1), 350);
    });
    ['filterTipo','filterCommessa','filterDataDa','filterDataA'].forEach(id =>
        document.getElementById(id).addEventListener('change', () => loadVerbali(1)));
}
JS;
include __DIR__ . '/../components/footer.php';
