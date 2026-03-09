<?php
/**
 * API REST: Gestione Commesse
 *
 * GET    /api/pm_commesse.php          - Lista pm_commesse (con filtri, paginazione)
 * GET    /api/pm_commesse.php?id=N     - Dettaglio commessa
 * POST   /api/pm_commesse.php          - Crea commessa
 * PUT    /api/pm_commesse.php?id=N     - Aggiorna commessa
 * DELETE /api/pm_commesse.php?id=N     - Elimina commessa (soft)
 *
 * @version 1.0.0
 */
define('APP_INIT', true);
require_once __DIR__ . '/../php/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

// Richiede autenticazione
if (!Auth::check()) {
    jsonError('Non autenticato', 401);
}

$method = strtoupper($_SERVER['REQUEST_METHOD']);
$id     = sanitizeInt($_GET['id'] ?? null, 1);

switch ($method) {

    // =========================================================================
    case 'GET':
    // =========================================================================
        if (!Auth::can('pm_commesse.read')) {
            jsonError('Permesso negato', 403);
        }

        if ($id) {
            // Dettaglio singola commessa
            getCommessa($id);
        } else {
            // Lista pm_commesse
            listCommesse();
        }
        break;

    // =========================================================================
    case 'POST':
    // =========================================================================
        if (!Auth::can('pm_commesse.create')) {
            jsonError('Permesso negato', 403);
        }
        Auth::requireCsrf();
        createCommessa();
        break;

    // =========================================================================
    case 'PUT':
    // =========================================================================
        if (!$id) jsonError('ID commessa richiesto', 400);
        if (!Auth::can('pm_commesse.update')) {
            jsonError('Permesso negato', 403);
        }
        Auth::requireCsrf();
        updateCommessa($id);
        break;

    // =========================================================================
    case 'DELETE':
    // =========================================================================
        if (!$id) jsonError('ID commessa richiesto', 400);
        if (!Auth::can('pm_commesse.delete')) {
            jsonError('Permesso negato', 403);
        }
        Auth::requireCsrf();
        deleteCommessa($id);
        break;

    default:
        jsonError('Metodo non supportato', 405);
}

// =============================================================================
// FUNZIONI
// =============================================================================

function listCommesse(): void
{
    $page    = get('page', 1, 'int');
    $perPage = get('per_page', ITEMS_PER_PAGE, 'int');
    $stato   = sanitizeString($_GET['stato'] ?? '');
    $search  = sanitizeString($_GET['q'] ?? '');
    $saId    = get('sa_id', null, 'int');

    $user = Auth::user();

    // Base query
    $sql    = 'SELECT c.*, sa.denominazione AS stazione_appaltante,
                      i.ragione_sociale AS impresa,
                      a.codice_cig, a.codice_cup,
                      CONCAT(urup.cognome, " ", urup.nome) AS rup_nome,
                      CONCAT(upm.cognome, " ", upm.nome) AS pm_nome,
                      CONCAT(udl.cognome, " ", udl.nome) AS dl_nome,
                      (SELECT COUNT(*) FROM pm_tasks t WHERE t.commessa_id = c.id AND t.stato = "IN_RITARDO") AS tasks_ritardo,
                      (SELECT COUNT(*) FROM pm_scadenze sc WHERE sc.commessa_id = c.id AND sc.stato = "SCADUTA") AS scadenze_scadute
               FROM pm_commesse c
               JOIN pm_appalti a ON a.id = c.appalto_id
               JOIN pm_stazioni_appaltanti sa ON sa.id = a.stazione_appaltante_id
               JOIN pm_imprese i ON i.id = c.impresa_id
               LEFT JOIN pm_utenti urup ON urup.id = c.rup_id
               LEFT JOIN pm_utenti upm ON upm.id = c.pm_id
               LEFT JOIN pm_utenti udl ON udl.id = c.dl_id
               WHERE 1=1';
    $params = [];

    // Filtra per ruolo: impresa/tecnico vedono solo le loro pm_commesse
    if (!in_array($user['ruolo_codice'], ['SUPERADMIN', 'ADMIN', 'RUP', 'AMMINISTRAZIONE'])) {
        $sql .= ' AND (c.rup_id = :uid OR c.pm_id = :uid2 OR c.dl_id = :uid3 OR c.cse_id = :uid4
                       OR EXISTS (SELECT 1 FROM pm_commesse_utenti cu WHERE cu.commessa_id = c.id AND cu.utente_id = :uid5))';
        $params[':uid']  = $user['id'];
        $params[':uid2'] = $user['id'];
        $params[':uid3'] = $user['id'];
        $params[':uid4'] = $user['id'];
        $params[':uid5'] = $user['id'];
    }

    // Filtro stato
    $validStati = ['BOZZA','PIANIFICAZIONE','IN_ESECUZIONE','SOSPESA','COMPLETATA','COLLAUDATA','CHIUSA','ANNULLATA'];
    if ($stato && in_array($stato, $validStati, true)) {
        $sql .= ' AND c.stato = :stato';
        $params[':stato'] = $stato;
    }

    // Filtro stazione appaltante
    if ($saId) {
        $sql .= ' AND a.stazione_appaltante_id = :sa_id';
        $params[':sa_id'] = $saId;
    }

    // Ricerca full-text
    if ($search) {
        $sql .= ' AND (c.oggetto LIKE :search OR c.codice_commessa LIKE :search2 OR a.codice_cig LIKE :search3)';
        $params[':search']  = '%' . $search . '%';
        $params[':search2'] = '%' . $search . '%';
        $params[':search3'] = '%' . $search . '%';
    }

    $sql .= ' ORDER BY c.updated_at DESC';

    $result = Database::paginate($sql, $params, $page, $perPage);

    // Formatta date e importi
    $result['data'] = array_map('formatCommessaRow', $result['data']);

    jsonResponse($result);
}

