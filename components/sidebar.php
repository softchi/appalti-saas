<?php
/**
 * Sidebar navigazione - inclusa dopo header.php
 * Variabili: $activeMenu (stringa) per evidenziare voce attiva
 */
if (!defined('APP_INIT')) { exit('Accesso negato'); }
$user = Auth::user();

// Menu items: [id, icona, label, url, permesso_richiesto, solo_ruoli]
$menuItems = [
    ['id' => 'dashboard', 'icon' => 'bi-speedometer2', 'label' => 'Dashboard',
     'url' => APP_URL . '/pages/dashboard.php', 'perm' => null],

    ['type' => 'divider', 'label' => 'APPALTI & COMMESSE'],

    ['id' => 'pm_commesse', 'icon' => 'bi-briefcase-fill', 'label' => 'Commesse',
     'url' => APP_URL . '/pages/pm_commesse.php', 'perm' => 'pm_commesse.read',
     'badge_query' => "SELECT COUNT(*) FROM pm_commesse WHERE stato = 'IN_ESECUZIONE'"],

    ['id' => 'cronoprogramma', 'icon' => 'bi-bar-chart-steps', 'label' => 'Cronoprogramma',
     'url' => APP_URL . '/pages/cronoprogramma.php', 'perm' => 'pm_tasks.read'],

    ['type' => 'divider', 'label' => 'CONTABILITÀ'],

    ['id' => 'pm_sal', 'icon' => 'bi-receipt-cutoff', 'label' => 'S.A.L.',
     'url' => APP_URL . '/pages/pm_sal.php', 'perm' => 'pm_sal.read'],

    ['id' => 'contabilita', 'icon' => 'bi-currency-euro', 'label' => 'Contabilità Lavori',
     'url' => APP_URL . '/pages/contabilita.php', 'perm' => 'pm_sal.read'],

    ['type' => 'divider', 'label' => 'DOCUMENTI & VERBALI'],

    ['id' => 'pm_documenti', 'icon' => 'bi-folder2-open', 'label' => 'Documentale',
     'url' => APP_URL . '/pages/pm_documenti.php', 'perm' => 'pm_documenti.read'],

    ['id' => 'pm_verbali', 'icon' => 'bi-journal-check', 'label' => 'Verbali',
     'url' => APP_URL . '/pages/pm_verbali.php', 'perm' => 'pm_verbali.read'],

    ['type' => 'divider', 'label' => 'PIANIFICAZIONE'],

    ['id' => 'pm_scadenze', 'icon' => 'bi-calendar-event-fill', 'label' => 'Scadenzario',
     'url' => APP_URL . '/pages/pm_scadenze.php', 'perm' => 'pm_scadenze.read',
     'badge_query' => "SELECT COUNT(*) FROM pm_scadenze WHERE stato = 'ATTIVA' AND data_scadenza < CURDATE()",
     'badge_class' => 'bg-danger'],

    ['type' => 'divider', 'label' => 'ANALISI & REPORT'],

    ['id' => 'report', 'icon' => 'bi-graph-up-arrow', 'label' => 'Report',
     'url' => APP_URL . '/pages/report.php', 'perm' => 'report.read'],

    ['id' => 'ai', 'icon' => 'bi-robot', 'label' => 'AI Assistant',
     'url' => APP_URL . '/pages/ai-assistant.php', 'perm' => 'ai.use'],

    ['type' => 'divider', 'label' => 'AMMINISTRAZIONE', 'only_roles' => ['SUPERADMIN','ADMIN','RUP']],

    ['id' => 'pm_utenti', 'icon' => 'bi-people-fill', 'label' => 'Utenti',
     'url' => APP_URL . '/pages/pm_utenti.php', 'perm' => 'pm_utenti.read',
     'only_roles' => ['SUPERADMIN','ADMIN']],

    ['id' => 'impostazioni', 'icon' => 'bi-gear-fill', 'label' => 'Impostazioni',
     'url' => APP_URL . '/pages/impostazioni.php', 'perm' => null,
     'only_roles' => ['SUPERADMIN','ADMIN']],
];
?>

<!-- ============================================================
     SIDEBAR
============================================================ -->
<nav class="sidebar shadow-sm" id="sidebar">
  <div class="sidebar-sticky">
    <ul class="nav flex-column pt-2">
      <?php foreach ($menuItems as $item): ?>

        <?php if (isset($item['type']) && $item['type'] === 'divider'): ?>
          <?php
          // Mostra divisore solo se l'utente ha ruolo giusto
          if (!empty($item['only_roles']) && !Auth::hasRole($item['only_roles'])) continue;
          ?>
          <li class="nav-item mt-2">
            <span class="sidebar-label px-3 text-uppercase small fw-semibold text-muted">
              <?= e($item['label']) ?>
            </span>
          </li>

        <?php else: ?>
          <?php
          // Verifica permesso
          if (!empty($item['perm']) && !Auth::can($item['perm'])) continue;
          // Verifica ruolo
          if (!empty($item['only_roles']) && !Auth::hasRole($item['only_roles'])) continue;

          $isActive = ($activeMenu === $item['id']);

          // Badge dinamico
          $badge = '';
          if (!empty($item['badge_query'])) {
              $badgeCount = (int)Database::fetchValue($item['badge_query']);
              if ($badgeCount > 0) {
                  $badgeClass = $item['badge_class'] ?? 'bg-warning text-dark';
                  $badge = '<span class="badge ' . $badgeClass . ' ms-auto">' . $badgeCount . '</span>';
              }
          }
          ?>
          <li class="nav-item">
            <a class="nav-link d-flex align-items-center <?= $isActive ? 'active' : '' ?>"
               href="<?= e($item['url']) ?>"
               title="<?= e($item['label']) ?>">
              <i class="bi <?= e($item['icon']) ?> me-2 fs-5"></i>
              <span class="nav-label"><?= e($item['label']) ?></span>
              <?= $badge ?>
            </a>
          </li>
        <?php endif; ?>

      <?php endforeach; ?>
    </ul>

    <!-- Footer sidebar -->
    <div class="sidebar-footer border-top mt-auto px-3 py-3">
      <div class="d-flex align-items-center gap-2">
        <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold flex-shrink-0"
             style="width: 36px; height: 36px; font-size: 0.85rem;">
          <?= strtoupper(substr($user['nome'], 0, 1) . substr($user['cognome'], 0, 1)) ?>
        </div>
        <div class="overflow-hidden nav-label">
          <p class="mb-0 small fw-semibold text-truncate">
            <?= e($user['nome']) ?> <?= e($user['cognome']) ?>
          </p>
          <small class="text-muted text-truncate d-block">
            <i class="bi bi-shield-check me-1"></i><?= e($user['ruolo_nome']) ?>
          </small>
        </div>
      </div>
    </div>
  </div>
</nav>

<!-- Overlay mobile -->
<div class="sidebar-overlay d-lg-none" id="sidebarOverlay"></div>

<!-- Main content wrapper -->
<main class="main-content flex-grow-1" id="mainContent">
