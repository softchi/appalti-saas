<?php
/**
 * API REST: Gestione Documentale
 *
 * GET    /api/pm_documenti.php?commessa_id=N   - Lista pm_documenti
 * GET    /api/pm_documenti.php?id=N            - Dettaglio documento
 * POST   /api/pm_documenti.php                 - Upload documento (multipart)
 * PUT    /api/pm_documenti.php?id=N            - Aggiorna metadati
 * DELETE /api/pm_documenti.php?id=N            - Elimina documento
 * GET    /api/pm_documenti.php?action=download&id=N - Download sicuro
 *
 * @version 1.0.0
 */
define('APP_INIT', true);
require_once __DIR__ . '/../php/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
if (!Auth::check()) jsonError('Non autenticato', 401);
if (!Auth::can('pm_documenti.read')) jsonError('Permesso negato', 403);

$method     = strtoupper($_SERVER['REQUEST_METHOD']);
$id         = sanitizeInt($_GET['id'] ?? null, 1);
$commessaId = sanitizeInt($_GET['commessa_id'] ?? null, 1);
$action     = sanitizeString($_GET['action'] ?? '');

// Download non richiede JSON response
if ($action === 'download' && $id) {
    downloadDocumento($id);
}

switch ($method) {
    case 'GET':
        $id ? getDocumento($id) : listDocumenti($commessaId ?? 0);
        break;

    case 'POST':
        if (!Auth::can('pm_documenti.upload')) jsonError('Permesso negato', 403);
        Auth::requireCsrf();
        uploadDocumento();
        break;

    case 'PUT':
        if (!$id) jsonError('ID documento richiesto', 400);
        if (!Auth::can('pm_documenti.update')) jsonError('Permesso negato', 403);
        Auth::requireCsrf();
        updateDocumento($id);
        break;

    case 'DELETE':
        if (!$id) jsonError('ID documento richiesto', 400);
        if (!Auth::can('pm_documenti.delete')) jsonError('Permesso negato', 403);
        Auth::requireCsrf();
        deleteDocumento($id);
        break;

    default:
        jsonError('Metodo non supportato', 405);
}

// =============================================================================

function listDocumenti(int $commessaId): never
{
    if (!$commessaId) jsonError('commessa_id richiesto', 400);

    $categoria = sanitizeString($_GET['categoria'] ?? '');
    $search    = sanitizeString($_GET['q'] ?? '');
    $stato     = sanitizeString($_GET['stato'] ?? 'PUBBLICATO');

    $sql    = 'SELECT d.*,
                      cd.nome AS categoria_nome, cd.icona AS categoria_icona, cd.colore AS categoria_colore,
                      CONCAT(u.cognome, " ", u.nome) AS caricato_da
               FROM pm_documenti d
               LEFT JOIN pm_categorie_documento cd ON cd.id = d.categoria_id
               JOIN pm_utenti u ON u.id = d.uploaded_by
               WHERE d.commessa_id = :cid AND d.doc_padre_id IS NULL';
    $params = [':cid' => $commessaId];

    if ($stato) {
        $sql .= ' AND d.stato = :stato';
        $params[':stato'] = $stato;
    }
    if ($categoria) {
        $sql .= ' AND cd.codice = :cat';
        $params[':cat'] = $categoria;
    }
    if ($search) {
        $sql .= ' AND (d.titolo LIKE :search OR d.descrizione LIKE :search2)';
        $params[':search']  = '%' . $search . '%';
        $params[':search2'] = '%' . $search . '%';
    }

    $sql .= ' ORDER BY d.created_at DESC';

    $page    = get('page', 1, 'int');
    $perPage = get('per_page', ITEMS_PER_PAGE, 'int');
    $result  = Database::paginate($sql, $params, $page, $perPage);

    $result['data'] = array_map(function($doc) {
        $doc['dimensione_fmt']     = formatFileSize((int)$doc['dimensione']);
        $doc['created_at_it']      = formatDateTime($doc['created_at']);
        $doc['data_documento_it']  = formatDate($doc['data_documento']);
        $doc['data_scadenza_it']   = formatDate($doc['data_scadenza']);
        $doc['url_download']       = APP_URL . "/api/pm_documenti.php?action=download&id={$doc['id']}";
        return $doc;
    }, $result['data']);

    // Categorie con conteggio pm_documenti
    $categorie = Database::fetchAll(
        'SELECT cd.id, cd.codice, cd.nome, cd.icona, cd.colore,
                COUNT(d.id) AS n_documenti
         FROM pm_categorie_documento cd
         LEFT JOIN pm_documenti d ON d.categoria_id = cd.id AND d.commessa_id = :cid AND d.stato = "PUBBLICATO"
         GROUP BY cd.id
         ORDER BY cd.ordine',
        [':cid' => $commessaId]
    );

    jsonResponse(array_merge($result, ['categorie' => $categorie]));
}

