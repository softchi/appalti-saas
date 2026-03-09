<?php
/**
 * Pagina: Lista Commesse
 */
define('APP_INIT', true);
require_once __DIR__ . '/../php/bootstrap.php';
Auth::require();
if (!Auth::can('pm_commesse.read')) {
    header('Location: ' . APP_URL . '/pages/dashboard.php');
    exit;
}

$pageTitle  = 'Commesse';
$activeMenu = 'pm_commesse';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/sidebar.php';
?>

<div class="container-fluid px-4 py-3">

  <!-- Page header -->
  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h1 class="h4 mb-1 fw-bold">Commesse</h1>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
          <li class="breadcrumb-item"><a href="<?= APP_URL ?>/pages/dashboard.php">Dashboard</a></li>
          <li class="breadcrumb-item active">Commesse</li>
        </ol>
      </nav>
    </div>
    <?php if (Auth::can('pm_commesse.create')): ?>
    <button class="btn btn-primary" id="btnNuovaCommessa">
      <i class="bi bi-plus-circle me-2"></i>Nuova Commessa
    </button>
    <?php endif; ?>
  </div>

  <!-- KPI Cards -->
  <div class="row g-3 mb-4" id="kpiRow">
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm kpi-primary h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <p class="text-white-50 small mb-1">Totale Commesse</p>
              <h3 class="text-white fw-bold mb-0" id="kpiTotale">—</h3>
            </div>
            <div class="text-white-50"><i class="bi bi-briefcase-fill fs-2"></i></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm kpi-success h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <p class="text-white-50 small mb-1">In Esecuzione</p>
              <h3 class="text-white fw-bold mb-0" id="kpiEsecuzione">—</h3>
            </div>
            <div class="text-white-50"><i class="bi bi-play-circle-fill fs-2"></i></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm kpi-warning h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <p class="text-white-50 small mb-1">In Ritardo</p>
              <h3 class="text-white fw-bold mb-0" id="kpiRitardo">—</h3>
            </div>
            <div class="text-white-50"><i class="bi bi-exclamation-triangle-fill fs-2"></i></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm kpi-info h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <p class="text-white-50 small mb-1">Valore Totale</p>
              <h3 class="text-white fw-bold mb-0" id="kpiValore">—</h3>
            </div>
            <div class="text-white-50"><i class="bi bi-currency-euro fs-2"></i></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filters -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
      <div class="row g-2 align-items-center">
        <div class="col-12 col-md-4 col-lg-3">
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
            <input type="search" class="form-control border-start-0" id="searchInput"
                   placeholder="Cerca CIG, CUP, oggetto...">
          </div>
        </div>
        <div class="col-6 col-md-2">
          <select class="form-select form-select-sm" id="filterStato">
            <option value="">Tutti gli stati</option>
            <option value="IN_ESECUZIONE">In Esecuzione</option>
            <option value="COMPLETATA">Completata</option>
            <option value="SOSPESA">Sospesa</option>
            <option value="IN_ATTESA">In Attesa</option>
            <option value="ANNULLATA">Annullata</option>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <select class="form-select form-select-sm" id="filterSA">
            <option value="">Tutte le SA</option>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <select class="form-select form-select-sm" id="sortBy">
            <option value="data_consegna">Scadenza</option>
            <option value="importo">Importo</option>
            <option value="avanzamento">Avanzamento</option>
            <option value="codice">Codice</option>
          </select>
        </div>
        <div class="col-6 col-md-auto ms-md-auto">
          <div class="btn-group btn-group-sm" id="viewToggle">
            <button class="btn btn-outline-secondary active" id="btnViewTable" title="Vista tabella">
              <i class="bi bi-list-ul"></i>
            </button>
            <button class="btn btn-outline-secondary" id="btnViewGrid" title="Vista schede">
              <i class="bi bi-grid-3x3-gap"></i>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- List view -->
  <div id="viewTable">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" id="commesseTable">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Codice</th>
                <th>Oggetto / Stazione Appaltante</th>
                <th class="d-none d-xl-table-cell">CIG</th>
                <th>Importo</th>
                <th>Avanzamento</th>
                <th>Stato</th>
                <th class="d-none d-lg-table-cell">Scadenza</th>
                <th class="d-none d-md-table-cell">RUP</th>
                <th class="text-end pe-3">Azioni</th>
              </tr>
            </thead>
            <tbody id="commesseBody">
              <tr><td colspan="9" class="text-center py-5 text-muted">
                <div class="spinner-border spinner-border-sm me-2"></div>Caricamento...
              </td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Grid view -->
  <div id="viewGrid" class="d-none">
    <div class="row g-3" id="commesseGrid"></div>
  </div>

  <!-- Pagination -->
  <div id="paginationWrap" class="mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <small class="text-muted" id="paginationInfo"></small>
    <nav><ul class="pagination pagination-sm mb-0" id="pagination"></ul></nav>
  </div>
