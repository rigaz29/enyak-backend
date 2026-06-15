<?php
declare(strict_types=1);

function layout_header(string $title): void
{
    $admin = currentAdmin();
    ?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> — Enyak Admin</title>
<style>
  :root { color-scheme: dark; }
  body { font-family: system-ui, Arial, sans-serif; margin: 0; background: #0f1115; color: #e6e6e6; }
  header { background: #1a1d24; padding: 12px 20px; display: flex; gap: 16px; align-items: center; }
  a { color: #7db3ff; text-decoration: none; }
  nav a { margin-right: 14px; }
  main { padding: 20px; max-width: 1100px; margin: 0 auto; }
  h2 { margin-top: 0; }
  table { width: 100%; border-collapse: collapse; }
  th, td { padding: 8px; border-bottom: 1px solid #2a2e37; text-align: left; font-size: 14px; vertical-align: top; }
  input, select, textarea, button { font: inherit; padding: 8px; border-radius: 6px; border: 1px solid #2a2e37; background: #11141a; color: #e6e6e6; }
  button { background: #2d6cdf; border: none; cursor: pointer; }
  button.btn-danger { background: #c0392b; }
  .muted { color: #9aa0aa; font-size: 12px; }
  .pill { padding: 2px 8px; border-radius: 999px; font-size: 12px; color: #fff; }
  form.inline { display: inline; }
  .card { background: #161a21; padding: 16px; border-radius: 10px; margin-bottom: 16px; }
</style>
</head>
<body>
<header>
  <strong>Enyak Admin</strong>
  <?php if ($admin): ?>
    <nav>
      <a href="devices.php">Devices</a>
      <a href="channels.php">Channels</a>
      <a href="import.php">Import M3U</a>
    </nav>
    <span style="margin-left:auto" class="muted"><?= h($admin['email']) ?></span>
    <a href="logout.php">Keluar</a>
  <?php endif; ?>
</header>
<main>
    <?php
}

function layout_footer(): void
{
    echo '</main></body></html>';
}
