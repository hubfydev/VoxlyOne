<?php
declare(strict_types=1);

require_once __DIR__ . '/boot.php';
require_once APP_INCLUDES . '/phrases.php';

$user   = require_auth();
$userId = (int)$user['id'];

// Sem id explícito, pega a próxima da fila (RF-10)
$id     = (int)($_GET['id'] ?? 0);
$phrase = $id > 0 ? find_phrase($userId, $id) : null;

if ($phrase === null) {
    $nextId = next_phrase_id($userId);
    if ($nextId === null) {
        // Nada pendente: ou não há frases, ou está tudo dominado
        redirect('/dashboard.php?tudo=ok');
    }
    $phrase = find_phrase($userId, $nextId);
}

$attempts  = (int)$phrase['attempts_count'];
$bestScore = $phrase['best_score'] !== null
    ? number_format((float)$phrase['best_score'], 1, ',', '')
    : null;
$levelLabel = match ($phrase['level']) {
    'beginner'     => 'Iniciante',
    'intermediate' => 'Intermediário',
    default        => 'Avançado',
};

$pageTitle = 'Praticar — VoxlyOne';
$pageStyles = ['practice'];
require APP_INCLUDES . '/header.php';
?>

<h1 class="sr-only">Praticar</h1>

<section class="practice">

  <!-- Barra da sessão: etapas do fluxo + saída. O estado das etapas vem do CSS (:has) -->
  <div class="practice-bar">
    <ol class="steps" aria-label="Etapas da prática">
      <li class="steps__item steps__item--listen"><span class="steps__dot"><span>1</span><?= icon('check') ?></span>Ouça</li>
      <li class="steps__line" aria-hidden="true"></li>
      <li class="steps__item steps__item--record"><span class="steps__dot"><span>2</span><?= icon('check') ?></span>Grave</li>
      <li class="steps__line" aria-hidden="true"></li>
      <li class="steps__item steps__item--score"><span class="steps__dot"><span>3</span><?= icon('check') ?></span>Nota</li>
    </ol>
    <a class="btn btn--icon btn--ghost practice-bar__exit" href="/dashboard.php" aria-label="Sair da prática">
      <?= icon('x') ?>
    </a>
  </div>

  <!-- Cartão-herói: a frase, a fonética (método de estudo) e os controles de escuta -->
  <article class="phrase-hero">
    <div class="phrase-hero__meta">
      <span class="badge badge--<?= e(phrase_status_slug($phrase)) ?>"><?= e(phrase_status_label($phrase)) ?></span>
      <span class="phrase-hero__stat">
        <?php if ($bestScore !== null): ?>
          Melhor nota <strong><?= e($bestScore) ?></strong>
        <?php else: ?>
          <?= e($levelLabel) ?><?= $phrase['category_name'] !== null ? ' · ' . e($phrase['category_name']) : '' ?>
        <?php endif; ?>
      </span>
    </div>

    <p class="practice__en" lang="en"><?= e($phrase['text_en']) ?></p>

    <?php if ($phrase['phonetic_guide'] !== null): ?>
      <div class="phonetic">
        <span class="phonetic__label"><?= icon('languages') ?>Como se fala</span>
        <p class="practice__phonetic"><?= e($phrase['phonetic_guide']) ?></p>
      </div>
    <?php endif; ?>

    <?php if ($phrase['text_pt'] !== null): ?>
      <p class="practice__pt"><?= e($phrase['text_pt']) ?></p>
    <?php endif; ?>

    <div class="listen">
      <div class="speeds listen__speeds" role="group" aria-label="Velocidade da pronúncia">
        <button class="speed" type="button" data-speed="slow"   aria-pressed="false"><?= icon('turtle') ?>Devagar</button>
        <button class="speed" type="button" data-speed="normal" aria-pressed="true"><?= icon('gauge') ?>Normal</button>
        <button class="speed" type="button" data-speed="fast"   aria-pressed="false"><?= icon('rabbit') ?>Rápido</button>
      </div>

      <div class="speeds voices" role="group" aria-label="Voz da pronúncia">
        <button class="speed" type="button" data-voice="female" aria-pressed="true">Voz feminina</button>
        <button class="speed" type="button" data-voice="male"   aria-pressed="false">Voz masculina</button>
      </div>

      <button class="btn btn--lg btn--block listen__play" type="button" id="btn-play">
        <?= icon('volume-2') ?>Ouvir pronúncia
      </button>
    </div>
  </article>

  <!-- Estúdio de gravação: a onda fica atrás do botão e reage ao nível do microfone -->
  <div class="studio">
    <div class="recorder">
      <div class="recorder__wave" id="rec-wave" aria-hidden="true">
        <?= str_repeat('<span></span>', 22) ?>
      </div>

      <button class="rec-btn" type="button" id="btn-record" disabled><?= icon('mic', 'rec-btn__icon') ?><span class="rec-btn__label">Gravar</span></button>
    </div>

    <p class="recorder__status">
      <span class="recorder__dot" aria-hidden="true"></span>
      <span class="recorder__timer" id="rec-timer">0.0s</span>
    </p>

    <p class="practice__hint"><?= icon('ear') ?><span>Ouça a pronúncia ao menos uma vez para liberar a gravação.</span></p>
    <p class="studio__hint studio__hint--ready">Toque no microfone e fale a frase em inglês.</p>
    <p class="studio__hint studio__hint--rec">Gravando… toque em Parar quando terminar.</p>
  </div>

  <p class="form__error practice__error" id="practice-error" role="alert" hidden></p>

  <div class="preview" id="preview-box" hidden>
    <p class="preview__title"><?= icon('headphones') ?>Sua gravação</p>
    <audio id="preview" controls></audio>
    <div class="preview__actions">
      <button class="btn btn--ghost" type="button" id="btn-again"><?= icon('rotate-ccw') ?>Regravar</button>
      <button class="btn" type="button" id="btn-analyze"><?= icon('sparkles') ?>Analisar</button>
    </div>
  </div>

  <div class="loading analyzing" id="loading" hidden>
    <span class="analyzing__icon"><?= icon('ear') ?></span>
    <div>
      <span class="loading__dot"></span>
      <span class="loading__dot"></span>
      <span class="loading__dot"></span>
    </div>
    <p>Ouvindo sua pronúncia…</p>
  </div>

  <div class="feedback" id="feedback" hidden aria-live="polite"></div>
