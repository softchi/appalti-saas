<?php
/**
 * Pagina: Dettaglio Commessa
 */
define('APP_INIT', true);
require_once __DIR__ . '/../php/bootstrap.php';
Auth::require();
if (!Auth::can('commesse.read')) {
    header('Location: ' . APP_URL . '/pages/dashboard.php');
    exit;
}

$commessaId = sanitizeInt($_GET['id'] ?? null, 1);
if (!$commessaId) {
    header('Location: ' . APP_URL . '/pages/commesse.php');
    exit;
}

// Fetch basic commessa data for title
$commessa = Database::fetchOne(
    'SELECT c.codice_commessa, c.oggetto, c.stato, c.percentuale_avanzamento,
            c.importo_contrattuale, c.data_fine_prevista, c.scostamento_giorni,
            c.cig, c.cup, c.data_consegna,
            CONCAT(rup.cognome," ",rup.nome) AS rup_nominativo
     FROM commesse c
     LEFT JOIN utenti rup ON rup.id = c.rup_id
     WHERE c.id = :id',
    [':id' => $commessaId]
);

if (!$commessa) {
    header('Location: ' . APP_URL . '/pages/commesse.php');
    exit;
}

$pageTitle  = $commessa['codice_commessa'] . ' — ' . mb_substr($commessa['oggetto'], 0, 50);
$activeMenu = 'commesse';
$extraScripts = ['js/charts.js', 'js/gantt.js'];
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/sidebar.php';
?>

