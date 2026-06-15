<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
requireSuperadmin();
$pdo = pdo();
$me = currentAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = (string) post('action');
    if ($action === 'create') {
        $email = trim((string) post('email'));
        $pass = (string) post('password');
        $role = post('role') === 'superadmin' ? 'superadmin' : 'admin';
        if ($email === '' || strlen($pass) < 8) {
            flash('Email wajib & password minimal 8 karakter.', 'error');
        } else {
            try {
                $pdo->prepare('INSERT INTO admins (email, password_hash, role) VALUES (?, ?, ?)')
                    ->execute([$email, password_hash($pass, PASSWORD_BCRYPT), $role]);
                flash('Admin ditambahkan.');
            } catch (\PDOException $e) {
                flash('Gagal: email mungkin sudah dipakai.', 'error');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) post('id');
        if ($id === (int) $me['id']) {
            flash('Tidak bisa menghapus akun sendiri.', 'error');
        } else {
            $pdo->prepare('DELETE FROM admins WHERE id = ?')->execute([$id]);
            flash('Admin dihapus.');
        }
    }
    redirect('admins.php');
}

$rows = $pdo->query('SELECT id, email, role, created_at FROM admins ORDER BY id')->fetchAll();
layout_header('Admins');
$csrf = csrfToken();
?>
<div class="card" style="max-width:520px">
  <h3>Tambah admin</h3>
  <form method="post" class="row-inline" style="flex-wrap:wrap;gap:10px">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="action" value="create">
    <input name="email" type="email" placeholder="email" required style="flex:1;min-width:180px">
    <input name="password" type="password" placeholder="password (min 8)" required style="flex:1;min-width:140px">
    <select name="role" style="max-width:150px"><option value="admin">admin</option><option value="superadmin">superadmin</option></select>
    <button><i data-lucide="plus"></i> Tambah</button>
  </form>
</div>

<div class="table-wrap">
  <table>
    <thead><tr><th>Email</th><th>Role</th><th>Dibuat</th><th style="width:120px">Aksi</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= h($r['email']) ?><?= (int) $r['id'] === (int) $me['id'] ? ' <span class="muted">(kamu)</span>' : '' ?></td>
        <td><span class="badge badge-soft"><?= h($r['role']) ?></span></td>
        <td class="muted"><?= h($r['created_at']) ?></td>
        <td>
          <?php if ((int) $r['id'] !== (int) $me['id']): ?>
          <form class="inline" method="post" onsubmit="return confirm('Hapus admin ini?')">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <button class="btn-danger btn-sm"><i data-lucide="trash-2"></i></button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
layout_footer();
