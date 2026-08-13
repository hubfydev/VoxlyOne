<?php
declare(strict_types=1);

/**
 * Cabeçalho comum. Espera as variáveis opcionais:
 *   $pageTitle (string) e $hideNav (bool)
 */
$pageTitle = $pageTitle ?? 'VoxlyOne';
$hideNav   = $hideNav ?? false;
$navUser   = current_user();
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#1d4ed8">
<title><?= e($pageTitle) ?></title>
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php if (!$hideNav && $navUser !== null): ?>
<nav class="nav">
  <a class="nav__brand" href="/dashboard.php">VoxlyOne</a>
  <div class="nav__links">
    <a href="/dashboard.php">Frases</a>
    <a href="/mastered.php">Conquistas</a>
    <a href="/profile.php">
      <?php if (!empty($navUser['avatar_url'])): ?>
        <img class="nav__avatar" src="<?= e($navUser['avatar_url']) ?>" alt="" width="28" height="28">
      <?php else: ?>
        Perfil
      <?php endif; ?>
    </a>
  </div>
</nav>
<?php endif; ?>
<main class="main">
