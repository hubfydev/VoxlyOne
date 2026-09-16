<?php
declare(strict_types=1);

define('ADMIN_AREA', true);
require_once __DIR__ . '/../boot.php';
require_once APP_INCLUDES . '/admin.php';

$admin = require_admin();

$view = (string)($_GET['ver'] ?? 'usuarios') === 'painel' ? 'painel' : 'usuarios';

$summary = db()->query(
    "SELECT
        (SELECT COUNT(*) FROM users WHERE last_login_at >= CURDATE())                       AS users_today,
        (SELECT COUNT(*) FROM users WHERE last_login_at >= CURDATE() - INTERVAL 6 DAY)      AS users_7d,
        (SELECT COUNT(*) FROM admin_logins WHERE success = 0 AND created_at >= NOW() - INTERVAL 1 DAY) AS admin_fails_24h,
        (SELECT COUNT(*) FROM admin_logins WHERE success = 1 AND created_at >= NOW() - INTERVAL 7 DAY) AS admin_ok_7d"
)->fetch();

if ($view === 'usuarios') {
    $rows = db()->query(
        'SELECT id, name, email, avatar_url, blocked_at, last_login_at, login_count, created_at
           FROM users
          WHERE last_login_at IS NOT NULL
          ORDER BY last_login_at DESC
          LIMIT 50'
    )->fetchAll();
} else {
    $rows = db()->query(
        'SELECT username_tried, ip, user_agent, success, created_at
           FROM admin_logins
          ORDER BY id DESC
          LIMIT 50'
    )->fetchAll();
}

/** Navegador e sistema, em poucas palavras, a partir do user agent. */
function short_agent(?string $agent): string
{
    $agent = (string)$agent;
    $browser = match (true) {
        str_contains($agent, 'Edg/')     => 'Edge',
        str_contains($agent, 'Chrome/')  => 'Chrome',
        str_contains($agent, 'Firefox/') => 'Firefox',
        str_contains($agent, 'Safari/')  => 'Safari',
        $agent === ''                    => 'Desconhecido',
        default                          => 'Outro',
    };
    $os = match (true) {
        str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iOS',
        str_contains($agent, 'Android')   => 'Android',
        str_contains($agent, 'Windows')   => 'Windows',
        str_contains($agent, 'Mac OS X')  => 'macOS',
        str_contains($agent, 'Linux')     => 'Linux',
        default                           => '',
    };
    return trim($browser . ($os !== '' ? ' · ' . $os : ''));
}

$pageTitle = 'Acessos — VoxlyOne Admin';
$adminTab  = 'access';
require APP_INCLUDES . '/admin_header.php';
?>

<div class="page-head">
  <div>
    <p class="page-head__eyebrow">Painel administrativo</p>
    <h1>Acessos</h1>
  </div>
</div>

<ul class="admin-stats">
  <li class="admin-stat admin-stat--brand">
    <span class="admin-stat__icon"><?= icon('log-in') ?></span>
    <span class="admin-stat__value"><?= num((int)$summary['users_today']) ?></span>
    <span class="admin-stat__label">usuários entraram hoje</span>
  </li>
  <li class="admin-stat">
    <span class="admin-stat__icon"><?= icon('users') ?></span>
    <span class="admin-stat__value"><?= num((int)$summary['users_7d']) ?></span>
    <span class="admin-stat__label">em 7 dias</span>
  </li>
  <li class="admin-stat admin-stat--success">
    <span class="admin-stat__icon"><?= icon('shield-check') ?></span>
    <span class="admin-stat__value"><?= num((int)$summary['admin_ok_7d']) ?></span>
    <span class="admin-stat__label">logins no painel (7 dias)</span>
  </li>
  <li class="admin-stat admin-stat--danger">
    <span class="admin-stat__icon"><?= icon('triangle-alert') ?></span>
    <span class="admin-stat__value"><?= num((int)$summary['admin_fails_24h']) ?></span>
    <span class="admin-stat__label">senhas erradas (24 h)</span>
  </li>
</ul>

<nav class="speeds admin-segmented" aria-label="Tipo de acesso">
  <a class="speed<?= $view === 'usuarios' ? ' is-active' : '' ?>" href="/admin/access.php" <?= $view === 'usuarios' ? 'aria-current="true"' : '' ?>>
    <?= icon('users') ?> Usuários do app
  </a>
  <a class="speed<?= $view === 'painel' ? ' is-active' : '' ?>" href="/admin/access.php?ver=painel" <?= $view === 'painel' ? 'aria-current="true"' : '' ?>>
    <?= icon('shield') ?> Painel
  </a>
</nav>

<?php if ($rows === []): ?>
  <div class="empty">
    <span class="empty__icon"><?= icon('history') ?></span>
    <p class="empty__title">Nenhum acesso registrado ainda</p>
    <p class="empty__text">Os acessos passam a ser registrados a partir desta versão do app.</p>
  </div>
<?php elseif ($view === 'usuarios'): ?>
  <ul class="card admin-list">
    <?php foreach ($rows as $row): ?>
      <li>
        <a class="admin-row" href="/admin/user.php?id=<?= (int)$row['id'] ?>">
          <?= admin_avatar($row) ?>
          <span class="admin-row__text">
            <span class="admin-row__title"><?= e($row['name']) ?></span>
            <span class="admin-row__sub"><?= e(format_datetime($row['last_login_at'])) ?> · <?= num((int)$row['login_count']) ?> login<?= (int)$row['login_count'] === 1 ? '' : 's' ?></span>
          </span>
          <span class="admin-row__value admin-row__value--muted"><?= e(time_ago($row['last_login_at'])) ?></span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
<?php else: ?>
  <ul class="card admin-list">
    <?php foreach ($rows as $row): ?>
      <?php $ok = (int)$row['success'] === 1; ?>
      <li class="admin-row">
        <span class="admin-row__icon <?= $ok ? 'is-ok' : 'is-fail' ?>"><?= icon($ok ? 'circle-check' : 'x') ?></span>
        <span class="admin-row__text">
          <span class="admin-row__title"><?= $ok ? 'Entrou' : 'Senha ou usuário errado' ?> · <?= e($row['username_tried']) ?></span>
          <span class="admin-row__sub"><?= e(format_datetime($row['created_at'])) ?> · <?= e($row['ip']) ?> · <?= e(short_agent($row['user_agent'])) ?></span>
        </span>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<?php require APP_INCLUDES . '/admin_footer.php'; ?>
