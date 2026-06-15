<?php
declare(strict_types=1);

// Minimal PSR-4-ish autoloader for the Enyak\ namespace -> src/. No Composer needed.
spl_autoload_register(function (string $class): void {
    $prefix = 'Enyak\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $rel  = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $rel) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$configFile = __DIR__ . '/../config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'config.php missing — copy config/config.example.php to config/config.php']);
    exit;
}
\Enyak\Config::load($configFile);
