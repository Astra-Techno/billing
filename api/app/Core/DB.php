<?php

namespace App\Core;

use PDO;
use PDOException;

class DB
{
    private static array $connections = [];
    private static string $default    = 'mysql';
    private static array $config      = [];

    public static function connect(array $config): void
    {
        self::$config  = $config;
        self::$default = $config['default'] ?? 'mysql';
    }

    public static function connection(string $name = ''): static
    {
        // Returns a proxy — all static calls on the returned value hit the named connection.
        // For simplicity we store the requested name in a thread-local var and the next
        // real DB call picks it up.  Because PHP is single-threaded this is safe.
        $name = $name ?: self::$default;
        $_SERVER['__db_connection'] = $name;
        return new static(); // thin proxy
    }

    // ── PDO factory ──────────────────────────────────────────────────────────

    private static function pdo(string $name = ''): PDO
    {
        $name = $name ?: ($_SERVER['__db_connection'] ?? self::$default);
        unset($_SERVER['__db_connection']);

        if (isset(self::$connections[$name]))
            return self::$connections[$name];

        $cfg = self::$config['connections'][$name]
            ?? throw new \RuntimeException("DB connection '{$name}' not configured");

        $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset={$cfg['charset']}";

        self::$connections[$name] = new PDO($dsn, $cfg['username'], $cfg['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return self::$connections[$name];
    }

    // ── Public API ────────────────────────────────────────────────────────────

    public static function select(string $sql, array $params = []): array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function selectOne(string $sql, array $params = []): ?object
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function statement(string $sql, array $params = []): bool
    {
        $stmt = self::pdo()->prepare($sql);
        return $stmt->execute($params);
    }

    public static function affectingStatement(string $sql, array $params = []): int
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function lastInsertId(): string
    {
        return self::pdo()->lastInsertId();
    }

    public static function getPdo(string $name = ''): PDO
    {
        return self::pdo($name);
    }

    public static function getDatabaseName(string $name = ''): string
    {
        return self::$config['connections'][$name ?: self::$default]['database'] ?? '';
    }

    public static function prefix(): string
    {
        return self::$config['prefix'] ?? '';
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo    = self::pdo();
        $nested = $pdo->inTransaction();

        if (!$nested) $pdo->beginTransaction();
        try {
            $result = $callback();
            if (!$nested) $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if (!$nested) $pdo->rollBack();
            throw $e;
        }
    }

    // ── Simple query builder (for Table::save internals) ─────────────────────

    public static function table(string $table): QueryBuilder
    {
        return new QueryBuilder($table, self::pdo());
    }
}
