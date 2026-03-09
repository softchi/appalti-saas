<?php
/**
 * Pagina: Gestione Utenti (solo admin)
 */
define('APP_INIT', true);
require_once __DIR__ . '/../php/bootstrap.php';
Auth::require();
if (!Auth::can('pm_utenti.read')) {
    header('Location: ' . APP_URL . '/pages/dashboard.php');
    exit;
}

$pageTitle  = 'Gestione Utenti';
$activeMenu = 'pm_utenti';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/sidebar.php';
?>

<div class="container-fluid px-4 py-3">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h1 class="h4 mb-1 fw-bold">Gestione Utenti</h1>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
          <li class="breadcrumb-item"><a href="<?= APP_URL ?>/pages/dashboard.php">Dashboard</a></li>
          <li class="breadcrumb-item active">Utenti</li>
        </ol>
      </nav>
    </div>
    <?php if (Auth::can('pm_utenti.create')): ?>
    <button class="btn btn-primary" id="btnNuovoUtente">
      <i class="bi bi-person-plus me-2"></i>Nuovo Utente
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
                   placeholder="Cerca per nome, email...">
          </div>
        </div>
        <div class="col-6 col-md-2">
          <select class="form-select form-select-sm" id="filterRuolo">
            <option value="">Tutti i pm_ruoli</option>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <select class="form-select form-select-sm" id="filterStato">
            <option value="">Tutti gli stati</option>
            <option value="1">Attivi</option>
            <option value="0">Disabilitati</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  <!-- Tabella pm_utenti -->
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="ps-3">Utente</th>
              <th>Email</th>
              <th>Ruolo</th>
              <th>Telefono</th>
              <th>Ultimo Accesso</th>
              <th>Stato</th>
              <th class="text-end pe-3">Azioni</th>
            </tr>
          </thead>
          <tbody id="utentiBody">
            <tr><td colspan="7" class="text-center py-5 text-muted">
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

<!-- Modal Utente -->
<div class="modal fade" id="utenteModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="utenteModalLabel">
          <i class="bi bi-person-plus me-2"></i>Nuovo Utente
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="utenteForm" novalidate>
          <input type="hidden" id="utenteId">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Nome <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="nome" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Cognome <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="cognome" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
              <input type="email" class="form-control" name="email" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Telefono</label>
              <input type="tel" class="form-control" name="telefono" placeholder="+39 0xx xxxx xxxx">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Ruolo <span class="text-danger">*</span></label>
              <select class="form-select" name="ruolo_id" id="fRuolo" required>
                <option value="">— Seleziona —</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Qualifica</label>
              <input type="text" class="form-control" name="qualifica"
                     placeholder="es. Architetto, Ing. Civile...">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Password
                <span class="text-danger" id="pwdRequired">*</span>
                <small class="text-muted fw-normal" id="pwdOptional" style="display:none">(vuoto = nessun cambio)</small>
              </label>
              <div class="input-group">
                <input type="password" class="form-control" name="password" id="fPassword"
                       minlength="8" autocomplete="new-password">
                <button type="button" class="btn btn-outline-secondary" id="togglePwd">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
              <div class="form-text">Minimo 8 caratteri.</div>
            </div>
            <div class="col-md-6">
              <div class="form-check form-switch mt-2">
                <input class="form-check-input" type="checkbox" name="attivo" id="fAttivo"
                       value="1" checked>
                <label class="form-check-label" for="fAttivo">Utente attivo</label>
              </div>
            </div>
          </div>
          <div id="utenteFormErrors" class="mt-3"></div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
        <button type="button" class="btn btn-primary" id="btnSalvaUtente">
          <i class="bi bi-check-lg me-1"></i>Salva
        </button>
      </div>
    </div>
  </div>
</div>

<?php
$inlineScript = <<<'JS'
let currentPage = 1;
let searchTimer = null;

