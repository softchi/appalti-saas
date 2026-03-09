<?php
/**
 * Pagina SAL - Stato Avanzamento Lavori
 */
define('APP_INIT', true);
require_once __DIR__ . '/../php/bootstrap.php';

Auth::require('pm_sal.read');

$commessaId = sanitizeInt($_GET['commessa_id'] ?? null, 1);

$pm_commesse = Database::fetchAll(
    'SELECT id, codice_commessa, oggetto, importo_contrattuale
     FROM pm_commesse WHERE stato IN ("IN_ESECUZIONE","COMPLETATA","PIANIFICAZIONE")
     ORDER BY codice_commessa'
);

if (!$commessaId && !empty($pm_commesse)) {
    $commessaId = (int)$pm_commesse[0]['id'];
}

$pageTitle    = 'SAL - Stato Avanzamento Lavori';
$activeMenu   = 'pm_sal';
$extraScripts = [APP_URL . '/js/charts.js'];

include COMPONENTS_PATH . '/header.php';
include COMPONENTS_PATH . '/sidebar.php';
?>

<!-- Page Header -->
<div class="page-header d-flex align-items-center gap-3">
  <div class="flex-grow-1">
    <h1><i class="bi bi-receipt-cutoff me-2 text-primary"></i>Stato Avanzamento Lavori</h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/pages/dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active">SAL</li>
      </ol>
    </nav>
  </div>
  <div class="d-flex align-items-center gap-2">
    <select class="form-select form-select-sm" id="commessaSelect" style="min-width:280px;">
      <?php foreach ($pm_commesse as $c): ?>
      <option value="<?= e($c['id']) ?>" <?= $c['id'] == $commessaId ? 'selected' : '' ?>>
        <?= e($c['codice_commessa']) ?> – <?= e(mb_substr($c['oggetto'], 0, 40)) ?>
      </option>
      <?php endforeach; ?>
    </select>
    <?php if (Auth::can('pm_sal.create')): ?>
    <button class="btn btn-primary btn-sm" id="nuovoSalBtn">
      <i class="bi bi-plus-lg me-1"></i>Nuovo SAL
    </button>
    <?php endif; ?>
  </div>
</div>

<!-- KPI SAL -->
<div class="row g-3 mb-4" id="salKpi">
  <div class="col-md-3"><div class="skeleton" style="height:90px;border-radius:.75rem;"></div></div>
  <div class="col-md-3"><div class="skeleton" style="height:90px;border-radius:.75rem;"></div></div>
  <div class="col-md-3"><div class="skeleton" style="height:90px;border-radius:.75rem;"></div></div>
  <div class="col-md-3"><div class="skeleton" style="height:90px;border-radius:.75rem;"></div></div>
</div>

<!-- Grafico andamento SAL -->
<div class="row g-3 mb-4">
  <div class="col-xl-8">
    <div class="card">
      <div class="card-header"><i class="bi bi-graph-up me-2 text-primary"></i>Andamento liquidazione</div>
      <div class="card-body"><div style="height:240px;"><canvas id="chartSal"></canvas></div></div>
    </div>
  </div>
  <div class="col-xl-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-pie-chart me-2 text-primary"></i>Liquidazione</div>
      <div class="card-body d-flex align-items-center justify-content-center">
        <div style="height:200px; width:100%;"><canvas id="chartLiquidazione"></canvas></div>
      </div>
      <div class="card-footer text-center">
        <div class="d-flex justify-content-around small">
          <div><div class="fw-bold text-success" id="kpiLiquidato">—</div><div class="text-muted">Liquidato</div></div>
          <div><div class="fw-bold text-warning" id="kpiResiduo">—</div><div class="text-muted">Residuo</div></div>
          <div><div class="fw-bold text-primary" id="kpiPercLiq">—</div><div class="text-muted">Avanzamento</div></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Tabella SAL -->
<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between">
    <span><i class="bi bi-table me-2 text-primary"></i>Elenco S.A.L.</span>
    <span class="badge bg-secondary" id="salCount">0</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0" id="salTable">
      <thead>
        <tr>
          <th>N°</th>
          <th>Periodo</th>
          <th>Emissione</th>
          <th class="text-end">Importo periodo</th>
          <th class="text-end">Importo cumulato</th>
          <th class="text-center">Avanzamento</th>
          <th>Stato</th>
          <th>DL</th>
          <th class="text-center">Azioni</th>
        </tr>
      </thead>
      <tbody id="salTbody">
        <tr><td colspan="9" class="text-center py-4">
          <div class="spinner-border spinner-border-sm text-primary me-2"></div>Caricamento...
        </td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Nuovo SAL -->
