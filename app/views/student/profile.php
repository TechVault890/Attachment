<div class="card">
  <h1>My profile</h1>
  <p class="muted"><?= e($s['full_name']) ?> &middot; <?= e($s['institution']) ?> &middot; Reg. no <?= e($s['reg_no']) ?></p>
  <p>Status: <?= badge($s['verification_status']) ?>
    <?php if ($s['verification_status'] === 'rejected' && $s['verification_reason']): ?>
      <span class="muted">Reason: <?= e($s['verification_reason']) ?></span>
    <?php endif; ?></p>
  <?php if ($locked): ?><div class="alert alert-warning">Your profile is verified and locked. Contact your coordinator if something must change.</div><?php endif; ?>
  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <form method="post" action="<?= e(url('student/profile')) ?>">
    <?= csrf_field() ?>
    <fieldset class="grid"<?= $locked ? ' disabled' : '' ?>>
      <div><label for="phone">Phone</label>
        <input id="phone" name="phone" type="tel" maxlength="20" value="<?= old('phone') ?>"></div>
      <div><label for="course">Attachment course</label>
        <input id="course" name="course" required maxlength="150" value="<?= old('course') ?>"></div>
      <div><label for="area_of_interest">Area of interest</label>
        <select id="area_of_interest" name="area_of_interest" required>
          <?php foreach ($areas as $a): ?><option<?= sel('area_of_interest', $a) ?>><?= e($a) ?></option><?php endforeach; ?>
        </select></div>
      <div><label for="preferred_county_id">Preferred county</label>
        <select id="preferred_county_id" name="preferred_county_id" required>
          <?php foreach ($counties as $c): ?><option value="<?= (int)$c['id'] ?>"<?= sel('preferred_county_id', $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select></div>
      <div><label for="period_months">Period available</label>
        <select id="period_months" name="period_months" required>
          <?php foreach ([3, 6, 12] as $p): ?><option value="<?= $p ?>"<?= sel('period_months', $p) ?>><?= $p ?> months</option><?php endforeach; ?>
        </select></div>
      <div><label for="available_from">Available from (optional)</label>
        <input id="available_from" name="available_from" type="date" value="<?= old('available_from') ?>"></div>
      <div><label for="available_to">Available until (optional)</label>
        <input id="available_to" name="available_to" type="date" value="<?= old('available_to') ?>"></div>
    </fieldset>
    <?php if (!$locked): ?><button class="btn" type="submit">Save changes</button><?php endif; ?>
  </form>
</div>
