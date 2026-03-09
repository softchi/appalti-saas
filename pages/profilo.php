<?php
/**
 * Pagina: Profilo Utente
 */
define('APP_INIT', true);
require_once __DIR__ . '/../php/bootstrap.php';
Auth::require();

$pageTitle  = 'Il Mio Profilo';
$activeMenu = 'profilo';
$currentUser = Auth::user();
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/sidebar.php';
?>

<div class="container-fluid px-4 py-3" style="max-width:900px">

  <div class="mb-4">
    <h1 class="h4 mb-1 fw-bold">Il Mio Profilo</h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/pages/dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Profilo</li>
      </ol>
    </nav>
  </div>

  <div class="row g-4">

    <!-- Avatar + info -->
    <div class="col-md-4">
      <div class="card border-0 shadow-sm text-center p-4">
        <div class="mx-auto mb-3 rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold"
             style="width:90px;height:90px;font-size:2rem" id="avatarCircle">
          <?= e(mb_strtoupper(mb_substr($currentUser['nome'],0,1) . mb_substr($currentUser['cognome'],0,1))) ?>
        </div>
        <h5 class="fw-bold mb-0" id="displayName">
          <?= e($currentUser['cognome'] . ' ' . $currentUser['nome']) ?>
        </h5>
        <p class="text-muted small mb-2" id="displayEmail"><?= e($currentUser['email']) ?></p>
        <span class="badge bg-primary" id="displayRuolo"><?= e($currentUser['ruolo_nome'] ?? '—') ?></span>
        <hr>
        <dl class="text-start small mb-0">
          <dt class="text-muted">Qualifica</dt>
          <dd id="displayQualifica"><?= e($currentUser['qualifica'] ?? '—') ?></dd>
          <dt class="text-muted">Telefono</dt>
          <dd id="displayTelefono"><?= e($currentUser['telefono'] ?? '—') ?></dd>
          <dt class="text-muted">Registrato il</dt>
          <dd><?= $currentUser['created_at'] ? date('d/m/Y', strtotime($currentUser['created_at'])) : '—' ?></dd>
          <dt class="text-muted">Ultimo accesso</dt>
          <dd id="displayUltimoAccesso"><?= $currentUser['ultimo_accesso'] ? date('d/m/Y H:i', strtotime($currentUser['ultimo_accesso'])) : 'N/D' ?></dd>
        </dl>
      </div>
    </div>

    <!-- Tabs dati / sicurezza -->
    <div class="col-md-8">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom-0">
          <ul class="nav nav-tabs card-header-tabs" id="profiloTabs">
            <li class="nav-item">
              <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabDatiPersonali">
                <i class="bi bi-person me-1"></i>Dati Personali
              </button>
            </li>
            <li class="nav-item">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabSicurezza">
                <i class="bi bi-shield-lock me-1"></i>Sicurezza
              </button>
            </li>
            <li class="nav-item">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabNotifiche">
                <i class="bi bi-bell me-1"></i>Notifiche
              </button>
            </li>
          </ul>
        </div>
        <div class="card-body tab-content">

          <!-- Dati personali -->
          <div class="tab-pane fade show active" id="tabDatiPersonali">
            <form id="profiloForm" novalidate>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Nome <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" name="nome" id="fNome"
                         value="<?= e($currentUser['nome']) ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Cognome <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" name="cognome" id="fCognome"
                         value="<?= e($currentUser['cognome']) ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                  <input type="email" class="form-control" name="email" id="fEmail"
                         value="<?= e($currentUser['email']) ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Telefono</label>
                  <input type="tel" class="form-control" name="telefono" id="fTelefono"
                         value="<?= e($currentUser['telefono'] ?? '') ?>"
                         placeholder="+39 0xx xxxx xxxx">
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold">Qualifica Professionale</label>
                  <input type="text" class="form-control" name="qualifica" id="fQualifica"
                         value="<?= e($currentUser['qualifica'] ?? '') ?>"
                         placeholder="es. Architetto, Ing. Civile, RUP...">
                </div>
              </div>
              <div id="profiloErrors" class="mt-3"></div>
              <div class="mt-3">
                <button type="button" class="btn btn-primary" id="btnSalvaProfilo">
                  <i class="bi bi-check-lg me-1"></i>Salva Dati
                </button>
              </div>
            </form>
          </div>

          <!-- Sicurezza -->
          <div class="tab-pane fade" id="tabSicurezza">
            <form id="passwordForm" novalidate>
              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label fw-semibold">Password Attuale <span class="text-danger">*</span></label>
                  <input type="password" class="form-control" name="current_password" required
                         autocomplete="current-password">
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Nuova Password <span class="text-danger">*</span></label>
                  <input type="password" class="form-control" name="new_password" id="fNewPwd"
                         required minlength="8" autocomplete="new-password">
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Conferma Password <span class="text-danger">*</span></label>
                  <input type="password" class="form-control" name="confirm_password" id="fConfirmPwd"
                         required minlength="8" autocomplete="new-password">
                </div>
                <div class="col-12">
                  <div class="alert alert-light border small">
                    <i class="bi bi-info-circle me-2 text-primary"></i>
                    La password deve essere di almeno 8 caratteri. Si consiglia di usare lettere maiuscole, minuscole, numeri e simboli.
                  </div>
                </div>
              </div>
              <div id="pwdErrors" class="mt-3"></div>
              <div class="mt-3">
                <button type="button" class="btn btn-warning" id="btnCambiaPwd">
                  <i class="bi bi-shield-lock me-1"></i>Cambia Password
                </button>
              </div>
            </form>

            <hr class="my-4">

            <!-- Sessioni attive -->
            <h6 class="fw-semibold mb-3">Sessioni Attive</h6>
            <div id="sessioniList">
              <div class="spinner-border spinner-border-sm"></div>
            </div>
            <button class="btn btn-outline-danger btn-sm mt-3" id="btnLogoutAll">
              <i class="bi bi-box-arrow-right me-1"></i>Esci da tutti i dispositivi
            </button>
          </div>

          <!-- Notifiche -->
          <div class="tab-pane fade" id="tabNotifiche">
            <h6 class="fw-semibold mb-3">Preferenze Notifiche</h6>
            <div class="list-group list-group-flush" id="notifichePrefs">
              <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                  <strong class="d-block">Scadenze imminenti</strong>
                  <small class="text-muted">Notifica 7 giorni prima della scadenza</small>
                </div>
                <div class="form-check form-switch mb-0">
                  <input class="form-check-input" type="checkbox" id="notifScadenze" checked>
                </div>
              </div>
              <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                  <strong class="d-block">SAL da approvare</strong>
                  <small class="text-muted">Notifica quando viene emesso un nuovo SAL</small>
                </div>
                <div class="form-check form-switch mb-0">
                  <input class="form-check-input" type="checkbox" id="notifSal" checked>
                </div>
              </div>
              <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                  <strong class="d-block">Task assegnati</strong>
                  <small class="text-muted">Notifica quando un task viene assegnato</small>
                </div>
                <div class="form-check form-switch mb-0">
                  <input class="form-check-input" type="checkbox" id="notifTasks" checked>
                </div>
              </div>
              <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                  <strong class="d-block">Email digest giornaliero</strong>
                  <small class="text-muted">Riepilogo email ogni mattina alle 08:00</small>
                </div>
                <div class="form-check form-switch mb-0">
                  <input class="form-check-input" type="checkbox" id="notifEmail">
                </div>
              </div>
            </div>
            <div class="mt-3">
              <button class="btn btn-primary" id="btnSalvaNotifiche">
                <i class="bi bi-check-lg me-1"></i>Salva Preferenze
              </button>
            </div>
          </div>

        </div>
      </div>
    </div>

  </div><!-- /row -->
