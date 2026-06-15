<?php
declare(strict_types=1);

// Create/update an admin account (used by the Fase 3 dashboard).
// Usage:  php tools/make_admin.php admin@enyak.my.id 'YourPassword'
require __DIR__ . '/../src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
if ($argc < 3) {
    fwrite(STDERR, "Usage: php tools/make_admin.php <email> <password>\n");
    exit(1);
}

[$_, $email, $password] = $argv;
$hash = password_hash($password, PASSWORD_BCRYPT);

\Enyak\Db::conn()
    ->prepare('INSERT INTO admins (email, password_hash) VALUES (?, ?)
               ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)')
    ->execute([$email, $hash]);

echo "Admin saved: {$email}\n";
