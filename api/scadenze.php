<?php
/**
 * API REST: Scadenzario
 * @version 1.0.0
 */
define('APP_INIT', true);
require_once __DIR__ . '/../php/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
if (!Auth::check()) jsonError('Non autenticato', 401);
if (!Auth::can('scadenze.read')) jsonError('Permesso negato', 403);

$method     = strtoupper($_SERVER['REQUEST_METHOD']);
$id         = sanitizeInt($_GET['id'] ?? null, 1);
$commessaId = sanitizeInt($_GET['commessa_id'] ?? null, 1);

switch ($method) {
    case 'GET':
        if ($id) {
            $sc = Database::fetchOne('SELECT * FROM scadenze WHERE id = :id', [':id' => $id]);
            if (!$sc) jsonError('Scadenza non trovata', 404);
            $sc['data_scadenza_it'] = formatDate($sc['data_scadenza']);
            $sc['giorni_alla_scadenza'] = (int)(strtotime($sc['data_scadenza']) - time()) / 86400;
            jsonResponse(['scadenza' => $sc]);
        }

        $sql    = 'SELECT sc.*, c.codice_commessa, c.oggetto AS commessa_oggetto,
                          CONCAT(u.cognome, " ", u.nome) AS responsabile_nome,
                          DATEDIFF(sc.data_scadenza, CURDATE()) AS giorni
                   FROM scadenze sc
                   LEFT JOIN commesse c ON c.id = sc.commessa_id
                   LEFT JOIN utenti u ON u.id = sc.responsabile_id
                   WHERE 1=1';
        $params = [];

        if ($commessaId) { $sql .= ' AND sc.commessa_id = :cid'; $params[':cid'] = $commessaId; }
        $stato = sanitizeString($_GET['stato'] ?? '');
        if ($stato) { $sql .= ' AND sc.stato = :stato'; $params[':stato'] = $stato; }
        $tipo = sanitizeString($_GET['tipo'] ?? '');
        if ($tipo) { $sql .= ' AND sc.tipo = :tipo'; $params[':tipo'] = $tipo; }
        if (get('prossime', false, 'bool')) {
            $sql .= ' AND sc.data_scadenza BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)';
        }
        $sql .= ' ORDER BY sc.data_scadenza ASC';

        $result  = Database::paginate($sql, $params, get('page', 1, 'int'), get('per_page', 25, 'int'));
        $result['data'] = array_map(function($s) {
            $s['data_scadenza_it'] = formatDate($s['data_scadenza']);
            $s['scaduta']  = $s['giorni'] < 0;
            $s['urgente']  = $s['giorni'] >= 0 && $s['giorni'] <= 7;
            return $s;
        }, $result['data']);
        jsonResponse($result);
        break;

    case 'POST':
        if (!Auth::can('scadenze.create')) jsonError('Permesso negato', 403);
        Auth::requireCsrf();
        $body = !empty($_POST) ? $_POST : getJsonBody();
        $v = new Validator($body);
        $v->required('titolo', 'Titolo')->required('data_scadenza', 'Data scadenza')
          ->date('data_scadenza', 'Data scadenza')->orFail();

        $id = Database::insert('scadenze', [
            'commessa_id'       => sanitizeInt($body['commessa_id'] ?? null, 1) ?? null,
            'appalto_id'        => sanitizeInt($body['appalto_id'] ?? null, 1) ?? null,
            'tipo'              => in_array($body['tipo'] ?? '', ['CONTRATTUALE','NORMATIVA','DOCUMENTALE','PAGAMENTO','COMUNICAZIONE','COLLAUDO','ALTRO']) ? $body['tipo'] : 'ALTRO',
            'titolo'            => sanitizeString($body['titolo'], 300),
            'descrizione'       => sanitizeString($body['descrizione'] ?? '', 65535),
            'data_scadenza'     => sanitizeDate($body['data_scadenza']),
            'giorni_preavviso'  => sanitizeInt($body['giorni_preavviso'] ?? 15, 0) ?? 15,
            'responsabile_id'   => sanitizeInt($body['responsabile_id'] ?? null, 1) ?? null,
            'priorita'          => in_array($body['priorita'] ?? '', ['BASSA','NORMALE','ALTA','CRITICA']) ? $body['priorita'] : 'NORMALE',
            'note'              => sanitizeString($body['note'] ?? '', 65535),
            'created_by'        => Auth::id(),
        ]);
        Logger::audit('CREATE', 'scadenze', $id);
        jsonSuccess('Scadenza creata', ['id' => $id], 201);
        break;

    case 'PUT':
        if (!$id) jsonError('ID richiesto', 400);
        if (!Auth::can('scadenze.update')) jsonError('Permesso negato', 403);
        Auth::requireCsrf();
        $existing = Database::fetchOne('SELECT * FROM scadenze WHERE id = :id', [':id' => $id]);
        if (!$existing) jsonError('Scadenza non trovata', 404);
        $body = !empty($_POST) ? $_POST : getJsonBody();
        $upd = [];
        foreach (['titolo','descrizione','data_scadenza','stato','priorita','note','responsabile_id','data_completamento'] as $f) {
            if (!array_key_exists($f, $body)) continue;
            $upd[$f] = match($f) {
                'data_scadenza','data_completamento' => sanitizeDate((string)$body[$f]),
                'responsabile_id' => sanitizeInt($body[$f], 1),
                'stato'  => in_array($body[$f], ['ATTIVA','COMPLETATA','SCADUTA','ANNULLATA','PROROGATA']) ? $body[$f] : $existing['stato'],
                default  => sanitizeString((string)$body[$f], 65535),
            };
        }
        Database::update('scadenze', $upd, ['id' => $id]);
        Logger::audit('UPDATE', 'scadenze', $id);
        jsonSuccess('Scadenza aggiornata');
        break;

    default:
        jsonError('Metodo non supportato', 405);
}
