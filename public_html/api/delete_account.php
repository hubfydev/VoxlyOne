<?php
declare(strict_types=1);

require_once __DIR__ . '/../boot.php';
require_once APP_INCLUDES . '/recordings.php';
require_once APP_INCLUDES . '/tts.php';

require_method('POST');
$user = require_auth_api();
csrf_require();

// Confirmação explícita além do CSRF: evita exclusão por requisição perdida
if ((string)($_POST['confirm'] ?? '') !== 'EXCLUIR') {
    json_error('not_confirmed', 'Confirmação inválida.', 422);
}

// Frases, categorias, tentativas, gravações, playlists e rate_limits somem
// pelas FKs ON DELETE CASCADE
$stmt = db()->prepare('DELETE FROM users WHERE id = ?');
$stmt->execute([$user['id']]);

// Os arquivos de áudio não estão no banco: apaga a pasta do usuário
delete_user_recordings((int)$user['id']);
tts_delete_user((int)$user['id']);

// Derruba a sessão antes de responder
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $p['path'],
        'domain'   => $p['domain'],
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
session_destroy();

json_ok(['deleted' => true]);
