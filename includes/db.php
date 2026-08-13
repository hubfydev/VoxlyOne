<?php
declare(strict_types=1);

/** Conexão PDO singleton. Toda query do app passa por aqui, com prepared statements. */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $ex) {
        error_log('Falha na conexão MySQL: ' . $ex->getMessage());
        http_response_code(503);
        exit('Serviço temporariamente indisponível.');
    }

    return $pdo;
}
