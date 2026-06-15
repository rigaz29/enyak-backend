<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$_SESSION = [];
session_destroy();
setcookie('enyak_remember', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off'),
]);
redirect('login.php');