</div>

<?php
$inlineScript = <<<'JS'
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('btnSalvaProfilo').addEventListener('click', salvaProfilo);
    document.getElementById('btnCambiaPwd').addEventListener('click', cambiaPwd);
    document.getElementById('btnLogoutAll').addEventListener('click', logoutAll);
    document.getElementById('btnSalvaNotifiche').addEventListener('click', () => UI.success('Preferenze salvate'));

    // Load sessioni on tab open
    document.querySelector('[data-bs-target="#tabSicurezza"]').addEventListener('click', loadSessioni, {once:true});
});

async function salvaProfilo() {
    const form = document.getElementById('profiloForm');
    if (!form.checkValidity()) { form.classList.add('was-validated'); return; }
    const data = serializeForm(form);
    UI.showLoader();
    try {
        await API.put('/api/utenti.php', data);
        UI.success('Profilo aggiornato');
        // Aggiorna UI
        document.getElementById('displayName').textContent =
            (data.cognome ?? '') + ' ' + (data.nome ?? '');
        document.getElementById('displayEmail').textContent = data.email ?? '';
        document.getElementById('displayQualifica').textContent = data.qualifica || '—';
        document.getElementById('displayTelefono').textContent = data.telefono || '—';
        const initials = ((data.nome?.charAt(0)??'') + (data.cognome?.charAt(0)??'')).toUpperCase();
        document.getElementById('avatarCircle').textContent = initials;
    } catch(e) {
        if (e.errors) showFormErrors(e.errors, 'profiloErrors');
        else document.getElementById('profiloErrors').innerHTML =
            `<div class="alert alert-danger">${escapeHtml(e.message)}</div>`;
    } finally { UI.hideLoader(); }
}

