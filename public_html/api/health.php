<?php
declare(strict_types=1);

require_once __DIR__ . '/../boot.php';

// Toca o banco de propósito: sem isso, responderia 'ok' com o MySQL fora do ar
try {
    db()->query('SELECT 1');
} catch (Throwable $ex) {
    json_error('db_down', 'Serviço indisponível.', 503, $ex->getMessage());
}

json_ok(['status' => 'ok']);
