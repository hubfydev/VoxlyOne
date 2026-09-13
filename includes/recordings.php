<?php
declare(strict_types=1);

/**
 * Gravações aprovadas do usuário — o conteúdo das playlists.
 *
 * Só a gravação com nota ESTRITAMENTE maior que 8 é guardada; as demais seguem
 * sendo descartadas logo após a análise, como sempre foram. Cada frase tem no
 * máximo UMA gravação aprovada atual (UNIQUE phrase_id): uma nova aprovação
 * troca o arquivo e mantém a linha, então as playlists nunca precisam ser
 * atualizadas. Uma tentativa ≤ 8 nunca chega aqui e não mexe na aprovada.
 *
 * Os arquivos ficam em voxly-app/storage/recordings/{user_id}/, fora de todo
 * document root, e só saem pelo recording.php, que confere o dono.
 */

/** Nota de corte. A comparação é `>`: 8.0 avança na prática, mas não entra em playlist. */
const PLAYLIST_MIN_SCORE = 8.0;

function recording_is_approved(float $score): bool
{
    return $score > PLAYLIST_MIN_SCORE;
}

/** Pasta raiz das gravações, irmã de includes/ e config/ dentro da voxly-app. */
function recordings_root(): string
{
    return dirname(APP_INCLUDES) . '/storage/recordings';
}

function recordings_user_dir(int $userId): string
{
    return recordings_root() . '/' . $userId;
}

/** Caminho do arquivo. O nome vem do banco, mas é revalidado para impedir path traversal. */
function recording_path(int $userId, string $fileName): string
{
    if (preg_match('/^[a-f0-9]{32}\.wav$/', $fileName) !== 1) {
        throw new RuntimeException('Nome de arquivo de gravação inválido: ' . $fileName);
    }
    return recordings_user_dir($userId) . '/' . $fileName;
}

/**
 * Cria a pasta do usuário. Na primeira vez deixa também um .htaccess negando
 * tudo na raiz de storage/ — rede de segurança para quando a voxly-app estiver
 * dentro do public_html (o fallback do boot.php).
 */
function ensure_recordings_dir(int $userId): string
{
    $dir = recordings_user_dir($userId);
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Não foi possível criar a pasta de gravações: ' . $dir);
    }

    $guard = dirname(recordings_root()) . '/.htaccess';
    if (!is_file($guard)) {
        @file_put_contents($guard, "Require all denied\n");
    }

    return $dir;
}

