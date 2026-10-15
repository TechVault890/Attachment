<?php $nav = current_user(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Home') ?> | <?= e(config()['app']['name']) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body>
<header class="topbar">
  <a class="brand" href="<?= e(url()) ?>"><?= e(config()['app']['name']) ?></a>
  <nav>
    <a href="<?= e(url('vacancy/browse')) ?>">Vacancies</a>
    <?php if ($nav): ?>
      <a href="<?= e(url('dashboard/index')) ?>">Dashboard</a>
      <?php if ($nav['role'] === 'student'): ?>
        <a href="<?= e(url('student/profile')) ?>">Profile</a>
        <a href="<?= e(url('student/documents')) ?>">Documents</a>
      <?php elseif ($nav['role'] === 'coordinator'): ?>
        <a href="<?= e(url('coordinator/queue')) ?>">Verification</a>
      <?php elseif ($nav['role'] === 'ministry'): ?>
        <a href="<?= e(url('vacancy/manage')) ?>">My vacancies</a>
      <?php elseif ($nav['role'] === 'admin'): ?>
        <a href="<?= e(url('window/index')) ?>">Windows</a>
      <?php endif; ?>
      <span class="who"><?= e($nav['full_name']) ?> (<?= e($nav['role']) ?>)</span>
      <form method="post" action="<?= e(url('auth/logout')) ?>" class="inline">
        <?= csrf_field() ?><button type="submit" class="link">Log out</button>
      </form>
    <?php else: ?>
      <a href="<?= e(url('auth/login')) ?>">Log in</a>
      <a href="<?= e(url('auth/register')) ?>">Register</a>
    <?php endif; ?>
  </nav>
</header>
<main class="container">
<?php foreach (flashes() as $f): ?>
  <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
<?php endforeach; ?>
