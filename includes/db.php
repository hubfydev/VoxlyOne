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
        // Alinha o MySQL ao fuso do app. Sem isto, CURDATE() e NOW() seguiriam o
        // fuso do servidor da hospedagem, e o limite diário de análises zeraria
        // numa hora diferente da que o usuário vê no perfil.
        //
        // Usamos o offset numérico atual (ex.: -04:00) porque as tabelas de fuso
        // do MySQL raramente estão carregadas em hospedagem compartilhada. Como
        // é recalculado a cada conexão, o horário de verão continua correto.
        $offset = (new DateTime('now', new DateTimeZone(date_default_timezone_get())))->format('P');
        $stmt = $pdo->prepare('SET time_zone = ?');
        $stmt->execute([$offset]);
    } catch (PDOException $ex) {
        error_log('Falha na conexão MySQL: ' . $ex->getMessage());
        http_response_code(503);
        exit('Serviço temporariamente indisponível.');
    }

    return $pdo;
}
