<?php
declare(strict_types=1);

define('ADMIN_AREA', true);
require_once __DIR__ . '/../boot.php';
require_once APP_INCLUDES . '/admin.php';

$admin = require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_admin_post('/admin/account.php');
    $current = (string)($_POST['current_password'] ?? '');

    if (!admin_password_ok((int)$admin['id'], $current)) {
        admin_flash('error', 'Senha atual incorreta. Nada foi alterado.');
        redirect('/admin/account.php');
    }

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'username') {
        $newUser = trim((string)($_POST['new_username'] ?? ''));
        if (preg_match('/^[A-Za-z0-9._-]{3,60}$/', $newUser) !== 1) {
            admin_flash('error', 'O usuário deve ter de 3 a 60 caracteres: letras, números, ponto, hífen ou sublinhado.');
            redirect('/admin/account.php');
        }
        try {
            db()->prepare('UPDATE admins SET username = ? WHERE id = ?')->execute([$newUser, $admin['id']]);
        } catch (PDOException $ex) {
            admin_flash('error', 'Esse usuário já existe.');
            redirect('/admin/account.php');
        }
        admin_flash('success', 'Usuário alterado para "' . $newUser . '".');
        redirect('/admin/account.php');
    }

    if ($action === 'password') {
        $new     = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        $problem = match (true) {
            mb_strlen($new) < 8                          => 'A nova senha precisa de pelo menos 8 caracteres.',
            $new !== $confirm                            => 'A confirmação não confere com a nova senha.',
            $new === $current                            => 'A nova senha precisa ser diferente da atual.',
            strcasecmp($new, ADMIN_DEFAULT_PASSWORD) === 0 => 'Escolha uma senha diferente da inicial.',
            default                                      => null,
        };
        if ($problem !== null) {
            admin_flash('error', $problem);
            redirect('/admin/account.php');
        }

        $stmt = db()->prepare('UPDATE admins SET password_hash = ?, password_changed_at = NOW() WHERE id = ?');
        $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $admin['id']]);

        // Senha nova, sessão nova
        session_regenerate_id(true);
        admin_flash('success', 'Senha alterada com sucesso.');
        redirect('/admin/account.php');
    }

    admin_flash('error', 'Ação inválida.');
    redirect('/admin/account.php');
}

$pageTitle = 'Conta — Painel VoxlyOne';
$adminTab  = 'account';
require APP_INCLUDES . '/admin_header.php';
?>

<div class="page-head">
  <div>
    <p class="page-head__eyebrow">Painel administrativo</p>
    <h1>Conta</h1>
  </div>
</div>

<section class="card admin-identity">
  <span class="admin-identity__avatar"><?= icon('shield-check') ?></span>
  <div>
    <p class="admin-identity__name"><?= e($admin['username']) ?></p>
    <p class="admin-identity__meta">
      Último acesso: <?= e(format_datetime($admin['last_login_at'])) ?>
    </p>
  </div>
</section>

<?php if (admin_uses_default_password($admin)): ?>
  <p class="admin-warning admin-warning--static">
    <?= icon('triangle-alert') ?>
    <span><strong>Senha inicial em uso.</strong> Qualquer pessoa que conheça a senha padrão consegue entrar. Troque agora.</span>
  </p>
<?php endif; ?>

<h2 class="section-title">Trocar senha</h2>
<form class="card form" method="post" action="/admin/account.php" novalidate>
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="password">
  <input type="hidden" name="username" value="<?= e($admin['username']) ?>" autocomplete="username">

  <label class="field">
    <span class="field__label">Senha atual</span>
    <input type="password" name="current_password" autocomplete="current-password" required>
  </label>
  <label class="field">
    <span class="field__label">Nova senha</span>
    <input type="password" name="new_password" autocomplete="new-password" minlength="8" required>
    <span class="field__hint">Pelo menos 8 caracteres. Uma frase longa é mais segura que uma senha curta complicada.</span>
  </label>
  <label class="field">
    <span class="field__label">Confirmar nova senha</span>
    <input type="password" name="confirm_password" autocomplete="new-password" minlength="8" required>
  </label>
  <button class="btn btn--block" type="submit"><?= icon('key-round') ?> Salvar nova senha</button>
</form>

<h2 class="section-title">Trocar usuário</h2>
<form class="card form" method="post" action="/admin/account.php" novalidate>
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="username">

  <label class="field">
    <span class="field__label">Novo usuário</span>
    <input type="text" name="new_username" value="<?= e($admin['username']) ?>" autocapitalize="none"
           spellcheck="false" maxlength="60" required>
    <span class="field__hint">De 3 a 60 caracteres: letras, números, ponto, hífen ou sublinhado.</span>
  </label>
  <label class="field">
    <span class="field__label">Senha atual</span>
    <input type="password" name="current_password" autocomplete="current-password" required>
  </label>
  <button class="btn btn--block btn--ghost" type="submit"><?= icon('user-check') ?> Salvar novo usuário</button>
</form>

<?php require APP_INCLUDES . '/admin_footer.php'; ?>
