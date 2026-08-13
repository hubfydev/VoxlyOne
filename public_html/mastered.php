<?php
declare(strict_types=1);

require_once __DIR__ . '/boot.php';
require_once APP_INCLUDES . '/phrases.php';

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

$pageTitle = 'Conquistas — VoxlyOne';
require APP_INCLUDES . '/header.php';
?>

<div class="page-head">
  <h1>Conquistas</h1>
  <span class="list-count"><?= count($phrases) ?> dominada<?= count($phrases) === 1 ? '' : 's' ?></span>
</div>

<?php if ($phrases === []): ?>
  <div class="empty">
    <p class="empty__title">Nenhuma frase dominada ainda.</p>
    <p class="empty__text">
      A frase entra aqui quando você tira <strong>10</strong> na pronúncia.
      Com 8 ou mais você já avança para a próxima — o 10 é o selo de ouro.
    </p>
    <a class="btn" href="/practice.php">Praticar agora</a>
  </div>
<?php else: ?>

  <div class="speeds" role="group" aria-label="Velocidade da pronúncia">
    <button class="speed" type="button" data-speed="slow"   aria-pressed="false">Devagar</button>
    <button class="speed" type="button" data-speed="normal" aria-pressed="true">Normal</button>
    <button class="speed" type="button" data-speed="fast"   aria-pressed="false">Rápido</button>
  </div>

  <ul class="cards">
    <?php foreach ($phrases as $phrase): ?>
      <li class="card">
        <div class="card__head">
          <span class="badge badge--mastered">Dominada</span>
          <span class="card__score">
            <?= e(date('d/m/Y', strtotime((string)$phrase['mastered_at']))) ?>
          </span>
        </div>

        <p class="card__en"><?= e($phrase['text_en']) ?></p>

        <?php if ($phrase['text_pt'] !== null): ?>
          <p class="card__pt"><?= e($phrase['text_pt']) ?></p>
        <?php endif; ?>

        <?php if ($phrase['phonetic_guide'] !== null): ?>
          <p class="card__phonetic"><?= e($phrase['phonetic_guide']) ?></p>
        <?php endif; ?>

        <div class="card__meta">
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

        <div class="card__actions">
          <button class="btn btn--sm" type="button"
                  data-play="<?= e($phrase['text_en']) ?>">🔊 Ouvir</button>
          <a class="btn btn--sm btn--ghost" href="/practice.php?id=<?= (int)$phrase['id'] ?>">Praticar de novo</a>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<script type="module" src="/assets/js/mastered.js"></script>

<?php require APP_INCLUDES . '/footer.php'; ?>
