<?php
/**
 * API: Generazione Report
 *
 * GET /api/reports.php?tipo=avanzamento&commessa_id=N
 * GET /api/reports.php?tipo=pm_sal&commessa_id=N
 * GET /api/reports.php?tipo=costi&commessa_id=N
 * GET /api/reports.php?tipo=pm_scadenze
 * GET /api/reports.php?tipo=gantt&commessa_id=N
 *
 * @version 1.0.0
 */
define('APP_INIT', true);
require_once __DIR__ . '/../php/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
if (!Auth::check()) jsonError('Non autenticato', 401);
if (!Auth::can('report.read')) jsonError('Permesso negato', 403);

$tipo       = sanitizeString($_GET['tipo'] ?? 'avanzamento');
$commessaId = sanitizeInt($_GET['commessa_id'] ?? null, 1);
$formato    = sanitizeString($_GET['formato'] ?? 'json'); // json | csv

switch ($tipo) {
    case 'avanzamento':
        reportAvanzamento($commessaId);
        break;
    case 'pm_sal':
        reportSal($commessaId ?? 0);
        break;
    case 'costi':
        reportCosti($commessaId ?? 0);
        break;
    case 'pm_scadenze':
        reportScadenze($commessaId);
        break;
    case 'gantt':
        reportGantt($commessaId ?? 0);
        break;
    case 'riepilogo':
        reportRiepilogoGlobale();
        break;
    default:
        jsonError('Tipo report non valido', 400);
}

// =============================================================================

function reportAvanzamento(?int $commessaId): void
{
    if ($commessaId) {
        // Report singola commessa
        $commessa = Database::fetchOne('SELECT * FROM v_commesse_riepilogo WHERE id = :id', [':id' => $commessaId]);
        if (!$commessa) jsonError('Commessa non trovata', 404);

        $pm_tasks = Database::fetchAll(
            'SELECT f.nome AS fase, t.codice_wbs, t.nome, t.tipo, t.stato,
                    t.data_inizio_prevista, t.data_fine_prevista,
                    t.data_inizio_effettiva, t.data_fine_effettiva,
                    t.percentuale_completamento, t.durata_prevista,
                    DATEDIFF(t.data_fine_effettiva, t.data_fine_prevista) AS scostamento_gg,
                    CONCAT(u.cognome, " ", u.nome) AS responsabile
             FROM pm_tasks t
             LEFT JOIN pm_fasi_lavoro f ON f.id = t.fase_id
             LEFT JOIN pm_utenti u ON u.id = t.assegnato_a
             WHERE t.commessa_id = :id
             ORDER BY t.ordine, t.codice_wbs',
            [':id' => $commessaId]
        );

        // Riepilogo per fase
        $perFase = Database::fetchAll(
            'SELECT f.nome AS fase, f.colore,
                    COUNT(t.id) AS totale_tasks,
                    SUM(t.stato = "COMPLETATO") AS completati,
                    SUM(t.stato = "IN_RITARDO") AS in_ritardo,
                    AVG(t.percentuale_completamento) AS media_avanzamento
             FROM pm_fasi_lavoro f
             LEFT JOIN pm_tasks t ON t.fase_id = f.id AND t.commessa_id = :id
             WHERE f.commessa_id = :id2
             GROUP BY f.id',
            [':id' => $commessaId, ':id2' => $commessaId]
        );

        jsonResponse([
            'tipo'       => 'avanzamento_commessa',
            'generato_il' => date('d/m/Y H:i'),
            'commessa'   => $commessa,
            'pm_tasks'      => $pm_tasks,
            'per_fase'   => $perFase,
            'riepilogo'  => [
                'totale_tasks'   => count($pm_tasks),
                'completati'     => count(array_filter($pm_tasks, fn($t) => $t['stato'] === 'COMPLETATO')),
                'in_ritardo'     => count(array_filter($pm_tasks, fn($t) => $t['stato'] === 'IN_RITARDO')),
                'avanzamento'    => $commessa['percentuale_avanzamento'],
                'scostamento'    => $commessa['scostamento_giorni'],
            ],
        ]);
    }

    // Report globale tutte le pm_commesse
    $pm_commesse = Database::fetchAll(
        'SELECT id, codice_commessa, oggetto, stato, percentuale_avanzamento,
                data_fine_prevista, data_fine_effettiva, scostamento_giorni,
                importo_contrattuale, tasks_in_ritardo, rup_nominativo
         FROM v_commesse_riepilogo
         ORDER BY stato, data_fine_prevista'
    );

    jsonResponse([
        'tipo'      => 'avanzamento_globale',
        'generato_il' => date('d/m/Y H:i'),
        'pm_commesse'  => $pm_commesse,
        'totali'    => [
            'n_commesse'       => count($pm_commesse),
            'in_esecuzione'    => count(array_filter($pm_commesse, fn($c) => $c['stato'] === 'IN_ESECUZIONE')),
            'completate'       => count(array_filter($pm_commesse, fn($c) => $c['stato'] === 'COMPLETATA')),
            'valore_totale'    => formatEuro(array_sum(array_column($pm_commesse, 'importo_contrattuale'))),
        ],
    ]);
}

