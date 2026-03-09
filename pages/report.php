<?php
/**
 * Pagina: Report e Statistiche
 */
define('APP_INIT', true);
require_once __DIR__ . '/../php/bootstrap.php';
Auth::require();
if (!Auth::can('report.read')) {
    header('Location: ' . APP_URL . '/pages/dashboard.php');
    exit;
}

$pageTitle  = 'Report';
$activeMenu = 'report';
$extraScripts = ['js/charts.js'];
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/sidebar.php';
?>

<div class="container-fluid px-4 py-3">

  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h1 class="h4 mb-1 fw-bold">Report & Statistiche</h1>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
          <li class="breadcrumb-item"><a href="<?= APP_URL ?>/pages/dashboard.php">Dashboard</a></li>
          <li class="breadcrumb-item active">Report</li>
        </ol>
      </nav>
    </div>
  </div>

  <!-- Report type nav -->
  <ul class="nav nav-tabs mb-4" id="reportTabs">
    <li class="nav-item">
      <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabRiepilogo">
        <i class="bi bi-bar-chart-line me-1"></i>Riepilogo Portfolio
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabAvanzamento">
        <i class="bi bi-graph-up-arrow me-1"></i>Avanzamento
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabSal">
        <i class="bi bi-receipt-cutoff me-1"></i>SAL / Contabilità
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabScadenze">
        <i class="bi bi-calendar-event me-1"></i>Scadenze
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabGantt">
        <i class="bi bi-bar-chart-steps me-1"></i>Esporta Gantt
      </button>
    </li>
  </ul>

  <div class="tab-content">

    <!-- ===== TAB RIEPILOGO PORTFOLIO ===== -->
    <div class="tab-pane fade show active" id="tabRiepilogo">
      <div class="row g-3 mb-4" id="portfolioKpi">
        <?php foreach (['kpiTotCommesse','kpiInEsecuzione','kpiValore','kpiMediaAvanz'] as $k): ?>
        <div class="col-6 col-lg-3">
          <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-4">
              <h3 class="fw-bold mb-0 skeleton-text" id="<?= $k ?>">—</h3>
              <small class="text-muted" id="<?= $k ?>_label">Caricamento...</small>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="row g-3">
        <div class="col-lg-6">
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Commesse per Stato</div>
            <div class="card-body"><canvas id="chartStatiPortfolio" height="220"></canvas></div>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Valore per Stazione Appaltante</div>
            <div class="card-body"><canvas id="chartValoreSA" height="220"></canvas></div>
          </div>
        </div>
        <div class="col-12">
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
              <span class="fw-semibold">Tutte le Commesse</span>
              <button class="btn btn-outline-secondary btn-sm" onclick="exportCsv('riepilogo')">
                <i class="bi bi-download me-1"></i>Esporta CSV
              </button>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0" id="portfolioTable">
                  <thead class="table-light">
                    <tr>
                      <th class="ps-3">Codice</th>
                      <th>Oggetto</th>
                      <th>Stato</th>
                      <th>Importo</th>
                      <th>Avanzamento</th>
                      <th>Scostamento</th>
                      <th>RUP</th>
                    </tr>
                  </thead>
                  <tbody id="portfolioBody">
                    <tr><td colspan="7" class="text-center py-4 text-muted">
                      <div class="spinner-border spinner-border-sm me-2"></div></td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== TAB AVANZAMENTO ===== -->
    <div class="tab-pane fade" id="tabAvanzamento">
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">
          <div class="row g-2 align-items-center">
            <div class="col-md-4">
              <select class="form-select form-select-sm" id="avanzCommessa">
                <option value="">— Seleziona Commessa —</option>
              </select>
            </div>
            <div class="col-md-auto">
              <button class="btn btn-primary btn-sm" id="btnCaricaAvanzamento">
                <i class="bi bi-graph-up-arrow me-1"></i>Genera Report
              </button>
            </div>
            <div class="col-md-auto ms-md-auto">
              <button class="btn btn-outline-secondary btn-sm" onclick="exportCsv('avanzamento')">
                <i class="bi bi-download me-1"></i>Esporta CSV
              </button>
            </div>
          </div>
        </div>
      </div>

      <div id="avanzamentoResult" class="d-none">
        <div class="row g-3 mb-3" id="avanzKpi"></div>
        <div class="row g-3">
          <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-header bg-white fw-semibold">Avanzamento per Fase</div>
              <div class="card-body"><canvas id="chartPerFase" height="240"></canvas></div>
            </div>
          </div>
          <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-header bg-white fw-semibold">Tasks</div>
              <div class="card-body p-0">
                <div class="table-responsive" style="max-height:400px;overflow-y:auto">
                  <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light sticky-top">
                      <tr>
                        <th class="ps-3">WBS</th><th>Nome</th><th>Stato</th>
                        <th>%</th><th>Fine Prev.</th><th>Scostam.</th>
                      </tr>
                    </thead>
                    <tbody id="avanzTasksBody"></tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div id="avanzPlaceholder" class="text-center py-5 text-muted">
        <i class="bi bi-graph-up-arrow fs-1 d-block mb-2 opacity-25"></i>
        Seleziona una commessa e premi "Genera Report"
      </div>
    </div>

    <!-- ===== TAB SAL ===== -->
    <div class="tab-pane fade" id="tabSal">
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">
          <div class="row g-2 align-items-center">
            <div class="col-md-4">
              <select class="form-select form-select-sm" id="salCommessa">
                <option value="">— Seleziona Commessa —</option>
              </select>
            </div>
            <div class="col-md-auto">
              <button class="btn btn-primary btn-sm" id="btnCaricaSal">
                <i class="bi bi-receipt-cutoff me-1"></i>Genera Report SAL
              </button>
            </div>
            <div class="col-md-auto">
              <button class="btn btn-outline-success btn-sm" id="btnCaricaCosti">
                <i class="bi bi-currency-euro me-1"></i>Report Costi
              </button>
            </div>
            <div class="col-md-auto ms-md-auto">
              <button class="btn btn-outline-secondary btn-sm" onclick="exportCsv('pm_sal')">
                <i class="bi bi-download me-1"></i>CSV
              </button>
            </div>
          </div>
        </div>
      </div>

      <div id="salResult" class="d-none">
        <div class="row g-3 mb-3" id="salKpi"></div>
        <div class="row g-3">
          <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
              <div class="card-header bg-white fw-semibold">Andamento SAL</div>
              <div class="card-body"><canvas id="chartSalReport" height="220"></canvas></div>
            </div>
          </div>
          <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
              <div class="card-header bg-white fw-semibold">Stato Liquidazione</div>
              <div class="card-body"><canvas id="chartSalLiquidazione" height="220"></canvas></div>
            </div>
          </div>
          <div class="col-12">
            <div class="card border-0 shadow-sm">
              <div class="card-header bg-white fw-semibold">Dettaglio SAL</div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th class="ps-3">N° SAL</th><th>Periodo</th><th>Importo</th>
                        <th>Cumulato</th><th>Ritenuta</th><th>Netto</th><th>%</th><th>Stato</th>
                      </tr>
                    </thead>
                    <tbody id="salTableBody"></tbody>
                    <tfoot id="salTableFoot" class="table-light fw-semibold"></tfoot>
                  </table>
                </div>
              </div>
            </div>
          </div>
          <div class="col-12" id="costiResult" style="display:none">
            <div class="card border-0 shadow-sm">
              <div class="card-header bg-white fw-semibold">Analisi Costi per Categoria</div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th class="ps-3">Cod.</th><th>Descrizione</th><th>U.M.</th>
                        <th>Q.tà Contr.</th><th>Prezzo Un.</th><th>Imp. Contr.</th>
                        <th>Q.tà Eseg.</th><th>Imp. Eseg.</th><th>%</th>
                      </tr>
                    </thead>
                    <tbody id="costiTableBody"></tbody>
                    <tfoot id="costiTableFoot" class="table-light fw-semibold"></tfoot>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div id="salPlaceholder" class="text-center py-5 text-muted">
        <i class="bi bi-receipt fs-1 d-block mb-2 opacity-25"></i>
        Seleziona una commessa e scegli il tipo di report
      </div>
    </div>

    <!-- ===== TAB SCADENZE ===== -->
    <div class="tab-pane fade" id="tabScadenze">
      <div class="d-flex justify-content-end mb-3">
        <button class="btn btn-outline-secondary btn-sm" onclick="exportCsv('pm_scadenze')">
          <i class="bi bi-download me-1"></i>Esporta CSV
        </button>
      </div>
      <div class="row g-3 mb-3" id="scadenzeKpi"></div>
      <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-3">Urgenza</th><th>Commessa</th><th>Descrizione</th>
                  <th>Tipo</th><th>Data</th><th>Giorni</th><th>Responsabile</th>
                </tr>
              </thead>
              <tbody id="scadenzeReportBody">
                <tr><td colspan="7" class="text-center py-4 text-muted">
                  <div class="spinner-border spinner-border-sm me-2"></div></td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== TAB GANTT EXPORT ===== -->
    <div class="tab-pane fade" id="tabGantt">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <h6 class="fw-semibold mb-3">Esporta dati Gantt</h6>
          <div class="row g-3">
            <div class="col-md-5">
              <label class="form-label">Commessa</label>
              <select class="form-select" id="ganttCommessa">
                <option value="">— Seleziona —</option>
              </select>
            </div>
            <div class="col-md-auto align-self-end">
              <button class="btn btn-primary" id="btnEsportaGantt">
                <i class="bi bi-download me-1"></i>Scarica JSON
              </button>
              <a href="#" class="btn btn-outline-primary ms-2" id="btnApriGantt">
                <i class="bi bi-bar-chart-steps me-1"></i>Apri Gantt
              </a>
            </div>
          </div>
          <div class="mt-4 alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            Il file JSON esportato è compatibile con Microsoft Project, Primavera e altri strumenti di PM.
            Contiene pm_tasks, dipendenze, date e percentuali di avanzamento.
          </div>
        </div>
      </div>
    </div>

  </div><!-- /tab-content -->
