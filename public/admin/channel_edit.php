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

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $data = [
        'name' => trim((string) post('name')),
        'group_title' => trim((string) post('group_title')) ?: null,
        'logo_url' => trim((string) post('logo_url')) ?: null,
        'stream_url' => trim((string) post('stream_url')),
        'stream_type' => in_array(post('stream_type'), ['hls', 'dash', 'other'], true) ? post('stream_type') : 'other',
        'is_free' => post('is_free') ? 1 : 0,
        'is_enabled' => post('is_enabled') ? 1 : 0,
        'sort_index' => (int) post('sort_index'),
        'drm_scheme' => trim((string) post('drm_scheme')) ?: null,
        'drm_license_url' => trim((string) post('drm_license_url')) ?: null,
        'drm_clearkey' => trim((string) post('drm_clearkey')) ?: null,
    ];
    if ($data['name'] === '' || $data['stream_url'] === '') {
        $err = 'Nama dan Stream URL wajib diisi.';
    } else {
        if ($id) {
            $data['id'] = $id;
            $pdo->prepare(
                'UPDATE channels SET name=:name, group_title=:group_title, logo_url=:logo_url, stream_url=:stream_url,
                 stream_type=:stream_type, is_free=:is_free, is_enabled=:is_enabled, sort_index=:sort_index,
                 drm_scheme=:drm_scheme, drm_license_url=:drm_license_url, drm_clearkey=:drm_clearkey WHERE id=:id',
            )->execute($data);
        } else {
            $pdo->prepare(
                'INSERT INTO channels (name, group_title, logo_url, stream_url, stream_type, is_free, is_enabled,
                 sort_index, drm_scheme, drm_license_url, drm_clearkey)
                 VALUES (:name,:group_title,:logo_url,:stream_url,:stream_type,:is_free,:is_enabled,:sort_index,
                 :drm_scheme,:drm_license_url,:drm_clearkey)',
            )->execute($data);
        }
        redirect('channels.php');
    }
}

$v = fn(string $k, string $d = '') => h((string) ($ch[$k] ?? $d));
layout_header($id ? 'Edit channel' : 'Tambah channel');
$csrf = csrfToken();
?>
<div class="card" style="max-width:680px">
  <h2><?= $id ? 'Edit channel' : 'Tambah channel' ?></h2>
  <?php if ($err) echo '<p style="color:#ff8a80">' . h($err) . '</p>'; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <p>Nama<br><input name="name" value="<?= $v('name') ?>" required style="width:100%"></p>
    <p>Kategori<br><input name="group_title" value="<?= $v('group_title') ?>" style="width:100%"></p>
    <p>Logo URL<br><input name="logo_url" value="<?= $v('logo_url') ?>" style="width:100%"></p>
    <p>Stream URL asli (.m3u8 / .mpd)<br><input name="stream_url" value="<?= $v('stream_url') ?>" required style="width:100%"></p>
    <p>Tipe
      <select name="stream_type">
        <?php foreach (['other', 'hls', 'dash'] as $t): ?>
          <option value="<?= $t ?>" <?= (($ch['stream_type'] ?? 'other') === $t) ? 'selected' : '' ?>><?= $t ?></option>
        <?php endforeach; ?>
      </select>
      &nbsp; Urutan <input type="number" name="sort_index" value="<?= $v('sort_index', '0') ?>" style="width:80px">
    </p>
    <p>
      <label><input type="checkbox" name="is_free" <?= ($ch['is_free'] ?? 0) ? 'checked' : '' ?>> Free</label>
      &nbsp;&nbsp;
      <label><input type="checkbox" name="is_enabled" <?= ($ch['is_enabled'] ?? 1) ? 'checked' : '' ?>> Aktif</label>
    </p>
    <hr>
    <p class="muted">DRM (opsional)</p>
    <p>Scheme <input name="drm_scheme" value="<?= $v('drm_scheme') ?>" placeholder="widevine / clearkey / playready"></p>
    <p>License URL<br><input name="drm_license_url" value="<?= $v('drm_license_url') ?>" style="width:100%"></p>
    <p>ClearKey statik (kid:key,kid:key)<br><input name="drm_clearkey" value="<?= $v('drm_clearkey') ?>" style="width:100%"></p>
    <button>Simpan</button> &nbsp; <a href="channels.php">Batal</a>
  </form>
</div>
<?php
layout_footer();
