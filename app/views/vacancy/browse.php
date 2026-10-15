<h1>Vacancies</h1>
<?php if ($win): ?>
  <div class="alert alert-success window-open">Applications are open: <?= e($win['name']) ?> (until <?= e($win['closes_on']) ?>).</div>
<?php else: ?>
  <div class="alert alert-warning window-closed">No application window is open right now. You can still browse vacancies.</div>
<?php endif; ?>
<form method="get" action="<?= e(base_url() . '/index.php') ?>" class="card">
  <input type="hidden" name="r" value="vacancy/browse">
  <div class="grid">
    <div><label for="county">County</label>
      <select id="county" name="county"><option value="">All counties</option>
        <?php foreach ($counties as $c): ?><option value="<?= (int)$c['id'] ?>"<?= $county === (int)$c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div><label for="area">Course / area</label>
      <select id="area" name="area"><option value="">All areas</option>
        <?php foreach ($areas as $a): ?><option<?= $area === $a ? ' selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?>
      </select></div>
    <div><label for="ministry">Ministry</label>
      <select id="ministry" name="ministry"><option value="">All ministries</option>
        <?php foreach ($ministries as $m): ?><option value="<?= (int)$m['id'] ?>"<?= $min === (int)$m['id'] ? ' selected' : '' ?>><?= e($m['name']) ?></option><?php endforeach; ?>
      </select></div>
  </div>
  <button class="btn btn-sm" type="submit">Filter</button>
  <a class="btn btn-sm btn-alt" href="<?= e(url('vacancy/browse')) ?>">Clear</a>
</form>
<p class="muted"><?= (int)$total ?> vacancies found</p>
<?php foreach ($rows as $r): ?>
<div class="card vac">
  <h2><a href="<?= e(url('vacancy/view', ['id' => $r['id']])) ?>"><?= e($r['title']) ?></a></h2>
  <p class="muted"><?= e($r['ministry']) ?> &middot; <?= e($r['department']) ?> &middot; <?= e($r['office_location']) ?>, <?= e($r['county']) ?></p>
  <p><strong><?= e($r['course_area']) ?></strong> &middot; <?= (int)open_slots($r) ?> slots open &middot; <?= (int)$r['duration_months'] ?> months &middot; closes <?= e($r['closes_on']) ?></p>
</div>
<?php endforeach; ?>
<?php if ($pages > 1): ?>
<p class="filters">
  <?php for ($i = 1; $i <= $pages; $i++): ?>
    <a class="<?= $i === $page ? 'active' : '' ?>" href="<?= e(url('vacancy/browse', $query + ['page' => $i])) ?>"><?= $i ?></a>
  <?php endfor; ?>
</p>
<?php endif; ?>
