<?php if (!defined('APP_INIT')) { exit('Accesso negato'); } ?>

</main><!-- /main-content -->
</div><!-- /mainWrapper -->

<!-- ============================================================
     TOAST CONTAINER (notifiche UI)
============================================================ -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer" style="z-index: 1100;"></div>

<!-- ============================================================
     MODAL CONFERMA GLOBALE
============================================================ -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="confirmModalTitle">Conferma operazione</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="confirmModalBody">
        Sei sicuro di voler procedere?
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
        <button type="button" class="btn btn-danger" id="confirmModalBtn">Conferma</button>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================
     LOADING OVERLAY
============================================================ -->
<div id="loadingOverlay" class="d-none">
  <div class="d-flex align-items-center justify-content-center h-100">
    <div class="text-center text-white">
      <div class="spinner-border" role="status" style="width: 3rem; height: 3rem;"></div>
      <p class="mt-3 fs-5" id="loadingMessage">Caricamento...</p>
    </div>
  </div>
</div>

<!-- ============================================================
     SCRIPTS
============================================================ -->
<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- App JavaScript -->
<script src="<?= APP_URL ?>/js/api.js"></script>
<script src="<?= APP_URL ?>/js/main.js"></script>

<!-- Page-specific scripts (definiti nelle singole pagine) -->
<?php if (!empty($extraScripts)): ?>
  <?php foreach ($extraScripts as $script): ?>
    <script src="<?= e($script) ?>"></script>
  <?php endforeach; ?>
<?php endif; ?>

<?php if (!empty($inlineScript)): ?>
<script><?= $inlineScript ?></script>
<?php endif; ?>

</body>
</html>
