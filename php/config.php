<?php
/**
 * APPALTI PUBBLICI SAAS - Configurazione Principale
 *
 * IMPORTANTE: Rinominare questo file in config.php e NON committarlo
 * su repository pubblici. Contenere le credenziali in variabili
 * d'ambiente in produzione.
 *
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Accesso negato');
}

// =============================================================================
// AMBIENTE
// =============================================================================
define('APP_ENV', getenv('APP_ENV') ?: 'production'); // development | production
define('APP_DEBUG', APP_ENV === 'development');
define('APP_VERSION', '1.0.0');
define('APP_NAME', 'Appalti Pubblici SaaS');
define('APP_TAGLINE', 'Gestione Commesse Pubbliche - D.Lgs. 36/2023');

// =============================================================================
// URL E PERCORSI
// =============================================================================
// Rilevamento automatico del base URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script   = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$basePath = rtrim($script, '/\\');

define('APP_URL', $protocol . '://' . $host . $basePath);
define('BASE_PATH', dirname(__DIR__)); // root del progetto
define('PHP_PATH', BASE_PATH . '/php');
define('API_PATH', BASE_PATH . '/api');
define('COMPONENTS_PATH', BASE_PATH . '/components');
define('UPLOADS_PATH', BASE_PATH . '/uploads');
define('UPLOADS_URL', APP_URL . '/uploads');

// =============================================================================
// DATABASE
// =============================================================================
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
define('DB_NAME', getenv('DB_NAME') ?: 'appalti_saas');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// Opzioni PDO
define('DB_OPTIONS', [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_PERSISTENT         => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
]);

// =============================================================================
// SESSIONI
// =============================================================================
define('SESSION_NAME', 'appalti_sid');
define('SESSION_LIFETIME', 28800);   // 8 ore in secondi
define('SESSION_SECURE', $protocol === 'https');
define('SESSION_HTTP_ONLY', true);
define('SESSION_SAME_SITE', 'Strict');
define('CSRF_TOKEN_LENGTH', 32);
define('REMEMBER_ME_DAYS', 30);

// =============================================================================
// SICUREZZA
// =============================================================================
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_COST', 12);           // bcrypt cost factor
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900);       // 15 minuti in secondi
define('TOKEN_RESET_EXPIRY', 3600);    // 1 ora

// Headers sicurezza
define('SECURITY_HEADERS', [
    'X-Content-Type-Options'    => 'nosniff',
    'X-Frame-Options'           => 'SAMEORIGIN',
    'X-XSS-Protection'          => '1; mode=block',
    'Referrer-Policy'           => 'strict-origin-when-cross-origin',
    'Permissions-Policy'        => 'geolocation=(), microphone=(), camera=()',
    'Content-Security-Policy'   =>
        "default-src 'self'; " .
        "script-src 'self' 'unsafe-inline' cdn.jsdelivr.net cdnjs.cloudflare.com; " .
        "style-src 'self' 'unsafe-inline' cdn.jsdelivr.net cdnjs.cloudflare.com fonts.googleapis.com; " .
        "font-src 'self' fonts.gstatic.com cdn.jsdelivr.net; " .
        "img-src 'self' data: blob:; " .
        "connect-src 'self'; " .
        "frame-ancestors 'self';",
]);

// =============================================================================
// UPLOAD FILE
// =============================================================================
define('UPLOAD_MAX_SIZE', 52428800);   // 50 MB in bytes
define('UPLOAD_ALLOWED_TYPES', [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'image/svg+xml',
    'text/plain',
    'text/csv',
    'application/zip',
    'application/x-rar-compressed',
    'application/x-zip-compressed',
    'application/dwg', // AutoCAD
    'application/dxf',
]);
define('UPLOAD_ALLOWED_EXT', [
    'pdf','doc','docx','xls','xlsx','ppt','pptx',
    'jpg','jpeg','png','gif','webp','svg',
    'txt','csv','zip','rar','7z','dwg','dxf','ifc',
]);

// =============================================================================
// PAGINAZIONE
// =============================================================================
define('ITEMS_PER_PAGE', 20);
define('MAX_ITEMS_PER_PAGE', 100);

// =============================================================================
// EMAIL (SMTP)
// =============================================================================
define('MAIL_ENABLED', false);         // Attivare in produzione
define('MAIL_HOST', getenv('MAIL_HOST') ?: 'smtp.example.com');
define('MAIL_PORT', (int)(getenv('MAIL_PORT') ?: 587));
define('MAIL_USER', getenv('MAIL_USER') ?: '');
define('MAIL_PASS', getenv('MAIL_PASS') ?: '');
define('MAIL_FROM', getenv('MAIL_FROM') ?: 'noreply@appalti.local');
define('MAIL_FROM_NAME', APP_NAME);
define('MAIL_ENCRYPTION', 'tls');      // tls | ssl | none

// =============================================================================
// AI ASSISTANT (Anthropic Claude API)
// =============================================================================
define('AI_ENABLED', !empty(getenv('ANTHROPIC_API_KEY')));
define('AI_API_KEY', getenv('ANTHROPIC_API_KEY') ?: '');
define('AI_MODEL', 'claude-sonnet-4-6');
define('AI_MAX_TOKENS', 2048);
define('AI_ENDPOINT', 'https://api.anthropic.com/v1/messages');

// =============================================================================
// LOGGING
// =============================================================================
define('LOG_PATH', BASE_PATH . '/logs');
define('LOG_LEVEL', APP_DEBUG ? 'DEBUG' : 'ERROR'); // DEBUG|INFO|WARNING|ERROR
define('LOG_MAX_SIZE', 10485760); // 10 MB

// =============================================================================
// TIMEZONE E LOCALE
// =============================================================================
date_default_timezone_set('Europe/Rome');
setlocale(LC_TIME, 'it_IT.UTF-8', 'it_IT', 'Italian');
define('DATE_FORMAT_IT', 'd/m/Y');
define('DATETIME_FORMAT_IT', 'd/m/Y H:i');
define('CURRENCY', 'EUR');
define('LOCALE', 'it_IT');

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
