<?php
declare(strict_types=1);

require_once __DIR__ . '/boot.php';
require_once APP_INCLUDES . '/playlists.php';

$user   = require_auth();
$userId = (int)$user['id'];

$playlists = list_playlists($userId);

$pageTitle = 'Playlists — VoxlyOne';
require APP_INCLUDES . '/header.php';
?>

<div class="page-head">
  <h1>Playlists</h1>
  <button class="btn btn--sm" type="button" id="btn-new-playlist" aria-expanded="false"
          aria-controls="new-playlist">+ Nova playlist</button>
</div>

<p class="page-intro">
  Ouça <strong>a sua própria voz</strong> nas frases em que tirou mais de 8.
  Repetir o próprio acerto ajuda a fixar o som certo.
</p>

<form class="form card" id="new-playlist" hidden novalidate>
  <label class="field">
    <span class="field__label">Nome</span>
    <input type="text" name="name" maxlength="80" required placeholder="Ex.: Trabalho, Viagem, Daily English">
  </label>
  <label class="field">
    <span class="field__label">Descrição <em>(opcional)</em></span>
    <textarea name="description" rows="2" maxlength="300"></textarea>
  </label>
  <p class="form__error" id="new-playlist-error" role="alert" hidden></p>
  <button class="btn btn--block" type="submit">Criar playlist</button>
</form>

<?php if ($playlists === []): ?>
  <div class="empty">
    <p class="empty__title">Nenhuma playlist ainda.</p>
    <p class="empty__text">
      Crie uma playlist e adicione as frases em que você tirou mais de 8 — logo
      depois da nota, ou pelo botão <strong>+ Playlist</strong> na lista de frases.
    </p>
  </div>
<?php else: ?>
  <ul class="cards">
    <?php foreach ($playlists as $playlist): ?>
      <li class="card">
        <a class="playlist-card" href="/playlist.php?id=<?= $playlist['id'] ?>">
          <span class="playlist-card__name"><?= e($playlist['name']) ?></span>
          <?php if ($playlist['description'] !== null): ?>
            <span class="playlist-card__desc"><?= e($playlist['description']) ?></span>
          <?php endif; ?>
          <span class="card__meta">
            <span><?= $playlist['items_count'] ?> frase<?= $playlist['items_count'] === 1 ? '' : 's' ?></span>
            <?php if ($playlist['shuffle_enabled']): ?><span>🔀 Aleatório</span><?php endif; ?>
            <?php if ($playlist['repeat_enabled']): ?><span>🔁 Repetir</span><?php endif; ?>
            <span>Alterada em <?= e(format_date($playlist['updated_at'])) ?></span>
          </span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<script>window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;</script>
<script type="module" src="/assets/js/playlists.js"></script>

<?php require APP_INCLUDES . '/footer.php'; ?>
