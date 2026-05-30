<?php
/**
 * Single PDO database connection.
 * Uses prepared statements everywhere to prevent SQL injection.
 */
require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // throw on errors
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // assoc arrays
        PDO::ATTR_EMULATE_PREPARES   => false,                   // real prepared stmts
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        http_response_code(500);
        exit(
            'Database connection failed. Please make sure MySQL is running and that '
            . 'the database "' . DB_NAME . '" has been imported (see README.md).'
        );
    }

    return $pdo;
}
