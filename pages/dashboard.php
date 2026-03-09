<?php
/**
 * Dashboard principale
 */
define('APP_INIT', true);
require_once __DIR__ . '/../php/bootstrap.php';

Auth::require('pm_commesse.read');

$pageTitle  = 'Dashboard';
$activeMenu = 'dashboard';

$user       = Auth::user();
$extraScripts = [
    APP_URL . '/js/charts.js',
];

include COMPONENTS_PATH . '/header.php';
include COMPONENTS_PATH . '/sidebar.php';
?>

<div class="container-fluid px-0">

  <!-- Page Header -->
  <div class="page-header d-flex align-items-center justify-content-between">
    <div>
      <h1><i class="bi bi-speedometer2 me-2 text-primary"></i>Dashboard</h1>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item active">Home</li>
          <li class="breadcrumb-item active">Dashboard</li>
        </ol>
      </nav>
    </div>
    <div class="d-flex gap-2">
      <span class="badge bg-light text-dark border">
        <i class="bi bi-clock me-1"></i>
        Aggiornato: <span id="lastUpdate">—</span>
      </span>
      <button class="btn btn-sm btn-outline-primary" id="refreshBtn">
        <i class="bi bi-arrow-clockwise"></i> Aggiorna
      </button>
    </div>
  </div>

  <!-- KPI CARDS -->
  <div class="row g-3 mb-4" id="kpiRow">
    <!-- Skeleton iniziale -->
    <?php for ($i = 0; $i < 5; $i++): ?>
    <div class="col-xl-2 col-lg-4 col-md-6">
      <div class="skeleton" style="height:110px; border-radius:0.75rem;"></div>
    </div>
    <?php endfor; ?>
  </div>

  <!-- RIGA 2: Commesse + Grafico stato -->
  <div class="row g-3 mb-4">

    <!-- Commesse recenti -->
    <div class="col-xl-8">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center justify-content-between">
          <span><i class="bi bi-briefcase me-2 text-primary"></i>Commesse in corso</span>
          <a href="<?= APP_URL ?>/pages/pm_commesse.php" class="btn btn-sm btn-outline-primary">
            Tutte <i class="bi bi-arrow-right ms-1"></i>
          </a>
        </div>
        <div class="card-body p-0">
          <div id="commesseList">
            <div class="p-4 text-center"><?= UI.spinner() ?></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Grafico stato pm_commesse -->
    <div class="col-xl-4">
      <div class="card h-100">
        <div class="card-header">
          <i class="bi bi-pie-chart me-2 text-primary"></i>Distribuzione per stato
        </div>
        <div class="card-body d-flex align-items-center justify-content-center">
          <div style="height:220px; width:100%;">
            <canvas id="chartStato"></canvas>
          </div>
        </div>
      </div>
    </div>

  </div>

  <!-- RIGA 3: My Tasks + Scadenze + Avanzamento mensile -->
  <div class="row g-3 mb-4">

    <!-- I miei pm_tasks -->
    <div class="col-xl-4">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center justify-content-between">
          <span><i class="bi bi-list-task me-2 text-primary"></i>Le mie attività</span>
          <span class="badge bg-primary rounded-pill" id="myTasksBadge">0</span>
        </div>
        <div class="card-body p-0">
          <div id="myTasksList" style="max-height:320px; overflow-y:auto;"></div>
        </div>
      </div>
    </div>

    <!-- Scadenze prossime -->
    <div class="col-xl-4">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center justify-content-between">
          <span><i class="bi bi-alarm me-2 text-danger"></i>Scadenze prossime</span>
          <a href="<?= APP_URL ?>/pages/pm_scadenze.php" class="btn btn-sm btn-outline-danger btn-sm">
            Tutte
          </a>
        </div>
        <div class="card-body p-0">
          <div id="scadenzeList" style="max-height:320px; overflow-y:auto;"></div>
        </div>
      </div>
    </div>

    <!-- Avanzamento mensile -->
    <div class="col-xl-4">
      <div class="card h-100">
        <div class="card-header">
          <i class="bi bi-graph-up me-2 text-success"></i>Andamento mensile
        </div>
        <div class="card-body">
          <div style="height:260px;"><canvas id="chartMensile"></canvas></div>
        </div>
      </div>
    </div>

  </div>

  <!-- RIGA 4: Valore per stato + Attività recente -->
  <div class="row g-3">

    <!-- Valore economico per stato -->
    <div class="col-xl-6">
      <div class="card">
        <div class="card-header">
          <i class="bi bi-currency-euro me-2 text-success"></i>Valore per stato commessa
        </div>
        <div class="card-body">
          <div style="height:220px;"><canvas id="chartValore"></canvas></div>
        </div>
      </div>
    </div>

    <!-- Attività recente -->
    <div class="col-xl-6">
      <div class="card">
        <div class="card-header">
          <i class="bi bi-activity me-2 text-info"></i>Attività recente
        </div>
        <div class="card-body p-0">
          <div id="attivitaList" style="max-height:255px; overflow-y:auto;"></div>
        </div>
      </div>
    </div>

  </div>

