<?php
declare(strict_types=1);

require_once __DIR__ . '/boot.php';
require_once APP_INCLUDES . '/phrases.php';

$user   = require_auth();
$userId = (int)$user['id'];

$id     = (int)($_GET['id'] ?? 0);
$phrase = $id > 0 ? find_phrase($userId, $id) : null;

if ($id > 0 && $phrase === null) {
    redirect('/dashboard.php');
}

$isEdit     = $phrase !== null;
$categories = list_categories($userId);

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

<script src="/assets/js/phrase_form.js"></script>

<?php require APP_INCLUDES . '/footer.php'; ?>
