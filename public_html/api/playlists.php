<?php
declare(strict_types=1);

require_once __DIR__ . '/../boot.php';
require_once APP_INCLUDES . '/playlists.php';

require_method('POST');
$user   = require_auth_api();
$userId = (int)$user['id'];
csrf_require();

// Só POST, como o resto da /api: o verbo real vem no campo 'action'
$action = (string)($_POST['action'] ?? '');

match ($action) {
    'list'            => action_list($userId),
    'items'           => action_items($userId),
    'create'          => action_create($userId),
    'update'          => action_update($userId),
    'delete'          => action_delete($userId),
    'settings'        => action_settings($userId),
    'set_memberships' => action_set_memberships($userId),
    'add_item'        => action_add_item($userId),
    'remove_item'     => action_remove_item($userId),
    'reorder'         => action_reorder($userId),
    default           => json_error('bad_action', 'Ação inválida.', 400),
};

/** Playlist do usuário vinda do POST, ou 404. */
function require_playlist(int $userId, string $field = 'playlist_id'): array
{
    $playlist = find_playlist($userId, (int)($_POST[$field] ?? 0));
    if ($playlist === null) {
        json_error('not_found', 'Playlist não encontrada.', 404);
    }
    return $playlist;
}

/**
 * Gravação do usuário vinda do POST, e APROVADA. A checagem de nota se repete
 * aqui de propósito: a regra "só > 8 entra em playlist" vale no servidor, não só
 * na interface.
 */
function require_approved_recording(int $userId): array
{
    $recording = find_recording($userId, (int)($_POST['recording_id'] ?? 0));
    if ($recording === null) {
        json_error('not_found', 'Gravação não encontrada.', 404);
    }
    if (!recording_is_approved((float)$recording['score'])) {
        json_error('not_approved', 'Só gravações com nota acima de 8 entram em playlists.', 422);
    }
    return $recording;
}

function action_list(int $userId): never
{
    $recordingId = (int)($_POST['recording_id'] ?? 0);
    json_ok(['playlists' => list_playlists($userId, $recordingId)]);
}

function action_items(int $userId): never
{
    $playlist = require_playlist($userId);
    json_ok(['playlist' => $playlist, 'items' => playlist_items($userId, $playlist['id'])]);
}

/** Cria a playlist, vazia. No modal ela já volta marcada e entra ao Salvar. */
function action_create(int $userId): never
{
    [$in, $error] = validate_playlist_input(
        (string)($_POST['name'] ?? ''),
        (string)($_POST['description'] ?? '')
    );
    if ($error !== null) {
        json_error('invalid', $error, 422);
    }

    try {
        $stmt = db()->prepare('INSERT INTO playlists (user_id, name, description) VALUES (?, ?, ?)');
        $stmt->execute([$userId, $in['name'], $in['description']]);
    } catch (PDOException $ex) {
        if (is_duplicate_key($ex)) {
            json_error('duplicate', 'Você já tem uma playlist com esse nome.', 409);
        }
        throw $ex;
    }

    $playlist = find_playlist($userId, (int)db()->lastInsertId());
    $playlist['items_count']   = 0;
    $playlist['has_recording'] = false;

    json_ok(['playlist' => $playlist], 201);
}

/** Renomear e/ou editar a descrição. */
function action_update(int $userId): never
{
    $playlist = require_playlist($userId);

    [$in, $error] = validate_playlist_input(
        (string)($_POST['name'] ?? ''),
        (string)($_POST['description'] ?? '')
    );
    if ($error !== null) {
        json_error('invalid', $error, 422);
    }

    try {
        $stmt = db()->prepare(
            'UPDATE playlists SET name = ?, description = ?, updated_at = NOW()
              WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$in['name'], $in['description'], $playlist['id'], $userId]);
    } catch (PDOException $ex) {
        if (is_duplicate_key($ex)) {
            json_error('duplicate', 'Você já tem uma playlist com esse nome.', 409);
        }
        throw $ex;
    }

    json_ok(['playlist' => find_playlist($userId, $playlist['id'])]);
}

