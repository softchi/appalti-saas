<?php
/**
 * Pagina Gestione Documentale
 */
define('APP_INIT', true);
require_once __DIR__ . '/../php/bootstrap.php';

Auth::require('pm_documenti.read');

$commessaId = sanitizeInt($_GET['commessa_id'] ?? null, 1);

$pm_commesse = Database::fetchAll(
    'SELECT id, codice_commessa, oggetto FROM pm_commesse ORDER BY codice_commessa'
);

if (!$commessaId && !empty($pm_commesse)) {
    $commessaId = (int)$pm_commesse[0]['id'];
}

$pageTitle  = 'Archivio Documentale';
$activeMenu = 'pm_documenti';

include COMPONENTS_PATH . '/header.php';
include COMPONENTS_PATH . '/sidebar.php';
?>

<!-- Page Header -->
<div class="page-header d-flex align-items-center gap-3">
  <div class="flex-grow-1">
    <h1><i class="bi bi-folder2-open me-2 text-primary"></i>Archivio Documentale</h1>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/pages/dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Documenti</li>
      </ol>
    </nav>
  </div>
  <div class="d-flex align-items-center gap-2">
    <select class="form-select form-select-sm" id="commessaSelect" style="min-width:260px;">
      <?php foreach ($pm_commesse as $c): ?>
      <option value="<?= e($c['id']) ?>" <?= $c['id'] == $commessaId ? 'selected' : '' ?>>
        <?= e($c['codice_commessa']) ?> – <?= e(mb_substr($c['oggetto'], 0, 40)) ?>
      </option>
      <?php endforeach; ?>
    </select>
    <?php if (Auth::can('pm_documenti.upload')): ?>
    <button class="btn btn-primary btn-sm" id="uploadBtn">
      <i class="bi bi-upload me-1"></i>Carica documento
    </button>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">

  <!-- Sidebar categorie -->
  <div class="col-xl-3 col-lg-4">
    <div class="card">
      <div class="card-header"><i class="bi bi-tags me-2 text-primary"></i>Categorie</div>
      <div class="list-group list-group-flush" id="categorieList">
        <button class="list-group-item list-group-item-action active" data-cat="">
          <i class="bi bi-grid me-2"></i>Tutti i pm_documenti
          <span class="badge bg-primary rounded-pill ms-auto" id="totDocs">0</span>
        </button>
      </div>
    </div>

    <!-- Ricerca -->
    <div class="card mt-3">
      <div class="card-body p-2">
        <div class="input-group input-group-sm">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="search" class="form-control" id="searchDoc" placeholder="Cerca pm_documenti...">
        </div>
      </div>
    </div>

    <!-- Info storage -->
    <div class="card mt-3">
      <div class="card-body p-3 text-center">
        <div class="small text-muted mb-1">Documenti caricati</div>
        <div class="fs-4 fw-bold text-primary" id="nDocs">0</div>
      </div>
    </div>
  </div>

  <!-- Griglia pm_documenti -->
  <div class="col-xl-9 col-lg-8">

    <!-- Toolbar -->
    <div class="d-flex align-items-center gap-2 mb-3">
      <div class="d-flex gap-1">
        <button class="btn btn-sm btn-outline-secondary active" id="viewGrid" title="Vista griglia">
          <i class="bi bi-grid-3x3-gap"></i>
        </button>
        <button class="btn btn-sm btn-outline-secondary" id="viewList" title="Vista lista">
          <i class="bi bi-list-ul"></i>
        </button>
      </div>
      <select class="form-select form-select-sm ms-auto" id="sortDoc" style="width:auto;">
        <option value="created_at_desc">Più recenti</option>
        <option value="titolo_asc">Titolo A-Z</option>
        <option value="dimensione_desc">Più grandi</option>
      </select>
    </div>

    <!-- Griglia -->
    <div id="docsGrid" class="row g-3">
      <div class="col-12 text-center py-5">
        <div class="spinner-border text-primary"></div>
      </div>
    </div>

    <!-- Paginazione -->
    <div id="docsPagination" class="mt-3 d-flex justify-content-center"></div>

  </div>
