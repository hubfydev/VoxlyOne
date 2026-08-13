<?php
declare(strict_types=1);

/**
 * Rate limit com incremento ATÔMICO antes da chamada externa.
 * Nunca check-depois-grava: duas abas simultâneas furariam o limite.
 */

/**
 * Incrementa e devolve a contagem do dia. Sempre chamar ANTES da chamada à OpenAI.
 * Se algo falhar depois, use rate_limit_refund() — ver a regra de devolução em 3.2.
 */
function rate_limit_hit(int $userId, string $action): int
{
    $stmt = db()->prepare(
        'INSERT INTO rate_limits (user_id, action, day, count)
              VALUES (:uid, :action, CURDATE(), 1)
         ON DUPLICATE KEY UPDATE count = count + 1'
    );
    $stmt->execute([':uid' => $userId, ':action' => $action]);

    $stmt = db()->prepare(
        'SELECT count FROM rate_limits
          WHERE user_id = :uid AND action = :action AND day = CURDATE()'
    );
    $stmt->execute([':uid' => $userId, ':action' => $action]);

    return (int)$stmt->fetchColumn();
}

/**
 * Devolve a cota. Chamar em QUALQUER caminho que não grave um attempt:
 * timeout, HTTP 4xx/5xx da OpenAI, saldo esgotado, abort pelo teto global.
 * Única exceção: erro de validação do usuário.
 */
function rate_limit_refund(int $userId, string $action): void
{
    $stmt = db()->prepare(
        'UPDATE rate_limits SET count = count - 1
          WHERE user_id = :uid AND action = :action AND day = CURDATE() AND count > 0'
    );
    $stmt->execute([':uid' => $userId, ':action' => $action]);
}

/** Soma de TODAS as análises do dia — o botão de emergência de custo. */
function global_daily_count(string $action = 'analyze'): int
{
    $stmt = db()->prepare(
        'SELECT COALESCE(SUM(count), 0) FROM rate_limits
          WHERE action = :action AND day = CURDATE()'
    );
    $stmt->execute([':action' => $action]);

    return (int)$stmt->fetchColumn();
}