</div>

<?php
$inlineScript = <<<'JS'
let _portfolioData = null;
let _salData       = null;
let _scadenzeData  = null;
let _avanzData     = null;

document.addEventListener('DOMContentLoaded', () => {
    loadSelectOptions();
    loadPortfolio();
    loadScadenzeReport();

    document.querySelectorAll('#reportTabs .nav-link').forEach(btn => {
        btn.addEventListener('click', function() {
            const t = this.getAttribute('data-bs-target');
            if (t === '#tabRiepilogo' && !_portfolioData) loadPortfolio();
            if (t === '#tabScadenze' && !_scadenzeData) loadScadenzeReport();
        });
    });

    document.getElementById('btnCaricaAvanzamento').addEventListener('click', () => {
        const id = parseInt(document.getElementById('avanzCommessa').value);
        if (!id) { UI.warning('Seleziona una commessa'); return; }
        loadAvanzamento(id);
    });
    document.getElementById('btnCaricaSal').addEventListener('click', () => {
        const id = parseInt(document.getElementById('salCommessa').value);
        if (!id) { UI.warning('Seleziona una commessa'); return; }
        loadSalReport(id);
    });
    document.getElementById('btnCaricaCosti').addEventListener('click', () => {
        const id = parseInt(document.getElementById('salCommessa').value);
        if (!id) { UI.warning('Seleziona una commessa'); return; }
        loadCostiReport(id);
    });
    document.getElementById('btnEsportaGantt').addEventListener('click', esportaGantt);
    document.getElementById('ganttCommessa').addEventListener('change', function() {
        const link = document.getElementById('btnApriGantt');
        link.href = this.value ? (API.getAppUrl() + '/pages/cronoprogramma.php?commessa_id=' + this.value) : '#';
    });
});