function getCommessa(int $id): void
{
    // Verifica accesso
    if (!Auth::hasRole(['SUPERADMIN','ADMIN','RUP','AMMINISTRAZIONE']) && !Auth::canAccessCommessa($id)) {
        jsonError('Accesso negato a questa commessa', 403);
    }

    $commessa = Database::fetchOne(
        'SELECT c.*,
                sa.denominazione AS stazione_appaltante, sa.id AS sa_id,
                i.ragione_sociale AS impresa_nome, i.id AS impresa_id_val,
                a.codice_cig, a.codice_cup, a.oggetto AS appalto_oggetto,
                CONCAT(urup.cognome, " ", urup.nome) AS rup_nome, urup.email AS rup_email,
                CONCAT(upm.cognome, " ", upm.nome) AS pm_nome, upm.email AS pm_email,
                CONCAT(udl.cognome, " ", udl.nome) AS dl_nome, udl.email AS dl_email,
                CONCAT(ucse.cognome, " ", ucse.nome) AS cse_nome
         FROM pm_commesse c
         JOIN pm_appalti a ON a.id = c.appalto_id
         JOIN pm_stazioni_appaltanti sa ON sa.id = a.stazione_appaltante_id
         JOIN pm_imprese i ON i.id = c.impresa_id
         LEFT JOIN pm_utenti urup ON urup.id = c.rup_id
         LEFT JOIN pm_utenti upm ON upm.id = c.pm_id
         LEFT JOIN pm_utenti udl ON udl.id = c.dl_id
         LEFT JOIN pm_utenti ucse ON ucse.id = c.cse_id
         WHERE c.id = :id',
        [':id' => $id]
    );

    if (!$commessa) {
        jsonError('Commessa non trovata', 404);
    }

    // Team commessa
    $team = Database::fetchAll(
        'SELECT cu.*, CONCAT(u.cognome, " ", u.nome) AS nome_completo, u.email, u.qualifica, r.nome AS ruolo_nome
         FROM pm_commesse_utenti cu
         JOIN pm_utenti u ON u.id = cu.utente_id
         JOIN pm_ruoli r ON r.id = u.ruolo_id
         WHERE cu.commessa_id = :id',
        [':id' => $id]
    );

    // KPI commessa
    $kpi = Database::fetchOne(
        'SELECT * FROM v_kpi_commessa WHERE commessa_id = :id',
        [':id' => $id]
    );

    // Ultimi SAL
    $pm_sal = Database::fetchAll(
        'SELECT id, numero_sal, data_inizio, data_fine, importo_totale, stato
         FROM pm_sal WHERE commessa_id = :id ORDER BY numero_sal DESC LIMIT 5',
        [':id' => $id]
    );

    // Scadenze prossime
    $pm_scadenze = Database::fetchAll(
        'SELECT id, titolo, data_scadenza, tipo, priorita, stato
         FROM pm_scadenze WHERE commessa_id = :id AND stato = "ATTIVA"
         ORDER BY data_scadenza ASC LIMIT 5',
        [':id' => $id]
    );

    $commessa = formatCommessaRow($commessa);

    jsonResponse([
        'commessa' => $commessa,
        'team'     => $team,
        'kpi'      => $kpi,
        'pm_sal'      => $pm_sal,
        'pm_scadenze' => $pm_scadenze,
    ]);
}

