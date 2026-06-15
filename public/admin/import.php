<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
requireAdmin();

use Enyak\M3u;

$pdo = pdo();
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $raw = (string) post('m3u');
    $defaultFree = post('default_free') ? 1 : 0;
    $replace = (bool) post('replace');
    $items = M3u::parse($raw);
    if ($items) {
        if ($replace) {
            $pdo->exec('DELETE FROM channels');
        }
        $sort = (int) $pdo->query('SELECT COALESCE(MAX(sort_index), 0) FROM channels')->fetchColumn();
        $stmt = $pdo->prepare(
            'INSERT INTO channels (name, group_title, logo_url, stream_url, stream_type, is_free, is_enabled,
             sort_index, drm_scheme, drm_clearkey)
             VALUES (?,?,?,?,?,?,1,?,?,?)',
        );
        foreach ($items as $it) {
            $sort++;
            $stmt->execute([
                $it['name'], $it['group'], $it['logo'], $it['url'], $it['stream_type'],
                $defaultFree, $sort, $it['drm_scheme'], $it['drm_clearkey'],
            ]);
        }
        $msg = count($items) . ' channel diimpor.';
    } else {
        $msg = 'Tidak ada channel terbaca dari teks tersebut.';
    }
}

layout_header('Import M3U');
$csrf = csrfToken();
?>
<div class="card">
  <h2>Import M3U</h2>
  <?php if ($msg) echo '<p style="color:#8bc34a">' . h($msg) . '</p>'; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <p>Tempel isi playlist M3U (stream URL = URL asli, otomatis disembunyikan saat dikirim ke app):</p>
    <textarea name="m3u" rows="14" style="width:100%" placeholder="#EXTM3U&#10;#EXTINF:-1 group-title=&quot;News&quot;,Channel A&#10;https://contoh.com/a.m3u8"></textarea>
    <p>
      <label><input type="checkbox" name="default_free"> Tandai semua sebagai Free</label>
      &nbsp;&nbsp;
      <label><input type="checkbox" name="replace"> Ganti seluruh katalog (hapus yang lama dulu)</label>
    </p>
    <button>Import</button>
  </form>
  <p class="muted">Channel terimpor langsung aktif. Atur Free/Paid & DRM per channel di halaman Channels.</p>
</div>
<?php
layout_footer();
