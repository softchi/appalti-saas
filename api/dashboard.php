<?php
/**
 * API: Dashboard - KPI e dati aggregati
 *
 * GET /api/dashboard.php - Dati dashboard home
 *
 * @version 1.0.0
 */
define('APP_INIT', true);
require_once __DIR__ . '/../php/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
if (!Auth::check()) jsonError('Non autenticato', 401);

$user = Auth::user();
$uid  = (int)$user['id'];

// ============================================================================
// KPI GLOBALI
// ============================================================================
$kpiCommesse = Database::fetchOne(
    'SELECT
       COUNT(*) AS totale,
       SUM(stato = "IN_ESECUZIONE") AS in_esecuzione,
       SUM(stato = "COMPLETATA") AS completate,
       SUM(stato = "SOSPESA") AS sospese,
       SUM(stato = "ANNULLATA") AS annullate,
       SUM(stato = "BOZZA" OR stato = "PIANIFICAZIONE") AS in_pianificazione,
       ROUND(AVG(percentuale_avanzamento), 1) AS media_avanzamento,
       SUM(importo_contrattuale) AS valore_totale
     FROM commesse c
     WHERE 1=1' . (in_array($user['ruolo_codice'], ['SUPERADMIN','ADMIN','RUP','AMMINISTRAZIONE']) ? '' :
       ' AND (c.rup_id = :uid OR c.pm_id = :uid2 OR c.dl_id = :uid3
              OR EXISTS (SELECT 1 FROM commesse_utenti cu WHERE cu.commessa_id = c.id AND cu.utente_id = :uid4))'),
    in_array($user['ruolo_codice'], ['SUPERADMIN','ADMIN','RUP','AMMINISTRAZIONE']) ? [] :
        [':uid' => $uid, ':uid2' => $uid, ':uid3' => $uid, ':uid4' => $uid]
);

// Tasks in scadenza/ritardo
$kpiTasks = Database::fetchOne(
    'SELECT
       COUNT(*) AS totale,
       SUM(stato = "IN_CORSO") AS in_corso,
       SUM(stato = "IN_RITARDO") AS in_ritardo,
       SUM(stato = "COMPLETATO") AS completati,
       SUM(tipo = "MILESTONE") AS milestones
     FROM tasks t
     JOIN commesse c ON c.id = t.commessa_id
     WHERE t.assegnato_a = :uid OR c.pm_id = :uid2',
    [':uid' => $uid, ':uid2' => $uid]
);

// SAL in attesa approvazione
$salDaApprovare = (int)Database::fetchValue(
    'SELECT COUNT(*) FROM sal s
     JOIN commesse c ON c.id = s.commessa_id
     WHERE s.stato = "EMESSO" AND c.rup_id = :uid',
    [':uid' => $uid]
);

// Scadenze prossime (7 giorni)
$scadenzeProssime = Database::fetchAll(
    'SELECT sc.id, sc.titolo, sc.data_scadenza, sc.tipo, sc.priorita,
            c.codice_commessa, c.oggetto AS commessa,
            DATEDIFF(sc.data_scadenza, CURDATE()) AS giorni
     FROM scadenze sc
     LEFT JOIN commesse c ON c.id = sc.commessa_id
     WHERE sc.stato = "ATTIVA"
       AND sc.data_scadenza BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
       AND (sc.responsabile_id = :uid OR c.pm_id = :uid2 OR c.rup_id = :uid3)
     ORDER BY sc.data_scadenza ASC
     LIMIT 10',
    [':uid' => $uid, ':uid2' => $uid, ':uid3' => $uid]
);

// Scadenze scadute
$scadenzeScadute = (int)Database::fetchValue(
    'SELECT COUNT(*) FROM scadenze sc
     LEFT JOIN commesse c ON c.id = sc.commessa_id
     WHERE sc.stato = "ATTIVA" AND sc.data_scadenza < CURDATE()
       AND (sc.responsabile_id = :uid OR c.pm_id = :uid2)',
    [':uid' => $uid, ':uid2' => $uid]
);

