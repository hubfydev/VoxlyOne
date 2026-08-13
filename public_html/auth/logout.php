<?php
declare(strict_types=1);

require_once __DIR__ . '/../boot.php';

$_SESSION = [];

// Expira o cookie de sessão no browser além de destruir os dados no servidor
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
redirect('/index.php');
