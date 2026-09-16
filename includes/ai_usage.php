<?php
declare(strict_types=1);

/**
 * Registro de uso e custo da IA, por usuário e por chamada.
 *
 * O custo é calculado no momento da chamada com os preços em vigor e gravado em
 * ai_usage — mudar o preço depois não reescreve o histórico. Os preços padrão
 * vêm da tabela oficial da OpenAI (set/2026) e podem ser editados no painel.
 *
 * Nada aqui pode derrubar uma requisição do usuário: toda falha só vai para o log.
 */

const AI_ACTIONS = ['analyze', 'translate', 'tts'];

/** Preços em US$ por 1 milhão de tokens. */
function default_ai_prices(): array
{
    return [
        'gpt-audio-1.5'   => ['text_in' => 2.50, 'audio_in' => 32.00, 'text_out' => 10.00, 'audio_out' => 64.00],
        'gpt-audio'       => ['text_in' => 2.50, 'audio_in' => 32.00, 'text_out' => 10.00, 'audio_out' => 64.00],
        'gpt-audio-mini'  => ['text_in' => 0.60, 'audio_in' => 10.00, 'text_out' => 2.40,  'audio_out' => 20.00],
        'gpt-4o-mini'     => ['text_in' => 0.15, 'audio_in' => 0.00,  'text_out' => 0.60,  'audio_out' => 0.00],
        'gpt-4o-mini-tts' => ['text_in' => 0.60, 'audio_in' => 0.00,  'text_out' => 0.00,  'audio_out' => 12.00],
    ];
}

const AI_PRICE_FIELDS = ['text_in', 'audio_in', 'text_out', 'audio_out'];

function app_setting(string $name): ?string
{
    try {
        $stmt = db()->prepare('SELECT value FROM app_settings WHERE name = ?');
        $stmt->execute([$name]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : (string)$value;
    } catch (Throwable $ex) {
        return null;
    }
}

function save_app_setting(string $name, string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO app_settings (name, value, updated_at) VALUES (:name, :value, NOW())
         ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()'
    );
    $stmt->execute([':name' => $name, ':value' => $value]);
}

/** Preços em vigor: os padrões com o que foi editado no painel por cima. */
function ai_prices(): array
{
    $prices = default_ai_prices();
    $saved  = json_decode((string)app_setting('ai_prices'), true);

    if (is_array($saved)) {
        foreach ($saved as $model => $fields) {
            if (!is_array($fields)) {
                continue;
            }
            foreach (AI_PRICE_FIELDS as $field) {
                if (isset($fields[$field]) && is_numeric($fields[$field])) {
                    $prices[(string)$model][$field] = (float)$fields[$field];
                }
            }
        }
    }

    // Modelos configurados no app sempre aparecem, mesmo sem preço conhecido
    foreach (array_filter([
        defined('OPENAI_AUDIO_MODEL') ? OPENAI_AUDIO_MODEL : null,
        defined('OPENAI_TEXT_MODEL') ? OPENAI_TEXT_MODEL : null,
        defined('OPENAI_TTS_MODEL') ? OPENAI_TTS_MODEL : 'gpt-4o-mini-tts',
    ]) as $model) {
        $prices[$model] ??= array_fill_keys(AI_PRICE_FIELDS, 0.0);
    }

    return $prices;
}

/** Custo em US$ de um conjunto de tokens. */
function ai_cost(string $model, array $tokens): float
{
    $price = ai_prices()[$model] ?? array_fill_keys(AI_PRICE_FIELDS, 0.0);

    return ((int)($tokens['text_in'] ?? 0)   * (float)$price['text_in']
          + (int)($tokens['audio_in'] ?? 0)  * (float)$price['audio_in']
          + (int)($tokens['text_out'] ?? 0)  * (float)$price['text_out']
          + (int)($tokens['audio_out'] ?? 0) * (float)$price['audio_out']) / 1_000_000;
}

/**
 * Converte o campo `usage` do chat/completions nos quatro tipos de token.
 * A OpenAI informa o total e, nos detalhes, quanto disso é áudio.
 */
