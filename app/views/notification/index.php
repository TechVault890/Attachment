<h1>Notifications</h1>
<form method="post" action="<?= e(url('notification/read')) ?>">
  <?= csrf_field() ?><input type="hidden" name="all" value="1">
  <button class="btn btn-sm btn-alt" type="submit">Mark all as read</button>
</form>
<?php if (!$rows): ?><div class="card"><p class="muted">No notifications yet.</p></div><?php endif; ?>
<?php foreach ($rows as $r): ?>
<div class="card notif<?= $r['is_read'] ? '' : ' unread' ?>">
  <strong><?= e($r['title']) ?></strong> <span class="muted"><?= e($r['created_at']) ?></span>
  <p><?= e($r['message']) ?></p>
  <?php if ($r['link']): ?><a href="<?= e($r['link']) ?>">Open</a><?php endif; ?>
  <?php if (!$r['is_read']): ?>
    <form method="post" action="<?= e(url('notification/read')) ?>" class="inline"><?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-alt" type="submit">Mark read</button></form>
  <?php endif; ?>
</div>
<?php endforeach; ?>
