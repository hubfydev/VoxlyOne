<?php
declare(strict_types=1);

require_once __DIR__ . '/../boot.php';
require_once APP_INCLUDES . '/rate_limit.php';
require_once APP_INCLUDES . '/openai.php';

require_method('POST');
$user = require_auth_api();
csrf_require();

$textPt = trim((string)($_POST['text_pt'] ?? ''));
$textEn = trim((string)($_POST['text_en'] ?? ''));

// Erro de validação do usuário: aborta ANTES de incrementar a cota
if ($textPt === '' && $textEn === '') {
    json_error('empty_input', 'Preencha ao menos um dos idiomas.', 422);
}
if (mb_strlen($textPt) > 200 || mb_strlen($textEn) > 200) {
    json_error('text_too_long', 'As frases devem ter no máximo 200 caracteres.', 422);
}

$userId = (int)$user['id'];

// Incremento ATÔMICO antes da chamada — nunca check-depois-grava
$count   = rate_limit_hit($userId, 'translate');
$charged = true;
$result  = null;
$failure = null;

// ATENÇÃO: nada de json_ok()/json_error() dentro deste try.
// Em PHP, exit NÃO executa o finally — responder aqui dentro pularia o refund.
try {
    if ($count > LIMIT_TRANSLATE_PER_DAY) {
        $failure = ['rate_limited', 'Você atingiu o limite de traduções de hoje. Tente novamente amanhã.', 429, ''];
    } else {
        $result  = call_text_model($textPt, $textEn);
        $charged = false;  // produziu resultado: a cota foi bem gasta
    }
} catch (OpenAiException $ex) {
    $failure = ['ai_failed', 'Não foi possível traduzir agora. Tente novamente.', 502, $ex->getMessage()];
} catch (Throwable $ex) {
    $failure = ['internal', 'Erro inesperado. Tente novamente.', 500, $ex->getMessage()];
} finally {
    // Devolve a cota em QUALQUER caminho que não produza resultado
    if ($charged) {
        rate_limit_refund($userId, 'translate');
    }
}

if ($failure !== null) {
    json_error($failure[0], $failure[1], $failure[2], $failure[3]);
}

json_ok($result);
