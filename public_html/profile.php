<?php
declare(strict_types=1);

require_once __DIR__ . '/boot.php';

$user   = require_auth();
$userId = (int)$user['id'];

// Uma query só para todas as contagens de frases
$stmt = db()->prepare(
    "SELECT COUNT(*)                                                      AS total,
            COALESCE(SUM(status = 'mastered'), 0)                         AS mastered,
            COALESCE(SUM(best_score >= :advance AND status <> 'mastered'), 0) AS approved,
            COALESCE(SUM(attempts_count), 0)                              AS attempts
       FROM phrases WHERE user_id = :uid"
);
$stmt->execute([':advance' => SCORE_TO_ADVANCE, ':uid' => $userId]);
$stats = $stmt->fetch();

// Quanto da cota de hoje já foi usada
$stmt = db()->prepare(
    "SELECT COALESCE(count, 0) FROM rate_limits
      WHERE user_id = ? AND action = 'analyze' AND day = CURDATE()"
);
$stmt->execute([$userId]);
$usedToday = (int)$stmt->fetchColumn();

// Barra da cota: porcentagem usada, limitada a 0–100
$quotaPct  = LIMIT_ANALYZE_PER_DAY > 0 ? min(100, (int)round($usedToday / LIMIT_ANALYZE_PER_DAY * 100)) : 0;
$quotaLeft = max(0, LIMIT_ANALYZE_PER_DAY - $usedToday);
$initial   = mb_strtoupper(mb_substr(trim((string)$user['name']), 0, 1));

$pageTitle = 'Perfil — VoxlyOne';
$pageStyles = ['profile'];
require APP_INCLUDES . '/header.php';
?>

<div class="page-head">
  <h1>Perfil</h1>
</div>

<section class="profile">
  <!-- Identidade -->
  <div class="profile__id">
    <?php if (!empty($user['avatar_url'])): ?>
      <img class="profile__avatar" src="<?= e($user['avatar_url']) ?>" alt="" width="72" height="72" referrerpolicy="no-referrer">
    <?php else: ?>
      <span class="profile__avatar profile__avatar--initial" aria-hidden="true"><?= e($initial) ?></span>
    <?php endif; ?>
    <div class="profile__who">
      <p class="profile__name"><?= e($user['name']) ?></p>
      <p class="profile__email"><?= e($user['email']) ?></p>
      <p class="profile__since">
        <?= icon('calendar') ?>
        <span>Conta criada em <?= e(format_date($user['created_at'])) ?></span>
      </p>
    </div>
  </div>

  <!-- Estatísticas -->
  <section aria-labelledby="stats-title">
    <h2 class="profile__heading" id="stats-title">Seu progresso</h2>
    <ul class="stats">
      <li class="stat">
        <span class="stat__icon"><?= icon('book-open-text') ?></span>
        <span class="stat__value"><?= (int)$stats['total'] ?></span>
        <span class="stat__label">frases</span>
      </li>
      <li class="stat stat--gold">
        <span class="stat__icon"><?= icon('crown') ?></span>
        <span class="stat__value"><?= (int)$stats['mastered'] ?></span>
        <span class="stat__label">dominadas</span>
      </li>
      <li class="stat stat--brand">
        <span class="stat__icon"><?= icon('circle-check') ?></span>
        <span class="stat__value"><?= (int)$stats['approved'] ?></span>
        <span class="stat__label">aprovadas</span>
      </li>
      <li class="stat">
        <span class="stat__icon"><?= icon('mic') ?></span>
        <span class="stat__value"><?= (int)$stats['attempts'] ?></span>
        <span class="stat__label">tentativas</span>
      </li>
    </ul>
  </section>

  <!-- Cota diária de análises -->
  <section class="quota" aria-labelledby="quota-title">
    <div class="quota__top">
      <span class="quota__icon"><?= icon('gauge') ?></span>
      <h2 class="quota__title" id="quota-title">Análises hoje</h2>
      <p class="quota__count"><strong><?= $usedToday ?> de <?= LIMIT_ANALYZE_PER_DAY ?></strong></p>
    </div>
    <div class="quota__bar" role="progressbar" aria-label="Análises usadas hoje"
         aria-valuemin="0" aria-valuemax="<?= LIMIT_ANALYZE_PER_DAY ?>" aria-valuenow="<?= min($usedToday, LIMIT_ANALYZE_PER_DAY) ?>">
      <span class="quota__fill<?= $quotaLeft === 0 ? ' quota__fill--full' : '' ?>" style="width: <?= $quotaPct ?>%"></span>
    </div>
    <p class="quota__note">
      <?= $quotaLeft === 1 ? 'Resta 1 análise.' : 'Restam ' . $quotaLeft . ' análises.' ?>
      O limite zera à meia-noite no horário do Leste dos EUA.
    </p>
  </section>

  <!-- Conta e documentos -->
  <section aria-labelledby="settings-title">
    <h2 class="profile__heading" id="settings-title">Conta</h2>
    <ul class="settings">
      <?php if ($user['consented_at'] !== null): ?>
        <li class="settings__row settings__row--static">
          <span class="settings__icon settings__icon--success"><?= icon('shield-check') ?></span>
          <span class="settings__text">
            <span class="settings__label">Consentimento de gravação</span>
            <span class="settings__hint">Aceito em <?= e(format_date($user['consented_at'])) ?></span>
          </span>
        </li>
      <?php endif; ?>
      <li>
        <a class="settings__row" href="/privacy.php">
          <span class="settings__icon"><?= icon('lock') ?></span>
          <span class="settings__text"><span class="settings__label">Política de privacidade</span></span>
          <?= icon('chevron-right', 'settings__chevron') ?>
        </a>
      </li>
      <li>
        <a class="settings__row" href="/terms.php">
          <span class="settings__icon"><?= icon('book-open-text') ?></span>
          <span class="settings__text"><span class="settings__label">Termos de uso</span></span>
          <?= icon('chevron-right', 'settings__chevron') ?>
        </a>
      </li>
      <li>
        <a class="settings__row" href="mailto:us@hubfy.us">
          <span class="settings__icon"><?= icon('mail') ?></span>
          <span class="settings__text">
            <span class="settings__label">Fale com a gente</span>
            <span class="settings__hint">us@hubfy.us</span>
          </span>
          <?= icon('chevron-right', 'settings__chevron') ?>
        </a>
      </li>
      <li>
        <a class="settings__row settings__row--logout" href="/auth/logout.php">
          <span class="settings__icon"><?= icon('log-out') ?></span>
          <span class="settings__text"><span class="settings__label">Sair da conta</span></span>
          <?= icon('chevron-right', 'settings__chevron') ?>
        </a>
      </li>
    </ul>
  </section>

  <!-- Zona de perigo -->
  <section class="danger-zone" aria-labelledby="danger-title">
    <div class="danger-zone__head">
      <span class="danger-zone__icon"><?= icon('triangle-alert') ?></span>
      <h2 id="danger-title">Excluir conta</h2>
    </div>
    <p>
      Apaga definitivamente sua conta, suas frases, categorias, todo o histórico
      de tentativas, suas gravações guardadas e playlists. Não há como desfazer.
    </p>
    <p class="form__error" id="delete-error" role="alert" hidden></p>
    <button class="btn btn--danger btn--block" type="button" id="btn-delete">
      Excluir minha conta
    </button>
  </section>
</section>

<script>window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;</script>
<script src="<?= e(asset('/assets/js/profile.js')) ?>"></script>

<?php require APP_INCLUDES . '/footer.php'; ?>