<div class="container-fluid px-4 py-3">

  <!-- Breadcrumb + header -->
  <div class="d-flex align-items-start justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1 small">
          <li class="breadcrumb-item"><a href="<?= APP_URL ?>/pages/dashboard.php">Dashboard</a></li>
          <li class="breadcrumb-item"><a href="<?= APP_URL ?>/pages/commesse.php">Commesse</a></li>
          <li class="breadcrumb-item active"><?= e($commessa['codice_commessa']) ?></li>
        </ol>
      </nav>
      <h1 class="h4 fw-bold mb-0">
        <?= e($commessa['oggetto']) ?>
        <span id="statoHeaderBadge"><?= Format\badgeStato($commessa['stato']) ?></span>
      </h1>
      <small class="text-muted">
        CIG: <span class="font-monospace"><?= e($commessa['cig'] ?? '—') ?></span>
        <?php if ($commessa['cup']): ?>
          &nbsp;|&nbsp; CUP: <span class="font-monospace"><?= e($commessa['cup']) ?></span>
        <?php endif; ?>
        &nbsp;|&nbsp; RUP: <?= e($commessa['rup_nominativo'] ?? '—') ?>
      </small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <?php if (Auth::can('commesse.update')): ?>
      <button class="btn btn-outline-secondary btn-sm" id="btnEditCommessa">
        <i class="bi bi-pencil me-1"></i>Modifica
      </button>
      <?php endif; ?>
      <a href="<?= APP_URL ?>/pages/cronoprogramma.php?commessa_id=<?= $commessaId ?>"
         class="btn btn-outline-primary btn-sm">
        <i class="bi bi-bar-chart-steps me-1"></i>Cronoprogramma
      </a>
      <a href="<?= APP_URL ?>/pages/sal.php?commessa_id=<?= $commessaId ?>"
         class="btn btn-outline-success btn-sm">
        <i class="bi bi-receipt-cutoff me-1"></i>SAL
      </a>
      <a href="<?= APP_URL ?>/pages/documenti.php?commessa_id=<?= $commessaId ?>"
         class="btn btn-outline-info btn-sm">
        <i class="bi bi-folder2-open me-1"></i>Documenti
      </a>
    </div>
  </div>

  <!-- KPI Row -->
  <div class="row g-3 mb-4" id="kpiRow">
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100 border-start border-4 border-primary">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="rounded-circle bg-primary bg-opacity-10 p-3 flex-shrink-0">
            <i class="bi bi-currency-euro text-primary fs-4"></i>
          </div>
          <div>
            <small class="text-muted">Importo Contrattuale</small>
            <h5 class="mb-0 fw-bold" id="kpiImporto">—</h5>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100 border-start border-4 border-success">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="rounded-circle bg-success bg-opacity-10 p-3 flex-shrink-0">
            <i class="bi bi-check2-circle text-success fs-4"></i>
          </div>
          <div>
            <small class="text-muted">Avanzamento</small>
            <h5 class="mb-0 fw-bold" id="kpiAvanzamento">—</h5>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100 border-start border-4 border-warning">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="rounded-circle bg-warning bg-opacity-10 p-3 flex-shrink-0">
            <i class="bi bi-calendar-event text-warning fs-4"></i>
          </div>
          <div>
            <small class="text-muted">Scostamento</small>
            <h5 class="mb-0 fw-bold" id="kpiScostamento">—</h5>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100 border-start border-4 border-info">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="rounded-circle bg-info bg-opacity-10 p-3 flex-shrink-0">
            <i class="bi bi-receipt text-info fs-4"></i>
          </div>
          <div>
            <small class="text-muted">Liquidato / Totale</small>
            <h5 class="mb-0 fw-bold" id="kpiLiquidato">—</h5>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Nav tabs principali -->
  <ul class="nav nav-tabs mb-3" id="detailTabs">
    <li class="nav-item">
      <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabRiepilogo">
        <i class="bi bi-speedometer2 me-1"></i>Riepilogo
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabTasks">
        <i class="bi bi-list-task me-1"></i>Tasks
        <span class="badge bg-secondary ms-1" id="badgeTasks">0</span>
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabSal">
        <i class="bi bi-receipt-cutoff me-1"></i>SAL
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabScadenze">
        <i class="bi bi-calendar-event me-1"></i>Scadenze
        <span class="badge bg-danger ms-1 d-none" id="badgeScadenze"></span>
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabTeam">
        <i class="bi bi-people me-1"></i>Team
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabDocs">
        <i class="bi bi-folder2-open me-1"></i>Documenti
      </button>
    </li>
  </ul>

  <div class="tab-content">

    <!-- ===== TAB RIEPILOGO ===== -->
    <div class="tab-pane fade show active" id="tabRiepilogo">
      <div class="row g-3">
        <!-- Avanzamento gauge + dati chiave -->
        <div class="col-lg-4">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold border-bottom-0 pb-0">
              Avanzamento Lavori
            </div>
            <div class="card-body text-center">
              <canvas id="chartGauge" height="160"></canvas>
              <div class="mt-3">
                <div class="row text-center g-2">
                  <div class="col-6">
                    <small class="text-muted d-block">Fine Prevista</small>
                    <strong id="infoFinePrevista">—</strong>
                  </div>
                  <div class="col-6">
                    <small class="text-muted d-block">Fine Effettiva</small>
                    <strong id="infoFineEffettiva">—</strong>
                  </div>
                  <div class="col-6">
                    <small class="text-muted d-block">Task Totali</small>
                    <strong id="infoTaskTotali">—</strong>
                  </div>
                  <div class="col-6">
                    <small class="text-muted d-block">Task in Ritardo</small>
                    <strong class="text-danger" id="infoTaskRitardo">—</strong>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Dati anagrafici -->
        <div class="col-lg-4">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold border-bottom-0 pb-0">
              Dati Contrattuali
            </div>
            <div class="card-body">
              <dl class="row small mb-0" id="datiContrattuali">
                <dt class="col-5 text-muted">Codice</dt>
                <dd class="col-7 font-monospace" id="dcCodice">—</dd>
                <dt class="col-5 text-muted">CIG</dt>
                <dd class="col-7 font-monospace" id="dcCig">—</dd>
                <dt class="col-5 text-muted">CUP</dt>
                <dd class="col-7 font-monospace" id="dcCup">—</dd>
                <dt class="col-5 text-muted">Impresa</dt>
                <dd class="col-7" id="dcImpresa">—</dd>
                <dt class="col-5 text-muted">S.A.</dt>
                <dd class="col-7" id="dcSA">—</dd>
                <dt class="col-5 text-muted">Importo</dt>
                <dd class="col-7 fw-semibold" id="dcImporto">—</dd>
                <dt class="col-5 text-muted">Sicurezza</dt>
                <dd class="col-7" id="dcSicurezza">—</dd>
                <dt class="col-5 text-muted">Ribasso</dt>
                <dd class="col-7" id="dcRibasso">—</dd>
                <dt class="col-5 text-muted">Categoria SOA</dt>
                <dd class="col-7" id="dcSoa">—</dd>
                <dt class="col-5 text-muted">Consegna Lavori</dt>
                <dd class="col-7" id="dcConsegna">—</dd>
              </dl>
            </div>
          </div>
        </div>

        <!-- Andamento SAL -->
        <div class="col-lg-4">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold border-bottom-0 pb-0">
              Andamento SAL
            </div>
            <div class="card-body">
              <canvas id="chartSal" height="160"></canvas>
              <div class="mt-2 text-center">
                <small class="text-muted">Liquidato: </small>
                <strong id="salLiquidato">—</strong>
                <small class="text-muted"> / </small>
                <strong id="salTotale">—</strong>
              </div>
            </div>
          </div>
        </div>

        <!-- Task per stato -->
        <div class="col-lg-6">
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold border-bottom-0 pb-0">
              Tasks per Stato
            </div>
            <div class="card-body">
              <div id="tasksStato" class="row g-2 text-center">
                <div class="col-12 text-muted py-3 skeleton-text"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Scadenze imminenti -->
        <div class="col-lg-6">
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold border-bottom-0 pb-0 d-flex justify-content-between">
              Prossime Scadenze
              <a href="<?= APP_URL ?>/pages/scadenze.php?commessa_id=<?= $commessaId ?>"
                 class="btn btn-link btn-sm p-0 small">Vedi tutte</a>
            </div>
            <div class="card-body p-0">
              <ul class="list-group list-group-flush" id="prossimeScadenze">
                <li class="list-group-item text-muted text-center py-3 skeleton-text"></li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Note -->
        <div class="col-12">
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold border-bottom-0 pb-0">Note</div>
            <div class="card-body">
              <p class="mb-0 text-muted" id="noteCommessa"><em>Nessuna nota.</em></p>
            </div>
          </div>
        </div>
      </div>
    </div><!-- /tabRiepilogo -->

    <!-- ===== TAB TASKS ===== -->
    <div class="tab-pane fade" id="tabTasks">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0 fw-semibold">Tasks della Commessa</h6>
        <?php if (Auth::can('tasks.create')): ?>
        <a href="<?= APP_URL ?>/pages/cronoprogramma.php?commessa_id=<?= $commessaId ?>"
           class="btn btn-primary btn-sm">
          <i class="bi bi-bar-chart-steps me-1"></i>Apri Cronoprogramma
        </a>
        <?php endif; ?>
      </div>
      <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-3">WBS</th>
                  <th>Nome Task</th>
                  <th>Tipo</th>
                  <th>Responsabile</th>
                  <th>Avanzamento</th>
                  <th>Stato</th>
                  <th>Fine Prevista</th>
                </tr>
              </thead>
              <tbody id="tasksBody">
                <tr><td colspan="7" class="text-center py-4 text-muted">
                  <div class="spinner-border spinner-border-sm me-2"></div>Caricamento...
                </td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== TAB SAL ===== -->
    <div class="tab-pane fade" id="tabSal">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0 fw-semibold">Stato Avanzamento Lavori</h6>
        <a href="<?= APP_URL ?>/pages/sal.php?commessa_id=<?= $commessaId ?>"
           class="btn btn-success btn-sm">
          <i class="bi bi-receipt-cutoff me-1"></i>Gestisci SAL
        </a>
      </div>
      <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-3">N°</th>
                  <th>Periodo</th>
                  <th>Importo Totale</th>
                  <th>Cumulato</th>
                  <th>% Avanz.</th>
                  <th>Stato</th>
                  <th>Data Pagamento</th>
                </tr>
              </thead>
              <tbody id="salBody">
                <tr><td colspan="7" class="text-center py-4 text-muted">
                  <div class="spinner-border spinner-border-sm me-2"></div>Caricamento...
                </td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== TAB SCADENZE ===== -->
    <div class="tab-pane fade" id="tabScadenze">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0 fw-semibold">Scadenze</h6>
        <a href="<?= APP_URL ?>/pages/scadenze.php?commessa_id=<?= $commessaId ?>"
           class="btn btn-outline-primary btn-sm">
          <i class="bi bi-calendar-plus me-1"></i>Gestisci Scadenze
        </a>
      </div>
      <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-3">Tipo</th>
                  <th>Descrizione</th>
                  <th>Data Scadenza</th>
                  <th>Giorni</th>
                  <th>Responsabile</th>
                  <th>Stato</th>
                </tr>
              </thead>
              <tbody id="scadenzeBody">
                <tr><td colspan="6" class="text-center py-4 text-muted">
                  <div class="spinner-border spinner-border-sm me-2"></div>Caricamento...
                </td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== TAB TEAM ===== -->
    <div class="tab-pane fade" id="tabTeam">
      <div class="row g-3" id="teamGrid">
        <div class="col-12 text-center py-4 text-muted">
          <div class="spinner-border spinner-border-sm me-2"></div>Caricamento...
        </div>
      </div>
    </div>

    <!-- ===== TAB DOCUMENTI ===== -->
    <div class="tab-pane fade" id="tabDocs">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0 fw-semibold">Ultimi documenti</h6>
        <a href="<?= APP_URL ?>/pages/documenti.php?commessa_id=<?= $commessaId ?>"
           class="btn btn-outline-primary btn-sm">
          <i class="bi bi-folder2-open me-1"></i>Archivio Completo
        </a>
      </div>
      <div class="row g-3" id="docsGrid">
        <div class="col-12 text-center py-4 text-muted">
          <div class="spinner-border spinner-border-sm me-2"></div>Caricamento...
        </div>
      </div>
    </div>

  </div><!-- /tab-content -->