// ============================================================================
// COMMESSE RECENTI (con mini KPI)
// ============================================================================
$commesseRecenti = Database::fetchAll(
    'SELECT c.id, c.codice_commessa, c.oggetto, c.stato, c.percentuale_avanzamento,
            c.data_fine_prevista, c.priorita, c.colore,
            sa.denominazione AS stazione_appaltante,
            DATEDIFF(c.data_fine_prevista, CURDATE()) AS giorni_alla_fine,
            (SELECT COUNT(*) FROM tasks t WHERE t.commessa_id = c.id AND t.stato = "IN_RITARDO") AS tasks_ritardo
     FROM commesse c
     JOIN appalti a ON a.id = c.appalto_id
     JOIN stazioni_appaltanti sa ON sa.id = a.stazione_appaltante_id
     WHERE c.stato IN ("IN_ESECUZIONE","PIANIFICAZIONE")
     ORDER BY c.updated_at DESC
     LIMIT 6'
);

// ============================================================================
// GRAFICO AVANZAMENTO MENSILE (ultimi 6 mesi)
// ============================================================================
$avanzamentoMensile = Database::fetchAll(
    'SELECT
       DATE_FORMAT(created_at, "%Y-%m") AS mese,
       DATE_FORMAT(created_at, "%b %Y") AS mese_label,
       COUNT(DISTINCT CASE WHEN stato = "COMPLETATA" THEN id END) AS completate,
       COUNT(DISTINCT CASE WHEN stato IN ("IN_ESECUZIONE","PIANIFICAZIONE") THEN id END) AS in_corso
     FROM commesse
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY DATE_FORMAT(created_at, "%Y-%m")
     ORDER BY mese ASC'
);

// ============================================================================
// VALORE ECONOMICO PER STATO
// ============================================================================
$valorePerStato = Database::fetchAll(
    'SELECT stato,
            COUNT(*) AS n,
            ROUND(SUM(importo_contrattuale), 2) AS valore
     FROM commesse
     GROUP BY stato
     ORDER BY valore DESC'
);

// ============================================================================
// MY TASKS (tasks assegnati all\'utente corrente)
// ============================================================================
$myTasks = Database::fetchAll(
    'SELECT t.id, t.nome, t.stato, t.priorita, t.percentuale_completamento,
            t.data_fine_prevista, c.codice_commessa, c.id AS commessa_id,
            DATEDIFF(t.data_fine_prevista, CURDATE()) AS giorni_alla_scadenza
     FROM tasks t
     JOIN commesse c ON c.id = t.commessa_id
     WHERE t.assegnato_a = :uid AND t.stato NOT IN ("COMPLETATO","ANNULLATO")
     ORDER BY t.data_fine_prevista ASC
     LIMIT 8',
    [':uid' => $uid]
);

// ============================================================================
// ATTIVITÀ RECENTE (audit log)
// ============================================================================
$attivitaRecente = Database::fetchAll(
    'SELECT al.azione, al.entita_tipo, al.entita_id, al.created_at,
            CONCAT(u.cognome, " ", u.nome) AS utente
     FROM audit_log al
     LEFT JOIN utenti u ON u.id = al.utente_id
     WHERE al.esito = "OK"
     ORDER BY al.created_at DESC
     LIMIT 10'
);

// Formatta scadenze prossime
$scadenzeProssime = array_map(function($s) {
    $s['data_scadenza_it'] = formatDate($s['data_scadenza']);
    $s['urgente'] = $s['giorni'] <= 3;
    return $s;
}, $scadenzeProssime);

// Formatta commesse
$commesseRecenti = array_map(function($c) {
    $c['data_fine_it'] = formatDate($c['data_fine_prevista']);
    $c['in_ritardo']   = ($c['giorni_alla_fine'] !== null && $c['giorni_alla_fine'] < 0);
    return $c;
}, $commesseRecenti);

jsonResponse([
    'kpi' => [
        'commesse'         => $kpiCommesse,
        'tasks'            => $kpiTasks,
        'sal_da_approvare' => $salDaApprovare,
        'scadenze_scadute' => $scadenzeScadute,
        'valore_totale_fmt' => formatEuro((float)($kpiCommesse['valore_totale'] ?? 0)),
    ],
    'commesse_recenti'    => $commesseRecenti,
    'scadenze_prossime'   => $scadenzeProssime,
    'my_tasks'            => $myTasks,
    'avanzamento_mensile' => $avanzamentoMensile,
    'valore_per_stato'    => $valorePerStato,
    'attivita_recente'    => $attivitaRecente,
    'user'                => [
        'nome'          => $user['nome'] . ' ' . $user['cognome'],
        'ruolo'         => $user['ruolo_nome'],
        'ultimo_accesso' => formatDateTime($user['ultimo_accesso']),
    ],
]);