async function cambiaPwd() {
    const form = document.getElementById('passwordForm');
    document.getElementById('pwdErrors').innerHTML = '';
    if (!form.checkValidity()) { form.classList.add('was-validated'); return; }
    const np = document.getElementById('fNewPwd').value;
    const cp = document.getElementById('fConfirmPwd').value;
    if (np !== cp) {
        document.getElementById('pwdErrors').innerHTML =
            '<div class="alert alert-danger">Le password non coincidono</div>';
        return;
    }
    UI.showLoader();
    try {
        await API.post('/api/utenti.php?action=change_password', serializeForm(form));
        UI.success('Password cambiata con successo');
        form.reset();
    } catch(e) {
        document.getElementById('pwdErrors').innerHTML =
            `<div class="alert alert-danger">${escapeHtml(e.message)}</div>`;
    } finally { UI.hideLoader(); }
}

async function loadSessioni() {
    const wrap = document.getElementById('sessioniList');
    try {
        const res = await API.get('/api/utenti.php?action=sessioni');
        const list = res.data || [];
        if (!list.length) {
            wrap.innerHTML = '<p class="text-muted small">Nessuna sessione trovata</p>';
            return;
        }
        wrap.innerHTML = list.map(s => `
            <div class="d-flex align-items-center justify-content-between border rounded p-2 mb-2">
                <div class="small">
                    <i class="bi bi-pc-display me-2 text-muted"></i>
                    <span class="text-muted">${escapeHtml(s.ip_address ?? '—')}</span>
                    <span class="ms-2 text-muted">${s.created_at ? Format.datetime(s.created_at) : ''}</span>
                    ${s.current ? '<span class="badge bg-success ms-2">Corrente</span>' : ''}
                </div>
            </div>`).join('');
    } catch(e) {
        wrap.innerHTML = '<p class="text-muted small">Errore caricamento sessioni</p>';
    }
}

async function logoutAll() {
    const ok = await UI.confirm('Vuoi disconnetterti da tutti i dispositivi?<br><small>Sarai reindirizzato al login.</small>');
    if (!ok) return;
    try {
        await API.post('/api/utenti.php?action=logout_all', {});
        window.location.href = API.getAppUrl() + '/login.php';
    } catch(e) { UI.error(e.message); }
}
JS;
include __DIR__ . '/../components/footer.php';
