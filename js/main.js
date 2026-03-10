/**
 * APPALTI PUBBLICI SAAS - Main JavaScript
 *
 * Funzionalità globali:
 * - UI helpers (toast, loader, modali)
 * - Sidebar toggle
 * - Notifiche polling
 * - Ricerca globale
 * - Formattazione numeri/date
 *
 * @version 1.0.0
 */

'use strict';

/* =============================================================================
   UI UTILITIES
============================================================================= */
const UI = {

  /**
   * Mostra un toast notification
   * @param {string} message
   * @param {string} type - 'success' | 'danger' | 'warning' | 'info'
   * @param {number} duration - ms (0 = persistente)
   */
  toast(message, type = 'info', duration = 4500) {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const icons = {
      success: 'bi-check-circle-fill',
      danger:  'bi-x-circle-fill',
      warning: 'bi-exclamation-triangle-fill',
      info:    'bi-info-circle-fill',
    };

    const id   = 'toast_' + Date.now();
    const html = `
      <div id="${id}" class="toast align-items-center text-bg-${type} border-0 mb-2" role="alert" aria-live="assertive">
        <div class="d-flex">
          <div class="toast-body d-flex align-items-center gap-2">
            <i class="bi ${icons[type] || icons.info}"></i>
            <span>${escapeHtml(message)}</span>
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
      </div>`;

    container.insertAdjacentHTML('beforeend', html);
    const el    = document.getElementById(id);
    const toast = new bootstrap.Toast(el, {
      autohide: duration > 0,
      delay:    duration,
    });
    toast.show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
  },

  success: (msg, dur) => UI.toast(msg, 'success', dur),
  error:   (msg, dur) => UI.toast(msg, 'danger', dur || 6000),
  warning: (msg, dur) => UI.toast(msg, 'warning', dur),
  info:    (msg, dur) => UI.toast(msg, 'info', dur),

  /**
   * Mostra overlay di caricamento
   */
  showLoader(message = 'Caricamento...') {
    let overlay = document.getElementById('loadingOverlay');
    if (!overlay) return;
    const msgEl = document.getElementById('loadingMessage');
    if (msgEl) msgEl.textContent = message;
    overlay.classList.remove('d-none');
  },

  hideLoader() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) overlay.classList.add('d-none');
  },

  /**
   * Modale di conferma generica
   * @returns {Promise<boolean>}
   */
  confirm(title = 'Conferma', body = 'Sei sicuro?', btnLabel = 'Conferma', btnClass = 'btn-danger') {
    return new Promise((resolve) => {
      const modal  = document.getElementById('confirmModal');
      if (!modal) { resolve(window.confirm(body)); return; }

      document.getElementById('confirmModalTitle').textContent = title;
      document.getElementById('confirmModalBody').textContent  = body;
      const btn = document.getElementById('confirmModalBtn');
      btn.textContent  = btnLabel;
      btn.className    = `btn ${btnClass}`;

      const bsModal = new bootstrap.Modal(modal);
      bsModal.show();

      const onConfirm = () => {
        bsModal.hide();
        btn.removeEventListener('click', onConfirm);
        resolve(true);
      };
      const onHide = () => {
        btn.removeEventListener('click', onConfirm);
        modal.removeEventListener('hidden.bs.modal', onHide);
        resolve(false);
      };

      btn.addEventListener('click', onConfirm);
      modal.addEventListener('hidden.bs.modal', onHide, { once: true });
    });
  },

  /**
   * Spinner inline
   */
  spinner(size = 'sm') {
    return `<span class="spinner-border spinner-border-${size}" role="status"><span class="visually-hidden">Caricamento...</span></span>`;
  },

  /**
   * Skeleton placeholder per card
   */
  skeletonCard(rows = 3) {
    const lines = Array(rows).fill(0)
      .map((_, i) => `<div class="skeleton mb-2" style="height:14px; width:${60 + i * 10}%"></div>`)
      .join('');
    return `<div class="p-3">${lines}</div>`;
  },
};

