<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
requireAdmin();
$pdo = pdo();

$totalDev = (int) $pdo->query('SELECT COUNT(*) FROM devices')->fetchColumn();
$activeDev = (int) $pdo->query(
    'SELECT COUNT(*) FROM devices WHERE status <> "banned" AND
     ((subscription_expires_at IS NOT NULL AND subscription_expires_at > NOW())
      OR (trial_expires_at IS NOT NULL AND trial_expires_at > NOW()))',
)->fetchColumn();
$totalCh = (int) $pdo->query('SELECT COUNT(*) FROM channels')->fetchColumn();
$activeCh = (int) $pdo->query('SELECT COUNT(*) FROM channels WHERE is_enabled = 1')->fetchColumn();
$newDev = (int) $pdo->query('SELECT COUNT(*) FROM devices WHERE created_at >= (NOW() - INTERVAL 7 DAY)')->fetchColumn();

// Registrations per day (last 14 days).
$reg = $pdo->query(
    'SELECT DATE(created_at) d, COUNT(*) c FROM devices WHERE created_at >= (NOW() - INTERVAL 14 DAY) GROUP BY DATE(created_at)',
)->fetchAll(\PDO::FETCH_KEY_PAIR);
$regLabels = [];
$regVals = [];
for ($i = 13; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i day"));
    $regLabels[] = date('d/m', strtotime($day));
    $regVals[] = (int) ($reg[$day] ?? 0);
}

// Channels per category (top 8).
$cat = $pdo->query(
    "SELECT COALESCE(NULLIF(group_title, ''), 'Lainnya') g, COUNT(*) c FROM channels GROUP BY g ORDER BY c DESC LIMIT 8",
)->fetchAll();
$catLabels = array_column($cat, 'g');
$catVals = array_map('intval', array_column($cat, 'c'));

layout_header('Dashboard');
?>
<div class="grid cols-4">
  <div class="card stat"><span class="lbl">Total Device</span><span class="num"><?= number_format($totalDev) ?></span></div>
  <div class="card stat"><span class="lbl">Device Aktif (premium/trial)</span><span class="num" style="color:var(--success)"><?= number_format($activeDev) ?></span></div>
  <div class="card stat"><span class="lbl">Total Channel</span><span class="num"><?= number_format($totalCh) ?></span></div>
  <div class="card stat"><span class="lbl">Channel Aktif</span><span class="num"><?= number_format($activeCh) ?></span></div>
</div>
<div class="grid cols-4" style="margin-bottom:16px">
  <div class="card stat"><span class="lbl">Registrasi 7 hari</span><span class="num" style="color:var(--primary)"><?= number_format($newDev) ?></span></div>
</div>

<div class="grid" style="grid-template-columns:3fr 2fr">
  <div class="card"><h3>Registrasi device (14 hari)</h3><canvas id="cReg" height="120"></canvas></div>
  <div class="card"><h3>Channel per kategori</h3><canvas id="cCat" height="120"></canvas></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var grid = getComputedStyle(document.documentElement).getPropertyValue('--border') || '#333';
  var txt = getComputedStyle(document.documentElement).getPropertyValue('--muted') || '#999';
  Chart.defaults.color = txt.trim();
  Chart.defaults.borderColor = grid.trim();

  new Chart(document.getElementById('cReg'), {
    type: 'line',
    data: { labels: <?= json_encode($regLabels) ?>,
      datasets: [{ label: 'Registrasi', data: <?= json_encode($regVals) ?>,
        borderColor: '#4f8cff', backgroundColor: 'rgba(79,140,255,.15)', fill: true, tension: .35 }] },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
  });

  new Chart(document.getElementById('cCat'), {
    type: 'bar',
    data: { labels: <?= json_encode($catLabels) ?>,
      datasets: [{ label: 'Channel', data: <?= json_encode($catVals) ?>, backgroundColor: '#7c5cff' }] },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
  });
});
</script>
<?php
layout_footer();