/** A gravação aprovada de uma frase, ou null. Sempre filtrada pelo dono. */
function find_recording_by_phrase(int $userId, int $phraseId): ?array
{
    $stmt = db()->prepare(
        'SELECT id, phrase_id, score, file_name, audio_seconds, updated_at
           FROM recordings WHERE phrase_id = ? AND user_id = ?'
    );
    $stmt->execute([$phraseId, $userId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function find_recording(int $userId, int $recordingId): ?array
{
    $stmt = db()->prepare(
        'SELECT r.id, r.phrase_id, r.score, r.file_name, r.audio_bytes, r.updated_at,
                p.text_en, p.text_pt
           FROM recordings r
           JOIN phrases p ON p.id = r.phrase_id AND p.user_id = r.user_id
          WHERE r.id = ? AND r.user_id = ?'
    );
    $stmt->execute([$recordingId, $userId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/** URL do áudio. O `v` muda a cada substituição e invalida o cache do navegador. */
function recording_url(array $recording): string
{
    return '/recording.php?id=' . (int)$recording['id']
         . '&v=' . substr((string)$recording['file_name'], 0, 8);
}

/**
 * Guarda o WAV como a gravação aprovada atual da frase.
 *
 * Ordem pensada para nunca deixar uma playlist apontando para arquivo inexistente:
 * 1) grava o arquivo novo; 2) troca a referência no banco; 3) só então apaga o
 * antigo. Se o banco falhar, o arquivo novo é removido e o antigo continua valendo.
 *
 * @return array{id: int, url: string, replaced: bool}
 */
function save_approved_recording(
    int $userId,
    int $phraseId,
    int $attemptId,
    float $score,
    string $wav,
    int $audioSeconds
): array {
    $dir      = ensure_recordings_dir($userId);
    $fileName = bin2hex(random_bytes(16)) . '.wav';
    $path     = $dir . '/' . $fileName;

    // Escreve num .part e renomeia: quem estiver tocando nunca lê arquivo pela metade
    if (file_put_contents($path . '.part', $wav, LOCK_EX) !== strlen($wav)) {
        @unlink($path . '.part');
        throw new RuntimeException('Falha ao gravar o arquivo de áudio.');
    }
    if (!rename($path . '.part', $path)) {
        @unlink($path . '.part');
        throw new RuntimeException('Falha ao mover o arquivo de áudio.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'SELECT id, file_name FROM recordings
              WHERE phrase_id = ? AND user_id = ? FOR UPDATE'
        );
        $stmt->execute([$phraseId, $userId]);
        $previous = $stmt->fetch();

        if ($previous === false) {
            $stmt = $pdo->prepare(
                'INSERT INTO recordings
                        (user_id, phrase_id, attempt_id, score, file_name, audio_bytes, audio_seconds)
                 VALUES (:uid, :pid, :aid, :score, :file, :bytes, :secs)'
            );
            $stmt->execute([
                ':uid'   => $userId,
                ':pid'   => $phraseId,
                ':aid'   => $attemptId,
                ':score' => $score,
                ':file'  => $fileName,
                ':bytes' => strlen($wav),
                ':secs'  => $audioSeconds,
            ]);
            $recordingId = (int)$pdo->lastInsertId();
        } else {
            // Mesma linha, arquivo novo: os playlist_items continuam válidos
            $stmt = $pdo->prepare(
                'UPDATE recordings
                    SET attempt_id = :aid, score = :score, file_name = :file,
                        audio_bytes = :bytes, audio_seconds = :secs, updated_at = NOW()
                  WHERE id = :id AND user_id = :uid'
            );
            $stmt->execute([
                ':aid'   => $attemptId,
                ':score' => $score,
                ':file'  => $fileName,
                ':bytes' => strlen($wav),
                ':secs'  => $audioSeconds,
                ':id'    => $previous['id'],
                ':uid'   => $userId,
            ]);
            $recordingId = (int)$previous['id'];
        }

        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        @unlink($path);
        throw $ex;
    }

    if ($previous !== false) {
        delete_recording_file($userId, (string)$previous['file_name']);
    }

    return [
        'id'       => $recordingId,
        'url'      => recording_url(['id' => $recordingId, 'file_name' => $fileName]),
        'replaced' => $previous !== false,
    ];
}

/**
 * Integração com a análise: chamada depois que o attempt já foi gravado.
 * NUNCA lança — uma falha ao guardar o áudio não pode transformar uma análise
 * bem-sucedida (e já paga) em erro para o usuário. O detalhe vai para o log.
 *
 * @return array{playlist_eligible: bool, recording: ?array}
 */
function attach_approved_recording(
    int $userId,
    int $phraseId,
    int $attemptId,
    float $score,
    string $wav,
    int $audioSeconds
): array {
    if (!recording_is_approved($score)) {
        // Nota ≤ 8: não guarda nada e preserva a aprovada anterior, se houver
        $existing = null;
        try {
            $existing = find_recording_by_phrase($userId, $phraseId);
        } catch (Throwable $ex) {
            error_log('[recording_lookup] ' . $ex->getMessage());
        }
        return [
            'playlist_eligible' => false,
            'recording'         => $existing !== null
                ? ['id' => (int)$existing['id'], 'url' => recording_url($existing), 'replaced' => false]
                : null,
        ];
    }

    try {
        $recording = save_approved_recording($userId, $phraseId, $attemptId, $score, $wav, $audioSeconds);
        return ['playlist_eligible' => true, 'recording' => $recording];
    } catch (Throwable $ex) {
        error_log('[recording_save] frase ' . $phraseId . ': ' . $ex->getMessage());
        return ['playlist_eligible' => false, 'recording' => null];
    }
}

/** Apaga um arquivo de gravação. Ausente não é erro: o objetivo é ele não existir. */
function delete_recording_file(int $userId, string $fileName): void
{
    try {
        $path = recording_path($userId, $fileName);
    } catch (Throwable $ex) {
        error_log('[recording_delete] ' . $ex->getMessage());
        return;
    }
    if (is_file($path) && !@unlink($path)) {
        error_log('[recording_delete] não foi possível apagar ' . $path);
    }
}

/** Remove a pasta inteira do usuário — usado na exclusão da conta. */
function delete_user_recordings(int $userId): void
{
    $dir = recordings_user_dir($userId);
    if (!is_dir($dir)) {
        return;
    }
    foreach ((array)glob($dir . '/*') as $file) {
        if (is_file((string)$file) && !@unlink((string)$file)) {
            error_log('[recording_delete] não foi possível apagar ' . $file);
        }
    }
    @rmdir($dir);
}
