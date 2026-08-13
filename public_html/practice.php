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

$pageTitle = 'Praticar — VoxlyOne';
require APP_INCLUDES . '/header.php';
?>

<div class="page-head">
  <h1>Praticar</h1>
  <a class="btn btn--sm btn--ghost" href="/dashboard.php">Sair da prática</a>
</div>

<section class="practice">
  <p class="practice__en"><?= e($phrase['text_en']) ?></p>

  <?php if ($phrase['phonetic_guide'] !== null): ?>
    <p class="practice__phonetic"><?= e($phrase['phonetic_guide']) ?></p>
  <?php endif; ?>

  <?php if ($phrase['text_pt'] !== null): ?>
    <p class="practice__pt"><?= e($phrase['text_pt']) ?></p>
  <?php endif; ?>

  <div class="speeds" role="group" aria-label="Velocidade da pronúncia">
    <button class="speed" type="button" data-speed="slow"   aria-pressed="false">Devagar</button>
    <button class="speed" type="button" data-speed="normal" aria-pressed="true">Normal</button>
    <button class="speed" type="button" data-speed="fast"   aria-pressed="false">Rápido</button>
  </div>

  <button class="btn btn--block" type="button" id="btn-play">🔊 Ouvir pronúncia</button>

  <div class="recorder">
    <div class="recorder__wave" id="rec-wave" aria-hidden="true"></div>
    <span class="recorder__timer" id="rec-timer">0.0s</span>
  </div>

  <button class="btn btn--block btn--record" type="button" id="btn-record" disabled>
    🎤 Gravar
  </button>
  <p class="practice__hint">Ouça a pronúncia ao menos uma vez para liberar a gravação.</p>

  <p class="form__error" id="practice-error" role="alert" hidden></p>

  <div class="preview" id="preview-box" hidden>
    <audio id="preview" controls></audio>
    <div class="preview__actions">
      <button class="btn btn--ghost" type="button" id="btn-again">Regravar</button>
      <button class="btn" type="button" id="btn-analyze">Analisar pronúncia</button>
    </div>
  </div>

  <div class="loading" id="loading" hidden>
    <span class="loading__dot"></span>
    <span class="loading__dot"></span>
    <span class="loading__dot"></span>
    <p>Ouvindo sua pronúncia…</p>
  </div>

  <div class="feedback" id="feedback" hidden></div>
</section>

<div class="modal" id="consent-modal" hidden>
  <div class="modal__box" role="dialog" aria-modal="true" aria-labelledby="consent-title">
    <h2 id="consent-title">Sobre sua gravação</h2>
    <p>
      Para avaliar sua pronúncia, o áudio é enviado a um serviço de inteligência
      artificial (OpenAI) e <strong>descartado logo em seguida</strong>. Nada de
      áudio é guardado nos nossos servidores — só a nota e o texto do feedback.
    </p>
    <p>
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
  consented: <?= $user['consented_at'] !== null ? 'true' : 'false' ?>,
  attempts: <?= (int)$phrase['attempts_count'] ?>
};
</script>
<script type="module" src="/assets/js/practice.js"></script>

<?php require APP_INCLUDES . '/footer.php'; ?>
