<?php
declare(strict_types=1);

/**
 * Ponto de entrada de todo arquivo PHP do app.
 * Carrega config, configura erros e sessão, expõe os helpers comuns.
 */

/** Caminho absoluto de includes/ — use para incluir header.php e footer.php. */
define('APP_INCLUDES', __DIR__);

require_once __DIR__ . '/../config/config.php';

// Produção: nunca exibir erro ao usuário; sempre logar em arquivo dedicado
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', LOG_FILE);
error_reporting(E_ALL);

// Produto sediado nos EUA: o fuso vem do config e vale para o PHP e para o MySQL
date_default_timezone_set(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'America/New_York');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';

start_app_session();

/**
 * Sessão com cookie httponly + secure + samesite=Lax (RF-01).
 * Os parâmetros precisam ser definidos ANTES do session_start().
 */
function start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('voxlyone');
    session_start();
}

/**
 * Data legível e sem ambiguidade: "12 ago 2026".
 *
 * Evita de propósito o formato numérico: 12/08 é 12 de agosto para um leitor
 * brasileiro e 8 de dezembro para um americano. Como o produto é dos EUA mas a
 * interface é em português, o mês escrito remove a dúvida para os dois públicos.
 */
function format_date(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '—';
    }

    static $months = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun',
                      'jul', 'ago', 'set', 'out', 'nov', 'dez'];

    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return '—';
    }

    return date('j', $timestamp) . ' '
         . $months[(int)date('n', $timestamp) - 1] . ' '
         . date('Y', $timestamp);
}

/** Escapa saída para HTML. Usar em TODO dado que veio do usuário. */
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** Redireciona e encerra. */
function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

/** Resposta JSON de sucesso para endpoints /api. */
function json_ok(array $data = [], int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Resposta JSON de erro. A mensagem vai para o usuário (genérica, PT-BR);
 * o detalhe técnico vai só para o log.
 */
function json_error(string $code, string $message, int $status = 400, string $logDetail = ''): never
{
    if ($logDetail !== '') {
        error_log(sprintf('[%s] %s | %s', $code, $message, $logDetail));
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error'   => ['code' => $code, 'message' => $message],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Aborta se o método HTTP não for o esperado. */
function require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        json_error('method_not_allowed', 'Requisição inválida.', 405);
    }
}
