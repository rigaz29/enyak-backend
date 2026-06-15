<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Enyak\RateLimiter;
use Enyak\Response;
use Enyak\Controllers\SyncController;
use Enyak\Controllers\ConfigController;
use Enyak\Controllers\StreamController;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path   = rtrim($path, '/');
if ($path === '') {
    $path = '/';
}
$ip = $_SERVER['REMOTE_ADDR'] ?? '0';

try {
    if ($method === 'POST' && $path === '/v1/sync') {
        if (!RateLimiter::allow("sync:$ip", 60, 60)) {
            Response::error('Terlalu banyak permintaan, coba lagi nanti.', 429);
        }
        (new SyncController())->handle();
    } elseif ($method === 'GET' && $path === '/v1/config') {
        (new ConfigController())->handle();
    } elseif ($method === 'GET' && preg_match('#^/s/(\d+)$#', $path, $m)) {
        if (!RateLimiter::allow("s:$ip", 120, 60)) {
            Response::error('Terlalu banyak permintaan, coba lagi nanti.', 429);
        }
        (new StreamController())->handle((int) $m[1]);
    } elseif ($path === '/' || $path === '/health') {
        Response::json(['ok' => true, 'service' => 'enyak-backend']);
    } else {
        Response::error('Not found', 404);
    }
} catch (\Throwable $e) {
    error_log('[enyak] ' . $e->getMessage());
    Response::error('Server error', 500);
}