</section>

<div class="modal" id="consent-modal" hidden>
  <div class="modal__box consent" role="dialog" aria-modal="true" aria-labelledby="consent-title">
    <span class="consent__icon"><?= icon('shield-check') ?></span>
    <h2 id="consent-title">Sobre sua gravação</h2>

    <ul class="consent__list">
      <li>
        <span class="consent__bullet"><?= icon('sparkles') ?></span>
        <p>
          Para avaliar sua pronúncia, o áudio é enviado a um serviço de inteligência
          artificial (OpenAI).
        </p>
      </li>
      <li>
        <span class="consent__bullet"><?= icon('trash-2') ?></span>
        <p>
          Gravações com nota <strong>até 8</strong> são
          <strong>descartadas logo em seguida</strong> — delas guardamos só a nota e o
          feedback.
        </p>
      </li>
      <li>
        <span class="consent__bullet"><?= icon('lock') ?></span>
        <p>
          Quando a nota passa de 8, guardamos <strong>a última gravação aprovada</strong>
          de cada frase para você ouvir a própria voz nas suas playlists. Só você tem
          acesso a ela, e ela é apagada quando você exclui a frase ou a conta.
        </p>
      </li>
    </ul>

    <p class="consent__more">
      Detalhes na <a href="/privacy.php" target="_blank" rel="noopener">Política de privacidade</a>.
    </p>
    <div class="modal__actions">
      <button class="btn btn--ghost" type="button" id="consent-cancel">Agora não</button>
      <button class="btn" type="button" id="consent-accept">Aceitar e gravar</button>
    </div>
  </div>
</div>

<script>
window.PRACTICE = {
  phraseId: <?= (int)$phrase['id'] ?>,
  textEn: <?= json_encode($phrase['text_en'], JSON_UNESCAPED_UNICODE) ?>,
  csrf: <?= json_encode(csrf_token()) ?>,
  consented: <?= has_current_consent($user) ? 'true' : 'false' ?>,
  attempts: <?= $attempts ?>
};
</script>
<script type="module" src="<?= e(asset('/assets/js/practice.js')) ?>"></script>

<?php require APP_INCLUDES . '/footer.php'; ?>
