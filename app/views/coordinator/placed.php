<h1>Placed students</h1>
<p class="muted">Where your students are placed, with the supervisor contact, to plan assessment visits.</p>
<form method="get" action="<?= e(base_url() . '/index.php') ?>" class="card">
  <input type="hidden" name="r" value="coordinator/placed">
  <label for="county">Filter by county</label>
  <select id="county" name="county"><option value="">All counties</option>
    <?php foreach ($counties as $c): ?><option value="<?= (int)$c['id'] ?>"<?= $county === (int)$c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
  </select>
  <button class="btn btn-sm" type="submit">Filter</button>
  <a class="btn btn-sm btn-alt" href="<?= e(url('coordinator/placed')) ?>">Clear</a>
</form>
<div class="card table-wrap">
<?php if (!$rows): ?><p class="muted">No placed students to show.</p><?php else: ?>
<table class="table">
  <thead><tr><th>Student</th><th>Placement</th><th>Location</th><th>Dates</th><th>Supervisor</th><th>Assessments</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><strong><?= e($r['full_name']) ?></strong><br><span class="muted"><?= e($r['reg_no']) ?> &middot; <?= e($r['course']) ?><?= $r['phone'] ? '<br>' . e($r['phone']) : '' ?></span></td>
    <td><?= e($r['ministry']) ?><br><span class="muted"><?= e($r['department']) ?> &middot; <?= e($r['title']) ?></span></td>
    <td><?= e($r['office_location']) ?><br><span class="muted"><?= e($r['county']) ?></span></td>
    <td><?= $r['start_date'] ? e($r['start_date']) . '<br><span class="muted">to ' . e((string)$r['end_date']) . '</span>' : '<span class="muted">Not set</span>' ?><br><?= badge($r['pstatus']) ?></td>
    <td><?= $r['supervisor_name'] ? e($r['supervisor_name']) : '<span class="muted">Not provided</span>' ?>
        <?= $r['supervisor_phone'] ? '<br>' . e($r['supervisor_phone']) : '' ?><?= $r['supervisor_email'] ? '<br>' . e($r['supervisor_email']) : '' ?></td>
    <td><?= (int)$r['assess_count'] ?><?= $r['last_score'] !== null ? '<br><span class="muted">last ' . (int)$r['last_score'] . '/100</span>' : '' ?></td>
    <td><a class="btn btn-sm" href="<?= e(url('coordinator/assess', ['id' => $r['placement_id']])) ?>">Assess</a></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
</div>
