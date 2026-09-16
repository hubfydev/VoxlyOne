<?php
declare(strict_types=1);

define('ADMIN_AREA', true);
require_once __DIR__ . '/../boot.php';
require_once APP_INCLUDES . '/admin.php';

$admin = require_admin();

$userId = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare(
    'SELECT id, name, email, avatar_url, created_at, consented_at, consent_version,
            blocked_at, blocked_reason, last_login_at, login_count
       FROM users WHERE id = ?'
);
$stmt->execute([$userId]);
$user = $stmt->fetch();

if ($user === false) {
    admin_flash('error', 'Usuário não encontrado.');
    redirect('/admin/users.php');
}

$stmt = db()->prepare(
    "SELECT COUNT(*)                                                 AS phrases,
            COALESCE(SUM(status = 'mastered'), 0)                    AS mastered,
            COALESCE(SUM(best_score >= :adv AND status <> 'mastered'), 0) AS approved,
            COALESCE(SUM(attempts_count), 0)                         AS attempts
       FROM phrases WHERE user_id = :uid"
);
$stmt->execute([':adv' => SCORE_TO_ADVANCE, ':uid' => $userId]);
$learning = $stmt->fetch();

$stmt = db()->prepare(
    'SELECT (SELECT COUNT(*) FROM recordings WHERE user_id = :u1) AS recordings,
            (SELECT COUNT(*) FROM playlists  WHERE user_id = :u2) AS playlists'
);
$stmt->execute([':u1' => $userId, ':u2' => $userId]);
$library = $stmt->fetch();

// Uso de hoje contra os limites diários
$stmt = db()->prepare('SELECT action, count FROM rate_limits WHERE user_id = ? AND day = CURDATE()');
$stmt->execute([$userId]);
$today = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$stmt = db()->prepare(
    "SELECT action,
            COUNT(*)                                                                  AS calls,
            COALESCE(SUM(cost_usd), 0)                                                AS cost,
            COALESCE(SUM(CASE WHEN created_at >= :month THEN cost_usd END), 0)        AS cost_month,
            COALESCE(SUM(text_input_tokens + text_output_tokens), 0)                  AS text_tokens,
            COALESCE(SUM(audio_input_tokens + audio_output_tokens), 0)                AS audio_tokens
       FROM ai_usage WHERE user_id = :uid GROUP BY action"
);
$stmt->execute([':month' => date('Y-m-01 00:00:00'), ':uid' => $userId]);
$usage = $stmt->fetchAll(PDO::FETCH_UNIQUE);

$totals = ['calls' => 0, 'cost' => 0.0, 'cost_month' => 0.0, 'text_tokens' => 0, 'audio_tokens' => 0];
foreach ($usage as $row) {
    foreach ($totals as $key => $_) {
        $totals[$key] += $row[$key];
    }
}

$stmt = db()->prepare(
    'SELECT action, model, text_input_tokens, audio_input_tokens, text_output_tokens,
            audio_output_tokens, cost_usd, estimated, created_at
       FROM ai_usage WHERE user_id = ? ORDER BY id DESC LIMIT 15'
);
$stmt->execute([$userId]);
$recent = $stmt->fetchAll();

$isBlocked = $user['blocked_at'] !== null;
$self      = '/admin/user.php?id=' . $userId;

$limits = [
    'analyze'   => LIMIT_ANALYZE_PER_DAY,
    'translate' => LIMIT_TRANSLATE_PER_DAY,
    'tts'       => defined('LIMIT_TTS_PER_DAY') ? LIMIT_TTS_PER_DAY : 100,
];

$pageTitle = $user['name'] . ' — VoxlyOne Admin';
$adminTab  = 'users';
require APP_INCLUDES . '/admin_header.php';
?>

<a class="admin-back" href="/admin/users.php"><?= icon('chevron-left') ?> Usuários</a>

