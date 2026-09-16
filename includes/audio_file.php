<?php
declare(strict_types=1);

/**
 * Entrega um arquivo de áudio com suporte a Range (206) e ETag (304).
 *
 * O Safari iOS se recusa a tocar áudio de servidor que ignora Range — sem isso
 * a playlist e a pronúncia ficariam mudas justamente no iPhone.
 * Usado pelo recording.php (voz do usuário) e pelo tts.php (voz de referência).
 */
function send_audio_file(string $path, string $contentType, string $etag, string $cacheControl): never
{
    $size  = (int)filesize($path);
    $start = 0;
    $end   = $size - 1;

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

    header('Content-Type: ' . $contentType);
    header('Accept-Ranges: bytes');
    header('Content-Length: ' . ($end - $start + 1));
    header('Cache-Control: ' . $cacheControl);
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
    exit;
}
