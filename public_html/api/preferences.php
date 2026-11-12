<?php
declare(strict_types=1);

require_once __DIR__ . '/../boot.php';

require_method('POST');
$user = require_auth_api();
csrf_require();

$threshold = validate_playlist_threshold($_POST['playlist_score_threshold'] ?? null);
if ($threshold === null) {
    json_error('invalid', 'Escolha uma nota de 4 a 9, com no máximo uma casa decimal.', 422);
}

try {
    $stmt = db()->prepare('UPDATE users SET playlist_score_threshold = ? WHERE id = ?');
    $stmt->execute([$threshold, (int)$user['id']]);
} catch (Throwable $ex) {
    json_error('internal', 'Não foi possível salvar a configuração. Tente novamente.', 500, $ex->getMessage());
}

json_ok(['playlist_score_threshold' => $threshold]);
