<?php
declare(strict_types=1);

define('ADMIN_AREA', true);
require_once __DIR__ . '/../boot.php';
require_once APP_INCLUDES . '/admin.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

if (current_admin() !== null) {
    redirect('/admin/dashboard.php');
}

$error    = null;
$username = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $error = 'Sessão expirada. Tente novamente.';
    } elseif ($username === '' || $password === '') {
        $error = 'Informe usuário e senha.';
    } else {
        $error = admin_attempt_login($username, $password);
        if ($error === null) {
            redirect('/admin/dashboard.php');
        }
    }
}

$expired = !empty($_SESSION['admin_expired']);
unset($_SESSION['admin_expired']);

$pageTitle = 'Entrar no painel — VoxlyOne';
$admin     = null;
require APP_INCLUDES . '/admin_header.php';
?>

<section class="admin-login">
  <div class="admin-login__brand">
    <span class="brand__mark admin-login__mark"><?= icon('audio-lines') ?></span>
    <p class="admin-login__name"><span>Voxly<span class="brand__one">One</span></span> <span class="admin-badge">Admin</span></p>
  </div>

  <form class="card admin-login__card form" method="post" action="/admin/index.php" novalidate>
    <div>
      <h1 class="admin-login__title">Painel administrativo</h1>
      <p class="admin-login__sub">Acesso restrito à equipe do VoxlyOne.</p>
    </div>

    <?php if ($expired && $error === null): ?>
      <p class="notice" role="status">Sua sessão expirou por inatividade. Entre de novo.</p>
    <?php endif; ?>
    <?php if ($error !== null): ?>
      <p class="alert" role="alert"><?= icon('triangle-alert') ?><span><?= e($error) ?></span></p>
    <?php endif; ?>

    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <label class="field">
      <span class="field__label">Usuário</span>
      <input type="text" name="username" value="<?= e($username) ?>" autocomplete="username"
             autocapitalize="none" spellcheck="false" required autofocus>
    </label>

    <label class="field">
      <span class="field__label">Senha</span>
      <span class="admin-password">
        <input type="password" name="password" autocomplete="current-password" required>
        <button class="admin-password__toggle" type="button" data-toggle-password aria-label="Mostrar senha">
          <?= icon('eye') ?>
        </button>
      </span>
    </label>

    <button class="btn btn--lg btn--block" type="submit"><?= icon('log-in') ?> Entrar</button>
  </form>

  <p class="admin-login__foot"><?= icon('shield-check') ?> Tentativas erradas repetidas bloqueiam o acesso por 15 minutos.</p>
</section>

<?php require APP_INCLUDES . '/admin_footer.php'; ?>
