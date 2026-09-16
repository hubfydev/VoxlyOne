<?php
declare(strict_types=1);

/**
 * Painel administrativo: autenticação, proteção contra força bruta e helpers.
 *
 * Toda página do painel define ADMIN_AREA antes do boot (sessão separada — ver
 * start_app_session) e começa com require_admin(). Todo POST valida CSRF.
 */

require_once __DIR__ . '/ai_usage.php';

/** Inatividade máxima e duração máxima da sessão do painel. */
const ADMIN_IDLE_SECONDS     = 30 * 60;
const ADMIN_ABSOLUTE_SECONDS = 8 * 60 * 60;

/** Força bruta: tentativas erradas toleradas por IP e por usuário, na janela. */
const ADMIN_MAX_FAILS_PER_IP   = 5;
const ADMIN_MAX_FAILS_PER_USER = 10;
const ADMIN_LOCK_WINDOW_MIN    = 15;

/** Senha inicial da migration: enquanto estiver em uso, o painel avisa. */
const ADMIN_DEFAULT_PASSWORD = 'adm123';

function current_admin(): ?array
{
    static $admin = null;
    static $loaded = false;

    if ($loaded) {
        return $admin;
    }
    $loaded = true;

    $id = $_SESSION['admin_id'] ?? null;
    if (!is_int($id)) {
        return null;
    }

    $now = time();
    $idle = $now - (int)($_SESSION['admin_seen_at'] ?? 0);
    $age  = $now - (int)($_SESSION['admin_login_at'] ?? 0);
    if ($idle > ADMIN_IDLE_SECONDS || $age > ADMIN_ABSOLUTE_SECONDS) {
        admin_logout();
        $_SESSION['admin_expired'] = true;
        return null;
    }

    $stmt = db()->prepare(
        'SELECT id, username, password_changed_at, last_login_at, created_at FROM admins WHERE id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if ($row === false) {
        admin_logout();
        return null;
    }

    $_SESSION['admin_seen_at'] = $now;
    $admin = $row;
    return $admin;
}

/** Topo de toda página do painel. */
function require_admin(): array
{
    // Painel nunca vai para buscadores nem para cache compartilhado
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store');

    $admin = current_admin();
    if ($admin === null) {
        redirect('/admin/index.php');
    }
    return $admin;
}

/** POST do painel: método, CSRF e sessão válidos, senão volta com erro. */
function require_admin_post(string $backTo): array
{
    $admin = require_admin();
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_check($_POST['csrf_token'] ?? null)) {
        admin_flash('error', 'Sessão expirada. Tente novamente.');
        redirect($backTo);
    }
    return $admin;
}

function admin_logout(): void
{
    unset($_SESSION['admin_id'], $_SESSION['admin_seen_at'], $_SESSION['admin_login_at']);
    session_regenerate_id(true);
}

function client_ip(): string
{
    return mb_substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/** Está travado por excesso de tentativas? */
function admin_login_locked(string $username): bool
{
    $stmt = db()->prepare(
        'SELECT
            COALESCE(SUM(ip = :ip), 0)                AS by_ip,
            COALESCE(SUM(username_tried = :user), 0)  AS by_user
           FROM admin_logins
          WHERE success = 0 AND created_at >= NOW() - INTERVAL ' . ADMIN_LOCK_WINDOW_MIN . ' MINUTE'
    );
    $stmt->execute([':ip' => client_ip(), ':user' => mb_substr($username, 0, 60)]);
    $row = $stmt->fetch();

    return (int)$row['by_ip'] >= ADMIN_MAX_FAILS_PER_IP
        || (int)$row['by_user'] >= ADMIN_MAX_FAILS_PER_USER;
}

function record_admin_login(?int $adminId, string $username, bool $success): void
{
    $stmt = db()->prepare(
        'INSERT INTO admin_logins (admin_id, username_tried, ip, user_agent, success)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $adminId,
        mb_substr($username, 0, 60),
        client_ip(),
        mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        $success ? 1 : 0,
    ]);
}

/**
 * Tenta entrar. Devolve null em sucesso ou a mensagem de erro (genérica: não
 * revela se o usuário existe).
 */
function admin_attempt_login(string $username, string $password): ?string
{
    $username = trim($username);

    if (admin_login_locked($username)) {
        record_admin_login(null, $username, false);
        return 'Muitas tentativas. Aguarde ' . ADMIN_LOCK_WINDOW_MIN . ' minutos e tente de novo.';
    }

    $stmt = db()->prepare('SELECT id, password_hash FROM admins WHERE username = ?');
    $stmt->execute([$username]);
    $row = $stmt->fetch();

    // Verifica mesmo sem usuário, contra um hash fixo: o tempo de resposta não
    // entrega quais usuários existem
    $hash  = $row !== false ? (string)$row['password_hash'] : '$2y$12$aUNFm6N6w9FxrhL.RONr2OVm08gpGzEEn2U9JWQavJmf5HjXi9/Ra';
    $valid = password_verify($password, $hash) && $row !== false;

    if (!$valid) {
        record_admin_login($row !== false ? (int)$row['id'] : null, $username, false);
        return 'Usuário ou senha incorretos.';
    }

    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        $up = db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
        $up->execute([password_hash($password, PASSWORD_DEFAULT), $row['id']]);
    }

    record_admin_login((int)$row['id'], $username, true);
    db()->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = ?')->execute([$row['id']]);

    session_regenerate_id(true);
    $_SESSION['admin_id']       = (int)$row['id'];
    $_SESSION['admin_login_at'] = time();
    $_SESSION['admin_seen_at']  = time();
    unset($_SESSION['admin_expired']);

    return null;
}

