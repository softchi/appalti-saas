<?php
/**
 * API: AI Assistant - Assistente intelligente per Project Manager
 *
 * POST /api/ai_assistant.php
 *
 * Analizza dati commessa e fornisce:
 * - Analisi avanzamento lavori
 * - Individuazione ritardi
 * - Previsione rischi
 * - Generazione report narrativo
 * - Suggerimenti azioni correttive
 *
 * Se Claude API non è configurata, usa un motore di analisi rule-based.
 *
 * @version 1.0.0
 */
define('APP_INIT', true);
require_once __DIR__ . '/../php/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
if (!Auth::check()) jsonError('Non autenticato', 401);
if (!Auth::can('ai.use')) jsonError('Permesso negato', 403);
if (!isMethod('POST')) jsonError('Metodo non consentito', 405);
Auth::requireCsrf();

$body       = !empty($_POST) ? $_POST : getJsonBody();
$commessaId = sanitizeInt($body['commessa_id'] ?? null, 1);
$tipoAnalisi = sanitizeString($body['tipo'] ?? 'completa');
$domanda    = sanitizeString($body['domanda'] ?? '', 1000);

if (!$commessaId) jsonError('commessa_id richiesto', 400);

// Verifica accesso
if (!Auth::canAccessCommessa($commessaId) && !Auth::hasRole(['SUPERADMIN','ADMIN'])) {
    jsonError('Accesso negato', 403);
}

// =============================================================================
// RACCOLTA DATI COMMESSA PER ANALISI
// =============================================================================
$commessa = Database::fetchOne(
    'SELECT c.*,
            sa.denominazione AS stazione_appaltante,
            i.ragione_sociale AS impresa,
            a.codice_cig, a.codice_cup,
            CONCAT(urup.cognome, " ", urup.nome) AS rup_nome,
            CONCAT(upm.cognome, " ", upm.nome) AS pm_nome
     FROM pm_commesse c
     JOIN pm_appalti a ON a.id = c.appalto_id
     JOIN pm_stazioni_appaltanti sa ON sa.id = a.stazione_appaltante_id
     JOIN pm_imprese i ON i.id = c.impresa_id
     LEFT JOIN pm_utenti urup ON urup.id = c.rup_id
     LEFT JOIN pm_utenti upm ON upm.id = c.pm_id
     WHERE c.id = :id',
    [':id' => $commessaId]
);

if (!$commessa) jsonError('Commessa non trovata', 404);

// Tasks statistiche
$tasksStats = Database::fetchOne(
    'SELECT COUNT(*) AS totale,
            SUM(stato = "COMPLETATO") AS completati,
            SUM(stato = "IN_CORSO") AS in_corso,
            SUM(stato = "IN_RITARDO") AS in_ritardo,
            SUM(stato = "NON_INIZIATO") AS non_iniziati,
            SUM(tipo = "MILESTONE") AS milestones,
            AVG(percentuale_completamento) AS media_perc,
            MIN(data_inizio_prevista) AS prima_data,
            MAX(data_fine_prevista) AS ultima_data
     FROM pm_tasks WHERE commessa_id = :id AND stato != "ANNULLATO"',
    [':id' => $commessaId]
);

// Tasks in ritardo (dettaglio)
$tasksRitardo = Database::fetchAll(
    'SELECT t.nome, t.data_fine_prevista, t.percentuale_completamento, t.stato,
            DATEDIFF(CURDATE(), t.data_fine_prevista) AS giorni_ritardo,
            CONCAT(u.cognome, " ", u.nome) AS responsabile
     FROM pm_tasks t
     LEFT JOIN pm_utenti u ON u.id = t.assegnato_a
     WHERE t.commessa_id = :id AND t.stato = "IN_RITARDO"
     ORDER BY giorni_ritardo DESC LIMIT 10',
    [':id' => $commessaId]
);

// SAL storico
$salStorico = Database::fetchAll(
    'SELECT numero_sal, data_fine, importo_cumulato, percentuale_avanzamento, stato
     FROM pm_sal WHERE commessa_id = :id ORDER BY numero_sal DESC LIMIT 5',
    [':id' => $commessaId]
);

