<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

// One-time: create the first admin when none exists. Delete this file afterwards.
$count = (int) pdo()->query('SELECT COUNT(*) FROM admins')->fetchColumn();
if ($count > 0) {
    http_response_code(403);
    exit('Setup sudah selesai. Hapus file setup.php.');
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) post('email'));
    $pass = (string) post('password');
    if ($email !== '' && strlen($pass) >= 8) {
        // First admin is the superadmin (can manage other admins).
        pdo()->prepare('INSERT INTO admins (email, password_hash, role) VALUES (?, ?, "superadmin")')
            ->execute([$email, password_hash($pass, PASSWORD_BCRYPT)]);
        redirect('login.php');
    }
    $err = 'Email wajib diisi & password minimal 8 karakter.';
}

layout_header('Setup');
?>
<div class="card" style="max-width:380px">
  <h2>Buat admin pertama</h2>
  <?php if ($err) echo '<p style="color:#ff8a80">' . h($err) . '</p>'; ?>
  <form method="post">
    <p><input name="email" type="email" placeholder="email" required style="width:100%"></p>
    <p><input name="password" type="password" placeholder="password (min 8)" required style="width:100%"></p>
    <button>Buat admin</button>
  </form>
  <p class="muted">Setelah berhasil, <strong>hapus file setup.php</strong> demi keamanan.</p>
</div>
<?php
layout_footer();
