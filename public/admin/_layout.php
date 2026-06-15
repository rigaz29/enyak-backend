<?php
declare(strict_types=1);

/** Active-nav helper: marks the current page. */
function navActive(string $file): string
{
    return basename($_SERVER['SCRIPT_NAME'] ?? '') === $file ? 'active' : '';
}

function layout_head(string $title): void
{
    ?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> — Enyak Admin</title>
<script>document.documentElement.setAttribute('data-theme', localStorage.getItem('enyak_theme') || 'dark');</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/admin.css">
<script defer src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script defer src="assets/admin.js"></script>
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
    <?php
}

function layout_toasts(): void
{
    $f = takeFlash();
    if ($f === null) {
        return;
    }
    echo '<div class="toasts"><div class="toast ' . h($f['type']) . '">' . h($f['msg']) . '</div></div>';
}

function layout_header(string $title): void
{
    layout_head($title);
    $admin = currentAdmin();

    // Auth pages (not logged in): minimal centered layout, no sidebar.
    if ($admin === null) {
        echo '<body><div class="auth-wrap">';
        layout_toasts();
        return;
    }
    ?>
<body x-data="adminShell()">
  <div class="scrim" :class="sidebarOpen && 'show'" @click="sidebarOpen=false"></div>
  <div class="app">
    <aside class="sidebar" :class="sidebarOpen && 'open'">
      <div class="brand"><span class="dot"><i data-lucide="tv"></i></span> Enyak Admin</div>
      <nav class="nav">
        <a href="dashboard.php" class="<?= navActive('dashboard.php') ?>"><i data-lucide="layout-dashboard"></i> Dashboard</a>
        <a href="devices.php" class="<?= navActive('devices.php') ?>"><i data-lucide="users"></i> Devices</a>
        <a href="channels.php" class="<?= navActive('channels.php') ?>"><i data-lucide="tv"></i> Channels</a>
        <a href="import.php" class="<?= navActive('import.php') ?>"><i data-lucide="upload"></i> Import M3U</a>
        <a href="settings.php" class="<?= navActive('settings.php') ?>"><i data-lucide="settings"></i> Settings</a>
        <?php if (isSuperadmin()): ?><a href="admins.php" class="<?= navActive('admins.php') ?>"><i data-lucide="shield"></i> Admins</a><?php endif; ?>
      </nav>
      <div class="sidebar-foot">
        <a href="profile.php" style="color:var(--muted);display:flex;gap:11px;padding:10px 12px"><i data-lucide="user"></i> Profil</a>
        <a href="logout.php" style="color:var(--muted);display:flex;gap:11px;padding:10px 12px"><i data-lucide="log-out"></i> Keluar</a>
      </div>
    </aside>

    <div class="content">
      <header class="topbar">
        <button class="icon-btn hamburger" @click="sidebarOpen=true" title="Menu"><i data-lucide="menu"></i></button>
        <div class="crumb">Admin / <b><?= h($title) ?></b></div>
        <div class="spacer"></div>
        <button class="icon-btn" @click="toggleDark()" title="Tema">
          <i data-lucide="sun" x-show="dark" x-cloak></i><i data-lucide="moon" x-show="!dark" x-cloak></i>
        </button>
        <span class="muted" style="margin:0 4px"><?= h($admin['email']) ?></span>
      </header>
      <main>
        <?php layout_toasts(); ?>
    <?php
}

function layout_footer(): void
{
    $admin = currentAdmin();
    if ($admin === null) {
        echo '</div></body></html>'; // close auth-wrap
        return;
    }
    echo '      </main>
    </div>
  </div>
</body></html>';
}
