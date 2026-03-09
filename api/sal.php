<?php
/**
 * API REST: SAL - Stato Avanzamento Lavori
 *
 * GET    /api/sal.php?commessa_id=N   - Lista SAL commessa
 * GET    /api/sal.php?id=N            - Dettaglio SAL
 * POST   /api/sal.php                 - Crea SAL
 * PUT    /api/sal.php?id=N            - Aggiorna/approva SAL
 * POST   /api/sal.php?action=approve  - Approva SAL (RUP/DL)
 *
 * @version 1.0.0
 */
define('APP_INIT', true);
require_once __DIR__ . '/../php/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
if (!Auth::check()) jsonError('Non autenticato', 401);
if (!Auth::can('sal.read')) jsonError('Permesso negato', 403);

$method     = strtoupper($_SERVER['REQUEST_METHOD']);
$id         = sanitizeInt($_GET['id'] ?? null, 1);
$commessaId = sanitizeInt($_GET['commessa_id'] ?? null, 1);
$action     = sanitizeString($_GET['action'] ?? '');

switch ($method) {
    case 'GET':
        $id ? getSal($id) : listSal($commessaId ?? 0);
        break;

    case 'POST':
        Auth::requireCsrf();
        if ($action === 'approve') {
            if (!Auth::can('sal.approve')) jsonError('Permesso negato', 403);
            approveSal($id ?? 0);
        } else {
            if (!Auth::can('sal.create')) jsonError('Permesso negato', 403);
            createSal();
        }
        break;

    case 'PUT':
        if (!$id) jsonError('ID SAL richiesto', 400);
        if (!Auth::can('sal.update')) jsonError('Permesso negato', 403);
        Auth::requireCsrf();
        updateSal($id);
        break;

    default:
        jsonError('Metodo non supportato', 405);
}

// =============================================================================

function listSal(int $commessaId): never
{
    if (!$commessaId) jsonError('commessa_id richiesto', 400);

    $sal = Database::fetchAll(
        'SELECT s.*,
                CONCAT(udl.cognome, " ", udl.nome) AS dl_nome,
                CONCAT(urup.cognome, " ", urup.nome) AS rup_nome
         FROM sal s
         LEFT JOIN utenti udl ON udl.id = s.dl_id
         LEFT JOIN utenti urup ON urup.id = s.rup_id
         WHERE s.commessa_id = :cid
         ORDER BY s.numero_sal DESC',
        [':cid' => $commessaId]
    );

    // Recupera importo contrattuale per calcolo residuo
    $commessa = Database::fetchOne(
        'SELECT importo_contrattuale, importo_sicurezza, importo_varianti FROM commesse WHERE id = :id',
        [':id' => $commessaId]
    );

    $importoTotale = ($commessa['importo_contrattuale'] ?? 0)
                   + ($commessa['importo_sicurezza'] ?? 0)
                   + ($commessa['importo_varianti'] ?? 0);

    // Ultimo importo cumulato
    $ultimoCumulato = 0;
    foreach ($sal as $s) {
        if (in_array($s['stato'], ['APPROVATO', 'PAGATO'])) {
            $ultimoCumulato = max($ultimoCumulato, (float)$s['importo_cumulato']);
        }
    }

    // Formatta SAL
    $sal = array_map(function($s) {
        $s['importo_totale_fmt']   = formatEuro((float)$s['importo_totale']);
        $s['importo_cumulato_fmt'] = formatEuro((float)$s['importo_cumulato']);
        $s['importo_netto_fmt']    = formatEuro((float)$s['importo_netto']);
        $s['data_inizio_it']       = formatDate($s['data_inizio']);
        $s['data_fine_it']         = formatDate($s['data_fine']);
        $s['data_emissione_it']    = formatDate($s['data_emissione']);
        return $s;
    }, $sal);

    jsonResponse([
        'sal'             => $sal,
        'importo_totale'  => $importoTotale,
        'importo_totale_fmt' => formatEuro($importoTotale),
        'importo_liquidato' => $ultimoCumulato,
        'importo_residuo'   => $importoTotale - $ultimoCumulato,
        'importo_liquidato_fmt' => formatEuro($ultimoCumulato),
        'importo_residuo_fmt'   => formatEuro($importoTotale - $ultimoCumulato),
        'percentuale_liquidata' => $importoTotale > 0
            ? round(($ultimoCumulato / $importoTotale) * 100, 2) : 0,
    ]);
}