</div>

<!-- ======================================================
     MODAL COMMESSA (Crea / Modifica)
====================================================== -->
<div class="modal fade" id="commessaModal" tabindex="-1" aria-labelledby="commessaModalLabel">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="commessaModalLabel">
          <i class="bi bi-briefcase-fill me-2"></i>Nuova Commessa
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="commessaForm" novalidate>
          <input type="hidden" id="commessaId">

          <!-- Nav tabs -->
          <ul class="nav nav-tabs mb-3" id="commessaTabs">
            <li class="nav-item">
              <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabGenerali">
                <i class="bi bi-info-circle me-1"></i>Dati Generali
              </button>
            </li>
            <li class="nav-item">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabFinanziari">
                <i class="bi bi-currency-euro me-1"></i>Dati Finanziari
              </button>
            </li>
            <li class="nav-item">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabTeam">
                <i class="bi bi-people me-1"></i>Team
              </button>
            </li>
          </ul>

          <div class="tab-content">
            <!-- Tab: Dati Generali -->
            <div class="tab-pane fade show active" id="tabGenerali">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Oggetto <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="fOggetto" name="oggetto" required
                         placeholder="Descrizione lavori...">
                </div>
                <div class="col-md-3">
                  <label class="form-label fw-semibold">CIG <span class="text-danger">*</span></label>
                  <input type="text" class="form-control font-monospace" id="fCig" name="cig"
                         placeholder="XXXXXXXXXX" maxlength="10">
                </div>
                <div class="col-md-3">
                  <label class="form-label fw-semibold">CUP</label>
                  <input type="text" class="form-control font-monospace" id="fCup" name="cup"
                         placeholder="X00X00X000X" maxlength="15">
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Stazione Appaltante <span class="text-danger">*</span></label>
                  <select class="form-select" id="fStazioneAppaltante" name="stazione_appaltante_id" required>
                    <option value="">— Seleziona —</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Impresa Esecutrice</label>
                  <select class="form-select" id="fImpresa" name="impresa_id">
                    <option value="">— Seleziona —</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">Data Consegna <span class="text-danger">*</span></label>
                  <input type="date" class="form-control" id="fDataConsegna" name="data_consegna" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">Fine Prevista <span class="text-danger">*</span></label>
                  <input type="date" class="form-control" id="fDataFinePrevista" name="data_fine_prevista" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">Stato</label>
                  <select class="form-select" id="fStato" name="stato">
                    <option value="IN_ATTESA">In Attesa</option>
                    <option value="IN_ESECUZIONE" selected>In Esecuzione</option>
                    <option value="SOSPESA">Sospesa</option>
                    <option value="COMPLETATA">Completata</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">Categoria SOA</label>
                  <input type="text" class="form-control" name="categoria_soa" id="fCategoriaSoa"
                         placeholder="es. OG1, OG3...">
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">Classifica SOA</label>
                  <select class="form-select" name="classifica_soa" id="fClassificaSoa">
                    <option value="">—</option>
                    <?php foreach (['I','II','III','III-bis','IV','IV-bis','V','VI','VII','VIII'] as $cl): ?>
                    <option value="<?= $cl ?>"><?= $cl ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">Tipo Procedura</label>
                  <select class="form-select" name="tipo_procedura" id="fTipoProcedura">
                    <option value="APERTA">Aperta</option>
                    <option value="RISTRETTA">Ristretta</option>
                    <option value="NEGOZIATA">Negoziata</option>
                    <option value="DIRETTA">Affidamento Diretto</option>
                  </select>
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold">Note</label>
                  <textarea class="form-control" name="note" id="fNote" rows="2"
                            placeholder="Note libere..."></textarea>
                </div>
              </div>
            </div>

            <!-- Tab: Dati Finanziari -->
            <div class="tab-pane fade" id="tabFinanziari">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label fw-semibold">Importo Contrattuale (€) <span class="text-danger">*</span></label>
                  <input type="number" class="form-control" name="importo_contrattuale" id="fImporto"
                         step="0.01" min="0" required placeholder="0.00">
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">Oneri Sicurezza (€)</label>
                  <input type="number" class="form-control" name="importo_sicurezza" id="fImportoSicurezza"
                         step="0.01" min="0" placeholder="0.00">
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">Ribasso d'Asta (%)</label>
                  <div class="input-group">
                    <input type="number" class="form-control" name="ribasso_percentuale" id="fRibasso"
                           step="0.001" min="0" max="100" placeholder="0.000">
                    <span class="input-group-text">%</span>
                  </div>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">Importo Perizia (€)</label>
                  <input type="number" class="form-control" name="importo_perizia" id="fImportoPerizia"
                         step="0.01" min="0" placeholder="0.00">
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">Costi Progettazione (€)</label>
                  <input type="number" class="form-control" name="costi_progettazione" id="fCostiProgettazione"
                         step="0.01" min="0" placeholder="0.00">
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">Finanziamento</label>
                  <input type="text" class="form-control" name="fonte_finanziamento" id="fFonte"
                         placeholder="es. PNRR, Fondi Regionali...">
                </div>

                <!-- Importo totale calcolato -->
                <div class="col-12">
                  <div class="alert alert-info mb-0">
                    <div class="row text-center">
                      <div class="col">
                        <small class="text-muted d-block">Importo Lavori + Sicurezza</small>
                        <strong id="calcTotale">€ 0,00</strong>
                      </div>
                      <div class="col">
                        <small class="text-muted d-block">Con Ribasso</small>
                        <strong id="calcConRibasso">€ 0,00</strong>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Tab: Team -->
            <div class="tab-pane fade" id="tabTeam">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-semibold">RUP <span class="text-danger">*</span></label>
                  <select class="form-select" name="rup_id" id="fRup" required>
                    <option value="">— Seleziona RUP —</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Project Manager</label>
                  <select class="form-select" name="pm_id" id="fPm">
                    <option value="">— Seleziona PM —</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Direttore Lavori</label>
                  <select class="form-select" name="dl_id" id="fDl">
                    <option value="">— Seleziona DL —</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">CSE (Sicurezza Esecuzione)</label>
                  <select class="form-select" name="cse_id" id="fCse">
                    <option value="">— Seleziona CSE —</option>
                  </select>
                </div>
                <div class="col-12">
                  <div class="alert alert-light border">
                    <i class="bi bi-info-circle me-2 text-primary"></i>
                    Le figure aggiuntive (collaudatori, tecnici, ecc.) potranno essere aggiunte dalla pagina di dettaglio della commessa.
                  </div>
                </div>
              </div>
            </div>
          </div><!-- /tab-content -->

          <div id="formErrors" class="mt-3"></div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
        <button type="button" class="btn btn-primary" id="btnSalvaCommessa">
          <i class="bi bi-check-lg me-1"></i>Salva Commessa
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal dettaglio rapido -->
<div class="modal fade" id="quickViewModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="quickViewTitle">Dettaglio Commessa</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="quickViewBody">
        <div class="text-center py-4"><div class="spinner-border"></div></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
        <a href="#" class="btn btn-primary" id="quickViewDetail">
          <i class="bi bi-arrow-right me-1"></i>Apri Dettaglio
        </a>
      </div>
    </div>
  </div>
