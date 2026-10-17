<h1>Coordinator dashboard</h1>
<div class="cards">
  <div class="card stat"><span class="num"><?= $counts['pending'] ?></span> Pending verification
    <a href="<?= e(url('coordinator/queue', ['status' => 'pending'])) ?>">Open queue</a></div>
  <div class="card stat"><span class="num"><?= $counts['verified'] ?></span> Verified
    <a href="<?= e(url('coordinator/queue', ['status' => 'verified'])) ?>">View</a></div>
  <div class="card stat"><span class="num"><?= $counts['rejected'] ?></span> Rejected
    <a href="<?= e(url('coordinator/queue', ['status' => 'rejected'])) ?>">View</a></div>
  <div class="card stat"><span class="num"><?= (int)$placed ?></span> Placed students
    <a href="<?= e(url('coordinator/placed')) ?>">View and assess</a></div>
</div>