function tokens_from_chat_usage(array $usage): array
{
    $promptAudio     = (int)($usage['prompt_tokens_details']['audio_tokens'] ?? 0);
    $completionAudio = (int)($usage['completion_tokens_details']['audio_tokens'] ?? 0);

    return [
        'text_in'   => max(0, (int)($usage['prompt_tokens'] ?? 0) - $promptAudio),
        'audio_in'  => $promptAudio,
        'text_out'  => max(0, (int)($usage['completion_tokens'] ?? 0) - $completionAudio),
        'audio_out' => $completionAudio,
    ];
}

/**
 * Tokens estimados de uma geração de voz. O /v1/audio/speech devolve só o MP3,
 * sem `usage`: o texto vira ~4 caracteres por token e o áudio sai da duração
 * (US$ 0,015/min a US$ 12/1M ≈ 1.250 tokens por minuto).
 */
function estimate_tts_tokens(string $text, string $instructions, string $mp3): array
{
    $seconds = mp3_duration_seconds($mp3) ?? max(1.0, str_word_count($text) * 0.4);

    return [
        'text_in'   => (int)ceil((strlen($text) + strlen($instructions)) / 4),
        'audio_in'  => 0,
        'text_out'  => 0,
        'audio_out' => (int)ceil($seconds * 1250 / 60),
    ];
}

/** Duração de um MP3 de taxa constante, lendo o cabeçalho do primeiro quadro. */
function mp3_duration_seconds(string $mp3): ?float
{
    $offset = 0;
    // Pula a tag ID3v2, se houver
    if (strncmp($mp3, 'ID3', 3) === 0 && strlen($mp3) > 10) {
        $size   = (ord($mp3[6]) << 21) | (ord($mp3[7]) << 14) | (ord($mp3[8]) << 7) | ord($mp3[9]);
        $offset = 10 + $size;
    }

    $length = strlen($mp3);
    for ($i = $offset; $i < min($length - 4, $offset + 8192); $i++) {
        if (ord($mp3[$i]) !== 0xFF || (ord($mp3[$i + 1]) & 0xE0) !== 0xE0) {
            continue;
        }
        $b1 = ord($mp3[$i + 1]);
        $b2 = ord($mp3[$i + 2]);
        $version = ($b1 >> 3) & 0x03;   // 3 = MPEG1, 2 = MPEG2, 0 = MPEG2.5
        $layer   = ($b1 >> 1) & 0x03;   // 1 = Layer III
        $index   = ($b2 >> 4) & 0x0F;
        if ($layer !== 1 || $version === 1 || $index === 0 || $index === 15) {
            continue;
        }
        $table = $version === 3
            ? [0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320]
            : [0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160];
        $bitrate = $table[$index] * 1000;

        return $bitrate > 0 ? ($length - $i) * 8 / $bitrate : null;
    }

    return null;
}

/** Grava uma chamada à IA. Nunca lança. */
function record_ai_usage(?int $userId, string $action, string $model, array $tokens, bool $estimated = false): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO ai_usage
                    (user_id, action, model, text_input_tokens, audio_input_tokens,
                     text_output_tokens, audio_output_tokens, cost_usd, estimated)
             VALUES (:uid, :action, :model, :ti, :ai, :to, :ao, :cost, :est)'
        );
        $stmt->execute([
            ':uid'    => $userId,
            ':action' => $action,
            ':model'  => mb_substr($model, 0, 60),
            ':ti'     => (int)($tokens['text_in'] ?? 0),
            ':ai'     => (int)($tokens['audio_in'] ?? 0),
            ':to'     => (int)($tokens['text_out'] ?? 0),
            ':ao'     => (int)($tokens['audio_out'] ?? 0),
            ':cost'   => round(ai_cost($model, $tokens), 6),
            ':est'    => $estimated ? 1 : 0,
        ]);
    } catch (Throwable $ex) {
        error_log('[ai_usage] ' . $action . ' usuário ' . $userId . ': ' . $ex->getMessage());
    }
}

/**
 * Registra a última chamada de chat/completions feita nesta requisição.
 * Chamar depois do try: a chamada paga é registrada mesmo que a resposta do
 * modelo venha inválida e a análise falhe.
 */
function record_last_chat_usage(int $userId, string $action, string $model): void
{
    $usage = openai_take_last_usage();
    if ($usage !== null) {
        record_ai_usage($userId, $action, $model, tokens_from_chat_usage($usage));
    }
}
