<?php
/**
 * CampusCoin — database connection (PDO).
 * Edit the defaults below OR create a .env file (see .env.example).
 * XAMPP defaults: user "root", empty password.
 */

/** Returns a shared PDO connection (created lazily). */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = env('DB_HOST', 'localhost');
    $port = env('DB_PORT', '3306');
    $name = env('DB_NAME', 'campuscoin');
    $user = env('DB_USER', 'root');
    $pass = env('DB_PASS', '');

    try {
        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (PDOException $e) {
        error_log('CampusCoin DB connection failed: ' . $e->getMessage());
        renderErrorPage(
            500,
            'We can\'t reach the database',
            'CampusCoin could not connect to MySQL. Make sure MySQL is running in XAMPP and that the "campuscoin" database has been imported (see README.md).',
            'database',
            APP_DEBUG ? $e->getMessage() : null
        );
    }
    return $pdo;
}

/** Run a prepared statement and return the statement. */
function dbRun(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

/** All rows. */
function dbAll(string $sql, array $params = []): array
{
    return dbRun($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
}

/** First row or null. */
function dbRow(string $sql, array $params = []): ?array
{
    $row = dbRun($sql, $params)->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}

/** First column of the first row (or null). */
function dbValue(string $sql, array $params = [])
{
    $v = dbRun($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}