</div>

<?php
$extraScripts = [];
$inlineScript = <<<'JS'
// ============================================================
// STATE
// ============================================================
let currentPage  = 1;
let currentView  = 'table'; // 'table' | 'grid'
let searchTimer  = null;
let allUtenti    = [];
let editingId    = null;

// ============================================================
// INIT
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    loadSelectOptions();
    loadCommesse();
    initFilters();
    initViewToggle();
    initForm();
});

// ============================================================
// LOAD SELECT OPTIONS (SA, pm_imprese, pm_utenti)
// ============================================================
async function loadSelectOptions() {
    try {
        // Stazioni appaltanti
        const resSA = await API.get('/api/appalti.php?action=stazioni');
        if (resSA.stazioni) {
            const selSA = document.getElementById('fStazioneAppaltante');
            const filterSA = document.getElementById('filterSA');
            resSA.stazioni.forEach(sa => {
                selSA.insertAdjacentHTML('beforeend', `<option value="${sa.id}">${escapeHtml(sa.denominazione)}</option>`);
                filterSA.insertAdjacentHTML('beforeend', `<option value="${sa.id}">${escapeHtml(sa.denominazione)}</option>`);
            });
        }
        // Imprese
        const resImp = await API.get('/api/appalti.php?action=pm_imprese');
        if (resImp.data) {
            const selImp = document.getElementById('fImpresa');
            resImp.data.forEach(i => {
                selImp.insertAdjacentHTML('beforeend', `<option value="${i.id}">${escapeHtml(i.denominazione)}</option>`);
            });
        }
        // Utenti
        const resU = await API.get('/api/utenti.php?per_page=200');
        if (resU.data) {
            allUtenti = resU.data;
            ['fRup','fPm','fDl','fCse'].forEach(id => {
                const sel = document.getElementById(id);
                allUtenti.forEach(u => {
                    sel.insertAdjacentHTML('beforeend',
                        `<option value="${u.id}">${escapeHtml(u.cognome)} ${escapeHtml(u.nome)}</option>`);
                });
            });
        }
    } catch(e) {
        console.warn('Errore caricamento opzioni select', e);
    }
}