<div class="modal fade" id="salModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="bi bi-receipt-cutoff me-2"></i>Nuovo SAL</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="salForm">
        <div class="modal-body">
          <input type="hidden" name="commessa_id" id="sf_commessa_id">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label required-star">Data inizio periodo</label>
              <input type="date" class="form-control" name="data_inizio" id="sf_inizio" required>
            </div>
            <div class="col-md-6">
              <label class="form-label required-star">Data fine periodo</label>
              <input type="date" class="form-control" name="data_fine" id="sf_fine" required>
            </div>
            <div class="col-md-4">
              <label class="form-label required-star">Importo lavori (€)</label>
              <div class="input-group">
                <span class="input-group-text">€</span>
                <input type="number" class="form-control" name="importo_lavori" id="sf_lavori"
                       min="0" step="0.01" value="0">
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Importo sicurezza (€)</label>
              <div class="input-group">
                <span class="input-group-text">€</span>
                <input type="number" class="form-control" name="importo_sicurezza" id="sf_sicurezza"
                       min="0" step="0.01" value="0">
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Importo pm_varianti (€)</label>
              <div class="input-group">
                <span class="input-group-text">€</span>
                <input type="number" class="form-control" name="importo_varianti" id="sf_varianti"
                       min="0" step="0.01" value="0">
              </div>
            </div>
            <!-- Totale calcolato -->
            <div class="col-12">
              <div class="alert alert-info py-2 mb-0 d-flex align-items-center justify-content-between">
                <span><i class="bi bi-calculator me-2"></i><strong>Totale periodo:</strong></span>
                <strong class="fs-5" id="sf_totale">€ 0,00</strong>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label">Note DL</label>
              <textarea class="form-control" name="note_dl" id="sf_note" rows="2"
                        placeholder="Annotazioni del Direttore dei Lavori"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
          <button type="submit" class="btn btn-primary" id="salSaveBtn">
            <i class="bi bi-save me-1"></i>Emetti SAL
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$inlineScript = <<<'JS'
'use strict';

let currentCid  = parseInt(document.getElementById('commessaSelect')?.value) || 0;
let salModalBS  = null;
let salData     = [];

const STATI_SAL = {
  BOZZA:      ['secondary', 'Bozza'],
  EMESSO:     ['info',      'Emesso'],
  APPROVATO:  ['success',   'Approvato'],
  PAGATO:     ['purple',    'Pagato'],
  CONTESTATO: ['danger',    'Contestato'],
};

