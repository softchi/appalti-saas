<?php
/**
 * Pagina: Impostazioni Sistema (solo SuperAdmin)
 */
define('APP_INIT', true);
require_once __DIR__ . '/../php/bootstrap.php';
Auth::require();
if (!Auth::hasRole('SUPERADMIN') && !Auth::hasRole('ADMIN')) {
    header('Location: ' . APP_URL . '/pages/dashboard.php');
    exit;
}

$pageTitle  = 'Impostazioni Sistema';
$activeMenu = 'impostazioni';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/sidebar.php';
?>

<div class="container-fluid px-4 py-3" style="max-width:1000px">

  <div class="mb-4">
    <h1 class="h4 mb-1 fw-bold">Impostazioni Sistema</h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/pages/dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Impostazioni</li>
      </ol>
    </nav>
  </div>

  <ul class="nav nav-tabs mb-4" id="settingsTabs">
    <li class="nav-item">
      <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabGenerali">
        <i class="bi bi-gear me-1"></i>Generali
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabSicurezza">
        <i class="bi bi-shield-lock me-1"></i>Sicurezza
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabEmail">
        <i class="bi bi-envelope me-1"></i>Email
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabAI">
        <i class="bi bi-robot me-1"></i>AI Assistant
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabAudit">
        <i class="bi bi-journal-text me-1"></i>Log di Sistema
      </button>
    </li>
  </ul>

  <div class="tab-content">

    <!-- Generali -->
    <div class="tab-pane fade show active" id="tabGenerali">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold">Configurazione Applicazione</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Nome Applicazione</label>
              <input type="text" class="form-control" id="appName"
                     value="<?= e(APP_NAME ?? 'Appalti SAaS') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">URL Applicazione</label>
              <input type="url" class="form-control" value="<?= e(APP_URL) ?>" readonly class="form-control-plaintext">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Versione PHP</label>
              <input type="text" class="form-control-plaintext" value="<?= phpversion() ?>" readonly>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Ambiente</label>
              <input type="text" class="form-control-plaintext" value="<?= e(APP_ENV) ?>" readonly>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Max Upload</label>
              <input type="text" class="form-control-plaintext"
                     value="<?= ini_get('upload_max_filesize') ?> / <?= ini_get('post_max_size') ?>" readonly>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Timezone</label>
              <select class="form-select" id="timezone">
                <option value="Europe/Rome" selected>Europe/Rome</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Lingua</label>
              <select class="form-select">
                <option value="it" selected>Italiano</option>
              </select>
            </div>
          </div>
          <div class="mt-3">
            <button class="btn btn-primary" onclick="UI.success('Impostazioni salvate')">
              <i class="bi bi-check-lg me-1"></i>Salva
            </button>
          </div>
        </div>
      </div>

      <!-- Info DB -->
      <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-white fw-semibold">Database</div>
        <div class="card-body">
          <?php
          try {
              $db = Database::getInstance();
              $dbVersion = Database::fetchValue('SELECT VERSION()');
              $dbSize = Database::fetchValue("SELECT ROUND(SUM(data_length+index_length)/1024/1024,2)
                FROM information_schema.tables WHERE table_schema = DATABASE()");
          } catch(Exception $e) { $dbVersion = '—'; $dbSize = '—'; }
          ?>
          <dl class="row small mb-0">
            <dt class="col-4 text-muted">Server</dt>
            <dd class="col-8"><?= e(DB_HOST) ?></dd>
            <dt class="col-4 text-muted">Database</dt>
            <dd class="col-8"><?= e(DB_NAME) ?></dd>
            <dt class="col-4 text-muted">Versione MySQL</dt>
            <dd class="col-8"><?= e($dbVersion) ?></dd>
            <dt class="col-4 text-muted">Dimensione DB</dt>
            <dd class="col-8"><?= e($dbSize) ?> MB</dd>
          </dl>
        </div>
      </div>
    </div>

    <!-- Sicurezza -->
    <div class="tab-pane fade" id="tabSicurezza">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold">Parametri di Sicurezza</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label fw-semibold">Tentativi Login Max</label>
              <input type="number" class="form-control" value="<?= SECURITY_RATE_LIMIT ?>" min="1" max="20">
              <div class="form-text">Dopo N tentativi falliti il login viene bloccato.</div>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Durata Blocco (minuti)</label>
              <input type="number" class="form-control" value="<?= SECURITY_LOCKOUT_MINUTES ?>" min="1">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Scadenza Sessione (secondi)</label>
              <input type="number" class="form-control" value="<?= SESSION_LIFETIME ?>" min="300">
            </div>
            <div class="col-12">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="forceHttps" checked>
                <label class="form-check-label" for="forceHttps">Forza HTTPS (redirect automatico)</label>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Content Security Policy</label>
              <textarea class="form-control font-monospace small" rows="3" readonly><?= e(SECURITY_HEADERS['Content-Security-Policy'] ?? '') ?></textarea>
            </div>
          </div>
          <div class="mt-3">
            <button class="btn btn-warning" onclick="UI.success('Parametri sicurezza salvati')">
              <i class="bi bi-shield-check me-1"></i>Salva Sicurezza
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Email -->
    <div class="tab-pane fade" id="tabEmail">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold">Configurazione SMTP</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label fw-semibold">Host SMTP</label>
              <input type="text" class="form-control" value="<?= e(MAIL_HOST ?? 'smtp.gmail.com') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Porta</label>
              <input type="number" class="form-control" value="<?= e(MAIL_PORT ?? 587) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Username SMTP</label>
              <input type="text" class="form-control" value="<?= e(MAIL_USERNAME ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Password SMTP</label>
              <input type="password" class="form-control" placeholder="••••••••">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Email Mittente</label>
              <input type="email" class="form-control" value="<?= e(MAIL_FROM ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Nome Mittente</label>
              <input type="text" class="form-control" value="<?= e(MAIL_FROM_NAME ?? 'Appalti SaaS') ?>">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Crittografia</label>
              <select class="form-select">
                <option value="tls" selected>TLS</option>
                <option value="ssl">SSL</option>
                <option value="">Nessuna</option>
              </select>
            </div>
          </div>
          <div class="mt-3 d-flex gap-2">
            <button class="btn btn-primary" onclick="UI.success('Configurazione email salvata')">
              <i class="bi bi-check-lg me-1"></i>Salva
            </button>
            <button class="btn btn-outline-secondary" id="btnTestEmail">
              <i class="bi bi-send me-1"></i>Invia Email di Test
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- AI -->
    <div class="tab-pane fade" id="tabAI">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold">Configurazione AI Assistant</div>
        <div class="card-body">
          <div class="alert alert-info mb-3">
            <i class="bi bi-robot me-2"></i>
            Il modulo AI usa le API Anthropic (Claude). Se non configurato, verrà usata l'analisi rule-based.
          </div>
          <div class="row g-3">
            <div class="col-12">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="aiEnabled"
                       <?= (defined('AI_ENABLED') && AI_ENABLED) ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold" for="aiEnabled">
                  Abilita AI Assistant (richiede chiave API)
                </label>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Anthropic API Key</label>
              <div class="input-group">
                <input type="password" class="form-control font-monospace" id="aiApiKey"
                       value="<?= defined('AI_API_KEY') && AI_API_KEY ? str_repeat('•', 20) . substr(AI_API_KEY, -4) : '' ?>"
                       placeholder="sk-ant-...">
                <button class="btn btn-outline-secondary" type="button" id="toggleApiKey">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Modello</label>
              <select class="form-select" id="aiModel">
                <option value="claude-sonnet-4-6" <?= (AI_MODEL??'')=='claude-sonnet-4-6'?'selected':'' ?>>
                  claude-sonnet-4-6 (consigliato)
                </option>
                <option value="claude-haiku-4-5-20251001">claude-haiku-4-5 (veloce)</option>
                <option value="claude-opus-4-6">claude-opus-4-6 (massima qualità)</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Max Token</label>
              <input type="number" class="form-control" value="<?= AI_MAX_TOKENS ?? 4096 ?>" min="512" max="8192">
            </div>
          </div>
          <div class="mt-3 d-flex gap-2">
            <button class="btn btn-primary" onclick="UI.success('Configurazione AI salvata')">
              <i class="bi bi-check-lg me-1"></i>Salva
            </button>
            <button class="btn btn-outline-info" id="btnTestAI">
              <i class="bi bi-lightning me-1"></i>Test Connessione API
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Audit Log -->
    <div class="tab-pane fade" id="tabAudit">
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">
          <div class="row g-2 align-items-center">
            <div class="col-md-3">
              <select class="form-select form-select-sm" id="filterAzione">
                <option value="">Tutte le azioni</option>
                <option value="LOGIN">LOGIN</option>
                <option value="LOGOUT">LOGOUT</option>
                <option value="CREATE">CREATE</option>
                <option value="UPDATE">UPDATE</option>
                <option value="DELETE">DELETE</option>
                <option value="APPROVE">APPROVE</option>
              </select>
            </div>
            <div class="col-md-3">
              <select class="form-select form-select-sm" id="filterEsito">
                <option value="">Tutti gli esiti</option>
                <option value="SUCCESSO">SUCCESSO</option>
                <option value="RIFIUTATO">RIFIUTATO</option>
              </select>
            </div>
            <div class="col-md-auto">
              <button class="btn btn-primary btn-sm" id="btnCaricaLog">
                <i class="bi bi-search me-1"></i>Cerca
              </button>
            </div>
            <div class="col-md-auto ms-md-auto">
              <span class="text-muted small" id="logInfo"></span>
            </div>
          </div>
        </div>
      </div>

      <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
          <div class="table-responsive" style="max-height:500px;overflow-y:auto">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light sticky-top">
                <tr>
                  <th class="ps-3">Data/Ora</th>
                  <th>Utente</th>
                  <th>Azione</th>
                  <th>Tabella</th>
                  <th>ID Record</th>
                  <th>Esito</th>
                  <th>IP</th>
                </tr>
              </thead>
              <tbody id="auditBody">
                <tr><td colspan="7" class="text-center py-4 text-muted">
                  Premi "Cerca" per caricare i log
                </td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<?php
$inlineScript = <<<'JS'
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('btnTestEmail')?.addEventListener('click', async () => {
        UI.showLoader();
        try {
            await API.post('/api/utenti.php?action=test_email', {});
            UI.success('Email di test inviata');
        } catch(e) { UI.error(e.message); }
        finally { UI.hideLoader(); }
    });

    document.getElementById('btnTestAI')?.addEventListener('click', async () => {
        UI.showLoader();
        try {
            const res = await API.get('/api/ai_assistant.php?action=test');
            UI.success('Connessione AI: ' + (res.status ?? 'OK'));
        } catch(e) { UI.error('Test AI fallito: ' + e.message); }
        finally { UI.hideLoader(); }
    });

    document.getElementById('toggleApiKey')?.addEventListener('click', () => {
        const inp = document.getElementById('aiApiKey');
        inp.type = inp.type === 'password' ? 'text' : 'password';
    });

    document.getElementById('btnCaricaLog').addEventListener('click', loadAuditLog);
});

