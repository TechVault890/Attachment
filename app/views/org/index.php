<h1>Organisations</h1>
<div class="card">
  <h2>Institutions</h2>
  <p class="muted">Every institution needs at least one active coordinator, or its students cannot be verified.</p>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Institution</th><th>Code</th><th>Students</th><th>Coordinators</th></tr></thead><tbody>
    <?php foreach ($institutions as $i): ?>
    <tr><td><?= e($i['name']) ?></td><td><?= e((string)$i['code']) ?></td><td><?= (int)$i['students'] ?></td>
      <td><?php if ((int)$i['coordinators'] > 0): ?><?= (int)$i['coordinators'] ?><?php else: ?><span class="badge badge-pending">No coordinator</span> <a href="<?= e(url('user/create')) ?>">Create one</a><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <form method="post" action="<?= e(url('org/add')) ?>" class="grid">
    <?= csrf_field() ?><input type="hidden" name="type" value="institution">
    <div><label for="iname">New institution name</label><input id="iname" name="name" required maxlength="150"></div>
    <div><label for="icode">Short code (optional)</label><input id="icode" name="code" maxlength="20"></div>
    <div class="full"><button class="btn btn-sm" type="submit">Add institution</button></div>
  </form>
</div>
<div class="card">
  <h2>Ministries</h2>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Ministry</th><th>Abbreviation</th><th>Vacancies</th><th>Officers</th></tr></thead><tbody>
    <?php foreach ($ministries as $m): ?>
    <tr><td><?= e($m['name']) ?></td><td><?= e((string)$m['abbreviation']) ?></td><td><?= (int)$m['vacancies'] ?></td>
      <td><?php if ((int)$m['officers'] > 0): ?><?= (int)$m['officers'] ?><?php else: ?><span class="badge badge-pending">No officer</span> <a href="<?= e(url('user/create')) ?>">Create one</a><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <form method="post" action="<?= e(url('org/add')) ?>" class="grid">
    <?= csrf_field() ?><input type="hidden" name="type" value="ministry">
    <div><label for="mname">New Ministry name</label><input id="mname" name="name" required maxlength="150"></div>
    <div><label for="mcode">Abbreviation (optional)</label><input id="mcode" name="code" maxlength="20"></div>
    <div class="full"><button class="btn btn-sm" type="submit">Add Ministry</button></div>
  </form>
</div>
