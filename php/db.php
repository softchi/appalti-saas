<?php
/**
 * APPALTI PUBBLICI SAAS - Database Layer (PDO Singleton)
 *
 * Gestione connessione MySQL con PDO.
 * Pattern Singleton per evitare connessioni multiple.
 * Tutti i parametri usano prepared statements (anti SQL-injection).
 *
 * @version 1.0.0
 */

if (!defined('APP_INIT')) { exit('Accesso negato'); }

class Database
{
    private static ?PDO $instance = null;
    private static int  $queryCount = 0;

    /**
     * Restituisce la connessione PDO (Singleton)
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::connect();
        }
        return self::$instance;
    }

    /**
     * Crea la connessione PDO
     */
    private static function connect(): void
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );

        try {
            self::$instance = new PDO($dsn, DB_USER, DB_PASS, DB_OPTIONS);
        } catch (PDOException $e) {
            self::handleError($e);
        }
    }

    /**
     * Esegue una query con parametri (prepared statement)
     *
     * @param string $sql     Query SQL con placeholder
     * @param array  $params  Parametri da bindare
     * @return PDOStatement
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $pdo  = self::getInstance();
        self::$queryCount++;

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            self::handleError($e, $sql, $params);
        }
    }

    /**
     * Recupera tutte le righe
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /**
     * Recupera una singola riga
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $result = self::query($sql, $params)->fetch();
        return $result !== false ? $result : null;
    }

    /**
     * Recupera un singolo valore scalare
     */
    public static function fetchValue(string $sql, array $params = []): mixed
    {
        $result = self::query($sql, $params)->fetchColumn();
        return $result !== false ? $result : null;
    }

    /**
     * Inserisce un record e restituisce l'ID inserito
     *
     * @param string $table   Nome tabella
     * @param array  $data    Dati da inserire (colonna => valore)
     * @return int            Ultimo ID inserito
     */
    public static function insert(string $table, array $data): int
    {
        if (empty($data)) {
            throw new InvalidArgumentException('Dati inserimento vuoti');
        }

        // Sanitizza nome tabella (solo caratteri alfanumerici e underscore)
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);

        $columns      = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $columns);

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(fn($c) => '`' . $c . '`', $columns)),
            implode(', ', $placeholders)
        );

        $namedParams = [];
        foreach ($data as $col => $val) {
            $namedParams[':' . $col] = $val;
        }

        self::query($sql, $namedParams);
        return (int) self::getInstance()->lastInsertId();
    }

    /**
     * Aggiorna record per ID
     *
     * @param string $table   Nome tabella
     * @param array  $data    Dati da aggiornare
     * @param array  $where   Condizioni WHERE (colonna => valore)
     * @return int            Righe modificate
     */
    public static function update(string $table, array $data, array $where): int
    {
        if (empty($data) || empty($where)) {
            throw new InvalidArgumentException('Dati o condizioni WHERE vuoti');
        }

        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);

        $setClauses   = [];
        $whereClauses = [];
        $params       = [];

        foreach ($data as $col => $val) {
            $key            = 'set_' . $col;
            $setClauses[]   = '`' . $col . '` = :' . $key;
            $params[':' . $key] = $val;
        }

        foreach ($where as $col => $val) {
            $key             = 'wh_' . $col;
            $whereClauses[]  = '`' . $col . '` = :' . $key;
            $params[':' . $key] = $val;
        }

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            $table,
            implode(', ', $setClauses),
            implode(' AND ', $whereClauses)
        );

        $stmt = self::query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Elimina record (soft delete consigliato a livello applicativo)
     */
    public static function delete(string $table, array $where): int
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);

        $whereClauses = [];
        $params       = [];

        foreach ($where as $col => $val) {
            $key             = 'wh_' . $col;
            $whereClauses[]  = '`' . $col . '` = :' . $key;
            $params[':' . $key] = $val;
        }

        $sql = sprintf(
            'DELETE FROM `%s` WHERE %s',
            $table,
            implode(' AND ', $whereClauses)
        );

        return self::query($sql, $params)->rowCount();
    }

    /**
     * Avvia una transazione
     */
    public static function beginTransaction(): bool
    {
        return self::getInstance()->beginTransaction();
    }

    /**
     * Commit transazione
     */
    public static function commit(): bool
    {
        return self::getInstance()->commit();
    }

    /**
     * Rollback transazione
     */
    public static function rollback(): bool
    {
        try {
            return self::getInstance()->rollBack();
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Paginazione: restituisce dati + metadata
     *
     * @param string $sql         Query base (senza LIMIT/OFFSET)
     * @param array  $params      Parametri query
     * @param int    $page        Pagina corrente (1-based)
     * @param int    $perPage     Elementi per pagina
     * @return array              ['data'=>[], 'total'=>N, 'pages'=>N, 'page'=>N]
     */
    public static function paginate(
        string $sql,
        array  $params  = [],
        int    $page    = 1,
        int    $perPage = ITEMS_PER_PAGE
    ): array {
        $page    = max(1, $page);
        $perPage = min($perPage, MAX_ITEMS_PER_PAGE);
        $offset  = ($page - 1) * $perPage;

        // Conta totale righe
        $countSql = 'SELECT COUNT(*) FROM (' . $sql . ') AS _count_query';
        $total    = (int) self::fetchValue($countSql, $params);

        // Esegui query con paginazione
        $data = self::fetchAll($sql . ' LIMIT :limit OFFSET :offset',
            array_merge($params, [':limit' => $perPage, ':offset' => $offset])
        );

        return [
            'data'    => $data,
            'total'   => $total,
            'pages'   => (int) ceil($total / $perPage),
            'page'    => $page,
            'perPage' => $perPage,
        ];
    }

    /**
     * Gestione errori centralizzata
     */
    private static function handleError(PDOException $e, string $sql = '', array $params = []): never
    {
        $message = APP_DEBUG
            ? sprintf("DB Error: %s\nSQL: %s\nParams: %s",
                $e->getMessage(), $sql, json_encode($params))
            : 'Errore database. Riprovare più tardi.';

        Logger::error('Database error', [
            'error'  => $e->getMessage(),
            'sql'    => APP_DEBUG ? $sql : '***',
            'code'   => $e->getCode(),
        ]);

        throw new RuntimeException($message, (int) $e->getCode(), $e);
    }

    /**
     * Numero query eseguite (debug)
     */
    public static function getQueryCount(): int
    {
        return self::$queryCount;
    }

    // Prevent instantiation
    private function __construct() {}
    private function __clone() {}
}

// Alias breve per uso rapido
function db(): PDO { return Database::getInstance(); }
