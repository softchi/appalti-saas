<?php
/**
 * API REST: Sistema Notifiche
 *
 * GET  /api/notifiche.php           - Lista notifiche utente
 * POST /api/notifiche.php?action=read&id=N  - Segna come letta
 * POST /api/notifiche.php?action=readall    - Segna tutte come lette
 *
 * @version 1.0.0
 */
define('APP_INIT', true);
require_once __DIR__ . '/../php/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
if (!Auth::check()) jsonError('Non autenticato', 401);

$method = strtoupper($_SERVER['REQUEST_METHOD']);
$action = sanitizeString($_GET['action'] ?? '');
$id     = sanitizeInt($_GET['id'] ?? null, 1);
$uid    = Auth::id();

switch ($method) {
    case 'GET':
        $limit  = min(get('limit', 20, 'int'), 100);
        $onlyUnread = get('unread', false, 'bool');

        $sql    = 'SELECT * FROM notifiche WHERE utente_id = :uid';
        $params = [':uid' => $uid];
        if ($onlyUnread) { $sql .= ' AND letta = 0'; }
        $sql .= ' ORDER BY created_at DESC LIMIT :limit';
        $params[':limit'] = $limit;

        $notifiche = Database::fetchAll($sql, $params);
        $unread    = (int)Database::fetchValue(
            'SELECT COUNT(*) FROM notifiche WHERE utente_id = :uid AND letta = 0',
            [':uid' => $uid]
        );

        $notifiche = array_map(function($n) {
            $n['created_at_it'] = formatDateTime($n['created_at']);
            $n['ago'] = timeAgo($n['created_at']);
            return $n;
        }, $notifiche);

        jsonResponse(['notifiche' => $notifiche, 'unread_count' => $unread]);
        break;

    case 'POST':
        Auth::requireCsrf();
        if ($action === 'readall') {
            Database::query(
                'UPDATE notifiche SET letta = 1, data_lettura = NOW() WHERE utente_id = :uid AND letta = 0',
                [':uid' => $uid]
            );
            jsonSuccess('Tutte le notifiche segnate come lette');
        } elseif ($action === 'read' && $id) {
            Database::update('notifiche',
                ['letta' => 1, 'data_lettura' => date('Y-m-d H:i:s')],
                ['id' => $id, 'utente_id' => $uid]
            );
            jsonSuccess('Notifica segnata come letta');
        } else {
            jsonError('Azione non valida', 400);
        }
        break;

    default:
        jsonError('Metodo non supportato', 405);
}

function timeAgo(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'Adesso';
    if ($diff < 3600)   return floor($diff/60) . ' min fa';
    if ($diff < 86400)  return floor($diff/3600) . ' ore fa';
    if ($diff < 604800) return floor($diff/86400) . ' giorni fa';
    return formatDate($datetime);
}