/* =============================================================================
   FORMATTAZIONE
============================================================================= */
const Format = {
  euro(value, decimals = 2) {
    const n = parseFloat(value) || 0;
    return '€ ' + n.toLocaleString('it-IT', {
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals,
    });
  },

  percent(value, decimals = 1) {
    return parseFloat(value).toFixed(decimals).replace('.', ',') + '%';
  },

  date(dateStr, format = 'it') {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d)) return dateStr;
    if (format === 'it') {
      return d.toLocaleDateString('it-IT');
    }
    return d.toISOString().split('T')[0];
  },

  datetime(dtStr) {
    if (!dtStr) return '—';
    const d = new Date(dtStr);
    if (isNaN(d)) return dtStr;
    return d.toLocaleString('it-IT', {
      day: '2-digit', month: '2-digit', year: 'numeric',
      hour: '2-digit', minute: '2-digit',
    });
  },

  fileSize(bytes) {
    const b = parseInt(bytes) || 0;
    if (b >= 1073741824) return (b / 1073741824).toFixed(2) + ' GB';
    if (b >= 1048576)    return (b / 1048576).toFixed(2)    + ' MB';
    if (b >= 1024)       return (b / 1024).toFixed(2)       + ' KB';
    return b + ' B';
  },

  timeAgo(dateStr) {
    const d    = new Date(dateStr);
    const diff = Date.now() - d.getTime();
    const m    = Math.floor(diff / 60000);
    if (m < 1)    return 'Adesso';
    if (m < 60)   return `${m} min fa`;
    if (m < 1440) return `${Math.floor(m/60)} ore fa`;
    return `${Math.floor(m/1440)} giorni fa`;
  },

  badgeStato(stato) {
    const map = {
      BOZZA:          ['secondary', 'Bozza'],
      PIANIFICAZIONE: ['info',      'Pianificazione'],
      IN_ESECUZIONE:  ['primary',   'In esecuzione'],
      SOSPESA:        ['warning',   'Sospesa'],
      COMPLETATA:     ['success',   'Completata'],
      COLLAUDATA:     ['purple',    'Collaudata'],
      CHIUSA:         ['dark',      'Chiusa'],
      ANNULLATA:      ['danger',    'Annullata'],
    };
    const [cls, label] = map[stato] || ['secondary', stato];
    return `<span class="badge bg-${cls}">${label}</span>`;
  },

  badgePriorita(p) {
    const map = {
      BASSA:   ['light text-dark', 'Bassa'],
      NORMALE: ['primary',         'Normale'],
      ALTA:    ['warning',         'Alta'],
      CRITICA: ['danger',          'Critica'],
    };
    const [cls, label] = map[p] || ['secondary', p];
    return `<span class="badge bg-${cls}">${label}</span>`;
  },

  progressBar(perc, cls = '') {
    const n = Math.min(100, Math.max(0, parseFloat(perc) || 0));
    const color = n >= 80 ? 'bg-success' : n >= 50 ? 'bg-primary' : n >= 25 ? 'bg-warning' : 'bg-danger';
    return `
      <div class="progress ${cls}" style="height:6px;" title="${n.toFixed(1)}%">
        <div class="progress-bar ${color}" style="width:${n}%"></div>
      </div>`;
  },
};

/* =============================================================================
   ESCAPE HTML (sicurezza XSS)
============================================================================= */
function escapeHtml(str) {
  if (str === null || str === undefined) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

/* =============================================================================
   PAGINAZIONE
============================================================================= */
function renderPagination(container, currentPage, totalPages, onPageChange) {
  if (typeof container === 'string') container = document.getElementById(container);
  if (!container) return;
  if (totalPages <= 1) { container.innerHTML = ''; return; }

  let html = '<nav><ul class="pagination pagination-sm mb-0">';

  // Prev
  html += `<li class="page-item ${currentPage <= 1 ? 'disabled' : ''}">
    <a class="page-link" href="#" data-page="${currentPage - 1}">&laquo;</a></li>`;

  // Pages
  const range = [];
  for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
    range.push(i);
  }
  if (range[0] > 1) { html += '<li class="page-item disabled"><span class="page-link">…</span></li>'; }
  range.forEach(p => {
    html += `<li class="page-item ${p === currentPage ? 'active' : ''}">
      <a class="page-link" href="#" data-page="${p}">${p}</a></li>`;
  });
  if (range[range.length - 1] < totalPages) {
    html += '<li class="page-item disabled"><span class="page-link">…</span></li>';
  }

  // Next
  html += `<li class="page-item ${currentPage >= totalPages ? 'disabled' : ''}">
    <a class="page-link" href="#" data-page="${currentPage + 1}">&raquo;</a></li>`;

  html += '</ul></nav>';
  container.innerHTML = html;
  container.querySelectorAll('[data-page]').forEach(a => {
    a.addEventListener('click', e => {
      e.preventDefault();
      const p = parseInt(a.dataset.page);
      if (p >= 1 && p <= totalPages) onPageChange(p);
    });
  });
}