function createCommessa(): void
{
    $body = !empty($_POST) ? $_POST : getJsonBody();

    // Validazione
    $v = new Validator($body);
    $v->required('appalto_id', 'Appalto')
      ->required('impresa_id', 'Impresa')
      ->required('oggetto', 'Oggetto')
      ->minLength('oggetto', 10, 'Oggetto')
      ->numeric('importo_contrattuale', 'Importo contrattuale')
      ->min('importo_contrattuale', 0, 'Importo contrattuale')
      ->date('data_inizio_prevista', 'Data inizio prevista')
      ->date('data_fine_prevista', 'Data fine prevista')
      ->orFail();

    // Verifica che appalto e impresa esistano
    $appalto = Database::fetchOne('SELECT id FROM pm_appalti WHERE id = :id', [':id' => $body['appalto_id']]);
    if (!$appalto) jsonError('Appalto non trovato', 404);

    $impresa = Database::fetchOne('SELECT id FROM pm_imprese WHERE id = :id', [':id' => $body['impresa_id']]);
    if (!$impresa) jsonError('Impresa non trovata', 404);

    $codiceCommessa = generateCodiceCommessa();

    $data = [
        'uuid'                  => generateUUID(),
        'appalto_id'            => (int)$body['appalto_id'],
        'impresa_id'            => (int)$body['impresa_id'],
        'codice_commessa'       => $codiceCommessa,
        'oggetto'               => sanitizeString($body['oggetto'], 500),
        'descrizione'           => sanitizeString($body['descrizione'] ?? '', 65535),
        'luogo_esecuzione'      => sanitizeString($body['luogo_esecuzione'] ?? '', 300),
        'comune'                => sanitizeString($body['comune'] ?? '', 100),
        'provincia'             => strtoupper(sanitizeString($body['provincia'] ?? '', 2)),
        'rup_id'                => sanitizeInt($body['rup_id'] ?? null, 1) ?? null,
        'pm_id'                 => sanitizeInt($body['pm_id'] ?? null, 1) ?? null,
        'dl_id'                 => sanitizeInt($body['dl_id'] ?? null, 1) ?? null,
        'cse_id'                => sanitizeInt($body['cse_id'] ?? null, 1) ?? null,
        'importo_contrattuale'  => round((float)($body['importo_contrattuale'] ?? 0), 2),
        'importo_sicurezza'     => round((float)($body['importo_sicurezza'] ?? 0), 2),
        'data_inizio_prevista'  => sanitizeDate($body['data_inizio_prevista'] ?? '') ?? null,
        'data_fine_prevista'    => sanitizeDate($body['data_fine_prevista'] ?? '') ?? null,
        'durata_contrattuale'   => sanitizeInt($body['durata_contrattuale'] ?? null, 1) ?? null,
        'stato'                 => 'BOZZA',
        'priorita'              => in_array($body['priorita'] ?? '', ['BASSA','NORMALE','ALTA','CRITICA'])
                                   ? $body['priorita'] : 'NORMALE',
        'colore'                => preg_match('/^#[0-9a-fA-F]{6}$/', $body['colore'] ?? '')
                                   ? $body['colore'] : '#0d6efd',
        'note'                  => sanitizeString($body['note'] ?? '', 65535),
        'created_by'            => Auth::id(),
    ];

    Database::beginTransaction();
    try {
        $commessaId = Database::insert('pm_commesse', $data);

        // Aggiungi RUP/PM/DL al team
        foreach (['rup_id' => 'RUP', 'pm_id' => 'PM', 'dl_id' => 'DL', 'cse_id' => 'CSE'] as $field => $ruolo) {
            if (!empty($data[$field])) {
                Database::insert('pm_commesse_utenti', [
                    'commessa_id'    => $commessaId,
                    'utente_id'      => $data[$field],
                    'ruolo_progetto' => $ruolo,
                    'data_inizio'    => $data['data_inizio_prevista'],
                ]);
            }
        }

        Database::commit();

        Logger::audit('CREATE', 'pm_commesse', $commessaId, null, $data);
        notifyCommessaTeam($commessaId, 'INFO',
            'Nuova commessa creata',
            "È stata creata la commessa: {$data['oggetto']}",
            "/pages/commessa-detail.php?id={$commessaId}"
        );

        jsonSuccess('Commessa creata con successo', [
            'id'             => $commessaId,
            'codice_commessa' => $codiceCommessa,
        ], 201);

    } catch (Throwable $e) {
        Database::rollback();
        Logger::error('Errore creazione commessa: ' . $e->getMessage());
        jsonError('Errore durante la creazione della commessa', 500);
    }
}