function getDocumento(int $id): never
{
    $doc = Database::fetchOne(
        'SELECT d.*,
                cd.nome AS categoria_nome,
                CONCAT(u.cognome, " ", u.nome) AS caricato_da
         FROM pm_documenti d
         LEFT JOIN pm_categorie_documento cd ON cd.id = d.categoria_id
         JOIN pm_utenti u ON u.id = d.uploaded_by
         WHERE d.id = :id',
        [':id' => $id]
    );

    if (!$doc) jsonError('Documento non trovato', 404);

    // Versioni precedenti
    $versioni = Database::fetchAll(
        'SELECT d.id, d.versione, d.nome_file, d.dimensione, d.created_at,
                CONCAT(u.cognome, " ", u.nome) AS caricato_da
         FROM pm_documenti d
         JOIN pm_utenti u ON u.id = d.uploaded_by
         WHERE d.doc_padre_id = :padre_id OR d.id = :id
         ORDER BY d.versione DESC',
        [':padre_id' => $id, ':id' => $id]
    );

    $doc['url_download']    = APP_URL . "/api/pm_documenti.php?action=download&id={$id}";
    $doc['dimensione_fmt']  = formatFileSize((int)$doc['dimensione']);

    jsonResponse(['documento' => $doc, 'versioni' => $versioni]);
}

