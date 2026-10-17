<h1>Ministry dashboard</h1>
<div class="cards">
  <div class="card stat"><span class="num"><?= (int)$stats['vacancies'] ?></span> Open vacancies</div>
  <div class="card stat"><span class="num"><?= (int)$stats['open_slots'] ?></span> Open slots</div>
  <div class="card stat"><span class="num"><?= (int)$stats['pending'] ?></span> Applications awaiting a decision</div>
</div>
<p><a class="btn" href="<?= e(url('vacancy/manage')) ?>">Manage vacancies and applicants</a>
   <a class="btn btn-alt" href="<?= e(url('vacancy/create')) ?>">Post a vacancy</a></p>