document.addEventListener('DOMContentLoaded', () => {
    loadRuoli();
    loadUtenti();
    initFilters();
    document.getElementById('btnNuovoUtente')?.addEventListener('click', openCreate);
    document.getElementById('btnSalvaUtente').addEventListener('click', salvaUtente);
    document.getElementById('togglePwd').addEventListener('click', () => {
        const inp = document.getElementById('fPassword');
        const ico = document.getElementById('togglePwd').querySelector('i');
        inp.type = inp.type === 'password' ? 'text' : 'password';
        ico.className = inp.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
    });
});

async function loadRuoli() {
    try {
        const res = await API.get('/api/pm_utenti.php?action=pm_ruoli');
        const pm_ruoli = res.data || [];
        const selRuolo = document.getElementById('fRuolo');
        const filterRuolo = document.getElementById('filterRuolo');
        pm_ruoli.forEach(r => {
            const opt = `<option value="${r.id}">${escapeHtml(r.nome)}</option>`;
            selRuolo.insertAdjacentHTML('beforeend', opt);
            filterRuolo.insertAdjacentHTML('beforeend', opt);
        });
    } catch(e) {}
}

async function loadUtenti(page = 1) {
    currentPage = page;
    const search = document.getElementById('searchInput').value.trim();
    const ruolo  = document.getElementById('filterRuolo').value;
    const stato  = document.getElementById('filterStato').value;

    const params = new URLSearchParams({
        page, per_page: 20,
        ...(search && { search }),
        ...(ruolo  && { ruolo_id: ruolo }),
        ...(stato !== '' && { attivo: stato }),
    });

    try {
        const res = await API.get('/api/pm_utenti.php?' + params.toString());
        renderTable(res.data || []);
        const meta = res.meta || {};
        renderPagination(meta.current_page||1, meta.last_page||1, 'paginazione', loadUtenti);
        document.getElementById('paginationInfo').textContent =
            `${meta.from??0}–${meta.to??0} di ${meta.total??0} pm_utenti`;
    } catch(e) {
        document.getElementById('utentiBody').innerHTML =
            `<tr><td colspan="7" class="text-danger text-center py-4">${escapeHtml(e.message)}</td></tr>`;
    }
}

