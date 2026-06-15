<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

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

try {
    if ($method === 'POST' && $path === '/v1/sync') {
        (new SyncController())->handle();
    } elseif ($method === 'GET' && $path === '/v1/config') {
        (new ConfigController())->handle();
    } elseif ($method === 'GET' && preg_match('#^/s/(\d+)$#', $path, $m)) {
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
