<?php
/**
 * APPALTI PUBBLICI SAAS - Configurazione Principale
 * @version 1.0.1 (FIXED per Altervista)
 */
if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Accesso negato');
}
// =============================================================================
// AMBIENTE
// =============================================================================
define('APP_ENV',      getenv('APP_ENV') ?: 'production');
define('APP_DEBUG',    APP_ENV === 'development');
define('APP_VERSION',  '1.0.0');
define('APP_NAME',     'Appalti Pubblici SaaS');
define('APP_TAGLINE',  'Gestione Commesse Pubbliche - D.Lgs. 36/2023');
// =============================================================================
// URL E PERCORSI
// =============================================================================
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script   = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$basePath = rtrim($script, '/\\');
define('APP_URL',         $protocol . '://' . $host . $basePath);
define('BASE_PATH',       dirname(__DIR__));
define('PHP_PATH',        BASE_PATH . '/php');
define('API_PATH',        BASE_PATH . '/api');
define('COMPONENTS_PATH', BASE_PATH . '/components');
define('UPLOADS_PATH',    BASE_PATH . '/uploads');
define('UPLOADS_URL',     APP_URL . '/uploads');
// =============================================================================
// CREDENZIALI LOCALI (file da creare manualmente su Altervista, NON in git)
// Crea php/local_config.php con:
//   <?php
//   define('DB_USER_LOCAL', 'tuo_username_altervista');
//   define('DB_PASS_LOCAL', 'tua_password_db');
// =============================================================================
$_localCfg = __DIR__ . '/local_config.php';
if (file_exists($_localCfg)) {
    require_once $_localCfg;
}
unset($_localCfg);
// =============================================================================
// DATABASE
// FIX: rimosso define('DB_OPTIONS', [...]) con costanti PDO dentro define()
//      causa errore 500 su Altervista. Le opzioni PDO ora sono in db.php
//      come array normale al momento della connessione.
// =============================================================================
define('DB_HOST',    (defined('DB_HOST_LOCAL') ? DB_HOST_LOCAL : null) ?? getenv('DB_HOST') ?: 'localhost');
define('DB_PORT',    (int)((defined('DB_PORT_LOCAL') ? DB_PORT_LOCAL : null) ?? getenv('DB_PORT') ?: 3306));
define('DB_NAME',    (defined('DB_NAME_LOCAL') ? DB_NAME_LOCAL : null) ?? getenv('DB_NAME') ?: 'my_softchi');
define('DB_USER',    (defined('DB_USER_LOCAL') ? DB_USER_LOCAL : null) ?? getenv('DB_USER') ?: 'softchi');
define('DB_PASS',    (defined('DB_PASS_LOCAL') ? DB_PASS_LOCAL : null) ?? getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');
// NOTA: le DB_OPTIONS sono definite direttamente in db.php
// =============================================================================
// SESSIONI
// =============================================================================
define('SESSION_NAME',       'appalti_sid');
define('SESSION_LIFETIME',   28800);
define('SESSION_SECURE',     $protocol === 'https');
define('SESSION_HTTP_ONLY',  true);
define('SESSION_SAME_SITE',  'Strict');
define('CSRF_TOKEN_LENGTH',  32);
define('REMEMBER_ME_DAYS',   30);
// =============================================================================
// SICUREZZA
// =============================================================================
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_COST',       12);
define('MAX_LOGIN_ATTEMPTS',  5);
define('LOCKOUT_DURATION',    900);
define('TOKEN_RESET_EXPIRY',  3600);
// FIX: rimosso define('SECURITY_HEADERS', [...]) con array
//      Le header vengono impostate direttamente in bootstrap.php
define('CSP_POLICY',
    "default-src 'self'; " .
    "script-src 'self' 'unsafe-inline' cdn.jsdelivr.net cdnjs.cloudflare.com; " .
    "style-src 'self' 'unsafe-inline' cdn.jsdelivr.net cdnjs.cloudflare.com fonts.googleapis.com; " .
    "font-src 'self' fonts.gstatic.com cdn.jsdelivr.net; " .
    "img-src 'self' data: blob:; " .
    "connect-src 'self'; " .
    "frame-ancestors 'self';"
);
// =============================================================================
// UPLOAD FILE
// FIX: rimosso define('UPLOAD_ALLOWED_TYPES', [...]) con array
//      Gli array sono usati come variabili globali invece di costanti
// =============================================================================
define('UPLOAD_MAX_SIZE', 52428800);
$GLOBALS['UPLOAD_ALLOWED_TYPES'] = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
    'text/plain', 'text/csv',
    'application/zip', 'application/x-rar-compressed', 'application/x-zip-compressed',
    'application/dwg', 'application/dxf',
];
$GLOBALS['UPLOAD_ALLOWED_EXT'] = [
    'pdf','doc','docx','xls','xlsx','ppt','pptx',
    'jpg','jpeg','png','gif','webp','svg',
    'txt','csv','zip','rar','7z','dwg','dxf','ifc',
];
// =============================================================================
// PAGINAZIONE
// =============================================================================
define('ITEMS_PER_PAGE',     20);
define('MAX_ITEMS_PER_PAGE', 100);
// =============================================================================
// EMAIL (SMTP)
// =============================================================================
define('MAIL_ENABLED',    false);
define('MAIL_HOST',       getenv('MAIL_HOST') ?: 'smtp.example.com');
define('MAIL_PORT',       (int)(getenv('MAIL_PORT') ?: 587));
define('MAIL_USER',       getenv('MAIL_USER') ?: '');
define('MAIL_PASS',       getenv('MAIL_PASS') ?: '');
define('MAIL_FROM',       getenv('MAIL_FROM') ?: 'noreply@appalti.local');
define('MAIL_FROM_NAME',  APP_NAME);
define('MAIL_ENCRYPTION', 'tls');
// =============================================================================
// AI ASSISTANT (Anthropic Claude API)
// =============================================================================
define('AI_ENABLED',   !empty(getenv('ANTHROPIC_API_KEY')));
define('AI_API_KEY',   getenv('ANTHROPIC_API_KEY') ?: '');
define('AI_MODEL',     'claude-sonnet-4-6');
define('AI_MAX_TOKENS', 2048);
define('AI_ENDPOINT',  'https://api.anthropic.com/v1/messages');
// =============================================================================
// LOGGING
// =============================================================================
define('LOG_PATH',     BASE_PATH . '/logs');
define('LOG_LEVEL',    APP_DEBUG ? 'DEBUG' : 'ERROR');
define('LOG_MAX_SIZE', 10485760);
// =============================================================================
// TIMEZONE E LOCALE
// FIX: rimosso setlocale() con it_IT.UTF-8 - causa errore su Altervista
//      perché il locale italiano non è installato sul server
// =============================================================================
date_default_timezone_set('Europe/Rome');
define('DATE_FORMAT_IT',     'd/m/Y');
define('DATETIME_FORMAT_IT', 'd/m/Y H:i');
define('CURRENCY', 'EUR');
define('LOCALE',   'it_IT');
// =============================================================================
// ERROR HANDLING
// =============================================================================
if (APP_DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}