</div><!-- /container-fluid -->

<?php
$inlineScript = <<<'JS'
'use strict';

const STATI_LABEL = {
  BOZZA:'Bozza', PIANIFICAZIONE:'Pianificazione', IN_ESECUZIONE:'In esecuzione',
  SOSPESA:'Sospesa', COMPLETATA:'Completata', COLLAUDATA:'Collaudata',
  CHIUSA:'Chiusa', ANNULLATA:'Annullata',
};

async function loadDashboard() {
  try {
    const data = await API.dashboard();
    renderKpi(data.kpi);
    renderCommesse(data.commesse_recenti || []);
    renderMyTasks(data.my_tasks || []);
    renderScadenze(data.scadenze_prossime || []);
    renderCharts(data);
    renderAttivita(data.attivita_recente || []);

    document.getElementById('lastUpdate').textContent =
      new Date().toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' });
  } catch (err) {
    UI.error('Errore caricamento dashboard: ' + err.message);
  }
}

function renderKpi(kpi) {
  const c  = kpi.pm_commesse || {};
  const t  = kpi.pm_tasks    || {};
  const cards = [
    {
      cls:   'kpi-primary',
      icon:  'bi-briefcase-fill',
      label: 'Commesse Totali',
      value: c.totale || 0,
      sub:   `${c.in_esecuzione || 0} in esecuzione`,
      link:  '/pages/pm_commesse.php',
    },
    {
      cls:   'kpi-warning',
      icon:  'bi-bar-chart-steps',
      label: 'Tasks in Ritardo',
      value: t.in_ritardo || 0,
      sub:   `su ${t.totale || 0} totali`,
      link:  '/pages/cronoprogramma.php',
    },
    {
      cls:   'kpi-success',
      icon:  'bi-receipt-cutoff',
      label: 'SAL da Approvare',
      value: kpi.sal_da_approvare || 0,
      sub:   'In attesa di approvazione',
      link:  '/pages/pm_sal.php',
    },
    {
      cls:   'kpi-danger',
      icon:  'bi-alarm',
      label: 'Scadenze Scadute',
      value: kpi.scadenze_scadute || 0,
      sub:   'Richiedono azione urgente',
      link:  '/pages/pm_scadenze.php',
    },
    {
      cls:   'kpi-purple',
      icon:  'bi-currency-euro',
      label: 'Valore Portfolio',
      value: kpi.valore_totale_fmt || '€ 0',
      sub:   `${c.completate || 0} completate`,
      isText: true,
    },
  ];

  document.getElementById('kpiRow').innerHTML = cards.map(c => `
    <div class="col-xl col-lg-4 col-md-6 col-sm-6">
      ${c.link ? `<a href="${API.getAppUrl() + c.link}" class="text-decoration-none">` : ''}
      <div class="kpi-card ${c.cls} shadow-sm">
        <i class="bi ${c.icon} kpi-icon"></i>
        <div class="kpi-value">${c.isText ? escapeHtml(String(c.value)) : Number(c.value).toLocaleString('it-IT')}</div>
        <div class="kpi-label">${escapeHtml(c.label)}</div>
        <div class="kpi-sub">${escapeHtml(c.sub)}</div>
      </div>
      ${c.link ? '</a>' : ''}
    </div>`).join('');
}

