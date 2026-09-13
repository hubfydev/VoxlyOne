<?php
declare(strict_types=1);

require_once __DIR__ . '/boot.php';
require_once APP_INCLUDES . '/playlists.php';

$user   = require_auth();
$userId = (int)$user['id'];

$playlists = list_playlists($userId);

/** Inicial do nome para a "capa" gerada da playlist. */
function playlist_initial(string $name): string
{
    $name = trim($name);
    return $name === '' ? '♪' : mb_strtoupper(mb_substr($name, 0, 1));
}

$pageTitle = 'Playlists — VoxlyOne';
$pageStyles = ['playlists'];
require APP_INCLUDES . '/header.php';
?>

<div class="page-head">
  <div>
    <h1>Playlists</h1>
    <p class="page-head__sub">A sua voz, em repetição</p>
  </div>
  <button class="btn btn--sm pl-new-toggle" type="button" id="btn-new-playlist" aria-expanded="false"
          aria-controls="new-playlist">
    <?= icon('plus') ?>Nova playlist
  </button>
</div>

<div class="pl-intro">
  <span class="pl-intro__icon"><?= icon('headphones') ?></span>
  <p>
    Ouça <strong>a sua própria voz</strong> nas frases em que tirou mais de 8.
    Repetir o próprio acerto ajuda a fixar o som certo.
  </p>
</div>

<form class="form pl-new" id="new-playlist" hidden novalidate>
  <div class="pl-new__head">
    <span class="pl-new__icon"><?= icon('list-plus') ?></span>
    <div>
      <h2 class="pl-new__title">Nova playlist</h2>
      <p class="pl-new__sub">Depois é só adicionar suas gravações aprovadas.</p>
    </div>
  </div>
  <label class="field">
    <span class="field__label">Nome</span>
    <input type="text" name="name" maxlength="80" required placeholder="Ex.: Trabalho, Viagem, Daily English">
  </label>
  <label class="field">
    <span class="field__label">Descrição <em>(opcional)</em></span>
    <textarea name="description" rows="2" maxlength="300" placeholder="Para que serve esta playlist?"></textarea>
  </label>
  <p class="form__error" id="new-playlist-error" role="alert" hidden></p>
  <button class="btn btn--block" type="submit">Criar playlist</button>
</form>

<?php if ($playlists === []): ?>
  <div class="empty pl-empty-list">
    <span class="empty__icon"><?= icon('list-music') ?></span>
    <p class="empty__title">Nenhuma playlist ainda</p>
    <p class="empty__text">
      Junte as frases em que você tirou mais de 8 e ouça a sua voz acertando.
      Dá para adicionar logo depois da nota ou pelo botão <strong>+ Playlist</strong>
      na lista de frases.
    </p>
    <button class="btn" type="button" data-new-playlist><?= icon('plus') ?>Criar playlist</button>
  </div>
<?php else: ?>
  <p class="list-count"><?= count($playlists) ?> playlist<?= count($playlists) === 1 ? '' : 's' ?></p>
  <ul class="pl-cards">
    <?php foreach ($playlists as $playlist): ?>
      <?php $count = (int)$playlist['items_count']; ?>
      <li>
        <a class="playlist-card" href="/playlist.php?id=<?= (int)$playlist['id'] ?>">
          <span class="pl-cover pl-cover--<?= (int)$playlist['id'] % 6 ?>" aria-hidden="true">
            <span class="pl-cover__letter"><?= e(playlist_initial($playlist['name'])) ?></span>
          </span>
          <span class="playlist-card__body">
            <span class="playlist-card__name"><?= e($playlist['name']) ?></span>
            <?php if ($playlist['description'] !== null): ?>
              <span class="playlist-card__desc"><?= e($playlist['description']) ?></span>
            <?php endif; ?>
            <span class="playlist-card__meta">
              <span class="playlist-card__count"><?= $count ?> frase<?= $count === 1 ? '' : 's' ?></span>
              <?php if ($playlist['shuffle_enabled']): ?>
                <span class="playlist-card__mode" title="Aleatório ligado"><?= icon('shuffle') ?><span class="sr-only">Aleatório ligado</span></span>
              <?php endif; ?>
              <?php if ($playlist['repeat_enabled']): ?>
                <span class="playlist-card__mode" title="Repetir ligado"><?= icon('repeat') ?><span class="sr-only">Repetir ligado</span></span>
              <?php endif; ?>
            </span>
            <span class="playlist-card__date">Alterada em <?= e(format_date($playlist['updated_at'])) ?></span>
          </span>
          <span class="playlist-card__go" aria-hidden="true"><?= icon('chevron-right') ?></span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<script>window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;</script>
<script type="module" src="<?= e(asset('/assets/js/playlists.js')) ?>"></script>

<?php require APP_INCLUDES . '/footer.php'; ?>