function getSal(int $id): never
{
    $sal = Database::fetchOne(
        'SELECT s.*,
                CONCAT(udl.cognome, " ", udl.nome) AS dl_nome,
                CONCAT(urup.cognome, " ", urup.nome) AS rup_nome,
                c.codice_commessa, c.oggetto AS commessa_oggetto,
                c.importo_contrattuale
         FROM sal s
         LEFT JOIN utenti udl ON udl.id = s.dl_id
         LEFT JOIN utenti urup ON urup.id = s.rup_id
         JOIN commesse c ON c.id = s.commessa_id
         WHERE s.id = :id',
        [':id' => $id]
    );

    if (!$sal) jsonError('SAL non trovato', 404);

    // Voci SAL
    $voci = Database::fetchAll(
        'SELECT sv.*,
                cl.codice, cl.descrizione, cl.unita_misura, cl.prezzo_unitario,
                cl.quantita_contrattuale,
                sv.quantita_periodo * cl.prezzo_unitario AS importo_periodo_calc
         FROM sal_voci sv
         JOIN categorie_lavoro cl ON cl.id = sv.categoria_id
         WHERE sv.sal_id = :sid
         ORDER BY cl.ordine, cl.codice',
        [':sid' => $id]
    );

    // Formatta
    $sal['data_inizio_it']      = formatDate($sal['data_inizio']);
    $sal['data_fine_it']        = formatDate($sal['data_fine']);
    $sal['importo_totale_fmt']  = formatEuro((float)$sal['importo_totale']);
    $sal['importo_netto_fmt']   = formatEuro((float)$sal['importo_netto']);
    $sal['importo_cumulato_fmt'] = formatEuro((float)$sal['importo_cumulato']);

    jsonResponse(['sal' => $sal, 'voci' => $voci]);
}