async function loadSelectOptions() {
    const res = await API.get('/api/pm_commesse.php?per_page=200&sort_by=codice');
    const list = res.data || [];
    ['avanzCommessa','salCommessa','ganttCommessa'].forEach(selId => {
        const sel = document.getElementById(selId);
        list.forEach(c => sel.insertAdjacentHTML('beforeend',
            `<option value="${c.id}">${escapeHtml(c.codice_commessa)} — ${escapeHtml(c.oggetto.substring(0,50))}</option>`));
    });
}

// ============================================================
// PORTFOLIO
// ============================================================
async function loadPortfolio() {
    try {
        const res = await API.get('/api/reports.php?tipo=riepilogo');
        _portfolioData = res;
        const stats = res.statistiche || {};

        document.getElementById('kpiTotCommesse').textContent = stats.totale_commesse ?? '—';
        document.getElementById('kpiTotCommesse_label').textContent = 'Totale Commesse';
        document.getElementById('kpiInEsecuzione').textContent = stats.in_esecuzione ?? '—';
        document.getElementById('kpiInEsecuzione_label').textContent = 'In Esecuzione';
        document.getElementById('kpiValore').textContent = res.valore_totale_fmt ?? '—';
        document.getElementById('kpiValore_label').textContent = 'Valore Portfolio';
        document.getElementById('kpiMediaAvanz').textContent =
            (parseFloat(stats.media_avanzamento) || 0).toFixed(1) + '%';
        document.getElementById('kpiMediaAvanz_label').textContent = 'Avanz. Medio';

        // Fetch pm_commesse list for table + charts
        const resC = await API.get('/api/reports.php?tipo=avanzamento');
        const pm_commesse = resC.pm_commesse || [];
        renderPortfolioTable(pm_commesse);

        // Charts
        const statiCount = {};
        const valorePerSA = {};
        pm_commesse.forEach(c => {
            statiCount[c.stato] = (statiCount[c.stato] || 0) + 1;
            const sa = c.rup_nominativo || 'N/D';
            valorePerSA[sa] = (valorePerSA[sa] || 0) + parseFloat(c.importo_contrattuale || 0);
        });

        chartStatoCommesse('chartStatiPortfolio',
            Object.keys(statiCount), Object.values(statiCount));

        // Top 8 SA by value
        const saSorted = Object.entries(valorePerSA).sort((a,b)=>b[1]-a[1]).slice(0,8);
        chartValoreStato('chartValoreSA',
            saSorted.map(x=>x[0]), saSorted.map(x=>x[1]));

    } catch(e) { UI.error('Errore report: ' + e.message); }
}

