<?php
$au = current_user();
$owner = $au && ($au['role'] === 'admin' || ($au['role'] === 'ministry' && (int)$au['ministry_id'] === (int)$v['ministry_id']));
$ap = apply_state($v, $au);
?>
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
<?php if ($owner): ?>
  <p><a class="btn" href="<?= e(url('application/applicants', ['vacancy' => $v['id']])) ?>">View applicants</a></p>
<?php endif; ?>
<?php if ($ap['state'] === 'can_apply'): $m = $ap['match']; ?>
  <div class="card">
    <h2>Apply for this vacancy</h2>
    <p>Your match: <strong><?= (int)$m['total'] ?>%</strong></p>
    <?php if (auto_placement_ok($m)): ?>
    <p class="muted">You match this vacancy, so applying places you automatically (first come, first served). The Ministry then approves or declines your placement.</p>
    <?php else: ?>
    <p class="muted">Your area of interest differs from this vacancy, so the Ministry will review your application.</p>
    <?php endif; ?>
    <p class="chips">
      <span class="<?= $m['area'] ? 'yes' : '' ?>">Area <?= $m['area'] ? 'match' : 'differs' ?> (+<?= (int)$m['area'] ?>)</span>
      <span class="<?= $m['county'] ? 'yes' : '' ?>">County <?= $m['county'] ? 'match' : 'differs' ?> (+<?= (int)$m['county'] ?>)</span>
      <span class="<?= $m['period'] ? 'yes' : '' ?>">Period (+<?= (int)$m['period'] ?>)</span>
    </p>
    <form method="post" action="<?= e(url('application/apply')) ?>">
      <?= csrf_field() ?><input type="hidden" name="vacancy_id" value="<?= (int)$v['id'] ?>">
      <button class="btn" type="submit">Apply for this vacancy</button>
    </form>
  </div>
<?php elseif ($ap['state'] === 'login'): ?>
  <p class="muted"><a href="<?= e(url('auth/login')) ?>">Log in</a> as a verified student to apply.</p>
<?php elseif (isset($ap['msg'])): ?>
  <div class="alert alert-warning"><?= e($ap['msg']) ?></div>
<?php endif; ?>
<?php if ($ap['state'] !== 'no_window'): ?>
  <?php if ($win): ?>
    <div class="alert alert-success window-open">Applications are open: <?= e($win['name']) ?> (until <?= e($win['closes_on']) ?>).</div>
  <?php else: ?>
    <div class="alert alert-warning window-closed">No application window is open right now.</div>
  <?php endif; ?>
<?php endif; ?>