// ============================================================
// LOAD COMMESSE
// ============================================================
async function loadCommesse(page = 1) {
    currentPage = page;
    const search = document.getElementById('searchInput').value.trim();
    const stato  = document.getElementById('filterStato').value;
    const saId   = document.getElementById('filterSA').value;
    const sort   = document.getElementById('sortBy').value;

    const params = new URLSearchParams({
        page, per_page: 15,
        ...(search && { q: search }),
        ...(stato  && { stato }),
        ...(saId   && { sa_id: saId }),
    });

    try {
        const res = await API.get('/api/commesse.php?' + params.toString());
        if (currentView === 'table') renderTable(res.data || []);
        else renderGrid(res.data || []);
        renderPagination(res.page || 1, res.pages || 1, 'paginazione', loadCommesse);
        const _from = (res.total ?? 0) > 0 ? ((res.page - 1) * res.perPage + 1) : 0;
        const _to   = Math.min(res.page * res.perPage, res.total ?? 0);
        document.getElementById('paginationInfo').textContent =
            `${_from}–${_to} di ${res.total ?? 0} commesse`;
    } catch(e) {
        document.getElementById('commesseBody').innerHTML =
            `<tr><td colspan="9" class="text-center text-danger py-4"><i class="bi bi-exclamation-triangle me-2"></i>${escapeHtml(e.message)}</td></tr>`;
    }
}