</div>

<!-- Drop zone overlay -->
<div id="dropZoneOverlay" class="d-none">
  <div class="text-center text-white">
    <i class="bi bi-cloud-upload-fill" style="font-size:5rem; opacity:0.8;"></i>
    <h3 class="mt-3">Rilascia qui per caricare</h3>
  </div>
</div>

<!-- Modal Upload -->
<div class="modal fade" id="uploadModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="bi bi-upload me-2"></i>Carica Documento</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="uploadForm" enctype="multipart/form-data">
        <div class="modal-body">
          <input type="hidden" name="commessa_id" id="uf_commessa_id">
          <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">

          <!-- Drop area file -->
          <div class="border border-dashed border-primary rounded-3 p-4 text-center mb-3 bg-primary bg-opacity-10"
               id="dropArea" style="cursor:pointer;">
            <i class="bi bi-cloud-upload text-primary" style="font-size:2.5rem;"></i>
            <p class="mt-2 mb-1 fw-semibold text-primary">Trascina il file qui o clicca per selezionare</p>
            <small class="text-muted">PDF, Word, Excel, immagini, DWG, ZIP · Max 50 MB</small>
            <input type="file" id="fileInput" name="file" class="d-none" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.dwg,.zip,.rar">
          </div>
          <!-- Anteprima file selezionato -->
          <div id="filePreview" class="d-none alert alert-success py-2 mb-3">
            <i class="bi bi-file-earmark me-2"></i>
            <strong id="fileName"></strong>
            <span class="text-muted ms-2" id="fileSize"></span>
          </div>

          <div class="row g-3">
            <div class="col-12">
              <label class="form-label required-star">Titolo documento</label>
              <input type="text" class="form-control" name="titolo" id="uf_titolo" required maxlength="300"
                     placeholder="es. Progetto esecutivo - Tavola 001">
            </div>
            <div class="col-md-6">
              <label class="form-label">Categoria</label>
              <select class="form-select" name="categoria_id" id="uf_categoria">
                <option value="">— Seleziona categoria —</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Data documento</label>
              <input type="date" class="form-control" name="data_documento"
                     value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Data scadenza validità</label>
              <input type="date" class="form-control" name="data_scadenza">
            </div>
            <div class="col-md-6">
              <label class="form-label">Tag (separati da virgola)</label>
              <input type="text" class="form-control" name="tags" placeholder="es. tavole, DL, approvato">
            </div>
            <div class="col-12">
              <label class="form-label">Descrizione</label>
              <textarea class="form-control" name="descrizione" rows="2" placeholder="Descrizione opzionale"></textarea>
            </div>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="riservato" id="uf_riservato" value="1">
                <label class="form-check-label" for="uf_riservato">
                  <i class="bi bi-lock me-1 text-danger"></i>Documento riservato (solo RUP/DL/Admin)
                </label>
              </div>
            </div>
          </div>

          <!-- Progress bar upload -->
          <div id="uploadProgress" class="d-none mt-3">
            <div class="progress"><div class="progress-bar progress-bar-striped progress-bar-animated" id="uploadBar" style="width:0%"></div></div>
            <small class="text-muted mt-1 d-block text-center" id="uploadStatus">Caricamento in corso...</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
          <button type="submit" class="btn btn-primary" id="uploadSaveBtn">
            <i class="bi bi-cloud-upload me-1"></i>Carica
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
#dropZoneOverlay {
  position: fixed; top:0; left:0; right:0; bottom:0;
  background: rgba(13,71,161,0.85);
  z-index: 9998;
  display: flex; align-items: center; justify-content: center;
}
.doc-card {
  transition: transform 0.15s, box-shadow 0.15s;
  cursor: pointer;
}
.doc-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.12) !important; }
.doc-icon { font-size: 2.5rem; }
.doc-card .doc-riservato { position: absolute; top: 8px; right: 8px; }
</style>

