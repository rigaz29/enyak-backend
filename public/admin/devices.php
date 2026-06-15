<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
requireAdmin();
$pdo = pdo();

/* ---------- Filters ---------- */
$q = trim((string) query('q'));
$status = (string) query('status');   // premium|trial|free|banned
$from = trim((string) query('from')); // created_at >= date (YYYY-MM-DD)

$where = [];
$args = [];
if ($q !== '') { $where[] = 'device_id LIKE ?'; $args[] = "%$q%"; }
if ($from !== '') { $where[] = 'created_at >= ?'; $args[] = $from . ' 00:00:00'; }
switch ($status) {
    case 'banned': $where[] = 'status = "banned"'; break;
    case 'premium': $where[] = 'status <> "banned" AND subscription_expires_at IS NOT NULL AND subscription_expires_at > NOW()'; break;
    case 'trial': $where[] = 'status <> "banned" AND (subscription_expires_at IS NULL OR subscription_expires_at <= NOW()) AND trial_expires_at IS NOT NULL AND trial_expires_at > NOW()'; break;
    case 'free': $where[] = 'status <> "banned" AND (subscription_expires_at IS NULL OR subscription_expires_at <= NOW()) AND (trial_expires_at IS NULL OR trial_expires_at <= NOW())'; break;
}
$wsql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* ---------- Export CSV (filtered) ---------- */
if (query('export') === 'csv') {
    $st = $pdo->prepare("SELECT * FROM devices $wsql ORDER BY last_seen DESC");
    $st->execute($args);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="enyak-devices.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['device_id', 'status', 'trial_expires_at', 'subscription_expires_at', 'last_seen', 'created_at', 'note_admin']);
    foreach ($st as $r) {
        fputcsv($out, [
            $r['device_id'], deviceStatus($r), $r['trial_expires_at'], $r['subscription_expires_at'],
            $r['last_seen'], $r['created_at'], $r['note_admin'],
        ]);
    }
    fclose($out);
    exit;
}

/* ---------- Actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    applyDeviceAction($pdo, (int) post('id'), (string) post('action'), (int) post('days'));
    redirect('devices.php' . devBackQuery());
}

/* ---------- Pagination ---------- */
$perPage = 25;
$page = max(1, (int) query('page', 1));
$cs = $pdo->prepare("SELECT COUNT(*) FROM devices $wsql");
$cs->execute($args);
$total = (int) $cs->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;
$ls = $pdo->prepare("SELECT * FROM devices $wsql ORDER BY last_seen DESC LIMIT $perPage OFFSET $offset");
$ls->execute($args);
$rows = $ls->fetchAll();

function devBackQuery(): string
{
    $keep = array_filter([
        'q' => $_POST['q'] ?? $_GET['q'] ?? '',
        'status' => $_POST['status'] ?? $_GET['status'] ?? '',
        'from' => $_POST['from'] ?? $_GET['from'] ?? '',
        'page' => $_POST['page'] ?? $_GET['page'] ?? '',
    ], fn($v) => $v !== '');
    return $keep ? ('?' . http_build_query($keep)) : '';
}
function devLink(array $over): string
{
    $m = array_filter(array_merge(
        ['q' => $GLOBALS['q'], 'status' => $GLOBALS['status'], 'from' => $GLOBALS['from'], 'page' => $GLOBALS['page']],
        $over,
    ), fn($v) => $v !== '' && $v !== null);
    return 'devices.php?' . http_build_query($m);
}

layout_header('Devices');
$csrf = csrfToken();
?>
<div class="card">
  <div class="row-inline" style="justify-content:space-between">
    <form class="row-inline" method="get" style="flex:1">
      <input name="q" value="<?= h($q) ?>" placeholder="Cari Device ID…" style="max-width:240px">
      <select name="status" style="max-width:150px">
        <option value="">Semua status</option>
        <?php foreach (['premium' => 'Premium', 'trial' => 'Trial', 'free' => 'Free', 'banned' => 'Banned'] as $k => $lbl): ?>
          <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $lbl ?></option>
        <?php endforeach; ?>
      </select>
      <label class="muted">Terdaftar dari</label>
      <input type="date" name="from" value="<?= h($from) ?>" style="max-width:160px">
      <button class="btn-ghost"><i data-lucide="search"></i> Filter</button>
    </form>
    <a href="<?= h(devLink(['export' => 'csv', 'page' => ''])) ?>"><button class="btn-ghost"><i data-lucide="download"></i> Export CSV</button></a>
  </div>
</div>

<div class="table-wrap">
  <table>
    <thead><tr><th>Device ID</th><th>Status</th><th>Trial s/d</th><th>Langganan s/d</th><th>Terakhir aktif</th><th style="width:280px">Aksi cepat</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): $s = deviceStatus($r); ?>
      <tr>
        <td><a class="mono" href="device_view.php?id=<?= (int) $r['id'] ?>"><?= h($r['device_id']) ?></a></td>
        <td><span class="pill" style="background:<?= deviceStatusColor($s) ?>"><?= $s ?></span></td>
        <td class="muted"><?= h($r['trial_expires_at'] ?? '-') ?></td>
        <td class="muted"><?= h($r['subscription_expires_at'] ?? '-') ?></td>
        <td class="muted"><?= h($r['last_seen'] ?? '-') ?></td>
        <td>
          <form class="inline row-inline" method="post" style="gap:6px">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <input type="hidden" name="q" value="<?= h($q) ?>"><input type="hidden" name="status" value="<?= h($status) ?>">
            <input type="hidden" name="from" value="<?= h($from) ?>"><input type="hidden" name="page" value="<?= $page ?>">
            <input type="number" name="days" value="30" min="1" style="width:62px"><span class="muted">hr</span>
            <button class="btn-sm" name="action" value="extend">Aktifkan</button>
            <button class="btn-ghost btn-sm" name="action" value="revoke">Cabut</button>
            <?php if ($r['status'] === 'banned'): ?>
              <button class="btn-sm" name="action" value="unban">Unban</button>
            <?php else: ?>
              <button class="btn-danger btn-sm" name="action" value="ban">Ban</button>
            <?php endif; ?>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php if (!$rows): ?>
  <div class="empty"><i data-lucide="users"></i><div>Belum ada device cocok.</div></div>
<?php endif; ?>

<div class="row-inline" style="justify-content:space-between;margin-top:14px">
  <span class="muted">Total <?= $total ?> device</span>
  <div class="row-inline">
    <?php if ($page > 1): ?><a class="btn btn-ghost btn-sm" href="<?= h(devLink(['page' => $page - 1])) ?>">‹</a><?php endif; ?>
    <span class="muted">Hal <?= $page ?>/<?= $pages ?></span>
    <?php if ($page < $pages): ?><a class="btn btn-ghost btn-sm" href="<?= h(devLink(['page' => $page + 1])) ?>">›</a><?php endif; ?>
  </div>
</div>
<?php
layout_footer();
