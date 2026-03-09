<?php
/**
 * APPALTI PUBBLICI SAAS - Bootstrap
 *
 * Punto di ingresso per ogni richiesta PHP.
 * Carica config, helpers, DB e Auth.
 * Include questo file in ogni script PHP/API.
 *
 * Uso:
 *   define('APP_INIT', true);
 *   require_once __DIR__ . '/../php/bootstrap.php';
 *
 * @version 1.0.0
 */

if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Accesso negato');
}

// Caricamento configurazione
require_once __DIR__ . '/config.php';

// Autoloader semplice per classi nella cartella /php
spl_autoload_register(function (string $class) {
    $map = [
        'Database'  => __DIR__ . '/db.php',
        'Auth'      => __DIR__ . '/auth.php',
        'Logger'    => __DIR__ . '/functions.php',
        'Validator' => __DIR__ . '/functions.php',
    ];
    if (isset($map[$class]) && file_exists($map[$class])) {
        require_once $map[$class];
    }
});

// Carica funzioni globali
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// Gestione eccezioni globali
set_exception_handler(function (Throwable $e) {
    Logger::error('Uncaught exception: ' . $e->getMessage(), [
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        'trace' => APP_DEBUG ? $e->getTraceAsString() : '***',
    ]);

    // Rileva richiesta API: SCRIPT_NAME contiene /api/ (funziona indipendentemente
    // dal basepath e dal fatto che X-Requested-With venga rimosso dal proxy)
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '';
    $isApiReq   = isAjax() || strpos($scriptName, '/api/') !== false;
    if ($isApiReq) {
        jsonError(
            APP_DEBUG ? $e->getMessage() : 'Errore interno del server',
            500
        );
    }

    http_response_code(500);
    if (APP_DEBUG) {
        echo '<pre>' . e($e->getMessage()) . "\n" . e($e->getTraceAsString()) . '</pre>';
    } else {
        echo 'Si è verificato un errore. Riprovare più tardi.';
    }
    exit;
});

// Gestione errori PHP come eccezioni
set_error_handler(function (int $severity, string $message, string $file, int $line) {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Inizializza sessione
Auth::initSession();
