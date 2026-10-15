<p><a href="<?= e(url('vacancy/manage')) ?>">&larr; My vacancies</a></p>
<div class="card">
  <h1><?= $v ? 'Edit vacancy' : 'Post a vacancy' ?></h1>
  <?php if ($v): ?><p class="muted"><?= (int)$v['slots_filled'] ?> slots already filled.</p><?php endif; ?>
  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <form method="post" action="<?= e($action) ?>" class="grid">
    <?= csrf_field() ?>
    <div class="full"><label for="title">Title</label>
      <input id="title" name="title" required maxlength="150" value="<?= old('title') ?>"></div>
    <div><label for="department">Department</label>
      <input id="department" name="department" required maxlength="120" value="<?= old('department') ?>"></div>
    <div><label for="office_location">Office location</label>
      <input id="office_location" name="office_location" required maxlength="150" value="<?= old('office_location') ?>"></div>
    <div><label for="county_id">County</label>
      <select id="county_id" name="county_id" required><option value="">Select...</option>
        <?php foreach ($counties as $c): ?><option value="<?= (int)$c['id'] ?>"<?= sel('county_id', $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div><label for="course_area">Course / area</label>
      <select id="course_area" name="course_area" required><option value="">Select...</option>
        <?php foreach ($areas as $a): ?><option<?= sel('course_area', $a) ?>><?= e($a) ?></option><?php endforeach; ?>
      </select></div>
    <div><label for="slots_total">Number of slots</label>
      <input id="slots_total" name="slots_total" type="number" min="1" max="500" required value="<?= old('slots_total') ?>"></div>
    <div><label for="duration_months">Duration</label>
      <select id="duration_months" name="duration_months" required>
        <?php foreach ([3, 6, 12] as $d): ?><option value="<?= $d ?>"<?= sel('duration_months', $d) ?>><?= $d ?> months</option><?php endforeach; ?>
      </select></div>
    <div><label for="opens_on">Opens on</label>
      <input id="opens_on" name="opens_on" type="date" required value="<?= old('opens_on') ?>"></div>
    <div><label for="closes_on">Closes on</label>
      <input id="closes_on" name="closes_on" type="date" required value="<?= old('closes_on') ?>"></div>
    <div class="full"><label for="description">Description (optional)</label>
      <textarea id="description" name="description" rows="5" maxlength="2000"><?= old('description') ?></textarea></div>
    <div class="full"><button class="btn" type="submit"><?= $v ? 'Save changes' : 'Post vacancy' ?></button></div>
  </form>
</div>
