<?php
/**
 * API: Stazioni Appaltanti e Imprese
 * Fornisce anche endpoint per pm_stazioni_appaltanti e pm_imprese (usati dai select di pm_commesse.php)
 */
define('APP_INIT', true);
require_once __DIR__ . '/../php/bootstrap.php';

Auth::require();
$method = $_SERVER['REQUEST_METHOD'];
$action = get('action', '', 'string');
$id     = get('id', 0, 'int');

try {
    match (true) {
        // Stazioni Appaltanti
        $action === 'stazioni' && $method === 'GET'    => listStazioni(),
        $action === 'stazione' && $method === 'GET'    => getStazione($id),
        $action === 'stazione' && $method === 'POST'   => createStazione(),
        $action === 'stazione' && $method === 'PUT'    => updateStazione(),
        $action === 'stazione' && $method === 'DELETE' => deleteStazione(),

        // Imprese
        $action === 'pm_imprese'  && $method === 'GET'    => listImprese(),
        $action === 'impresa'  && $method === 'GET'    => getImpresa($id),
        $action === 'impresa'  && $method === 'POST'   => createImpresa(),
        $action === 'impresa'  && $method === 'PUT'    => updateImpresa(),
        $action === 'impresa'  && $method === 'DELETE' => deleteImpresa(),

        // Shortcut senza action (for backward compat with selects)
        $method === 'GET' && get('tipo', '', 'string') === 'pm_imprese'  => listImprese(),
        $method === 'GET' && get('tipo', '', 'string') === 'stazioni' => listStazioni(),

        default => jsonError('Azione non supportata', 405),
    };
} catch (Exception $e) {
    jsonError($e->getMessage(), 500);
}

// ============================================================
// STAZIONI APPALTANTI
// ============================================================
function listStazioni(): void {
    $search = get('search', '', 'string');
    $params = [];
    $where  = ['1=1'];
    if ($search) {
        $where[] = '(denominazione LIKE :s OR codice_fiscale LIKE :s2 OR comune LIKE :s3)';
        $params[':s'] = $params[':s2'] = $params[':s3'] = '%' . $search . '%';
    }
    $rows = Database::fetchAll(
        'SELECT id, denominazione, codice_fiscale, indirizzo, comune, provincia, cap, email, pec
         FROM pm_stazioni_appaltanti WHERE ' . implode(' AND ', $where) . ' ORDER BY denominazione',
        $params
    );
    jsonResponse(['stazioni' => $rows]);
}

function getStazione(int $id): void {
    $row = Database::fetchOne(
        'SELECT * FROM pm_stazioni_appaltanti WHERE id=:id', [':id' => $id]);
    if (!$row) jsonError('Stazione appaltante non trovata', 404);
    jsonResponse(['stazione' => $row]);
}

function createStazione(): void {
    Auth::requireCsrf();
    Auth::require('admin');

    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $v = new Validator($data);
    $v->required('denominazione');
    if ($errors = $v->errors()) jsonError('Dati non validi', 422, $errors);

    $id = Database::insert('pm_stazioni_appaltanti', [
        'denominazione'  => sanitizeString($data['denominazione']),
        'codice_fiscale' => !empty($data['codice_fiscale']) ? sanitizeString($data['codice_fiscale']) : null,
        'indirizzo'      => !empty($data['indirizzo'])      ? sanitizeString($data['indirizzo']) : null,
        'comune'         => !empty($data['comune'])         ? sanitizeString($data['comune']) : null,
        'provincia'      => !empty($data['provincia'])      ? sanitizeString($data['provincia']) : null,
        'cap'            => !empty($data['cap'])            ? sanitizeString($data['cap']) : null,
        'email'          => !empty($data['email'])          ? sanitizeEmail($data['email']) : null,
        'pec'            => !empty($data['pec'])            ? sanitizeEmail($data['pec']) : null,
        'telefono'       => !empty($data['telefono'])       ? sanitizeString($data['telefono']) : null,
        'referente'      => !empty($data['referente'])      ? sanitizeString($data['referente']) : null,
    ]);

    Logger::audit('CREATE', 'pm_stazioni_appaltanti', $id);
    jsonSuccess('Stazione appaltante creata', ['id' => $id], 201);
}

function updateStazione(): void {
    Auth::requireCsrf();
    Auth::require('admin');

    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = (int)($data['id'] ?? 0);
    if (!$id) jsonError('ID mancante', 400);

    $allowed = ['denominazione','codice_fiscale','indirizzo','comune',
                'provincia','cap','email','pec','telefono','referente'];
    $update = [];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $data)) {
            $update[$f] = $data[$f] !== '' ? sanitizeString((string)$data[$f]) : null;
        }
    }
    if (!$update) jsonError('Nessun dato', 400);

    Database::update('pm_stazioni_appaltanti', $update, ['id' => $id]);
    Logger::audit('UPDATE', 'pm_stazioni_appaltanti', $id);
    jsonSuccess('Stazione aggiornata');
}

