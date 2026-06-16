<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
requireAdmin();
$pdo = pdo();

/* ---------- Export M3U (real upstream URLs, admin backup) ---------- */
if (query('export') === 'm3u') {
    $rows = $pdo->query('SELECT * FROM channels ORDER BY sort_index, id')->fetchAll();
    header('Content-Type: audio/x-mpegurl; charset=utf-8');
    header('Content-Disposition: attachment; filename="enyak-channels.m3u"');
    echo "#EXTM3U\n";
    foreach ($rows as $r) {
        $name = str_replace('"', '', (string) $r['name']);
        $attrs = 'tvg-name="' . $name . '"';
        if ($r['logo_url']) $attrs .= ' tvg-logo="' . $r['logo_url'] . '"';
        if ($r['group_title']) $attrs .= ' group-title="' . str_replace('"', '', (string) $r['group_title']) . '"';
        echo "#EXTINF:-1 {$attrs}," . $name . "\n" . $r['stream_url'] . "\n";
    }
    exit;
}

/* ---------- Actions (single row, bulk, reorder) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();

    if (post('reorder') !== null) {
        $ids = array_filter(array_map('intval', explode(',', (string) post('order'))));
        $page = max(1, (int) post('page'));
        $base = ($page - 1) * 25;
        $pos = 0;
        $st = $pdo->prepare('UPDATE channels SET sort_index = ? WHERE id = ?');
        foreach ($ids as $cid) {
            $st->execute([$base + $pos, $cid]);
            $pos++;
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    if (($row = post('row')) !== null) {
        [$op, $idStr] = array_pad(explode(':', (string) $row, 2), 2, '');
        $id = (int) $idStr;
        if ($op === 'delete') {
            $pdo->prepare('DELETE FROM channels WHERE id = ?')->execute([$id]);
            flash('Channel dihapus.');
        } elseif ($op === 'toggle_enabled') {
            $pdo->prepare('UPDATE channels SET is_enabled = 1 - is_enabled WHERE id = ?')->execute([$id]);
            flash('Status channel diubah.');
        } elseif ($op === 'toggle_free') {
            $pdo->prepare('UPDATE channels SET is_free = 1 - is_free WHERE id = ?')->execute([$id]);
            flash('Akses channel (Gratis/Donasi) diubah.');
        }
    } elseif (post('bulk') !== null) {
        $op = (string) post('op');
        $ids = array_filter(array_map('intval', (array) post('ids', [])));
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            if ($op === 'enable') {
                $pdo->prepare("UPDATE channels SET is_enabled = 1 WHERE id IN ($in)")->execute($ids);
            } elseif ($op === 'disable') {
                $pdo->prepare("UPDATE channels SET is_enabled = 0 WHERE id IN ($in)")->execute($ids);
            } elseif ($op === 'free') {
                $pdo->prepare("UPDATE channels SET is_free = 1 WHERE id IN ($in)")->execute($ids);
            } elseif ($op === 'paid') {
                $pdo->prepare("UPDATE channels SET is_free = 0 WHERE id IN ($in)")->execute($ids);
            } elseif ($op === 'delete') {
                $pdo->prepare("DELETE FROM channels WHERE id IN ($in)")->execute($ids);
            }
            flash(count($ids) . ' channel diperbarui.');
        }
    }
    redirect('channels.php' . backQuery());
}

/* ---------- Filters + pagination ---------- */
$q = trim((string) query('q'));
$cat = trim((string) query('cat'));
$status = (string) query('status');     // '', '1', '0'
$premium = (string) query('premium');   // '', 'free', 'paid'

$where = [];
$args = [];
if ($q !== '') { $where[] = '(name LIKE ? OR group_title LIKE ?)'; $args[] = "%$q%"; $args[] = "%$q%"; }
if ($cat !== '') { $where[] = 'group_title = ?'; $args[] = $cat; }
if ($status !== '') { $where[] = 'is_enabled = ?'; $args[] = (int) $status; }
if ($premium !== '') { $where[] = 'is_free = ?'; $args[] = ($premium === 'free' ? 1 : 0); }
$wsql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$perPage = 25;
$page = max(1, (int) query('page', 1));
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM channels $wsql");
$countStmt->execute($args);
$total = (int) $countStmt->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$listStmt = $pdo->prepare("SELECT * FROM channels $wsql ORDER BY sort_index, id LIMIT $perPage OFFSET $offset");
$listStmt->execute($args);
$rows = $listStmt->fetchAll();

