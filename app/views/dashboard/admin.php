<h1>Admin dashboard</h1>
<div class="cards">
  <?php foreach ($roles as $role => $n): ?>
    <div class="card stat"><span class="num"><?= $n ?></span> <?= e(ucfirst($role)) ?> accounts</div>
  <?php endforeach; ?>
  <div class="card stat"><span class="num"><?= $stats['vacancies'] ?></span> Vacancies</div>
  <div class="card stat"><span class="num"><?= $stats['placements'] ?></span> Placements</div>
</div>