<?php
$inlineScript = <<<'JS'
'use strict';

let currentCid   = parseInt(document.getElementById('commessaSelect')?.value) || 0;
let currentCat   = '';
let currentPage  = 1;
let viewMode     = 'grid';
let uploadModal  = null;

const MIME_ICONS = {
  'application/pdf':   { icon: 'bi-file-pdf',     color: '#dc3545' },
  'image':             { icon: 'bi-file-image',    color: '#0dcaf0' },
  'application/vnd.openxmlformats-officedocument.wordprocessingml': { icon: 'bi-file-word', color: '#0d6efd' },
  'application/vnd.openxmlformats-officedocument.spreadsheetml':   { icon: 'bi-file-excel', color: '#198754' },
  'application/vnd.ms-excel':  { icon: 'bi-file-excel',    color: '#198754' },
  'application/msword':        { icon: 'bi-file-word',     color: '#0d6efd' },
  'application/zip':           { icon: 'bi-file-zip',      color: '#fd7e14' },
  'default':                   { icon: 'bi-file-earmark',  color: '#6c757d' },
};

function getIconForMime(mime) {
  if (!mime) return MIME_ICONS.default;
  if (mime.startsWith('image/')) return MIME_ICONS.image;
  for (const [key, val] of Object.entries(MIME_ICONS)) {
    if (mime.startsWith(key)) return val;
  }
  return MIME_ICONS.default;
}

async function loadDocumenti(page = 1) {
  if (!currentCid) return;
  currentPage = page;

  const params = { stato: 'PUBBLICATO', per_page: 12, page };
  if (currentCat) params.categoria = currentCat;
  const q = document.getElementById('searchDoc').value.trim();
  if (q.length >= 2) params.q = q;

  const grid = document.getElementById('docsGrid');
  grid.innerHTML = '<div class="col-12 text-center py-5"><div class="spinner-border text-primary"></div></div>';

  try {
    const data = await API.pm_documenti.list(currentCid, params);

    // Aggiorna categorie sidebar
    renderCategorie(data.categorie || []);
    document.getElementById('nDocs').textContent   = data.total || 0;
    document.getElementById('totDocs').textContent = data.total || 0;

    if (!data.data?.length) {
      grid.innerHTML = `
        <div class="col-12 text-center py-5 text-muted">
          <i class="bi bi-folder2 fs-1 mb-3 d-block"></i>
          <p>Nessun documento trovato</p>
          ${data.total === 0 ? '<small>Carica il primo documento usando il pulsante "Carica documento"</small>' : ''}
        </div>`;
      document.getElementById('docsPagination').innerHTML = '';
      return;
    }

    if (viewMode === 'grid') {
      grid.innerHTML = data.data.map(doc => {
        const { icon, color } = getIconForMime(doc.mime_type);
        return `
        <div class="col-xl-3 col-md-4 col-sm-6">
          <div class="card doc-card position-relative" onclick="openDoc(${doc.id})">
            ${doc.riservato ? '<span class="badge bg-danger doc-riservato"><i class="bi bi-lock"></i></span>' : ''}
            <div class="card-body text-center py-3">
              <i class="bi ${icon} doc-icon mb-2" style="color:${color};"></i>
              <div class="fw-semibold small text-truncate mb-1" title="${escapeHtml(doc.titolo)}">${escapeHtml(doc.titolo)}</div>
              <div class="d-flex align-items-center justify-content-center gap-1 mb-2">
                ${doc.categoria_nome ? `<span class="badge" style="background:${doc.categoria_colore || '#6c757d'}; font-size:0.65rem;">${escapeHtml(doc.categoria_nome)}</span>` : ''}
                <span class="badge bg-light text-dark border" style="font-size:0.65rem;">v${doc.versione}</span>
              </div>
              <small class="text-muted d-block">${escapeHtml(doc.dimensione_fmt || '')}</small>
              <small class="text-muted">${escapeHtml(doc.created_at_it || '')}</small>
            </div>
            <div class="card-footer p-1 text-center bg-transparent border-top">
              <a href="${API.getAppUrl()}/api/pm_documenti.php?action=download&id=${doc.id}"
                 class="btn btn-link btn-sm p-0 me-2" onclick="event.stopPropagation();" title="Scarica">
                <i class="bi bi-download text-primary"></i>
              </a>
              <small class="text-muted">${escapeHtml(doc.caricato_da || '')}</small>
            </div>
          </div>
        </div>`;
      }).join('');
    } else {
      grid.innerHTML = `<div class="col-12">
        <div class="table-responsive">
          <table class="table table-hover align-middle small">
            <thead>
              <tr>
                <th>Documento</th><th>Categoria</th><th>Versione</th>
                <th>Dimensione</th><th>Caricato da</th><th>Data</th><th></th>
              </tr>
            </thead>
            <tbody>
              ${data.data.map(doc => {
                const { icon, color } = getIconForMime(doc.mime_type);
                return `<tr onclick="openDoc(${doc.id})" style="cursor:pointer;">
                  <td>
                    <i class="bi ${icon} me-2" style="color:${color};"></i>
                    <span class="fw-semibold">${escapeHtml(doc.titolo)}</span>
                    ${doc.riservato ? '<i class="bi bi-lock text-danger ms-1" title="Riservato"></i>' : ''}
                  </td>
                  <td>${doc.categoria_nome ? `<span class="badge" style="background:${doc.categoria_colore}; font-size:0.7rem;">${escapeHtml(doc.categoria_nome)}</span>` : '—'}</td>
                  <td>v${doc.versione}</td>
                  <td>${escapeHtml(doc.dimensione_fmt || '—')}</td>
                  <td>${escapeHtml(doc.caricato_da || '—')}</td>
                  <td>${escapeHtml(doc.created_at_it || '—')}</td>
                  <td onclick="event.stopPropagation();">
                    <a href="${API.getAppUrl()}/api/pm_documenti.php?action=download&id=${doc.id}"
                       class="btn btn-outline-primary btn-sm btn-icon" title="Scarica">
                      <i class="bi bi-download"></i>
                    </a>
                  </td>
                </tr>`;
              }).join('')}
            </tbody>
          </table>
        </div>
      </div>`;
    }

    renderPagination(
      document.getElementById('docsPagination'),
      data.page, data.pages,
      (p) => loadDocumenti(p)
    );

  } catch (err) {
    grid.innerHTML = `<div class="col-12"><div class="alert alert-danger">${escapeHtml(err.message)}</div></div>`;
  }
}

