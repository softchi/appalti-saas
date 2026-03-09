<?php
/**
 * API REST: Gestione Tasks / Cronoprogramma
 *
 * GET  /api/pm_tasks.php?commessa_id=N          - Lista pm_tasks commessa (struttura Gantt)
 * GET  /api/pm_tasks.php?id=N                   - Dettaglio task
 * POST /api/pm_tasks.php                        - Crea task
 * PUT  /api/pm_tasks.php?id=N                   - Aggiorna task (incluso completamento %)
 * DELETE /api/pm_tasks.php?id=N                 - Elimina task
 * POST /api/pm_tasks.php?action=reorder         - Riordina pm_tasks
 *
 * @version 1.0.0
 */
define('APP_INIT', true);
require_once __DIR__ . '/../php/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
if (!Auth::check()) jsonError('Non autenticato', 401);
if (!Auth::can('pm_tasks.read')) jsonError('Permesso negato', 403);

$method     = strtoupper($_SERVER['REQUEST_METHOD']);
$id         = sanitizeInt($_GET['id'] ?? null, 1);
$commessaId = sanitizeInt($_GET['commessa_id'] ?? null, 1);
$action     = sanitizeString($_GET['action'] ?? '');

switch ($method) {
    case 'GET':
        if ($id) {
            getTask($id);
        } elseif ($commessaId) {
            listTasks($commessaId);
        } else {
            jsonError('Parametro commessa_id o id richiesto', 400);
        }
        break;

    case 'POST':
        Auth::requireCsrf();
        if ($action === 'reorder') {
            reorderTasks();
        } else {
            if (!Auth::can('pm_tasks.create')) jsonError('Permesso negato', 403);
            createTask();
        }
        break;

    case 'PUT':
        if (!$id) jsonError('ID task richiesto', 400);
        if (!Auth::can('pm_tasks.update')) jsonError('Permesso negato', 403);
        Auth::requireCsrf();
        updateTask($id);
        break;

    case 'DELETE':
        if (!$id) jsonError('ID task richiesto', 400);
        if (!Auth::can('pm_tasks.delete')) jsonError('Permesso negato', 403);
        Auth::requireCsrf();
        deleteTask($id);
        break;

    default:
        jsonError('Metodo non supportato', 405);
}

// =============================================================================

function listTasks(int $commessaId): never
{
    if (!Auth::canAccessCommessa($commessaId) && !Auth::hasRole(['SUPERADMIN','ADMIN'])) {
        jsonError('Accesso negato', 403);
    }

    // Recupera pm_tasks strutturati per Gantt
    $pm_tasks = Database::fetchAll(
        'SELECT t.*,
                CONCAT(u.cognome, " ", u.nome) AS assegnato_nome,
                u.avatar_path,
                f.nome AS fase_nome, f.colore AS fase_colore,
                (SELECT COUNT(*) FROM pm_tasks sub WHERE sub.parent_id = t.id) AS n_subtasks
         FROM pm_tasks t
         LEFT JOIN pm_utenti u ON u.id = t.assegnato_a
         LEFT JOIN pm_fasi_lavoro f ON f.id = t.fase_id
         WHERE t.commessa_id = :cid
         ORDER BY t.ordine ASC, t.data_inizio_prevista ASC',
        [':cid' => $commessaId]
    );

    // Recupera dipendenze
    $taskIds = array_column($pm_tasks, 'id');
    $dipendenze = [];
    if (!empty($taskIds)) {
        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
        $dipendenze = Database::fetchAll(
            "SELECT * FROM pm_dipendenze_tasks WHERE task_id IN ({$placeholders}) OR task_pred_id IN ({$placeholders})",
            array_merge($taskIds, $taskIds)
        );
    }

    // Costruisci struttura gerarchica per Gantt
    $taskMap    = [];
    $rootTasks  = [];

    foreach ($pm_tasks as &$task) {
        $task['subtasks']    = [];
        $task['dipendenze']  = [];
        // Formatta date
        foreach (['data_inizio_prevista','data_fine_prevista','data_inizio_effettiva','data_fine_effettiva'] as $df) {
            $task[$df . '_it'] = formatDate($task[$df]);
        }
        $taskMap[$task['id']] = &$task;
    }
    unset($task);

    // Associa dipendenze
    foreach ($dipendenze as $dep) {
        if (isset($taskMap[$dep['task_id']])) {
            $taskMap[$dep['task_id']]['dipendenze'][] = $dep;
        }
    }

    // Struttura ad albero
    foreach ($pm_tasks as &$task) {
        if ($task['parent_id'] && isset($taskMap[$task['parent_id']])) {
            $taskMap[$task['parent_id']]['subtasks'][] = &$task;
        } else {
            $rootTasks[] = &$task;
        }
    }
    unset($task);

    // Statistiche cronoprogramma
    $stats = Database::fetchOne(
        'SELECT
           COUNT(*) AS totale,
           SUM(tipo = "MILESTONE") AS milestones,
           SUM(stato = "COMPLETATO") AS completati,
           SUM(stato = "IN_CORSO") AS in_corso,
           SUM(stato = "IN_RITARDO") AS in_ritardo,
           SUM(stato = "NON_INIZIATO") AS non_iniziati,
           AVG(percentuale_completamento) AS media_completamento,
           MIN(data_inizio_prevista) AS data_inizio_progetto,
           MAX(data_fine_prevista) AS data_fine_progetto
         FROM pm_tasks WHERE commessa_id = :cid',
        [':cid' => $commessaId]
    );

    jsonResponse([
        'pm_tasks'      => $rootTasks,
        'stats'      => $stats,
        'commessa_id' => $commessaId,
    ]);
}

