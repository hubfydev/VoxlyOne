<?php
declare(strict_types=1);

require_once __DIR__ . '/boot.php';

// Já logado vai direto para o app
if (current_user() !== null) {
    redirect('/dashboard.php');
}

// Conta bloqueada: pelo login recusado (?erro=bloqueado) ou pela sessão derrubada
$blocked    = ($_GET['erro'] ?? '') === 'bloqueado' || session_was_blocked();
unset($_SESSION['blocked_notice']);
$loginError = isset($_GET['erro']) && !$blocked;
$pageTitle  = 'VoxlyOne — treine sua pronúncia em inglês';
$pageStyles = ['landing'];
$bodyClass  = 'is-landing';
$hideNav    = true;

require APP_INCLUDES . '/header.php';
?>
<section class="landing">
  <div class="landing__intro">
    <a class="brand landing__brand" href="/" aria-label="VoxlyOne — início">
      <span class="brand__mark"><?= icon('audio-lines') ?></span>
      <span>Voxly<span class="brand__one">One</span></span>
    </a>

    <p class="landing__eyebrow"><?= icon('sparkles') ?> Treino de pronúncia com IA</p>

    <h1 class="landing__title">
      Fale inglês com <span class="landing__accent">confiança</span>
    </h1>

    <p class="landing__tagline">
      Cadastre as frases que você usa, ouça a pronúncia certa, grave sua voz e
      receba uma nota de 0 a 10 com dicas em português.
    </p>
  </div>

  <!-- Prévia ilustrativa da tela de prática, feita só com CSS -->
  <div class="mock" aria-hidden="true">
    <div class="mock__card">
      <div class="mock__top">
        <span class="mock__chip">Viagem</span>
        <span class="mock__listen"><?= icon('volume-2') ?></span>
      </div>
      <p class="mock__en">Where is the baggage claim?</p>
      <p class="mock__pt">Onde fica a esteira de bagagem?</p>
      <p class="mock__phonetic"><?= icon('ear') ?> uér iz dê BÉ-guidj KLEIM</p>

      <div class="mock__result">
        <span class="mock__ring"><span>9,2</span></span>
        <span class="mock__feedback">
          <strong>Muito bom!</strong>
          <span>Alongue o “ei” de <em>claim</em>.</span>
        </span>
      </div>
    </div>

    <span class="mock__mic"><?= icon('mic') ?></span>
    <span class="mock__wave">
      <i></i><i></i><i></i><i></i><i></i><i></i><i></i>
    </span>
  </div>

  <div class="landing__cta">
    <?php if ($blocked): ?>
      <p class="alert" role="alert">
        <?= icon('lock') ?>
        <span>Sua conta está bloqueada. Se acha que é um engano, escreva para
          <a href="mailto:us@hubfy.us">us@hubfy.us</a>.</span>
      </p>
    <?php endif; ?>
    <?php if ($loginError): ?>
      <p class="alert" role="alert">
        <?= icon('triangle-alert') ?>
        <span>Não foi possível entrar. Tente novamente.</span>
      </p>
    <?php endif; ?>

    <a class="btn btn--lg btn--block btn--google" href="/auth/login.php">
      <span class="btn--google__logo">
        <!-- "G" do Google, desenhado inline (cores oficiais da marca) -->
        <svg viewBox="0 0 48 48" width="22" height="22" aria-hidden="true" focusable="false">
          <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
          <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
          <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
          <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
        </svg>
      </span>
      Entrar com Google
    </a>

    <p class="landing__legal">
      Ao entrar, você concorda com os <a href="/terms.php">Termos de uso</a>
      e a <a href="/privacy.php">Política de privacidade</a>.
    </p>
  </div>

  <ol class="steps">
    <li class="step">
      <span class="step__icon"><?= icon('volume-2') ?></span>
      <span class="step__text">
        <strong>Ouça</strong>
        <span>A pronúncia certa em três velocidades.</span>
      </span>
    </li>
    <li class="step">
      <span class="step__icon"><?= icon('mic') ?></span>
      <span class="step__text">
        <strong>Grave</strong>
        <span>Sua voz, no celular ou no computador.</span>
      </span>
    </li>
    <li class="step">
      <span class="step__icon"><?= icon('target') ?></span>
      <span class="step__text">
        <strong>Receba sua nota</strong>
        <span>De 0 a 10, com dicas em português. Avance com 8.</span>
      </span>
    </li>
  </ol>
</section>
<?php require APP_INCLUDES . '/footer.php'; ?>
