<?php
/**
 * APPALTI PUBBLICI SAAS - Database Layer (PDO Singleton)
 * @version 1.0.1 (FIXED per Altervista)
 *
 * FIX: le opzioni PDO sono ora definite localmente come array
 *      invece di usare la costante DB_OPTIONS (rimossa da config.php)
 */
if (!defined('APP_INIT')) { exit('Accesso negato'); }
class Database
{
    private static $instance = null;
    private static $queryCount = 0;
    // FIX: opzioni PDO definite qui come costante di classe
    // invece di define('DB_OPTIONS', [...]) in config.php
    private static function getPdoOptions(): array
    {
        return [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];
    }
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::connect();
        }
        return self::$instance;
    }
    private static function connect()
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );
        try {
            self::$instance = new PDO($dsn, DB_USER, DB_PASS, self::getPdoOptions());
        } catch (PDOException $e) {
            self::handleError($e);
        }
    }
    public static function query(string $sql, array $params = [])
    {
        $pdo = self::getInstance();
        self::$queryCount++;
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            self::handleError($e, $sql, $params);
        }
    }
    public static function fetchAll(string $sql, array $params = [])
    {
        return self::query($sql, $params)->fetchAll();
    }
    public static function fetchOne(string $sql, array $params = [])
    {
        $result = self::query($sql, $params)->fetch();
        return $result !== false ? $result : null;
    }
    // FIX: rimosso tipo di ritorno "mixed" (non supportato in PHP < 8.0)
    public static function fetchValue(string $sql, array $params = [])
    {
        $result = self::query($sql, $params)->fetchColumn();
        return $result !== false ? $result : null;
    }
    public static function insert(string $table, array $data)
    {
        if (empty($data)) {
            throw new InvalidArgumentException('Dati inserimento vuoti');
        }
        $table        = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $columns      = array_keys($data);
        $placeholders = array_map(function($c) { return ':' . $c; }, $columns);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(function($c) { return '`' . $c . '`'; }, $columns)),
            implode(', ', $placeholders)
        );
        $namedParams = [];
        foreach ($data as $col => $val) {
            $namedParams[':' . $col] = $val;
        }
        self::query($sql, $namedParams);
        return (int) self::getInstance()->lastInsertId();
    }
    public static function update(string $table, array $data, array $where)
    {
        if (empty($data) || empty($where)) {
            throw new InvalidArgumentException('Dati o condizioni WHERE vuoti');
        }
        $table        = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $setClauses   = [];
        $whereClauses = [];
        $params       = [];
        foreach ($data as $col => $val) {
            $key              = 'set_' . $col;
            $setClauses[]     = '`' . $col . '` = :' . $key;
            $params[':' . $key] = $val;
        }
        foreach ($where as $col => $val) {
            $key               = 'wh_' . $col;
            $whereClauses[]    = '`' . $col . '` = :' . $key;
            $params[':' . $key] = $val;
        }
        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            $table,
            implode(', ', $setClauses),
            implode(' AND ', $whereClauses)
        );
        return self::query($sql, $params)->rowCount();
    }
    public static function delete(string $table, array $where)
    {
        $table        = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $whereClauses = [];
        $params       = [];
        foreach ($where as $col => $val) {
            $key               = 'wh_' . $col;
            $whereClauses[]    = '`' . $col . '` = :' . $key;
            $params[':' . $key] = $val;
        }
        $sql = sprintf(
            'DELETE FROM `%s` WHERE %s',
            $table,
            implode(' AND ', $whereClauses)
        );
        return self::query($sql, $params)->rowCount();
    }
    public static function beginTransaction()
    {
        return self::getInstance()->beginTransaction();
    }
    public static function commit()
    {
        return self::getInstance()->commit();
    }
    public static function rollback()
    {
        try {
            return self::getInstance()->rollBack();
        } catch (PDOException $e) {
            return false;
        }
    }
    public static function paginate(
        string $sql,
        array  $params  = [],
        int    $page    = 1,
        int    $perPage = ITEMS_PER_PAGE
    ) {
        $page    = max(1, $page);
        $perPage = min($perPage, MAX_ITEMS_PER_PAGE);
        $offset  = ($page - 1) * $perPage;
        $countSql = 'SELECT COUNT(*) FROM (' . $sql . ') AS _count_query';
        $total    = (int) self::fetchValue($countSql, $params);
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
    // FIX: rimosso tipo di ritorno "never" (non supportato in PHP < 8.1)
    private static function handleError(PDOException $e, string $sql = '', array $params = [])
    {
        $message = APP_DEBUG
            ? sprintf("DB Error: %s\nSQL: %s\nParams: %s",
                $e->getMessage(), $sql, json_encode($params))
            : 'Errore database. Riprovare più tardi.';
        if (class_exists('Logger')) {
            Logger::error('Database error', [
                'error' => $e->getMessage(),
                'sql'   => APP_DEBUG ? $sql : '***',
                'code'  => $e->getCode(),
            ]);
        }
        throw new RuntimeException($message, (int) $e->getCode(), $e);
    }
    public static function getQueryCount()
    {
        return self::$queryCount;
    }
    private function __construct() {}
    private function __clone() {}
}
function db() { return Database::getInstance(); }
