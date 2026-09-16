<?php
declare(strict_types=1);

/**
 * Cabeçalho do painel administrativo — mesmo design system do app (app.css),
 * com navegação própria. Espera:
 *   $pageTitle (string), $adminTab (string), $admin (?array)
 */
$pageTitle = $pageTitle ?? 'Painel — VoxlyOne';
$adminTab  = $adminTab ?? '';
$admin     = $admin ?? null;

$adminTabs = [
    'dashboard' => ['/admin/dashboard.php', 'layout-dashboard', 'Painel'],
    'users'     => ['/admin/users.php', 'users', 'Usuários'],
    'costs'     => ['/admin/costs.php', 'circle-dollar-sign', 'Custos'],
    'access'    => ['/admin/access.php', 'history', 'Acessos'],
    'account'   => ['/admin/account.php', 'settings', 'Conta'],
];

$favicon = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E"
         . "%3Cdefs%3E%3ClinearGradient id='g' x1='0' y1='0' x2='1' y2='1'%3E%3Cstop offset='0' stop-color='%236a55ff'/%3E"
         . "%3Cstop offset='1' stop-color='%23c0458f'/%3E%3C/linearGradient%3E%3C/defs%3E"
         . "%3Crect width='32' height='32' rx='9' fill='url(%23g)'/%3E"
         . "%3Cpath d='M9 13v6M13 10v12M17 8v16M21 11v10M25 14v4' stroke='white' stroke-width='2.4' stroke-linecap='round'/%3E%3C/svg%3E";
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#f5f4fa" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0e0d16" media="(prefers-color-scheme: dark)">
<title><?= e($pageTitle) ?></title>
<link rel="icon" href="<?= $favicon ?>">
<link rel="preload" href="/assets/fonts/plus-jakarta-sans.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('/assets/css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('/assets/css/pages/admin.css')) ?>">
</head>
<body class="<?= $admin !== null ? 'has-tabbar ' : '' ?>is-admin">
<?php if ($admin !== null): ?>
<header class="topbar">
  <a class="brand" href="/admin/dashboard.php" aria-label="VoxlyOne Admin — painel">
    <span class="brand__mark"><?= icon('audio-lines') ?></span>
    <span>Voxly<span class="brand__one">One</span></span>
    <span class="admin-badge">Admin</span>
  </a>
  <form method="post" action="/admin/logout.php">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <button class="btn btn--sm btn--ghost btn--icon" type="submit" aria-label="Sair do painel" title="Sair do painel">
      <?= icon('log-out') ?>
    </button>
  </form>
</header>
<?php endif; ?>
<main class="main admin-main">
<?php if ($admin !== null && admin_uses_default_password($admin) && $adminTab !== 'account'): ?>
  <a class="admin-warning" href="/admin/account.php">
    <?= icon('triangle-alert') ?>
    <span><strong>Você ainda usa a senha inicial.</strong> Troque agora para proteger o painel.</span>
    <?= icon('chevron-right') ?>
  </a>
<?php endif; ?>
<?php foreach (admin_take_flashes() as $flash): ?>
  <p class="admin-flash admin-flash--<?= e($flash['type']) ?>" role="<?= $flash['type'] === 'error' ? 'alert' : 'status' ?>">
    <?= icon($flash['type'] === 'error' ? 'triangle-alert' : 'circle-check') ?>
    <span><?= e($flash['message']) ?></span>
  </p>
<?php endforeach; ?>
