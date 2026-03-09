<?php
/**
 * API: Verbali di Cantiere — CRUD
 */
define('APP_INIT', true);
require_once __DIR__ . '/../php/bootstrap.php';

Auth::require();
$method = $_SERVER['REQUEST_METHOD'];
$action = get('action', 'string', '');
$id     = get('id', 'int', 0);

// Route
try {
    match (true) {
        $method === 'GET'    && $id > 0   => getVerbale($id),
        $method === 'GET'                  => listVerbali(),
        $method === 'POST'                 => createVerbale(),
        $method === 'PUT'                  => updateVerbale(),
        $method === 'DELETE'               => deleteVerbale(),
        default                            => jsonError('Metodo non supportato', 405),
    };
} catch (Exception $e) {
    Logger::audit('ERROR', 'verbali', null, 'RIFIUTATO', $e->getMessage());
    jsonError($e->getMessage(), 500);
}

// ============================================================
// LIST
// ============================================================
function listVerbali(): void {
    Auth::requireCsrf(['GET']);

    $page     = max(1, (int)get('page','int',1));
    $perPage  = min(50, max(5, (int)get('per_page','int',15)));
    $search   = get('search','string','');
    $tipo     = get('tipo','string','');
    $commId   = get('commessa_id','int',0);
    $dataDa   = get('data_da','date','');
    $dataA    = get('data_a','date','');
    $sort     = get('sort','string','data_verbale_desc');

    $where  = ['1=1'];
    $params = [];

    // RBAC – non-admin vedono solo le proprie commesse
    if (!Auth::can('verbali.read_all')) {
        $where[] = 'v.commessa_id IN (
            SELECT id FROM commesse WHERE rup_id=:uid1 OR pm_id=:uid2 OR dl_id=:uid3 OR cse_id=:uid4
            UNION
            SELECT commessa_id FROM commesse_utenti WHERE utente_id=:uid5
        )';
        $uid = Auth::id();
        $params += [':uid1'=>$uid,':uid2'=>$uid,':uid3'=>$uid,':uid4'=>$uid,':uid5'=>$uid];
    }

    if ($search) {
        $where[]  = '(v.oggetto LIKE :s1 OR v.contenuto LIKE :s2 OR v.luogo LIKE :s3)';
        $params[':s1'] = $params[':s2'] = $params[':s3'] = '%' . $search . '%';
    }
    if ($tipo) {
        $allowed = ['CONSEGNA_LAVORI','SOSPENSIONE','RIPRESA','VISITA_CANTIERE',
                    'COLLAUDO','CONTABILITA','RIUNIONE','ALTRO'];
        if (in_array($tipo, $allowed, true)) {
            $where[]  = 'v.tipo = :tipo';
            $params[':tipo'] = $tipo;
        }
    }
    if ($commId > 0) {
        $where[]  = 'v.commessa_id = :cid';
        $params[':cid'] = $commId;
    }
    if ($dataDa) { $where[] = 'v.data_verbale >= :da'; $params[':da'] = $dataDa; }
    if ($dataA)  { $where[] = 'v.data_verbale <= :a';  $params[':a']  = $dataA; }

    $orderMap = [
        'data_verbale_desc' => 'v.data_verbale DESC',
        'data_verbale'      => 'v.data_verbale ASC',
        'numero'            => 'v.numero_verbale ASC',
    ];
    $orderBy = $orderMap[$sort] ?? 'v.data_verbale DESC';

    $base = 'FROM verbali v
             JOIN commesse c ON c.id = v.commessa_id
             LEFT JOIN utenti u ON u.id = v.redatto_da
             WHERE ' . implode(' AND ', $where);

    $total = Database::fetchValue("SELECT COUNT(*) $base", $params);
    $pages = max(1, (int)ceil($total / $perPage));
    $offset = ($page - 1) * $perPage;

    $rows = Database::fetchAll(
        "SELECT v.id, v.numero_verbale, v.tipo, v.oggetto, v.data_verbale,
                v.ora_inizio, v.ora_fine, v.luogo, v.stato,
                v.commessa_id, c.codice_commessa,
                CONCAT(u.cognome,' ',u.nome) AS redattore
         $base ORDER BY $orderBy LIMIT :limit OFFSET :offset",
        array_merge($params, [':limit' => $perPage, ':offset' => $offset])
    );

    jsonSuccess($rows, [
        'current_page' => $page,
        'last_page'    => $pages,
        'total'        => (int)$total,
        'from'         => $total > 0 ? $offset + 1 : 0,
        'to'           => min($offset + $perPage, (int)$total),
    ]);
}

