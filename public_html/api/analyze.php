<?php
declare(strict_types=1);

require_once __DIR__ . '/../boot.php';
require_once APP_INCLUDES . '/phrases.php';
require_once APP_INCLUDES . '/rate_limit.php';
require_once APP_INCLUDES . '/openai.php';
require_once APP_INCLUDES . '/recordings.php';

// Medido no spike: a análise leva ~2,3s e o teto do LiteSpeed passa de 35s.
// Os 60s aqui são folga; o corte real é o CURLOPT_TIMEOUT de 25s.
set_time_limit(60);

require_method('POST');
$user   = require_auth_api();
$userId = (int)$user['id'];
csrf_require();

// --- validações do usuário: acontecem ANTES de tocar na cota ----------------

// O modal é client-side; sem esta checagem o consentimento seria decorativo
if (!has_current_consent($user)) {
    json_error('no_consent', 'É preciso aceitar o aviso de gravação antes de analisar.', 403);
}

$phraseId = (int)($_POST['phrase_id'] ?? 0);
$phrase   = find_phrase($userId, $phraseId);

if ($phrase === null) {
    json_error('not_found', 'Frase não encontrada.', 404);
}

if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
    json_error('no_audio', 'Não recebemos sua gravação. Tente novamente.', 422);
}

if ($_FILES['audio']['size'] > 2 * 1024 * 1024) {
    json_error('audio_too_big', 'A gravação é longa demais. Grave até 30 segundos.', 422);
}

$wav = (string)file_get_contents($_FILES['audio']['tmp_name']);

// O upload temporário do PHP sai do disco já. Só uma gravação aprovada (> 8)
// volta a ser escrita, depois da nota — ver attach_approved_recording().
@unlink($_FILES['audio']['tmp_name']);

// Magic bytes: precisa ser mesmo um RIFF/WAVE
if (strlen($wav) < 45 || substr($wav, 0, 4) !== 'RIFF' || substr($wav, 8, 4) !== 'WAVE') {
    json_error('bad_audio', 'Formato de áudio inválido. Grave novamente.', 422);
}

// WAV 16kHz mono 16-bit: 32000 bytes por segundo, menos o header de 44
$audioSeconds = (int)round((strlen($wav) - 44) / 32000);
$audioSeconds = max(0, min(255, $audioSeconds));

// --- a partir daqui a cota é consumida --------------------------------------

$count   = rate_limit_hit($userId, 'analyze');
$charged = true;
$result  = null;
$failure = null;

// ATENÇÃO: nada de json_ok()/json_error() dentro do try.
// exit não executa finally em PHP — responder aqui pularia a devolução da cota.
try {
    if ($count > LIMIT_ANALYZE_PER_DAY) {
        $failure = ['rate_limited',
            'Você atingiu o limite de análises de hoje. Volte amanhã.', 429, ''];
    } elseif (global_daily_count('analyze') > GLOBAL_DAILY_ANALYSES) {
        $failure = ['maintenance',
            'Sistema em manutenção. Tente novamente mais tarde.', 503,
            'teto global diário atingido'];
    } else {
        $ai = call_audio_model($wav, (string)$phrase['text_en'], (string)$phrase['level']);
        $result = persist_attempt($userId, $phrase, $ai['feedback'], $audioSeconds);
        $charged = false;  // gravou o attempt: a cota foi bem gasta

        // Playlists: guarda o áudio só se aprovado. Nunca lança — ver recordings.php
        $result += attach_approved_recording(
            $userId, (int)$phrase['id'], $result['attempt_id'], $result['score'], $wav, $audioSeconds
        );
    }
} catch (OpenAiException $ex) {
    $failure = ['ai_failed',
        'Não conseguimos analisar agora. Sua gravação foi mantida — tente novamente.',
        502, $ex->getMessage()];
} catch (Throwable $ex) {
    $failure = ['internal', 'Erro inesperado. Tente novamente.', 500, $ex->getMessage()];
} finally {
    if ($charged) {
        rate_limit_refund($userId, 'analyze');
    }
}

if ($failure !== null) {
    json_error($failure[0], $failure[1], $failure[2], $failure[3]);
}

json_ok($result);

/**
 * Sanitiza a resposta do modelo e grava tentativa + frase na MESMA transação.
 * Sem transação, uma falha entre os dois deixa attempts_count divergente para sempre.
 */
function persist_attempt(int $userId, array $phrase, array $feedback, int $audioSeconds): array
{
    // O modelo pode devolver 11, "9.5" ou negativo — clamp antes de qualquer INSERT
    $score = (float)($feedback['score'] ?? 0);
    $score = round(max(0.0, min(10.0, $score)), 1);

    // E um heard de 600 chars estouraria a coluna DEPOIS de já ter pago a chamada
    $heard = mb_substr((string)($feedback['heard'] ?? ''), 0, 500);

    $feedback['score'] = $score;
    $feedback['heard'] = $heard;

    $isMastered = $score >= 10.0;
    $pdo = db();

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO attempts (phrase_id, user_id, score, heard, feedback_json, audio_seconds)
                  VALUES (:pid, :uid, :score, :heard, :json, :secs)'
        );
        $stmt->execute([
            ':pid'   => $phrase['id'],
            ':uid'   => $userId,
            ':score' => $score,
            ':heard' => $heard,
            ':json'  => json_encode($feedback, JSON_UNESCAPED_UNICODE),
            ':secs'  => $audioSeconds,
        ]);
        $attemptId = (int)$pdo->lastInsertId();

        // best_score NUNCA regride; 'mastered' NUNCA regride; mastered_at nunca
        // é sobrescrito; last_attempt_at muda em TODA tentativa (ordena a fila).
        $stmt = $pdo->prepare(
            "UPDATE phrases SET
                 attempts_count  = attempts_count + 1,
                 last_attempt_at = NOW(),
                 best_score      = GREATEST(COALESCE(best_score, 0), :score_best),
                 status          = CASE
                                     WHEN status = 'mastered' THEN 'mastered'
                                     WHEN :score_status >= 10  THEN 'mastered'
                                     ELSE 'in_progress'
                                   END,
                 mastered_at     = CASE
                                     WHEN mastered_at IS NOT NULL THEN mastered_at
                                     WHEN :score_at >= 10         THEN NOW()
                                     ELSE mastered_at
                                   END
              WHERE id = :id AND user_id = :uid"
        );
        $stmt->execute([
            ':score_best'   => $score,
            ':score_status' => $score,
            ':score_at'     => $score,
            ':id'           => $phrase['id'],
            ':uid'          => $userId,
        ]);

        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }

    $fresh = find_phrase($userId, (int)$phrase['id']);

    return [
        'attempt_id'     => $attemptId,
        'score'          => $score,
        'feedback'       => $feedback,
        'is_mastered'    => $isMastered,
        'advanced'       => $score >= SCORE_TO_ADVANCE,
        'attempts_count' => (int)($fresh['attempts_count'] ?? 0),
        'best_score'     => $fresh['best_score'] !== null ? (float)$fresh['best_score'] : null,
    ];
}
