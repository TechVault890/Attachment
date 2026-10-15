<p><a href="<?= e(url('coordinator/queue')) ?>">&larr; Back to queue</a></p>
<h1><?= e($s['full_name']) ?></h1>
<div class="cards">
  <div class="card"><h2>Details</h2>
    <p><strong>Reg. no:</strong> <?= e($s['reg_no']) ?></p>
    <p><strong>Email:</strong> <?= e($s['email']) ?></p>
    <p><strong>Phone:</strong> <?= e((string)$s['phone']) ?: '-' ?></p>
    <p><strong>Course:</strong> <?= e($s['course']) ?></p>
    <p><strong>Area of interest:</strong> <?= e($s['area_of_interest']) ?></p>
    <p><strong>Preferred county:</strong> <?= e($s['county']) ?></p>
    <p><strong>Period:</strong> <?= (int)$s['period_months'] ?> months</p>
    <p><strong>Status:</strong> <?= badge($s['verification_status']) ?></p>
    <?php if ($s['verification_reason']): ?><p class="muted">Reason: <?= e($s['verification_reason']) ?></p><?php endif; ?></div>
  <div class="card"><h2>Documents (<?= count($docs) ?>/4)</h2>
    <ul class="plain">
    <?php foreach ($types as $key => $label): $d = $docs[$key] ?? null; ?>
      <li><?= e($label) ?>:
        <?php if ($d): ?><a href="<?= e(url('document/download', ['id' => $d['id']])) ?>"><?= e($d['original_name']) ?></a>
        <?php else: ?><span class="badge badge-pending">Missing</span><?php endif; ?></li>
    <?php endforeach; ?>
    </ul></div>
</div>
<div class="cards">
  <div class="card"><h2>Verify</h2>
    <?php if (count($docs) < 4): ?><p class="muted">Available once all four documents are uploaded.</p><?php endif; ?>
    <form method="post" action="<?= e(url('coordinator/decide')) ?>">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="decision" value="verify">
      <button class="btn" type="submit"<?= count($docs) < 4 ? ' disabled' : '' ?>>Verify student</button>
    </form></div>
  <div class="card"><h2>Reject</h2>
    <form method="post" action="<?= e(url('coordinator/decide')) ?>">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="decision" value="reject">
      <label for="reason">Reason (shown to the student)</label>
      <textarea id="reason" name="reason" rows="3" minlength="5" maxlength="255" required></textarea>
      <button class="btn btn-danger" type="submit">Reject student</button>
    </form></div>
</div>