function getTask(int $id): never
{
    $task = Database::fetchOne(
        'SELECT t.*, CONCAT(u.cognome, " ", u.nome) AS assegnato_nome, f.nome AS fase_nome
         FROM pm_tasks t
         LEFT JOIN pm_utenti u ON u.id = t.assegnato_a
         LEFT JOIN pm_fasi_lavoro f ON f.id = t.fase_id
         WHERE t.id = :id',
        [':id' => $id]
    );

    if (!$task) jsonError('Task non trovato', 404);

    // Dipendenze
    $task['predecessori'] = Database::fetchAll(
        'SELECT dt.*, t.nome AS task_nome, t.codice_wbs
         FROM pm_dipendenze_tasks dt
         JOIN pm_tasks t ON t.id = dt.task_pred_id
         WHERE dt.task_id = :id',
        [':id' => $id]
    );

    $task['successori'] = Database::fetchAll(
        'SELECT dt.*, t.nome AS task_nome, t.codice_wbs
         FROM pm_dipendenze_tasks dt
         JOIN pm_tasks t ON t.id = dt.task_id
         WHERE dt.task_pred_id = :id',
        [':id' => $id]
    );

    jsonResponse(['task' => $task]);
}

function createTask(): never
{
    $body = !empty($_POST) ? $_POST : getJsonBody();

    $v = new Validator($body);
    $v->required('commessa_id', 'Commessa')
      ->required('nome', 'Nome attività')
      ->minLength('nome', 3, 'Nome attività')
      ->date('data_inizio_prevista', 'Data inizio')
      ->date('data_fine_prevista', 'Data fine')
      ->orFail();

    $commessaId = (int)$body['commessa_id'];
    if (!Auth::canAccessCommessa($commessaId) && !Auth::hasRole(['SUPERADMIN','ADMIN'])) {
        jsonError('Accesso negato a questa commessa', 403);
    }

    // Genera codice WBS automatico
    $parentId  = sanitizeInt($body['parent_id'] ?? null, 1);
    $codicewbs = generateWbsCode($commessaId, $parentId);

    // Ordine: posiziona in fondo
    $maxOrdine = (int)Database::fetchValue(
        'SELECT COALESCE(MAX(ordine), 0) FROM pm_tasks WHERE commessa_id = :cid AND COALESCE(parent_id, 0) = :pid',
        [':cid' => $commessaId, ':pid' => $parentId ?? 0]
    );

    $data = [
        'uuid'                    => generateUUID(),
        'commessa_id'             => $commessaId,
        'fase_id'                 => sanitizeInt($body['fase_id'] ?? null, 1) ?? null,
        'parent_id'               => $parentId,
        'codice_wbs'              => $codicewbs,
        'nome'                    => sanitizeString($body['nome'], 300),
        'descrizione'             => sanitizeString($body['descrizione'] ?? '', 65535),
        'tipo'                    => in_array($body['tipo'] ?? '', ['TASK','MILESTONE','FASE','SOMMARIO'])
                                     ? $body['tipo'] : 'TASK',
        'assegnato_a'             => sanitizeInt($body['assegnato_a'] ?? null, 1) ?? null,
        'data_inizio_prevista'    => sanitizeDate($body['data_inizio_prevista'] ?? '') ?? null,
        'data_fine_prevista'      => sanitizeDate($body['data_fine_prevista'] ?? '') ?? null,
        'durata_prevista'         => sanitizeInt($body['durata_prevista'] ?? null, 1) ?? null,
        'importo_previsto'        => round((float)($body['importo_previsto'] ?? 0), 2),
        'stato'                   => 'NON_INIZIATO',
        'priorita'                => in_array($body['priorita'] ?? '', ['BASSA','NORMALE','ALTA','CRITICA'])
                                     ? $body['priorita'] : 'NORMALE',
        'ordine'                  => $maxOrdine + 10,
        'note'                    => sanitizeString($body['note'] ?? '', 65535),
        'created_by'              => Auth::id(),
    ];

    Database::beginTransaction();
    try {
        $taskId = Database::insert('pm_tasks', $data);

        // Aggiungi dipendenze se specificate
        if (!empty($body['predecessori']) && is_array($body['predecessori'])) {
            foreach ($body['predecessori'] as $pred) {
                $predId = sanitizeInt($pred['task_id'] ?? null, 1);
                $tipo   = in_array($pred['tipo'] ?? '', ['FS','SS','FF','SF']) ? $pred['tipo'] : 'FS';
                if ($predId) {
                    Database::insert('pm_dipendenze_tasks', [
                        'task_id'      => $taskId,
                        'task_pred_id' => $predId,
                        'tipo'         => $tipo,
                        'lag_giorni'   => sanitizeInt($pred['lag'] ?? 0) ?? 0,
                    ]);
                }
            }
        }

        Database::commit();
        Logger::audit('CREATE', 'pm_tasks', $taskId, null, $data);
        jsonSuccess('Attività creata con successo', ['id' => $taskId, 'codice_wbs' => $codicewbs], 201);

    } catch (Throwable $e) {
        Database::rollback();
        Logger::error('Errore creazione task: ' . $e->getMessage());
        jsonError('Errore durante la creazione dell\'attività', 500);
    }
}

