<?php
declare(strict_types=1);

/**
 * Cabeçalho comum. Espera as variáveis opcionais:
 *   $pageTitle  (string)   título da aba
 *   $hideNav    (bool)     sem barra superior nem abas (landing, telas públicas)
 *   $pageStyles (string[]) CSS da tela em assets/css/pages/{nome}.css
 *   $bodyClass  (string)   classe extra no <body>
 */
$pageTitle  = $pageTitle ?? 'VoxlyOne';
$hideNav    = $hideNav ?? false;
$pageStyles = $pageStyles ?? [];
$navUser    = current_user();
$showNav    = !$hideNav && $navUser !== null;

// Aba ativa pelo script em execução
$currentScript = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$activeTab = match ($currentScript) {
    'dashboard.php', 'phrase_form.php' => 'phrases',
    'practice.php'                     => 'practice',
    'playlists.php', 'playlist.php'    => 'playlists',
    'mastered.php'                     => 'mastered',
    'profile.php'                      => 'profile',
    default                            => '',
};

$tabs = [
    'phrases'   => ['/dashboard.php', 'book-open-text', 'Frases'],
    'playlists' => ['/playlists.php', 'list-music', 'Playlists'],
    'practice'  => ['/practice.php', 'mic', 'Praticar'],
    'mastered'  => ['/mastered.php', 'trophy', 'Conquistas'],
    'profile'   => ['/profile.php', 'user-round', 'Perfil'],
];

$bodyClasses = trim(($showNav ? 'has-tabbar ' : '') . ($bodyClass ?? ''));
$initial = $navUser !== null ? mb_strtoupper(mb_substr(trim((string)$navUser['name']), 0, 1)) : '';

// Favicon inline: nada de requisição extra nem 404 de favicon.ico
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
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#f5f4fa" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0e0d16" media="(prefers-color-scheme: dark)">
<meta name="apple-mobile-web-app-title" content="VoxlyOne">
<title><?= e($pageTitle) ?></title>
<link rel="icon" href="<?= $favicon ?>">
<link rel="preload" href="/assets/fonts/plus-jakarta-sans.woff2" as="font" type="font/woff2" crossorigin>
<script type="importmap"><?= asset_import_map() ?></script>
<link rel="stylesheet" href="<?= e(asset('/assets/css/app.css')) ?>">
<?php if ($showNav): ?>
<link rel="stylesheet" href="<?= e(asset('/assets/css/picker.css')) ?>">
<?php endif; ?>
<?php foreach ($pageStyles as $style): ?>
<link rel="stylesheet" href="<?= e(asset('/assets/css/pages/' . preg_replace('/[^a-z0-9-]/', '', (string)$style) . '.css')) ?>">
<?php endforeach; ?>
</head>
<body<?= $bodyClasses !== '' ? ' class="' . e($bodyClasses) . '"' : '' ?>>
<?php if ($showNav): ?>
<header class="topbar">
  <a class="brand" href="/dashboard.php" aria-label="VoxlyOne — início">
    <span class="brand__mark"><?= icon('audio-lines') ?></span>
    <span>Voxly<span class="brand__one">One</span></span>
  </a>
  <a class="topbar__avatar" href="/profile.php" aria-label="Perfil">
    <?php if (!empty($navUser['avatar_url'])): ?>
      <img src="<?= e($navUser['avatar_url']) ?>" alt="" width="34" height="34" referrerpolicy="no-referrer">
    <?php else: ?>
      <span class="topbar__initial"><?= e($initial) ?></span>
    <?php endif; ?>
  </a>
</header>
<?php endif; ?>
<main class="main">