// ============================================================
// RENDER KPI
// ============================================================
function renderKpi(meta) {
    if (!meta.kpi) return;
    document.getElementById('kpiTotale').textContent    = meta.kpi.totale ?? '—';
    document.getElementById('kpiEsecuzione').textContent = meta.kpi.in_esecuzione ?? '—';
    document.getElementById('kpiRitardo').textContent   = meta.kpi.in_ritardo ?? '—';
    document.getElementById('kpiValore').textContent    = meta.kpi.valore_totale_fmt ?? '—';
}

// ============================================================
// RENDER TABLE
// ============================================================
function renderTable(data) {
    const tbody = document.getElementById('commesseBody');
    if (!data.length) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center py-5 text-muted">
            <i class="bi bi-inbox fs-1 d-block mb-2"></i>Nessuna commessa trovata</td></tr>`;
        return;
    }

    tbody.innerHTML = data.map(c => {
        const avanzamento = parseFloat(c.percentuale_avanzamento) || 0;
        const barColor = avanzamento >= 80 ? 'bg-success' : avanzamento >= 40 ? 'bg-primary' : 'bg-warning';
        const scostamento = parseInt(c.scostamento_giorni) || 0;
        const scostHtml = scostamento > 0
            ? `<small class="text-danger ms-1"><i class="bi bi-arrow-up"></i>${scostamento}gg</small>` : '';

        return `<tr>
            <td class="ps-3">
                <a href="${APP_URL}/pages/commessa-detail.php?id=${c.id}" class="fw-semibold text-decoration-none">
                    ${escapeHtml(c.codice_commessa)}
                </a>
            </td>
            <td>
                <div class="fw-semibold text-truncate" style="max-width:280px" title="${escapeHtml(c.oggetto)}">
                    ${escapeHtml(c.oggetto)}
                </div>
                <small class="text-muted">${escapeHtml(c.stazione_appaltante ?? '')}</small>
            </td>
            <td class="d-none d-xl-table-cell font-monospace small">${escapeHtml(c.codice_cig ?? '—')}</td>
            <td class="fw-semibold">${Format.euro(c.importo_contrattuale)}</td>
            <td style="min-width:120px">
                <div class="d-flex align-items-center gap-2">
                    <div class="progress flex-grow-1" style="height:6px">
                        <div class="progress-bar ${barColor}" style="width:${avanzamento}%"></div>
                    </div>
                    <small class="text-muted">${avanzamento.toFixed(0)}%</small>
                    ${scostHtml}
                </div>
            </td>
            <td>${Format.badgeStato(c.stato)}</td>
            <td class="d-none d-lg-table-cell small">
                ${c.data_fine_prevista ? Format.date(c.data_fine_prevista) : '—'}
            </td>
            <td class="d-none d-md-table-cell small text-muted">
                ${escapeHtml(c.rup_nome ?? '—')}
            </td>
            <td class="text-end pe-3">
                <div class="btn-group btn-group-sm">
                    <a href="${APP_URL}/pages/commessa-detail.php?id=${c.id}"
                       class="btn btn-outline-primary btn-sm" title="Dettaglio">
                        <i class="bi bi-eye"></i>
                    </a>
                    ${canEdit(c) ? `<button class="btn btn-outline-secondary btn-sm" title="Modifica"
                        onclick="openEdit(${c.id})"><i class="bi bi-pencil"></i></button>` : ''}
                    ${canDelete(c) ? `<button class="btn btn-outline-danger btn-sm" title="Elimina"
                        onclick="deleteCommessa(${c.id}, '${escapeHtml(c.codice_commessa)}')">
                        <i class="bi bi-trash"></i></button>` : ''}
                </div>
            </td>
        </tr>`;
    }).join('');
}

// ============================================================
// RENDER GRID
// ============================================================
function renderGrid(data) {
    const grid = document.getElementById('commesseGrid');
    if (!data.length) {
        grid.innerHTML = `<div class="col-12 text-center py-5 text-muted">
            <i class="bi bi-inbox fs-1 d-block mb-2"></i>Nessuna commessa trovata</div>`;
        return;
    }

    grid.innerHTML = data.map(c => {
        const avanzamento = parseFloat(c.percentuale_avanzamento) || 0;
        const barColor = avanzamento >= 80 ? 'bg-success' : avanzamento >= 40 ? 'bg-primary' : 'bg-warning';
        return `<div class="col-12 col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom-0 pt-3 pb-1 d-flex justify-content-between">
                    <span class="badge bg-light text-dark font-monospace">${escapeHtml(c.codice_commessa)}</span>
                    ${Format.badgeStato(c.stato)}
                </div>
                <div class="card-body pt-2">
                    <h6 class="fw-bold mb-1" title="${escapeHtml(c.oggetto)}">${escapeHtml(c.oggetto.length > 60 ? c.oggetto.substring(0,60)+'...' : c.oggetto)}</h6>
                    <p class="text-muted small mb-2"><i class="bi bi-building me-1"></i>${escapeHtml(c.stazione_appaltante ?? '—')}</p>
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Avanzamento</span><span>${avanzamento.toFixed(1)}%</span>
                    </div>
                    <div class="progress mb-3" style="height:8px">
                        <div class="progress-bar ${barColor}" style="width:${avanzamento}%"></div>
                    </div>
                    <div class="row text-center g-0">
                        <div class="col border-end">
                            <small class="text-muted d-block">Importo</small>
                            <strong class="small">${Format.euro(c.importo_contrattuale)}</strong>
                        </div>
                        <div class="col">
                            <small class="text-muted d-block">Scadenza</small>
                            <strong class="small">${c.data_fine_prevista ? Format.date(c.data_fine_prevista) : '—'}</strong>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white border-top-0 d-flex gap-2">
                    <a href="${APP_URL}/pages/commessa-detail.php?id=${c.id}"
                       class="btn btn-sm btn-primary flex-grow-1">
                        <i class="bi bi-eye me-1"></i>Dettaglio
                    </a>
                    ${canEdit(c) ? `<button class="btn btn-sm btn-outline-secondary" onclick="openEdit(${c.id})">
                        <i class="bi bi-pencil"></i></button>` : ''}
                </div>
            </div>
        </div>`;
    }).join('');
}

// ============================================================
// HELPERS RBAC (lato client, solo UX - il server re-verifica)
// ============================================================
function canEdit(c)   { return true; } // API re-checks
function canDelete(c) { return c.stato !== 'IN_ESECUZIONE' && c.stato !== 'COMPLETATA'; }

// ============================================================
// VIEW TOGGLE
// ============================================================
function initViewToggle() {
    document.getElementById('btnViewTable').addEventListener('click', () => {
        currentView = 'table';
        document.getElementById('viewTable').classList.remove('d-none');
        document.getElementById('viewGrid').classList.add('d-none');
        document.getElementById('btnViewTable').classList.add('active');
        document.getElementById('btnViewGrid').classList.remove('active');
        loadCommesse(currentPage);
    });
    document.getElementById('btnViewGrid').addEventListener('click', () => {
        currentView = 'grid';
        document.getElementById('viewGrid').classList.remove('d-none');
        document.getElementById('viewTable').classList.add('d-none');
        document.getElementById('btnViewGrid').classList.add('active');
        document.getElementById('btnViewTable').classList.remove('active');
        loadCommesse(currentPage);
    });
}

// ============================================================
// FILTERS
// ============================================================
function initFilters() {
    document.getElementById('searchInput').addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadCommesse(1), 350);
    });
    ['filterStato','filterSA','sortBy'].forEach(id =>
        document.getElementById(id).addEventListener('change', () => loadCommesse(1)));
}

// ============================================================
// FORM: Open / Save / Delete
// ============================================================
function initForm() {
    document.getElementById('btnNuovaCommessa')?.addEventListener('click', openCreate);
    document.getElementById('btnSalvaCommessa').addEventListener('click', saveCommessa);

    // Calcolo importo in tempo reale
    ['fImporto','fImportoSicurezza','fRibasso'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', updateCalcoli);
    });
}

function openCreate() {
    editingId = null;
    document.getElementById('commessaModalLabel').innerHTML =
        '<i class="bi bi-plus-circle me-2"></i>Nuova Commessa';
    document.getElementById('commessaForm').reset();
    document.getElementById('commessaId').value = '';
    document.getElementById('formErrors').innerHTML = '';
    // Reset tabs to first
    document.querySelector('#commessaTabs .nav-link.active')?.click();
    new bootstrap.Modal(document.getElementById('commessaModal')).show();
}

async function openEdit(id) {
    editingId = id;
    document.getElementById('commessaModalLabel').innerHTML =
        '<i class="bi bi-pencil me-2"></i>Modifica Commessa';
    document.getElementById('formErrors').innerHTML = '';

    const modal = new bootstrap.Modal(document.getElementById('commessaModal'));
    modal.show();

    try {
        const res = await API.get(`/api/commesse.php?id=${id}`);
        const c = res.data;
        document.getElementById('commessaId').value = c.id;
        const fields = ['oggetto','cig','cup','data_consegna','data_fine_prevista','stato','note',
            'categoria_soa','classifica_soa','tipo_procedura','importo_contrattuale',
            'importo_sicurezza','ribasso_percentuale','importo_perizia',
            'costi_progettazione','fonte_finanziamento'];
        fields.forEach(f => {
            const el = document.querySelector(`[name="${f}"]`);
            if (el) el.value = c[f] ?? '';
        });
        // Select fields
        ['fStazioneAppaltante','fImpresa','fRup','fPm','fDl','fCse'].forEach(selId => {
            const nameMap = {fStazioneAppaltante:'stazione_appaltante_id', fImpresa:'impresa_id',
                fRup:'rup_id', fPm:'pm_id', fDl:'dl_id', fCse:'cse_id'};
            const sel = document.getElementById(selId);
            if (sel && c[nameMap[selId]]) sel.value = c[nameMap[selId]];
        });
        updateCalcoli();
    } catch(e) {
        UI.error('Errore caricamento commessa: ' + e.message);
    }
}

async function saveCommessa() {
    const form = document.getElementById('commessaForm');
    document.getElementById('formErrors').innerHTML = '';

    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
    }

    const data = serializeForm(form);
    data.id = document.getElementById('commessaId').value || null;

    UI.showLoader();
    try {
        if (data.id) {
            await API.put('/api/commesse.php', data);
            UI.success('Commessa aggiornata con successo');
        } else {
            await API.post('/api/commesse.php', data);
            UI.success('Commessa creata con successo');
        }
        bootstrap.Modal.getInstance(document.getElementById('commessaModal')).hide();
        loadCommesse(currentPage);
    } catch(e) {
        if (e.errors) showFormErrors(e.errors, 'formErrors');
        else document.getElementById('formErrors').innerHTML =
            `<div class="alert alert-danger">${escapeHtml(e.message)}</div>`;
    } finally {
        UI.hideLoader();
    }
}

async function deleteCommessa(id, codice) {
    const ok = await UI.confirm(
        `Eliminare la commessa <strong>${escapeHtml(codice)}</strong>?<br>
        <small class="text-muted">L'operazione è irreversibile.</small>`);
    if (!ok) return;

    UI.showLoader();
    try {
        await API.delete('/api/commesse.php', { id });
        UI.success('Commessa eliminata');
        loadCommesse(currentPage);
    } catch(e) {
        UI.error(e.message);
    } finally {
        UI.hideLoader();
    }
}

function updateCalcoli() {
    const imp  = parseFloat(document.getElementById('fImporto')?.value) || 0;
    const sic  = parseFloat(document.getElementById('fImportoSicurezza')?.value) || 0;
    const rib  = parseFloat(document.getElementById('fRibasso')?.value) || 0;
    const tot  = imp + sic;
    const netto = tot * (1 - rib / 100);
    document.getElementById('calcTotale').textContent    = Format.euro(tot);
    document.getElementById('calcConRibasso').textContent = Format.euro(netto);
}
JS;
include __DIR__ . '/../components/footer.php';
