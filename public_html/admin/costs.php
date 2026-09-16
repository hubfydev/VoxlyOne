<?php
declare(strict_types=1);

define('ADMIN_AREA', true);
require_once __DIR__ . '/../boot.php';
require_once APP_INCLUDES . '/admin.php';

$admin = require_admin();

// --- salvar preços ---------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_admin_post('/admin/costs.php#precos');

    $posted = $_POST['prices'] ?? [];
    $clean  = [];
    if (is_array($posted)) {
        foreach ($posted as $model => $fields) {
            $model = (string)$model;
            if (preg_match('/^[A-Za-z0-9._-]{1,60}$/', $model) !== 1 || !is_array($fields)) {
                continue;
            }
            foreach (AI_PRICE_FIELDS as $field) {
                $raw = str_replace(',', '.', trim((string)($fields[$field] ?? '0')));
                if (!is_numeric($raw) || (float)$raw < 0 || (float)$raw > 10000) {
                    admin_flash('error', "Preço inválido em {$model}. Use números, como 2.50.");
                    redirect('/admin/costs.php#precos');
                }
                $clean[$model][$field] = round((float)$raw, 4);
            }
        }
    }

    save_app_setting('ai_prices', (string)json_encode($clean));
    admin_flash('success', 'Preços salvos. Valem para as próximas chamadas; o histórico não muda.');
    redirect('/admin/costs.php#precos');
}

$period = cost_period((string)($_GET['periodo'] ?? 'mes'));
[$periodWhere, $periodParams] = period_sql($period);

$stmt = db()->prepare(
    "SELECT COUNT(*) AS calls, COALESCE(SUM(cost_usd), 0) AS cost,
            COALESCE(SUM(text_input_tokens), 0)   AS text_in,
            COALESCE(SUM(audio_input_tokens), 0)  AS audio_in,
            COALESCE(SUM(text_output_tokens), 0)  AS text_out,
            COALESCE(SUM(audio_output_tokens), 0) AS audio_out,
            COUNT(DISTINCT user_id)               AS users
       FROM ai_usage WHERE {$periodWhere}"
);
$stmt->execute($periodParams);
$summary = $stmt->fetch();

$stmt = db()->prepare(
    "SELECT action, COUNT(*) AS calls, COALESCE(SUM(cost_usd), 0) AS cost,
            COALESCE(SUM(text_input_tokens + text_output_tokens), 0)   AS text_tokens,
            COALESCE(SUM(audio_input_tokens + audio_output_tokens), 0) AS audio_tokens,
            COALESCE(MAX(estimated), 0) AS estimated
       FROM ai_usage WHERE {$periodWhere} GROUP BY action"
);
$stmt->execute($periodParams);
$byAction = $stmt->fetchAll(PDO::FETCH_UNIQUE);

$stmt = db()->prepare(
    "SELECT model, COUNT(*) AS calls, COALESCE(SUM(cost_usd), 0) AS cost,
            COALESCE(SUM(text_input_tokens), 0) AS text_in, COALESCE(SUM(audio_input_tokens), 0) AS audio_in,
            COALESCE(SUM(text_output_tokens), 0) AS text_out, COALESCE(SUM(audio_output_tokens), 0) AS audio_out
       FROM ai_usage WHERE {$periodWhere} GROUP BY model ORDER BY cost DESC"
);
$stmt->execute($periodParams);
$byModel = $stmt->fetchAll();

[$userWhere, $userParams] = period_sql($period, 'x.created_at', 'u');
$stmt = db()->prepare(
    "SELECT x.user_id AS id, u.name, u.email, u.avatar_url, u.blocked_at,
            COUNT(*) AS calls, SUM(x.cost_usd) AS cost
       FROM ai_usage x
       LEFT JOIN users u ON u.id = x.user_id
      WHERE {$userWhere}
      GROUP BY x.user_id, u.name, u.email, u.avatar_url, u.blocked_at
      ORDER BY cost DESC
      LIMIT 20"
);
$stmt->execute($userParams);
$byUser = $stmt->fetchAll();

