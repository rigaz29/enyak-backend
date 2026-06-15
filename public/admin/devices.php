<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
requireAdmin();
$pdo = pdo();

function logAction(\PDO $pdo, int $deviceId, string $action, ?string $old, ?string $new): void
{
    $admin = currentAdmin();
    $pdo->prepare(
        'INSERT INTO activation_logs (device_id, admin_id, action, old_expiry, new_expiry) VALUES (?,?,?,?,?)',
    )->execute([$deviceId, $admin['id'] ?? null, $action, $old, $new]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $id = (int) post('id');
    $action = (string) post('action');
    $st = $pdo->prepare('SELECT * FROM devices WHERE id = ?');
    $st->execute([$id]);
    $d = $st->fetch();
    if ($d) {
        if ($action === 'extend') {
            $days = max(1, (int) post('days'));
            $hasActiveSub = !empty($d['subscription_expires_at']) && strtotime($d['subscription_expires_at']) > time();
            $base = $hasActiveSub ? strtotime($d['subscription_expires_at']) : time();
            $new = date('Y-m-d H:i:s', $base + $days * 86400);
            $pdo->prepare('UPDATE devices SET subscription_expires_at = ?, status = "active" WHERE id = ?')
                ->execute([$new, $id]);
            logAction($pdo, $id, 'extend', $d['subscription_expires_at'], $new);
        } elseif ($action === 'revoke') {
            $pdo->prepare('UPDATE devices SET subscription_expires_at = NULL WHERE id = ?')->execute([$id]);
            logAction($pdo, $id, 'revoke', $d['subscription_expires_at'], null);
        } elseif ($action === 'ban') {
            $pdo->prepare('UPDATE devices SET status = "banned" WHERE id = ?')->execute([$id]);
            logAction($pdo, $id, 'ban', null, null);
        } elseif ($action === 'unban') {
            $pdo->prepare('UPDATE devices SET status = "active" WHERE id = ?')->execute([$id]);
            logAction($pdo, $id, 'unban', null, null);
        }
    }
    redirect('devices.php' . (post('q') ? '?q=' . urlencode((string) post('q')) : ''));
}

$q = trim((string) query('q'));
if ($q !== '') {
    $st = $pdo->prepare('SELECT * FROM devices WHERE device_id LIKE ? ORDER BY last_seen DESC LIMIT 200');
    $st->execute(['%' . $q . '%']);
    $rows = $st->fetchAll();
} else {
    $rows = $pdo->query('SELECT * FROM devices ORDER BY last_seen DESC LIMIT 200')->fetchAll();
}

layout_header('Devices');
$csrf = csrfToken();
$now = time();
?>
<div class="card">
  <form method="get">
    <input name="q" value="<?= h($q) ?>" placeholder="cari Device ID..." style="width:320px">
    <button>Cari</button>
  </form>
</div>
<table>
  <tr><th>Device ID</th><th>Status</th><th>Trial s/d</th><th>Langganan s/d</th><th>Terakhir aktif</th><th>Aksi</th></tr>
  <?php foreach ($rows as $r):
      $sub = !empty($r['subscription_expires_at']) ? strtotime($r['subscription_expires_at']) : 0;
      $trial = !empty($r['trial_expires_at']) ? strtotime($r['trial_expires_at']) : 0;
      $status = $r['status'] === 'banned' ? 'banned' : ($sub > $now ? 'premium' : ($trial > $now ? 'trial' : 'free'));
      $color = ['banned' => '#c0392b', 'premium' => '#2e7d32', 'trial' => '#f9a825', 'free' => '#616161'][$status];
  ?>
  <tr>
    <td style="font-family:monospace"><?= h($r['device_id']) ?></td>
    <td><span class="pill" style="background:<?= $color ?>"><?= $status ?></span></td>
    <td class="muted"><?= h($r['trial_expires_at'] ?? '-') ?></td>
    <td class="muted"><?= h($r['subscription_expires_at'] ?? '-') ?></td>
    <td class="muted"><?= h($r['last_seen'] ?? '-') ?></td>
    <td>
      <form class="inline" method="post">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
        <input type="hidden" name="q" value="<?= h($q) ?>">
        <input type="number" name="days" value="30" min="1" style="width:64px"> hari
        <button name="action" value="extend">Aktifkan / Perpanjang</button>
        <button name="action" value="revoke" class="btn-danger">Cabut</button>
        <?php if ($r['status'] === 'banned'): ?>
          <button name="action" value="unban">Unban</button>
        <?php else: ?>
          <button name="action" value="ban" class="btn-danger">Ban</button>
        <?php endif; ?>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="muted">Belum ada device.</td></tr><?php endif; ?>
</table>
<?php
layout_footer();
