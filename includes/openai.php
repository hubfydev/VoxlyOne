<?php
declare(strict_types=1);

/**
 * Integração com a OpenAI via cURL.
 *
 * As funções LANÇAM exceção em qualquer falha. Isso é proposital: quem chama
 * usa try/finally e a devolução da cota fica correta por construção, sem
 * depender de enumerar cada caso de erro (ver regra em 3.2 do documento).
 */

/** Falha de infraestrutura/serviço — a cota deve ser devolvida. */
class OpenAiException extends RuntimeException {}

/**
 * Avalia a pronúncia de um WAV. Modelo validado no spike: gpt-audio-1.5.
 *
 * @param string $wavBinary WAV 16kHz mono 16-bit já validado (magic bytes RIFF)
 * @return array{feedback: array, usage: array, elapsed: float}
 */
function call_audio_model(string $wavBinary, string $textEn, string $level): array
{
    $systemPrompt = 'You are an English pronunciation coach specialized in Brazilian '
        . 'Portuguese speakers. You will receive an audio recording of a student '
        . 'attempting to say a target phrase. Listen carefully to the ACTUAL '
        . 'pronunciation, rhythm, intonation and speed. '
        . 'Always respond with ONLY a valid JSON object, no markdown.';

    // Sem attempt_number: informar a tentativa enviesa a nota para cima.
    $userPrompt = 'Target phrase: "' . $textEn . '"' . "\n"
        . 'Level: ' . $level . "\n\n"
        . "Listen to the attached audio and evaluate the pronunciation.\n"
        . "Return ONLY this JSON:\n"
        . "{\n"
        . '  "score": number (0-10, one decimal),' . "\n"
        . '  "heard": string (what the student actually said),' . "\n"
        . '  "positives": string (PT-BR, max 80 chars),' . "\n"
        . '  "errors": [{ "word", "said", "correct", "tip" }],' . "\n"
        . '  "naturalness": string (PT-BR, max 80 chars),' . "\n"
        . '  "speed_feedback": string (PT-BR, max 60 chars),' . "\n"
        . '  "main_tip": string (PT-BR, max 100 chars)' . "\n"
        . "}\n\n"
        . 'Scoring: 0-2 incomprehensible | 3-4 very hard to understand | '
        . '5-6 understandable with significant errors | 7-8 good, minor errors | '
        . '9 near-native | 10 native-level fluency and naturalness';

    $payload = [
        'model'       => OPENAI_AUDIO_MODEL,
        'temperature' => 0,
        'modalities'  => ['text'],
        'messages'    => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => [
                ['type' => 'text', 'text' => $userPrompt],
                ['type' => 'input_audio', 'input_audio' => [
                    'data'   => base64_encode($wavBinary),
                    'format' => 'wav',
                ]],
            ]],
        ],
    ];

    $start    = microtime(true);
    $response = openai_post($payload, 25);
    $elapsed  = microtime(true) - $start;

    return [
        'feedback' => parse_model_json($response['choices'][0]['message']['content'] ?? ''),
        'usage'    => $response['usage'] ?? [],
        'elapsed'  => $elapsed,
    ];
}

/**
 * Traduz PT↔EN e devolve, na mesma chamada, a pronúncia aproximada em sons do
 * português (phonetic_br). Custo marginal: alguns tokens de saída.
 *
 * @return array{text_pt: string, text_en: string, phonetic_br: string}
 */