// ============================================================
// GET SINGLE
// ============================================================
function getVerbale(int $id): void {
    $v = Database::fetchOne(
        'SELECT v.*, c.codice_commessa, c.oggetto AS commessa_oggetto, c.id AS commessa_id,
                CONCAT(u.cognome," ",u.nome) AS redattore_nome
         FROM verbali v
         JOIN commesse c ON c.id = v.commessa_id
         LEFT JOIN utenti u ON u.id = v.redatto_da
         WHERE v.id = :id',
        [':id' => $id]
    );
    if (!$v) jsonError('Verbale non trovato', 404);
    jsonSuccess($v);
}

// ============================================================
// CREATE
// ============================================================
function createVerbale(): void {
    Auth::requireCsrf();
    Auth::require('verbali.create');

    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    $v = new Validator($data);
    $v->required('commessa_id')->required('tipo')->required('oggetto')->required('data_verbale');
    $v->inArray('tipo', ['CONSEGNA_LAVORI','SOSPENSIONE','RIPRESA','VISITA_CANTIERE',
                         'COLLAUDO','CONTABILITA','RIUNIONE','ALTRO']);
    $v->date('data_verbale');
    $errors = $v->errors();
    if ($errors) jsonError('Dati non validi', 422, $errors);

    // Auto numero_verbale
    $commessaId = (int)$data['commessa_id'];
    if (empty($data['numero_verbale'])) {
        $max = Database::fetchValue(
            'SELECT MAX(numero_verbale) FROM verbali WHERE commessa_id=:cid',
            [':cid' => $commessaId]
        );
        $data['numero_verbale'] = ($max ?? 0) + 1;
    }

    $insertId = Database::insert('verbali', [
        'commessa_id'    => $commessaId,
        'tipo'           => sanitizeString($data['tipo']),
        'oggetto'        => sanitizeString($data['oggetto']),
        'data_verbale'   => sanitizeDate($data['data_verbale']),
        'ora_inizio'     => !empty($data['ora_inizio'])   ? sanitizeString($data['ora_inizio']) : null,
        'ora_fine'       => !empty($data['ora_fine'])     ? sanitizeString($data['ora_fine']) : null,
        'luogo'          => !empty($data['luogo'])        ? sanitizeString($data['luogo']) : null,
        'numero_verbale' => (int)$data['numero_verbale'],
        'partecipanti'   => !empty($data['partecipanti']) ? sanitizeString($data['partecipanti']) : null,
        'contenuto'      => !empty($data['contenuto'])    ? sanitizeString($data['contenuto']) : null,
        'prescrizioni'   => !empty($data['prescrizioni']) ? sanitizeString($data['prescrizioni']) : null,
        'stato'          => in_array($data['stato'] ?? '', ['BOZZA','FIRMATO','ARCHIVIATO'])
                            ? $data['stato'] : 'BOZZA',
        'redatto_da'     => Auth::id(),
    ]);

    Logger::audit('CREATE', 'verbali', $insertId, 'SUCCESSO');

    // Notifica team
    createNotification(
        'VERBALE',
        'Nuovo verbale: ' . sanitizeString($data['oggetto']),
        '/api/verbali.php?id=' . $insertId,
        null,
        $commessaId
    );

    jsonSuccess(['id' => $insertId, 'numero_verbale' => $data['numero_verbale']], [], 'Verbale creato');
}

// ============================================================
// UPDATE
// ============================================================
function updateVerbale(): void {
    Auth::requireCsrf();
    Auth::require('verbali.update');

    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = (int)($data['id'] ?? 0);
    if (!$id) jsonError('ID mancante', 400);

    $existing = Database::fetchOne('SELECT id,commessa_id FROM verbali WHERE id=:id', [':id'=>$id]);
    if (!$existing) jsonError('Verbale non trovato', 404);

    $allowed = [
        'tipo','oggetto','data_verbale','ora_inizio','ora_fine','luogo',
        'numero_verbale','partecipanti','contenuto','prescrizioni','stato',
    ];
    $update = [];
    foreach ($allowed as $field) {
        if (array_key_exists($field, $data)) {
            $update[$field] = $data[$field] !== '' && $data[$field] !== null
                ? sanitizeString((string)$data[$field]) : null;
        }
    }
    if (empty($update)) jsonError('Nessun dato da aggiornare', 400);

    Database::update('verbali', $update, ['id' => $id]);
    Logger::audit('UPDATE', 'verbali', $id, 'SUCCESSO');
    jsonSuccess(null, [], 'Verbale aggiornato');
}

// ============================================================
// DELETE
// ============================================================
function deleteVerbale(): void {
    Auth::requireCsrf();
    Auth::require('verbali.delete');

    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = (int)($data['id'] ?? get('id','int',0));
    if (!$id) jsonError('ID mancante', 400);

    $existing = Database::fetchOne('SELECT id FROM verbali WHERE id=:id', [':id'=>$id]);
    if (!$existing) jsonError('Verbale non trovato', 404);

    Database::delete('verbali', ['id' => $id]);
    Logger::audit('DELETE', 'verbali', $id, 'SUCCESSO');
    jsonSuccess(null, [], 'Verbale eliminato');
}