/** Confere a senha atual do admin logado (para trocar usuário ou senha). */
function admin_password_ok(int $adminId, string $password): bool
{
    $stmt = db()->prepare('SELECT password_hash FROM admins WHERE id = ?');
    $stmt->execute([$adminId]);
    $hash = $stmt->fetchColumn();

    return $hash !== false && password_verify($password, (string)$hash);
}

function admin_uses_default_password(array $admin): bool
{
    return $admin['password_changed_at'] === null;
}

// --- mensagens de uma requisição para a próxima (padrão POST → redirect → GET) ---

function admin_flash(string $type, string $message): void
{
    $_SESSION['admin_flash'][] = ['type' => $type, 'message' => $message];
}

function admin_take_flashes(): array
{
    $flashes = $_SESSION['admin_flash'] ?? [];
    unset($_SESSION['admin_flash']);
    return is_array($flashes) ? $flashes : [];
}

// --- formatação ------------------------------------------------------------------

/**
 * US$ com precisão útil: o custo por usuário costuma ser fração de centavo
 * (uma análise ≈ US$ 0,005), então abaixo de US$ 1 mostra 4 casas.
 */
function money(float $value): string
{
    $decimals = $value !== 0.0 && abs($value) < 1 ? 4 : 2;
    return 'US$ ' . number_format($value, $decimals, ',', '.');
}

function num(int|float $value): string
{
    return number_format((float)$value, 0, ',', '.');
}

/** "12 set 2026, 14:05" — mesmo formato de data do app, com hora. */
function format_datetime(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '—';
    }
    $ts = strtotime($datetime);
    return $ts === false ? '—' : format_date($datetime) . ', ' . date('H:i', $ts);
}

/** "há 5 min", "há 3 dias" — leitura rápida de último acesso. */
function time_ago(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return 'nunca';
    }
    $diff = time() - (int)strtotime($datetime);
    return match (true) {
        $diff < 60        => 'agora',
        $diff < 3600      => 'há ' . intdiv($diff, 60) . ' min',
        $diff < 86400     => 'há ' . intdiv($diff, 3600) . ' h',
        $diff < 86400 * 30 => 'há ' . intdiv($diff, 86400) . ' dia' . (intdiv($diff, 86400) === 1 ? '' : 's'),
        default           => format_date($datetime),
    };
}

/** Avatar do usuário nas listas do painel (foto do Google ou inicial). */
function admin_avatar(array $row, string $extraClass = ''): string
{
    $class = trim('admin-avatar ' . (!empty($row['blocked_at']) ? 'is-blocked ' : '') . $extraClass);
    if (!empty($row['avatar_url'])) {
        $inner = '<img src="' . e((string)$row['avatar_url']) . '" alt="" width="36" height="36" loading="lazy" referrerpolicy="no-referrer">';
    } else {
        $inner = e(mb_strtoupper(mb_substr(trim((string)($row['name'] ?? '?')), 0, 1)));
    }
    return '<span class="' . e($class) . '" aria-hidden="true">' . $inner . '</span>';
}

function action_label(string $action): string
{
    return match ($action) {
        'analyze'   => 'Análise de pronúncia',
        'translate' => 'Tradução e fonética',
        'tts'       => 'Voz da pronúncia',
        default     => $action,
    };
}

/**
 * Períodos do filtro de custos. Datas no fuso do app (o MySQL segue o mesmo).
 *
 * @return array{key: string, label: string, from: ?string, to: ?string}
 */
function cost_period(string $key): array
{
    $today = date('Y-m-d');
    return match ($key) {
        'hoje'         => ['key' => 'hoje', 'label' => 'Hoje', 'from' => $today . ' 00:00:00', 'to' => null],
        '7d'           => ['key' => '7d', 'label' => 'Últimos 7 dias', 'from' => date('Y-m-d 00:00:00', strtotime('-6 days')), 'to' => null],
        '30d'          => ['key' => '30d', 'label' => 'Últimos 30 dias', 'from' => date('Y-m-d 00:00:00', strtotime('-29 days')), 'to' => null],
        'mes_anterior' => ['key' => 'mes_anterior', 'label' => 'Mês anterior',
                           'from' => date('Y-m-01 00:00:00', strtotime('first day of last month')),
                           'to'   => date('Y-m-01 00:00:00')],
        'tudo'         => ['key' => 'tudo', 'label' => 'Todo o período', 'from' => null, 'to' => null],
        default        => ['key' => 'mes', 'label' => 'Este mês', 'from' => date('Y-m-01 00:00:00'), 'to' => null],
    };
}

/** Cláusula e parâmetros de período para ai_usage (prefixo evita nome repetido no PDO). */
function period_sql(array $period, string $column = 'created_at', string $prefix = 'p'): array
{
    $where  = [];
    $params = [];
    if ($period['from'] !== null) {
        $where[] = "{$column} >= :{$prefix}_from";
        $params[":{$prefix}_from"] = $period['from'];
    }
    if ($period['to'] !== null) {
        $where[] = "{$column} < :{$prefix}_to";
        $params[":{$prefix}_to"] = $period['to'];
    }
    return [$where === [] ? '1=1' : implode(' AND ', $where), $params];
}
