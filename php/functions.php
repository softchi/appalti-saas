<?php
/**
 * APPALTI PUBBLICI SAAS - Funzioni Helper Globali
 * @version 1.0.1 (FIXED per Altervista)
 *
 * FIX:
 *   1. Rimosso foreach (SECURITY_HEADERS ...) - costante array rimossa da config.php
 *      Le header vengono impostate direttamente qui sotto
 *   2. Rimosso tipo "mixed" da firme funzioni (PHP 8.0+, per sicurezza compatibilità)
 *   3. Rimosso tipo "never" da redirect/jsonResponse (PHP 8.1+)
 *   4. UPLOAD_ALLOWED_TYPES/EXT ora usano $GLOBALS invece di costanti array
 */
if (!defined('APP_INIT')) { exit('Accesso negato'); }
// =============================================================================
// SICUREZZA - INPUT/OUTPUT
// =============================================================================
function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
function sanitizeString(string $value, int $maxLen = 65535): string
{
    $value = strip_tags(trim($value));
    return mb_substr($value, 0, $maxLen, 'UTF-8');
}
function sanitizeInt($value, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): ?int
{
    $int = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => $min, 'max_range' => $max]
    ]);
    return $int !== false ? (int) $int : null;
}
function sanitizeFloat($value): ?float
{
    $float = filter_var($value, FILTER_VALIDATE_FLOAT, ['flags' => FILTER_FLAG_ALLOW_FRACTION]);
    return $float !== false ? (float) $float : null;
}
function sanitizeEmail(string $email): ?string
{
    $clean = filter_var(strtolower(trim($email)), FILTER_SANITIZE_EMAIL);
    return filter_var($clean, FILTER_VALIDATE_EMAIL) ? $clean : null;
}
function sanitizeDate(string $date): ?string
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    if ($d && $d->format('Y-m-d') === $date) return $date;
    $d = DateTime::createFromFormat('d/m/Y', $date);
    return $d ? $d->format('Y-m-d') : null;
}
function post(string $key, $default = null, string $type = 'string')
{
    if (!isset($_POST[$key])) return $default;
    $val = $_POST[$key];
    return match($type) {
        'int'   => sanitizeInt($val) ?? $default,
        'float' => sanitizeFloat($val) ?? $default,
        'bool'  => (bool) filter_var($val, FILTER_VALIDATE_BOOLEAN),
        'email' => sanitizeEmail((string)$val) ?? $default,
        'date'  => sanitizeDate((string)$val),
        'raw'   => $val,
        default => sanitizeString((string)$val),
    };
}
function get(string $key, $default = null, string $type = 'string')
{
    if (!isset($_GET[$key])) return $default;
    $val = $_GET[$key];
    return match($type) {
        'int'   => sanitizeInt($val) ?? $default,
        'float' => sanitizeFloat($val) ?? $default,
        'bool'  => (bool) filter_var($val, FILTER_VALIDATE_BOOLEAN),
        default => sanitizeString((string)$val),
    };
}
// =============================================================================
// RISPOSTA API (JSON)
// FIX: rimosso tipo ritorno "never" (PHP 8.1+), sostituito con void + exit
// =============================================================================
function jsonResponse($data, int $status = 200, bool $success = true): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    $response = [
        'success'    => $success,
        'timestamp'  => time(),
        'csrf_token' => Auth::csrfToken(),
    ];
    if (is_array($data)) {
        $response = array_merge($response, $data);
    } else {
        $response['data'] = $data;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function jsonError(string $message, int $status = 400, array $errors = []): void
{
    $response = ['message' => $message];
    if (!empty($errors)) $response['errors'] = $errors;
    jsonResponse($response, $status, false);
}
function jsonSuccess(string $message, array $data = [], int $status = 200): void
{
    jsonResponse(array_merge(['message' => $message], $data), $status, true);
}
// =============================================================================
// VALIDAZIONE
// =============================================================================
class Validator
{
    private array $errors = [];
    private array $data   = [];
    public function __construct(array $data)
    {
        $this->data = $data;
    }
    public function required(string $field, string $label = ''): self
    {
        $label = $label ?: $field;
        if (empty($this->data[$field]) && $this->data[$field] !== '0' && $this->data[$field] !== 0) {
            $this->errors[$field] = "Il campo {$label} è obbligatorio";
        }
        return $this;
    }
    public function minLength(string $field, int $min, string $label = ''): self
    {
        $label = $label ?: $field;
        if (isset($this->data[$field]) && mb_strlen($this->data[$field]) < $min) {
            $this->errors[$field] = "{$label} deve contenere almeno {$min} caratteri";
        }
        return $this;
    }
    public function maxLength(string $field, int $max, string $label = ''): self
    {
        $label = $label ?: $field;
        if (isset($this->data[$field]) && mb_strlen($this->data[$field]) > $max) {
            $this->errors[$field] = "{$label} non può superare {$max} caratteri";
        }
        return $this;
    }
    public function email(string $field, string $label = 'Email'): self
    {
        if (!empty($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "{$label} non è valida";
        }
        return $this;
    }
    public function numeric(string $field, string $label = ''): self
    {
        $label = $label ?: $field;
        if (!empty($this->data[$field]) && !is_numeric($this->data[$field])) {
            $this->errors[$field] = "{$label} deve essere un numero";
        }
        return $this;
    }
    public function date(string $field, string $label = ''): self
    {
        $label = $label ?: $field;
        if (!empty($this->data[$field])) {
            $d = DateTime::createFromFormat('Y-m-d', $this->data[$field]);
            if (!$d || $d->format('Y-m-d') !== $this->data[$field]) {
                $this->errors[$field] = "{$label} non è una data valida";
            }
        }
        return $this;
    }
    public function inArray(string $field, array $allowed, string $label = ''): self
    {
        $label = $label ?: $field;
        if (!empty($this->data[$field]) && !in_array($this->data[$field], $allowed, true)) {
            $this->errors[$field] = "{$label} ha un valore non valido";
        }
        return $this;
    }
    public function min(string $field, float $min, string $label = ''): self
    {
        $label = $label ?: $field;
        if (isset($this->data[$field]) && is_numeric($this->data[$field]) && (float)$this->data[$field] < $min) {
            $this->errors[$field] = "{$label} deve essere almeno {$min}";
        }
        return $this;
    }
    public function unique(string $field, string $table, string $column, ?int $excludeId = null, string $label = ''): self
    {
        $label = $label ?: $field;
        if (!empty($this->data[$field])) {
            $sql    = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = :val";
            $params = [':val' => $this->data[$field]];
            if ($excludeId) {
                $sql .= ' AND id != :id';
                $params[':id'] = $excludeId;
            }
            $count = (int) Database::fetchValue($sql, $params);
            if ($count > 0) {
                $this->errors[$field] = "{$label} è già in uso";
            }
        }
        return $this;
    }
    public function fails(): bool   { return !empty($this->errors); }
    public function passes(): bool  { return empty($this->errors); }
    public function errors(): array { return $this->errors; }
    public function orFail(): void
    {
        if ($this->fails()) {
            jsonError('Errore di validazione', 422, $this->errors);
        }
    }
}
// =============================================================================
// FORMATTAZIONE
// =============================================================================
function formatEuro(float $amount, int $decimals = 2): string
{
    return '€ ' . number_format($amount, $decimals, ',', '.');
}
function formatDate(?string $date, string $format = 'd/m/Y'): string
{
    if (empty($date)) return '—';
    $d = DateTime::createFromFormat('Y-m-d', $date) ?: DateTime::createFromFormat('Y-m-d H:i:s', $date);
    return $d ? $d->format($format) : $date;
}
function formatDateTime(?string $datetime): string
{
    return formatDate($datetime, 'd/m/Y H:i');
}
function formatPercent(float $value, int $decimals = 1): string
{
    return number_format($value, $decimals, ',', '.') . '%';
}
function formatFileSize(int $bytes): string
{
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576)    return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024)       return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}
function generateUUID(): string
{
    $data    = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
function generateCodiceCommessa(): string
{
    $anno   = date('Y');
    $ultimo = Database::fetchValue(
        "SELECT MAX(CAST(SUBSTRING(codice_commessa, -4) AS UNSIGNED))
         FROM pm_commesse WHERE codice_commessa LIKE :pattern",
        [':pattern' => "CO-{$anno}-%"]
    );
    $numero = str_pad(((int)$ultimo + 1), 4, '0', STR_PAD_LEFT);
    return "CO-{$anno}-{$numero}";
}
// =============================================================================
// UPLOAD FILE
// FIX: UPLOAD_ALLOWED_TYPES e UPLOAD_ALLOWED_EXT ora letti da $GLOBALS
// =============================================================================
function uploadFile(array $file, string $subfolder = 'pm_documenti'): array
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => getUploadErrorMessage($file['error'])];
    }
    if ($file['size'] > UPLOAD_MAX_SIZE) {
        return ['success' => false, 'message' => 'File troppo grande. Massimo ' . formatFileSize(UPLOAD_MAX_SIZE)];
    }
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    // FIX: usa $GLOBALS invece della costante array UPLOAD_ALLOWED_TYPES
    $allowedTypes = $GLOBALS['UPLOAD_ALLOWED_TYPES'] ?? [];
    if (!in_array($mimeType, $allowedTypes, true)) {
        return ['success' => false, 'message' => "Tipo file non consentito ({$mimeType})"];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    // FIX: usa $GLOBALS invece della costante array UPLOAD_ALLOWED_EXT
    $allowedExt = $GLOBALS['UPLOAD_ALLOWED_EXT'] ?? [];
    if (!in_array($ext, $allowedExt, true)) {
        return ['success' => false, 'message' => "Estensione file non consentita (.{$ext})"];
    }
    $safeBasename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
    $uniqueName   = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $safeBasename . '.' . $ext;
    $subPath      = preg_replace('/[^a-zA-Z0-9_\/]/', '', $subfolder);
    $destDir      = UPLOADS_PATH . '/' . $subPath . '/' . date('Y') . '/';
    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
        file_put_contents(UPLOADS_PATH . '/.htaccess',
            "Options -Indexes\n<FilesMatch '\\.php$'>\n  Deny from all\n</FilesMatch>\n"
        );
    }
    $destPath = $destDir . $uniqueName;
    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['success' => false, 'message' => 'Errore durante il salvataggio del file'];
    }
    $md5          = md5_file($destPath);
    $relativePath = $subPath . '/' . date('Y') . '/' . $uniqueName;
    return [
        'success' => true,
        'path'    => $relativePath,
        'url'     => UPLOADS_URL . '/' . $relativePath,
        'name'    => $file['name'],
        'size'    => $file['size'],
        'mime'    => $mimeType,
        'ext'     => $ext,
        'md5'     => $md5,
    ];
}
function getUploadErrorMessage(int $code): string
{
    return match($code) {
        UPLOAD_ERR_INI_SIZE   => 'File supera il limite php.ini',
        UPLOAD_ERR_FORM_SIZE  => 'File supera il limite del form',
        UPLOAD_ERR_PARTIAL    => 'File caricato parzialmente',
        UPLOAD_ERR_NO_FILE    => 'Nessun file selezionato',
        UPLOAD_ERR_NO_TMP_DIR => 'Directory temporanea mancante',
        UPLOAD_ERR_CANT_WRITE => 'Impossibile scrivere il file',
        UPLOAD_ERR_EXTENSION  => 'Estensione PHP ha bloccato il file',
        default               => 'Errore upload sconosciuto',
    };
}
// =============================================================================
// LOGGING
// =============================================================================
class Logger
{
    private static string $logFile = '';
    private static function getLogFile(): string
    {
        if (!self::$logFile) {
            if (!is_dir(LOG_PATH)) mkdir(LOG_PATH, 0755, true);
            self::$logFile = LOG_PATH . '/app_' . date('Y-m') . '.log';
        }
        return self::$logFile;
    }
    public static function log(string $level, string $message, array $context = []): void
    {
        $levels      = ['DEBUG' => 0, 'INFO' => 1, 'WARNING' => 2, 'ERROR' => 3];
        $configLevel = $levels[LOG_LEVEL] ?? 1;
        $msgLevel    = $levels[$level]    ?? 0;
        if ($msgLevel < $configLevel) return;
        $contextStr = !empty($context) ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $line = sprintf(
            "[%s] [%s] [IP:%s] [U:%s] %s%s\n",
            date('Y-m-d H:i:s'),
            $level,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            Auth::id() ?? 0,
            $message,
            $contextStr
        );
        $logFile = self::getLogFile();
        if (file_exists($logFile) && filesize($logFile) > LOG_MAX_SIZE) {
            rename($logFile, $logFile . '.' . date('His') . '.bak');
        }
        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
    public static function debug(string $msg, array $ctx = []): void   { self::log('DEBUG',   $msg, $ctx); }
    public static function info(string $msg, array $ctx = []): void    { self::log('INFO',    $msg, $ctx); }
    public static function warning(string $msg, array $ctx = []): void { self::log('WARNING', $msg, $ctx); }
    public static function error(string $msg, array $ctx = []): void   { self::log('ERROR',   $msg, $ctx); }
    public static function audit(
        string $azione,
        string $entitaTipo,
        ?int   $entitaId  = null,
               $dataPrima = null,
               $dataDopo  = null,
        string $esito     = 'OK',
        string $msg       = ''
    ): void {
        try {
            Database::insert('pm_audit_log', [
                'utente_id'   => Auth::id(),
                'azione'      => $azione,
                'entita_tipo' => $entitaTipo,
                'entita_id'   => $entitaId,
                'dati_prima'  => $dataPrima ? json_encode($dataPrima) : null,
                'dati_dopo'   => $dataDopo  ? json_encode($dataDopo)  : null,
                'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                'user_agent'  => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                'esito'       => $esito,
                'messaggio'   => $msg,
            ]);
        } catch (Throwable $e) {
            self::error('Audit log fallito: ' . $e->getMessage());
        }
    }
}
// =============================================================================
// NOTIFICATION HELPER
// =============================================================================
function createNotification(
    int    $utenteId,
    string $tipo,
    string $titolo,
    string $messaggio,
    string $link       = '',
    string $entitaTipo = '',
    ?int   $entitaId   = null
): void {
    try {
        Database::insert('pm_notifiche', [
            'utente_id'   => $utenteId,
            'tipo'        => $tipo,
            'titolo'      => $titolo,
            'messaggio'   => $messaggio,
            'link'        => $link,
            'entita_tipo' => $entitaTipo,
            'entita_id'   => $entitaId,
        ]);
    } catch (Throwable $e) {
        Logger::error('Notifica non creata: ' . $e->getMessage());
    }
}
function notifyCommessaTeam(int $commessaId, string $tipo, string $titolo, string $msg, string $link = ''): void
{
    $utenti = Database::fetchAll(
        'SELECT DISTINCT utente_id FROM pm_commesse_utenti WHERE commessa_id = :id
         UNION
         SELECT rup_id FROM pm_commesse WHERE id = :id2 AND rup_id IS NOT NULL
         UNION
         SELECT pm_id FROM pm_commesse WHERE id = :id3 AND pm_id IS NOT NULL
         UNION
         SELECT dl_id FROM pm_commesse WHERE id = :id4 AND dl_id IS NOT NULL',
        [':id' => $commessaId, ':id2' => $commessaId, ':id3' => $commessaId, ':id4' => $commessaId]
    );
    foreach ($utenti as $u) {
        if ($u['utente_id'] && $u['utente_id'] != Auth::id()) {
            createNotification((int)$u['utente_id'], $tipo, $titolo, $msg, $link, 'commessa', $commessaId);
        }
    }
}
// =============================================================================
// REQUEST HELPERS
// =============================================================================
function isAjax(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}
function isMethod(string $method): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === strtoupper($method);
}
function getJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
// =============================================================================
// REDIRECT
// FIX: rimosso tipo ritorno "never" (PHP 8.1+), sostituito con void + exit
// =============================================================================
function redirect(string $url, int $code = 302): void
{
    header("Location: {$url}", true, $code);
    exit;
}
function redirectBack(string $fallback = '/'): void
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    redirect(!empty($ref) ? $ref : $fallback);
}
// =============================================================================
// BOOT: applica headers sicurezza
// FIX: rimosso foreach (SECURITY_HEADERS ...) - era un array costante rimosso.
//      Le header sono ora impostate direttamente qui.
// =============================================================================
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
if (defined('CSP_POLICY')) {
    header('Content-Security-Policy: ' . CSP_POLICY);
}