async function loadSal(cid) {
  if (!cid) return;
  currentCid = cid;
  document.getElementById('sf_commessa_id').value = cid;

  try {
    const data = await API.pm_sal.list(cid);
    salData = data.pm_sal || [];

    // KPI
    document.getElementById('salKpi').innerHTML = `
      <div class="col-md-3">
        <div class="kpi-card kpi-primary shadow-sm">
          <i class="bi bi-receipt kpi-icon"></i>
          <div class="kpi-value">${salData.length}</div>
          <div class="kpi-label">SAL Emessi</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="kpi-card kpi-success shadow-sm">
          <i class="bi bi-check-circle kpi-icon"></i>
          <div class="kpi-value">${salData.filter(s => s.stato === 'APPROVATO' || s.stato === 'PAGATO').length}</div>
          <div class="kpi-label">SAL Approvati</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="kpi-card kpi-info shadow-sm">
          <i class="bi bi-currency-euro kpi-icon"></i>
          <div class="kpi-value">${escapeHtml(data.importo_liquidato_fmt || '€ 0')}</div>
          <div class="kpi-label">Liquidato</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="kpi-card kpi-warning shadow-sm">
          <i class="bi bi-hourglass-split kpi-icon"></i>
          <div class="kpi-value">${escapeHtml(data.importo_residuo_fmt || '€ 0')}</div>
          <div class="kpi-label">Residuo</div>
        </div>
      </div>`;

    // Dati riepilogo
    document.getElementById('kpiLiquidato').textContent = data.importo_liquidato_fmt || '—';
    document.getElementById('kpiResiduo').textContent   = data.importo_residuo_fmt   || '—';
    document.getElementById('kpiPercLiq').textContent   = (data.percentuale_liquidata || 0).toFixed(1) + '%';

    // Grafici
    if (salData.length) {
      chartSalAndamento('chartSal', [...salData].reverse());
    }

    // Doughnut liquidazione
    const liquidato = parseFloat(data.importo_liquidato || 0);
    const residuo   = parseFloat(data.importo_residuo   || 0);
    getOrCreateChart('chartLiquidazione', {
      type: 'doughnut',
      data: {
        labels: ['Liquidato', 'Residuo'],
        datasets: [{
          data:            [liquidato, Math.max(0, residuo)],
          backgroundColor: ['#198754', '#e9ecef'],
          borderWidth:     0,
        }],
      },
      options: {
        cutout: '72%',
        plugins: { legend: { display: false }, tooltip: {
          callbacks: { label: ctx => ' € ' + ctx.raw.toLocaleString('it-IT', { maximumFractionDigits: 0 }) }
        }},
      },
    });

    // Tabella
    document.getElementById('salCount').textContent = salData.length;
    const tbody = document.getElementById('salTbody');

    if (!salData.length) {
      tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">Nessun SAL emesso per questa commessa</td></tr>';
      return;
    }

    tbody.innerHTML = salData.map(s => {
      const [badgeCls, badgeLabel] = STATI_SAL[s.stato] || ['secondary', s.stato];
      const canApprove = s.stato === 'EMESSO';
      return `
      <tr>
        <td><span class="badge bg-primary">N.${s.numero_sal}</span></td>
        <td>
          <small>${escapeHtml(s.data_inizio_it || s.data_inizio)}</small><br>
          <small class="text-muted">→ ${escapeHtml(s.data_fine_it || s.data_fine)}</small>
        </td>
        <td><small>${escapeHtml(s.data_emissione_it || '—')}</small></td>
        <td class="text-end fw-semibold text-euro">${escapeHtml(s.importo_totale_fmt || '—')}</td>
        <td class="text-end text-euro">${escapeHtml(s.importo_cumulato_fmt || '—')}</td>
        <td class="text-center">
          <div class="d-flex align-items-center gap-1">
            <div class="progress flex-grow-1" style="height:5px;">
              <div class="progress-bar bg-success" style="width:${s.percentuale_avanzamento}%"></div>
            </div>
            <small>${parseFloat(s.percentuale_avanzamento).toFixed(1)}%</small>
          </div>
        </td>
        <td><span class="badge bg-${badgeCls}">${badgeLabel}</span></td>
        <td><small>${escapeHtml(s.dl_nome || '—')}</small></td>
        <td class="text-center">
          <div class="btn-group btn-group-sm">
            <a href="${API.getAppUrl()}/pages/pm_sal-detail.php?id=${s.id}"
               class="btn btn-outline-primary btn-icon" title="Dettaglio">
              <i class="bi bi-eye"></i>
            </a>
            ${canApprove && <?= json_encode(Auth::can('pm_sal.approve')) ?> ? `
            <button class="btn btn-outline-success btn-icon approvaSal" data-id="${s.id}" title="Approva SAL">
              <i class="bi bi-check-circle"></i>
            </button>` : ''}
          </div>
        </td>
      </tr>`;
    }).join('');

    // Approva SAL
    document.querySelectorAll('.approvaSal').forEach(btn => {
      btn.addEventListener('click', async function() {
        const id = this.dataset.id;
        const ok = await UI.confirm('Approva SAL', 'Confermi l\'approvazione del SAL?', 'Approva', 'btn-success');
        if (!ok) return;
        try {
          await API.pm_sal.approve(id, '');
          UI.success('SAL approvato con successo');
          loadSal(currentCid);
        } catch (err) { UI.error(err.message); }
      });
    });

  } catch (err) {
    UI.error('Errore caricamento SAL: ' + err.message);
  }
}

// Calcolo totale automatico
function aggiornaSelTotale() {
  const l = parseFloat(document.getElementById('sf_lavori').value)    || 0;
  const s = parseFloat(document.getElementById('sf_sicurezza').value) || 0;
  const v = parseFloat(document.getElementById('sf_varianti').value)  || 0;
  document.getElementById('sf_totale').textContent = Format.euro(l + s + v);
}

['sf_lavori','sf_sicurezza','sf_varianti'].forEach(id => {
  document.getElementById(id)?.addEventListener('input', aggiornaSelTotale);
});

// Form SAL
document.getElementById('salForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = document.getElementById('salSaveBtn');
  btn.disabled = true;
  btn.innerHTML = UI.spinner('sm') + ' Creazione...';

  const data = serializeForm(document.getElementById('salForm'));
  try {
    await API.pm_sal.create(data);
    UI.success('SAL creato con successo');
    salModalBS.hide();
    loadSal(currentCid);
  } catch (err) {
    UI.error(err.message || 'Errore creazione SAL');
    if (err.errors) showFormErrors(document.getElementById('salForm'), err.errors);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-save me-1"></i>Emetti SAL';
  }
});

document.getElementById('nuovoSalBtn')?.addEventListener('click', () => {
  document.getElementById('salForm').reset();
  document.getElementById('sf_commessa_id').value = currentCid;
  document.getElementById('sf_inizio').value = new Date().toISOString().split('T')[0];
  document.getElementById('sf_fine').value   = new Date().toISOString().split('T')[0];
  document.getElementById('sf_totale').textContent = '€ 0,00';
  salModalBS = salModalBS || new bootstrap.Modal(document.getElementById('salModal'));
  salModalBS.show();
});

document.getElementById('commessaSelect')?.addEventListener('change', function() {
  loadSal(parseInt(this.value));
  history.replaceState(null, '', '?commessa_id=' + this.value);
});

document.addEventListener('DOMContentLoaded', () => {
  loadSal(currentCid);
});
JS;

include COMPONENTS_PATH . '/footer.php';
?>