function updateTask(int $id): never
{
    $existing = Database::fetchOne('SELECT * FROM pm_tasks WHERE id = :id', [':id' => $id]);
    if (!$existing) jsonError('Task non trovato', 404);

    if (!Auth::canAccessCommessa((int)$existing['commessa_id']) && !Auth::hasRole(['SUPERADMIN','ADMIN'])) {
        jsonError('Accesso negato', 403);
    }

    $body = !empty($_POST) ? $_POST : getJsonBody();

    $allowed = [
        'nome', 'descrizione', 'fase_id', 'assegnato_a',
        'data_inizio_prevista', 'data_fine_prevista',
        'data_inizio_effettiva', 'data_fine_effettiva',
        'durata_prevista', 'durata_effettiva',
        'percentuale_completamento', 'importo_previsto', 'importo_effettivo',
        'stato', 'priorita', 'note'
    ];

    $updateData = [];
    foreach ($allowed as $field) {
        if (!array_key_exists($field, $body)) continue;

        $updateData[$field] = match(true) {
            in_array($field, ['importo_previsto','importo_effettivo','percentuale_completamento'])
                => round((float)$body[$field], 2),
            in_array($field, ['fase_id','assegnato_a','durata_prevista','durata_effettiva'])
                => sanitizeInt($body[$field], 1),
            in_array($field, ['data_inizio_prevista','data_fine_prevista','data_inizio_effettiva','data_fine_effettiva'])
                => sanitizeDate((string)$body[$field]),
            $field === 'stato'
                => in_array($body[$field], ['NON_INIZIATO','IN_CORSO','COMPLETATO','IN_RITARDO','SOSPESO','ANNULLATO'])
                   ? $body[$field] : $existing['stato'],
            default => sanitizeString((string)$body[$field], 65535),
        };
    }

    // Auto-set stato in base alla percentuale
    if (isset($updateData['percentuale_completamento'])) {
        $perc = (float)$updateData['percentuale_completamento'];
        if ($perc >= 100) {
            $updateData['stato']              = 'COMPLETATO';
            $updateData['percentuale_completamento'] = 100.00;
            if (!isset($updateData['data_fine_effettiva'])) {
                $updateData['data_fine_effettiva'] = date('Y-m-d');
            }
        } elseif ($perc > 0 && ($existing['stato'] === 'NON_INIZIATO')) {
            $updateData['stato'] = 'IN_CORSO';
            if (!isset($updateData['data_inizio_effettiva'])) {
                $updateData['data_inizio_effettiva'] = date('Y-m-d');
            }
        }
    }

    if (empty($updateData)) jsonError('Nessun dato da aggiornare', 400);

    Database::update('pm_tasks', $updateData, ['id' => $id]);

    // Aggiorna avanzamento commessa
    updateCommessaAvanzamento((int)$existing['commessa_id']);

    Logger::audit('UPDATE', 'pm_tasks', $id, $existing, $updateData);
    jsonSuccess('Attività aggiornata', ['id' => $id]);
}

