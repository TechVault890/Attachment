<p><a href="<?= e(url('coordinator/placed')) ?>">&larr; Placed students</a></p>
<h1>Assessment: <?= e($pl['full_name']) ?></h1>
<div class="card">
  <p><strong><?= e($pl['reg_no']) ?></strong> &middot; <?= e($pl['course']) ?></p>
  <p><?= e($pl['ministry']) ?> &middot; <?= e($pl['department']) ?> &middot; <?= e($pl['office_location']) ?>, <?= e($pl['county']) ?></p>
  <p class="muted">Supervisor: <?= $pl['supervisor_name'] ? e($pl['supervisor_name']) : 'not provided' ?><?= $pl['supervisor_phone'] ? ', ' . e($pl['supervisor_phone']) : '' ?><?= $pl['supervisor_email'] ? ', ' . e($pl['supervisor_email']) : '' ?></p>
</div>
<div class="card">
  <h2>Record an assessment</h2>
  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <form method="post" action="<?= e(url('coordinator/assess', ['id' => $pl['id']])) ?>" class="grid">
    <?= csrf_field() ?>
    <div><label for="assessed_on">Assessment date</label><input id="assessed_on" name="assessed_on" type="date" required value="<?= old('assessed_on') ?>"></div>
    <div><label for="score">Score (0 to 100)</label><input id="score" name="score" type="number" min="0" max="100" required value="<?= old('score') ?>"></div>
    <div class="full"><label for="comments">Comments (optional)</label><textarea id="comments" name="comments" rows="4" maxlength="2000"><?= old('comments') ?></textarea></div>
    <div class="full"><button class="btn" type="submit">Save assessment</button></div>
  </form>
</div>
<div class="card table-wrap">
  <h2>History</h2>
  <?php if (!$history): ?><p class="muted">No assessments recorded yet.</p><?php else: ?>
  <table class="table"><thead><tr><th>Date</th><th>Score</th><th>Comments</th><th>By</th></tr></thead><tbody>
  <?php foreach ($history as $h): ?>
    <tr><td><?= e($h['assessed_on']) ?></td><td><?= (int)$h['score'] ?>/100</td><td><?= e((string)$h['comments']) ?></td><td><?= e((string)$h['coordinator']) ?></td></tr>
  <?php endforeach; ?></tbody></table>
  <?php endif; ?>
</div>
