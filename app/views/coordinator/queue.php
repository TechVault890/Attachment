<h1>Verification queue</h1>
<p class="filters">
  <?php foreach (['pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected', 'all' => 'All'] as $k => $label): ?>
    <a class="<?= $filter === $k ? 'active' : '' ?>" href="<?= e(url('coordinator/queue', ['status' => $k])) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</p>
<div class="card table-wrap">
<?php if (!$rows): ?><p class="muted">No students in this list.</p><?php else: ?>
<table class="table">
  <thead><tr><th>Student</th><th>Reg. no</th><th>Course</th><th>Area</th><th>County</th><th>Docs</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><?= e($r['full_name']) ?></td><td><?= e($r['reg_no']) ?></td><td><?= e($r['course']) ?></td>
    <td><?= e($r['area_of_interest']) ?></td><td><?= e($r['county']) ?></td><td><?= (int)$r['docs'] ?>/4</td>
    <td><?= badge($r['verification_status']) ?></td>
    <td><a href="<?= e(url('coordinator/review', ['id' => $r['id']])) ?>">Review</a></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
</div>
