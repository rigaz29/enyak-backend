<?php
declare(strict_types=1);

// Admin area bootstrap: config + DB + session + helpers + shared layout.
require __DIR__ . '/../../src/bootstrap.php';

use Enyak\Db;

if (session_status() !== PHP_SESSION_ACTIVE) {
    // Keep admin sessions in an app-private dir so neighbour vhosts' GC can't expire them early.
    $sessDir = __DIR__ . '/../../storage/sessions';
    if (!is_dir($sessDir)) {
        @mkdir($sessDir, 0700, true);
    }
    if (is_dir($sessDir) && is_writable($sessDir)) {
        session_save_path($sessDir);
    }
    $secure = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off');
    $remember = (($_COOKIE['enyak_remember'] ?? '') === '1');
    // 30 days when "remember me" is on, otherwise 4 hours (fixes the too-fast logout).
    $lifetime = $remember ? 60 * 60 * 24 * 30 : 60 * 60 * 4;
    @ini_set('session.gc_maxlifetime', (string) $lifetime);
    session_set_cookie_params([
        'lifetime' => $remember ? $lifetime : 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $secure,
    ]);
    session_start();
    // Sliding expiry: refresh the cookie on activity so a remembered session stays alive.
    if ($remember) {
        setcookie(session_name(), session_id(), [
            'expires' => time() + $lifetime,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => $secure,
        ]);
    }
}

// Basic security headers for every admin page.
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
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

function isSuperadmin(): bool
{
    return (currentAdmin()['role'] ?? '') === 'superadmin';
}

function requireSuperadmin(): void
{
    requireAdmin();
    if (!isSuperadmin()) {
        http_response_code(403);
        exit('Akses ditolak — khusus superadmin.');
    }
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

/** Compute display status from a device row. */
function deviceStatus(array $d): string
{
    if (($d['status'] ?? 'active') === 'banned') return 'banned';
    $now = time();
    if (!empty($d['subscription_expires_at']) && strtotime($d['subscription_expires_at']) > $now) return 'premium';
    if (!empty($d['trial_expires_at']) && strtotime($d['trial_expires_at']) > $now) return 'trial';
    return 'free';
}

function deviceStatusColor(string $s): string
{
    return ['banned' => '#c0392b', 'premium' => '#2e7d32', 'trial' => '#f59e0b', 'free' => '#616161'][$s] ?? '#616161';
}

function logActivation(\PDO $pdo, int $deviceId, string $action, ?string $old, ?string $new): void
{
    $a = currentAdmin();
    $pdo->prepare(
        'INSERT INTO activation_logs (device_id, admin_id, action, old_expiry, new_expiry) VALUES (?,?,?,?,?)',
    )->execute([$deviceId, $a['id'] ?? null, $action, $old, $new]);
}

/** Shared device action handler (used by devices list + detail). Sets a flash message. */
function applyDeviceAction(\PDO $pdo, int $id, string $action, int $days = 30): void
{
    $st = $pdo->prepare('SELECT * FROM devices WHERE id = ?');
    $st->execute([$id]);
    $d = $st->fetch();
    if (!$d) return;

    if ($action === 'extend') {
        $active = !empty($d['subscription_expires_at']) && strtotime($d['subscription_expires_at']) > time();
        $base = $active ? strtotime($d['subscription_expires_at']) : time();
        $new = date('Y-m-d H:i:s', $base + max(1, $days) * 86400);
        $pdo->prepare('UPDATE devices SET subscription_expires_at = ?, status = "active" WHERE id = ?')->execute([$new, $id]);
        logActivation($pdo, $id, 'extend', $d['subscription_expires_at'], $new);
        flash('Langganan diperpanjang sampai ' . $new . '.');
    } elseif ($action === 'revoke') {
        $pdo->prepare('UPDATE devices SET subscription_expires_at = NULL WHERE id = ?')->execute([$id]);
        logActivation($pdo, $id, 'revoke', $d['subscription_expires_at'], null);
        flash('Langganan dicabut.');
    } elseif ($action === 'ban') {
        $pdo->prepare('UPDATE devices SET status = "banned" WHERE id = ?')->execute([$id]);
        logActivation($pdo, $id, 'ban', null, null);
        flash('Device diblokir.');
    } elseif ($action === 'unban') {
        $pdo->prepare('UPDATE devices SET status = "active" WHERE id = ?')->execute([$id]);
        logActivation($pdo, $id, 'unban', null, null);
        flash('Device diaktifkan kembali.');
    }
}

require __DIR__ . '/_layout.php';
