<h1>Recommended vacancies</h1>
<?php if (!$verified): ?><div class="alert alert-warning">You can apply once your coordinator verifies you. These recommendations are for your information.</div><?php endif; ?>
<p class="muted">Open vacancies in your area of interest (<?= e($s['area_of_interest']) ?>), best match first. Score: area 50, county 30, period 20.</p>
<?php if (!$list): ?><div class="card"><p class="muted">No matching vacancies are open right now. Check back soon, or <a href="<?= e(url('vacancy/browse')) ?>">browse all vacancies</a>.</p></div><?php endif; ?>
<?php foreach ($list as $v): $m = $v['match']; ?>
<div class="card vac">
  <h2><a href="<?= e(url('vacancy/view', ['id' => $v['id']])) ?>"><?= e($v['title']) ?></a> <span class="badge badge-verified"><?= (int)$m['total'] ?>% match</span></h2>
  <p class="muted"><?= e($v['ministry']) ?> &middot; <?= e($v['office_location']) ?>, <?= e($v['county']) ?></p>
  <p><?= (int)open_slots($v) ?> slots open &middot; <?= (int)$v['duration_months'] ?> months &middot; closes <?= e($v['closes_on']) ?></p>
  <p class="chips">
    <span class="yes">Area match (+<?= (int)$m['area'] ?>)</span>
    <span class="<?= $m['county'] ? 'yes' : '' ?>">County <?= $m['county'] ? 'match' : 'differs' ?> (+<?= (int)$m['county'] ?>)</span>
    <span class="<?= $m['period'] ? 'yes' : '' ?>">Period (+<?= (int)$m['period'] ?>)</span>
  </p>
</div>
<?php endforeach; ?>