$cats = $pdo->query("SELECT DISTINCT group_title FROM channels WHERE group_title IS NOT NULL AND group_title <> '' ORDER BY group_title")
    ->fetchAll(\PDO::FETCH_COLUMN);

$reorderable = ($q === '' && $cat === '' && $status === '' && $premium === '');

function backQuery(): string
{
    $keep = array_filter([
        'q' => $_POST['q'] ?? $_GET['q'] ?? '',
        'cat' => $_POST['cat'] ?? $_GET['cat'] ?? '',
        'status' => $_POST['status'] ?? $_GET['status'] ?? '',
        'premium' => $_POST['premium'] ?? $_GET['premium'] ?? '',
        'page' => $_POST['page'] ?? $_GET['page'] ?? '',
    ], fn($v) => $v !== '');
    return $keep ? ('?' . http_build_query($keep)) : '';
}
function qLink(array $over): string
{
    $base = ['q' => $GLOBALS['q'], 'cat' => $GLOBALS['cat'], 'status' => $GLOBALS['status'], 'premium' => $GLOBALS['premium'], 'page' => $GLOBALS['page']];
    $m = array_filter(array_merge($base, $over), fn($v) => $v !== '' && $v !== null);
    return 'channels.php?' . http_build_query($m);
}

layout_header('Channels');
$csrf = csrfToken();
?>
<div class="card">
  <div class="row-inline" style="justify-content:space-between">
    <form class="row-inline" method="get" data-autosubmit style="flex:1">
      <input type="search" name="q" value="<?= h($q) ?>" data-autofocus placeholder="Cari nama / kategori…" style="max-width:240px">
      <select name="cat" style="max-width:170px">
        <option value="">Semua kategori</option>
        <?php foreach ($cats as $c): ?>
          <option value="<?= h($c) ?>" <?= $cat === $c ? 'selected' : '' ?>><?= h($c) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="status" style="max-width:140px">
        <option value="">Semua status</option>
        <option value="1" <?= $status === '1' ? 'selected' : '' ?>>Aktif</option>
        <option value="0" <?= $status === '0' ? 'selected' : '' ?>>Nonaktif</option>
      </select>
      <select name="premium" style="max-width:130px">
        <option value="">Free & Paid</option>
        <option value="free" <?= $premium === 'free' ? 'selected' : '' ?>>Gratis</option>
        <option value="paid" <?= $premium === 'paid' ? 'selected' : '' ?>>Donasi</option>
      </select>
      <noscript><button class="btn-ghost">Filter</button></noscript>
    </form>
    <div class="row-inline">
      <a href="channel_edit.php"><button><i data-lucide="plus"></i> Tambah</button></a>
      <a href="import.php"><button class="btn-ghost"><i data-lucide="upload"></i> Import</button></a>
      <a href="channels.php?export=m3u"><button class="btn-ghost"><i data-lucide="download"></i> Export</button></a>
    </div>
  </div>
</div>

