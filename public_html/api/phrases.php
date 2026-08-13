<?php
declare(strict_types=1);

require_once __DIR__ . '/../boot.php';
require_once APP_INCLUDES . '/phrases.php';

require_method('POST');
$user = require_auth_api();
csrf_require();

// Só POST: PUT/DELETE em hospedagem compartilhada não chegam em $_POST e
// costumam ser bloqueados por WAF. O verbo real vem no campo 'action'.
$action = (string)($_POST['action'] ?? '');

match ($action) {
    'create' => action_create($user),
    'update' => action_update($user),
    'delete' => action_delete($user),
    'skip'   => action_skip($user),
    default  => json_error('bad_action', 'Ação inválida.', 400),
};

/**
 * Valida e normaliza os campos vindos do formulário.
 * Server-side sempre — nunca confiar só no JS (RF-03).
 *
 * @return array{text_pt: ?string, text_en: string, phonetic: ?string, level: string, category: string}
 */
function read_phrase_input(): array
{
    $textEn    = trim((string)($_POST['text_en'] ?? ''));
    $textPt    = trim((string)($_POST['text_pt'] ?? ''));
    $phonetic  = trim((string)($_POST['phonetic_guide'] ?? ''));
    $level     = (string)($_POST['level'] ?? '');
    $category  = trim((string)($_POST['category'] ?? ''));

    if ($textEn === '') {
        json_error('text_en_required', 'A frase em inglês é obrigatória.', 422);
    }
    if (mb_strlen($textEn) > 200 || mb_strlen($textPt) > 200) {
        json_error('text_too_long', 'As frases devem ter no máximo 200 caracteres.', 422);
    }
    if (mb_strlen($phonetic) > 400) {
        json_error('phonetic_too_long', 'A pronúncia aproximada deve ter no máximo 400 caracteres.', 422);
    }
    if (!in_array($level, PHRASE_LEVELS, true)) {
        json_error('bad_level', 'Nível inválido.', 422);
    }

    return [
        'text_pt'  => $textPt === '' ? null : $textPt,
        'text_en'  => $textEn,
        'phonetic' => $phonetic === '' ? null : $phonetic,
        'level'    => $level,
        'category' => $category,
    ];
}

function action_create(array $user): never
{
    $in         = read_phrase_input();
    $categoryId = get_or_create_category((int)$user['id'], $in['category']);

    $stmt = db()->prepare(
        'INSERT INTO phrases (user_id, category_id, text_pt, text_en, phonetic_guide, level)
              VALUES (:uid, :cat, :pt, :en, :phon, :level)'
    );
    $stmt->execute([
        ':uid'   => $user['id'],
        ':cat'   => $categoryId,
        ':pt'    => $in['text_pt'],
        ':en'    => $in['text_en'],
        ':phon'  => $in['phonetic'],
        ':level' => $in['level'],
    ]);

    json_ok(['id' => (int)db()->lastInsertId()], 201);
}

function action_update(array $user): never
{
    $id = (int)($_POST['id'] ?? 0);
    if (find_phrase((int)$user['id'], $id) === null) {
        json_error('not_found', 'Frase não encontrada.', 404);
    }

    $in         = read_phrase_input();
    $categoryId = get_or_create_category((int)$user['id'], $in['category']);

    // O user_id no WHERE é redundante com a checagem acima, mas mantém a regra
    // "toda query filtra por user_id" verdadeira em qualquer leitura isolada.
    $stmt = db()->prepare(
        'UPDATE phrases
            SET category_id = :cat, text_pt = :pt, text_en = :en,
                phonetic_guide = :phon, level = :level
          WHERE id = :id AND user_id = :uid'
    );
    $stmt->execute([
        ':cat'   => $categoryId,
        ':pt'    => $in['text_pt'],
        ':en'    => $in['text_en'],
        ':phon'  => $in['phonetic'],
        ':level' => $in['level'],
        ':id'    => $id,
        ':uid'   => $user['id'],
    ]);

    json_ok(['id' => $id]);
}

/**
 * 'Pular por agora' (RF-10): só mexe em last_attempt_at, que é o critério de
 * ordenação da fila — a frase vai para o fim sem registrar tentativa nem nota.
 */
function action_skip(array $user): never
{
    $id = (int)($_POST['id'] ?? 0);

    $stmt = db()->prepare(
        'UPDATE phrases SET last_attempt_at = NOW() WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([$id, $user['id']]);

    if ($stmt->rowCount() === 0) {
        json_error('not_found', 'Frase não encontrada.', 404);
    }

    json_ok(['next_id' => next_phrase_id((int)$user['id'])]);
}

function action_delete(array $user): never
{
    $id = (int)($_POST['id'] ?? 0);

    // As tentativas somem junto pela FK ON DELETE CASCADE
    $stmt = db()->prepare('DELETE FROM phrases WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $user['id']]);

    if ($stmt->rowCount() === 0) {
        json_error('not_found', 'Frase não encontrada.', 404);
    }

    json_ok(['id' => $id]);
}
