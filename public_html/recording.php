<?php
declare(strict_types=1);

/**
 * Entrega o áudio de uma gravação aprovada ao dono dela.
 *
 * Fica fora da /api porque devolve áudio, não JSON, e é GET porque o <audio>
 * só sabe fazer GET. Não altera nada, então não precisa de CSRF.
 *
 * Suporta Range (206): o Safari iOS se recusa a tocar áudio de servidor que
 * ignora Range, e sem isso a playlist ficaria muda justamente no iPhone.
 */

require_once __DIR__ . '/boot.php';
require_once APP_INCLUDES . '/recordings.php';

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

$size  = (int)filesize($path);
$start = 0;
$end   = $size - 1;
$etag  = '"' . substr((string)$recording['file_name'], 0, 32) . '"';

// O nome do arquivo muda a cada substituição, então ele serve de ETag
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}

$range = (string)($_SERVER['HTTP_RANGE'] ?? '');
if ($range !== '') {
    if (preg_match('/^bytes=(\d*)-(\d*)$/', $range, $m) !== 1 || ($m[1] === '' && $m[2] === '')) {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        exit;
    }
    if ($m[1] === '') {
        // "bytes=-500": os últimos 500 bytes
        $start = max(0, $size - (int)$m[2]);
    } else {
        $start = (int)$m[1];
        $end   = $m[2] === '' ? $end : min((int)$m[2], $end);
    }
    if ($start > $end || $start >= $size) {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        exit;
    }
    http_response_code(206);
    header("Content-Range: bytes {$start}-{$end}/{$size}");
}

header('Content-Type: audio/wav');
header('Accept-Ranges: bytes');
header('Content-Length: ' . ($end - $start + 1));
header('Cache-Control: private, max-age=86400');
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
    exit;
}

$handle = fopen($path, 'rb');
if ($handle === false) {
    exit;
}
fseek($handle, $start);
$remaining = $end - $start + 1;
while ($remaining > 0 && !feof($handle)) {
    $chunk = fread($handle, min(65536, $remaining));
    if ($chunk === false) {
        break;
    }
    echo $chunk;
    $remaining -= strlen($chunk);
}
fclose($handle);