/* =============================================================================
   SIDEBAR
============================================================================= */
function initSidebar() {
  const sidebar  = document.getElementById('sidebar');
  const toggle   = document.getElementById('sidebarToggle');
  const overlay  = document.getElementById('sidebarOverlay');
  const content  = document.getElementById('mainContent');

  if (!sidebar) return;

  // Mobile toggle
  toggle?.addEventListener('click', () => {
    const isOpen = sidebar.classList.toggle('show');
    overlay?.classList.toggle('show', isOpen);
  });

  overlay?.addEventListener('click', () => {
    sidebar.classList.remove('show');
    overlay.classList.remove('show');
  });

  // Desktop collapse (bottone interno, se presente)
  document.getElementById('sidebarCollapseBtn')?.addEventListener('click', () => {
    document.body.classList.toggle('sidebar-collapsed');
    localStorage.setItem('sidebar-collapsed', document.body.classList.contains('sidebar-collapsed'));
  });

  // Ripristina stato collapse da localStorage
  if (localStorage.getItem('sidebar-collapsed') === 'true') {
    document.body.classList.add('sidebar-collapsed');
  }
}

/* =============================================================================
   NOTIFICHE POLLING
============================================================================= */
let _notificheInterval = null;

function initNotifiche() {
  const badge    = document.getElementById('notificheBadge');
  const list     = document.getElementById('notificheList');
  const readAll  = document.getElementById('leggiTutteBtn');
  const dropdown = document.getElementById('notificheToggle');

  // Carica notifiche all'apertura dropdown
  dropdown?.addEventListener('show.bs.dropdown', loadNotifiche);

  // Segna tutte come lette
  readAll?.addEventListener('click', async () => {
    try {
      await API.notifiche.readAll();
      updateBadge(0);
      list.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-bell-slash fs-3"></i><p class="mt-2 mb-0 small">Nessuna notifica</p></div>';
    } catch (e) {
      UI.error('Errore aggiornamento notifiche');
    }
  });

  // Polling ogni 60 secondi per badge non lette
  loadBadgeCount();
  _notificheInterval = setInterval(loadBadgeCount, 60000);
}

async function loadBadgeCount() {
  try {
    const data = await API.get('/api/notifiche.php', { unread: true, limit: 1 });
    updateBadge(data.unread_count || 0);
  } catch { /* silenzioso */ }
}

async function loadNotifiche() {
  const list = document.getElementById('notificheList');
  if (!list) return;

  list.innerHTML = '<div class="text-center py-3">' + UI.spinner() + '</div>';

  try {
    const data = await API.notifiche.list({ limit: 15 });
    updateBadge(data.unread_count || 0);

    if (!data.notifiche?.length) {
      list.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-bell-slash fs-3"></i><p class="mt-2 mb-0 small">Nessuna notifica</p></div>';
      return;
    }

    list.innerHTML = data.notifiche.map(n => `
      <div class="notifica-item ${!n.letta ? 'non-letta' : ''}" data-id="${n.id}" data-link="${escapeHtml(n.link || '')}">
        <div class="d-flex gap-2">
          <i class="bi ${notificaIcon(n.tipo)} text-${notificaColor(n.tipo)} mt-1 flex-shrink-0"></i>
          <div class="flex-grow-1 overflow-hidden">
            <div class="notifica-titolo text-truncate">${escapeHtml(n.titolo)}</div>
            <div class="notifica-msg text-truncate">${escapeHtml(n.messaggio)}</div>
            <div class="notifica-time">${n.ago}</div>
          </div>
        </div>
      </div>`).join('');

    // Click notifica: segna letta e naviga
    list.querySelectorAll('.notifica-item').forEach(el => {
      el.addEventListener('click', async () => {
        const id   = el.dataset.id;
        const link = el.dataset.link;
        el.classList.remove('non-letta');
        await API.notifiche.read(id).catch(() => {});
        if (link) window.location.href = link;
      });
    });

  } catch {
    list.innerHTML = '<div class="text-center text-danger py-3 small">Errore caricamento notifiche</div>';
  }
}

function updateBadge(count) {
  const badge = document.getElementById('notificheBadge');
  if (!badge) return;
  if (count > 0) {
    badge.textContent = Math.min(count, 99) + (count > 99 ? '+' : '');
    badge.style.display = '';
  } else {
    badge.style.display = 'none';
  }
}

function notificaIcon(tipo) {
  const map = {
    INFO: 'bi-info-circle', AVVISO: 'bi-exclamation-triangle',
    SCADENZA: 'bi-alarm', APPROVAZIONE: 'bi-check-circle',
    DOCUMENTO: 'bi-file-earmark', TASK: 'bi-list-task',
    SAL: 'bi-receipt', SISTEMA: 'bi-gear',
  };
  return map[tipo] || 'bi-bell';
}

