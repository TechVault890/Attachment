<h1>Users</h1>
<p><a class="btn" href="<?= e(url('user/create')) ?>">Create account</a></p>
<p class="filters">
  <?php foreach (['staff' => 'Staff', 'coordinator' => 'Coordinators', 'ministry' => 'Ministry', 'admin' => 'Admins', 'student' => 'Students'] as $k => $label): ?>
    <a class="<?= $filter === $k ? 'active' : '' ?>" href="<?= e(url('user/index', ['role' => $k])) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</p>
<div class="card table-wrap">
<?php if (!$rows): ?><p class="muted">No accounts in this list.</p><?php else: ?>
<table class="table">
  <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Organisation</th><th>Last login</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><?= e($r['full_name']) ?><br><span class="muted"><?= e($r['email']) ?></span></td>
    <td><?= e($r['username']) ?></td><td><?= e($r['role']) ?></td>
    <td><?= e((string)($r['institution'] ?? $r['ministry'] ?? '')) ?></td>
    <td><?= $r['last_login_at'] ? e($r['last_login_at']) : '<span class="muted">never</span>' ?></td>
    <td><?= badge($r['is_active'] ? 'active' : 'inactive') ?></td>
    <td><form method="post" action="<?= e(url('user/toggle')) ?>"><?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="back" value="<?= e($filter) ?>">
      <button class="btn btn-sm <?= $r['is_active'] ? 'btn-danger' : '' ?>" type="submit"><?= $r['is_active'] ? 'Deactivate' : 'Activate' ?></button></form></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
</div>