function renderCategorie(categorie) {
  const el = document.getElementById('categorieList');
  const all = el.querySelector('[data-cat=""]');
  el.innerHTML = '';
  if (all) el.appendChild(all);

  categorie.forEach(cat => {
    const btn = document.createElement('button');
    btn.className = 'list-group-item list-group-item-action d-flex align-items-center gap-2' +
                    (currentCat === cat.codice ? ' active' : '');
    btn.dataset.cat = cat.codice;
    btn.innerHTML = `
      <i class="bi ${cat.icona || 'bi-file-earmark'}" style="color:${cat.colore || '#6c757d'};"></i>
      ${escapeHtml(cat.nome)}
      <span class="badge bg-secondary rounded-pill ms-auto">${cat.n_documenti}</span>`;
    btn.addEventListener('click', () => {
      el.querySelectorAll('.list-group-item').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      currentCat = cat.codice;
      loadDocumenti();
    });
    el.appendChild(btn);
  });

  el.querySelector('[data-cat=""]')?.addEventListener('click', () => {
    el.querySelectorAll('.list-group-item').forEach(b => b.classList.remove('active'));
    el.querySelector('[data-cat=""]').classList.add('active');
    currentCat = '';
    loadDocumenti();
  });
}

function openDoc(id) {
  window.open(API.getAppUrl() + `/api/pm_documenti.php?action=download&id=${id}`, '_blank');
}

// Upload form
document.getElementById('dropArea')?.addEventListener('click', () => {
  document.getElementById('fileInput').click();
});