function notificaColor(tipo) {
  const map = {
    AVVISO: 'warning', SCADENZA: 'danger', APPROVAZIONE: 'success',
    DOCUMENTO: 'primary', SAL: 'info', SISTEMA: 'secondary',
  };
  return map[tipo] || 'primary';
}

/* =============================================================================
   RICERCA GLOBALE
============================================================================= */
function initGlobalSearch() {
  const input   = document.getElementById('globalSearch');
  const results = document.getElementById('searchResults');
  if (!input || !results) return;

  let _timer = null;

  input.addEventListener('input', () => {
    clearTimeout(_timer);
    const q = input.value.trim();
    if (q.length < 2) { results.classList.remove('show'); return; }
    _timer = setTimeout(() => searchGlobal(q), 300);
  });

  input.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      results.classList.remove('show');
      input.blur();
    }
  });

  document.addEventListener('click', (e) => {
    if (!input.contains(e.target)) results.classList.remove('show');
  });
}

async function searchGlobal(q) {
  const results = document.getElementById('searchResults');
  results.innerHTML = '<div class="p-2 text-center">' + UI.spinner() + '</div>';
  results.classList.add('show');

  try {
    const data = await API.commesse.list({ q, per_page: 8 });
    if (!data.data?.length) {
      results.innerHTML = '<div class="dropdown-item text-muted">Nessun risultato</div>';
      return;
    }
    results.innerHTML = data.data.map(c => `
      <a href="${API.getAppUrl()}/pages/commessa-detail.php?id=${c.id}"
         class="dropdown-item d-flex flex-column py-2">
        <div class="d-flex align-items-center gap-2">
          <span class="badge bg-primary badge-sm">${escapeHtml(c.codice_commessa)}</span>
          <span class="fw-semibold small text-truncate">${escapeHtml(c.oggetto)}</span>
        </div>
        <small class="text-muted ms-1">${escapeHtml(c.stazione_appaltante || '')} · CIG: ${escapeHtml(c.codice_cig || '')}</small>
      </a>`).join('');
  } catch {
    results.innerHTML = '<div class="dropdown-item text-danger small">Errore ricerca</div>';
  }
}

/* =============================================================================
   GESTIONE FORM GENERICA
============================================================================= */

/**
 * Mostra errori di validazione su un form
 * @param {HTMLFormElement} form
 * @param {Object} errors  - { fieldName: 'messaggio errore' }
 */
function showFormErrors(form, errors) {
  // Pulisci errori precedenti
  form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
  form.querySelectorAll('.invalid-feedback').forEach(el => el.remove());

  Object.entries(errors).forEach(([field, msg]) => {
    const el = form.querySelector(`[name="${field}"]`);
    if (el) {
      el.classList.add('is-invalid');
      const fb = document.createElement('div');
      fb.className   = 'invalid-feedback';
      fb.textContent = msg;
      el.parentNode.appendChild(fb);
    }
  });
}

/**
 * Serializza un form in oggetto
 */
function serializeForm(form) {
  const data = {};
  new FormData(form).forEach((val, key) => { data[key] = val; });
  return data;
}

/* =============================================================================
   INIT
============================================================================= */
document.addEventListener('DOMContentLoaded', () => {
  initSidebar();
  initNotifiche();
  initGlobalSearch();

  // Tooltip Bootstrap auto-init
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el =>
    new bootstrap.Tooltip(el, { trigger: 'hover' })
  );

  // Popover auto-init
  document.querySelectorAll('[data-bs-toggle="popover"]').forEach(el =>
    new bootstrap.Popover(el)
  );

  // Auto-dismiss alert dopo 5s
  document.querySelectorAll('.alert-dismissible.auto-dismiss').forEach(el => {
    setTimeout(() => {
      new bootstrap.Alert(el)?.close();
    }, 5000);
  });

  // Numero animato nelle KPI card
  document.querySelectorAll('[data-count-up]').forEach(el => {
    const target = parseFloat(el.dataset.countUp) || 0;
    const isEuro = el.dataset.countType === 'euro';
    let start = 0;
    const step = target / 40;
    const timer = setInterval(() => {
      start = Math.min(start + step, target);
      el.textContent = isEuro ? Format.euro(start, 0) : Math.floor(start).toLocaleString('it-IT');
      if (start >= target) clearInterval(timer);
    }, 25);
  });
});

// Cleanup al navigare via
window.addEventListener('beforeunload', () => {
  if (_notificheInterval) clearInterval(_notificheInterval);
});