function renderPortfolioTable(pm_commesse) {
    const tbody = document.getElementById('portfolioBody');
    if (!pm_commesse.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted">Nessuna commessa</td></tr>';
        return;
    }
    tbody.innerHTML = pm_commesse.map(c => {
        const sc = parseInt(c.scostamento_giorni) || 0;
        const scCls = sc > 0 ? 'text-danger' : sc < 0 ? 'text-success' : 'text-muted';
        return `<tr>
            <td class="ps-3 font-monospace small fw-semibold">${escapeHtml(c.codice_commessa)}</td>
            <td class="small" style="max-width:260px">${escapeHtml(c.oggetto?.substring(0,60)??'')}</td>
            <td>${Format.badgeStato(c.stato)}</td>
            <td class="small">${Format.euro(c.importo_contrattuale)}</td>
            <td style="min-width:100px">
                <div class="progress" style="height:5px">
                    <div class="progress-bar" style="width:${c.percentuale_avanzamento??0}%"></div>
                </div>
                <small>${parseFloat(c.percentuale_avanzamento||0).toFixed(0)}%</small>
            </td>
            <td class="small ${scCls}">${sc > 0 ? '+'+sc : sc} gg</td>
            <td class="small text-muted">${escapeHtml(c.rup_nominativo??'—')}</td>
        </tr>`;
    }).join('');
}