$prices  = ai_prices();
$users   = max(1, (int)$summary['users']);
$periods = ['hoje' => 'Hoje', '7d' => '7 dias', '30d' => '30 dias', 'mes' => 'Este mês', 'mes_anterior' => 'Mês anterior', 'tudo' => 'Tudo'];

$pageTitle = 'Custos — VoxlyOne Admin';
$adminTab  = 'costs';
require APP_INCLUDES . '/admin_header.php';
?>

<div class="page-head">
  <div>
    <p class="page-head__eyebrow">Painel administrativo</p>
    <h1>Custos de IA</h1>
  </div>
</div>

<nav class="admin-chips admin-chips--scroll" aria-label="Período">
  <?php foreach ($periods as $key => $label): ?>
    <a class="admin-chip" href="/admin/costs.php?periodo=<?= e($key) ?>" <?= $period['key'] === $key ? 'aria-current="true"' : '' ?>><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>

<section class="admin-hero admin-hero--compact" aria-label="Resumo do período">
  <p class="admin-hero__label"><?= icon('coins') ?> <?= e($period['label']) ?></p>
  <p class="admin-hero__value"><?= e(money((float)$summary['cost'])) ?></p>
  <div class="admin-hero__row">
    <span><strong><?= num((int)$summary['calls']) ?></strong> chamadas</span>
    <span><strong><?= num((int)$summary['users']) ?></strong> usuários</span>
    <span><strong><?= e(money((float)$summary['cost'] / $users)) ?></strong> por usuário</span>
  </div>
</section>

<h2 class="section-title">Por recurso</h2>
<ul class="admin-actions">
  <?php foreach (AI_ACTIONS as $action): ?>
    <?php
      $row  = $byAction[$action] ?? ['calls' => 0, 'cost' => 0, 'text_tokens' => 0, 'audio_tokens' => 0, 'estimated' => 0];
      $calls = (int)$row['calls'];
    ?>
    <li class="card admin-action admin-action--<?= e($action) ?>">
      <span class="admin-action__icon"><?= icon(match ($action) { 'analyze' => 'mic', 'translate' => 'languages', default => 'volume-2' }) ?></span>
      <span class="admin-action__text">
        <span class="admin-action__name"><?= e(action_label($action)) ?></span>
        <span class="admin-action__calls">
          <?= num($calls) ?> chamada<?= $calls === 1 ? '' : 's' ?>
          · média <?= e(money($calls > 0 ? (float)$row['cost'] / $calls : 0)) ?>
          <?= (int)$row['estimated'] === 1 ? '· tokens estimados' : '' ?>
        </span>
      </span>
      <span class="admin-action__cost"><?= e(money((float)$row['cost'])) ?></span>
    </li>
  <?php endforeach; ?>
</ul>

<h2 class="section-title">Tokens</h2>
<ul class="admin-stats admin-stats--compact">
  <li class="admin-stat"><span class="admin-stat__value"><?= num((int)$summary['text_in']) ?></span><span class="admin-stat__label">texto enviado</span></li>
  <li class="admin-stat"><span class="admin-stat__value"><?= num((int)$summary['audio_in']) ?></span><span class="admin-stat__label">áudio enviado</span></li>
  <li class="admin-stat"><span class="admin-stat__value"><?= num((int)$summary['text_out']) ?></span><span class="admin-stat__label">texto recebido</span></li>
  <li class="admin-stat"><span class="admin-stat__value"><?= num((int)$summary['audio_out']) ?></span><span class="admin-stat__label">áudio recebido</span></li>
</ul>

