<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
requireAdmin();
$pdo = pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $id = (int) post('id');
    $action = (string) post('action');
    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM channels WHERE id = ?')->execute([$id]);
    } elseif ($action === 'toggle_enabled') {
        $pdo->prepare('UPDATE channels SET is_enabled = 1 - is_enabled WHERE id = ?')->execute([$id]);
    } elseif ($action === 'toggle_free') {
        $pdo->prepare('UPDATE channels SET is_free = 1 - is_free WHERE id = ?')->execute([$id]);
    }
    redirect('channels.php');
}

$rows = $pdo->query('SELECT * FROM channels ORDER BY sort_index, id')->fetchAll();
layout_header('Channels');
$csrf = csrfToken();
?>
<div class="card">
  <a href="channel_edit.php"><button>+ Tambah channel</button></a>
  <a href="import.php"><button>Import M3U</button></a>
  <span class="muted">Total: <?= count($rows) ?></span>
</div>
<table>
  <tr><th>#</th><th>Nama</th><th>Kategori</th><th>Tipe</th><th>Free</th><th>Aktif</th><th>DRM</th><th>Aksi</th></tr>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><?= (int) $r['sort_index'] ?></td>
    <td><?= h($r['name']) ?></td>
    <td class="muted"><?= h($r['group_title'] ?? '-') ?></td>
    <td class="muted"><?= h($r['stream_type']) ?></td>
    <td><?= $r['is_free'] ? '✅' : '—' ?></td>
    <td><?= $r['is_enabled'] ? '✅' : '—' ?></td>
    <td class="muted"><?= h($r['drm_scheme'] ?? '') ?></td>
    <td>
      <a href="channel_edit.php?id=<?= (int) $r['id'] ?>"><button>Edit</button></a>
      <form class="inline" method="post">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
        <button name="action" value="toggle_free"><?= $r['is_free'] ? 'Jadikan Paid' : 'Jadikan Free' ?></button>
        <button name="action" value="toggle_enabled"><?= $r['is_enabled'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
        <button name="action" value="delete" class="btn-danger" onclick="return confirm('Hapus channel ini?')">Hapus</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="8" class="muted">Belum ada channel. Tambah manual atau Import M3U.</td></tr><?php endif; ?>
</table>
<?php
layout_footer();
