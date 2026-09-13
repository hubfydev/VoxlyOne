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
$level      = $phrase['level'] ?? 'beginner';

$pageTitle = $isEdit ? 'Editar frase — VoxlyOne' : 'Nova frase — VoxlyOne';
$pageStyles = ['phrase-form'];
require APP_INCLUDES . '/header.php';
?>

<div class="form-head">
  <a class="btn btn--icon btn--ghost form-head__back" href="/dashboard.php" aria-label="Voltar para as frases">
    <?= icon('arrow-left') ?>
  </a>
  <div>
    <h1 class="form-head__title"><?= $isEdit ? 'Editar frase' : 'Nova frase' ?></h1>
    <p class="form-head__sub">
      <?= $isEdit ? 'Ajuste texto, pronúncia e organização.' : 'Escreva em inglês; a IA sugere o resto.' ?>
    </p>
  </div>
</div>

<form class="form phrase-form" id="phrase-form" novalidate>
  <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
  <?php if ($isEdit): ?>
    <input type="hidden" name="id" value="<?= (int)$phrase['id'] ?>">
  <?php endif; ?>
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

  <!-- A frase: inglês (praticada) e português -->
  <section class="fsec" aria-labelledby="sec-phrase">
    <header class="fsec__head">
      <span class="fsec__icon"><?= icon('languages') ?></span>
      <h2 class="fsec__title" id="sec-phrase">A frase</h2>
    </header>

    <label class="field">
      <span class="field__label field__label--row">
        Em inglês <span class="req-pill">Obrigatória</span>
      </span>
      <textarea class="textarea-en" name="text_en" id="text_en" rows="2" maxlength="200"
                placeholder="Ex.: Where is the baggage claim?"
                required><?= e($phrase['text_en'] ?? '') ?></textarea>
      <span class="field__hint">É esta que você vai praticar.</span>
    </label>

    <label class="field">
      <span class="field__label">Tradução em português</span>
      <textarea name="text_pt" id="text_pt" rows="2" maxlength="200"
                placeholder="Ex.: Onde fica a esteira de bagagem?"><?= e($phrase['text_pt'] ?? '') ?></textarea>
    </label>
  </section>

  <!-- Pronúncia aproximada: o método de estudo do app, em destaque -->
  <section class="fsec fsec--phonetic" aria-labelledby="sec-phonetic">
    <header class="fsec__head">
      <span class="fsec__icon fsec__icon--brand"><?= icon('ear') ?></span>
      <div>
        <h2 class="fsec__title" id="sec-phonetic">Pronúncia aproximada</h2>
        <p class="fsec__sub">Escrita com os sons do português.</p>
      </div>
    </header>

    <div class="field">
      <label class="sr-only" for="phonetic_guide">Pronúncia aproximada (em sons do português)</label>
      <textarea class="textarea-phonetic" name="phonetic_guide" id="phonetic_guide" rows="2" maxlength="400"
                placeholder="ex.: RRAU ar iú"><?= e($phrase['phonetic_guide'] ?? '') ?></textarea>
      <span class="field__hint">Sem IPA — escreva como você leria em português.</span>
    </div>

    <button class="btn btn--block btn-generate" type="button" id="btn-generate">
      <?= icon('wand-sparkles') ?>
      <span>Traduzir e gerar fonética</span>
    </button>
    <span class="field__status gen-status" id="gen-status" role="status"></span>
  </section>

  <!-- Organização -->
  <section class="fsec" aria-labelledby="sec-org">
    <header class="fsec__head">
      <span class="fsec__icon"><?= icon('sliders-horizontal') ?></span>
      <h2 class="fsec__title" id="sec-org">Organização</h2>
    </header>

    <div class="fsec__grid">
      <label class="field">
        <span class="field__label">Categoria</span>
        <span class="input-icon">
          <?= icon('book-open-text') ?>
          <input type="text" name="category" list="category-list" maxlength="80"
                 value="<?= e($phrase['category_name'] ?? '') ?>"
                 placeholder="Ex.: Trabalho, Viagem">
        </span>
        <datalist id="category-list">
          <?php foreach ($categories as $cat): ?>
            <option value="<?= e($cat['name']) ?>"></option>
          <?php endforeach; ?>
        </datalist>
        <span class="field__hint">Digite uma nova ou escolha uma existente.</span>
      </label>

      <label class="field">
        <span class="field__label">Nível</span>
        <span class="input-icon">
          <?= icon('gauge') ?>
          <select name="level">
            <option value="beginner"     <?= $level === 'beginner' ? 'selected' : '' ?>>Iniciante</option>
            <option value="intermediate" <?= $level === 'intermediate' ? 'selected' : '' ?>>Intermediário</option>
            <option value="advanced"     <?= $level === 'advanced' ? 'selected' : '' ?>>Avançado</option>
          </select>
        </span>
      </label>
    </div>
  </section>

  <!-- Salvar: fica preso ao pé da tela enquanto o formulário rola -->
  <div class="form-actions">
    <p class="form__error" id="form-error" role="alert" hidden></p>
    <button class="btn btn--lg btn--block" type="submit" id="btn-save">
      <?= $isEdit ? 'Salvar alterações' : 'Criar frase' ?>
    </button>
  </div>
</form>

<?php if ($isEdit): ?>
  <section class="my-recording<?= $recording === null ? ' my-recording--empty' : '' ?>" aria-labelledby="sec-recording">
    <header class="my-recording__head">
      <span class="my-recording__icon"><?= icon($recording !== null ? 'headphones' : 'lock') ?></span>
      <div>
        <h2 id="sec-recording">Sua gravação aprovada</h2>
        <?php if ($recording !== null): ?>
          <p class="my-recording__meta">
            <span class="my-recording__score"><?= icon('circle-check') ?> Nota <?= e(number_format((float)$recording['score'], 1, ',', '')) ?></span>
            <span>gravada em <?= e(format_date($recording['updated_at'])) ?></span>
          </p>
        <?php else: ?>
          <p class="my-recording__meta">Ainda não há gravação guardada.</p>
        <?php endif; ?>
      </div>
    </header>

    <?php if ($recording !== null): ?>
      <div class="my-recording__player">
        <audio controls preload="none" src="<?= e(recording_url($recording)) ?>"></audio>
      </div>
      <div class="my-recording__actions">
        <button class="btn btn--sm" type="button" id="btn-playlist"
                data-recording="<?= (int)$recording['id'] ?>"
                data-label="<?= e($phrase['text_en']) ?>"><?= icon('list-plus') ?> Adicionar à playlist</button>
        <a class="btn btn--sm btn--ghost" href="/practice.php?id=<?= (int)$phrase['id'] ?>"><?= icon('mic') ?> Gravar de novo</a>
      </div>
      <p class="my-recording__note">
        <?= icon('info') ?>
        <span>
          Uma nova gravação com nota acima de 8 substitui esta em todas as playlists.
          Com nota até 8, esta continua valendo.
        </span>
      </p>
    <?php else: ?>
      <p class="my-recording__text">
        Tire nota acima de 8 na prática para guardar sua gravação e ouvi-la nas playlists.
      </p>
      <div class="my-recording__actions">
        <a class="btn btn--sm" href="/practice.php?id=<?= (int)$phrase['id'] ?>"><?= icon('mic') ?> Praticar</a>
        <button class="btn btn--sm btn--ghost" type="button" disabled><?= icon('list-plus') ?> Adicionar à playlist</button>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>

<script type="module" src="<?= e(asset('/assets/js/phrase_form.js')) ?>"></script>

<?php require APP_INCLUDES . '/footer.php'; ?>