function reportSal(int $commessaId): void
{
    $pm_sal = Database::fetchAll(
        'SELECT s.numero_sal, s.data_inizio, s.data_fine, s.data_emissione, s.data_approvazione, s.data_pagamento,
                s.importo_lavori, s.importo_sicurezza, s.importo_varianti, s.importo_totale,
                s.importo_cumulato, s.ritenuta_garanzia, s.importo_netto,
                s.percentuale_avanzamento, s.stato,
                CONCAT(udl.cognome, " ", udl.nome) AS dl_nome,
                CONCAT(urup.cognome, " ", urup.nome) AS rup_nome
         FROM pm_sal s
         LEFT JOIN pm_utenti udl ON udl.id = s.dl_id
         LEFT JOIN pm_utenti urup ON urup.id = s.rup_id
         WHERE s.commessa_id = :cid
         ORDER BY s.numero_sal',
        [':cid' => $commessaId]
    );

    $totPagato   = array_sum(array_map(fn($s) => $s['stato'] === 'PAGATO' ? (float)$s['importo_netto'] : 0, $pm_sal));
    $totApprovato = array_sum(array_map(fn($s) => in_array($s['stato'],['APPROVATO','PAGATO']) ? (float)$s['importo_totale'] : 0, $pm_sal));
    $commessa    = Database::fetchOne('SELECT importo_contrattuale, importo_sicurezza FROM pm_commesse WHERE id = :id', [':id' => $commessaId]);
    $importoBase = (float)$commessa['importo_contrattuale'] + (float)$commessa['importo_sicurezza'];

    $pm_sal = array_map(function($s) {
        foreach (['data_inizio','data_fine','data_emissione','data_approvazione','data_pagamento'] as $f) {
            $s[$f . '_it'] = formatDate($s[$f]);
        }
        foreach (['importo_lavori','importo_sicurezza','importo_varianti','importo_totale','importo_cumulato','ritenuta_garanzia','importo_netto'] as $f) {
            $s[$f . '_fmt'] = formatEuro((float)$s[$f]);
        }
        return $s;
    }, $pm_sal);

    jsonResponse([
        'tipo'              => 'report_sal',
        'generato_il'       => date('d/m/Y H:i'),
        'pm_sal'               => $pm_sal,
        'totali'            => [
            'importo_base'          => formatEuro($importoBase),
            'totale_approvato_fmt'  => formatEuro($totApprovato),
            'totale_pagato_fmt'     => formatEuro($totPagato),
            'residuo_fmt'           => formatEuro($importoBase - $totApprovato),
            'perc_liquidata'        => $importoBase > 0 ? round(($totApprovato / $importoBase) * 100, 2) : 0,
        ],
    ]);
}

function reportCosti(int $commessaId): void
{
    $categorie = Database::fetchAll(
        'SELECT cl.codice, cl.descrizione, cl.unita_misura, cl.prezzo_unitario,
                cl.quantita_contrattuale, cl.importo_contrattuale,
                COALESCE(SUM(sv.quantita_cumulata), 0) AS quantita_eseguita,
                COALESCE(SUM(sv.quantita_cumulata) * cl.prezzo_unitario, 0) AS importo_eseguito,
                cl.quantita_contrattuale - COALESCE(SUM(sv.quantita_cumulata), 0) AS quantita_residua
         FROM pm_categorie_lavoro cl
         LEFT JOIN pm_sal_voci sv ON sv.categoria_id = cl.id
             AND sv.sal_id IN (SELECT id FROM pm_sal WHERE commessa_id = :cid AND stato IN ("APPROVATO","PAGATO"))
         WHERE cl.commessa_id = :cid2
         GROUP BY cl.id
         ORDER BY cl.ordine, cl.codice',
        [':cid' => $commessaId, ':cid2' => $commessaId]
    );

    $totaleContrattuale = array_sum(array_column($categorie, 'importo_contrattuale'));
    $totaleEseguito     = array_sum(array_column($categorie, 'importo_eseguito'));

    $categorie = array_map(function($c) {
        $c['importo_contrattuale_fmt'] = formatEuro((float)$c['importo_contrattuale']);
        $c['importo_eseguito_fmt']     = formatEuro((float)$c['importo_eseguito']);
        $c['perc_eseguita'] = $c['importo_contrattuale'] > 0
            ? round(($c['importo_eseguito'] / $c['importo_contrattuale']) * 100, 2) : 0;
        return $c;
    }, $categorie);

    jsonResponse([
        'tipo'               => 'report_costi',
        'generato_il'        => date('d/m/Y H:i'),
        'categorie'          => $categorie,
        'totale_contrattuale_fmt' => formatEuro($totaleContrattuale),
        'totale_eseguito_fmt'    => formatEuro($totaleEseguito),
        'scostamento_fmt'        => formatEuro($totaleEseguito - $totaleContrattuale),
        'perc_eseguita'          => $totaleContrattuale > 0
            ? round(($totaleEseguito / $totaleContrattuale) * 100, 2) : 0,
    ]);
}

