<?php
/**
 * API REST: Gestione Utenti
 * @version 1.0.0
 */
define('APP_INIT', true);
require_once __DIR__ . '/../php/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
if (!Auth::check()) jsonError('Non autenticato', 401);

$method = strtoupper($_SERVER['REQUEST_METHOD']);
$id     = sanitizeInt($_GET['id'] ?? null, 1);

switch ($method) {
    case 'GET':
        if (!Auth::can('pm_utenti.read')) jsonError('Permesso negato', 403);
        if ($id) {
            $u = Database::fetchOne(
                'SELECT u.id, u.uuid, u.nome, u.cognome, u.email, u.telefono, u.qualifica,
                        u.attivo, u.ultimo_accesso, u.created_at,
                        r.id AS ruolo_id, r.codice AS ruolo_codice, r.nome AS ruolo_nome
                 FROM pm_utenti u JOIN pm_ruoli r ON r.id = u.ruolo_id WHERE u.id = :id',
                [':id' => $id]
            );
            if (!$u) jsonError('Utente non trovato', 404);
            $u['ultimo_accesso_it'] = formatDateTime($u['ultimo_accesso']);
            jsonResponse(['utente' => $u]);
        }

        // Lista pm_utenti
        $sql = 'SELECT u.id, u.nome, u.cognome, u.email, u.telefono, u.qualifica, u.attivo,
                       r.codice AS ruolo_codice, r.nome AS ruolo_nome, u.ultimo_accesso
                FROM pm_utenti u JOIN pm_ruoli r ON r.id = u.ruolo_id WHERE 1=1';
        $params = [];
        if ($ruolo = sanitizeString($_GET['ruolo'] ?? '')) {
            $sql .= ' AND r.codice = :ruolo'; $params[':ruolo'] = $ruolo;
        }
        if ($q = sanitizeString($_GET['q'] ?? '')) {
            $sql .= ' AND (u.cognome LIKE :q OR u.nome LIKE :q2 OR u.email LIKE :q3)';
            $params[':q'] = '%'.$q.'%'; $params[':q2'] = '%'.$q.'%'; $params[':q3'] = '%'.$q.'%';
        }
        $sql .= ' ORDER BY u.cognome, u.nome';
        $result = Database::paginate($sql, $params, get('page', 1, 'int'), get('per_page', 20, 'int'));
        $result['data'] = array_map(function($u) {
            $u['nome_completo'] = $u['cognome'] . ' ' . $u['nome'];
            return $u;
        }, $result['data']);

        // Anche lista semplice per dropdown
        if (get('dropdown', false, 'bool')) {
            $lista = Database::fetchAll(
                'SELECT u.id, CONCAT(u.cognome, " ", u.nome) AS nome_completo, r.codice AS ruolo
                 FROM pm_utenti u JOIN pm_ruoli r ON r.id = u.ruolo_id WHERE u.attivo = 1
                 ORDER BY u.cognome, u.nome'
            );
            jsonResponse(['pm_utenti' => $lista]);
        }
        jsonResponse($result);
        break;

    case 'POST':
        if (!Auth::can('pm_utenti.create')) jsonError('Permesso negato', 403);
        Auth::requireCsrf();
        $body = !empty($_POST) ? $_POST : getJsonBody();
        $v = new Validator($body);
        $v->required('nome','Nome')->required('cognome','Cognome')
          ->required('email','Email')->email('email','Email')
          ->required('password','Password')->minLength('password', PASSWORD_MIN_LENGTH, 'Password')
          ->required('ruolo_id','Ruolo')
          ->unique('email','pm_utenti','email',null,'Email')
          ->orFail();

        $uid = Database::insert('pm_utenti', [
            'uuid'          => generateUUID(),
            'ruolo_id'      => (int)$body['ruolo_id'],
            'nome'          => sanitizeString($body['nome'], 100),
            'cognome'       => sanitizeString($body['cognome'], 100),
            'email'         => sanitizeEmail($body['email']),
            'password_hash' => Auth::hashPassword($body['password']),
            'telefono'      => sanitizeString($body['telefono'] ?? '', 30),
            'qualifica'     => sanitizeString($body['qualifica'] ?? '', 150),
            'attivo'        => 1,
            'email_verificata' => 1,
        ]);
        Logger::audit('CREATE', 'pm_utenti', $uid);
        jsonSuccess('Utente creato', ['id' => $uid], 201);
        break;

    case 'PUT':
        if (!$id) jsonError('ID richiesto', 400);
        $uid = Auth::id();
        // Un utente può modificare solo se stesso, oppure admin
        if ($id != $uid && !Auth::can('pm_utenti.update')) jsonError('Permesso negato', 403);
        Auth::requireCsrf();
        $body    = !empty($_POST) ? $_POST : getJsonBody();
        $existing = Database::fetchOne('SELECT * FROM pm_utenti WHERE id = :id', [':id' => $id]);
        if (!$existing) jsonError('Utente non trovato', 404);
        $upd = [];
        $allowedFields = ['nome','cognome','telefono','qualifica','ordine_professionale','numero_iscrizione'];
        foreach ($allowedFields as $f) {
            if (isset($body[$f])) $upd[$f] = sanitizeString($body[$f], 255);
        }
        // Solo admin può cambiare ruolo
        if (Auth::hasRole(['SUPERADMIN','ADMIN']) && isset($body['ruolo_id'])) {
            $upd['ruolo_id'] = (int)$body['ruolo_id'];
        }
        if (Auth::hasRole(['SUPERADMIN','ADMIN']) && isset($body['attivo'])) {
            $upd['attivo'] = (int)(bool)$body['attivo'];
        }
        // Cambio password
        if (!empty($body['new_password'])) {
            if (empty($body['current_password']) && $id == $uid) {
                jsonError('Password attuale richiesta', 422);
            }
            if ($id == $uid && !password_verify($body['current_password'], $existing['password_hash'])) {
                jsonError('Password attuale non corretta', 401);
            }
            $upd['password_hash'] = Auth::hashPassword($body['new_password']);
        }
        if (empty($upd)) jsonError('Nessun dato da aggiornare', 400);
        Database::update('pm_utenti', $upd, ['id' => $id]);
        Logger::audit('UPDATE', 'pm_utenti', $id);
        jsonSuccess('Utente aggiornato');
        break;

    default:
        jsonError('Metodo non supportato', 405);
}
