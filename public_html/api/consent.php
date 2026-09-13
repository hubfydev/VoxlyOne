<?php
declare(strict_types=1);

require_once __DIR__ . '/../boot.php';

require_method('POST');
$user = require_auth_api();
csrf_require();

// Idempotente: registra o aceite da versão atual e não sobrescreve depois.
// Quem aceitou uma versão anterior do texto ganha data e versão novas.
$stmt = db()->prepare(
    'UPDATE users SET consented_at = NOW(), consent_version = ?
      WHERE id = ? AND (consented_at IS NULL OR consent_version < ?)'
);
$stmt->execute([CONSENT_VERSION, $user['id'], CONSENT_VERSION]);

json_ok(['consented' => true]);