function reportScadenze(?int $commessaId): void
{
    $sql    = 'SELECT sc.*, c.codice_commessa, c.oggetto AS commessa,
                      CONCAT(u.cognome, " ", u.nome) AS responsabile,
                      DATEDIFF(sc.data_scadenza, CURDATE()) AS giorni
               FROM pm_scadenze sc
               LEFT JOIN pm_commesse c ON c.id = sc.commessa_id
               LEFT JOIN pm_utenti u ON u.id = sc.responsabile_id
               WHERE sc.stato = "ATTIVA"';
    $params = [];
    if ($commessaId) { $sql .= ' AND sc.commessa_id = :cid'; $params[':cid'] = $commessaId; }
    $sql .= ' ORDER BY sc.data_scadenza ASC';

    $pm_scadenze = Database::fetchAll($sql, $params);
    $pm_scadenze = array_map(function($s) {
        $s['data_scadenza_it'] = formatDate($s['data_scadenza']);
        $s['stato_urgenza'] = $s['giorni'] < 0 ? 'SCADUTA' : ($s['giorni'] <= 7 ? 'CRITICA' : ($s['giorni'] <= 15 ? 'URGENTE' : 'NORMALE'));
        return $s;
    }, $pm_scadenze);

    jsonResponse([
        'tipo'       => 'report_scadenze',
        'generato_il' => date('d/m/Y H:i'),
        'pm_scadenze'   => $pm_scadenze,
        'riepilogo'  => [
            'totale'  => count($pm_scadenze),
            'scadute' => count(array_filter($pm_scadenze, fn($s) => $s['giorni'] < 0)),
            'critiche' => count(array_filter($pm_scadenze, fn($s) => $s['giorni'] >= 0 && $s['giorni'] <= 7)),
        ],
    ]);
}

function reportGantt(int $commessaId): void
{
    if (!$commessaId) jsonError('commessa_id richiesto', 400);
    $commessa = Database::fetchOne('SELECT id, codice_commessa, oggetto, data_inizio_prevista, data_fine_prevista FROM pm_commesse WHERE id = :id', [':id' => $commessaId]);
    if (!$commessa) jsonError('Commessa non trovata', 404);

    $pm_tasks = Database::fetchAll(
        'SELECT t.id, t.parent_id, t.codice_wbs, t.nome, t.tipo,
                t.data_inizio_prevista, t.data_fine_prevista,
                t.data_inizio_effettiva, t.data_fine_effettiva,
                t.percentuale_completamento, t.stato, t.ordine,
                CONCAT(u.cognome, " ", u.nome) AS responsabile
         FROM pm_tasks t
         LEFT JOIN pm_utenti u ON u.id = t.assegnato_a
         WHERE t.commessa_id = :id
         ORDER BY t.ordine, t.codice_wbs',
        [':id' => $commessaId]
    );

    $dipendenze = Database::fetchAll(
        'SELECT dt.task_id, dt.task_pred_id, dt.tipo AS dep_tipo, dt.lag_giorni
         FROM pm_dipendenze_tasks dt
         JOIN pm_tasks t ON t.id = dt.task_id WHERE t.commessa_id = :id',
        [':id' => $commessaId]
    );

    jsonResponse([
        'tipo'        => 'report_gantt',
        'generato_il' => date('d/m/Y H:i'),
        'commessa'    => $commessa,
        'pm_tasks'       => $pm_tasks,
        'dipendenze'  => $dipendenze,
    ]);
}

function reportRiepilogoGlobale(): void
{
    $stats = Database::fetchOne(
        'SELECT
           COUNT(*) AS totale_commesse,
           SUM(stato = "IN_ESECUZIONE") AS in_esecuzione,
           SUM(stato = "COMPLETATA") AS completate,
           SUM(stato = "SOSPESA") AS sospese,
           SUM(importo_contrattuale) AS valore_totale,
           AVG(percentuale_avanzamento) AS media_avanzamento,
           SUM(CASE WHEN scostamento_giorni > 0 THEN 1 ELSE 0 END) AS in_ritardo
         FROM pm_commesse WHERE stato != "ANNULLATA"'
    );

    $perSA = Database::fetchAll(
        'SELECT sa.denominazione, COUNT(c.id) AS n_commesse,
                SUM(c.importo_contrattuale) AS valore
         FROM pm_commesse c
         JOIN pm_appalti a ON a.id = c.appalto_id
         JOIN pm_stazioni_appaltanti sa ON sa.id = a.stazione_appaltante_id
         GROUP BY sa.id ORDER BY valore DESC LIMIT 10'
    );

    jsonResponse([
        'tipo'       => 'riepilogo_globale',
        'generato_il' => date('d/m/Y H:i'),
        'statistiche' => $stats,
        'per_stazione_appaltante' => $perSA,
        'valore_totale_fmt' => formatEuro((float)$stats['valore_totale']),
    ]);
}