function renderTable(data) {
    const tbody = document.getElementById('utentiBody');
    if (!data.length) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center py-5 text-muted">
            <i class="bi bi-people fs-1 d-block mb-2"></i>Nessun utente</td></tr>`;
        return;
    }
    tbody.innerHTML = data.map(u => {
        const initials = (u.nome?.charAt(0)??'') + (u.cognome?.charAt(0)??'');
        const colors   = ['primary','success','warning','info','danger','secondary'];
        const colorIdx = (u.id || 0) % colors.length;
        return `<tr>
            <td class="ps-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-${colors[colorIdx]} text-white d-flex align-items-center justify-content-center fw-bold flex-shrink-0"
                         style="width:36px;height:36px;font-size:.8rem">
                        ${escapeHtml(initials.toUpperCase())}
                    </div>
                    <div>
                        <div class="fw-semibold">${escapeHtml(u.cognome)} ${escapeHtml(u.nome)}</div>
                        <small class="text-muted">${escapeHtml(u.qualifica??'')}</small>
                    </div>
                </div>
            </td>
            <td class="small">${escapeHtml(u.email)}</td>
            <td><span class="badge bg-light text-dark border">${escapeHtml(u.ruolo_nome??'—')}</span></td>
            <td class="small text-muted">${escapeHtml(u.telefono??'—')}</td>
            <td class="small text-muted">${u.ultimo_accesso ? Format.datetime(u.ultimo_accesso) : 'Mai'}</td>
            <td>
                ${parseInt(u.attivo) ? '<span class="badge bg-success">Attivo</span>' : '<span class="badge bg-secondary">Disabilitato</span>'}
            </td>
            <td class="text-end pe-3">
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary" onclick="openEdit(${u.id})" title="Modifica">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-outline-${parseInt(u.attivo)?'warning':'success'}"
                            onclick="toggleAttivo(${u.id}, ${u.attivo})"
                            title="${parseInt(u.attivo)?'Disabilita':'Abilita'}">
                        <i class="bi bi-${parseInt(u.attivo)?'person-slash':'person-check'}"></i>
                    </button>
                    <button class="btn btn-outline-danger" onclick="eliminaUtente(${u.id}, '${escapeHtml(u.email)}')"
                            title="Elimina">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </td>
        </tr>`;
    }).join('');
}

function initFilters() {
    document.getElementById('searchInput').addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadUtenti(1), 350);
    });
    ['filterRuolo','filterStato'].forEach(id =>
        document.getElementById(id).addEventListener('change', () => loadUtenti(1)));
}

function openCreate() {
    document.getElementById('utenteId').value = '';
    document.getElementById('utenteModalLabel').innerHTML =
        '<i class="bi bi-person-plus me-2"></i>Nuovo Utente';
    document.getElementById('utenteForm').reset();
    document.getElementById('fAttivo').checked = true;
    document.getElementById('utenteFormErrors').innerHTML = '';
    document.getElementById('fPassword').required = true;
    document.getElementById('pwdRequired').style.display = '';
    document.getElementById('pwdOptional').style.display = 'none';
    new bootstrap.Modal(document.getElementById('utenteModal')).show();
}

async function openEdit(id) {
    document.getElementById('utenteModalLabel').innerHTML =
        '<i class="bi bi-pencil me-2"></i>Modifica Utente';
    document.getElementById('utenteFormErrors').innerHTML = '';
    document.getElementById('fPassword').required = false;
    document.getElementById('pwdRequired').style.display = 'none';
    document.getElementById('pwdOptional').style.display = '';
    const modal = new bootstrap.Modal(document.getElementById('utenteModal'));
    modal.show();
    try {
        const res = await API.get('/api/pm_utenti.php?id=' + id);
        const u = res.data;
        document.getElementById('utenteId').value = u.id;
        const form = document.getElementById('utenteForm');
        ['nome','cognome','email','telefono','qualifica'].forEach(f => {
            const el = form.querySelector('[name="'+f+'"]');
            if (el) el.value = u[f] ?? '';
        });
        if (u.ruolo_id) document.getElementById('fRuolo').value = u.ruolo_id;
        document.getElementById('fAttivo').checked = parseInt(u.attivo) === 1;
        document.getElementById('fPassword').value = '';
    } catch(e) { UI.error(e.message); }
}

async function salvaUtente() {
    const form = document.getElementById('utenteForm');
    if (!form.checkValidity()) { form.classList.add('was-validated'); return; }
    const data = serializeForm(form);
    data.id = document.getElementById('utenteId').value || null;
    data.attivo = document.getElementById('fAttivo').checked ? 1 : 0;
    if (!data.password) delete data.password;
    UI.showLoader();
    try {
        if (data.id) {
            await API.put('/api/pm_utenti.php', data);
            UI.success('Utente aggiornato');
        } else {
            await API.post('/api/pm_utenti.php', data);
            UI.success('Utente creato. Password inviata via email.');
        }
        bootstrap.Modal.getInstance(document.getElementById('utenteModal')).hide();
        loadUtenti(currentPage);
    } catch(e) {
        if (e.errors) showFormErrors(e.errors, 'utenteFormErrors');
        else document.getElementById('utenteFormErrors').innerHTML =
            `<div class="alert alert-danger">${escapeHtml(e.message)}</div>`;
    } finally { UI.hideLoader(); }
}

async function toggleAttivo(id, attivo) {
    const newVal = parseInt(attivo) ? 0 : 1;
    const label  = newVal ? 'abilitare' : 'disabilitare';
    const ok = await UI.confirm(`Vuoi ${label} questo utente?`);
    if (!ok) return;
    try {
        await API.put('/api/pm_utenti.php', { id, attivo: newVal });
        UI.success('Utente aggiornato');
        loadUtenti(currentPage);
    } catch(e) { UI.error(e.message); }
}

async function eliminaUtente(id, email) {
    const ok = await UI.confirm(`Eliminare definitivamente l'utente <strong>${escapeHtml(email)}</strong>?`);
    if (!ok) return;
    try {
        await API.delete('/api/pm_utenti.php', { id });
        UI.success('Utente eliminato');
        loadUtenti(currentPage);
    } catch(e) { UI.error(e.message); }
}
JS;
include __DIR__ . '/../components/footer.php';