function uploadDocumento(): never
{
    if (empty($_FILES['file'])) {
        jsonError('File non ricevuto', 400);
    }

    $v = new Validator($_POST);
    $v->required('commessa_id', 'Commessa')
      ->required('titolo', 'Titolo documento')
      ->orFail();

    $commessaId = (int)$_POST['commessa_id'];
    $commessa   = Database::fetchOne('SELECT id FROM pm_commesse WHERE id = :id', [':id' => $commessaId]);
    if (!$commessa) jsonError('Commessa non trovata', 404);

    // Upload file
    $upload = uploadFile($_FILES['file'], 'pm_documenti/' . $commessaId);
    if (!$upload['success']) {
        jsonError($upload['message'] ?? 'Errore upload', 422);
    }

    // Gestione versioning: se specificato doc_padre_id
    $docPadreId = sanitizeInt($_POST['doc_padre_id'] ?? null, 1) ?? null;
    $versione   = 1;

    if ($docPadreId) {
        $padre = Database::fetchOne(
            'SELECT id, versione FROM pm_documenti WHERE id = :id',
            [':id' => $docPadreId]
        );
        if (!$padre) jsonError('Documento padre non trovato', 404);

        // Imposta documento precedente come OBSOLETO
        Database::update('pm_documenti', ['stato' => 'OBSOLETO'], ['id' => $docPadreId]);

        // Calcola nuova versione
        $maxVer = Database::fetchValue(
            'SELECT COALESCE(MAX(versione), 1) FROM pm_documenti
             WHERE doc_padre_id = :pid OR id = :id',
            [':pid' => $docPadreId, ':id' => $docPadreId]
        );
        $versione = (int)$maxVer + 1;
    }

    $docId = Database::insert('pm_documenti', [
        'uuid'           => generateUUID(),
        'commessa_id'    => $commessaId,
        'categoria_id'   => sanitizeInt($_POST['categoria_id'] ?? null, 1) ?? null,
        'titolo'         => sanitizeString($_POST['titolo'], 300),
        'descrizione'    => sanitizeString($_POST['descrizione'] ?? '', 65535),
        'nome_file'      => $upload['name'],
        'path_file'      => $upload['path'],
        'mime_type'      => $upload['mime'],
        'dimensione'     => $upload['size'],
        'hash_md5'       => $upload['md5'],
        'versione'       => $versione,
        'doc_padre_id'   => $docPadreId,
        'stato'          => 'PUBBLICATO',
        'riservato'      => (int)filter_var($_POST['riservato'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'data_documento' => sanitizeDate($_POST['data_documento'] ?? '') ?: date('Y-m-d'),
        'data_scadenza'  => sanitizeDate($_POST['data_scadenza'] ?? '') ?: null,
        'tags'           => !empty($_POST['tags']) ? json_encode(array_map('trim', explode(',', $_POST['tags']))) : null,
        'uploaded_by'    => Auth::id(),
    ]);

    Logger::audit('UPLOAD', 'pm_documenti', $docId, null, [
        'titolo' => $_POST['titolo'], 'file' => $upload['name'], 'versione' => $versione
    ]);

    notifyCommessaTeam(
        $commessaId, 'DOCUMENTO',
        'Nuovo documento caricato',
        'È stato caricato: ' . sanitizeString($_POST['titolo'], 100),
        "/pages/pm_documenti.php?commessa_id={$commessaId}"
    );

    jsonSuccess('Documento caricato con successo', [
        'id'      => $docId,
        'versione' => $versione,
        'url'     => $upload['url'],
    ], 201);
}

function updateDocumento(int $id): never
{
    $doc = Database::fetchOne('SELECT * FROM pm_documenti WHERE id = :id', [':id' => $id]);
    if (!$doc) jsonError('Documento non trovato', 404);

    $body = !empty($_POST) ? $_POST : getJsonBody();

    $updateData = [];
    $allowed = ['titolo','descrizione','categoria_id','stato','riservato','data_documento','data_scadenza'];
    foreach ($allowed as $field) {
        if (!array_key_exists($field, $body)) continue;
        $updateData[$field] = match($field) {
            'categoria_id' => sanitizeInt($body[$field], 1),
            'riservato'    => (int)filter_var($body[$field], FILTER_VALIDATE_BOOLEAN),
            'stato'        => in_array($body[$field], ['BOZZA','PUBBLICATO','OBSOLETO','ARCHIVIATO']) ? $body[$field] : $doc['stato'],
            'data_documento','data_scadenza' => sanitizeDate((string)$body[$field]),
            default        => sanitizeString((string)$body[$field], 65535),
        };
    }

    Database::update('pm_documenti', $updateData, ['id' => $id]);
    Logger::audit('UPDATE', 'pm_documenti', $id, $doc, $updateData);
    jsonSuccess('Documento aggiornato');
}

function deleteDocumento(int $id): never
{
    $doc = Database::fetchOne('SELECT * FROM pm_documenti WHERE id = :id', [':id' => $id]);
    if (!$doc) jsonError('Documento non trovato', 404);

    // Solo chi ha caricato o admin può eliminare
    if ($doc['uploaded_by'] != Auth::id() && !Auth::hasRole(['SUPERADMIN','ADMIN'])) {
        jsonError('Non autorizzato ad eliminare questo documento', 403);
    }

    // Soft delete
    Database::update('pm_documenti', ['stato' => 'ARCHIVIATO'], ['id' => $id]);

    Logger::audit('DELETE', 'pm_documenti', $id, $doc, ['stato' => 'ARCHIVIATO']);
    jsonSuccess('Documento archiviato');
}

function downloadDocumento(int $id): never
{
    header_remove('Content-Type');

    $doc = Database::fetchOne(
        'SELECT * FROM pm_documenti WHERE id = :id AND stato != "ARCHIVIATO"',
        [':id' => $id]
    );

    if (!$doc) {
        http_response_code(404);
        exit('Documento non trovato');
    }

    // Verifica accesso
    if ($doc['riservato'] && !Auth::hasRole(['SUPERADMIN','ADMIN','RUP','DL'])) {
        http_response_code(403);
        exit('Accesso negato');
    }

    $filePath = UPLOADS_PATH . '/' . $doc['path_file'];
    if (!file_exists($filePath) || !is_readable($filePath)) {
        http_response_code(404);
        exit('File non trovato sul server');
    }

    // Verifica integrità MD5
    if ($doc['hash_md5'] && md5_file($filePath) !== $doc['hash_md5']) {
        Logger::error('MD5 mismatch documento ID: ' . $id);
        http_response_code(500);
        exit('Errore integrità file');
    }

    // Log download
    Logger::audit('DOWNLOAD', 'pm_documenti', $id, null, ['file' => $doc['nome_file']]);

    // Serve file
    $mimeType = $doc['mime_type'] ?: 'application/octet-stream';
    $safeName = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $doc['nome_file']);

    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . $safeName . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: private, no-cache, no-store, must-revalidate');
    header('X-Content-Type-Options: nosniff');

    readfile($filePath);
    exit;
}