function deleteStazione(): void {
    Auth::requireCsrf();
    Auth::require('superadmin');

    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = (int)($data['id'] ?? get('id', 0, 'int'));
    if (!$id) jsonError('ID mancante', 400);

    // Check if in use
    $count = Database::fetchValue('SELECT COUNT(*) FROM pm_appalti WHERE stazione_appaltante_id=:id', [':id'=>$id]);
    $count2 = Database::fetchValue('SELECT COUNT(*) FROM pm_commesse WHERE stazione_appaltante_id=:id', [':id'=>$id]);
    if (($count + $count2) > 0) jsonError('Stazione utilizzata in pm_appalti/pm_commesse, impossibile eliminare', 409);

    Database::delete('pm_stazioni_appaltanti', ['id' => $id]);
    Logger::audit('DELETE', 'pm_stazioni_appaltanti', $id);
    jsonSuccess('Stazione eliminata');
}

// ============================================================
// IMPRESE
// ============================================================
function listImprese(): void {
    $search = get('search', '', 'string');
    $params = [];
    $where  = ['1=1'];
    if ($search) {
        $where[] = '(ragione_sociale LIKE :s OR partita_iva LIKE :s2 OR codice_fiscale LIKE :s3)';
        $params[':s'] = $params[':s2'] = $params[':s3'] = '%' . $search . '%';
    }
    $rows = Database::fetchAll(
        'SELECT id, ragione_sociale AS denominazione, partita_iva, codice_fiscale,
                indirizzo, citta, provincia, soa_categorie, email, pec, telefono
         FROM pm_imprese WHERE ' . implode(' AND ', $where) . ' ORDER BY ragione_sociale',
        $params
    );
    jsonResponse(['data' => $rows]);
}

function getImpresa(int $id): void {
    $row = Database::fetchOne('SELECT * FROM pm_imprese WHERE id=:id', [':id' => $id]);
    if (!$row) jsonError('Impresa non trovata', 404);
    jsonResponse(['impresa' => $row]);
}

function createImpresa(): void {
    Auth::requireCsrf();
    Auth::require('admin');

    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $v = new Validator($data);
    $v->required('denominazione');
    if ($errors = $v->errors()) jsonError('Dati non validi', 422, $errors);

    $id = Database::insert('pm_imprese', [
        'denominazione'  => sanitizeString($data['denominazione']),
        'partita_iva'    => !empty($data['partita_iva'])    ? sanitizeString($data['partita_iva']) : null,
        'codice_fiscale' => !empty($data['codice_fiscale']) ? sanitizeString($data['codice_fiscale']) : null,
        'indirizzo'      => !empty($data['indirizzo'])      ? sanitizeString($data['indirizzo']) : null,
        'comune'         => !empty($data['comune'])         ? sanitizeString($data['comune']) : null,
        'provincia'      => !empty($data['provincia'])      ? sanitizeString($data['provincia']) : null,
        'cap'            => !empty($data['cap'])            ? sanitizeString($data['cap']) : null,
        'email'          => !empty($data['email'])          ? sanitizeEmail($data['email']) : null,
        'pec'            => !empty($data['pec'])            ? sanitizeEmail($data['pec']) : null,
        'telefono'       => !empty($data['telefono'])       ? sanitizeString($data['telefono']) : null,
        'referente'      => !empty($data['referente'])      ? sanitizeString($data['referente']) : null,
        'categoria_soa'  => !empty($data['categoria_soa'])  ? sanitizeString($data['categoria_soa']) : null,
        'classifica_soa' => !empty($data['classifica_soa']) ? sanitizeString($data['classifica_soa']) : null,
        'durc_scadenza'  => !empty($data['durc_scadenza'])  ? sanitizeDate($data['durc_scadenza']) : null,
    ]);

    Logger::audit('CREATE', 'pm_imprese', $id);
    jsonSuccess('Impresa creata', ['id' => $id], 201);
}

function updateImpresa(): void {
    Auth::requireCsrf();
    Auth::require('admin');

    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = (int)($data['id'] ?? 0);
    if (!$id) jsonError('ID mancante', 400);

    $allowed = ['denominazione','partita_iva','codice_fiscale','indirizzo','comune','provincia',
                'cap','email','pec','telefono','referente','categoria_soa','classifica_soa','durc_scadenza'];
    $update = [];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $data)) {
            $update[$f] = $data[$f] !== '' ? sanitizeString((string)$data[$f]) : null;
        }
    }
    if (!$update) jsonError('Nessun dato', 400);

    Database::update('pm_imprese', $update, ['id' => $id]);
    Logger::audit('UPDATE', 'pm_imprese', $id);
    jsonSuccess('Impresa aggiornata');
}

function deleteImpresa(): void {
    Auth::requireCsrf();
    Auth::require('superadmin');

    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = (int)($data['id'] ?? get('id', 0, 'int'));
    if (!$id) jsonError('ID mancante', 400);

    $count = Database::fetchValue('SELECT COUNT(*) FROM pm_commesse WHERE impresa_id=:id', [':id'=>$id]);
    if ($count > 0) jsonError('Impresa associata a pm_commesse, impossibile eliminare', 409);

    Database::delete('pm_imprese', ['id' => $id]);
    Logger::audit('DELETE', 'pm_imprese', $id);
    jsonSuccess('Impresa eliminata');
}