<form method="post" x-data="{ checked: [], get all(){ return <?= count($rows) ?> > 0 && this.checked.length === <?= count($rows) ?> } }">
  <input type="hidden" name="csrf" value="<?= $csrf ?>">
  <input type="hidden" name="page" value="<?= $page ?>">
  <div class="card" x-show="checked.length" x-cloak style="display:flex;gap:10px;align-items:center">
    <b x-text="checked.length + ' dipilih'"></b>
    <select name="op" style="max-width:180px">
      <option value="enable">Aktifkan</option>
      <option value="disable">Nonaktifkan</option>
      <option value="free">Jadikan Gratis</option>
      <option value="paid">Jadikan Donasi</option>
      <option value="delete">Hapus</option>
    </select>
    <button name="bulk" value="1" @click="return confirm('Terapkan ke ' + checked.length + ' channel?')">Terapkan</button>
  </div>

  <div class="table-wrap">
    <table>
      <thead><tr>
        <th style="width:34px"><input type="checkbox" @change="checked = $event.target.checked ? [...document.querySelectorAll('.rowchk')].map(c=>c.value) : []" :checked="all"></th>
        <?php if ($reorderable): ?><th style="width:30px"></th><?php endif; ?>
        <th style="width:60px">#</th><th>Nama</th><th>Kategori</th><th>Tipe</th><th>Akses</th><th>Status</th><th>DRM</th><th style="width:230px">Aksi</th>
      </tr></thead>
      <tbody id="chlist">
        <?php foreach ($rows as $r): ?>
        <tr data-id="<?= (int) $r['id'] ?>">
          <td><input class="rowchk" type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>" x-model="checked"></td>
          <?php if ($reorderable): ?><td class="drag" style="cursor:grab;color:var(--muted)"><i data-lucide="grip-vertical"></i></td><?php endif; ?>
          <td class="muted"><?= (int) $r['sort_index'] ?></td>
          <td><?= h($r['name']) ?></td>
          <td class="muted"><?= h($r['group_title'] ?? '-') ?></td>
          <td><span class="badge badge-soft"><?= h($r['stream_type']) ?></span></td>
          <td><span class="pill" style="background:<?= $r['is_free'] ? '#616161' : '#2e7d32' ?>"><?= $r['is_free'] ? 'Gratis' : 'Donasi' ?></span></td>
          <td><span class="pill" style="background:<?= $r['is_enabled'] ? '#2d6cdf' : '#c0392b' ?>"><?= $r['is_enabled'] ? 'Aktif' : 'Nonaktif' ?></span></td>
          <td class="muted"><?= h($r['drm_scheme'] ?? '') ?></td>
          <td class="row-inline">
            <a href="channel_edit.php?id=<?= (int) $r['id'] ?>"><button type="button" class="btn-ghost btn-sm"><i data-lucide="pencil"></i></button></a>
            <button class="btn-ghost btn-sm" name="row" value="toggle_free:<?= (int) $r['id'] ?>"><?= $r['is_free'] ? 'Donasi' : 'Gratis' ?></button>
            <button class="btn-ghost btn-sm" name="row" value="toggle_enabled:<?= (int) $r['id'] ?>"><?= $r['is_enabled'] ? 'Off' : 'On' ?></button>
            <button class="btn-danger btn-sm" name="row" value="delete:<?= (int) $r['id'] ?>" @click="return confirm('Hapus channel ini?')"><i data-lucide="trash-2"></i></button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if (!$rows): ?>
    <div class="empty"><i data-lucide="tv"></i><div>Belum ada channel cocok. <a href="channel_edit.php">Tambah</a> atau <a href="import.php">Import M3U</a>.</div></div>
  <?php endif; ?>
</form>

<div class="row-inline" style="justify-content:space-between;margin-top:14px">
  <span class="muted">Total <?= $total ?> channel<?= $reorderable ? ' • seret ⠿ untuk mengurutkan' : '' ?></span>
  <div class="row-inline">
    <?php if ($page > 1): ?><a class="btn btn-ghost btn-sm" href="<?= h(qLink(['page' => $page - 1])) ?>">‹ Sebelumnya</a><?php endif; ?>
    <span class="muted">Hal <?= $page ?>/<?= $pages ?></span>
    <?php if ($page < $pages): ?><a class="btn btn-ghost btn-sm" href="<?= h(qLink(['page' => $page + 1])) ?>">Berikutnya ›</a><?php endif; ?>
  </div>
</div>

<?php if ($reorderable && $rows): ?>
<script defer src="https://unpkg.com/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var tb = document.getElementById('chlist');
  if (!tb || !window.Sortable) return;
  new Sortable(tb, {
    handle: '.drag', animation: 150,
    onEnd: function () {
      var order = [...tb.querySelectorAll('tr')].map(tr => tr.dataset.id).join(',');
      var body = new URLSearchParams({ csrf: '<?= $csrf ?>', reorder: '1', page: '<?= $page ?>', order: order });
      fetch('channels.php', { method: 'POST', body: body })
        .then(() => { if (window.lucide) lucide.createIcons(); });
    }
  });
});
</script>
<?php endif; ?>
<?php
layout_footer();
