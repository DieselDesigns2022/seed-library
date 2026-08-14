<?php
declare(strict_types=1);

function render(string $title, callable $content, array $options = []): void
{
    $print = $options['print'] ?? false;
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · <?= e(config('app.name', 'Seed Library')) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= e(url('assets/app.css')) ?>" rel="stylesheet">
</head>
<body class="bg-light <?= $print ? 'print-view' : '' ?>">
<?php if (!$print): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-success sticky-top shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="<?= e(url('dashboard')) ?>">🌱 Seed Library</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="navMain">
      <?php if (current_user()): ?>
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="<?= e(url('seeds')) ?>">Inventory</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= e(url('calendar')) ?>">Calendar</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= e(url('companions')) ?>">Companions</a></li>
        <li class="nav-item dropdown"><a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Tools</a><ul class="dropdown-menu"><li><a class="dropdown-item" href="<?= e(url('import')) ?>">Import</a></li><li><a class="dropdown-item" href="<?= e(url('export')) ?>">Export</a></li><li><a class="dropdown-item" href="<?= e(url('print')) ?>">Print Reports</a></li><?php if(!empty(current_user()['is_owner'])):?><li><hr class="dropdown-divider"></li><li><a class="dropdown-item" href="<?= e(url('backup')) ?>">Database Backup &amp; Restore</a></li><?php endif?></ul></li>
        <?php if(!empty(current_user()['is_owner'])):?><li class="nav-item dropdown"><a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Manage</a><ul class="dropdown-menu"><li><a class="dropdown-item" href="<?= e(url('settings')) ?>">Settings</a></li><li><a class="dropdown-item" href="<?= e(url('manage/categories')) ?>">Categories</a></li><li><a class="dropdown-item" href="<?= e(url('manage/families')) ?>">Plant Families</a></li><li><a class="dropdown-item" href="<?= e(url('manage/uses')) ?>">Uses</a></li><li><a class="dropdown-item" href="<?= e(url('manage/statuses')) ?>">Statuses</a></li><li><a class="dropdown-item" href="<?= e(url('manage/storage')) ?>">Storage Locations</a></li></ul></li><?php endif?>
      </ul>
      <form method="post" action="<?= e(url('logout')) ?>" class="d-flex"><?= csrf_field() ?><button class="btn btn-outline-light btn-sm">Logout</button></form>
      <?php endif; ?>
    </div>
  </div>
</nav>
<?php endif; ?>
<main class="container-fluid py-4">
  <?php foreach (flashes() as $flash): ?>
  <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert"><?= e($flash['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  <?php endforeach; ?>
  <?php $content(); ?>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(url('assets/app.js')) ?>"></script>
</body>
</html>
<?php
}