function deleteTask(int $id): never
{
    $existing = Database::fetchOne('SELECT * FROM pm_tasks WHERE id = :id', [':id' => $id]);
    if (!$existing) jsonError('Task non trovato', 404);

    // Verifica che non abbia subtasks
    $children = (int)Database::fetchValue(
        'SELECT COUNT(*) FROM pm_tasks WHERE parent_id = :id', [':id' => $id]
    );
    if ($children > 0) {
        jsonError('Impossibile eliminare un task con sotto-attività. Eliminare prima le sotto-attività.', 409);
    }

    Database::delete('pm_tasks', ['id' => $id]);
    updateCommessaAvanzamento((int)$existing['commessa_id']);

    Logger::audit('DELETE', 'pm_tasks', $id, $existing, null);
    jsonSuccess('Attività eliminata');
}

function reorderTasks(): never
{
    $body = getJsonBody();
    if (empty($body['pm_tasks']) || !is_array($body['pm_tasks'])) {
        jsonError('Lista pm_tasks richiesta', 400);
    }

    Database::beginTransaction();
    try {
        foreach ($body['pm_tasks'] as $item) {
            $taskId   = sanitizeInt($item['id'] ?? null, 1);
            $ordine   = sanitizeInt($item['ordine'] ?? 0, 0);
            $parentId = sanitizeInt($item['parent_id'] ?? null, 1) ?? null;

            if (!$taskId) continue;

            $updateData = ['ordine' => $ordine];
            if (array_key_exists('parent_id', $item)) {
                $updateData['parent_id'] = $parentId;
            }
            Database::update('pm_tasks', $updateData, ['id' => $taskId]);
        }
        Database::commit();
        jsonSuccess('Ordine aggiornato');
    } catch (Throwable $e) {
        Database::rollback();
        jsonError('Errore aggiornamento ordine', 500);
    }
}

// =============================================================================
// HELPER
// =============================================================================

function generateWbsCode(int $commessaId, ?int $parentId): string
{
    if ($parentId) {
        $parent = Database::fetchOne(
            'SELECT codice_wbs FROM pm_tasks WHERE id = :id',
            [':id' => $parentId]
        );
        $parentWbs = $parent['codice_wbs'] ?? '1';

        $siblings = (int)Database::fetchValue(
            'SELECT COUNT(*) FROM pm_tasks WHERE commessa_id = :cid AND parent_id = :pid',
            [':cid' => $commessaId, ':pid' => $parentId]
        );
        return $parentWbs . '.' . ($siblings + 1);
    }

    $root = (int)Database::fetchValue(
        'SELECT COUNT(*) FROM pm_tasks WHERE commessa_id = :cid AND parent_id IS NULL',
        [':cid' => $commessaId]
    );
    return (string)($root + 1);
}

function updateCommessaAvanzamento(int $commessaId): void
{
    $avg = Database::fetchValue(
        'SELECT COALESCE(AVG(percentuale_completamento), 0)
         FROM pm_tasks WHERE commessa_id = :cid AND tipo != "SOMMARIO" AND stato != "ANNULLATO"',
        [':cid' => $commessaId]
    );

    Database::update('pm_commesse', [
        'percentuale_avanzamento' => round((float)$avg, 2),
        'updated_by'              => Auth::id(),
    ], ['id' => $commessaId]);
}
