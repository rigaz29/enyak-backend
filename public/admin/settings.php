<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
requireAdmin();
$pdo = pdo();

// Editable remote-config keys (stored in the settings table).
$fields = [
    ['k' => 'website_url',         'label' => 'URL Website Langganan', 'type' => 'text'],
    ['k' => 'promo_video_url',     'label' => 'URL Video Promo',       'type' => 'text'],
    ['k' => 'min_app_version',     'label' => 'Min Versi App (force update)', 'type' => 'text'],
    ['k' => 'update_url',          'label' => 'URL Update App',         'type' => 'text'],
    ['k' => 'trial_seconds',       'label' => 'Durasi Trial (detik)',   'type' => 'number'],
    ['k' => 'maintenance_enabled', 'label' => 'Mode Maintenance',       'type' => 'toggle'],
    ['k' => 'maintenance_message', 'label' => 'Pesan Maintenance',      'type' => 'textarea'],
    ['k' => 'announcement',        'label' => 'Pengumuman (in-app)',    'type' => 'textarea'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $stmt = $pdo->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
    foreach ($fields as $f) {
        $val = $f['type'] === 'toggle' ? (post($f['k']) ? '1' : '0') : trim((string) post($f['k']));
        $stmt->execute([$f['k'], $val]);
    }
    flash('Pengaturan disimpan.');
    redirect('settings.php');
}

$cur = [];
foreach ($pdo->query('SELECT k, v FROM settings') as $row) {
    $cur[$row['k']] = $row['v'];
}

layout_header('Settings');
$csrf = csrfToken();
?>
<form method="post">
  <input type="hidden" name="csrf" value="<?= $csrf ?>">
  <div class="card" style="max-width:680px">
    <h3>Konfigurasi App (remote)</h3>
    <p class="muted">Perubahan berlaku tanpa update app (lewat /v1/config & /v1/sync).</p>
    <?php foreach ($fields as $f): $val = $cur[$f['k']] ?? ''; ?>
      <div class="field">
        <label><?= h($f['label']) ?></label>
        <?php if ($f['type'] === 'textarea'): ?>
          <textarea name="<?= $f['k'] ?>" rows="2"><?= h($val) ?></textarea>
        <?php elseif ($f['type'] === 'toggle'): ?>
          <label class="row-inline"><input type="checkbox" name="<?= $f['k'] ?>" <?= $val === '1' ? 'checked' : '' ?>> Aktif</label>
        <?php elseif ($f['type'] === 'number'): ?>
          <input type="number" name="<?= $f['k'] ?>" value="<?= h($val) ?>">
        <?php else: ?>
          <input name="<?= $f['k'] ?>" value="<?= h($val) ?>">
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <button><i data-lucide="save"></i> Simpan</button>
  </div>
</form>
<?php
layout_footer();