</div>

<?php
$inlineScript = <<<JS
const COMMESSA_ID = {$commessaId};
const APP_URL_JS  = '{$_SERVER['SCRIPT_NAME']}'; // not used directly

document.addEventListener('DOMContentLoaded', () => {
    loadRiepilogo();

    // Lazy load tabs on first open
    document.getElementById('tabTasks')?.addEventListener('shown.bs.tab', loadTasks, {once: true});
    // Use event delegation on the nav tabs
    document.querySelectorAll('#detailTabs .nav-link').forEach(btn => {
        btn.addEventListener('click', function() {
            const target = this.getAttribute('data-bs-target');
            if (target === '#tabTasks')    loadTasks();
            if (target === '#tabSal')      loadSalTab();
            if (target === '#tabScadenze') loadScadenzeTab();
            if (target === '#tabTeam')     loadTeam();
            if (target === '#tabDocs')     loadDocs();
        });
    });
});

// ============================================================
// RIEPILOGO
// ============================================================
async function loadRiepilogo() {
    try {
        const res = await API.get('/api/commesse.php?id=' + COMMESSA_ID);
        const c   = res.data;
        // KPI
        document.getElementById('kpiImporto').textContent  = Format.euro(c.importo_contrattuale);
        document.getElementById('kpiAvanzamento').textContent = Format.percent(c.percentuale_avanzamento);
        const sc = parseInt(c.scostamento_giorni) || 0;
        const kpiSc = document.getElementById('kpiScostamento');
        kpiSc.textContent = sc > 0 ? `+${sc} gg` : sc === 0 ? 'In linea' : `${sc} gg`;
        kpiSc.classList.add(sc > 0 ? 'text-danger' : 'text-success');

        // Dati contrattuali
        document.getElementById('dcCodice').textContent  = c.codice_commessa;
        document.getElementById('dcCig').textContent     = c.cig ?? '—';
        document.getElementById('dcCup').textContent     = c.cup ?? '—';
        document.getElementById('dcImpresa').textContent = c.impresa_denominazione ?? '—';
        document.getElementById('dcSA').textContent      = c.stazione_appaltante ?? '—';
        document.getElementById('dcImporto').textContent = Format.euro(c.importo_contrattuale);
        document.getElementById('dcSicurezza').textContent = Format.euro(c.importo_sicurezza ?? 0);
        document.getElementById('dcRibasso').textContent = (parseFloat(c.ribasso_percentuale) || 0).toFixed(3) + '%';
        document.getElementById('dcSoa').textContent     = c.categoria_soa ? (c.categoria_soa + (c.classifica_soa ? ' – '+c.classifica_soa : '')) : '—';
        document.getElementById('dcConsegna').textContent = c.data_consegna ? Format.date(c.data_consegna) : '—';
        document.getElementById('infoFinePrevista').textContent  = c.data_fine_prevista ? Format.date(c.data_fine_prevista) : '—';
        document.getElementById('infoFineEffettiva').textContent = c.data_fine_effettiva ? Format.date(c.data_fine_effettiva) : 'In corso';
        document.getElementById('noteCommessa').textContent = c.note || 'Nessuna nota.';

        // Gauge avanzamento
        chartAvanzamentoCommessa('chartGauge', parseFloat(c.percentuale_avanzamento) || 0);

        // Task stats
        const stats = res.task_stats || {};
        document.getElementById('infoTaskTotali').textContent  = stats.totale ?? '—';
        document.getElementById('infoTaskRitardo').textContent = stats.in_ritardo ?? '0';
        document.getElementById('badgeTasks').textContent       = stats.totale ?? 0;
        renderTasksStato(stats);

        // SAL
        loadSalKpi();

        // Scadenze prossime
        loadProssimeScadenze();

    } catch(e) {
        UI.error('Errore caricamento: ' + e.message);
    }
}