// Scadenze critiche
$scadenzeCritiche = Database::fetchAll(
    'SELECT titolo, data_scadenza, tipo, priorita,
            DATEDIFF(data_scadenza, CURDATE()) AS giorni
     FROM pm_scadenze WHERE commessa_id = :id AND stato = "ATTIVA"
     AND data_scadenza <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     ORDER BY data_scadenza ASC',
    [':id' => $commessaId]
);

// Calcolo SPI (Schedule Performance Index)
$spiData = calcolaSPI($commessa, $tasksStats);

// =============================================================================
// ANALISI AI
// =============================================================================
$analisi = AI_ENABLED
    ? analisiClaude($commessa, $tasksStats, $tasksRitardo, $salStorico, $scadenzeCritiche, $spiData, $tipoAnalisi, $domanda)
    : analisiRuleBased($commessa, $tasksStats, $tasksRitardo, $salStorico, $scadenzeCritiche, $spiData, $tipoAnalisi);

jsonResponse([
    'analisi'   => $analisi,
    'metriche'  => [
        'spi'                    => $spiData['spi'],
        'cpi'                    => $spiData['cpi'],
        'percentuale_avanzamento' => (float)$commessa['percentuale_avanzamento'],
        'tasks_ritardo'          => (int)$tasksStats['in_ritardo'],
        'scadenze_critiche'      => count(array_filter($scadenzeCritiche, fn($s) => $s['giorni'] <= 7)),
        'scostamento_giorni'     => $commessa['scostamento_giorni'],
    ],
    'commessa'  => [
        'id'             => $commessa['id'],
        'codice'         => $commessa['codice_commessa'],
        'oggetto'        => $commessa['oggetto'],
    ],
]);

// =============================================================================
// ANALISI CON CLAUDE API
// =============================================================================

function analisiClaude(
    array $commessa, array $pm_tasks, array $tasksRitardo,
    array $pm_sal, array $pm_scadenze, array $spi,
    string $tipo, string $domanda
): array {

    $prompt = buildPrompt($commessa, $pm_tasks, $tasksRitardo, $pm_sal, $pm_scadenze, $spi, $tipo, $domanda);

    $payload = [
        'model'      => AI_MODEL,
        'max_tokens' => AI_MAX_TOKENS,
        'system'     => "Sei un esperto di project management per lavori pubblici e pm_appalti ai sensi del D.Lgs. 36/2023. " .
                        "Analizza i dati progettuali forniti e rispondi in italiano in modo professionale, " .
                        "preciso e orientato alle azioni correttive. " .
                        "Usa un linguaggio tecnico-professionale appropriato per RUP, DL e PM.",
        'messages'   => [['role' => 'user', 'content' => $prompt]],
    ];

    $ch = curl_init(AI_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . AI_API_KEY,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        Logger::error('Claude API error', ['code' => $httpCode]);
        return analisiRuleBased($commessa, $pm_tasks, $tasksRitardo, $pm_sal, $pm_scadenze, $spi, $tipo);
    }

    $data = json_decode($response, true);
    $text = $data['content'][0]['text'] ?? '';

    return [
        'tipo'    => 'ai',
        'testo'   => $text,
        'modello' => AI_MODEL,
        'tokens'  => $data['usage']['output_tokens'] ?? 0,
    ];
}

