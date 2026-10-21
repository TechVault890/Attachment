<h1>Admin dashboard</h1>
<div class="cards">
  <?php foreach ($roles as $role => $n): ?>
    <div class="card stat"><span class="num"><?= $n ?></span> <?= e(ucfirst($role)) ?> accounts</div>
  <?php endforeach; ?>
  <div class="card stat"><span class="num"><?= $stats['vacancies'] ?></span> Vacancies</div>
  <div class="card stat"><span class="num"><?= $stats['placements'] ?></span> Placements</div>
</div>
<p><a class="btn" href="<?= e(url('report/index')) ?>">Reports</a>
   <a class="btn btn-alt" href="<?= e(url('window/index')) ?>">Application windows</a>
   <a class="btn btn-alt" href="<?= e(url('user/index')) ?>">Users</a>
   <a class="btn btn-alt" href="<?= e(url('org/index')) ?>">Organisations</a>
   <a class="btn btn-alt" href="<?= e(url('audit/index')) ?>">Audit log</a>
   <a class="btn btn-alt" href="<?= e(url('vacancy/browse')) ?>">Browse vacancies</a></p>