function renderTasksStato(stats) {
    const wrap = document.getElementById('tasksStato');
    const items = [
        {label:'Non Iniziati', val:stats.non_iniziati??0, cls:'text-secondary'},
        {label:'In Corso',     val:stats.in_corso??0,     cls:'text-primary'},
        {label:'Completati',   val:stats.completati??0,   cls:'text-success'},
        {label:'In Ritardo',   val:stats.in_ritardo??0,   cls:'text-danger'},
        {label:'Sospesi',      val:stats.sospesi??0,      cls:'text-warning'},
    ];
    wrap.innerHTML = items.map(i => `
        <div class="col">
            <div class="p-2 rounded bg-light text-center">
                <h4 class="mb-0 fw-bold ${i.cls}">${i.val}</h4>
                <small class="text-muted">${i.label}</small>
            </div>
        </div>`).join('');
}

async function loadSalKpi() {
    try {
        const res = await API.get('/api/sal.php?commessa_id=' + COMMESSA_ID + '&per_page=20');
        const list = res.data || [];
        const meta = res.meta || {};
        document.getElementById('kpiLiquidato').textContent =
            (meta.totale_pagato_fmt ?? '—') + ' / ' + (meta.importo_base_fmt ?? '—');
        document.getElementById('salLiquidato').textContent = meta.totale_pagato_fmt ?? '—';
        document.getElementById('salTotale').textContent    = meta.importo_base_fmt ?? '—';
        if (list.length) chartSalAndamento('chartSal', list);
    } catch(e) {}
}

