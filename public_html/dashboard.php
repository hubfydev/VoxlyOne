<?php
declare(strict_types=1);

require_once __DIR__ . '/boot.php';
require_once APP_INCLUDES . '/phrases.php';

$user   = require_auth();
$userId = (int)$user['id'];

// --- filtros vindos da query string -----------------------------------------
$filterCategory = (int)($_GET['categoria'] ?? 0);
$filterLevel    = (string)($_GET['nivel'] ?? '');
$filterStatus   = (string)($_GET['status'] ?? '');
$search         = trim((string)($_GET['q'] ?? ''));
$sort           = (string)($_GET['ordem'] ?? 'recentes');
$page           = max(1, (int)($_GET['p'] ?? 1));
$perPage        = 20;

$where  = ['p.user_id = :uid'];
$params = [':uid' => $userId];

if ($filterCategory > 0) {
    $where[] = 'p.category_id = :cat';
    $params[':cat'] = $filterCategory;
}
if (in_array($filterLevel, PHRASE_LEVELS, true)) {
    $where[] = 'p.level = :level';
    $params[':level'] = $filterLevel;
}
if (in_array($filterStatus, PHRASE_STATUSES, true)) {
    $where[] = 'p.status = :status';
    $params[':status'] = $filterStatus;
}
if ($search !== '') {
    $where[] = '(p.text_pt LIKE :q OR p.text_en LIKE :q)';
    $params[':q'] = '%' . $search . '%';
}

$whereSql = implode(' AND ', $where);

// Lista branca de ordenações — nada vindo do usuário entra na SQL
$orderSql = match ($sort) {
    'nivel'  => 'FIELD(p.level, "beginner","intermediate","advanced") ASC, p.created_at DESC',
    'status' => 'FIELD(p.status, "not_started","in_progress","mastered") ASC, p.created_at DESC',
    default  => 'p.created_at DESC',
};

// --- total, para a paginação -------------------------------------------------
$stmt = db()->prepare("SELECT COUNT(*) FROM phrases p WHERE {$whereSql}");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$pages  = max(1, (int)ceil($total / $perPage));
$page   = min($page, $pages);
$offset = ($page - 1) * $perPage;

// --- página atual ------------------------------------------------------------
$sql = "SELECT p.*, c.name AS category_name
          FROM phrases p
          LEFT JOIN categories c ON c.id = p.category_id
         WHERE {$whereSql}
         ORDER BY {$orderSql}
         LIMIT :limit OFFSET :offset";

$stmt = db()->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
// LIMIT/OFFSET precisam de PARAM_INT explícito (prepares reais, sem emulação)
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$phrases = $stmt->fetchAll();

$categories = list_categories($userId);
$nextId     = next_phrase_id($userId);

/** Preserva os filtros ao trocar de página. */
function page_url(int $page): string
{
    $query = $_GET;
    $query['p'] = $page;

    return '/dashboard.php?' . http_build_query($query);
}

$pageTitle = 'Minhas frases — VoxlyOne';
require APP_INCLUDES . '/header.php';
?>

<div class="page-head">
  <h1>Minhas frases</h1>
  <?php if ($nextId !== null): ?>
    <a class="btn" href="/practice.php?id=<?= $nextId ?>">Praticar agora</a>
  <?php endif; ?>
</div>