function call_text_model(string $textPt, string $textEn): array
{
    $systemPrompt = 'You are a translator and pronunciation helper for Brazilian '
        . 'Portuguese speakers learning English. '
        . 'Always respond with ONLY a valid JSON object, no markdown.';

    $userPrompt = "Given these fields (one may be empty), fill in what is missing.\n"
        . 'text_pt: "' . $textPt . '"' . "\n"
        . 'text_en: "' . $textEn . '"' . "\n\n"
        . "Return ONLY this JSON:\n"
        . "{\n"
        . '  "text_pt": string (natural Brazilian Portuguese, max 200 chars),' . "\n"
        . '  "text_en": string (natural English, max 200 chars),' . "\n"
        . '  "phonetic_br": string (how to pronounce text_en written with Brazilian '
        . 'Portuguese sounds, NOT IPA — e.g. "How are you?" becomes "RRAU ar iu"; '
        . 'max 400 chars)' . "\n"
        . '}';

    $payload = [
        'model'       => OPENAI_TEXT_MODEL,
        'temperature' => 0,
        'messages'    => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ],
    ];

    $response = openai_post($payload, 20);
    $parsed   = parse_model_json($response['choices'][0]['message']['content'] ?? '');

    return [
        'text_pt'     => mb_substr((string)($parsed['text_pt'] ?? ''), 0, 200),
        'text_en'     => mb_substr((string)($parsed['text_en'] ?? ''), 0, 200),
        'phonetic_br' => mb_substr((string)($parsed['phonetic_br'] ?? ''), 0, 400),
    ];
}

/**
 * Guarda o `usage` da última resposta paga desta requisição, para o registro de
 * custos (ai_usage.php) ler depois — inclusive quando o JSON do modelo vem
 * inválido e a análise falha, já que a OpenAI cobra do mesmo jeito.
 */
function openai_remember_usage(?array $usage): void
{
    $GLOBALS['openai_last_usage'] = $usage;
}

/** Devolve e limpa o `usage` guardado (null se nenhuma chamada paga aconteceu). */
function openai_take_last_usage(): ?array
{
    $usage = $GLOBALS['openai_last_usage'] ?? null;
    $GLOBALS['openai_last_usage'] = null;

    return is_array($usage) ? $usage : null;
}

/** POST em /v1/chat/completions. Lança OpenAiException em qualquer falha. */
function openai_post(array $payload, int $timeout): array
{
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY,
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
    ]);

    $raw      = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        throw new OpenAiException('cURL falhou: ' . $curlErr);
    }

    $decoded = json_decode((string)$raw, true);

    if ($httpCode !== 200) {
        $detail = $decoded['error']['message'] ?? substr((string)$raw, 0, 300);
        throw new OpenAiException("HTTP {$httpCode}: {$detail}");
    }

    if (!is_array($decoded)) {
        throw new OpenAiException('Resposta da OpenAI não é JSON.');
    }

    openai_remember_usage(is_array($decoded['usage'] ?? null) ? $decoded['usage'] : null);

    return $decoded;
}

/**
 * Decodifica o JSON que veio DENTRO da mensagem do modelo.
 * O modelo às vezes embrulha em cercas de código apesar da instrução —
 * comportamento observado no spike do Dia 1.
 */
function parse_model_json(string $content): array
{
    $clean  = trim((string)preg_replace('/^```(?:json)?|```$/m', '', $content));
    $parsed = json_decode($clean, true);

    if (!is_array($parsed)) {
        throw new OpenAiException('Modelo não devolveu JSON válido: ' . substr($content, 0, 200));
    }

    return $parsed;
}

/**
 * Gera o áudio da pronúncia correta com a voz neural da OpenAI (/v1/audio/speech).
 * Devolve o MP3 binário. Lança OpenAiException em qualquer falha — quem chama
 * devolve a cota e o navegador cai na voz do aparelho.
 */
function call_tts_model(string $text, string $voice, string $instructions): string
{
    $payload = [
        'model'           => tts_model(),
        'voice'           => $voice,
        'input'           => $text,
        'instructions'    => $instructions,
        'response_format' => 'mp3',
    ];

    $ch = curl_init('https://api.openai.com/v1/audio/speech');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY,
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
    ]);

    $raw      = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        throw new OpenAiException('cURL (TTS) falhou: ' . $curlErr);
    }
    if ($httpCode !== 200) {
        $decoded = json_decode((string)$raw, true);
        $detail  = $decoded['error']['message'] ?? substr((string)$raw, 0, 300);
        throw new OpenAiException("TTS HTTP {$httpCode}: {$detail}");
    }
    // Resposta de áudio minúscula é sinal de erro disfarçado, não de MP3 válido
    if (strlen((string)$raw) < 512) {
        throw new OpenAiException('TTS devolveu áudio vazio ou inválido.');
    }

    return (string)$raw;
}
