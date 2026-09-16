<?php
declare(strict_types=1);

/**
 * Áudio da pronúncia correta com voz neural (ver includes/tts.php).
 *
 * GET porque quem pede é o próprio <audio>: no Safari iOS o play() precisa sair
 * direto do toque do usuário, e só um src apontando para cá preserva esse gesto.
 * A geração custa, mas o cookie de sessão é SameSite=Lax — outro site não
 * consegue disparar a chamada com a sessão do usuário. E há cota diária.
 *
 * Qualquer resposta que não seja áudio faz o navegador cair na voz do aparelho.
 */

require_once __DIR__ . '/boot.php';
require_once APP_INCLUDES . '/phrases.php';
require_once APP_INCLUDES . '/rate_limit.php';
require_once APP_INCLUDES . '/openai.php';
require_once APP_INCLUDES . '/tts.php';
require_once APP_INCLUDES . '/ai_usage.php';
require_once APP_INCLUDES . '/audio_file.php';

set_time_limit(40);

$user = current_user();
if ($user === null) {
    http_response_code(401);
    exit;
}

$gender = (string)($_GET['voice'] ?? '');
$phrase = find_phrase((int)$user['id'], (int)($_GET['phrase'] ?? 0));

if ($phrase === null || !in_array($gender, TTS_GENDERS, true)) {
    http_response_code(404);
    exit;
}

try {
    $path = tts_ensure_file((int)$user['id'], $phrase, $gender);
} catch (Throwable $ex) {
    $status = in_array($ex->getCode(), [429, 502], true) ? (int)$ex->getCode() : 500;
    error_log('[tts] frase ' . $phrase['id'] . ' voz ' . $gender . ': ' . $ex->getMessage());
    http_response_code($status);
    exit;
}

// A URL carrega o hash do conteúdo (v=): o navegador pode guardar à vontade
send_audio_file(
    $path,
    'audio/mpeg',
    '"' . tts_hash((string)$phrase['text_en'], $gender) . '"',
    'private, max-age=31536000, immutable'
);