// ============================================================
// AVANZAMENTO
// ============================================================
async function loadAvanzamento(commessaId) {
    document.getElementById('avanzPlaceholder').classList.add('d-none');
    document.getElementById('avanzamentoResult').classList.remove('d-none');
    try {
        const res = await API.get('/api/reports.php?tipo=avanzamento&commessa_id=' + commessaId);
        _avanzData = res;
        const r = res.riepilogo || {};
        document.getElementById('avanzKpi').innerHTML = [
            {label:'Task Totali',    val:r.totale_tasks??0,  cls:'primary'},
            {label:'Completati',     val:r.completati??0,    cls:'success'},
            {label:'In Ritardo',     val:r.in_ritardo??0,    cls:'danger'},
            {label:'Avanzamento',    val:(parseFloat(r.avanzamento||0).toFixed(1)+'%'), cls:'info'},
        ].map(k => `<div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <h3 class="fw-bold text-${k.cls} mb-0">${k.val}</h3>
                <small class="text-muted">${k.label}</small>
            </div></div>`).join('');

        // Chart per fase
        const fasi = res.per_fase || [];
        if (fasi.length) {
            chartCostiCategorie('chartPerFase',
                fasi.map(f => f.fase ?? 'N/D'),
                [{label:'Completati', data:fasi.map(f=>f.completati??0)},
                 {label:'In Ritardo', data:fasi.map(f=>f.in_ritardo??0)}]);
        }

        // Table pm_tasks
        const pm_tasks = res.pm_tasks || [];
        document.getElementById('avanzTasksBody').innerHTML = pm_tasks.map(t => {
            const sc = parseInt(t.scostamento_gg) || 0;
            return `<tr>
                <td class="ps-3 font-monospace small">${escapeHtml(t.codice_wbs)}</td>
                <td class="small">${escapeHtml(t.nome)}</td>
                <td>${Format.badgeStato(t.stato)}</td>
                <td><div class="progress" style="height:5px"><div class="progress-bar" style="width:${t.percentuale_completamento??0}%"></div></div></td>
                <td class="small">${t.data_fine_prevista ? Format.date(t.data_fine_prevista) : '—'}</td>
                <td class="small ${sc>0?'text-danger':sc<0?'text-success':'text-muted'}">${sc>0?'+'+sc:sc} gg</td>
            </tr>`;
        }).join('');
    } catch(e) { UI.error(e.message); }
}

// ============================================================
// SAL REPORT
// ============================================================
async function loadSalReport(commessaId) {
    document.getElementById('salPlaceholder').classList.add('d-none');
    document.getElementById('salResult').classList.remove('d-none');
    try {
        const res = await API.get('/api/reports.php?tipo=pm_sal&commessa_id=' + commessaId);
        _salData = res;
        const t = res.totali || {};
        document.getElementById('salKpi').innerHTML = [
            {label:'Importo Base',     val:t.importo_base??'—',         cls:'secondary'},
            {label:'Approvato',        val:t.totale_approvato_fmt??'—',  cls:'primary'},
            {label:'Pagato',           val:t.totale_pagato_fmt??'—',     cls:'success'},
            {label:'% Liquidata',      val:(t.perc_liquidata??0)+'%',    cls:'info'},
        ].map(k => `<div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <h4 class="fw-bold text-${k.cls} mb-0">${k.val}</h4>
                <small class="text-muted">${k.label}</small>
            </div></div>`).join('');

        const pm_sal = res.pm_sal || [];
        // Charts
        chartSalAndamento('chartSalReport', pm_sal);

        // Liquidazione doughnut
        const pagato  = pm_sal.filter(s=>s.stato==='PAGATO').reduce((a,s)=>a+parseFloat(s.importo_netto||0),0);
        const residuo = Math.max(0, parseFloat((t.importo_base||'0').replace(/[^0-9.]/g,'')) - pagato);
        chartStatoCommesse('chartSalLiquidazione', ['Pagato','Residuo'], [pagato, residuo]);

        // Table
        document.getElementById('salTableBody').innerHTML = pm_sal.map(s => `<tr>
            <td class="ps-3 fw-semibold">SAL ${s.numero_sal}</td>
            <td class="small">${s.data_inizio_it??'—'} → ${s.data_fine_it??'—'}</td>
            <td>${s.importo_totale_fmt??'—'}</td>
            <td>${s.importo_cumulato_fmt??'—'}</td>
            <td class="text-muted">${s.ritenuta_garanzia_fmt??'—'}</td>
            <td class="fw-semibold">${s.importo_netto_fmt??'—'}</td>
            <td>${Format.percent(s.percentuale_avanzamento)}</td>
            <td>${Format.badgeStato(s.stato)}</td>
        </tr>`).join('');

        document.getElementById('salTableFoot').innerHTML = `<tr>
            <td colspan="2" class="ps-3">TOTALE</td>
            <td colspan="2"></td>
            <td></td>
            <td>${t.totale_pagato_fmt??'—'}</td>
            <td colspan="2"></td>
        </tr>`;
    } catch(e) { UI.error(e.message); }
}

