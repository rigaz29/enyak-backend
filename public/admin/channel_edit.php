<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
requireAdmin();
$pdo = pdo();

$id = (int) query('id');
$ch = null;
if ($id) {
    $st = $pdo->prepare('SELECT * FROM channels WHERE id = ?');
    $st->execute([$id]);
    $ch = $st->fetch() ?: null;
}

// Convenience: User-Agent / Referer are stored inside the headers JSON column.
$hdr = ($ch && $ch['headers']) ? (json_decode((string) $ch['headers'], true) ?: []) : [];
$ua = $hdr['User-Agent'] ?? '';
$ref = $hdr['Referer'] ?? '';

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $headers = [];
    if (trim((string) post('user_agent')) !== '') $headers['User-Agent'] = trim((string) post('user_agent'));
    if (trim((string) post('referer')) !== '') $headers['Referer'] = trim((string) post('referer'));

    $data = [
        'name' => trim((string) post('name')),
        'group_title' => trim((string) post('group_title')) ?: null,
        'logo_url' => trim((string) post('logo_url')) ?: null,
        'stream_url' => trim((string) post('stream_url')),
        'stream_type' => in_array(post('stream_type'), ['hls', 'dash', 'other'], true) ? post('stream_type') : 'other',
        'is_free' => post('is_free') === '1' ? 1 : 0,
        'is_enabled' => post('is_enabled') === '1' ? 1 : 0,
        'sort_index' => (int) post('sort_index'),
        'drm_scheme' => trim((string) post('drm_scheme')) ?: null,
        'drm_license_url' => trim((string) post('drm_license_url')) ?: null,
        'drm_clearkey' => trim((string) post('drm_clearkey')) ?: null,
        'headers' => $headers ? json_encode($headers) : null,
    ];
    if ($data['name'] === '' || $data['stream_url'] === '') {
        $err = 'Nama dan Stream URL wajib diisi.';
    } else {
        if ($id) {
            $data['id'] = $id;
            $pdo->prepare(
                'UPDATE channels SET name=:name, group_title=:group_title, logo_url=:logo_url, stream_url=:stream_url,
                 stream_type=:stream_type, is_free=:is_free, is_enabled=:is_enabled, sort_index=:sort_index,
                 drm_scheme=:drm_scheme, drm_license_url=:drm_license_url, drm_clearkey=:drm_clearkey, headers=:headers
                 WHERE id=:id',
            )->execute($data);
            flash('Channel diperbarui.');
        } else {
            $pdo->prepare(
                'INSERT INTO channels (name, group_title, logo_url, stream_url, stream_type, is_free, is_enabled,
                 sort_index, drm_scheme, drm_license_url, drm_clearkey, headers)
                 VALUES (:name,:group_title,:logo_url,:stream_url,:stream_type,:is_free,:is_enabled,:sort_index,
                 :drm_scheme,:drm_license_url,:drm_clearkey,:headers)',
            )->execute($data);
            flash('Channel ditambahkan.');
        }
        redirect('channels.php');
    }
}

$cats = $pdo->query("SELECT DISTINCT group_title FROM channels WHERE group_title IS NOT NULL AND group_title <> '' ORDER BY group_title")
    ->fetchAll(\PDO::FETCH_COLUMN);
$v = fn(string $k, string $d = '') => h((string) ($ch[$k] ?? $d));

layout_header($id ? 'Edit Channel' : 'Tambah Channel');
$csrf = csrfToken();
?>
<form method="post" x-data="{ logo: '<?= $v('logo_url') ?>' }">
  <input type="hidden" name="csrf" value="<?= $csrf ?>">
  <?php if ($err): ?><div class="card" style="border-color:var(--danger);color:var(--danger)"><?= h($err) ?></div><?php endif; ?>

  <div class="card">
    <h3>Utama</h3>
    <div class="field"><label>Nama</label><input name="name" value="<?= $v('name') ?>" required></div>
    <div class="field">
      <label>Kategori</label>
      <input name="group_title" value="<?= $v('group_title') ?>" list="cats" placeholder="mis. News, Sport, Movies">
      <datalist id="cats"><?php foreach ($cats as $c): ?><option value="<?= h($c) ?>"></option><?php endforeach; ?></datalist>
    </div>
    <div class="field">
      <label>Logo URL</label>
      <div class="row-inline" style="align-items:flex-start">
        <input name="logo_url" x-model="logo" value="<?= $v('logo_url') ?>" placeholder="https://…" style="flex:1">
        <img :src="logo" x-show="logo" x-cloak alt="" style="width:56px;height:56px;border-radius:8px;object-fit:cover;border:1px solid var(--border);background:var(--surface-2)">
      </div>
    </div>
    <div class="field"><label>Stream URL asli (.m3u8 / .mpd)</label><input name="stream_url" value="<?= $v('stream_url') ?>" required placeholder="https://…"></div>
    <div class="row-inline">
      <div class="field" style="flex:1">
        <label>Akses</label>
        <select name="is_free">
          <option value="0" <?= (($ch['is_free'] ?? 0) == 0) ? 'selected' : '' ?>>Donasi (khusus donatur)</option>
          <option value="1" <?= (($ch['is_free'] ?? 0) == 1) ? 'selected' : '' ?>>Gratis</option>
        </select>
      </div>
      <div class="field" style="flex:1">
        <label>Status</label>
        <select name="is_enabled">
          <option value="1" <?= (($ch['is_enabled'] ?? 1) == 1) ? 'selected' : '' ?>>Aktif</option>
          <option value="0" <?= (($ch['is_enabled'] ?? 1) == 0) ? 'selected' : '' ?>>Nonaktif</option>
        </select>
      </div>
    </div>
  </div>

  <details class="accordion" <?= ($ua || $ref || ($ch['drm_scheme'] ?? '')) ? 'open' : '' ?>>
    <summary>Opsi Lanjutan (header & DRM)</summary>
    <div class="acc-body">
      <div class="row-inline">
        <div class="field" style="flex:1">
          <label>Tipe stream</label>
          <select name="stream_type">
            <?php foreach (['other', 'hls', 'dash'] as $t): ?>
              <option value="<?= $t ?>" <?= (($ch['stream_type'] ?? 'other') === $t) ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" style="width:120px"><label>Urutan</label><input type="number" name="sort_index" value="<?= $v('sort_index', '0') ?>"></div>
      </div>
      <div class="field"><label>User-Agent</label><input name="user_agent" value="<?= h($ua) ?>" placeholder="opsional, sebagian stream butuh ini"><div class="hint">Disimpan di header request stream.</div></div>
      <div class="field"><label>Referer</label><input name="referer" value="<?= h($ref) ?>" placeholder="opsional"></div>
      <hr>
      <div class="field"><label>DRM Scheme</label><input name="drm_scheme" value="<?= $v('drm_scheme') ?>" placeholder="widevine / clearkey / playready"></div>
      <div class="field"><label>DRM License URL</label><input name="drm_license_url" value="<?= $v('drm_license_url') ?>"></div>
      <div class="field"><label>ClearKey statik (kid:key,kid:key)</label><input name="drm_clearkey" value="<?= $v('drm_clearkey') ?>"></div>
    </div>
  </details>

  <div class="row-inline">
    <button><i data-lucide="save"></i> Simpan</button>
    <a href="channels.php"><button type="button" class="btn-ghost">Batal</button></a>
  </div>
</form>
<?php
layout_footer();