function renderCommesse(pm_commesse) {
  const el = document.getElementById('commesseList');
  if (!pm_commesse.length) {
    el.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-briefcase fs-2"></i><p class="mt-2 small">Nessuna commessa in corso</p></div>';
    return;
  }
  el.innerHTML = pm_commesse.map(c => {
    const perc       = parseFloat(c.percentuale_avanzamento) || 0;
    const inRitardo  = c.in_ritardo;
    const tasksRit   = parseInt(c.tasks_ritardo) || 0;
    return `
    <a href="${API.getAppUrl()}/pages/commessa-detail.php?id=${c.id}" class="text-decoration-none">
      <div class="px-3 py-2 border-bottom hover-lift d-flex align-items-center gap-3">
        <div class="flex-shrink-0" style="width:4px; height:48px; background:${escapeHtml(c.colore || '#0d6efd')}; border-radius:2px;"></div>
        <div class="flex-grow-1 overflow-hidden">
          <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge bg-primary bg-opacity-10 text-primary small">${escapeHtml(c.codice_commessa)}</span>
            ${Format.badgeStato(c.stato)}
            ${tasksRit > 0 ? `<span class="badge bg-danger-subtle text-danger"><i class="bi bi-exclamation-triangle me-1"></i>${tasksRit} in ritardo</span>` : ''}
          </div>
          <div class="fw-semibold small text-truncate text-dark">${escapeHtml(c.oggetto)}</div>
          <div class="d-flex align-items-center gap-2 mt-1">
            <div class="progress flex-grow-1" style="height:5px;">
              <div class="progress-bar ${perc >= 80 ? 'bg-success' : perc >= 50 ? 'bg-primary' : 'bg-warning'}" style="width:${perc}%"></div>
            </div>
            <small class="text-muted" style="white-space:nowrap;">${perc.toFixed(1)}%</small>
            ${c.data_fine_prevista ? `<small class="text-${inRitardo ? 'danger' : 'muted'}" style="white-space:nowrap;"><i class="bi bi-calendar3 me-1"></i>${escapeHtml(c.data_fine_it || '')}</small>` : ''}
          </div>
        </div>
      </div>
    </a>`;
  }).join('');
}

function renderMyTasks(pm_tasks) {
  const el = document.getElementById('myTasksList');
  document.getElementById('myTasksBadge').textContent = pm_tasks.length;
  if (!pm_tasks.length) {
    el.innerHTML = '<div class="text-center text-muted py-4 small"><i class="bi bi-check-all fs-3 text-success"></i><p class="mt-2">Nessun task assegnato</p></div>';
    return;
  }
  el.innerHTML = pm_tasks.map(t => {
    const urgente = parseInt(t.giorni_alla_scadenza) <= 3;
    const statoColor = t.stato === 'IN_RITARDO' ? 'danger' : t.stato === 'IN_CORSO' ? 'primary' : 'secondary';
    return `
    <div class="px-3 py-2 border-bottom d-flex align-items-center gap-2">
      <i class="bi bi-circle-fill text-${statoColor} small"></i>
      <div class="flex-grow-1 overflow-hidden">
        <div class="small fw-semibold text-truncate">${escapeHtml(t.nome)}</div>
        <small class="text-muted">${escapeHtml(t.codice_commessa)} &bull; ${t.percentuale_completamento || 0}%</small>
      </div>
      ${t.data_fine_prevista ? `<span class="badge ${urgente ? 'bg-danger' : 'bg-light text-dark'} small text-nowrap">
        <i class="bi bi-calendar3 me-1"></i>${Format.date(t.data_fine_prevista)}</span>` : ''}
    </div>`;
  }).join('');
}