<section class="card admin-profile<?= $isBlocked ? ' is-blocked' : '' ?>">
  <?= admin_avatar($user, 'admin-avatar--xl') ?>
  <div class="admin-profile__text">
    <h1 class="admin-profile__name"><?= e($user['name']) ?></h1>
    <p class="admin-profile__email"><?= e($user['email']) ?></p>
    <p class="admin-profile__meta">
      <?php if ($isBlocked): ?>
        <span class="badge admin-badge-blocked">Bloqueado desde <?= e(format_date($user['blocked_at'])) ?></span>
      <?php else: ?>
        <span class="badge badge--approved">Ativo</span>
      <?php endif; ?>
      <span class="admin-muted">Conta criada em <?= e(format_date($user['created_at'])) ?></span>
    </p>
  </div>
</section>

<?php if ($isBlocked && $user['blocked_reason'] !== null): ?>
  <p class="admin-reason"><?= icon('info') ?> <span><strong>Motivo do bloqueio:</strong> <?= e($user['blocked_reason']) ?></span></p>
<?php endif; ?>

<ul class="admin-stats admin-stats--compact">
  <li class="admin-stat"><span class="admin-stat__value"><?= e(money($totals['cost_month'])) ?></span><span class="admin-stat__label">custo no mês</span></li>
  <li class="admin-stat"><span class="admin-stat__value"><?= e(money($totals['cost'])) ?></span><span class="admin-stat__label">custo total</span></li>
  <li class="admin-stat"><span class="admin-stat__value"><?= e(time_ago($user['last_login_at'])) ?></span><span class="admin-stat__label">último acesso</span></li>
  <li class="admin-stat"><span class="admin-stat__value"><?= num((int)$user['login_count']) ?></span><span class="admin-stat__label">logins</span></li>
</ul>

<h2 class="section-title">Custos de IA por recurso</h2>
<div class="card admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr><th>Recurso</th><th class="num">Chamadas</th><th class="num">Tokens texto</th><th class="num">Tokens áudio</th><th class="num">Mês</th><th class="num">Total</th></tr>
    </thead>
    <tbody>
      <?php foreach (AI_ACTIONS as $action): ?>
        <?php $row = $usage[$action] ?? ['calls' => 0, 'cost' => 0, 'cost_month' => 0, 'text_tokens' => 0, 'audio_tokens' => 0]; ?>
        <tr>
          <td><?= e(action_label($action)) ?></td>
          <td class="num"><?= num((int)$row['calls']) ?></td>
          <td class="num"><?= num((int)$row['text_tokens']) ?></td>
          <td class="num"><?= num((int)$row['audio_tokens']) ?></td>
          <td class="num"><?= e(money((float)$row['cost_month'])) ?></td>
          <td class="num"><strong><?= e(money((float)$row['cost'])) ?></strong></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <th>Total</th>
        <th class="num"><?= num($totals['calls']) ?></th>
        <th class="num"><?= num($totals['text_tokens']) ?></th>
        <th class="num"><?= num($totals['audio_tokens']) ?></th>
        <th class="num"><?= e(money($totals['cost_month'])) ?></th>
        <th class="num"><?= e(money($totals['cost'])) ?></th>
      </tr>
    </tfoot>
  </table>
</div>

<h2 class="section-title">Uso de hoje</h2>
<ul class="admin-quota">
  <?php foreach ($limits as $action => $limit): ?>
    <?php $used = (int)($today[$action] ?? 0); $pct = $limit > 0 ? min(100, round($used / $limit * 100)) : 0; ?>
    <li class="card">
      <span class="admin-quota__top">
        <span><?= e(action_label($action)) ?></span>
        <strong><?= num($used) ?> de <?= num((int)$limit) ?></strong>
      </span>
      <span class="admin-quota__bar<?= $pct >= 90 ? ' is-high' : '' ?>" style="--pct: <?= $pct ?>%"><span></span></span>
    </li>
  <?php endforeach; ?>
</ul>

