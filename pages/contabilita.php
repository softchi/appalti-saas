<?php
/**
 * Pagina: Contabilità Lavori (categorie, voci, varianti)
 */
define('APP_INIT', true);
require_once __DIR__ . '/../php/bootstrap.php';
Auth::require();
if (!Auth::can('sal.read')) {
    header('Location: ' . APP_URL . '/pages/dashboard.php');
    exit;
}

$pageTitle  = 'Contabilità Lavori';
$activeMenu = 'contabilita';
$extraScripts = ['js/charts.js'];
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/sidebar.php';
?>

<div class="container-fluid px-4 py-3">

  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h1 class="h4 mb-1 fw-bold">Contabilità Lavori</h1>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
          <li class="breadcrumb-item"><a href="<?= APP_URL ?>/pages/dashboard.php">Dashboard</a></li>
          <li class="breadcrumb-item active">Contabilità</li>
        </ol>
      </nav>
    </div>
    <div class="d-flex gap-2">
      <select class="form-select form-select-sm" id="selectCommessa" style="max-width:300px">
        <option value="">— Seleziona Commessa —</option>
      </select>
      <?php if (Auth::can('sal.create')): ?>
      <button class="btn btn-primary btn-sm" id="btnNuovaCategoria">
        <i class="bi bi-plus-circle me-1"></i>Categoria
      </button>
      <button class="btn btn-outline-warning btn-sm" id="btnNuovaVariante">
        <i class="bi bi-shuffle me-1"></i>Variante
      </button>
      <?php endif; ?>
    </div>
  </div>

  <!-- KPI -->
  <div class="row g-3 mb-4" id="kpiRow">
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm border-start border-4 border-primary">
        <div class="card-body"><small class="text-muted">Importo Contrattuale</small>
          <h5 class="mb-0 fw-bold" id="kpiContrattuale">—</h5></div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm border-start border-4 border-success">
        <div class="card-body"><small class="text-muted">Importo Eseguito</small>
          <h5 class="mb-0 fw-bold" id="kpiEseguito">—</h5></div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm border-start border-4 border-warning">
        <div class="card-body"><small class="text-muted">Varianti Approvate</small>
          <h5 class="mb-0 fw-bold" id="kpiVarianti">—</h5></div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm border-start border-4 border-info">
        <div class="card-body"><small class="text-muted">% Avanzamento Economico</small>
          <h5 class="mb-0 fw-bold" id="kpiPercAvanz">—</h5></div>
      </div>
    </div>
  </div>

  <!-- Tabs -->
  <ul class="nav nav-tabs mb-3" id="contabTabs">
    <li class="nav-item">
      <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabCategorie">
        <i class="bi bi-list-columns me-1"></i>Categorie di Lavoro
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabVarianti">
        <i class="bi bi-shuffle me-1"></i>Varianti
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabGrafico">
        <i class="bi bi-bar-chart me-1"></i>Grafici
      </button>
    </li>
  </ul>

  <div class="tab-content">

    <!-- Categorie -->
    <div class="tab-pane fade show active" id="tabCategorie">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="categorieTable">
              <thead class="table-light">
                <tr>
                  <th class="ps-3">Codice</th>
                  <th>Descrizione</th>
                  <th>U.M.</th>
                  <th class="text-end">Q.tà Contr.</th>
                  <th class="text-end">Prezzo Un.</th>
                  <th class="text-end">Imp. Contr.</th>
                  <th class="text-end">Q.tà Eseg.</th>
                  <th class="text-end">Imp. Eseg.</th>
                  <th>% Eseg.</th>
                  <?php if (Auth::can('sal.update')): ?>
                  <th class="text-end pe-3">Azioni</th>
                  <?php endif; ?>
                </tr>
              </thead>
              <tbody id="categorieBody">
                <tr><td colspan="10" class="text-center py-5 text-muted">
                  Seleziona una commessa per visualizzare le categorie di lavoro
                </td></tr>
              </tbody>
              <tfoot id="categorieFoot" class="table-light fw-semibold"></tfoot>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Varianti -->
    <div class="tab-pane fade" id="tabVarianti">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-3">N°</th>
                  <th>Descrizione</th>
                  <th>Tipo</th>
                  <th>Importo</th>
                  <th>Data Approvazione</th>
                  <th>Stato</th>
                  <?php if (Auth::can('sal.update')): ?>
                  <th class="text-end pe-3">Azioni</th>
                  <?php endif; ?>
                </tr>
              </thead>
              <tbody id="variantiBody">
                <tr><td colspan="7" class="text-center py-5 text-muted">
                  Seleziona una commessa per visualizzare le varianti
                </td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Grafici -->
    <div class="tab-pane fade" id="tabGrafico">
      <div class="row g-3">
        <div class="col-lg-7">
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Categorie: Contratto vs Eseguito</div>
            <div class="card-body"><canvas id="chartCategorie" height="300"></canvas></div>
          </div>
        </div>
        <div class="col-lg-5">
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Distribuzione Importi</div>
            <div class="card-body"><canvas id="chartDistribuzione" height="300"></canvas></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Categoria -->
