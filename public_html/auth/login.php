<?php
declare(strict_types=1);

require_once __DIR__ . '/../boot.php';

// Já logado: não faz sentido refazer o fluxo
if (current_user() !== null) {
    redirect('/dashboard.php');
}

// State aleatório guardado na sessão e conferido no callback (proteção CSRF do OAuth)
$state = bin2hex(random_bytes(32));
$_SESSION['oauth_state'] = $state;

$params = [
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $state,
    'access_type'   => 'online',
    'prompt'        => 'select_account',
];

redirect('https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