function buildPrompt(
    array $commessa, array $pm_tasks, array $tasksRitardo,
    array $pm_sal, array $pm_scadenze, array $spi,
    string $tipo, string $domanda
): string {

    $dataFineP   = $commessa['data_fine_prevista'] ?? 'N/D';
    $giorni      = $commessa['scostamento_giorni'] ?? 0;
    $importo     = formatEuro((float)$commessa['importo_contrattuale']);
    $avanzamento = $commessa['percentuale_avanzamento'];

    $prompt = "## ANALISI COMMESSA\n\n";
    $prompt .= "**Commessa:** {$commessa['codice_commessa']} - {$commessa['oggetto']}\n";
    $prompt .= "**CIG:** {$commessa['codice_cig']} | **CUP:** " . ($commessa['codice_cup'] ?: 'N/D') . "\n";
    $prompt .= "**Stazione Appaltante:** {$commessa['stazione_appaltante']}\n";
    $prompt .= "**Impresa:** {$commessa['impresa']}\n";
    $prompt .= "**Importo Contrattuale:** {$importo}\n";
    $prompt .= "**Stato:** {$commessa['stato']} | **Priorità:** {$commessa['priorita']}\n";
    $prompt .= "**Data Fine Prevista:** " . formatDate($dataFineP) . "\n";
    $prompt .= "**Avanzamento:** {$avanzamento}%\n";
    $prompt .= "**Scostamento Giorni:** {$giorni} giorni\n\n";

    $prompt .= "### INDICATORI SCHEDULE\n";
    $prompt .= "- SPI (Schedule Performance Index): " . round($spi['spi'], 3) . " " .
               ($spi['spi'] < 0.9 ? "⚠️ CRITICO" : ($spi['spi'] < 1.0 ? "⚠️ ATTENZIONE" : "✅ OK")) . "\n";
    $prompt .= "- Giorni di scostamento: " . abs($giorni) . ($giorni > 0 ? ' di RITARDO' : ' di ANTICIPO') . "\n\n";

    $prompt .= "### STATO TASKS (totale: {$pm_tasks['totale']})\n";
    $prompt .= "- Completati: {$pm_tasks['completati']}\n";
    $prompt .= "- In corso: {$pm_tasks['in_corso']}\n";
    $prompt .= "- In ritardo: {$pm_tasks['in_ritardo']}\n";
    $prompt .= "- Non iniziati: {$pm_tasks['non_iniziati']}\n";
    $prompt .= "- Milestone: {$pm_tasks['milestones']}\n\n";

    if (!empty($tasksRitardo)) {
        $prompt .= "### TASKS IN RITARDO\n";
        foreach ($tasksRitardo as $t) {
            $prompt .= "- **{$t['nome']}**: " . $t['giorni_ritardo'] . " giorni di ritardo, " .
                       $t['percentuale_completamento'] . "% completato";
            if ($t['responsabile']) $prompt .= " (resp: {$t['responsabile']})";
            $prompt .= "\n";
        }
        $prompt .= "\n";
    }

    if (!empty($pm_sal)) {
        $prompt .= "### STORICO SAL\n";
        foreach ($pm_sal as $s) {
            $prompt .= "- SAL N.{$s['numero_sal']}: " . formatDate($s['data_fine']) .
                       " | Cumulato: " . formatEuro((float)$s['importo_cumulato']) .
                       " | Avanz: {$s['percentuale_avanzamento']}% | Stato: {$s['stato']}\n";
        }
        $prompt .= "\n";
    }

    if (!empty($pm_scadenze)) {
        $prompt .= "### SCADENZE PROSSIME (30 giorni)\n";
        foreach ($pm_scadenze as $sc) {
            $urgenza = $sc['giorni'] <= 0 ? '🔴 SCADUTA' : ($sc['giorni'] <= 7 ? '🟠 URGENTE' : '🟡 PROSSIMA');
            $prompt .= "- {$urgenza} **{$sc['titolo']}**: " . formatDate($sc['data_scadenza']) .
                       " ({$sc['giorni']} giorni) - {$sc['tipo']}\n";
        }
        $prompt .= "\n";
    }

    $prompt .= "---\n\n";

    switch ($tipo) {
        case 'rischi':
            $prompt .= "## RICHIESTA\nAnalizza i rischi principali del progetto e proponi azioni preventive/correttive concrete. " .
                       "Considera ritardi, scostamenti economici, pm_scadenze critiche e milestone a rischio.";
            break;
        case 'report':
            $prompt .= "## RICHIESTA\nGenera un report di avanzamento professionale pronto per essere presentato al RUP e alla Stazione Appaltante. " .
                       "Include: stato avanzamento, scostamenti, criticità, azioni intraprese e programmate.";
            break;
        case 'previsione':
            $prompt .= "## RICHIESTA\nCon i dati disponibili, fornisci una previsione della data di completamento realistica " .
                       "e dell'importo finale, con analisi degli scenari (ottimistico, realista, pessimista).";
            break;
        case 'domanda':
            $prompt .= "## DOMANDA SPECIFICA\n" . $domanda;
            break;
        default:
            $prompt .= "## RICHIESTA\nEsegui un'analisi completa della commessa includendo:\n" .
                       "1. Valutazione stato attuale e criticità\n" .
                       "2. Analisi dei rischi principali\n" .
                       "3. Azioni correttive consigliate (con priorità)\n" .
                       "4. Previsione completamento\n" .
                       "5. Indicatori chiave da monitorare";
    }

    return $prompt;
}