function renderScadenze(pm_scadenze) {
  const el = document.getElementById('scadenzeList');
  if (!pm_scadenze.length) {
    el.innerHTML = '<div class="text-center text-muted py-4 small"><i class="bi bi-calendar-check fs-3 text-success"></i><p class="mt-2">Nessuna scadenza urgente</p></div>';
    return;
  }
  el.innerHTML = pm_scadenze.map(s => {
    const gg      = parseInt(s.giorni);
    const urgente = gg <= 3;
    const cls     = gg <= 0 ? 'danger' : urgente ? 'warning' : 'info';
    return `
    <div class="px-3 py-2 border-bottom d-flex align-items-center gap-2">
      <i class="bi bi-alarm text-${cls} flex-shrink-0"></i>
      <div class="flex-grow-1 overflow-hidden">
        <div class="small fw-semibold text-truncate">${escapeHtml(s.titolo)}</div>
        <small class="text-muted">${escapeHtml(s.commessa || '')} &bull; ${escapeHtml(s.tipo || '')}</small>
      </div>
      <span class="badge bg-${cls} bg-opacity-${urgente ? '100' : '15'} text-${urgente ? 'white' : cls} small text-nowrap">
        ${gg <= 0 ? 'Scaduta' : gg === 0 ? 'Oggi' : gg + 'gg'}
      </span>
    </div>`;
  }).join('');
}

function renderCharts(data) {
  // Doughnut stato pm_commesse
  const statoData = (data.kpi?.pm_commesse
    ? [
        { stato: 'In esecuzione', n: data.kpi.pm_commesse.in_esecuzione },
        { stato: 'Completate',    n: data.kpi.pm_commesse.completate },
        { stato: 'Sospese',       n: data.kpi.pm_commesse.sospese },
        { stato: 'Pianificazione',n: data.kpi.pm_commesse.in_pianificazione },
      ].filter(d => d.n > 0)
    : []);
  if (statoData.length) chartStatoCommesse('chartStato', statoData);

  // Line avanzamento mensile
  if (data.avanzamento_mensile?.length) chartAvanzamentoMensile('chartMensile', data.avanzamento_mensile);

  // Bar valore per stato
  if (data.valore_per_stato?.length) chartValoreStato('chartValore', data.valore_per_stato);
}

function renderAttivita(attivita) {
  const el = document.getElementById('attivitaList');
  if (!attivita.length) {
    el.innerHTML = '<div class="text-center text-muted py-4 small">Nessuna attività recente</div>';
    return;
  }
  const icons = { CREATE: 'bi-plus-circle text-success', UPDATE: 'bi-pencil text-primary',
    DELETE: 'bi-trash text-danger', LOGIN: 'bi-box-arrow-in-right text-info',
    APPROVE: 'bi-check-circle text-success', UPLOAD: 'bi-upload text-primary' };
  el.innerHTML = attivita.map(a => `
    <div class="px-3 py-2 border-bottom d-flex align-items-center gap-2">
      <i class="bi ${icons[a.azione] || 'bi-activity text-secondary'} flex-shrink-0 small"></i>
      <div class="flex-grow-1 overflow-hidden">
        <small class="fw-semibold">${escapeHtml(a.utente || 'Sistema')}</small>
        <small class="text-muted ms-1">${escapeHtml(a.azione)} · ${escapeHtml(a.entita_tipo)}</small>
      </div>
      <small class="text-muted text-nowrap">${Format.timeAgo(a.created_at)}</small>
    </div>`).join('');
}

// Init
document.addEventListener('DOMContentLoaded', () => {
  loadDashboard();
  document.getElementById('refreshBtn')?.addEventListener('click', loadDashboard);
});
JS;

include COMPONENTS_PATH . '/footer.php';
?>
