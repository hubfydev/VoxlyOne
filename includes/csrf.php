<?php
declare(strict_types=1);

/** Token CSRF por sessão. Gerado uma vez e reutilizado enquanto a sessão viver. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Campo pronto para colar dentro de um <form>. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Compara em tempo constante. Retorna false se ausente ou divergente. */
function csrf_check(?string $token): bool
{
    return is_string($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/** Versão para endpoints /api: aborta com 403 JSON se o token não bater. */
function csrf_require(): void
{
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        json_error('csrf_invalid', 'Sessão expirada. Recarregue a página.', 403);
    }
}
