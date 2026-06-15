<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

if (currentAdmin() !== null) {
    redirect('devices.php');
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0';
    if (!\Enyak\RateLimiter::allow("login:$ip", 10, 300)) {
        $err = 'Terlalu banyak percobaan login. Coba lagi beberapa menit.';
    } else {
        $email = trim((string) post('email'));
        $pass = (string) post('password');
        $st = pdo()->prepare('SELECT * FROM admins WHERE email = ?');
        $st->execute([$email]);
        $a = $st->fetch();
        if ($a && password_verify($pass, $a['password_hash'])) {
            $remember = (bool) post('remember');
            $secure = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off');
            session_regenerate_id(true);
            $_SESSION['admin'] = ['id' => (int) $a['id'], 'email' => $a['email'], 'role' => $a['role'] ?? 'admin'];
            setcookie('enyak_remember', $remember ? '1' : '', [
                'expires' => $remember ? time() + 60 * 60 * 24 * 30 : time() - 3600,
                'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $secure,
            ]);
            if ($remember) {
                @ini_set('session.gc_maxlifetime', (string) (60 * 60 * 24 * 30));
                setcookie(session_name(), session_id(), [
                    'expires' => time() + 60 * 60 * 24 * 30,
                    'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $secure,
                ]);
            }
            redirect('dashboard.php');
        }
        $err = 'Email atau password salah.';
    }
}

layout_header('Masuk');
?>
<div class="card" style="max-width:360px">
  <h2>Masuk Admin</h2>
  <?php if ($err) echo '<p style="color:#ff8a80">' . h($err) . '</p>'; ?>
  <form method="post">
    <p><input name="email" type="email" placeholder="email" required style="width:100%"></p>
    <p><input name="password" type="password" placeholder="password" required style="width:100%"></p>
    <p><label><input type="checkbox" name="remember" checked> Ingat saya (tetap login 30 hari)</label></p>
    <button>Masuk</button>
  </form>
</div>
<?php
layout_footer();