<div class="modal fade" id="categoriaModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="bi bi-list-columns me-2"></i>Categoria di Lavoro</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="categoriaForm" novalidate>
          <input type="hidden" id="catId">
          <input type="hidden" id="catCommessaId">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label fw-semibold">Codice <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="codice" required placeholder="A.01.001">
            </div>
            <div class="col-md-8">
              <label class="form-label fw-semibold">Descrizione <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="descrizione" required placeholder="Descrizione lavorazione...">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Unità Misura</label>
              <input type="text" class="form-control" name="unita_misura" placeholder="m², ml, nr...">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Q.tà Contrattuale</label>
              <input type="number" class="form-control" name="quantita_contrattuale" id="catQta"
                     step="0.001" min="0" placeholder="0.000">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Prezzo Unitario (€)</label>
              <input type="number" class="form-control" name="prezzo_unitario" id="catPrezzo"
                     step="0.01" min="0" placeholder="0.00">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Importo Calcolato</label>
              <div class="form-control bg-light fw-semibold" id="catImportoCalc">€ 0,00</div>
            </div>
            <div class="col-md-2">
              <label class="form-label fw-semibold">Ordine</label>
              <input type="number" class="form-control" name="ordine" value="0" min="0">
            </div>
          </div>
          <div id="catFormErrors" class="mt-3"></div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
        <button type="button" class="btn btn-primary" id="btnSalvaCategoria">Salva</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Variante -->
<div class="modal fade" id="varianteModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title"><i class="bi bi-shuffle me-2"></i>Variante</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="varianteForm" novalidate>
          <input type="hidden" id="varId">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label fw-semibold">N° Variante</label>
              <input type="number" class="form-control" name="numero_variante" min="1" placeholder="Auto">
            </div>
            <div class="col-md-8">
              <label class="form-label fw-semibold">Tipo Variante</label>
              <select class="form-select" name="tipo">
                <option value="MIGLIORATIVA">Migliorativa</option>
                <option value="TECNICA">Tecnica</option>
                <option value="COMPENSATIVA">Compensativa</option>
                <option value="PERIZIA_SUPPLETIVA">Perizia Suppletiva (art.120)</option>
                <option value="ALTRA">Altra</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Descrizione <span class="text-danger">*</span></label>
              <textarea class="form-control" name="descrizione" required rows="3"
                        placeholder="Motivazione e descrizione della variante..."></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Importo (€)</label>
              <input type="number" class="form-control" name="importo" step="0.01" placeholder="0.00">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Data Approvazione</label>
              <input type="date" class="form-control" name="data_approvazione">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Stato</label>
              <select class="form-select" name="stato">
                <option value="IN_APPROVAZIONE">In Approvazione</option>
                <option value="APPROVATA">Approvata</option>
                <option value="RESPINTA">Respinta</option>
              </select>
            </div>
          </div>
          <div id="varFormErrors" class="mt-3"></div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
        <button type="button" class="btn btn-warning" id="btnSalvaVariante">Salva Variante</button>
      </div>
    </div>
  </div>