async function loadCostiReport(commessaId) {
    try {
        const res = await API.get('/api/reports.php?tipo=costi&commessa_id=' + commessaId);
        document.getElementById('costiResult').style.display = 'block';
        document.getElementById('costiTableBody').innerHTML = (res.categorie||[]).map(c => `<tr>
            <td class="ps-3 font-monospace small">${escapeHtml(c.codice??'')}</td>
            <td class="small">${escapeHtml(c.descrizione??'')}</td>
            <td class="small text-muted">${escapeHtml(c.unita_misura??'')}</td>
            <td class="small">${parseFloat(c.quantita_contrattuale||0).toFixed(2)}</td>
            <td class="small">${Format.euro(c.prezzo_unitario)}</td>
            <td>${c.importo_contrattuale_fmt??'—'}</td>
            <td class="small">${parseFloat(c.quantita_eseguita||0).toFixed(2)}</td>
            <td class="fw-semibold">${c.importo_eseguito_fmt??'—'}</td>
            <td>
                <div class="progress" style="height:5px"><div class="progress-bar" style="width:${c.perc_eseguita??0}%"></div></div>
                <small>${parseFloat(c.perc_eseguita||0).toFixed(1)}%</small>
            </td>
        </tr>`).join('');
        document.getElementById('costiTableFoot').innerHTML = `<tr>
            <td colspan="5" class="ps-3">TOTALE</td>
            <td>${res.totale_contrattuale_fmt??'—'}</td>
            <td></td>
            <td>${res.totale_eseguito_fmt??'—'}</td>
            <td>${parseFloat(res.perc_eseguita||0).toFixed(1)}%</td>
        </tr>`;
    } catch(e) { UI.error(e.message); }
}

