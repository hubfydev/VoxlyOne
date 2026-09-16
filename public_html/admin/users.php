<?php
declare(strict_types=1);

define('ADMIN_AREA', true);
require_once __DIR__ . '/../boot.php';
require_once APP_INCLUDES . '/admin.php';

$admin = require_admin();

$search  = trim((string)($_GET['q'] ?? ''));
$status  = (string)($_GET['status'] ?? '');
$sort    = (string)($_GET['ordem'] ?? 'recentes');
$page    = max(1, (int)($_GET['p'] ?? 1));
$perPage = 25;

$where  = ['1=1'];
$params = [];
if ($search !== '') {
    // Um nome por marcador: prepares nativos não aceitam o mesmo marcador duas vezes
    $where[] = '(u.name LIKE :q_name OR u.email LIKE :q_email)';
    $params[':q_name']  = '%' . $search . '%';
    $params[':q_email'] = '%' . $search . '%';
}
if ($status === 'bloqueados') {
    $where[] = 'u.blocked_at IS NOT NULL';
} elseif ($status === 'ativos') {
    $where[] = 'u.blocked_at IS NULL';
}
$whereSql = implode(' AND ', $where);

// Lista branca de ordenações
$orderSql = match ($sort) {
    'custo_mes'     => 'cost_month DESC, u.id DESC',
    'custo_total'   => 'cost_total DESC, u.id DESC',
    'ultimo_acesso' => 'u.last_login_at IS NULL, u.last_login_at DESC',
    'nome'          => 'u.name ASC',
    default         => 'u.created_at DESC',
};

$stmt = db()->prepare("SELECT COUNT(*) FROM users u WHERE {$whereSql}");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$pages  = max(1, (int)ceil($total / $perPage));
$page   = min($page, $pages);
$offset = ($page - 1) * $perPage;

$sql = "SELECT u.id, u.name, u.email, u.avatar_url, u.created_at, u.last_login_at, u.login_count, u.blocked_at,
               (SELECT COUNT(*) FROM phrases p WHERE p.user_id = u.id)                          AS phrases,
               (SELECT COUNT(*) FROM attempts a WHERE a.user_id = u.id)                         AS attempts,
               COALESCE((SELECT SUM(x.cost_usd) FROM ai_usage x
                          WHERE x.user_id = u.id AND x.created_at >= :month_start), 0)           AS cost_month,
               COALESCE((SELECT SUM(y.cost_usd) FROM ai_usage y WHERE y.user_id = u.id), 0)     AS cost_total
          FROM users u
         WHERE {$whereSql}
         ORDER BY {$orderSql}
         LIMIT :limit OFFSET :offset";

$stmt = db()->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':month_start', date('Y-m-01 00:00:00'));
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$counts = db()->query(
    'SELECT COUNT(*) AS total, COALESCE(SUM(blocked_at IS NOT NULL), 0) AS blocked FROM users'
)->fetch();

function users_url(array $changes): string
{
    $query = array_merge($_GET, $changes);
    $query = array_filter($query, static fn ($v): bool => $v !== '' && $v !== null);
    return '/admin/users.php' . ($query === [] ? '' : '?' . http_build_query($query));
}

$currentUrl = users_url([]);

$pageTitle = 'Usuários — VoxlyOne Admin';
$adminTab  = 'users';
require APP_INCLUDES . '/admin_header.php';
?>

<div class="page-head">
  <div>
    <p class="page-head__eyebrow">Painel administrativo</p>
    <h1>Usuários</h1>
  </div>
</div>

<nav class="admin-chips admin-chips--scroll" aria-label="Filtrar por situação">
  <a class="admin-chip" href="<?= e(users_url(['status' => '', 'p' => ''])) ?>" <?= $status === '' ? 'aria-current="true"' : '' ?>>
    Todos <span><?= num((int)$counts['total']) ?></span>
  </a>
  <a class="admin-chip" href="<?= e(users_url(['status' => 'ativos', 'p' => ''])) ?>" <?= $status === 'ativos' ? 'aria-current="true"' : '' ?>>
    Ativos <span><?= num((int)$counts['total'] - (int)$counts['blocked']) ?></span>
  </a>
  <a class="admin-chip admin-chip--danger" href="<?= e(users_url(['status' => 'bloqueados', 'p' => ''])) ?>" <?= $status === 'bloqueados' ? 'aria-current="true"' : '' ?>>
    Bloqueados <span><?= num((int)$counts['blocked']) ?></span>
  </a>
</nav>