function createSal(): never
{
    $body = !empty($_POST) ? $_POST : getJsonBody();

    $v = new Validator($body);
    $v->required('commessa_id', 'Commessa')
      ->required('data_inizio', 'Data inizio periodo')
      ->required('data_fine', 'Data fine periodo')
      ->date('data_inizio', 'Data inizio')
      ->date('data_fine', 'Data fine')
      ->orFail();

    $commessaId = (int)$body['commessa_id'];

    // Verifica commessa esiste
    $commessa = Database::fetchOne(
        'SELECT id, importo_contrattuale, importo_sicurezza, importo_varianti, stato
         FROM commesse WHERE id = :id',
        [':id' => $commessaId]
    );
    if (!$commessa) jsonError('Commessa non trovata', 404);
    if (!in_array($commessa['stato'], ['IN_ESECUZIONE', 'PIANIFICAZIONE'])) {
        jsonError('La commessa non è in stato esecuzione', 422);
    }

    // Numero SAL progressivo
    $ultimoNumero = (int)Database::fetchValue(
        'SELECT COALESCE(MAX(numero_sal), 0) FROM sal WHERE commessa_id = :cid',
        [':cid' => $commessaId]
    );
    $numeroSal = $ultimoNumero + 1;

    // Calcola importo cumulato precedente
    $cumulatoPrecedente = (float)(Database::fetchValue(
        'SELECT COALESCE(MAX(importo_cumulato), 0) FROM sal
         WHERE commessa_id = :cid AND stato IN ("APPROVATO", "PAGATO")',
        [':cid' => $commessaId]
    ) ?? 0);

    $importoLavori    = round((float)($body['importo_lavori'] ?? 0), 2);
    $importoSicurezza = round((float)($body['importo_sicurezza'] ?? 0), 2);
    $importoVarianti  = round((float)($body['importo_varianti'] ?? 0), 2);
    $importoTotale    = $importoLavori + $importoSicurezza + $importoVarianti;
    $importoCumulato  = $cumulatoPrecedente + $importoTotale;
    $ritenuta         = round($importoTotale * 0.05, 2); // 5% ritenuta contrattuale

    // Percentuale avanzamento rispetto al totale contrattuale
    $totaleContrattuale = (float)$commessa['importo_contrattuale']
                        + (float)$commessa['importo_sicurezza']
                        + (float)$commessa['importo_varianti'];
    $percAvanzamento = $totaleContrattuale > 0
        ? round(($importoCumulato / $totaleContrattuale) * 100, 2) : 0;

    Database::beginTransaction();
    try {
        $salId = Database::insert('sal', [
            'uuid'                    => generateUUID(),
            'commessa_id'             => $commessaId,
            'numero_sal'              => $numeroSal,
            'data_inizio'             => sanitizeDate($body['data_inizio']),
            'data_fine'               => sanitizeDate($body['data_fine']),
            'data_emissione'          => sanitizeDate($body['data_emissione'] ?? '') ?: date('Y-m-d'),
            'importo_lavori'          => $importoLavori,
            'importo_sicurezza'       => $importoSicurezza,
            'importo_varianti'        => $importoVarianti,
            'importo_cumulato'        => $importoCumulato,
            'ritenuta_garanzia'       => $ritenuta,
            'percentuale_avanzamento' => $percAvanzamento,
            'stato'                   => 'BOZZA',
            'dl_id'                   => Auth::id(),
            'note_dl'                 => sanitizeString($body['note_dl'] ?? '', 65535),
            'created_by'              => Auth::id(),
        ]);

        // Inserisci voci SAL se fornite
        if (!empty($body['voci']) && is_array($body['voci'])) {
            foreach ($body['voci'] as $voce) {
                $catId = sanitizeInt($voce['categoria_id'] ?? null, 1);
                if (!$catId) continue;

                Database::insert('sal_voci', [
                    'sal_id'             => $salId,
                    'categoria_id'       => $catId,
                    'quantita_periodo'   => round((float)($voce['quantita_periodo'] ?? 0), 4),
                    'quantita_cumulata'  => round((float)($voce['quantita_cumulata'] ?? 0), 4),
                    'note'               => sanitizeString($voce['note'] ?? '', 1000),
                ]);
            }
        }

        Database::commit();

        Logger::audit('CREATE', 'sal', $salId, null, ['numero_sal' => $numeroSal, 'commessa_id' => $commessaId]);

        // Notifica RUP per approvazione
        $commessaData = Database::fetchOne('SELECT rup_id, oggetto FROM commesse WHERE id = :id', [':id' => $commessaId]);
        if ($commessaData['rup_id']) {
            createNotification(
                (int)$commessaData['rup_id'],
                'SAL',
                "SAL N.{$numeroSal} in attesa di approvazione",
                "È stato emesso il SAL N.{$numeroSal} per la commessa: {$commessaData['oggetto']}",
                "/pages/sal-detail.php?id={$salId}",
                'sal', $salId
            );
        }

        jsonSuccess("SAL N.{$numeroSal} creato con successo", [
            'id'        => $salId,
            'numero_sal' => $numeroSal,
        ], 201);

    } catch (Throwable $e) {
        Database::rollback();
        Logger::error('Errore creazione SAL: ' . $e->getMessage());
        jsonError('Errore durante la creazione del SAL', 500);
    }
}