<?php if ($byModel !== []): ?>
  <h2 class="section-title">Por modelo</h2>
  <div class="card admin-table-wrap">
    <table class="admin-table">
      <thead><tr><th>Modelo</th><th class="num">Chamadas</th><th class="num">Texto in/out</th><th class="num">Áudio in/out</th><th class="num">Custo</th></tr></thead>
      <tbody>
        <?php foreach ($byModel as $row): ?>
          <tr>
            <td><code><?= e($row['model']) ?></code></td>
            <td class="num"><?= num((int)$row['calls']) ?></td>
            <td class="num"><?= num((int)$row['text_in']) ?> / <?= num((int)$row['text_out']) ?></td>
            <td class="num"><?= num((int)$row['audio_in']) ?> / <?= num((int)$row['audio_out']) ?></td>
            <td class="num"><strong><?= e(money((float)$row['cost'])) ?></strong></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<h2 class="section-title">
  Por usuário
  <span class="list-count">20 que mais custaram</span>
</h2>
<?php if ($byUser === []): ?>
  <div class="empty">
    <span class="empty__icon"><?= icon('coins') ?></span>
    <p class="empty__title">Sem uso de IA neste período</p>
    <p class="empty__text">Escolha outro período acima.</p>
  </div>
<?php else: ?>
  <ul class="card admin-list">
    <?php foreach ($byUser as $index => $row): ?>
      <li>
        <?php if ($row['id'] !== null): ?>
          <a class="admin-row" href="/admin/user.php?id=<?= (int)$row['id'] ?>">
        <?php else: ?>
          <span class="admin-row">
        <?php endif; ?>
          <span class="admin-rank"><?= $index + 1 ?></span>
          <?= admin_avatar($row['id'] !== null ? $row : ['name' => '?']) ?>
          <span class="admin-row__text">
            <span class="admin-row__title"><?= $row['id'] !== null ? e($row['name']) : 'Contas excluídas' ?></span>
            <span class="admin-row__sub admin-ellipsis"><?= num((int)$row['calls']) ?> chamadas<?= $row['id'] !== null ? ' · ' . e($row['email']) : '' ?></span>
          </span>
          <span class="admin-row__value"><?= e(money((float)$row['cost'])) ?></span>
        <?= $row['id'] !== null ? '</a>' : '</span>' ?>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<details class="admin-prices-box" id="precos">
<summary class="card admin-prices-box__summary">
  <span class="admin-action__icon"><?= icon('settings') ?></span>
  <span class="admin-action__text">
    <span class="admin-action__name">Preços por modelo</span>
    <span class="admin-action__calls">US$ por 1 milhão de tokens · editável</span>
  </span>
  <?= icon('chevron-down', 'admin-prices-box__chevron') ?>
</summary>
<form class="card admin-prices" method="post" action="/admin/costs.php">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <p class="admin-muted">
    US$ por <strong>1 milhão de tokens</strong>, conforme a tabela oficial da OpenAI. Alterar vale para
    as próximas chamadas — o custo já registrado não é recalculado.
  </p>
  <?php foreach ($prices as $model => $fields): ?>
    <fieldset class="admin-price">
      <legend><code><?= e((string)$model) ?></code></legend>
      <?php foreach (['text_in' => 'Texto entrada', 'text_out' => 'Texto saída', 'audio_in' => 'Áudio entrada', 'audio_out' => 'Áudio saída'] as $field => $label): ?>
        <label class="field">
          <span class="field__label"><?= e($label) ?></span>
          <input type="text" inputmode="decimal" name="prices[<?= e((string)$model) ?>][<?= e($field) ?>]"
                 value="<?= e(rtrim(rtrim(number_format((float)$fields[$field], 4, '.', ''), '0'), '.')) ?>">
        </label>
      <?php endforeach; ?>
    </fieldset>
  <?php endforeach; ?>
  <button class="btn btn--block" type="submit"><?= icon('check') ?> Salvar preços</button>
</form>
</details>

<?php require APP_INCLUDES . '/admin_footer.php'; ?>