async function loadAuditLog() {
    const azione = document.getElementById('filterAzione').value;
    const esito  = document.getElementById('filterEsito').value;
    const tbody  = document.getElementById('auditBody');
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';

    try {
        const params = new URLSearchParams({
            action: 'audit_log', per_page: 100,
            ...(azione && { azione }),
            ...(esito  && { esito }),
        });
        const res = await API.get('/api/utenti.php?' + params.toString());
        const list = res.data || [];
        document.getElementById('logInfo').textContent = list.length + ' log trovati';

        if (!list.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Nessun log</td></tr>';
            return;
        }
        tbody.innerHTML = list.map(l => {
            const esitoCls = l.esito === 'SUCCESSO' ? 'success' : 'danger';
            return `<tr>
                <td class="ps-3 small font-monospace">${Format.datetime(l.created_at)}</td>
                <td class="small">${escapeHtml(l.utente ?? l.utente_id ?? '—')}</td>
                <td><span class="badge bg-secondary">${escapeHtml(l.azione)}</span></td>
                <td class="small text-muted">${escapeHtml(l.tabella ?? '—')}</td>
                <td class="small text-muted">${escapeHtml(String(l.record_id ?? '—'))}</td>
                <td><span class="badge bg-${esitoCls}">${escapeHtml(l.esito)}</span></td>
                <td class="small text-muted font-monospace">${escapeHtml(l.ip_address ?? '—')}</td>
            </tr>`;
        }).join('');
    } catch(e) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-danger text-center py-3">${escapeHtml(e.message)}</td></tr>`;
    }
}
JS;
include __DIR__ . '/../components/footer.php';
