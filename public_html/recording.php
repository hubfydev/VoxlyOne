<?php
declare(strict_types=1);

/**
 * Entrega o áudio de uma gravação aprovada ao dono dela.
 *
 * Fica fora da /api porque devolve áudio, não JSON, e é GET porque o <audio>
 * só sabe fazer GET. Não altera nada, então não precisa de CSRF.
 *
 * Range e ETag ficam em send_audio_file() (includes/audio_file.php).
 */

require_once __DIR__ . '/boot.php';
require_once APP_INCLUDES . '/recordings.php';
require_once APP_INCLUDES . '/audio_file.php';

$user = current_user();
if ($user === null) {
    http_response_code(401);
    exit;
}

$recording = find_recording((int)$user['id'], (int)($_GET['id'] ?? 0));
if ($recording === null) {
    http_response_code(404);
    exit;
}

try {
    $path = recording_path((int)$user['id'], (string)$recording['file_name']);
} catch (Throwable $ex) {
    error_log('[recording_serve] ' . $ex->getMessage());
    http_response_code(404);
    exit;
}

if (!is_file($path)) {
    error_log('[recording_serve] arquivo ausente para a gravação ' . $recording['id']);
    http_response_code(404);
    exit;
}

// O nome do arquivo muda a cada substituição, então ele serve de ETag
send_audio_file(
    $path,
    'audio/wav',
    '"' . substr((string)$recording['file_name'], 0, 32) . '"',
    'private, max-age=86400'
);
