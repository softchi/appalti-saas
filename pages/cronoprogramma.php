<?php
/**
 * Pagina Cronoprogramma / Gantt
 */
define('APP_INIT', true);
require_once __DIR__ . '/../php/bootstrap.php';

Auth::require('pm_tasks.read');

$commessaId = sanitizeInt($_GET['commessa_id'] ?? null, 1);

// Lista pm_commesse per dropdown
$pm_commesse = Database::fetchAll(
    'SELECT id, codice_commessa, oggetto FROM pm_commesse
     WHERE stato IN ("IN_ESECUZIONE","PIANIFICAZIONE") ORDER BY codice_commessa',
);

if (!$commessaId && !empty($pm_commesse)) {
    $commessaId = (int)$pm_commesse[0]['id'];
}

$commessaSelezionata = $commessaId
    ? Database::fetchOne('SELECT * FROM pm_commesse WHERE id = :id', [':id' => $commessaId])
    : null;

$pageTitle    = 'Cronoprogramma';
$activeMenu   = 'cronoprogramma';
$extraScripts = [
    APP_URL . '/js/gantt.js',
];

include COMPONENTS_PATH . '/header.php';
include COMPONENTS_PATH . '/sidebar.php';
?>

<style>
  .gantt-wrapper { min-height: 500px; }
  #ganttContainer { height: calc(100vh - 240px); min-height: 400px; }
</style>

<!-- Page Header -->
<div class="page-header d-flex align-items-center gap-3">
  <div class="flex-grow-1">
    <h1><i class="bi bi-bar-chart-steps me-2 text-primary"></i>Cronoprogramma Lavori</h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/pages/dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Cronoprogramma</li>
      </ol>
    </nav>
  </div>

  <!-- Selettore commessa -->
  <div class="d-flex align-items-center gap-2">
    <label class="form-label mb-0 fw-semibold small text-nowrap">Commessa:</label>
    <select class="form-select form-select-sm" id="commessaSelect" style="min-width:260px;">
      <?php foreach ($pm_commesse as $c): ?>
      <option value="<?= e($c['id']) ?>" <?= $c['id'] == $commessaId ? 'selected' : '' ?>>
        <?= e($c['codice_commessa']) ?> – <?= e(mb_substr($c['oggetto'], 0, 50)) ?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>

  <!-- Azioni -->
  <div class="d-flex gap-2">
    <?php if (Auth::can('pm_tasks.create')): ?>
    <button class="btn btn-primary btn-sm" id="addTaskBtn">
      <i class="bi bi-plus-lg me-1"></i>Nuova attività
    </button>
    <?php endif; ?>
    <button class="btn btn-outline-secondary btn-sm" id="exportGanttBtn">
      <i class="bi bi-download me-1"></i>Esporta
    </button>
  </div>
</div>

<!-- Stats bar -->
<div id="ganttStats" class="row g-2 mb-3">
  <!-- Populate via JS -->
</div>

<!-- Gantt Container -->
<div id="ganttContainer"></div>

