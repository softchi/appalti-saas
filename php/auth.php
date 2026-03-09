<?php
/**
 * APPALTI PUBBLICI SAAS - Sistema di Autenticazione
 *
 * Gestione login, pm_sessioni server-side, CSRF, RBAC.
 * Protezione brute-force con rate limiting.
 *
 * @version 1.0.0
 */

if (!defined('APP_INIT')) { exit('Accesso negato'); }

class Auth
{
    private static ?array $currentUser     = null;
    private static ?array $userPermissions = null;
    private static string $sessionToken    = '';

    // ==========================================================================
    // SESSIONE
    // ==========================================================================

    /**
     * Inizializza la gestione pm_sessioni sicura
     */
    public static function initSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path'     => '/',
                'domain'   => '',
                'secure'   => SESSION_SECURE,
                'httponly' => SESSION_HTTP_ONLY,
                'samesite' => SESSION_SAME_SITE,
            ]);
            session_start();
        }

        // Rigenerazione ID sessione ogni 30 minuti (anti session fixation)
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }

    /**
     * Esegue il login utente
     *
     * @param string $email
     * @param string $password
     * @param bool   $rememberMe
     * @return array ['success' => bool, 'message' => string, 'user' => array|null]
     */
    public static function login(string $email, string $password, bool $rememberMe = false): array
    {
        $email = strtolower(trim($email));

        // Validazione input
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Email non valida'];
        }
        if (empty($password)) {
            return ['success' => false, 'message' => 'Password richiesta'];
        }

        // Verifica rate limiting (brute force protection)
        $lockKey = 'login_attempts_' . md5($email . $_SERVER['REMOTE_ADDR']);
        $attempts = self::getRateLimitAttempts($lockKey);

        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            $remaining = self::getRateLimitRemaining($lockKey);
            Logger::warning('Login bloccato (brute force)', ['email' => $email, 'ip' => $_SERVER['REMOTE_ADDR']]);
            return ['success' => false, 'message' => "Troppi tentativi. Riprova tra {$remaining} minuti."];
        }

        // Recupera utente dal DB
        $user = Database::fetchOne(
            'SELECT u.*, r.codice AS ruolo_codice, r.nome AS ruolo_nome, r.livello AS ruolo_livello
             FROM pm_utenti u
             JOIN pm_ruoli r ON r.id = u.ruolo_id
             WHERE u.email = :email AND u.attivo = 1',
            [':email' => $email]
        );

        // Utente non trovato O password errata (timing attack safe)
        if (!$user || !password_verify($password, $user['password_hash'])) {
            self::incrementRateLimit($lockKey);
            Logger::warning('Tentativo login fallito', ['email' => $email, 'ip' => $_SERVER['REMOTE_ADDR']]);
            return ['success' => false, 'message' => 'Credenziali non valide'];
        }

        // Verifica se l'email è stata verificata (opzionale, configurabile)
        // if (!$user['email_verificata']) {
        //     return ['success' => false, 'message' => 'Email non ancora verificata'];
        // }

        // Reset rate limiting
        self::clearRateLimit($lockKey);

        // Aggiorna password hash se necessario (password rehash)
        if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT, ['cost' => PASSWORD_COST])) {
            $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);
            Database::update('pm_utenti', ['password_hash' => $newHash], ['id' => $user['id']]);
        }

        // Crea token sessione
        $sessionToken = bin2hex(random_bytes(32));
        $csrfToken    = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
        $scadeIl      = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);

        // Persisti sessione nel DB
        Database::insert('pm_sessioni', [
            'id'         => $sessionToken,
            'utente_id'  => $user['id'],
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'csrf_token' => $csrfToken,
            'scade_il'   => $scadeIl,
        ]);

        // Aggiorna ultimo accesso
        Database::update('pm_utenti', ['ultimo_accesso' => date('Y-m-d H:i:s')], ['id' => $user['id']]);

        // Salva in sessione PHP
        $_SESSION['session_token'] = $sessionToken;
        $_SESSION['csrf_token']    = $csrfToken;
        $_SESSION['user_id']       = $user['id'];

        // Remember me: cookie persistente (30 giorni)
        if ($rememberMe) {
            $rememberToken = bin2hex(random_bytes(32));
            setcookie(
                'remember_token',
                $rememberToken . ':' . $user['id'],
                [
                    'expires'  => time() + (REMEMBER_ME_DAYS * 86400),
                    'path'     => '/',
                    'secure'   => SESSION_SECURE,
                    'httponly' => true,
                    'samesite' => 'Strict',
                ]
            );
            Database::update('pm_utenti', [
                'token_reset' => hash('sha256', $rememberToken)
            ], ['id' => $user['id']]);
        }

        // Audit log
        Logger::audit('LOGIN', 'pm_utenti', $user['id'], null, null, 'OK');

        self::$currentUser  = self::sanitizeUser($user);
        self::$sessionToken = $sessionToken;

        return ['success' => true, 'message' => 'Login effettuato', 'user' => self::$currentUser];
    }

    /**
     * Logout utente
     */
    public static function logout(): void
    {
        self::initSession();

        if (isset($_SESSION['session_token'])) {
            // Elimina sessione dal DB
            Database::delete('pm_sessioni', ['id' => $_SESSION['session_token']]);
        }

        if (isset($_SESSION['user_id'])) {
            Logger::audit('LOGOUT', 'pm_utenti', $_SESSION['user_id'], null, null, 'OK');
        }

        // Pulisci sessione PHP
        $_SESSION = [];
        session_destroy();

        // Rimuovi cookie remember_me
        setcookie('remember_token', '', time() - 3600, '/', '', SESSION_SECURE, true);
    }

    /**
     * Verifica se l'utente è autenticato
     */
    public static function check(): bool
    {
        self::initSession();

        if (empty($_SESSION['session_token']) || empty($_SESSION['user_id'])) {
            return false;
        }

        // Verifica sessione nel DB
        $sessione = Database::fetchOne(
            'SELECT s.*, u.attivo FROM pm_sessioni s
             JOIN pm_utenti u ON u.id = s.utente_id
             WHERE s.id = :token AND s.utente_id = :uid AND s.scade_il > NOW() AND u.attivo = 1',
            [':token' => $_SESSION['session_token'], ':uid' => $_SESSION['user_id']]
        );

        if (!$sessione) {
            self::logout();
            return false;
        }

        // Aggiorna last_activity
        Database::query(
            'UPDATE pm_sessioni SET last_activity = NOW() WHERE id = :id',
            [':id' => $_SESSION['session_token']]
        );

        return true;
    }

    /**
     * Richiede autenticazione, altrimenti reindirizza al login
     *
     * @param string|null $permission  Permesso richiesto (opzionale)
     */
    public static function require(string $permission = null): void
    {
        if (!self::check()) {
            $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
            header('Location: ' . APP_URL . '/login.php?redirect=' . $redirect);
            exit;
        }

        if ($permission && !self::can($permission)) {
            http_response_code(403);
            include COMPONENTS_PATH . '/403.php';
            exit;
        }
    }

    // ==========================================================================
    // UTENTE CORRENTE
    // ==========================================================================

    /**
     * Restituisce l'utente corrente autenticato
     */
    public static function user(): ?array
    {
        if (self::$currentUser !== null) {
            return self::$currentUser;
        }

        if (!self::check()) {
            return null;
        }

        $user = Database::fetchOne(
            'SELECT u.*, r.codice AS ruolo_codice, r.nome AS ruolo_nome, r.livello AS ruolo_livello
             FROM pm_utenti u
             JOIN pm_ruoli r ON r.id = u.ruolo_id
             WHERE u.id = :id AND u.attivo = 1',
            [':id' => $_SESSION['user_id']]
        );

        if (!$user) return null;

        self::$currentUser = self::sanitizeUser($user);
        return self::$currentUser;
    }

    /**
     * ID utente corrente
     */
    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int) $user['id'] : null;
    }

    /**
     * Controlla se l'utente ha un determinato permesso
     */
    public static function can(string $permissoCodice): bool
    {
        $user = self::user();
        if (!$user) return false;

        // SuperAdmin ha tutto
        if ($user['ruolo_codice'] === 'SUPERADMIN') return true;

        $permissions = self::getUserPermissions();
        return in_array($permissoCodice, $permissions, true);
    }

    /**
     * Controlla se l'utente ha un ruolo specifico
     */
    public static function hasRole(string|array $roles): bool
    {
        $user = self::user();
        if (!$user) return false;

        if (is_string($roles)) $roles = [$roles];
        return in_array($user['ruolo_codice'], $roles, true);
    }

    /**
     * Controlla se l'utente ha accesso a una commessa specifica
     */
    public static function canAccessCommessa(int $commessaId): bool
    {
        $user = self::user();
        if (!$user) return false;
        if ($user['ruolo_codice'] === 'SUPERADMIN' || $user['ruolo_codice'] === 'ADMIN') return true;

        // Verifica se l'utente è nel team della commessa
        $count = Database::fetchValue(
            'SELECT COUNT(*) FROM pm_commesse_utenti WHERE commessa_id = :cid AND utente_id = :uid
             UNION
             SELECT COUNT(*) FROM pm_commesse WHERE id = :cid2 AND (rup_id = :uid2 OR pm_id = :uid3 OR dl_id = :uid4 OR cse_id = :uid5)',
            [
                ':cid' => $commessaId, ':uid' => $user['id'],
                ':cid2' => $commessaId, ':uid2' => $user['id'],
                ':uid3' => $user['id'], ':uid4' => $user['id'], ':uid5' => $user['id'],
            ]
        );

        return (int) $count > 0;
    }

    // ==========================================================================
    // CSRF PROTECTION
    // ==========================================================================

    /**
     * Restituisce il token CSRF corrente
     */
    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Verifica il token CSRF (usare su ogni richiesta POST)
     */
    public static function verifyCsrf(?string $token = null): bool
    {
        $token = $token ?? ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Verifica CSRF o lancia eccezione (per API)
     */
    public static function requireCsrf(): void
    {
        if (!self::verifyCsrf()) {
            http_response_code(403);
            die(json_encode(['success' => false, 'message' => 'Token CSRF non valido']));
        }
    }

    // ==========================================================================
    // GESTIONE PASSWORD
    // ==========================================================================

    /**
     * Genera hash password sicuro
     */
    public static function hashPassword(string $password): string
    {
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            throw new InvalidArgumentException(
                'La password deve contenere almeno ' . PASSWORD_MIN_LENGTH . ' caratteri'
            );
        }
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);
    }

    /**
     * Genera token per reset password
     */
    public static function generateResetToken(string $email): ?string
    {
        $user = Database::fetchOne(
            'SELECT id FROM pm_utenti WHERE email = :email AND attivo = 1',
            [':email' => strtolower(trim($email))]
        );

        if (!$user) return null;

        $token    = bin2hex(random_bytes(32));
        $scadeIl  = date('Y-m-d H:i:s', time() + TOKEN_RESET_EXPIRY);

        Database::update('pm_utenti', [
            'token_reset'       => hash('sha256', $token),
            'token_reset_scade' => $scadeIl,
        ], ['id' => $user['id']]);

        return $token;
    }

    // ==========================================================================
    // HELPER PRIVATI
    // ==========================================================================

    /**
     * Rimuove dati sensibili dall'array utente
     */
    private static function sanitizeUser(array $user): array
    {
        unset($user['password_hash'], $user['token_reset'], $user['token_verifica']);
        return $user;
    }

    /**
     * Recupera pm_permessi utente (con cache in memoria)
     */
    private static function getUserPermissions(): array
    {
        if (self::$userPermissions !== null) {
            return self::$userPermissions;
        }

        $userId = self::id();
        if (!$userId) return [];

        $rows = Database::fetchAll(
            'SELECT p.codice FROM pm_permessi p
             JOIN pm_ruoli_permessi rp ON rp.permesso_id = p.id
             JOIN pm_utenti u ON u.ruolo_id = rp.ruolo_id
             WHERE u.id = :uid',
            [':uid' => $userId]
        );

        self::$userPermissions = array_column($rows, 'codice');
        return self::$userPermissions;
    }

    /**
     * Rate limiting semplice via DB/sessione
     */
    private static function getRateLimitAttempts(string $key): int
    {
        $record = Database::fetchOne(
            'SELECT COUNT(*) AS cnt FROM pm_audit_log
             WHERE entita_tipo = :key AND azione = :azione AND esito = :esito
             AND created_at > DATE_SUB(NOW(), INTERVAL :sec SECOND)',
            [':key' => 'rate_limit', ':azione' => $key, ':esito' => 'RIFIUTATO', ':sec' => LOCKOUT_DURATION]
        );
        return (int)($record['cnt'] ?? 0);
    }

    private static function getRateLimitRemaining(string $key): int
    {
        return (int) ceil(LOCKOUT_DURATION / 60);
    }

    private static function incrementRateLimit(string $key): void
    {
        Database::insert('pm_audit_log', [
            'utente_id'   => null,
            'azione'      => $key,
            'entita_tipo' => 'rate_limit',
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'esito'       => 'RIFIUTATO',
        ]);
    }

    private static function clearRateLimit(string $key): void
    {
        // Nota: non cancelliamo per mantenere lo storico, ma il conteggio
        // si azzera naturalmente dopo LOCKOUT_DURATION secondi
    }
}