</div>

<?php
$commessaIdFilter = sanitizeInt($_GET['commessa_id'] ?? null, 1);
$inlineScript = <<<JS
let _commessaId = {$commessaIdFilter};
let _costiData  = null;

document.addEventListener('DOMContentLoaded', () => {
    loadCommesse();
    document.getElementById('selectCommessa').addEventListener('change', function() {
        _commessaId = this.value ? parseInt(this.value) : null;
        if (_commessaId) loadContabilita();
        else clearAll();
    });
    document.getElementById('btnNuovaCategoria')?.addEventListener('click', openCreateCategoria);
    document.getElementById('btnNuovaVariante')?.addEventListener('click', openCreateVariante);
    document.getElementById('btnSalvaCategoria').addEventListener('click', salvaCategoria);
    document.getElementById('btnSalvaVariante').addEventListener('click', salvaVariante);

    // Calcolo importo in tempo reale
    ['catQta','catPrezzo'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', () => {
            const q = parseFloat(document.getElementById('catQta').value) || 0;
            const p = parseFloat(document.getElementById('catPrezzo').value) || 0;
            document.getElementById('catImportoCalc').textContent = Format.euro(q * p);
        });
    });

    if (_commessaId) {
        document.getElementById('selectCommessa').value = _commessaId;
        loadContabilita();
    }

    // Varianti tab lazy
    document.querySelector('[data-bs-target="#tabVarianti"]').addEventListener('click', () => {
        if (_commessaId) loadVarianti();
    });
});

async function loadCommesse() {
    const res = await API.get('/api/commesse.php?per_page=200&sort_by=codice');
    const sel = document.getElementById('selectCommessa');
    (res.data || []).forEach(c => sel.insertAdjacentHTML('beforeend',
        `<option value="${c.id}">${escapeHtml(c.codice_commessa)} — ${escapeHtml(c.oggetto.substring(0,50))}</option>`));
    if (_commessaId) sel.value = _commessaId;
}

async function loadContabilita() {
    if (!_commessaId) return;
    try {
        const res = await API.get('/api/reports.php?tipo=costi&commessa_id=' + _commessaId);
        _costiData = res;
        document.getElementById('kpiContrattuale').textContent = res.totale_contrattuale_fmt ?? '—';
        document.getElementById('kpiEseguito').textContent     = res.totale_eseguito_fmt ?? '—';
        document.getElementById('kpiPercAvanz').textContent    = (parseFloat(res.perc_eseguita)||0).toFixed(1) + '%';

        // Varianti KPI
        loadVariantiKpi();
        renderCategorie(res.categorie || []);
        renderGrafici(res.categorie || []);
    } catch(e) { UI.error(e.message); }
}

async function loadVariantiKpi() {
    try {
        const res = await API.get('/api/commesse.php?id=' + _commessaId + '&include=varianti');
        const v = res.varianti_tot ?? 0;
        document.getElementById('kpiVarianti').textContent = Format.euro(v);
    } catch(e) {}
}

