<?php
/**
 * Header HTML - incluso in ogni pagina autenticata
 * Richiede che Auth::require() sia già stato chiamato
 *
 * Variabili attese (opzionali, da definire prima dell'include):
 *   $pageTitle   string  - Titolo pagina (es. "Dashboard")
 *   $activeMenu  string  - Voce menu attiva (es. "pm_commesse")
 */
if (!defined('APP_INIT')) { exit('Accesso negato'); }

$user           = Auth::user();
$pageTitle      = $pageTitle ?? APP_NAME;
$activeMenu     = $activeMenu ?? '';

// Notifiche non lette (badge)
$unreadNotifiche = (int)Database::fetchValue(
    'SELECT COUNT(*) FROM pm_notifiche WHERE utente_id = :uid AND letta = 0',
    [':uid' => $user['id']]
);

// Scadenze urgenti (entro 7 giorni)
$scadenzeUrgenti = (int)Database::fetchValue(
    'SELECT COUNT(*) FROM pm_scadenze sc
     LEFT JOIN pm_commesse c ON c.id = sc.commessa_id
     WHERE sc.stato = "ATTIVA" AND sc.data_scadenza BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
     AND (sc.responsabile_id = :uid OR c.pm_id = :uid2 OR c.rup_id = :uid3)',
    [':uid' => $user['id'], ':uid2' => $user['id'], ':uid3' => $user['id']]
);
?>
<!DOCTYPE html>
<html lang="it" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>

  <!-- Bootstrap 5.3 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
  <!-- Custom CSS -->
  <link href="<?= APP_URL ?>/css/main.css" rel="stylesheet">

  <!-- Meta CSRF per JS -->
  <meta name="csrf-token" content="<?= e(Auth::csrfToken()) ?>">
  <meta name="app-url" content="<?= e(APP_URL) ?>">
  <meta name="user-id" content="<?= e($user['id']) ?>">
</head>
<body>

<!-- ============================================================
     NAVBAR TOP
============================================================ -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top shadow-sm" id="topNavbar">
  <div class="container-fluid">

    <!-- Brand -->
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= APP_URL ?>/pages/dashboard.php">
      <i class="bi bi-building-gear fs-4"></i>
      <span class="fw-bold d-none d-md-inline"><?= e(APP_NAME) ?></span>
      <small class="badge bg-white text-primary ms-1 d-none d-lg-inline">v<?= APP_VERSION ?></small>
    </a>

    <!-- Toggle sidebar (mobile) -->
    <button class="btn btn-link text-white me-2 d-lg-none" id="sidebarToggle" type="button">
      <i class="bi bi-list fs-4"></i>
    </button>

    <!-- Search bar (desktop) -->
    <form class="d-none d-lg-flex flex-grow-1 mx-4" id="globalSearchForm" autocomplete="off">
      <div class="input-group input-group-sm" style="max-width: 400px;">
        <span class="input-group-text bg-white border-end-0">
          <i class="bi bi-search text-muted"></i>
        </span>
        <input type="search" class="form-control border-start-0 ps-0"
               id="globalSearch" placeholder="Cerca commessa, CIG, CUP..."
               autocomplete="off">
        <div class="dropdown-menu shadow-lg" id="searchResults" style="top:100%; left:0; min-width:350px;"></div>
      </div>
    </form>

    <!-- Right actions -->
    <div class="d-flex align-items-center gap-1 ms-auto">

      <!-- Scadenze urgenti -->
      <?php if ($scadenzeUrgenti > 0): ?>
      <a href="<?= APP_URL ?>/pages/pm_scadenze.php"
         class="btn btn-sm btn-warning position-relative me-1" title="Scadenze urgenti">
        <i class="bi bi-alarm"></i>
        <span class="badge bg-danger rounded-pill position-absolute top-0 start-100 translate-middle">
          <?= $scadenzeUrgenti ?>
        </span>
      </a>
      <?php endif; ?>

      <!-- Notifiche -->
      <div class="dropdown">
        <button class="btn btn-link text-white position-relative p-1 px-2"
                type="button" id="notificheToggle"
                data-bs-toggle="dropdown" aria-expanded="false">
          <i class="bi bi-bell-fill fs-5"></i>
          <?php if ($unreadNotifiche > 0): ?>
          <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                id="notificheBadge">
            <?= min($unreadNotifiche, 99) ?><?= $unreadNotifiche > 99 ? '+' : '' ?>
          </span>
          <?php endif; ?>
        </button>
        <div class="dropdown-menu dropdown-menu-end shadow border-0" id="notificheDropdown"
             style="min-width: 360px; max-height: 480px; overflow-y: auto;">
          <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
            <h6 class="mb-0 fw-bold">Notifiche</h6>
            <button class="btn btn-link btn-sm text-muted p-0" id="leggiTutteBtn">
              Segna tutte come lette
            </button>
          </div>
          <div id="notificheList">
            <div class="text-center text-muted py-4">
              <i class="bi bi-bell-slash fs-3"></i>
              <p class="mt-2 mb-0 small">Nessuna notifica</p>
            </div>
          </div>
          <div class="border-top px-3 py-2 text-center">
            <a href="<?= APP_URL ?>/pages/pm_notifiche.php" class="btn btn-link btn-sm">
              Vedi tutte le pm_notifiche
            </a>
          </div>
        </div>
      </div>

      <!-- Profilo utente -->
      <div class="dropdown">
        <button class="btn btn-link text-white p-1 px-2 d-flex align-items-center gap-2"
                type="button" data-bs-toggle="dropdown" aria-expanded="false">
          <div class="avatar-sm bg-white text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold"
               style="width:32px; height:32px; font-size: 0.8rem;">
            <?= strtoupper(substr($user['nome'], 0, 1) . substr($user['cognome'], 0, 1)) ?>
          </div>
          <span class="d-none d-xl-inline small">
            <?= e($user['nome']) ?> <?= e($user['cognome']) ?>
          </span>
          <i class="bi bi-chevron-down small"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow border-0">
          <li>
            <div class="px-4 py-3 border-bottom">
              <p class="mb-0 fw-bold"><?= e($user['nome']) ?> <?= e($user['cognome']) ?></p>
              <small class="text-muted"><?= e($user['ruolo_nome']) ?></small><br>
              <small class="text-muted"><?= e($user['email']) ?></small>
            </div>
          </li>
          <li>
            <a class="dropdown-item" href="<?= APP_URL ?>/pages/profilo.php">
              <i class="bi bi-person-circle me-2"></i> Il mio profilo
            </a>
          </li>
          <?php if (Auth::hasRole(['SUPERADMIN','ADMIN'])): ?>
          <li>
            <a class="dropdown-item" href="<?= APP_URL ?>/pages/impostazioni.php">
              <i class="bi bi-gear me-2"></i> Impostazioni sistema
            </a>
          </li>
          <?php endif; ?>
          <li><hr class="dropdown-divider"></li>
          <li>
            <a class="dropdown-item text-danger" href="<?= APP_URL ?>/api/logout.php">
              <i class="bi bi-box-arrow-right me-2"></i> Esci
            </a>
          </li>
        </ul>
      </div>
    </div>
  </div>
</nav>

<!-- Spacer navbar -->
<div style="height: 56px;"></div>

<!-- ============================================================
     WRAPPER LAYOUT (sidebar + content)
============================================================ -->
<div class="d-flex" id="mainWrapper">
