<?php
declare(strict_types=1);

require_once __DIR__ . '/boot.php';
require_once APP_INCLUDES . '/phrases.php';
require_once APP_INCLUDES . '/recordings.php';

$user   = require_auth();
$userId = (int)$user['id'];

$id     = (int)($_GET['id'] ?? 0);
$phrase = $id > 0 ? find_phrase($userId, $id) : null;

if ($id > 0 && $phrase === null) {
    redirect('/dashboard.php');
}

$isEdit     = $phrase !== null;
$categories = list_categories($userId);
$recording  = $isEdit ? find_recording_by_phrase($userId, (int)$phrase['id']) : null;

$pageTitle = $isEdit ? 'Editar frase — VoxlyOne' : 'Nova frase — VoxlyOne';
require APP_INCLUDES . '/header.php';
?>

<div class="page-head">
  <h1><?= $isEdit ? 'Editar frase' : 'Nova frase' ?></h1>
  <a class="btn btn--sm btn--ghost" href="/dashboard.php">Voltar</a>
</div>

<form class="form" id="phrase-form" novalidate>
  <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
  <?php if ($isEdit): ?>
    <input type="hidden" name="id" value="<?= (int)$phrase['id'] ?>">
  <?php endif; ?>
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

  <label class="field">
    <span class="field__label">Frase em inglês <em>(obrigatória)</em></span>
    <textarea name="text_en" id="text_en" rows="2" maxlength="200"
              required><?= e($phrase['text_en'] ?? '') ?></textarea>
    <span class="field__hint">É esta que você vai praticar.</span>
  </label>

  <label class="field">
    <span class="field__label">Tradução em português</span>
    <textarea name="text_pt" id="text_pt" rows="2"
              maxlength="200"><?= e($phrase['text_pt'] ?? '') ?></textarea>
  </label>

  <div class="field">
    <span class="field__label">Pronúncia aproximada (em sons do português)</span>
    <textarea name="phonetic_guide" id="phonetic_guide" rows="2" maxlength="400"
              placeholder="ex.: RRAU ar iú"><?= e($phrase['phonetic_guide'] ?? '') ?></textarea>
    <span class="field__hint">Sem IPA — escreva como você leria em português.</span>
    <button class="btn btn--sm btn--ghost" type="button" id="btn-generate">
      Traduzir e gerar fonética
    </button>
    <span class="field__status" id="gen-status" role="status"></span>
  </div>

  <label class="field">
    <span class="field__label">Categoria</span>
    <input type="text" name="category" list="category-list" maxlength="80"
           value="<?= e($phrase['category_name'] ?? '') ?>"
           placeholder="Ex.: Trabalho, Viagem">
    <datalist id="category-list">
      <?php foreach ($categories as $cat): ?>
        <option value="<?= e($cat['name']) ?>"></option>
      <?php endforeach; ?>
    </datalist>
    <span class="field__hint">Digite uma nova ou escolha uma existente.</span>
  </label>

  <label class="field">
    <span class="field__label">Nível</span>
    <select name="level">
      <?php $level = $phrase['level'] ?? 'beginner'; ?>
      <option value="beginner"     <?= $level === 'beginner' ? 'selected' : '' ?>>Iniciante</option>
      <option value="intermediate" <?= $level === 'intermediate' ? 'selected' : '' ?>>Intermediário</option>
      <option value="advanced"     <?= $level === 'advanced' ? 'selected' : '' ?>>Avançado</option>
    </select>
  </label>

  <p class="form__error" id="form-error" role="alert" hidden></p>

  <button class="btn btn--block" type="submit" id="btn-save">
    <?= $isEdit ? 'Salvar alterações' : 'Criar frase' ?>
  </button>
</form>

<?php if ($isEdit): ?>
  <section class="my-recording">
    <h2>Sua gravação aprovada</h2>
    <?php if ($recording !== null): ?>
      <p class="my-recording__meta">
        Nota <?= e(number_format((float)$recording['score'], 1, ',', '')) ?>
        · gravada em <?= e(format_date($recording['updated_at'])) ?>
      </p>
      <audio controls preload="none" src="<?= e(recording_url($recording)) ?>"></audio>
      <div class="my-recording__actions">
        <button class="btn btn--sm" type="button" id="btn-playlist"
                data-recording="<?= (int)$recording['id'] ?>"
                data-label="<?= e($phrase['text_en']) ?>">Adicionar à playlist</button>
        <a class="btn btn--sm btn--ghost" href="/practice.php?id=<?= (int)$phrase['id'] ?>">Gravar de novo</a>
      </div>
      <p class="field__hint">
        Uma nova gravação com nota acima de 8 substitui esta em todas as playlists.
        Com nota até 8, esta continua valendo.
      </p>
    <?php else: ?>
      <p class="field__hint">
        Tire nota acima de 8 na prática para guardar sua gravação e ouvi-la nas playlists.
      </p>
      <button class="btn btn--sm btn--ghost" type="button" disabled>Adicionar à playlist</button>
      <a class="btn btn--sm btn--ghost" href="/practice.php?id=<?= (int)$phrase['id'] ?>">Praticar</a>
    <?php endif; ?>
  </section>
<?php endif; ?>

<script type="module" src="/assets/js/phrase_form.js"></script>

<?php require APP_INCLUDES . '/footer.php'; ?>
