<?php
/**
 * Pagina Login
 */
define('APP_INIT', true);
require_once __DIR__ . '/php/bootstrap.php';

// Già autenticato → redirect dashboard
if (Auth::check()) {
    redirect(APP_URL . '/pages/dashboard.php');
}

$error    = '';
$redirect = sanitizeString($_GET['redirect'] ?? '');
$expired  = isset($_GET['session_expired']);
?>
<!DOCTYPE html>
<html lang="it" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Accesso | <?= e(APP_NAME) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    :root { --ap-primary: #0d47a1; }
    body {
      min-height: 100vh;
      background: linear-gradient(135deg, #0d47a1 0%, #1565c0 40%, #283593 100%);
      display: flex; align-items: center; justify-content: center;
      font-family: 'Segoe UI', system-ui, sans-serif;
    }
    .login-container {
      width: 100%; max-width: 440px; padding: 1rem;
    }
    .login-card {
      background: #fff;
      border-radius: 1.25rem;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
      overflow: hidden;
    }
    .login-header {
      background: linear-gradient(135deg, #0d47a1, #1565c0);
      color: white;
      padding: 2rem 2rem 1.75rem;
      text-align: center;
    }
    .login-header .app-icon {
      width: 64px; height: 64px;
      background: rgba(255,255,255,0.15);
      border-radius: 1rem;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 1rem;
      font-size: 2rem;
    }
    .login-header h1 { font-size: 1.35rem; font-weight: 700; margin: 0; }
    .login-header p  { font-size: 0.82rem; opacity: 0.85; margin: 0.35rem 0 0; }
    .login-body { padding: 2rem; }
    .form-control {
      border-radius: 0.6rem;
      padding: 0.75rem 1rem;
      border-color: #dee2e6;
    }
    .form-control:focus { border-color: #86b7fe; box-shadow: 0 0 0 0.2rem rgba(13,110,253,0.15); }
    .btn-login {
      border-radius: 0.6rem;
      padding: 0.75rem;
      font-size: 1rem;
      font-weight: 600;
      background: linear-gradient(135deg, #1565c0, #0d47a1);
      border: none;
      transition: all 0.2s;
    }
    .btn-login:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(13,71,161,0.4); }
    .input-group-text { border-radius: 0.6rem 0 0 0.6rem; border-color: #dee2e6; background: #f8f9fa; }
    .login-footer { padding: 1rem 2rem; background: #f8f9fa; text-align: center; font-size: 0.8rem; color: #6c757d; }
    .norma-badge {
      display: inline-block;
      background: rgba(255,255,255,0.15);
      border: 1px solid rgba(255,255,255,0.3);
      border-radius: 20px;
      font-size: 0.72rem;
      padding: 0.2rem 0.7rem;
      margin-top: 0.5rem;
    }
    @keyframes shake {
      0%,100% { transform: translateX(0); }
      20%,60%  { transform: translateX(-6px); }
      40%,80%  { transform: translateX(6px); }
    }
    .shake { animation: shake 0.4s ease; }
  </style>
  <meta name="csrf-token" content="<?= e(Auth::csrfToken()) ?>">
</head>
<body>

<div class="login-container">
  <div class="login-card">

    <!-- Header -->
    <div class="login-header">
      <div class="app-icon"><i class="bi bi-building-gear"></i></div>
      <h1><?= e(APP_NAME) ?></h1>
      <p><?= e(APP_TAGLINE) ?></p>
      <div class="norma-badge"><i class="bi bi-shield-check me-1"></i>D.Lgs. 36/2023</div>
    </div>

    <!-- Body -->
    <div class="login-body">

      <?php if ($expired): ?>
      <div class="alert alert-warning alert-dismissible mb-3 py-2 small" role="alert">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Sessione scaduta. Effettua nuovamente l'accesso.
        <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
      </div>
      <?php endif; ?>

      <div id="loginAlert" class="alert alert-danger py-2 small d-none" role="alert">
        <i class="bi bi-x-circle me-1"></i>
        <span id="loginAlertMsg"></span>
      </div>

      <form id="loginForm" novalidate>
        <input type="hidden" name="csrf_token" id="csrfToken"
               value="<?= e(Auth::csrfToken()) ?>">
        <?php if ($redirect): ?>
        <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
        <?php endif; ?>

        <!-- Email -->
        <div class="mb-3">
          <label class="form-label fw-semibold small" for="email">
            <i class="bi bi-envelope me-1"></i>Email
          </label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input type="email" class="form-control" id="email" name="email"
                   placeholder="nome@organizzazione.it"
                   autocomplete="email" required autofocus>
          </div>
        </div>

        <!-- Password -->
        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <label class="form-label fw-semibold small mb-0" for="password">
              <i class="bi bi-lock me-1"></i>Password
            </label>
            <a href="#" class="small text-muted text-decoration-none"
               id="forgotLink">Password dimenticata?</a>
          </div>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-key"></i></span>
            <input type="password" class="form-control" id="password" name="password"
                   placeholder="••••••••" autocomplete="current-password" required>
            <button class="btn btn-outline-secondary" type="button" id="togglePwd">
              <i class="bi bi-eye" id="togglePwdIcon"></i>
            </button>
          </div>
        </div>

        <!-- Remember me -->
        <div class="d-flex align-items-center justify-content-between mb-4">
          <div class="form-check mb-0">
            <input class="form-check-input" type="checkbox" id="rememberMe" name="remember_me">
            <label class="form-check-label small" for="rememberMe">
              Mantieni l'accesso
            </label>
          </div>
        </div>

        <!-- Submit -->
        <button type="submit" class="btn btn-primary btn-login w-100 text-white" id="loginBtn">
          <span id="loginBtnText"><i class="bi bi-box-arrow-in-right me-2"></i>Accedi</span>
          <span id="loginBtnSpinner" class="d-none">
            <span class="spinner-border spinner-border-sm me-2"></span>Accesso in corso...
          </span>
        </button>
      </form>
    </div>

    <!-- Footer -->
    <div class="login-footer">
      <i class="bi bi-shield-lock me-1"></i>
      Accesso protetto con crittografia end-to-end
      <br>
      <span class="text-muted">v<?= APP_VERSION ?> &mdash; <?= e(APP_NAME) ?></span>
    </div>
  </div>

  <p class="text-center text-white-50 mt-3 small">
    Piattaforma riservata al personale autorizzato.
  </p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function() {
  'use strict';

  const form      = document.getElementById('loginForm');
  const btn       = document.getElementById('loginBtn');
  const btnText   = document.getElementById('loginBtnText');
  const btnSpinner = document.getElementById('loginBtnSpinner');
  const alert     = document.getElementById('loginAlert');
  const alertMsg  = document.getElementById('loginAlertMsg');

  // Toggle password visibility
  document.getElementById('togglePwd')?.addEventListener('click', () => {
    const pwd  = document.getElementById('password');
    const icon = document.getElementById('togglePwdIcon');
    if (pwd.type === 'password') {
      pwd.type = 'text';
      icon.className = 'bi bi-eye-slash';
    } else {
      pwd.type = 'password';
      icon.className = 'bi bi-eye';
    }
  });

  function showError(msg) {
    alertMsg.textContent = msg;
    alert.classList.remove('d-none');
    form.classList.add('shake');
    setTimeout(() => form.classList.remove('shake'), 400);
  }

  function setLoading(loading) {
    btn.disabled    = loading;
    btnText.classList.toggle('d-none', loading);
    btnSpinner.classList.toggle('d-none', !loading);
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    alert.classList.add('d-none');

    const email    = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const remember = document.getElementById('rememberMe').checked;
    const csrf     = document.getElementById('csrfToken').value;

    if (!email || !password) {
      showError('Inserisci email e password.');
      return;
    }

    setLoading(true);

    try {
      const resp = await fetch('api/login.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-Token': csrf,
        },
        body: JSON.stringify({ email, password, remember_me: remember }),
      });

      const data = await resp.json();

      if (data.csrf_token) {
        document.getElementById('csrfToken').value = data.csrf_token;
        document.querySelector('meta[name="csrf-token"]') &&
          (document.querySelector('meta[name="csrf-token"]').content = data.csrf_token);
      }

      if (data.success) {
        btnText.innerHTML = '<i class="bi bi-check-circle me-2"></i>Accesso effettuato!';
        btn.disabled = false;
        btn.classList.add('btn-success');
        setTimeout(() => {
          window.location.href = data.redirect || '<?= e(APP_URL) ?>/pages/dashboard.php';
        }, 600);
      } else {
        showError(data.message || 'Credenziali non valide.');
        setLoading(false);
      }
    } catch (err) {
      showError('Errore di rete. Verificare la connessione.');
      setLoading(false);
    }
  });

  // Enter submit
  document.getElementById('password')?.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') form.dispatchEvent(new Event('submit'));
  });

})();
</script>
</body>
</html>
