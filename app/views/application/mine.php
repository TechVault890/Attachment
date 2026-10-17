<h1>My applications</h1>
<p><a class="btn btn-sm" href="<?= e(url('application/matches')) ?>">Recommended vacancies</a>
   <a class="btn btn-sm btn-alt" href="<?= e(url('vacancy/browse')) ?>">Browse all vacancies</a></p>
<div class="card table-wrap">
<?php if (!$rows): ?><p class="muted">You have not applied yet. Verified students can apply from any open vacancy page.</p><?php else: ?>
<table class="table">
  <thead><tr><th>Vacancy</th><th>Location</th><th>Applied</th><th>Match</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><?php if ($r['vstatus'] === 'open'): ?><a href="<?= e(url('vacancy/view', ['id' => $r['vacancy_id']])) ?>"><?= e($r['title']) ?></a><?php else: ?><?= e($r['title']) ?><?php endif; ?>
        <br><span class="muted"><?= e($r['ministry']) ?></span></td>
    <td><?= e($r['office_location']) ?>, <?= e($r['county']) ?></td>
    <td><?= e(substr($r['applied_at'], 0, 10)) ?></td>
    <td><?= (int)$r['match'] ?>%</td>
    <td><?= badge($r['status']) ?>
      <?php if ($r['decision_note'] && $r['status'] !== 'placed'): ?><br><span class="muted"><?= e($r['decision_note']) ?></span><?php endif; ?>
      <?php if ($r['status'] === 'placed'): ?>
        <br><span class="muted">
        <?php if ($r['start_date']): ?>Start <?= e($r['start_date']) ?> to <?= e((string)$r['end_date']) ?><br><?php endif; ?>
        <?php if ($r['supervisor_name']): ?>Supervisor: <?= e($r['supervisor_name']) ?><?= $r['supervisor_phone'] ? ', ' . e($r['supervisor_phone']) : '' ?><?php endif; ?></span>
      <?php endif; ?></td>
    <td><?php if (in_array($r['status'], ['applied', 'shortlisted'], true)): ?>
      <form method="post" action="<?= e(url('application/withdraw')) ?>"><?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-danger" type="submit">Withdraw</button></form>
    <?php endif; ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
</div>
