<p><a href="<?= e(url('vacancy/browse')) ?>">&larr; All vacancies</a></p>
<div class="card">
  <h1><?= e($v['title']) ?></h1>
  <p><?= badge($v['status']) ?> <span class="muted"><?= e($v['ministry']) ?> &middot; <?= e($v['department']) ?></span></p>
  <p><strong>Location:</strong> <?= e($v['office_location']) ?>, <?= e($v['county']) ?></p>
  <p><strong>Course / area:</strong> <?= e($v['course_area']) ?></p>
  <p><strong>Slots:</strong> <?= (int)$slots_left ?> of <?= (int)$v['slots_total'] ?> open</p>
  <p><strong>Duration:</strong> <?= (int)$v['duration_months'] ?> months</p>
  <p><strong>Vacancy open:</strong> <?= e($v['opens_on']) ?> to <?= e($v['closes_on']) ?></p>
  <?php if ($v['description']): ?><h2>Description</h2><p><?= nl2br(e($v['description'])) ?></p><?php endif; ?>
</div>
<?php if ($win): ?>
  <div class="alert alert-success window-open">Applications are open: <?= e($win['name']) ?> (until <?= e($win['closes_on']) ?>).</div>
<?php else: ?>
  <div class="alert alert-warning window-closed">No application window is open right now.</div>
<?php endif; ?>
