<?php
declare(strict_types=1);

/**
 * Voz de referência neural (OpenAI TTS) para o botão "Ouvir pronúncia".
 *
 * Cada frase é gerada UMA vez por voz e fica em cache no servidor:
 *   voxly-app/storage/tts/{user_id}/{phrase_id}-{female|male}-{hash}.mp3
 * O hash cobre modelo + voz + instruções + texto — editar a frase ou trocar a voz
 * no config gera um áudio novo, e o antigo é apagado na mesma hora.
 *
 * O cache é por usuário (não compartilhado) de propósito: excluir a frase ou a
 * conta apaga também o áudio gerado a partir do texto dela.
 *
 * Qualquer falha (sem cota, OpenAI fora, config ausente) faz o navegador cair na
 * voz do próprio aparelho — o botão nunca fica mudo.
 */

const TTS_GENDERS = ['female', 'male'];

// Padrões seguros: o config.php de produção pode não ter as constantes novas
function tts_model(): string
{
    return defined('OPENAI_TTS_MODEL') ? OPENAI_TTS_MODEL : 'gpt-4o-mini-tts';
}

/** Voz da OpenAI para cada gênero. marin e cedar são as recomendadas para qualidade. */
function tts_voice(string $gender): string
{
    if ($gender === 'male') {
        return defined('TTS_VOICE_MALE') ? TTS_VOICE_MALE : 'cedar';
    }
    return defined('TTS_VOICE_FEMALE') ? TTS_VOICE_FEMALE : 'marin';
}

function tts_daily_limit(): int
{
    return defined('LIMIT_TTS_PER_DAY') ? (int)LIMIT_TTS_PER_DAY : 100;
}

/**
 * Direção de voz: suave, calorosa e natural, em inglês americano claro.
 * Mudar este texto regenera todos os áudios (ele entra no hash).
 */
function tts_instructions(): string
{
    return 'Speak in a soft, warm and charming voice, like a friendly American English teacher. '
         . 'Use natural, clear General American pronunciation with gentle intonation and a relaxed, '
         . 'conversational pace. Pronounce every word fully so a language learner can imitate it.';
}

function tts_hash(string $text, string $gender): string
{
    return substr(hash('sha256', tts_model() . '|' . tts_voice($gender) . '|' . tts_instructions() . '|' . $text), 0, 16);
}

/** URL da pronúncia. O `v` muda junto com o hash e invalida o cache do navegador. */
function tts_url(array $phrase, string $gender): string
{
    return '/tts.php?phrase=' . (int)$phrase['id'] . '&voice=' . $gender
         . '&v=' . tts_hash((string)$phrase['text_en'], $gender);
}

function tts_user_dir(int $userId): string
{
    return dirname(APP_INCLUDES) . '/storage/tts/' . $userId;
}

function tts_file(int $userId, int $phraseId, string $gender, string $hash): string
{
    return tts_user_dir($userId) . '/' . $phraseId . '-' . $gender . '-' . $hash . '.mp3';
}

/**
 * Caminho do MP3 da frase, gerando na primeira vez.
 * Lança RuntimeException com código HTTP (429 sem cota, 502 falha na OpenAI).
 */
function tts_ensure_file(int $userId, array $phrase, string $gender): string
{
    $phraseId = (int)$phrase['id'];
    $text     = (string)$phrase['text_en'];
    $hash     = tts_hash($text, $gender);
    $path     = tts_file($userId, $phraseId, $gender, $hash);

    if (is_file($path)) {
        return $path;
    }

    // Cota atômica ANTES da chamada paga; devolvida em qualquer falha
    $count   = rate_limit_hit($userId, 'tts');
    $charged = true;
    try {
        if ($count > tts_daily_limit()) {
            throw new RuntimeException('limite diário de TTS atingido', 429);
        }

        $mp3 = call_tts_model($text, tts_voice($gender), tts_instructions());

        // A voz não devolve `usage`: tokens estimados pela duração do MP3
        record_ai_usage($userId, 'tts', tts_model(), estimate_tts_tokens($text, tts_instructions(), $mp3), true);

        $dir = tts_user_dir($userId);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('não foi possível criar ' . $dir, 500);
        }
        $guard = dirname($dir, 2) . '/.htaccess';
        if (!is_file($guard)) {
            @file_put_contents($guard, "Require all denied\n");
        }

        // Escreve num .part e renomeia: duas abas pedindo ao mesmo tempo nunca leem pela metade
        if (file_put_contents($path . '.part', $mp3, LOCK_EX) !== strlen($mp3) || !rename($path . '.part', $path)) {
            @unlink($path . '.part');
            throw new RuntimeException('falha ao gravar ' . $path, 500);
        }
        $charged = false;
    } catch (OpenAiException $ex) {
        throw new RuntimeException($ex->getMessage(), 502);
    } finally {
        if ($charged) {
            rate_limit_refund($userId, 'tts');
        }
    }

    // Texto editado ou voz trocada: o áudio anterior desta frase não serve mais
    foreach ((array)glob(tts_user_dir($userId) . '/' . $phraseId . '-' . $gender . '-*.mp3') as $old) {
        if ((string)$old !== $path) {
            @unlink((string)$old);
        }
    }

    return $path;
}

/** Apaga os áudios gerados de uma frase (as duas vozes). */
function tts_delete_phrase(int $userId, int $phraseId): void
{
    foreach ((array)glob(tts_user_dir($userId) . '/' . $phraseId . '-*.mp3*') as $file) {
        @unlink((string)$file);
    }
}

/** Apaga todos os áudios gerados do usuário — exclusão de conta. */
function tts_delete_user(int $userId): void
{
    $dir = tts_user_dir($userId);
    if (!is_dir($dir)) {
        return;
    }
    foreach ((array)glob($dir . '/*') as $file) {
        @unlink((string)$file);
    }
    @rmdir($dir);
}
