<?php
declare(strict_types=1);

require_once __DIR__ . '/boot.php';
require_once APP_INCLUDES . '/phrases.php';
require_once APP_INCLUDES . '/recordings.php';

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
    // Um nome por marcador: com prepares nativos o PDO não aceita :q repetido (dava 500)
    $where[] = '(p.text_pt LIKE :q_pt OR p.text_en LIKE :q_en)';
    $params[':q_pt'] = '%' . $search . '%';
    $params[':q_en'] = '%' . $search . '%';
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
$sql = "SELECT p.*, c.name AS category_name, r.id AS recording_id
          FROM phrases p
          LEFT JOIN categories c ON c.id = p.category_id
          LEFT JOIN recordings r ON r.phrase_id = p.id AND r.user_id = p.user_id
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
$nextPhrase = $nextId !== null ? find_phrase($userId, $nextId) : null;

// Resumo do progresso para o topo — uma query só
$stmt = db()->prepare(
    "SELECT COUNT(*)                                                          AS total,
            COALESCE(SUM(status = 'mastered'), 0)                             AS mastered,
            COALESCE(SUM(best_score >= :advance AND status <> 'mastered'), 0) AS approved
       FROM phrases WHERE user_id = :uid"
);
$stmt->execute([':advance' => SCORE_TO_ADVANCE, ':uid' => $userId]);
$summary = array_map('intval', $stmt->fetch());

$activeFilters = ($filterCategory > 0 ? 1 : 0)
               + (in_array($filterLevel, PHRASE_LEVELS, true) ? 1 : 0)
               + (in_array($filterStatus, PHRASE_STATUSES, true) ? 1 : 0)
               + ($sort !== 'recentes' ? 1 : 0);
$isFiltering = $activeFilters > 0 || $search !== '';
$firstName   = explode(' ', trim((string)$user['name']))[0] ?? '';

/** Preserva os filtros ao trocar de página. */
function page_url(int $page): string
{
    $query = $_GET;
    $query['p'] = $page;

    return '/dashboard.php?' . http_build_query($query);
}

$pageTitle = 'Minhas frases — VoxlyOne';
$pageStyles = ['dashboard'];
require APP_INCLUDES . '/header.php';
?>

<div class="page-head">
  <div>
    <?php if ($firstName !== ''): ?>
      <p class="page-head__eyebrow">Olá, <?= e($firstName) ?></p>
    <?php endif; ?>
    <h1>Minhas frases</h1>
  </div>
  <a class="btn btn--sm btn--soft" href="/phrase_form.php"><?= icon('plus') ?> Nova</a>
</div>

<?php if ($summary['total'] > 0): ?>
  <?php if ($nextPhrase !== null): ?>
    <section class="dash-hero" aria-labelledby="dash-hero-label">
      <p class="dash-hero__label" id="dash-hero-label"><?= icon('sparkles') ?> Próxima para praticar</p>
      <p class="dash-hero__en"><?= e($nextPhrase['text_en']) ?></p>
      <?php if ($nextPhrase['phonetic_guide'] !== null): ?>
        <p class="dash-hero__phonetic"><?= e($nextPhrase['phonetic_guide']) ?></p>
      <?php endif; ?>
      <a class="btn btn--lg dash-hero__cta" href="/practice.php?id=<?= (int)$nextPhrase['id'] ?>">
        <?= icon('mic') ?> Praticar agora
      </a>
    </section>
  <?php else: ?>
    <section class="dash-hero dash-hero--done">
      <p class="dash-hero__label"><?= icon('crown') ?> Tudo dominado</p>
      <p class="dash-hero__en">Você dominou todas as suas frases.</p>
      <a class="btn btn--lg dash-hero__cta" href="/phrase_form.php"><?= icon('plus') ?> Cadastrar nova frase</a>
    </section>
  <?php endif; ?>

  <ul class="dash-stats" aria-label="Seu progresso">
    <li class="dash-stat">
      <span class="dash-stat__value"><?= $summary['total'] ?></span>
      <span class="dash-stat__label">frases</span>
    </li>
    <li class="dash-stat dash-stat--approved">
      <span class="dash-stat__value"><?= $summary['approved'] ?></span>
      <span class="dash-stat__label">aprovadas</span>
    </li>
    <li class="dash-stat dash-stat--mastered">
      <a href="/mastered.php">
        <span class="dash-stat__value"><?= $summary['mastered'] ?></span>
        <span class="dash-stat__label">dominadas</span>
      </a>
    </li>
  </ul>

  <form class="filters" method="get" action="/dashboard.php" id="filters">
    <div class="filters__bar">
      <label class="filters__search-wrap">
        <span class="sr-only">Buscar frases</span>
        <?= icon('search', 'filters__search-icon') ?>
        <input class="filters__search" type="search" name="q" value="<?= e($search) ?>"
               placeholder="Buscar frase" enterkeyhint="search">
      </label>
      <button class="btn btn--ghost btn--icon filters__toggle" type="button" id="filters-toggle"
              aria-expanded="<?= $activeFilters > 0 ? 'true' : 'false' ?>" aria-controls="filters-panel"
              aria-label="Filtros<?= $activeFilters > 0 ? ' (' . $activeFilters . ' ativos)' : '' ?>">
        <?= icon('sliders-horizontal') ?>
        <?php if ($activeFilters > 0): ?><span class="filters__count"><?= $activeFilters ?></span><?php endif; ?>
      </button>
    </div>

    <div class="filters__panel" id="filters-panel" <?= $activeFilters > 0 ? '' : 'hidden' ?>>
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
        <option value="nivel"    <?= $sort === 'nivel' ? 'selected' : '' ?>>Por nível</option>
        <option value="status"   <?= $sort === 'status' ? 'selected' : '' ?>>Por status</option>
      </select>

      <div class="filters__actions">
        <?php if ($isFiltering): ?>
          <a class="btn btn--sm btn--ghost" href="/dashboard.php">Limpar</a>
        <?php endif; ?>
        <button class="btn btn--sm" type="submit">Aplicar filtros</button>
      </div>
    </div>
  </form>
