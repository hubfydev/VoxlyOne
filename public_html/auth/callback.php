<?php
declare(strict_types=1);

require_once __DIR__ . '/../boot.php';

/** Erro de login: log técnico no servidor, mensagem genérica para o usuário. */
function login_failed(string $logDetail): never
{
    error_log('OAuth callback: ' . $logDetail);
    redirect('/index.php?erro=login');
}

// O Google devolve 'error' quando o usuário cancela na tela de consentimento
if (isset($_GET['error'])) {
    login_failed('usuário recusou: ' . (string)$_GET['error']);
}

// State: precisa existir na sessão e bater exatamente
$stateSent    = $_SESSION['oauth_state'] ?? null;
$stateBack    = $_GET['state'] ?? null;
unset($_SESSION['oauth_state']); // uso único, mesmo se falhar

if (!is_string($stateSent) || !is_string($stateBack) || !hash_equals($stateSent, $stateBack)) {
    login_failed('state inválido ou ausente');
}

$code = $_GET['code'] ?? null;
if (!is_string($code) || $code === '') {
    login_failed('code ausente');
}

// --- Troca do code por tokens ------------------------------------------------
$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_POSTFIELDS     => http_build_query([
        'code'          => $code,
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'grant_type'    => 'authorization_code',
    ]),
]);
$tokenRaw  = curl_exec($ch);
$tokenCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$tokenErr  = curl_error($ch);
curl_close($ch);

if ($tokenRaw === false) {
    login_failed('cURL no token endpoint: ' . $tokenErr);
}
if ($tokenCode !== 200) {
    login_failed("token endpoint HTTP {$tokenCode}: " . substr((string)$tokenRaw, 0, 300));
}

$token = json_decode((string)$tokenRaw, true);
$accessToken = $token['access_token'] ?? null;
if (!is_string($accessToken)) {
    login_failed('access_token ausente na resposta');
}

// --- Perfil do usuário -------------------------------------------------------
$ch = curl_init('https://openidconnect.googleapis.com/v1/userinfo');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
]);
$profileRaw  = curl_exec($ch);
$profileCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

if ($profileRaw === false || $profileCode !== 200) {
    login_failed("userinfo HTTP {$profileCode}");
}

$profile  = json_decode((string)$profileRaw, true);
$googleId = $profile['sub']   ?? null;
$email    = $profile['email'] ?? null;

if (!is_string($googleId) || !is_string($email)) {
    login_failed('perfil sem sub ou email');
}

// --- Cria/atualiza o usuário e abre a sessão ---------------------------------
try {
    $userId = upsert_google_user(
        $googleId,
        $email,
        (string)($profile['name'] ?? $email),
        isset($profile['picture']) ? (string)$profile['picture'] : null
    );
} catch (PDOException $ex) {
    login_failed('upsert falhou: ' . $ex->getMessage());
}

// Conta bloqueada pelo painel administrativo: não abre sessão
if (is_user_blocked($userId)) {
    error_log('OAuth callback: login recusado, conta bloqueada (usuário ' . $userId . ')');
    redirect('/index.php?erro=bloqueado');
}

// Regenerar o id da sessão no login evita fixação de sessão
session_regenerate_id(true);
$_SESSION['user_id'] = $userId;
unset($_SESSION['blocked_notice']);
record_user_login($userId);

redirect('/dashboard.php');
