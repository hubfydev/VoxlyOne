<?php
declare(strict_types=1);

/** Bloquear / desbloquear usuário. Só POST com CSRF; volta para a tela de origem. */

define('ADMIN_AREA', true);
require_once __DIR__ . '/../boot.php';
require_once APP_INCLUDES . '/admin.php';

// Só volta para telas do próprio painel (nada de redirect aberto)
$back = (string)($_POST['back'] ?? '');
if (preg_match('#^/admin/[a-z_]+\.php(\?[A-Za-z0-9=&_%.+-]*)?$#', $back) !== 1) {
    $back = '/admin/users.php';
}

require_admin_post($back);

$userId = (int)($_POST['user_id'] ?? 0);
$action = (string)($_POST['action'] ?? '');

$stmt = db()->prepare('SELECT id, name, blocked_at FROM users WHERE id = ?');
$stmt->execute([$userId]);
$target = $stmt->fetch();

if ($target === false) {
    admin_flash('error', 'Usuário não encontrado.');
    redirect($back);
}

if ($action === 'block') {
    $reason = trim((string)($_POST['reason'] ?? ''));
    $stmt = db()->prepare(
        'UPDATE users SET blocked_at = NOW(), blocked_reason = ? WHERE id = ? AND blocked_at IS NULL'
    );
    $stmt->execute([$reason === '' ? null : mb_substr($reason, 0, 200), $userId]);
    // A sessão dele cai na próxima requisição (current_user confere blocked_at)
    admin_flash('success', $target['name'] . ' foi bloqueado. O acesso ao app foi encerrado.');
} elseif ($action === 'unblock') {
    $stmt = db()->prepare('UPDATE users SET blocked_at = NULL, blocked_reason = NULL WHERE id = ?');
    $stmt->execute([$userId]);
    admin_flash('success', $target['name'] . ' foi desbloqueado e já pode entrar de novo.');
} else {
    admin_flash('error', 'Ação inválida.');
}

redirect($back);