<?php endif; ?>

<?php if ($total === 0 && !$isFiltering): ?>
  <div class="empty">
    <span class="empty__icon"><?= icon('book-open-text') ?></span>
    <p class="empty__title">Sua primeira frase começa aqui</p>
    <p class="empty__text">
      Cadastre uma frase que você usa no dia a dia. A gente traduz, escreve a
      pronúncia com sons do português e você começa a treinar.
    </p>
    <a class="btn" href="/phrase_form.php"><?= icon('plus') ?> Criar primeira frase</a>
  </div>
<?php elseif ($total === 0): ?>
  <div class="empty">
    <span class="empty__icon"><?= icon('search') ?></span>
    <p class="empty__title">Nada encontrado</p>
    <p class="empty__text">Nenhuma frase combina com a busca ou os filtros.</p>
    <a class="btn btn--ghost" href="/dashboard.php">Limpar filtros</a>
  </div>
<?php else: ?>
  <p class="list-count">
    <?= $isFiltering ? 'Encontradas: ' : '' ?><?= $total ?> frase<?= $total > 1 ? 's' : '' ?>
  </p>

  <ul class="cards">
    <?php foreach ($phrases as $phrase): ?>
      <?php
        $slug   = phrase_status_slug($phrase);
        $levelLabel = match ($phrase['level']) {
            'beginner'     => 'Iniciante',
            'intermediate' => 'Intermediário',
            default        => 'Avançado',
        };
        $attempts = (int)$phrase['attempts_count'];
      ?>
      <li class="card phrase-card phrase-card--<?= e($slug) ?>" data-phrase>
        <div class="card__head">
          <span class="badge badge--<?= e($slug) ?>"><?= e(phrase_status_label($phrase)) ?></span>
          <?php if ($phrase['best_score'] !== null): ?>
            <span class="phrase-card__score" title="Melhor nota">
              <?= icon('star') ?>
              <span class="sr-only">Melhor nota:</span>
              <?= e(number_format((float)$phrase['best_score'], 1, ',', '')) ?>
            </span>
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
          <span><?= e($levelLabel) ?></span>
          <span><?= $attempts ?> tentativa<?= $attempts === 1 ? '' : 's' ?></span>
          <?php if ($phrase['recording_id'] !== null): ?>
            <span class="card__meta--voice"><?= icon('headphones') ?> Sua voz</span>
          <?php endif; ?>
        </div>

        <div class="phrase-card__actions">
          <a class="btn btn--sm phrase-card__practice" href="/practice.php?id=<?= (int)$phrase['id'] ?>">
            <?= icon('mic') ?> Praticar
          </a>
          <?php if ($phrase['text_pt'] !== null): ?>
            <button class="btn btn--sm btn--ghost btn--icon" type="button" data-toggle-lang
                    aria-pressed="false" aria-label="Ver em português" title="Ver em português"><?= icon('languages') ?></button>
          <?php endif; ?>
          <?php if ($phrase['recording_id'] !== null): ?>
            <button class="btn btn--sm btn--ghost btn--icon" type="button"
                    data-playlist="<?= (int)$phrase['recording_id'] ?>"
                    data-label="<?= e($phrase['text_en']) ?>"
                    aria-label="Adicionar à playlist" title="Adicionar à playlist"><?= icon('list-plus') ?></button>
          <?php elseif ($phrase['best_score'] !== null && recording_is_approved((float)$phrase['best_score'])): ?>
            <?php /* Aprovada antes das playlists existirem: o áudio não foi guardado */ ?>
            <button class="btn btn--sm btn--ghost btn--icon" type="button" disabled data-playlist-unavailable
                    aria-label="Adicionar à playlist (pratique de novo e tire mais de 8 para salvar sua gravação)"
                    title="Pratique de novo e tire mais de 8 para salvar sua gravação"><?= icon('list-plus') ?></button>
          <?php endif; ?>
          <a class="btn btn--sm btn--ghost btn--icon" href="/phrase_form.php?id=<?= (int)$phrase['id'] ?>"
             aria-label="Editar" title="Editar"><?= icon('pencil') ?></a>
          <button class="btn btn--sm btn--ghost btn--icon phrase-card__delete" type="button"
                  data-delete="<?= (int)$phrase['id'] ?>"
                  data-label="<?= e($phrase['text_en']) ?>"
                  aria-label="Excluir" title="Excluir"><?= icon('trash-2') ?></button>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>

  <?php if ($pages > 1): ?>
    <nav class="pager" aria-label="Paginação">
      <?php if ($page > 1): ?>
        <a class="btn btn--sm btn--ghost" href="<?= e(page_url($page - 1)) ?>"><?= icon('chevron-left') ?> Anterior</a>
      <?php endif; ?>
      <span>Página <?= $page ?> de <?= $pages ?></span>
      <?php if ($page < $pages): ?>
        <a class="btn btn--sm btn--ghost" href="<?= e(page_url($page + 1)) ?>">Próxima <?= icon('chevron-right') ?></a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>

<script>window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;</script>
<script type="module" src="<?= e(asset('/assets/js/dashboard.js')) ?>"></script>

<?php require APP_INCLUDES . '/footer.php'; ?>
