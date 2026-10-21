<h1>Audit log</h1>
<?php foreach ($range['errors'] as $er): ?><div class="alert alert-danger"><?= e($er) ?></div><?php endforeach; ?>
<form method="get" action="<?= e(base_url() . '/index.php') ?>" class="card">
  <input type="hidden" name="r" value="audit/index">
  <div class="grid">
    <div><label for="action">Action</label>
      <select id="action" name="action"><option value="">All actions</option>
        <?php foreach ($actions as $a): ?><option<?= $action === $a ? ' selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?>
      </select></div>
    <div><label for="from">From</label><input id="from" name="from" type="date" value="<?= e($range['from']) ?>"></div>
    <div><label for="to">To</label><input id="to" name="to" type="date" value="<?= e($range['to']) ?>"></div>
  </div>
  <button class="btn btn-sm" type="submit">Filter</button>
  <a class="btn btn-sm btn-alt" href="<?= e(url('audit/index')) ?>">Clear</a>
</form>
<p class="muted"><?= (int)$total ?> entries &middot; page <?= (int)$page ?> of <?= (int)$pages ?></p>
<div class="card table-wrap">
<?php if (!$rows): ?><p class="muted">No entries match.</p><?php else: ?>
<table class="table">
  <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Item</th><th>Details</th><th>IP</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><?= e($r['created_at']) ?></td>
    <td><?= $r['username'] ? e($r['full_name']) . '<br><span class="muted">' . e($r['username']) . ' (' . e($r['role']) . ')</span>' : '<span class="muted">system or removed user</span>' ?></td>
    <td><?= e($r['action']) ?></td>
    <td><?= e($r['entity_type']) ?><?= $r['entity_id'] !== null ? ' #' . (int)$r['entity_id'] : '' ?></td>
    <td><?= e((string)$r['details']) ?></td>
    <td><?= e((string)$r['ip_address']) ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
</div>
<?php if ($pages > 1): ?>
<p class="filters">
  <?php if ($page > 1): ?><a href="<?= e(url('audit/index', $query + ['page' => $page - 1])) ?>">&larr; Newer</a><?php endif; ?>
  <?php if ($page < $pages): ?><a href="<?= e(url('audit/index', $query + ['page' => $page + 1])) ?>">Older &rarr;</a><?php endif; ?>
</p>
<?php endif; ?>
