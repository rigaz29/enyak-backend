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
            session_regenerate_id(true);
            $_SESSION['admin'] = ['id' => (int) $a['id'], 'email' => $a['email'], 'role' => $a['role'] ?? 'admin'];
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
    <button>Masuk</button>
  </form>
</div>
<?php
layout_footer();