// ============================================================
// SCADENZE REPORT
// ============================================================
async function loadScadenzeReport() {
    try {
        const res = await API.get('/api/reports.php?tipo=pm_scadenze');
        _scadenzeData = res;
        const r = res.riepilogo || {};
        document.getElementById('scadenzeKpi').innerHTML = [
            {label:'Totale Attive', val:r.totale??0,  cls:'primary'},
            {label:'Scadute',       val:r.scadute??0,  cls:'danger'},
            {label:'Critiche',      val:r.critiche??0, cls:'warning'},
        ].map(k => `<div class="col-4">
            <div class="card border-0 shadow-sm text-center py-3">
                <h3 class="fw-bold text-${k.cls} mb-0">${k.val}</h3>
                <small class="text-muted">${k.label}</small>
            </div></div>`).join('');

        document.getElementById('scadenzeReportBody').innerHTML = (res.pm_scadenze||[]).map(s => {
            const g = parseInt(s.giorni);
            const cls = g < 0 ? 'table-danger' : g<=7 ? 'table-warning' : '';
            const urgBadge = g < 0 ? '<span class="badge bg-danger">SCADUTA</span>' :
                g<=7 ? '<span class="badge bg-warning text-dark">CRITICA</span>' :
                g<=15 ? '<span class="badge bg-info text-dark">URGENTE</span>' :
                '<span class="badge bg-success">OK</span>';
            return `<tr class="${cls}">
                <td class="ps-3">${urgBadge}</td>
                <td class="small">${escapeHtml(s.codice_commessa??'')}</td>
                <td class="small">${escapeHtml(s.descrizione??'')}</td>
                <td><span class="badge bg-light text-dark">${escapeHtml(s.tipo??'')}</span></td>
                <td>${Format.date(s.data_scadenza)}</td>
                <td class="fw-semibold">${g<0?'Scaduta '+Math.abs(g)+'gg':g+' gg'}</td>
                <td class="small text-muted">${escapeHtml(s.responsabile??'—')}</td>
            </tr>`;
        }).join('');
    } catch(e) { UI.error(e.message); }
}

// ============================================================
// GANTT EXPORT
// ============================================================
async function esportaGantt() {
    const id = parseInt(document.getElementById('ganttCommessa').value);
    if (!id) { UI.warning('Seleziona una commessa'); return; }
    UI.showLoader();
    try {
        const res = await API.get('/api/reports.php?tipo=gantt&commessa_id=' + id);
        const blob = new Blob([JSON.stringify(res, null, 2)], {type:'application/json'});
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href = url;
        a.download = `gantt_${res.commessa?.codice_commessa ?? id}_${new Date().toISOString().slice(0,10)}.json`;
        a.click();
        URL.revokeObjectURL(url);
    } catch(e) { UI.error(e.message); }
    finally { UI.hideLoader(); }
}

// ============================================================
// CSV EXPORT
// ============================================================
function exportCsv(tipo) {
    let data = [];
    let filename = tipo + '_' + new Date().toISOString().slice(0,10) + '.csv';
    let headers = [];

    if (tipo === 'riepilogo' && _portfolioData) {
        // Already handled by API response — redirect to API
        window.open(API.getAppUrl() + '/api/reports.php?tipo=avanzamento&formato=csv', '_blank');
        return;
    }
    if (tipo === 'pm_sal' && _salData) {
        headers = ['N° SAL','Da','A','Importo Tot.','Cumulato','Ritenuta','Netto','%','Stato'];
        data = (_salData.pm_sal||[]).map(s => [
            's'+s.numero_sal, s.data_inizio_it, s.data_fine_it,
            s.importo_totale_fmt, s.importo_cumulato_fmt, s.ritenuta_garanzia_fmt,
            s.importo_netto_fmt, s.percentuale_avanzamento, s.stato
        ]);
    }
    if (tipo === 'pm_scadenze' && _scadenzeData) {
        headers = ['Urgenza','Commessa','Descrizione','Tipo','Data','Giorni','Responsabile'];
        data = (_scadenzeData.pm_scadenze||[]).map(s => {
            const g = parseInt(s.giorni);
            return [s.stato_urgenza, s.codice_commessa, s.descrizione, s.tipo,
                s.data_scadenza_it, g, s.responsabile];
        });
    }
    if (!data.length && !headers.length) {
        UI.warning('Nessun dato da esportare. Genera prima il report.');
        return;
    }

    const csv = [headers, ...data].map(row =>
        row.map(v => '"' + String(v??'').replace(/"/g,'""') + '"').join(',')).join('\n');
    const blob = new Blob(['\uFEFF' + csv], {type:'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob);
    const a   = document.createElement('a');
    a.href = url; a.download = filename; a.click();
    URL.revokeObjectURL(url);
}
JS;
include __DIR__ . '/../components/footer.php';
