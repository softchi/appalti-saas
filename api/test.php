<?php
/**
 * Diagnostica temporanea — DA ELIMINARE dopo il debug
 * Accedi a: https://softchi.altervista.org/1_GestPM/v5/appalti-saas-main/api/test.php
 */

// Step 1: PHP funziona
header('Content-Type: application/json; charset=UTF-8');

$result = ['php' => 'ok', 'version' => PHP_VERSION, 'steps' => []];

// Step 2: bootstrap caricabile?
define('APP_INIT', true);
try {
    require_once __DIR__ . '/../php/bootstrap.php';
    $result['steps'][] = 'bootstrap: ok';
} catch (Throwable $e) {
    $result['steps'][] = 'bootstrap: ERRORE - ' . $e->getMessage();
    echo json_encode($result);
    exit;
}

// Step 3: APP_URL corretto?
$result['APP_URL']       = APP_URL;
$result['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] ?? 'n/a';

// Step 4: DB connettibile?
try {
    Database::getInstance();
    $result['steps'][] = 'db_connect: ok';
} catch (Throwable $e) {
    $result['steps'][] = 'db_connect: ERRORE - ' . $e->getMessage();
}

// Step 5: local_config.php esiste?
$result['local_config'] = file_exists(__DIR__ . '/../php/local_config.php') ? 'trovato' : 'MANCANTE';

// Step 6: DB_USER usato
$result['DB_USER'] = defined('DB_USER') ? DB_USER : 'non definito';
$result['DB_NAME'] = defined('DB_NAME') ? DB_NAME : 'non definito';
$result['DB_HOST'] = defined('DB_HOST') ? DB_HOST : 'non definito';

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
