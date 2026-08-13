<?php
declare(strict_types=1);

require_once __DIR__ . '/../boot.php';

require_method('POST');
$user = require_auth_api();
csrf_require();

// Idempotente: registra o primeiro aceite e não sobrescreve depois
$stmt = db()->prepare(
    'UPDATE users SET consented_at = NOW() WHERE id = ? AND consented_at IS NULL'
);
$stmt->execute([$user['id']]);

json_ok(['consented' => true]);
