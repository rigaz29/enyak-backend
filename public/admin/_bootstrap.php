<?php
declare(strict_types=1);

// Admin area bootstrap: config + DB + session + helpers + shared layout.
require __DIR__ . '/../../src/bootstrap.php';

use Enyak\Db;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

function pdo(): \PDO
{
    return Db::conn();
}

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $to): void
{
    header("Location: $to");
    exit;
}

function currentAdmin(): ?array
{
    return $_SESSION['admin'] ?? null;
}

function requireAdmin(): array
{
    $a = currentAdmin();
    if ($a === null) {
        redirect('login.php');
    }
    return $a;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function checkCsrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) {
            http_response_code(400);
            exit('Bad CSRF token');
        }
    }
}

/** @return mixed */
function post(string $key, $default = null)
{
    return $_POST[$key] ?? $default;
}

/** @return mixed */
function query(string $key, $default = null)
{
    return $_GET[$key] ?? $default;
}

/** Set a one-shot flash message (rendered as a toast after the next redirect). */
function flash(string $msg, string $type = 'success'): void
{
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function takeFlash(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

require __DIR__ . '/_layout.php';