<form class="admin-toolbar" method="get" action="/admin/users.php">
  <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
  <label class="admin-search">
    <span class="sr-only">Buscar por nome ou e-mail</span>
    <?= icon('search', 'admin-search__icon') ?>
    <input class="input" type="search" name="q" value="<?= e($search) ?>" placeholder="Nome ou e-mail" enterkeyhint="search">
  </label>
  <label class="sr-only" for="u-sort">Ordenar por</label>
  <select class="input admin-select" id="u-sort" name="ordem" data-autosubmit>
    <option value="recentes"      <?= $sort === 'recentes' ? 'selected' : '' ?>>Mais recentes</option>
    <option value="ultimo_acesso" <?= $sort === 'ultimo_acesso' ? 'selected' : '' ?>>Último acesso</option>
    <option value="custo_mes"     <?= $sort === 'custo_mes' ? 'selected' : '' ?>>Maior custo no mês</option>
    <option value="custo_total"   <?= $sort === 'custo_total' ? 'selected' : '' ?>>Maior custo total</option>
    <option value="nome"          <?= $sort === 'nome' ? 'selected' : '' ?>>Nome (A–Z)</option>
  </select>
</form>

<?php if ($rows === []): ?>
  <div class="empty">
    <span class="empty__icon"><?= icon('users') ?></span>
    <p class="empty__title">Nenhum usuário encontrado</p>
    <p class="empty__text">Tente outra busca ou outro filtro.</p>
    <a class="btn btn--ghost" href="/admin/users.php">Limpar filtros</a>
  </div>
<?php else: ?>
  <p class="list-count"><?= num($total) ?> usuário<?= $total === 1 ? '' : 's' ?></p>

  <ul class="cards">
    <?php foreach ($rows as $row): ?>
      <?php $isBlocked = $row['blocked_at'] !== null; ?>
      <li class="card admin-user<?= $isBlocked ? ' is-blocked' : '' ?>">
        <a class="admin-user__head" href="/admin/user.php?id=<?= (int)$row['id'] ?>">
          <?= admin_avatar($row, 'admin-avatar--lg') ?>
          <span class="admin-row__text">
            <span class="admin-row__title"><?= e($row['name']) ?></span>
            <span class="admin-row__sub admin-ellipsis"><?= e($row['email']) ?></span>
          </span>
          <?php if ($isBlocked): ?>
            <span class="badge admin-badge-blocked">Bloqueado</span>
          <?php else: ?>
            <span class="badge badge--approved">Ativo</span>
          <?php endif; ?>
        </a>

        <dl class="admin-user__stats">
          <div><dt>Último acesso</dt><dd><?= e(time_ago($row['last_login_at'])) ?></dd></div>
          <div><dt>Frases</dt><dd><?= num((int)$row['phrases']) ?></dd></div>
          <div><dt>Tentativas</dt><dd><?= num((int)$row['attempts']) ?></dd></div>
          <div><dt>Custo no mês</dt><dd class="admin-money"><?= e(money((float)$row['cost_month'])) ?></dd></div>
          <div><dt>Custo total</dt><dd class="admin-money"><?= e(money((float)$row['cost_total'])) ?></dd></div>
          <div><dt>Desde</dt><dd><?= e(format_date($row['created_at'])) ?></dd></div>
        </dl>

        <div class="admin-user__actions">
          <a class="btn btn--sm btn--ghost" href="/admin/user.php?id=<?= (int)$row['id'] ?>"><?= icon('eye') ?> Detalhes</a>
          <form method="post" action="/admin/user_action.php"
                data-confirm="<?= e($isBlocked
                    ? 'Desbloquear ' . $row['name'] . '? A pessoa poderá entrar e usar o app de novo.'
                    : 'Bloquear ' . $row['name'] . '? O acesso ao app é encerrado na hora.') ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>">
            <input type="hidden" name="action" value="<?= $isBlocked ? 'unblock' : 'block' ?>">
            <input type="hidden" name="back" value="<?= e($currentUrl) ?>">
            <?php if ($isBlocked): ?>
              <button class="btn btn--sm btn--soft" type="submit"><?= icon('user-check') ?> Desbloquear</button>
            <?php else: ?>
              <button class="btn btn--sm btn--danger" type="submit"><?= icon('ban') ?> Bloquear</button>
            <?php endif; ?>
          </form>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>

  <?php if ($pages > 1): ?>
    <nav class="pager" aria-label="Paginação">
      <?php if ($page > 1): ?>
        <a class="btn btn--sm btn--ghost" href="<?= e(users_url(['p' => $page - 1])) ?>"><?= icon('chevron-left') ?> Anterior</a>
      <?php endif; ?>
      <span>Página <?= $page ?> de <?= $pages ?></span>
      <?php if ($page < $pages): ?>
        <a class="btn btn--sm btn--ghost" href="<?= e(users_url(['p' => $page + 1])) ?>">Próxima <?= icon('chevron-right') ?></a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>

<?php require APP_INCLUDES . '/admin_footer.php'; ?>
