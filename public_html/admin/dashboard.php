<?php
declare(strict_types=1);

define('ADMIN_AREA', true);
require_once __DIR__ . '/../boot.php';
require_once APP_INCLUDES . '/admin.php';

$admin = require_admin();

// --- usuários ---------------------------------------------------------------------
$users = db()->query(
    "SELECT COUNT(*)                                                  AS total,
            COALESCE(SUM(created_at >= NOW() - INTERVAL 7 DAY), 0)    AS new_7d,
            COALESCE(SUM(last_login_at >= NOW() - INTERVAL 7 DAY), 0) AS login_7d,
            COALESCE(SUM(blocked_at IS NOT NULL), 0)                  AS blocked
       FROM users"
)->fetch();

$active7d = (int)db()->query(
    'SELECT COUNT(DISTINCT user_id) FROM attempts WHERE created_at >= NOW() - INTERVAL 7 DAY'
)->fetchColumn();

// --- custos -------------------------------------------------------------------------
$costs = db()->query(
    "SELECT COALESCE(SUM(CASE WHEN created_at >= CURDATE() THEN cost_usd END), 0)                          AS today,
            COALESCE(SUM(CASE WHEN created_at >= CURDATE() - INTERVAL 6 DAY THEN cost_usd END), 0)         AS week,
            COALESCE(SUM(CASE WHEN created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN cost_usd END), 0) AS month,
            COALESCE(SUM(cost_usd), 0)                                                                     AS total,
            COUNT(CASE WHEN created_at >= CURDATE() THEN 1 END)                                            AS calls_today
       FROM ai_usage"
)->fetch();

$byAction = db()->query(
    "SELECT action, COUNT(*) AS calls, COALESCE(SUM(cost_usd), 0) AS cost
       FROM ai_usage
      WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
      GROUP BY action"
)->fetchAll(PDO::FETCH_UNIQUE);

// Custo por dia nos últimos 30 dias, com os dias sem uso preenchidos com zero
$rows = db()->query(
    'SELECT DATE(created_at) AS day, SUM(cost_usd) AS cost
       FROM ai_usage
      WHERE created_at >= CURDATE() - INTERVAL 29 DAY
      GROUP BY DATE(created_at)'
)->fetchAll(PDO::FETCH_KEY_PAIR);

$daily = [];
for ($i = 29; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} days"));
    $daily[$day] = (float)($rows[$day] ?? 0);
}
$peakDaily  = max($daily);
$chartScale = $peakDaily > 0 ? $peakDaily : 1;

$topUsers = db()->query(
    "SELECT u.id, u.name, u.email, u.avatar_url, u.blocked_at,
            SUM(x.cost_usd) AS cost, COUNT(*) AS calls
       FROM ai_usage x
       JOIN users u ON u.id = x.user_id
      WHERE x.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
      GROUP BY u.id, u.name, u.email, u.avatar_url, u.blocked_at
      ORDER BY cost DESC
      LIMIT 5"
)->fetchAll();

$trackingSince = app_setting('usage_tracking_since');

$pageTitle = 'Painel — VoxlyOne Admin';
$adminTab  = 'dashboard';
require APP_INCLUDES . '/admin_header.php';
?>

<div class="page-head">
  <div>
    <p class="page-head__eyebrow">Olá, <?= e($admin['username']) ?></p>
    <h1>Painel</h1>
  </div>
  <span class="admin-date"><?= icon('calendar') ?> <?= e(format_date(date('Y-m-d'))) ?></span>
</div>

<section class="admin-hero" aria-label="Custo de IA neste mês">
  <p class="admin-hero__label"><?= icon('circle-dollar-sign') ?> Custo de IA neste mês</p>
  <p class="admin-hero__value"><?= e(money((float)$costs['month'])) ?></p>
  <div class="admin-hero__row">
    <span><strong><?= e(money((float)$costs['today'])) ?></strong> hoje</span>
    <span><strong><?= e(money((float)$costs['week'])) ?></strong> 7 dias</span>
    <span><strong><?= e(money((float)$costs['total'])) ?></strong> total</span>
  </div>
  <a class="btn btn--lg admin-hero__cta" href="/admin/costs.php"><?= icon('chart-column') ?> Ver custos em detalhe</a>
</section>