async function loadProssimeScadenze() {
    try {
        const res = await API.get('/api/scadenze.php?commessa_id=' + COMMESSA_ID + '&per_page=5&sort=data_scadenza');
        const items = res.data || [];
        const ul = document.getElementById('prossimeScadenze');
        if (!items.length) {
            ul.innerHTML = '<li class="list-group-item text-muted text-center py-3">Nessuna scadenza</li>';
            return;
        }
        // Show count of scadute
        const scadute = items.filter(s => parseInt(s.giorni) < 0).length;
        if (scadute > 0) {
            document.getElementById('badgeScadenze').textContent = scadute;
            document.getElementById('badgeScadenze').classList.remove('d-none');
        }
        ul.innerHTML = items.map(s => {
            const giorni = parseInt(s.giorni);
            const cls = giorni < 0 ? 'text-danger' : giorni <= 7 ? 'text-warning' : 'text-muted';
            const icon = giorni < 0 ? 'bi-alarm-fill' : 'bi-calendar-event';
            return `<li class="list-group-item d-flex justify-content-between align-items-center py-2">
                <div>
                    <i class="bi ${icon} me-2 ${cls}"></i>
                    <span class="small">${escapeHtml(s.descrizione ?? s.tipo)}</span>
                </div>
                <small class="${cls}">${Format.date(s.data_scadenza)}</small>
            </li>`;
        }).join('');
    } catch(e) {}
}