<form class="filters" method="get" action="/dashboard.php">
  <input class="filters__search" type="search" name="q" value="<?= e($search) ?>"
         placeholder="Buscar em português ou inglês" aria-label="Buscar frases">

  <div class="filters__row">
    <label class="sr-only" for="f-cat">Categoria</label>
    <select id="f-cat" name="categoria">
      <option value="">Todas as categorias</option>
      <?php foreach ($categories as $cat): ?>
        <option value="<?= (int)$cat['id'] ?>" <?= $filterCategory === (int)$cat['id'] ? 'selected' : '' ?>>
          <?= e($cat['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label class="sr-only" for="f-level">Nível</label>
    <select id="f-level" name="nivel">
      <option value="">Todos os níveis</option>
      <option value="beginner"     <?= $filterLevel === 'beginner' ? 'selected' : '' ?>>Iniciante</option>
      <option value="intermediate" <?= $filterLevel === 'intermediate' ? 'selected' : '' ?>>Intermediário</option>
      <option value="advanced"     <?= $filterLevel === 'advanced' ? 'selected' : '' ?>>Avançado</option>
    </select>

    <label class="sr-only" for="f-status">Status</label>
    <select id="f-status" name="status">
      <option value="">Todos os status</option>
      <option value="not_started" <?= $filterStatus === 'not_started' ? 'selected' : '' ?>>Não iniciadas</option>
      <option value="in_progress" <?= $filterStatus === 'in_progress' ? 'selected' : '' ?>>Em progresso</option>
      <option value="mastered"    <?= $filterStatus === 'mastered' ? 'selected' : '' ?>>Dominadas</option>
    </select>

    <label class="sr-only" for="f-sort">Ordenar por</label>
    <select id="f-sort" name="ordem">
      <option value="recentes" <?= $sort === 'recentes' ? 'selected' : '' ?>>Mais recentes</option>
      <option value="nivel"    <?= $sort === 'nivel' ? 'selected' : '' ?>>Nível</option>
      <option value="status"   <?= $sort === 'status' ? 'selected' : '' ?>>Status</option>
    </select>

    <button class="btn btn--sm" type="submit">Filtrar</button>
  </div>
</form>

<?php if ($total === 0): ?>
  <div class="empty">
    <p class="empty__title">Nenhuma frase por aqui ainda.</p>
    <p class="empty__text">
      Cadastre uma frase em inglês (com a tradução e a pronúncia aproximada)
      para começar a treinar.
    </p>
    <a class="btn" href="/phrase_form.php">Criar primeira frase</a>
  </div>
<?php else: ?>
  <p class="list-count"><?= $total ?> frase<?= $total > 1 ? 's' : '' ?></p>

  <ul class="cards">
    <?php foreach ($phrases as $phrase): ?>
      <li class="card" data-phrase>
        <div class="card__head">
          <span class="badge badge--<?= phrase_status_slug($phrase) ?>">
            <?= e(phrase_status_label($phrase)) ?>
          </span>
          <?php if ($phrase['best_score'] !== null): ?>
            <span class="card__score">Melhor: <?= e(number_format((float)$phrase['best_score'], 1, ',', '')) ?></span>
          <?php endif; ?>
        </div>

        <p class="card__en" data-en><?= e($phrase['text_en']) ?></p>

        <?php if ($phrase['text_pt'] !== null): ?>
          <p class="card__pt" data-pt hidden><?= e($phrase['text_pt']) ?></p>
        <?php endif; ?>

        <?php if ($phrase['phonetic_guide'] !== null): ?>
          <p class="card__phonetic"><?= e($phrase['phonetic_guide']) ?></p>
        <?php endif; ?>

        <div class="card__meta">
          <?php if ($phrase['category_name'] !== null): ?>
            <span><?= e($phrase['category_name']) ?></span>
          <?php endif; ?>
          <span><?= e(match ($phrase['level']) {
              'beginner'     => 'Iniciante',
              'intermediate' => 'Intermediário',
              default        => 'Avançado',
          }) ?></span>
          <span><?= (int)$phrase['attempts_count'] ?> tentativa<?= (int)$phrase['attempts_count'] === 1 ? '' : 's' ?></span>
        </div>

        <div class="card__actions">
          <a class="btn btn--sm" href="/practice.php?id=<?= (int)$phrase['id'] ?>">Praticar</a>
          <?php if ($phrase['text_pt'] !== null): ?>
            <button class="btn btn--sm btn--ghost" type="button" data-toggle-lang>Ver PT</button>
          <?php endif; ?>
          <a class="btn btn--sm btn--ghost" href="/phrase_form.php?id=<?= (int)$phrase['id'] ?>">Editar</a>
          <button class="btn btn--sm btn--danger" type="button"
                  data-delete="<?= (int)$phrase['id'] ?>"
                  data-label="<?= e($phrase['text_en']) ?>">Excluir</button>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>

  <?php if ($pages > 1): ?>
    <nav class="pager" aria-label="Paginação">
      <?php if ($page > 1): ?>
        <a class="btn btn--sm btn--ghost" href="<?= e(page_url($page - 1)) ?>">Anterior</a>
      <?php endif; ?>
      <span>Página <?= $page ?> de <?= $pages ?></span>
      <?php if ($page < $pages): ?>
        <a class="btn btn--sm btn--ghost" href="<?= e(page_url($page + 1)) ?>">Próxima</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>

<a class="fab" href="/phrase_form.php" aria-label="Nova frase">+</a>

<script>window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;</script>
<script src="/assets/js/dashboard.js"></script>

<?php require APP_INCLUDES . '/footer.php'; ?>
