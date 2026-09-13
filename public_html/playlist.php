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

$pageTitle = $playlist['name'] . ' — VoxlyOne';
require APP_INCLUDES . '/header.php';
?>

<div class="page-head">
  <h1 id="pl-title"><?= e($playlist['name']) ?></h1>
  <a class="btn btn--sm btn--ghost" href="/playlists.php">Playlists</a>
</div>

<p class="page-intro" id="pl-description" <?= $playlist['description'] === null ? 'hidden' : '' ?>><?= e($playlist['description']) ?></p>

<section class="pl-player" aria-label="Player da playlist">
  <p class="pl-player__status" id="pl-status" aria-live="polite"></p>
  <p class="pl-player__en" id="pl-now-en"></p>
  <p class="pl-player__pt" id="pl-now-pt"></p>

  <div class="pl-player__bar" aria-hidden="true"><span id="pl-progress"></span></div>

  <div class="pl-player__controls">
    <button class="btn btn--ghost pl-player__nav" type="button" id="pl-prev" aria-label="Anterior">⏮</button>
    <button class="btn pl-player__play" type="button" id="pl-play">▶ Tocar</button>
    <button class="btn btn--ghost pl-player__nav" type="button" id="pl-next" aria-label="Próxima">⏭</button>
  </div>

  <div class="speeds pl-player__modes" role="group" aria-label="Modo de reprodução">
    <button class="speed" type="button" id="pl-shuffle" aria-pressed="false">🔀 Aleatório</button>
    <button class="speed" type="button" id="pl-repeat" aria-pressed="false">🔁 Repetir</button>
  </div>

  <p class="form__error" id="pl-error" role="alert" hidden></p>
  <audio id="pl-audio" preload="auto"></audio>
</section>

<div class="pl-list-head">
  <h2>Frases</h2>
  <span class="list-count" id="pl-count"></span>
</div>

<ol class="pl-items" id="pl-items"></ol>

<div class="empty" id="pl-empty" hidden>
  <p class="empty__title">Esta playlist está vazia.</p>
  <p class="empty__text">
    Adicione gravações em que você tirou mais de 8: logo depois da nota, na
    prática, ou pelo botão <strong>+ Playlist</strong> na lista de frases.
  </p>
  <a class="btn" href="/dashboard.php">Ir para minhas frases</a>
</div>

<details class="pl-edit">
  <summary>Editar playlist</summary>
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
    <p class="field__status" id="pl-edit-status" role="status"></p>
    <button class="btn btn--block" type="submit">Salvar</button>
  </form>

  <div class="danger-zone">
    <h2>Excluir playlist</h2>
    <p>Apaga só a playlist. Suas frases e gravações continuam intactas.</p>
    <button class="btn btn--danger btn--block" type="button" id="pl-delete">Excluir playlist</button>
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
<script type="module" src="/assets/js/playlist_player.js"></script>

<?php require APP_INCLUDES . '/footer.php'; ?>