function updateCommessa(int $id): void
{
    if (!Auth::hasRole(['SUPERADMIN','ADMIN','RUP','AMMINISTRAZIONE']) && !Auth::canAccessCommessa($id)) {
        jsonError('Accesso negato a questa commessa', 403);
    }

    $existing = Database::fetchOne('SELECT * FROM pm_commesse WHERE id = :id', [':id' => $id]);
    if (!$existing) jsonError('Commessa non trovata', 404);

    $body = !empty($_POST) ? $_POST : getJsonBody();

    $updateData = [];
    $allowed = [
        'oggetto', 'descrizione', 'luogo_esecuzione', 'comune', 'provincia',
        'rup_id', 'pm_id', 'dl_id', 'cse_id',
        'importo_contrattuale', 'importo_sicurezza', 'importo_varianti',
        'data_inizio_prevista', 'data_fine_prevista',
        'data_inizio_effettiva', 'data_fine_effettiva',
        'durata_contrattuale', 'percentuale_avanzamento',
        'stato', 'priorita', 'colore', 'note'
    ];

    foreach ($allowed as $field) {
        if (array_key_exists($field, $body)) {
            $updateData[$field] = match(true) {
                in_array($field, ['importo_contrattuale','importo_sicurezza','importo_varianti','percentuale_avanzamento'])
                    => round((float)$body[$field], 2),
                in_array($field, ['rup_id','pm_id','dl_id','cse_id','durata_contrattuale'])
                    => sanitizeInt($body[$field], 1),
                in_array($field, ['data_inizio_prevista','data_fine_prevista','data_inizio_effettiva','data_fine_effettiva'])
                    => sanitizeDate((string)$body[$field]),
                $field === 'provincia'
                    => strtoupper(sanitizeString($body[$field], 2)),
                $field === 'stato'
                    => in_array($body[$field], ['BOZZA','PIANIFICAZIONE','IN_ESECUZIONE','SOSPESA','COMPLETATA','COLLAUDATA','CHIUSA','ANNULLATA'])
                       ? $body[$field] : $existing['stato'],
                default => sanitizeString((string)$body[$field], 65535),
            };
        }
    }

    if (empty($updateData)) {
        jsonError('Nessun dato da aggiornare', 400);
    }

    $updateData['updated_by'] = Auth::id();

    Database::update('pm_commesse', $updateData, ['id' => $id]);

    Logger::audit('UPDATE', 'pm_commesse', $id, $existing, $updateData);

    jsonSuccess('Commessa aggiornata con successo', ['id' => $id]);
}

function deleteCommessa(int $id): void
{
    $existing = Database::fetchOne(
        'SELECT id, oggetto, stato FROM pm_commesse WHERE id = :id',
        [':id' => $id]
    );
    if (!$existing) jsonError('Commessa non trovata', 404);

    // Non eliminare pm_commesse in esecuzione
    if (in_array($existing['stato'], ['IN_ESECUZIONE', 'COMPLETATA', 'COLLAUDATA'])) {
        jsonError('Impossibile eliminare una commessa in esecuzione o completata', 409);
    }

    // Soft delete: aggiorna stato ad ANNULLATA
    Database::update('pm_commesse', [
        'stato'      => 'ANNULLATA',
        'updated_by' => Auth::id(),
    ], ['id' => $id]);

    Logger::audit('DELETE', 'pm_commesse', $id, $existing, ['stato' => 'ANNULLATA']);
    jsonSuccess('Commessa annullata con successo');
}

// =============================================================================
// HELPER
// =============================================================================
function formatCommessaRow(array $row): array
{
    $dateFields = ['data_inizio_prevista','data_fine_prevista','data_inizio_effettiva','data_fine_effettiva','created_at','updated_at'];
    foreach ($dateFields as $f) {
        if (isset($row[$f])) {
            $row[$f . '_formatted'] = formatDate($row[$f]);
        }
    }
    foreach (['importo_contrattuale','importo_sicurezza','importo_varianti'] as $f) {
        if (isset($row[$f])) {
            $row[$f . '_formatted'] = formatEuro((float)$row[$f]);
        }
    }
    return $row;
}
