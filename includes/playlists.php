<?php
declare(strict_types=1);

/**
 * Playlists: coleções ordenadas de gravações aprovadas do próprio usuário.
 * Os itens referenciam recordings.id — nenhum arquivo é copiado.
 * Toda query filtra pelo user_id da sessão, inclusive nos itens (via JOIN).
 */

require_once __DIR__ . '/recordings.php';

/** Playlists do usuário com a contagem de itens. Com $recordingId, marca onde ela já está. */
function list_playlists(int $userId, int $recordingId = 0): array
{
    $stmt = db()->prepare(
        'SELECT pl.id, pl.name, pl.description, pl.shuffle_enabled, pl.repeat_enabled,
                pl.created_at, pl.updated_at,
                COUNT(pi.id) AS items_count,
                COALESCE(SUM(pi.recording_id = :rid), 0) AS has_recording
           FROM playlists pl
           LEFT JOIN playlist_items pi ON pi.playlist_id = pl.id
          WHERE pl.user_id = :uid
          GROUP BY pl.id
          ORDER BY pl.name'
    );
    $stmt->execute([':rid' => $recordingId, ':uid' => $userId]);

    return array_map('normalize_playlist', $stmt->fetchAll());
}

function find_playlist(int $userId, int $playlistId): ?array
{
    $stmt = db()->prepare(
        'SELECT id, name, description, shuffle_enabled, repeat_enabled, created_at, updated_at
           FROM playlists WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([$playlistId, $userId]);
    $row = $stmt->fetch();

    return $row === false ? null : normalize_playlist($row);
}

/** Tipos consistentes para o JSON e para o PHP (o PDO devolve tudo como string). */
function normalize_playlist(array $row): array
{
    $row['id']              = (int)$row['id'];
    $row['shuffle_enabled'] = (bool)(int)$row['shuffle_enabled'];
    $row['repeat_enabled']  = (bool)(int)$row['repeat_enabled'];
    if (array_key_exists('items_count', $row)) {
        $row['items_count'] = (int)$row['items_count'];
    }
    if (array_key_exists('has_recording', $row)) {
        $row['has_recording'] = (int)$row['has_recording'] > 0;
    }
    return $row;
}

/** Itens na ordem manual, já com a frase e a URL do áudio do usuário. */
function playlist_items(int $userId, int $playlistId): array
{
    $stmt = db()->prepare(
        'SELECT pi.id AS item_id, pi.position, r.id AS recording_id, r.score,
                r.file_name, r.audio_seconds, p.id AS phrase_id, p.text_en, p.text_pt
           FROM playlist_items pi
           JOIN playlists pl  ON pl.id = pi.playlist_id AND pl.user_id = :uid
           JOIN recordings r  ON r.id = pi.recording_id AND r.user_id = :uid2
           JOIN phrases p     ON p.id = r.phrase_id
          WHERE pi.playlist_id = :pid
          ORDER BY pi.position ASC, pi.id ASC'
    );
    $stmt->execute([':uid' => $userId, ':uid2' => $userId, ':pid' => $playlistId]);

    return array_map(static fn (array $row): array => [
        'item_id'       => (int)$row['item_id'],
        'recording_id'  => (int)$row['recording_id'],
        'phrase_id'     => (int)$row['phrase_id'],
        'text_en'       => $row['text_en'],
        'text_pt'       => $row['text_pt'],
        'score'         => (float)$row['score'],
        'audio_seconds' => $row['audio_seconds'] !== null ? (int)$row['audio_seconds'] : null,
        'url'           => recording_url(['id' => $row['recording_id'], 'file_name' => $row['file_name']]),
    ], $stmt->fetchAll());
}

/**
 * Valida nome e descrição. Devolve [dados, null] ou [null, mensagem de erro].
 *
 * @return array{0: ?array{name: string, description: ?string}, 1: ?string}
 */
function validate_playlist_input(string $name, string $description): array
{
    $name        = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    $description = trim($description);

    if ($name === '') {
        return [null, 'Dê um nome para a playlist.'];
    }
    if (mb_strlen($name) > 80) {
        return [null, 'O nome deve ter no máximo 80 caracteres.'];
    }
    if (mb_strlen($description) > 300) {
        return [null, 'A descrição deve ter no máximo 300 caracteres.'];
    }

    return [['name' => $name, 'description' => $description === '' ? null : $description], null];
}

/** Violação do UNIQUE (user_id, name). */
function is_duplicate_key(PDOException $ex): bool
{
    return ($ex->errorInfo[1] ?? null) === 1062;
}

/** Marca a playlist como alterada — adicionar ou reordenar itens também conta. */
function touch_playlist(int $userId, int $playlistId): void
{
    $stmt = db()->prepare('UPDATE playlists SET updated_at = NOW() WHERE id = ? AND user_id = ?');
    $stmt->execute([$playlistId, $userId]);
}

/**
 * Adiciona a gravação ao fim da playlist. Idempotente: se já estiver, não duplica.
 * Quem chama garante que playlist e gravação são do usuário.
 */
function add_recording_to_playlist(int $userId, int $playlistId, int $recordingId): bool
{
    $stmt = db()->prepare(
        'INSERT IGNORE INTO playlist_items (playlist_id, recording_id, position)
         SELECT :pid, :rid, COALESCE(MAX(position), 0) + 1
           FROM playlist_items WHERE playlist_id = :pid2'
    );
    $stmt->execute([':pid' => $playlistId, ':rid' => $recordingId, ':pid2' => $playlistId]);

    $added = $stmt->rowCount() > 0;
    if ($added) {
        touch_playlist($userId, $playlistId);
    }
    return $added;
}

/** Tira um item da playlist e fecha o buraco na numeração. Nunca toca no arquivo. */
function remove_recording_from_playlist(int $userId, int $playlistId, int $recordingId): bool
{
    $stmt = db()->prepare(
        'DELETE pi FROM playlist_items pi
           JOIN playlists pl ON pl.id = pi.playlist_id AND pl.user_id = :uid
          WHERE pi.playlist_id = :pid AND pi.recording_id = :rid'
    );
    $stmt->execute([':uid' => $userId, ':pid' => $playlistId, ':rid' => $recordingId]);

    $removed = $stmt->rowCount() > 0;
    if ($removed) {
        renumber_playlist($userId, $playlistId);
        touch_playlist($userId, $playlistId);
    }
    return $removed;
}

/** Reescreve as posições como 1..n na ordem atual. */
function renumber_playlist(int $userId, int $playlistId): void
{
    $ids = array_column(playlist_items($userId, $playlistId), 'item_id');
    write_positions($playlistId, $ids);
}

/** Grava a ordem dada (ids de playlist_items) como posições 1..n. */
function write_positions(int $playlistId, array $itemIds): void
{
    $stmt = db()->prepare('UPDATE playlist_items SET position = ? WHERE id = ? AND playlist_id = ?');
    foreach (array_values($itemIds) as $index => $itemId) {
        $stmt->execute([$index + 1, (int)$itemId, $playlistId]);
    }
}

/** Ids inteiros e únicos vindos de um campo array do POST (ex.: playlist_ids[]). */
function post_int_list(string $field): array
{
    $raw = $_POST[$field] ?? [];
    if (!is_array($raw)) {
        return [];
    }
    $ids = array_filter(array_map('intval', $raw), static fn (int $id): bool => $id > 0);

    return array_values(array_unique($ids));
}