<!-- Modal Task -->
<div class="modal fade" id="taskModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="taskModalTitle">
          <i class="bi bi-list-task me-2"></i>Nuova Attività
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="taskForm">
        <div class="modal-body">
          <input type="hidden" name="commessa_id" id="tf_commessa_id">
          <input type="hidden" name="task_id"     id="tf_task_id">

          <div class="row g-3">
            <!-- Nome -->
            <div class="col-12">
              <label class="form-label required-star">Nome attività</label>
              <input type="text" class="form-control" name="nome" id="tf_nome" required maxlength="300">
            </div>
            <!-- Tipo + WBS -->
            <div class="col-md-6">
              <label class="form-label">Tipo</label>
              <select class="form-select" name="tipo" id="tf_tipo">
                <option value="TASK">Task</option>
                <option value="MILESTONE">Milestone</option>
                <option value="FASE">Fase</option>
                <option value="SOMMARIO">Sommario</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Assegnato a</label>
              <select class="form-select" name="assegnato_a" id="tf_assegnato_a">
                <option value="">— Non assegnato —</option>
              </select>
            </div>
            <!-- Date -->
            <div class="col-md-6">
              <label class="form-label required-star">Data inizio prevista</label>
              <input type="date" class="form-control" name="data_inizio_prevista" id="tf_inizio" required>
            </div>
            <div class="col-md-6">
              <label class="form-label required-star">Data fine prevista</label>
              <input type="date" class="form-control" name="data_fine_prevista" id="tf_fine" required>
            </div>
            <!-- Durata + Importo -->
            <div class="col-md-4">
              <label class="form-label">Durata (gg. lav.)</label>
              <input type="number" class="form-control" name="durata_prevista" id="tf_durata" min="1">
            </div>
            <div class="col-md-4">
              <label class="form-label">Importo previsto (€)</label>
              <input type="number" class="form-control" name="importo_previsto" id="tf_importo" min="0" step="0.01">
            </div>
            <div class="col-md-4">
              <label class="form-label">Priorità</label>
              <select class="form-select" name="priorita" id="tf_priorita">
                <option value="BASSA">Bassa</option>
                <option value="NORMALE" selected>Normale</option>
                <option value="ALTA">Alta</option>
                <option value="CRITICA">Critica</option>
              </select>
            </div>
            <!-- Avanzamento (solo in modifica) -->
            <div class="col-12" id="percContainer" style="display:none;">
              <label class="form-label">Percentuale completamento</label>
              <div class="perc-slider-container">
                <input type="range" class="form-range" name="percentuale_completamento"
                       id="tf_perc" min="0" max="100" step="5" value="0">
                <span class="perc-value" id="tf_percVal">0%</span>
              </div>
            </div>
            <!-- Note -->
            <div class="col-12">
              <label class="form-label">Note</label>
              <textarea class="form-control" name="note" id="tf_note" rows="2"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
          <button type="submit" class="btn btn-primary" id="taskFormSave">
            <i class="bi bi-save me-1"></i>Salva attività
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$inlineScript = <<<'JS'
'use strict';

let gantt         = null;
let currentCid    = parseInt(document.getElementById('commessaSelect')?.value) || 0;
let taskModalBS   = null;

// ============================================================
// INIT GANTT
// ============================================================
async function loadGantt(commessaId) {
  if (!commessaId) return;
  currentCid = commessaId;

  document.getElementById('ganttStats').innerHTML =
    '<div class="col-12"><div class="skeleton" style="height:60px; border-radius:0.5rem;"></div></div>';
  document.getElementById('ganttContainer').innerHTML =
    '<div class="text-center p-5"><div class="spinner-border text-primary"></div><p class="mt-3">Caricamento cronoprogramma...</p></div>';

  try {
    const data = await API.pm_tasks.list(commessaId);

    // Stats bar
    const s = data.stats || {};
    document.getElementById('ganttStats').innerHTML = `
      <div class="col-auto">
        <span class="badge bg-secondary">${s.totale || 0} Totali</span>
      </div>
      <div class="col-auto">
        <span class="badge bg-success">${s.completati || 0} Completati</span>
      </div>
      <div class="col-auto">
        <span class="badge bg-primary">${s.in_corso || 0} In corso</span>
      </div>
      <div class="col-auto">
        <span class="badge bg-danger">${s.in_ritardo || 0} In ritardo</span>
      </div>
      <div class="col-auto">
        <span class="badge bg-purple" style="background:#6f42c1;">${s.milestones || 0} Milestone</span>
      </div>
      ${s.data_inizio_progetto ? `<div class="col-auto ms-auto">
        <small class="text-muted">
          <i class="bi bi-calendar3 me-1"></i>
          ${Format.date(s.data_inizio_progetto)} → ${Format.date(s.data_fine_progetto)}
        </small>
      </div>` : ''}`;

    // Crea/ricrea Gantt
    if (gantt) gantt.destroy();
    document.getElementById('ganttContainer').innerHTML = '';

    gantt = new GanttChart('ganttContainer', {
      zoom: 'month',
      editable: true,
      onTaskClick: openTaskDetail,
    });

    gantt.load(data.pm_tasks || []);
    gantt.scrollToToday();

  } catch (err) {
    document.getElementById('ganttContainer').innerHTML =
      `<div class="alert alert-danger m-3"><i class="bi bi-exclamation-triangle me-2"></i>${escapeHtml(err.message)}</div>`;
  }
}

