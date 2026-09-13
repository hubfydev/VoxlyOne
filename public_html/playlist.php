<?php
declare(strict_types=1);

require_once __DIR__ . '/boot.php';
require_once APP_INCLUDES . '/playlists.php';

$user   = require_auth();
$userId = (int)$user['id'];

$playlist = find_playlist($userId, (int)($_GET['id'] ?? 0));
if ($playlist === null) {
    redirect('/playlists.php');
}

$items = playlist_items($userId, $playlist['id']);

// Alturas da onda decorativa da capa (0–1): formato de voz, alto no meio
$waveBars = [.28, .42, .6, .38, .74, .52, .9, .64, .46, .82, 1, .7, .54, .88, .62, .4,
             .76, .96, .58, .44, .7, .5, .84, .6, .36, .66, .48, .3];

$pageTitle = $playlist['name'] . ' — VoxlyOne';
$pageStyles = ['playlists'];
require APP_INCLUDES . '/header.php';
?>

<a class="pl-back" href="/playlists.php"><?= icon('chevron-left') ?>Playlists</a>

<div class="pl-head">
  <h1 id="pl-title"><?= e($playlist['name']) ?></h1>
  <p class="pl-head__desc" id="pl-description" <?= $playlist['description'] === null ? 'hidden' : '' ?>><?= e($playlist['description']) ?></p>
</div>

<section class="pl-player" aria-label="Player da playlist">
  <div class="pl-art pl-cover--<?= (int)$playlist['id'] % 6 ?>">
    <span class="pl-art__tag"><?= icon('mic') ?>Sua voz</span>
    <span class="pl-art__wave" aria-hidden="true">
      <?php foreach ($waveBars as $i => $height): ?>
        <i style="--h: <?= $height ?>; --d: <?= $i % 7 ?>"></i>
      <?php endforeach; ?>
    </span>
  </div>

  <div class="pl-player__body">
    <p class="pl-player__status" id="pl-status" aria-live="polite"></p>
    <p class="pl-player__en" id="pl-now-en"></p>
    <p class="pl-player__pt" id="pl-now-pt"></p>

    <div class="pl-player__bar" aria-hidden="true"><span id="pl-progress"></span></div>

    <div class="pl-player__controls">
      <button class="pl-player__nav" type="button" id="pl-prev" aria-label="Anterior"><?= icon('skip-back') ?></button>
      <button class="pl-player__play" type="button" id="pl-play" aria-label="Tocar">
        <?= icon('play', 'pl-player__icon-play') ?><?= icon('pause', 'pl-player__icon-pause') ?>
      </button>
      <button class="pl-player__nav" type="button" id="pl-next" aria-label="Próxima"><?= icon('skip-forward') ?></button>
    </div>

    <div class="pl-modes" role="group" aria-label="Modo de reprodução">
      <button class="pl-mode" type="button" id="pl-shuffle" aria-pressed="false"><?= icon('shuffle') ?>Aleatório</button>
      <button class="pl-mode" type="button" id="pl-repeat" aria-pressed="false"><?= icon('repeat') ?>Repetir</button>
    </div>

    <p class="form__error" id="pl-error" role="alert" hidden></p>
  </div>
  <audio id="pl-audio" preload="auto"></audio>
</section>

<div class="pl-list-head">
  <h2>Frases</h2>
  <span class="pl-list-head__count" id="pl-count"></span>
</div>

<ol class="pl-items" id="pl-items"></ol>

<div class="empty pl-empty" id="pl-empty" hidden>
  <span class="empty__icon"><?= icon('list-music') ?></span>
  <p class="empty__title">Esta playlist está vazia</p>
  <p class="empty__text">
    Adicione gravações em que você tirou mais de 8: logo depois da nota, na
    prática, ou pelo botão <strong>+ Playlist</strong> na lista de frases.
  </p>
  <a class="btn" href="/dashboard.php"><?= icon('book-open-text') ?>Ir para minhas frases</a>
</div>

<details class="pl-edit">
  <summary>
    <span class="pl-edit__icon"><?= icon('pencil') ?></span>
    <span class="pl-edit__label">Editar playlist</span>
    <?= icon('chevron-down', 'pl-edit__chevron') ?>
  </summary>

  <div class="pl-edit__body">
    <form class="form" id="pl-edit-form" novalidate>
      <label class="field">
        <span class="field__label">Nome</span>
        <input type="text" name="name" maxlength="80" required value="<?= e($playlist['name']) ?>">
      </label>
      <label class="field">
        <span class="field__label">Descrição <em>(opcional)</em></span>
        <textarea name="description" rows="2" maxlength="300"><?= e($playlist['description']) ?></textarea>
      </label>
      <p class="form__error" id="pl-edit-error" role="alert" hidden></p>
      <p class="field__status pl-edit__status" id="pl-edit-status" role="status"></p>
      <button class="btn btn--block" type="submit">Salvar alterações</button>
    </form>

    <div class="danger-zone">
      <div class="danger-zone__head">
        <span class="danger-zone__icon"><?= icon('trash-2') ?></span>
        <div>
          <h2>Excluir playlist</h2>
          <p>Apaga só a playlist. Suas frases e gravações continuam intactas.</p>
        </div>
      </div>
      <button class="btn btn--danger btn--block" type="button" id="pl-delete">Excluir playlist</button>
    </div>
  </div>
</details>

<script>
window.PLAYLIST = <?= json_encode([
    'id'      => $playlist['id'],
    'name'    => $playlist['name'],
    'shuffle' => $playlist['shuffle_enabled'],
    'repeat'  => $playlist['repeat_enabled'],
    'items'   => $items,
    'csrf'    => csrf_token(),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script type="module" src="<?= e(asset('/assets/js/playlist_player.js')) ?>"></script>

<?php require APP_INCLUDES . '/footer.php'; ?>