<ul class="admin-stats" aria-label="Usuários">
  <li class="admin-stat">
    <a href="/admin/users.php">
      <span class="admin-stat__icon"><?= icon('users') ?></span>
      <span class="admin-stat__value"><?= num((int)$users['total']) ?></span>
      <span class="admin-stat__label">usuários</span>
    </a>
  </li>
  <li class="admin-stat admin-stat--brand">
    <span class="admin-stat__icon"><?= icon('activity') ?></span>
    <span class="admin-stat__value"><?= num($active7d) ?></span>
    <span class="admin-stat__label">praticaram em 7 dias</span>
  </li>
  <li class="admin-stat admin-stat--success">
    <span class="admin-stat__icon"><?= icon('trending-up') ?></span>
    <span class="admin-stat__value"><?= num((int)$users['new_7d']) ?></span>
    <span class="admin-stat__label">novos em 7 dias</span>
  </li>
  <li class="admin-stat admin-stat--danger">
    <a href="/admin/users.php?status=bloqueados">
      <span class="admin-stat__icon"><?= icon('ban') ?></span>
      <span class="admin-stat__value"><?= num((int)$users['blocked']) ?></span>
      <span class="admin-stat__label">bloqueados</span>
    </a>
  </li>
</ul>

<h2 class="section-title">
  Custo por dia
  <span class="list-count">últimos 30 dias</span>
</h2>
<section class="card admin-chart" aria-label="Gráfico de custo por dia">
  <div class="admin-chart__bars">
    <?php foreach ($daily as $day => $cost): ?>
      <?php $isToday = $day === date('Y-m-d'); ?>
      <span class="admin-chart__bar<?= $isToday ? ' is-today' : '' ?>"
            style="--h: <?= round($cost / $chartScale * 100, 1) ?>%"
            title="<?= e(format_date($day) . ': ' . money($cost)) ?>">
        <span class="sr-only"><?= e(format_date($day) . ': ' . money($cost)) ?></span>
      </span>
    <?php endforeach; ?>
  </div>
  <div class="admin-chart__axis">
    <span><?= e(format_date(array_key_first($daily))) ?></span>
    <span>maior dia: <?= e(money($peakDaily)) ?></span>
    <span>hoje</span>
  </div>
</section>

<h2 class="section-title">Uso da IA neste mês</h2>
<ul class="admin-actions">
  <?php foreach (AI_ACTIONS as $action): ?>
    <?php $row = $byAction[$action] ?? ['calls' => 0, 'cost' => 0]; ?>
    <li class="card admin-action admin-action--<?= e($action) ?>">
      <span class="admin-action__icon"><?= icon(match ($action) { 'analyze' => 'mic', 'translate' => 'languages', default => 'volume-2' }) ?></span>
      <span class="admin-action__text">
        <span class="admin-action__name"><?= e(action_label($action)) ?></span>
        <span class="admin-action__calls"><?= num((int)$row['calls']) ?> chamada<?= (int)$row['calls'] === 1 ? '' : 's' ?></span>
      </span>
      <span class="admin-action__cost"><?= e(money((float)$row['cost'])) ?></span>
    </li>
  <?php endforeach; ?>
</ul>

<h2 class="section-title">
  Quem mais custou no mês
  <a class="list-count" href="/admin/users.php?ordem=custo_mes">ver todos</a>
</h2>
<?php if ($topUsers === []): ?>
  <div class="empty">
    <span class="empty__icon"><?= icon('coins') ?></span>
    <p class="empty__title">Nenhum uso de IA neste mês</p>
    <p class="empty__text">Os custos aparecem aqui assim que alguém analisar, traduzir ou ouvir uma frase.</p>
  </div>
<?php else: ?>
  <ul class="card admin-list">
    <?php foreach ($topUsers as $index => $row): ?>
      <li>
        <a class="admin-row" href="/admin/user.php?id=<?= (int)$row['id'] ?>">
          <span class="admin-rank"><?= $index + 1 ?></span>
          <?= admin_avatar($row) ?>
          <span class="admin-row__text">
            <span class="admin-row__title"><?= e($row['name']) ?></span>
            <span class="admin-row__sub"><?= num((int)$row['calls']) ?> chamadas</span>
          </span>
          <span class="admin-row__value"><?= e(money((float)$row['cost'])) ?></span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<p class="admin-footnote">
  <?= icon('info') ?>
  <span>
    Custos calculados com os preços em <a href="/admin/costs.php#precos">Custos → Preços</a>.
    <?php if ($trackingSince !== null): ?>
      Monitoramento desde <?= e(format_date($trackingSince)) ?>.
    <?php endif; ?>
  </span>
</p>

<?php require APP_INCLUDES . '/admin_footer.php'; ?>
