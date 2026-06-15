<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
$me = requireAdmin();
$pdo = pdo();

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $cur = (string) post('current');
    $new = (string) post('new');
    $st = $pdo->prepare('SELECT password_hash FROM admins WHERE id = ?');
    $st->execute([$me['id']]);
    $row = $st->fetch();
    if (!$row || !password_verify($cur, $row['password_hash'])) {
        $err = 'Password saat ini salah.';
    } elseif (strlen($new) < 8) {
        $err = 'Password baru minimal 8 karakter.';
    } else {
        $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_BCRYPT), $me['id']]);
        flash('Password diperbarui.');
        redirect('profile.php');
    }
}

layout_header('Profil');
$csrf = csrfToken();
?>
<div class="card" style="max-width:480px">
  <h3>Profil</h3>
  <p>Email: <b><?= h($me['email']) ?></b></p>
  <p class="muted">Role: <?= h($me['role'] ?? 'admin') ?></p>
</div>
<form method="post">
  <input type="hidden" name="csrf" value="<?= $csrf ?>">
  <div class="card" style="max-width:480px">
    <h3>Ganti Password</h3>
    <?php if ($err): ?><p style="color:var(--danger)"><?= h($err) ?></p><?php endif; ?>
    <div class="field"><label>Password saat ini</label><input type="password" name="current" required></div>
    <div class="field"><label>Password baru (min 8)</label><input type="password" name="new" required></div>
    <button><i data-lucide="save"></i> Simpan</button>
  </div>
</form>
<?php
layout_footer();
