<?php
declare(strict_types=1);

require_once __DIR__ . '/boot.php';

// Já logado vai direto para o app
if (current_user() !== null) {
    redirect('/dashboard.php');
}

$loginError = isset($_GET['erro']);
$pageTitle  = 'VoxlyOne — treine sua pronúncia em inglês';
$hideNav    = true;

require APP_INCLUDES . '/header.php';
?>
<section class="landing">
  <h1 class="landing__title">VoxlyOne</h1>
  <p class="landing__tagline">
    Treine a pronúncia em inglês com feedback de IA. Cadastre suas frases,
    ouça, grave sua voz e receba uma nota de 0 a 10 com dicas em português.
  </p>

  <?php if ($loginError): ?>
    <p class="alert" role="alert">
      Não foi possível entrar. Tente novamente.
    </p>
  <?php endif; ?>

  <a class="btn btn--google" href="/auth/login.php">Entrar com Google</a>

  <p class="landing__legal">
    Ao entrar, você concorda com os <a href="/terms.php">Termos de uso</a>
    e a <a href="/privacy.php">Política de privacidade</a>.
  </p>
</section>
<?php require APP_INCLUDES . '/footer.php'; ?>