document.getElementById('fileInput')?.addEventListener('change', function() {
  const file = this.files[0];
  if (file) {
    document.getElementById('fileName').textContent = file.name;
    document.getElementById('fileSize').textContent = Format.fileSize(file.size);
    document.getElementById('filePreview').classList.remove('d-none');
    if (!document.getElementById('uf_titolo').value) {
      document.getElementById('uf_titolo').value = file.name.replace(/\.[^.]+$/, '');
    }
  }
});

// Drag & drop globale
document.addEventListener('dragover', (e) => { e.preventDefault(); document.getElementById('dropZoneOverlay').classList.remove('d-none'); });
document.addEventListener('dragleave', (e) => { if (!e.relatedTarget) document.getElementById('dropZoneOverlay').classList.add('d-none'); });
document.addEventListener('drop', (e) => {
  e.preventDefault();
  document.getElementById('dropZoneOverlay').classList.add('d-none');
  const file = e.dataTransfer.files[0];
  if (file) {
    const input = document.getElementById('fileInput');
    const dt = new DataTransfer(); dt.items.add(file); input.files = dt.files;
    input.dispatchEvent(new Event('change'));
    uploadModal = uploadModal || new bootstrap.Modal(document.getElementById('uploadModal'));
    uploadModal.show();
  }
});

document.getElementById('uploadForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const fileInput = document.getElementById('fileInput');
  if (!fileInput.files.length) { UI.warning('Seleziona un file'); return; }

  const btn = document.getElementById('uploadSaveBtn');
  btn.disabled = true;
  document.getElementById('uploadProgress').classList.remove('d-none');

  const fd = new FormData(document.getElementById('uploadForm'));
  fd.set('csrf_token', document.querySelector('meta[name="csrf-token"]').content);

  try {
    await API.pm_documenti.upload(fd);
    UI.success('Documento caricato con successo');
    uploadModal.hide();
    document.getElementById('uploadForm').reset();
    document.getElementById('filePreview').classList.add('d-none');
    loadDocumenti();
  } catch (err) {
    UI.error(err.message || 'Errore caricamento');
  } finally {
    btn.disabled = false;
    document.getElementById('uploadProgress').classList.add('d-none');
  }
});

// Load categorie nel select upload
async function loadCategorie() {
  try {
    const data = await API.pm_documenti.list(currentCid, { stato: 'PUBBLICATO', per_page: 1 });
    const sel = document.getElementById('uf_categoria');
    (data.categorie || []).forEach(c => {
      sel.innerHTML += `<option value="${c.id}">${escapeHtml(c.nome)}</option>`;
    });
  } catch {}
}

// View toggle
document.getElementById('viewGrid')?.addEventListener('click', () => { viewMode = 'grid'; loadDocumenti(); document.getElementById('viewGrid').classList.add('active'); document.getElementById('viewList').classList.remove('active'); });
document.getElementById('viewList')?.addEventListener('click', () => { viewMode = 'list'; loadDocumenti(); document.getElementById('viewList').classList.add('active'); document.getElementById('viewGrid').classList.remove('active'); });

document.getElementById('commessaSelect')?.addEventListener('change', function() {
  currentCid = parseInt(this.value);
  document.getElementById('uf_commessa_id').value = currentCid;
  loadDocumenti();
});

document.getElementById('searchDoc')?.addEventListener('input', () => {
  clearTimeout(document.getElementById('searchDoc')._timer);
  document.getElementById('searchDoc')._timer = setTimeout(() => loadDocumenti(), 400);
});

document.getElementById('uploadBtn')?.addEventListener('click', () => {
  document.getElementById('uf_commessa_id').value = currentCid;
  uploadModal = uploadModal || new bootstrap.Modal(document.getElementById('uploadModal'));
  uploadModal.show();
});

document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('uf_commessa_id').value = currentCid;
  loadDocumenti();
  loadCategorie();
});
JS;

include COMPONENTS_PATH . '/footer.php';
?>