function renderCategorie(cats) {
    const tbody = document.getElementById('categorieBody');
    const tfoot = document.getElementById('categorieFoot');
    if (!cats.length) {
        tbody.innerHTML = '<tr><td colspan="10" class="text-center py-4 text-muted">Nessuna categoria. Crea la prima!</td></tr>';
        tfoot.innerHTML = '';
        return;
    }
    tbody.innerHTML = cats.map(c => {
        const perc = parseFloat(c.perc_eseguita || 0);
        const barCls = perc >= 90 ? 'bg-success' : perc >= 50 ? 'bg-primary' : 'bg-warning';
        return `<tr>
            <td class="ps-3 font-monospace small fw-semibold">${escapeHtml(c.codice)}</td>
            <td class="small">${escapeHtml(c.descrizione)}</td>
            <td class="small text-muted">${escapeHtml(c.unita_misura??'')}</td>
            <td class="text-end small">${parseFloat(c.quantita_contrattuale||0).toFixed(3)}</td>
            <td class="text-end small">${Format.euro(c.prezzo_unitario)}</td>
            <td class="text-end fw-semibold">${c.importo_contrattuale_fmt ?? Format.euro(c.importo_contrattuale)}</td>
            <td class="text-end small">${parseFloat(c.quantita_eseguita||0).toFixed(3)}</td>
            <td class="text-end fw-semibold">${c.importo_eseguito_fmt ?? Format.euro(c.importo_eseguito)}</td>
            <td style="min-width:100px">
                <div class="progress" style="height:6px">
                    <div class="progress-bar ${barCls}" style="width:${Math.min(perc,100)}%"></div>
                </div>
                <small>${perc.toFixed(1)}%</small>
            </td>
            <td class="text-end pe-3">
                <button class="btn btn-outline-secondary btn-sm" onclick="editCategoria(${c.id ?? 0})">
                    <i class="bi bi-pencil"></i>
                </button>
            </td>
        </tr>`;
    }).join('');

    const totC = cats.reduce((s,c) => s + parseFloat(c.importo_contrattuale||0), 0);
    const totE = cats.reduce((s,c) => s + parseFloat(c.importo_eseguito||0), 0);
    tfoot.innerHTML = `<tr>
        <td colspan="5" class="ps-3">TOTALE</td>
        <td class="text-end">${Format.euro(totC)}</td>
        <td></td>
        <td class="text-end">${Format.euro(totE)}</td>
        <td colspan="2"></td>
    </tr>`;
}

function renderGrafici(cats) {
    if (!cats.length) return;
    const labels = cats.map(c => c.codice + ' ' + (c.descrizione?.substring(0,20)??''));
    chartCostiCategorie('chartCategorie', labels, [
        {label:'Contrattuale', data: cats.map(c=>parseFloat(c.importo_contrattuale||0))},
        {label:'Eseguito',     data: cats.map(c=>parseFloat(c.importo_eseguito||0))},
    ]);
    chartStatoCommesse('chartDistribuzione', labels,
        cats.map(c => parseFloat(c.importo_contrattuale||0)));
}

async function loadVarianti() {
    if (!_commessaId) return;
    const tbody = document.getElementById('variantiBody');
    try {
        const res = await API.get('/api/sal.php?action=varianti&commessa_id=' + _commessaId);
        const list = res.data || [];
        if (!list.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Nessuna variante</td></tr>';
            return;
        }
        tbody.innerHTML = list.map(v => `<tr>
            <td class="ps-3 fw-semibold">Var. ${v.numero_variante??'—'}</td>
            <td class="small">${escapeHtml(v.descrizione??'')}</td>
            <td><span class="badge bg-light text-dark">${escapeHtml(v.tipo??'').replace(/_/g,' ')}</span></td>
            <td>${v.importo ? Format.euro(v.importo) : '—'}</td>
            <td class="small">${v.data_approvazione ? Format.date(v.data_approvazione) : '—'}</td>
            <td>${Format.badgeStato(v.stato??'IN_APPROVAZIONE')}</td>
            <td class="text-end pe-3">
                <button class="btn btn-outline-secondary btn-sm" onclick="editVariante(${v.id})">
                    <i class="bi bi-pencil"></i>
                </button>
            </td>
        </tr>`).join('');
    } catch(e) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-danger text-center py-3">${escapeHtml(e.message)}</td></tr>`;
    }
}

function clearAll() {
    document.getElementById('kpiContrattuale').textContent = '—';
    document.getElementById('kpiEseguito').textContent     = '—';
    document.getElementById('kpiVarianti').textContent     = '—';
    document.getElementById('kpiPercAvanz').textContent    = '—';
    document.getElementById('categorieBody').innerHTML =
        '<tr><td colspan="10" class="text-center py-4 text-muted">Seleziona una commessa</td></tr>';
    document.getElementById('categorieFoot').innerHTML = '';
    document.getElementById('variantiBody').innerHTML =
        '<tr><td colspan="7" class="text-center py-4 text-muted">Seleziona una commessa</td></tr>';
}

