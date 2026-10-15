<h1>Application windows</h1>
<p class="muted">Students can apply only while an active window covers today's date.</p>
<div class="card">
  <h2>New window</h2>
  <form method="post" action="<?= e(url('window/save')) ?>" class="grid">
    <?= csrf_field() ?>
    <div><label for="name">Name</label><input id="name" name="name" required maxlength="100" placeholder="e.g. May-August 2027 intake"></div>
    <div><label for="opens_on">Opens on</label><input id="opens_on" name="opens_on" type="date" required></div>
    <div><label for="closes_on">Closes on</label><input id="closes_on" name="closes_on" type="date" required></div>
    <div class="full"><button class="btn" type="submit">Create window</button></div>
  </form>
</div>
<div class="card table-wrap">
<?php if (!$rows): ?><p class="muted">No windows yet.</p><?php else: ?>
<table class="table">
  <thead><tr><th>Name</th><th>Opens</th><th>Closes</th><th>State</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><?= e($r['name']) ?></td><td><?= e($r['opens_on']) ?></td><td><?= e($r['closes_on']) ?></td>
    <td><?= badge($r['state']) ?></td>
    <td><form method="post" action="<?= e(url('window/toggle')) ?>" class="inline">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <button class="btn btn-sm <?= $r['is_active'] ? 'btn-danger' : '' ?>" type="submit"><?= $r['is_active'] ? 'Deactivate' : 'Activate' ?></button></form></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
</div>
