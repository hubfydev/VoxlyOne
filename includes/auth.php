<?php
declare(strict_types=1);

/**
 * Versão do texto de consentimento de gravação em vigor.
 * v1: o áudio era sempre descartado. v2: gravações com nota > 8 ficam guardadas
 * para as playlists. Quem aceitou uma versão anterior precisa aceitar de novo.
 */
const CONSENT_VERSION = 2;

/** Usuário logado, ou null. Faz uma única consulta por request. */
function current_user(): ?array
{
    static $user = null;
    static $loaded = false;

    if ($loaded) {
        return $user;
    }
    $loaded = true;

    $id = $_SESSION['user_id'] ?? null;
    if (!is_int($id)) {
        return null;
    }

    try {
        $stmt = db()->prepare(
            'SELECT id, google_id, email, name, avatar_url, consented_at, consent_version,
                    blocked_at, created_at
               FROM users WHERE id = ?'
        );
        $stmt->execute([$id]);
    } catch (PDOException $ex) {
        // Código novo no ar antes da migration do painel: segue sem o bloqueio
        error_log('current_user sem blocked_at (rodar migration do painel): ' . $ex->getMessage());
        $stmt = db()->prepare(
            'SELECT id, google_id, email, name, avatar_url, consented_at, consent_version, created_at
               FROM users WHERE id = ?'
        );
        $stmt->execute([$id]);
    }
    $row = $stmt->fetch();

    // Sessão apontando para usuário que não existe mais (conta excluída)
    if ($row === false) {
        session_destroy();
        return null;
    }

    // Conta bloqueada pelo painel: derruba a sessão na próxima requisição
    if (!empty($row['blocked_at'])) {
        unset($_SESSION['user_id']);
        $_SESSION['blocked_notice'] = true;
        return null;
    }

    $user = $row;
    return $user;
}

/** A sessão acabou de ser encerrada por bloqueio da conta? */
function session_was_blocked(): bool
{
    return !empty($_SESSION['blocked_notice']);
}

/** Registra o login bem-sucedido. Nunca derruba o login. */
function record_user_login(int $userId): void
{
    try {
        $stmt = db()->prepare(
            'UPDATE users SET last_login_at = NOW(), login_count = login_count + 1 WHERE id = ?'
        );
        $stmt->execute([$userId]);
    } catch (PDOException $ex) {
        error_log('record_user_login: ' . $ex->getMessage());
    }
}

/** A conta está bloqueada? (usada no login, antes de abrir a sessão) */
function is_user_blocked(int $userId): bool
{
    try {
        $stmt = db()->prepare('SELECT blocked_at IS NOT NULL FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        return (bool)$stmt->fetchColumn();
    } catch (PDOException $ex) {
        return false;
    }
}

/** O usuário aceitou o texto de consentimento ATUAL? */
function has_current_consent(array $user): bool
{
    return $user['consented_at'] !== null && (int)$user['consent_version'] >= CONSENT_VERSION;
}

/** Topo de toda PÁGINA protegida: sem sessão, volta para a landing. */
function require_auth(): array
{
    $user = current_user();
    if ($user === null) {
        redirect('/index.php');
    }
    return $user;
}

/** Topo de todo ENDPOINT /api protegido: sem sessão, 401 JSON — nunca redirect. */
function require_auth_api(): array
{
    $user = current_user();
    if ($user === null && session_was_blocked()) {
        json_error('blocked', 'Sua conta está bloqueada. Fale com us@hubfy.us.', 403);
    }
    if ($user === null) {
        json_error('unauthorized', 'Faça login para continuar.', 401);
    }
    return $user;
}

/**
 * Cria ou atualiza o usuário vindo do Google.
 * Upsert obrigatório: email e google_id são ambos UNIQUE, e um INSERT simples
 * daria erro 500 se o mesmo e-mail voltasse associado a outro google_id.
 */
function upsert_google_user(string $googleId, string $email, string $name, ?string $avatarUrl): int
{
    $stmt = db()->prepare(
        'INSERT INTO users (google_id, email, name, avatar_url)
              VALUES (:gid, :email, :name, :avatar)
         ON DUPLICATE KEY UPDATE
              name       = VALUES(name),
              avatar_url = VALUES(avatar_url),
              id         = LAST_INSERT_ID(id)'
    );
    $stmt->execute([
        ':gid'    => $googleId,
        ':email'  => $email,
        ':name'   => mb_substr($name, 0, 120),
        ':avatar' => $avatarUrl !== null ? mb_substr($avatarUrl, 0, 500) : null,
    ]);

    // LAST_INSERT_ID(id) no UPDATE faz o lastInsertId() devolver o id existente
    return (int)db()->lastInsertId();
}