// =============================================================================
// ANALISI RULE-BASED (fallback senza Claude API)
// =============================================================================
function analisiRuleBased(
    array $commessa, array $pm_tasks, array $tasksRitardo,
    array $pm_sal, array $pm_scadenze, array $spi, string $tipo
): array {

    $report     = [];
    $rischi     = [];
    $azioni     = [];
    $livello    = 'VERDE'; // VERDE, GIALLO, ARANCIO, ROSSO

    $avanzamento = (float)$commessa['percentuale_avanzamento'];
    $ritardo     = (int)($commessa['scostamento_giorni'] ?? 0);
    $tasksRitN   = (int)$pm_tasks['in_ritardo'];
    $spiVal      = $spi['spi'];

    // Valutazione livello critico
    if ($ritardo > 30 || $spiVal < 0.7 || $tasksRitN > 5) {
        $livello = 'ROSSO';
    } elseif ($ritardo > 15 || $spiVal < 0.85 || $tasksRitN > 2) {
        $livello = 'ARANCIO';
    } elseif ($ritardo > 5 || $spiVal < 0.95 || $tasksRitN > 0) {
        $livello = 'GIALLO';
    }

    // Analisi avanzamento
    $report[] = "## Stato Avanzamento Lavori\n";
    $report[] = "La commessa **{$commessa['codice_commessa']}** - {$commessa['oggetto']} " .
                "presenta un avanzamento del **{$avanzamento}%** " .
                "con uno scostamento di **{$ritardo} giorni** rispetto al cronoprogramma contrattuale.";

    $report[] = "\n**Schedule Performance Index (SPI):** " . round($spiVal, 3);
    if ($spiVal < 1.0) {
        $report[] = " - Il progetto è in **ritardo** rispetto alla pianificazione (SPI < 1.0).";
        $rischi[] = ['tipo' => 'RITARDO', 'descrizione' => "SPI = " . round($spiVal, 3) . " indica ritardo significativo nel cronoprogramma.", 'livello' => 'ALTO'];
    } else {
        $report[] = " - Il progetto è **in linea** o anticipato rispetto alla pianificazione.";
    }

    // Tasks in ritardo
    if ($tasksRitN > 0) {
        $report[] = "\n\n**Tasks in ritardo:** {$tasksRitN} attività non sono in linea con il cronoprogramma.";
        foreach (array_slice($tasksRitardo, 0, 5) as $t) {
            $report[] = "\n- _{$t['nome']}_: {$t['giorni_ritardo']} giorni di ritardo ({$t['percentuale_completamento']}% completato)";
        }
        $rischi[] = ['tipo' => 'TASKS', 'descrizione' => "{$tasksRitN} pm_tasks in ritardo impattano sul percorso critico.", 'livello' => $tasksRitN > 3 ? 'ALTO' : 'MEDIO'];
    }

    // SAL
    if (!empty($pm_sal)) {
        $ultimoSal = $pm_sal[0];
        $report[] = "\n\n**Contabilità Lavori:**\n";
        $report[] = "Ultimo SAL emesso: N.{$ultimoSal['numero_sal']} - Importo cumulato: " .
                    formatEuro((float)$ultimoSal['importo_cumulato']) .
                    " (avanzamento contabile: {$ultimoSal['percentuale_avanzamento']}%).";
    }

    // Scadenze critiche
    $scadenzeCrit = array_filter($pm_scadenze, fn($s) => $s['giorni'] <= 7);
    if (!empty($scadenzeCrit)) {
        $n = count($scadenzeCrit);
        $report[] = "\n\n⚠️ **ATTENZIONE - {$n} pm_scadenze critiche entro 7 giorni:**";
        foreach ($scadenzeCrit as $sc) {
            $report[] = "\n- {$sc['titolo']} - Scade il " . formatDate($sc['data_scadenza']);
        }
        $rischi[] = ['tipo' => 'SCADENZE', 'descrizione' => "{$n} pm_scadenze entro 7 giorni richiedono azione immediata.", 'livello' => 'CRITICO'];
    }

    // Azioni correttive
    if ($livello !== 'VERDE') {
        $report[] = "\n\n## Azioni Correttive Consigliate\n";
        if ($spiVal < 0.9) {
            $azioni[] = ['priorita' => 1, 'azione' => 'Convocare riunione di cantiere urgente per analisi cause ritardi e riprogrammazione'];
            $azioni[] = ['priorita' => 2, 'azione' => 'Verificare disponibilità risorse (manodopera, mezzi, materiali) e potenziare se necessario'];
        }
        if ($tasksRitN > 0) {
            $azioni[] = ['priorita' => 1, 'azione' => 'Analizzare le ' . $tasksRitN . ' attività in ritardo e identificare il percorso critico aggiornato'];
            $azioni[] = ['priorita' => 2, 'azione' => 'Valutare possibilità di lavorazioni in parallelo o straordinari per recuperare ritardo'];
        }
        if (!empty($scadenzeCrit)) {
            $azioni[] = ['priorita' => 0, 'azione' => 'URGENTE: Gestire immediatamente le ' . count($scadenzeCrit) . ' pm_scadenze critiche entro 7 giorni'];
        }
        if ($ritardo > 30) {
            $azioni[] = ['priorita' => 2, 'azione' => 'Valutare la necessità di una proroga contrattuale secondo art. 120 D.Lgs. 36/2023'];
            $azioni[] = ['priorita' => 3, 'azione' => 'Predisporre documentazione per giustificazione ritardo (cause di forza maggiore, pm_varianti, ecc.)'];
        }

        foreach ($azioni as $a) {
            $report[] = "\n" . str_repeat('#', 4 - min($a['priorita'], 2)) . " " . $a['azione'];
        }
    } else {
        $report[] = "\n\n## Valutazione\nIl progetto procede **regolarmente**. Continuare il monitoraggio settimanale.";
    }

    // Previsione fine lavori
    if ($avanzamento > 0 && $avanzamento < 100) {
        $dataInizio = $commessa['data_inizio_effettiva'] ?? $commessa['data_inizio_prevista'];
        if ($dataInizio) {
            $giorniTotali   = (time() - strtotime($dataInizio)) / 86400;
            $velocita       = $avanzamento / max(1, $giorniTotali);  // % per giorno
            $rimanente      = 100 - $avanzamento;
            $giorniRimanenti = $velocita > 0 ? (int)($rimanente / $velocita) : 0;
            $dataStimata    = date('d/m/Y', strtotime("+{$giorniRimanenti} days"));

            $report[] = "\n\n## Previsione Completamento\n";
            $report[] = "Con l'attuale velocità di avanzamento ({$avanzamento}% in {$giorniTotali} giorni), " .
                        "il completamento è stimato per il **{$dataStimata}** " .
                        "(circa {$giorniRimanenti} giorni rimanenti).";
        }
    }

    return [
        'tipo'    => 'rule_based',
        'testo'   => implode('', $report),
        'livello' => $livello,
        'rischi'  => $rischi,
        'azioni'  => $azioni,
    ];
}