// ============================================================
// TASK MODAL
// ============================================================
function openTaskModal(task = null) {
  const form = document.getElementById('taskForm');
  const title = document.getElementById('taskModalTitle');

  form.reset();
  document.getElementById('tf_commessa_id').value = currentCid;
  document.getElementById('percContainer').style.display = 'none';

  if (task) {
    title.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Modifica Attività';
    document.getElementById('tf_task_id').value  = task.id;
    document.getElementById('tf_nome').value      = task.nome || '';
    document.getElementById('tf_tipo').value      = task.tipo || 'TASK';
    document.getElementById('tf_inizio').value    = task.data_inizio_prevista || '';
    document.getElementById('tf_fine').value      = task.data_fine_prevista   || '';
    document.getElementById('tf_durata').value    = task.durata_prevista || '';
    document.getElementById('tf_importo').value   = task.importo_previsto || '';
    document.getElementById('tf_priorita').value  = task.priorita || 'NORMALE';
    document.getElementById('tf_note').value      = task.note || '';
    document.getElementById('tf_assegnato_a').value = task.assegnato_a || '';

    const perc = parseFloat(task.percentuale_completamento) || 0;
    document.getElementById('tf_perc').value = perc;
    document.getElementById('tf_percVal').textContent = perc + '%';
    document.getElementById('percContainer').style.display = '';
  } else {
    title.innerHTML = '<i class="bi bi-plus-circle me-2"></i>Nuova Attività';
    document.getElementById('tf_task_id').value = '';
  }

  taskModalBS = taskModalBS || new bootstrap.Modal(document.getElementById('taskModal'));
  taskModalBS.show();
}

function openTaskDetail(task) {
  if (task) openTaskModal(task);
}

// Slider avanzamento
document.getElementById('tf_perc')?.addEventListener('input', (e) => {
  document.getElementById('tf_percVal').textContent = e.target.value + '%';
});

// Form submit
document.getElementById('taskForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = document.getElementById('taskFormSave');
  btn.disabled = true;
  btn.innerHTML = UI.spinner('sm') + ' Salvataggio...';

  const data    = serializeForm(document.getElementById('taskForm'));
  const taskId  = document.getElementById('tf_task_id').value;

  try {
    if (taskId) {
      await API.pm_tasks.update(taskId, data);
      UI.success('Attività aggiornata con successo');
    } else {
      await API.pm_tasks.create(data);
      UI.success('Attività creata con successo');
    }
    taskModalBS.hide();
    loadGantt(currentCid);
  } catch (err) {
    UI.error(err.message || 'Errore salvataggio');
    if (err.errors) showFormErrors(document.getElementById('taskForm'), err.errors);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-save me-1"></i>Salva attività';
  }
});

// ============================================================
// EVENTS
// ============================================================
document.getElementById('commessaSelect')?.addEventListener('change', function() {
  loadGantt(parseInt(this.value));
  history.replaceState(null, '', '?commessa_id=' + this.value);
});

document.getElementById('addTaskBtn')?.addEventListener('click', () => openTaskModal());

document.getElementById('exportGanttBtn')?.addEventListener('click', async () => {
  try {
    const data = await API.reports.gantt(currentCid);
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a'); a.href = url;
    a.download = `gantt_${currentCid}_${new Date().toISOString().split('T')[0]}.json`;
    a.click(); URL.revokeObjectURL(url);
  } catch (err) { UI.error('Errore esportazione'); }
});

// Carica pm_utenti nel select
async function loadUtentiSelect() {
  try {
    const data = await API.pm_utenti.dropdown();
    const sel  = document.getElementById('tf_assegnato_a');
    if (!sel) return;
    const current = sel.value;
    sel.innerHTML = '<option value="">— Non assegnato —</option>' +
      (data.pm_utenti || []).map(u =>
        `<option value="${u.id}" ${u.id == current ? 'selected' : ''}>${escapeHtml(u.nome_completo)}</option>`
      ).join('');
  } catch { /* silenzioso */ }
}

// Init
document.addEventListener('DOMContentLoaded', () => {
  loadGantt(currentCid);
  loadUtentiSelect();
});
JS;

include COMPONENTS_PATH . '/footer.php';
?>