// ============================================================
// TABS LAZY LOAD
// ============================================================
async function loadTasks() {
    const tbody = document.getElementById('tasksBody');
    try {
        const res = await API.get('/api/tasks.php?commessa_id=' + COMMESSA_ID);
        const tasks = flattenTree(res.data || []);
        if (!tasks.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Nessun task</td></tr>';
            return;
        }
        tbody.innerHTML = tasks.map(t => {
            const indent = '&nbsp;'.repeat(t._depth * 4);
            const icon = t.tipo === 'MILESTONE' ? 'bi-diamond-fill text-warning' :
                         t.tipo === 'FASE' ? 'bi-folder-fill text-primary' : 'bi-check2-square';
            return `<tr>
                <td class="ps-3 font-monospace small">${escapeHtml(t.codice_wbs)}</td>
                <td>${indent}<i class="bi ${icon} me-1"></i>${escapeHtml(t.nome)}</td>
                <td><span class="badge bg-light text-dark">${t.tipo}</span></td>
                <td class="small text-muted">${escapeHtml(t.responsabile ?? '—')}</td>
                <td style="min-width:100px">
                    <div class="progress" style="height:6px">
                        <div class="progress-bar" style="width:${t.percentuale_completamento??0}%"></div>
                    </div>
                    <small>${t.percentuale_completamento??0}%</small>
                </td>
                <td>${Format.badgeStato(t.stato)}</td>
                <td class="small">${t.data_fine_prevista ? Format.date(t.data_fine_prevista) : '—'}</td>
            </tr>`;
        }).join('');
    } catch(e) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-danger text-center py-3">${escapeHtml(e.message)}</td></tr>`;
    }
}

function flattenTree(nodes, depth = 0) {
    let result = [];
    nodes.forEach(n => {
        n._depth = depth;
        result.push(n);
        if (n.subtasks?.length) result = result.concat(flattenTree(n.subtasks, depth + 1));
    });
    return result;
}

async function loadSalTab() {
    const tbody = document.getElementById('salBody');
    try {
        const res = await API.get('/api/sal.php?commessa_id=' + COMMESSA_ID + '&per_page=50');
        const list = res.data || [];
        if (!list.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Nessun SAL</td></tr>';
            return;
        }
        tbody.innerHTML = list.map(s => `<tr>
            <td class="ps-3 fw-semibold">SAL ${s.numero_sal}</td>
            <td class="small">${s.data_inizio ? Format.date(s.data_inizio) + ' → ' + Format.date(s.data_fine) : '—'}</td>
            <td>${Format.euro(s.importo_totale)}</td>
            <td>${Format.euro(s.importo_cumulato)}</td>
            <td>${Format.percent(s.percentuale_avanzamento)}</td>
            <td>${Format.badgeStato(s.stato)}</td>
            <td class="small">${s.data_pagamento ? Format.date(s.data_pagamento) : '—'}</td>
        </tr>`).join('');
    } catch(e) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-danger text-center py-3">${escapeHtml(e.message)}</td></tr>`;
    }
}

async function loadScadenzeTab() {
    const tbody = document.getElementById('scadenzeBody');
    try {
        const res = await API.get('/api/scadenze.php?commessa_id=' + COMMESSA_ID + '&per_page=50');
        const list = res.data || [];
        if (!list.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">Nessuna scadenza</td></tr>';
            return;
        }
        tbody.innerHTML = list.map(s => {
            const g = parseInt(s.giorni);
            const urgCls = g < 0 ? 'text-danger fw-bold' : g <= 7 ? 'text-warning fw-semibold' : 'text-muted';
            return `<tr>
                <td class="ps-3"><span class="badge bg-light text-dark">${escapeHtml(s.tipo ?? '—')}</span></td>
                <td>${escapeHtml(s.descrizione ?? '—')}</td>
                <td>${Format.date(s.data_scadenza)}</td>
                <td class="${urgCls}">${g < 0 ? 'Scaduta '+Math.abs(g)+'gg fa' : g === 0 ? 'Oggi' : g+' gg'}</td>
                <td class="small text-muted">${escapeHtml(s.responsabile ?? '—')}</td>
                <td>${Format.badgeStato(s.stato)}</td>
            </tr>`;
        }).join('');
    } catch(e) {}
}

