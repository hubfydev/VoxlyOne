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

$pageTitle = 'Perfil — VoxlyOne';
require APP_INCLUDES . '/header.php';
?>

<div class="page-head">
  <h1>Perfil</h1>
</div>

<section class="profile">
  <div class="profile__id">
    <?php if (!empty($user['avatar_url'])): ?>
      <img class="profile__avatar" src="<?= e($user['avatar_url']) ?>" alt="" width="56" height="56">
    <?php endif; ?>
    <div>
      <p class="profile__name"><?= e($user['name']) ?></p>
      <p class="profile__email"><?= e($user['email']) ?></p>
    </div>
  </div>

  <ul class="stats">
    <li class="stat">
      <span class="stat__value"><?= (int)$stats['total'] ?></span>
      <span class="stat__label">frases</span>
    </li>
    <li class="stat">
      <span class="stat__value stat__value--success"><?= (int)$stats['mastered'] ?></span>
      <span class="stat__label">dominadas</span>
    </li>
    <li class="stat">
      <span class="stat__value stat__value--brand"><?= (int)$stats['approved'] ?></span>
      <span class="stat__label">aprovadas</span>
    </li>
    <li class="stat">
      <span class="stat__value"><?= (int)$stats['attempts'] ?></span>
      <span class="stat__label">tentativas</span>
    </li>
  </ul>

  <p class="profile__quota">
    Análises hoje: <strong><?= $usedToday ?> de <?= LIMIT_ANALYZE_PER_DAY ?></strong>.
    O limite zera à meia-noite no horário do Leste dos EUA.
  </p>

  <p class="profile__since">
    Conta criada em <?= e(format_date($user['created_at'])) ?>.
    <?php if ($user['consented_at'] !== null): ?>
      Consentimento de gravação aceito em
      <?= e(format_date($user['consented_at'])) ?>.
    <?php endif; ?>
  </p>

  <div class="profile__actions">
    <a class="btn btn--ghost btn--block" href="/auth/logout.php">Sair da conta</a>
  </div>

  <div class="danger-zone">
    <h2>Excluir conta</h2>
    <p>
      Apaga definitivamente sua conta, suas frases, categorias, todo o histórico
      de tentativas, suas gravações guardadas e playlists. Não há como desfazer.
    </p>
    <p class="form__error" id="delete-error" role="alert" hidden></p>
    <button class="btn btn--danger btn--block" type="button" id="btn-delete">
      Excluir minha conta
    </button>
  </div>
</section>

<script>window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;</script>
<script src="/assets/js/profile.js"></script>

<?php require APP_INCLUDES . '/footer.php'; ?>