<h2 class="section-title">Aprendizado</h2>
<ul class="admin-stats admin-stats--compact">
  <li class="admin-stat"><span class="admin-stat__value"><?= num((int)$learning['phrases']) ?></span><span class="admin-stat__label">frases</span></li>
  <li class="admin-stat admin-stat--brand"><span class="admin-stat__value"><?= num((int)$learning['approved']) ?></span><span class="admin-stat__label">aprovadas</span></li>
  <li class="admin-stat admin-stat--gold"><span class="admin-stat__value"><?= num((int)$learning['mastered']) ?></span><span class="admin-stat__label">dominadas</span></li>
  <li class="admin-stat"><span class="admin-stat__value"><?= num((int)$learning['attempts']) ?></span><span class="admin-stat__label">tentativas</span></li>
  <li class="admin-stat"><span class="admin-stat__value"><?= num((int)$library['recordings']) ?></span><span class="admin-stat__label">gravações salvas</span></li>
  <li class="admin-stat"><span class="admin-stat__value"><?= num((int)$library['playlists']) ?></span><span class="admin-stat__label">playlists</span></li>
</ul>

<h2 class="section-title">
  Últimas chamadas à IA
  <span class="list-count"><?= count($recent) ?> mais recentes</span>
</h2>
<?php if ($recent === []): ?>
  <div class="empty">
    <span class="empty__icon"><?= icon('cpu') ?></span>
    <p class="empty__title">Nenhuma chamada registrada</p>
    <p class="empty__text">Este usuário ainda não usou os recursos de IA desde o início do monitoramento.</p>
  </div>
<?php else: ?>
  <ul class="card admin-list">
    <?php foreach ($recent as $row): ?>
      <li class="admin-row">
        <span class="admin-row__icon admin-action--<?= e($row['action']) ?>">
          <?= icon(match ($row['action']) { 'analyze' => 'mic', 'translate' => 'languages', default => 'volume-2' }) ?>
        </span>
        <span class="admin-row__text">
          <span class="admin-row__title"><?= e(action_label($row['action'])) ?></span>
          <span class="admin-row__sub admin-ellipsis" title="<?= e($row['model']) ?>">
            <?= e(format_datetime($row['created_at'])) ?>
            · <?= num((int)$row['text_input_tokens'] + (int)$row['text_output_tokens'] + (int)$row['audio_input_tokens'] + (int)$row['audio_output_tokens']) ?> tokens<?= (int)$row['estimated'] === 1 ? ' (est.)' : '' ?>
          </span>
        </span>
        <span class="admin-row__value"><?= e(money((float)$row['cost_usd'])) ?></span>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<p class="admin-footnote admin-footnote--tight"><?= icon('info') ?> <span>Tokens e custo por modelo ficam em <a href="/admin/costs.php">Custos</a>. "(est.)": voz, cujos tokens são estimados pela duração do áudio.</span></p>

<h2 class="section-title">Acesso ao app</h2>
<section class="card admin-danger<?= $isBlocked ? ' is-unblock' : '' ?>">
  <?php if ($isBlocked): ?>
    <p>Este usuário está <strong>bloqueado</strong>: não consegue entrar nem usar o app. Os dados dele continuam guardados.</p>
    <form method="post" action="/admin/user_action.php" data-confirm="Desbloquear <?= e($user['name']) ?>?">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="user_id" value="<?= $userId ?>">
      <input type="hidden" name="action" value="unblock">
      <input type="hidden" name="back" value="<?= e($self) ?>">
      <button class="btn btn--block" type="submit"><?= icon('user-check') ?> Desbloquear usuário</button>
    </form>
  <?php else: ?>
    <p>Bloquear encerra a sessão na hora e impede novos logins. Nada é apagado — dá para desbloquear quando quiser.</p>
    <form class="form" method="post" action="/admin/user_action.php"
          data-confirm="Bloquear <?= e($user['name']) ?>? O acesso ao app é encerrado na hora.">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="user_id" value="<?= $userId ?>">
      <input type="hidden" name="action" value="block">
      <input type="hidden" name="back" value="<?= e($self) ?>">
      <label class="field">
        <span class="field__label">Motivo <em>(opcional, só você vê)</em></span>
        <input type="text" name="reason" maxlength="200" placeholder="Ex.: uso abusivo da análise">
      </label>
      <button class="btn btn--block btn--danger-solid" type="submit"><?= icon('ban') ?> Bloquear usuário</button>
    </form>
  <?php endif; ?>
</section>

<?php require APP_INCLUDES . '/admin_footer.php'; ?>
