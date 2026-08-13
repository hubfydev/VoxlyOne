<?php
declare(strict_types=1);

/** Helpers de domínio das frases. Toda query filtra por user_id da sessão. */

const PHRASE_LEVELS = ['beginner', 'intermediate', 'advanced'];
const PHRASE_STATUSES = ['not_started', 'in_progress', 'mastered'];

/** Categorias do usuário, para o datalist do formulário e o filtro do dashboard. */
function list_categories(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT id, name FROM categories WHERE user_id = ? ORDER BY name'
    );
    $stmt->execute([$userId]);

    return $stmt->fetchAll();
}

/**
 * Devolve o id da categoria pelo nome, criando se ainda não existir.
 * Nome vazio → NULL (frase sem categoria).
 */
function get_or_create_category(int $userId, string $name): ?int
{
    $name = trim($name);
    if ($name === '') {
        return null;
    }
    $name = mb_substr($name, 0, 80);

    $stmt = db()->prepare('SELECT id FROM categories WHERE user_id = ? AND name = ?');
    $stmt->execute([$userId, $name]);
    $id = $stmt->fetchColumn();

    if ($id !== false) {
        return (int)$id;
    }

    $stmt = db()->prepare('INSERT INTO categories (user_id, name) VALUES (?, ?)');
    $stmt->execute([$userId, $name]);

    return (int)db()->lastInsertId();
}

/** Uma frase do usuário, ou null se não existir ou for de outro usuário. */
function find_phrase(int $userId, int $phraseId): ?array
{
    $stmt = db()->prepare(
        'SELECT p.*, c.name AS category_name
           FROM phrases p
           LEFT JOIN categories c ON c.id = p.category_id
          WHERE p.id = ? AND p.user_id = ?'
    );
    $stmt->execute([$phraseId, $userId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * Rótulo visual do card. 'Aprovada' é DERIVADO do best_score — o ENUM do
 * schema tem só três valores (RF-04).
 */
function phrase_status_label(array $phrase): string
{
    if ($phrase['status'] === 'mastered') {
        return 'Dominada';
    }
    if ($phrase['best_score'] !== null && (float)$phrase['best_score'] >= SCORE_TO_ADVANCE) {
        return 'Aprovada';
    }
    if ($phrase['status'] === 'in_progress') {
        return 'Em progresso';
    }

    return 'Não iniciada';
}

/** Slug do rótulo, para a classe CSS do badge. */
function phrase_status_slug(array $phrase): string
{
    return match (phrase_status_label($phrase)) {
        'Dominada'     => 'mastered',
        'Aprovada'     => 'approved',
        'Em progresso' => 'progress',
        default        => 'new',
    };
}

/**
 * Próxima frase da fila (RF-10). SEM filtro por best_score: filtrar < 8 faria
 * frases aprovadas sumirem da fila para sempre. Aprovadas-não-dominadas vão
 * para o FIM pela expressão do ORDER BY.
 */
function next_phrase_id(int $userId): ?int
{
    $stmt = db()->prepare(
        "SELECT id FROM phrases
          WHERE user_id = ? AND status <> 'mastered'
          ORDER BY (COALESCE(best_score,0) >= " . SCORE_TO_ADVANCE . ") ASC,
                   last_attempt_at IS NULL DESC,
                   last_attempt_at ASC,
                   created_at ASC
          LIMIT 1"
    );
    $stmt->execute([$userId]);
    $id = $stmt->fetchColumn();

    return $id === false ? null : (int)$id;
}
