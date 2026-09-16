<?php
declare(strict_types=1);

require_once __DIR__ . '/boot.php';
require_once APP_INCLUDES . '/phrases.php';
require_once APP_INCLUDES . '/tts.php';

$user   = require_auth();
$userId = (int)$user['id'];

$stmt = db()->prepare(
    "SELECT p.*, c.name AS category_name
       FROM phrases p
       LEFT JOIN categories c ON c.id = p.category_id
      WHERE p.user_id = ? AND p.status = 'mastered'
      ORDER BY p.mastered_at DESC"
);
$stmt->execute([$userId]);
$phrases = $stmt->fetchAll();
$total   = count($phrases);

$pageTitle = 'Conquistas — VoxlyOne';
$pageStyles = ['practice'];
require APP_INCLUDES . '/header.php';
?>

<section class="trophy-hero">
  <span class="trophy-hero__icon"><?= icon('trophy') ?></span>
  <div class="trophy-hero__text">
    <h1>Conquistas</h1>
    <p class="trophy-hero__sub">Suas frases com nota&nbsp;10.</p>
  </div>
  <p class="trophy-hero__count">
    <strong><?= $total ?></strong>
    <span>dominada<?= $total === 1 ? '' : 's' ?></span>
  </p>
</section>

<?php if ($phrases === []): ?>
  <div class="empty">
    <span class="empty__icon trophy-empty__icon"><?= icon('crown') ?></span>
    <p class="empty__title">Nenhuma frase dominada ainda.</p>
    <p class="empty__text">
      A frase entra aqui quando você tira <strong>10</strong> na pronúncia.
      Com 8 ou mais você já avança para a próxima — o 10 é o selo de ouro.
    </p>
    <a class="btn" href="/practice.php"><?= icon('mic') ?>Praticar agora</a>
  </div>
<?php else: ?>

  <div class="listen-bar">
    <div class="speeds" role="group" aria-label="Velocidade da pronúncia">
      <button class="speed" type="button" data-speed="slow"   aria-pressed="false"><?= icon('turtle') ?>Devagar</button>
      <button class="speed" type="button" data-speed="normal" aria-pressed="true"><?= icon('gauge') ?>Normal</button>
      <button class="speed" type="button" data-speed="fast"   aria-pressed="false"><?= icon('rabbit') ?>Rápido</button>
    </div>

    <div class="speeds voices" role="group" aria-label="Voz da pronúncia">
      <button class="speed" type="button" data-voice="female" aria-pressed="true">Voz feminina</button>
      <button class="speed" type="button" data-voice="male"   aria-pressed="false">Voz masculina</button>
    </div>
  </div>

  <ul class="cards trophies">
    <?php foreach ($phrases as $phrase): ?>
      <li class="card trophy">
        <div class="trophy__head">
          <span class="trophy__seal"><?= icon('award') ?></span>
          <span class="badge badge--mastered">Dominada</span>
          <span class="trophy__date"><?= e(format_date($phrase['mastered_at'])) ?></span>
        </div>

        <p class="trophy__en" lang="en"><?= e($phrase['text_en']) ?></p>

        <?php if ($phrase['phonetic_guide'] !== null): ?>
          <p class="trophy__phonetic"><?= e($phrase['phonetic_guide']) ?></p>
        <?php endif; ?>

        <?php if ($phrase['text_pt'] !== null): ?>
          <p class="trophy__pt"><?= e($phrase['text_pt']) ?></p>
        <?php endif; ?>

        <div class="card__meta trophy__meta">
          <?php if ($phrase['category_name'] !== null): ?>
            <span><?= e($phrase['category_name']) ?></span>
          <?php endif; ?>
          <span><?= e(match ($phrase['level']) {
              'beginner'     => 'Iniciante',
              'intermediate' => 'Intermediário',
              default        => 'Avançado',
          }) ?></span>
          <span><?= (int)$phrase['attempts_count'] ?> tentativa<?= (int)$phrase['attempts_count'] === 1 ? '' : 's' ?></span>
        </div>

        <div class="trophy__actions">
          <button class="btn btn--sm trophy__play" type="button"
                  data-play="<?= e($phrase['text_en']) ?>"
                  data-tts-female="<?= e(tts_url($phrase, 'female')) ?>"
                  data-tts-male="<?= e(tts_url($phrase, 'male')) ?>"><?= icon('volume-2') ?>Ouvir</button>
          <a class="btn btn--sm btn--ghost" href="/practice.php?id=<?= (int)$phrase['id'] ?>"><?= icon('rotate-ccw') ?>Praticar</a>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<script type="module" src="<?= e(asset('/assets/js/mastered.js')) ?>"></script>

<?php require APP_INCLUDES . '/footer.php'; ?>