// ==============================
// CRUD Categorie
// ==============================
function openCreateCategoria() {
    if (!_commessaId) { UI.warning('Seleziona prima una commessa'); return; }
    document.getElementById('catId').value = '';
    document.getElementById('catCommessaId').value = _commessaId;
    document.getElementById('categoriaForm').reset();
    document.getElementById('catImportoCalc').textContent = '€ 0,00';
    document.getElementById('catFormErrors').innerHTML = '';
    new bootstrap.Modal(document.getElementById('categoriaModal')).show();
}

async function editCategoria(id) {
    if (!id) { openCreateCategoria(); return; }
    // Open modal then load data
    const modal = new bootstrap.Modal(document.getElementById('categoriaModal'));
    modal.show();
    try {
        const res = await API.get('/api/sal.php?action=categoria&id=' + id);
        const cat = res.data;
        document.getElementById('catId').value = cat.id;
        document.getElementById('catCommessaId').value = cat.commessa_id;
        const form = document.getElementById('categoriaForm');
        Object.entries(cat).forEach(([k,v]) => {
            const el = form.querySelector('[name="'+k+'"]');
            if (el) el.value = v ?? '';
        });
        const q = parseFloat(cat.quantita_contrattuale||0);
        const p = parseFloat(cat.prezzo_unitario||0);
        document.getElementById('catImportoCalc').textContent = Format.euro(q*p);
    } catch(e) { UI.error(e.message); }
}

async function salvaCategoria() {
    const form = document.getElementById('categoriaForm');
    if (!form.checkValidity()) { form.classList.add('was-validated'); return; }
    const data = serializeForm(form);
    data.id = document.getElementById('catId').value || null;
    data.commessa_id = document.getElementById('catCommessaId').value;
    UI.showLoader();
    try {
        if (data.id) await API.put('/api/sal.php?action=categoria', data);
        else await API.post('/api/sal.php?action=categoria', data);
        UI.success('Categoria salvata');
        bootstrap.Modal.getInstance(document.getElementById('categoriaModal')).hide();
        loadContabilita();
    } catch(e) {
        document.getElementById('catFormErrors').innerHTML =
            `<div class="alert alert-danger">${escapeHtml(e.message)}</div>`;
    } finally { UI.hideLoader(); }
}

// ==============================
// CRUD Varianti
// ==============================
function openCreateVariante() {
    if (!_commessaId) { UI.warning('Seleziona prima una commessa'); return; }
    document.getElementById('varId').value = '';
    document.getElementById('varianteForm').reset();
    document.getElementById('varFormErrors').innerHTML = '';
    new bootstrap.Modal(document.getElementById('varianteModal')).show();
}

async function editVariante(id) {
    const modal = new bootstrap.Modal(document.getElementById('varianteModal'));
    modal.show();
    try {
        const res = await API.get('/api/sal.php?action=variante&id=' + id);
        const v = res.data;
        document.getElementById('varId').value = v.id;
        const form = document.getElementById('varianteForm');
        Object.entries(v).forEach(([k,val]) => {
            const el = form.querySelector('[name="'+k+'"]');
            if (el) el.value = val ?? '';
        });
    } catch(e) { UI.error(e.message); }
}

async function salvaVariante() {
    const form = document.getElementById('varianteForm');
    if (!form.checkValidity()) { form.classList.add('was-validated'); return; }
    const data = serializeForm(form);
    data.id = document.getElementById('varId').value || null;
    data.commessa_id = _commessaId;
    UI.showLoader();
    try {
        if (data.id) await API.put('/api/sal.php?action=variante', data);
        else await API.post('/api/sal.php?action=variante', data);
        UI.success('Variante salvata');
        bootstrap.Modal.getInstance(document.getElementById('varianteModal')).hide();
        loadVarianti();
    } catch(e) {
        document.getElementById('varFormErrors').innerHTML =
            `<div class="alert alert-danger">${escapeHtml(e.message)}</div>`;
    } finally { UI.hideLoader(); }
}
JS;
include __DIR__ . '/../components/footer.php';