function updateSal(int $id): never
{
    $existing = Database::fetchOne('SELECT * FROM sal WHERE id = :id', [':id' => $id]);
    if (!$existing) jsonError('SAL non trovato', 404);

    if (in_array($existing['stato'], ['APPROVATO', 'PAGATO'])) {
        jsonError('Impossibile modificare un SAL già approvato o pagato', 409);
    }

    $body = !empty($_POST) ? $_POST : getJsonBody();

    $updateData = [];
    $allowed = [
        'data_inizio', 'data_fine', 'data_emissione',
        'importo_lavori', 'importo_sicurezza', 'importo_varianti',
        'note_dl', 'stato'
    ];

    foreach ($allowed as $field) {
        if (!array_key_exists($field, $body)) continue;
        $updateData[$field] = match(true) {
            in_array($field, ['importo_lavori','importo_sicurezza','importo_varianti'])
                => round((float)$body[$field], 2),
            in_array($field, ['data_inizio','data_fine','data_emissione'])
                => sanitizeDate((string)$body[$field]),
            $field === 'stato'
                => in_array($body[$field], ['BOZZA','EMESSO','APPROVATO','PAGATO','CONTESTATO'])
                   ? $body[$field] : $existing['stato'],
            default => sanitizeString((string)$body[$field], 65535),
        };
    }

    // Ricalcola importo cumulato se cambiano gli importi
    if (isset($updateData['importo_lavori']) || isset($updateData['importo_sicurezza']) || isset($updateData['importo_varianti'])) {
        $lavori   = (float)($updateData['importo_lavori']    ?? $existing['importo_lavori']);
        $sic      = (float)($updateData['importo_sicurezza'] ?? $existing['importo_sicurezza']);
        $var      = (float)($updateData['importo_varianti']  ?? $existing['importo_varianti']);
        $tot      = $lavori + $sic + $var;
        $cumulPrec = (float)(Database::fetchValue(
            'SELECT COALESCE(MAX(importo_cumulato), 0) FROM sal
             WHERE commessa_id = :cid AND numero_sal < :n AND stato IN ("APPROVATO","PAGATO")',
            [':cid' => $existing['commessa_id'], ':n' => $existing['numero_sal']]
        ) ?? 0);

        $updateData['importo_cumulato']    = $cumulPrec + $tot;
        $updateData['ritenuta_garanzia']   = round($tot * 0.05, 2);
    }

    Database::update('sal', $updateData, ['id' => $id]);
    Logger::audit('UPDATE', 'sal', $id, $existing, $updateData);
    jsonSuccess('SAL aggiornato con successo');
}

function approveSal(int $id): never
{
    if (!$id) jsonError('ID SAL richiesto', 400);

    $sal = Database::fetchOne('SELECT * FROM sal WHERE id = :id', [':id' => $id]);
    if (!$sal) jsonError('SAL non trovato', 404);
    if ($sal['stato'] === 'APPROVATO') jsonError('SAL già approvato', 409);
    if ($sal['stato'] === 'PAGATO') jsonError('SAL già pagato', 409);

    $body = !empty($_POST) ? $_POST : getJsonBody();
    $noteRup = sanitizeString($body['note_rup'] ?? '', 65535);

    Database::update('sal', [
        'stato'              => 'APPROVATO',
        'rup_id'             => Auth::id(),
        'note_rup'           => $noteRup,
        'data_approvazione'  => date('Y-m-d'),
    ], ['id' => $id]);

    // Notifica DL che ha emesso il SAL
    if ($sal['dl_id'] && $sal['dl_id'] != Auth::id()) {
        createNotification(
            (int)$sal['dl_id'],
            'SAL',
            "SAL N.{$sal['numero_sal']} approvato",
            "Il SAL N.{$sal['numero_sal']} è stato approvato dal RUP",
            "/pages/sal-detail.php?id={$id}",
            'sal', $id
        );
    }

    Logger::audit('APPROVE', 'sal', $id, $sal, ['stato' => 'APPROVATO']);
    jsonSuccess("SAL N.{$sal['numero_sal']} approvato con successo");
}