async function loadTeam() {
    const grid = document.getElementById('teamGrid');
    try {
        const res = await API.get('/api/commesse.php?id=' + COMMESSA_ID + '&include=team');
        const members = res.team || [];
        if (!members.length) {
            grid.innerHTML = '<div class="col-12 text-muted text-center py-4">Nessun membro nel team</div>';
            return;
        }
        grid.innerHTML = members.map(m => `
            <div class="col-6 col-md-4 col-lg-3">
                <div class="card border-0 shadow-sm text-center p-3">
                    <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold mx-auto mb-2"
                         style="width:48px;height:48px;font-size:1.1rem">
                        ${escapeHtml(m.nome.charAt(0) + m.cognome.charAt(0)).toUpperCase()}
                    </div>
                    <h6 class="mb-0 small fw-semibold">${escapeHtml(m.cognome)} ${escapeHtml(m.nome)}</h6>
                    <small class="text-muted">${escapeHtml(m.ruolo_commessa ?? m.ruolo_nome ?? '')}</small>
                    <small class="text-muted d-block">${escapeHtml(m.email ?? '')}</small>
                </div>
            </div>`).join('');
    } catch(e) {
        grid.innerHTML = `<div class="col-12 text-danger text-center py-4">${escapeHtml(e.message)}</div>`;
    }
}

async function loadDocs() {
    const grid = document.getElementById('docsGrid');
    try {
        const res = await API.get('/api/documenti.php?commessa_id=' + COMMESSA_ID + '&per_page=12');
        const list = res.data || [];
        if (!list.length) {
            grid.innerHTML = '<div class="col-12 text-muted text-center py-4">Nessun documento</div>';
            return;
        }
        const iconMap = {pdf:'bi-file-pdf text-danger', doc:'bi-file-word text-primary',
            xls:'bi-file-excel text-success', img:'bi-file-image text-warning', default:'bi-file-earmark'};
        grid.innerHTML = list.map(d => {
            const ext = d.nome_file?.split('.').pop()?.toLowerCase() ?? '';
            const iconKey = ext === 'pdf' ? 'pdf' : ['doc','docx'].includes(ext) ? 'doc' :
                ['xls','xlsx'].includes(ext) ? 'xls' : ['jpg','png','gif'].includes(ext) ? 'img' : 'default';
            return `<div class="col-6 col-md-4 col-lg-2">
                <a href="${API.getAppUrl()}/api/documenti.php?action=download&id=${d.id}"
                   class="card border-0 shadow-sm text-center p-3 text-decoration-none h-100" target="_blank">
                    <i class="bi ${iconMap[iconKey]} fs-2 mb-2"></i>
                    <small class="text-dark fw-semibold text-truncate d-block">${escapeHtml(d.titolo ?? d.nome_file)}</small>
                    <small class="text-muted">${Format.date(d.data_documento ?? d.created_at)}</small>
                </a>
            </div>`;
        }).join('');
    } catch(e) {}
}
JS;
include __DIR__ . '/../components/footer.php';
?>
<?php
// Helper function for PHP-side badge rendering
namespace Format {
    function badgeStato(string $stato): string {
        $map = [
            'IN_ESECUZIONE' => 'success',
            'COMPLETATA'    => 'primary',
            'SOSPESA'       => 'warning',
            'IN_ATTESA'     => 'secondary',
            'ANNULLATA'     => 'danger',
            'EMESSO'        => 'info',
            'APPROVATO'     => 'success',
            'PAGATO'        => 'primary',
            'COMPLETATO'    => 'success',
            'IN_CORSO'      => 'primary',
            'IN_RITARDO'    => 'danger',
            'NON_INIZIATO'  => 'secondary',
            'ATTIVA'        => 'success',
        ];
        $cls = $map[$stato] ?? 'secondary';
        return '<span class="badge bg-'.$cls.'">'.htmlspecialchars($stato, ENT_QUOTES).'</span>';
    }
}
