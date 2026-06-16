<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
requireAdmin();
$pdo = pdo();

$id = (int) query('id');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    applyDeviceAction($pdo, $id, (string) post('action'), (int) post('days'));
    redirect('device_view.php?id=' . $id);
}

$st = $pdo->prepare('SELECT * FROM devices WHERE id = ?');
$st->execute([$id]);
$d = $st->fetch();
if (!$d) {
    redirect('devices.php');
}

$logsSt = $pdo->prepare('SELECT * FROM activation_logs WHERE device_id = ? ORDER BY id DESC LIMIT 50');
$logsSt->execute([$id]);
$logs = $logsSt->fetchAll();

$s = deviceStatus($d);
layout_header('Detail Device');
$csrf = csrfToken();
?>
<p><a href="devices.php" class="muted">‹ Kembali ke Devices</a></p>

<div class="grid cols-4">
  <div class="card stat"><span class="lbl">Status</span><span class="num"><span class="pill" style="background:<?= deviceStatusColor($s) ?>;font-size:16px"><?= $s ?></span></span></div>
  <div class="card stat"><span class="lbl">Trial s/d</span><span class="num" style="font-size:15px"><?= h($d['trial_expires_at'] ?? '-') ?></span></div>
  <div class="card stat"><span class="lbl">Langganan s/d</span><span class="num" style="font-size:15px"><?= h($d['subscription_expires_at'] ?? '-') ?></span></div>
  <div class="card stat"><span class="lbl">Terakhir aktif</span><span class="num" style="font-size:15px"><?= h($d['last_seen'] ?? '-') ?></span></div>
</div>

<div class="card">
  <h3>Device</h3>
  <p class="mono"><?= h($d['device_id']) ?></p>
  <p class="muted">Model: <?= h($d['device_model'] ?? '-') ?></p>
  <p class="muted">OS: <?= h($d['os_version'] ?? '-') ?> &nbsp;•&nbsp; App: <?= h($d['app_version'] ?? '-') ?></p>
  <p class="muted">IP terakhir: <?= h($d['last_ip'] ?? '-') ?></p>
  <p class="muted">Terdaftar: <?= h($d['created_at'] ?? '-') ?></p>
</div>

<div class="card">
  <h3>Aktivasi langganan</h3>
  <form class="row-inline" method="post">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="number" name="days" value="30" min="1" style="width:90px"><span class="muted">hari</span>
    <button name="action" value="extend"><i data-lucide="check"></i> Aktifkan / Perpanjang</button>
    <button class="btn-ghost" name="action" value="revoke">Cabut langganan</button>
    <?php if ($d['status'] === 'banned'): ?>
      <button name="action" value="unban">Unban</button>
    <?php else: ?>
      <button class="btn-danger" name="action" value="ban">Ban device</button>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <h3>Riwayat aktivasi</h3>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Waktu</th><th>Aksi</th><th>Expiry lama</th><th>Expiry baru</th></tr></thead>
      <tbody>
        <?php foreach ($logs as $l): ?>
        <tr>
          <td class="muted"><?= h($l['created_at']) ?></td>
          <td><span class="badge badge-soft"><?= h($l['action']) ?></span></td>
          <td class="muted"><?= h($l['old_expiry'] ?? '-') ?></td>
          <td class="muted"><?= h($l['new_expiry'] ?? '-') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if (!$logs): ?><p class="muted">Belum ada riwayat.</p><?php endif; ?>
</div>
<?php
layout_footer();