/** Exclui só a playlist: itens somem por cascata; frases, gravações e arquivos ficam. */
function action_delete(int $userId): never
{
    $playlist = require_playlist($userId);

    $stmt = db()->prepare('DELETE FROM playlists WHERE id = ? AND user_id = ?');
    $stmt->execute([$playlist['id'], $userId]);

    json_ok(['id' => $playlist['id']]);
}

/** Shuffle e Repeat são independentes e ficam salvos por playlist. */
function action_settings(int $userId): never
{
    $playlist = require_playlist($userId);

    $shuffle = array_key_exists('shuffle', $_POST) ? ($_POST['shuffle'] === '1') : $playlist['shuffle_enabled'];
    $repeat  = array_key_exists('repeat', $_POST) ? ($_POST['repeat'] === '1') : $playlist['repeat_enabled'];

    // Preferência de reprodução não conta como alteração do conteúdo: sem updated_at
    $stmt = db()->prepare(
        'UPDATE playlists SET shuffle_enabled = ?, repeat_enabled = ? WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([(int)$shuffle, (int)$repeat, $playlist['id'], $userId]);

    json_ok(['shuffle_enabled' => $shuffle, 'repeat_enabled' => $repeat]);
}

/**
 * Checkboxes do modal "Adicionar à playlist": a gravação passa a estar
 * exatamente nas playlists marcadas. Entra no fim das novas; sai das desmarcadas.
 */
function action_set_memberships(int $userId): never
{
    $recording = require_approved_recording($userId);
    $wanted    = post_int_list('playlist_ids');

    $owned = array_column(list_playlists($userId, (int)$recording['id']), null, 'id');

    foreach ($wanted as $playlistId) {
        if (!isset($owned[$playlistId])) {
            json_error('not_found', 'Playlist não encontrada.', 404);
        }
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        foreach ($owned as $playlistId => $playlist) {
            $shouldHave = in_array($playlistId, $wanted, true);
            if ($shouldHave && !$playlist['has_recording']) {
                add_recording_to_playlist($userId, $playlistId, (int)$recording['id']);
            } elseif (!$shouldHave && $playlist['has_recording']) {
                remove_recording_from_playlist($userId, $playlistId, (int)$recording['id']);
            }
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        json_error('internal', 'Não foi possível salvar. Tente novamente.', 500, $ex->getMessage());
    }

    json_ok(['playlists' => list_playlists($userId, (int)$recording['id'])]);
}

function action_add_item(int $userId): never
{
    $playlist  = require_playlist($userId);
    $recording = require_approved_recording($userId);

    $added = add_recording_to_playlist($userId, $playlist['id'], (int)$recording['id']);

    json_ok(['added' => $added, 'items' => playlist_items($userId, $playlist['id'])]);
}

/** Remove só o relacionamento. A gravação e o arquivo continuam existindo. */
function action_remove_item(int $userId): never
{
    $playlist    = require_playlist($userId);
    $recordingId = (int)($_POST['recording_id'] ?? 0);

    if (!remove_recording_from_playlist($userId, $playlist['id'], $recordingId)) {
        json_error('not_found', 'Esta frase não está na playlist.', 404);
    }

    json_ok(['items' => playlist_items($userId, $playlist['id'])]);
}

/** Recebe a ordem completa (item_ids[]) e regrava as posições. */
function action_reorder(int $userId): never
{
    $playlist = require_playlist($userId);
    $order    = post_int_list('item_ids');
    $current  = array_column(playlist_items($userId, $playlist['id']), 'item_id');

    // Precisa ser uma permutação exata dos itens atuais: nem faltar, nem sobrar
    $sortedOrder   = $order;
    $sortedCurrent = $current;
    sort($sortedOrder);
    sort($sortedCurrent);
    if ($sortedOrder !== $sortedCurrent) {
        json_error('stale', 'A playlist mudou. Recarregue a página.', 409);
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        write_positions($playlist['id'], $order);
        touch_playlist($userId, $playlist['id']);
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        json_error('internal', 'Não foi possível reordenar. Tente novamente.', 500, $ex->getMessage());
    }

    json_ok(['items' => playlist_items($userId, $playlist['id'])]);
}