// =============================================================================
// CALCOLO INDICI
// =============================================================================

function calcolaSPI(array $commessa, array $pm_tasks): array
{
    // SPI = EV / PV (Earned Value / Planned Value)
    $avanzamento = (float)$commessa['percentuale_avanzamento'];
    $importo     = (float)$commessa['importo_contrattuale'];
    $dataInizio  = $commessa['data_inizio_prevista'] ?? $commessa['data_inizio_effettiva'];
    $dataFine    = $commessa['data_fine_prevista'];

    $ev = ($avanzamento / 100) * $importo; // Earned Value

    // Planned Value: % pianificata al giorno corrente
    $pv = $importo; // default
    if ($dataInizio && $dataFine) {
        $totGiorni  = max(1, (strtotime($dataFine) - strtotime($dataInizio)) / 86400);
        $giorni     = max(0, (time() - strtotime($dataInizio)) / 86400);
        $percPiano  = min(100, ($giorni / $totGiorni) * 100);
        $pv = ($percPiano / 100) * $importo;
    }

    $spi = $pv > 0 ? round($ev / $pv, 4) : 1.0;

    // CPI (Cost Performance Index) - semplificato senza actual cost
    $ultimoCumulato = Database::fetchValue(
        'SELECT COALESCE(MAX(importo_cumulato), 0) FROM pm_sal WHERE commessa_id = :id',
        [':id' => $commessa['id']]
    );
    $ac  = (float)$ultimoCumulato;
    $cpi = $ac > 0 ? round($ev / $ac, 4) : 1.0;

    return ['spi' => $spi, 'cpi' => $cpi, 'ev' => $ev, 'pv' => $pv, 'ac' => $ac];
}
