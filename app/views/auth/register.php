<div class="card">
  <h1>Student registration</h1>
  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <form method="post" action="<?= e(url('auth/register')) ?>" class="grid">
    <?= csrf_field() ?>
    <div><label for="full_name">Full name</label>
      <input id="full_name" name="full_name" required maxlength="120" value="<?= old('full_name') ?>"></div>
    <div><label for="username">Username</label>
      <input id="username" name="username" required maxlength="30" value="<?= old('username') ?>"></div>
    <div><label for="email">Email</label>
      <input id="email" name="email" type="email" required maxlength="150" value="<?= old('email') ?>"></div>
    <div><label for="phone">Phone (optional)</label>
      <input id="phone" name="phone" type="tel" maxlength="20" value="<?= old('phone') ?>"></div>
    <div><label for="password">Password (min 8 characters)</label>
      <input id="password" name="password" type="password" required minlength="8"></div>
    <div><label for="password_confirm">Confirm password</label>
      <input id="password_confirm" name="password_confirm" type="password" required minlength="8"></div>
    <div><label for="institution_id">Institution</label>
      <select id="institution_id" name="institution_id" required>
        <option value="">Select...</option>
        <?php foreach ($institutions as $i): ?><option value="<?= (int)$i['id'] ?>"<?= sel('institution_id', $i['id']) ?>><?= e($i['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div><label for="reg_no">Registration number</label>
      <input id="reg_no" name="reg_no" required maxlength="40" value="<?= old('reg_no') ?>"></div>
    <div><label for="course">Attachment course</label>
      <input id="course" name="course" required maxlength="150" value="<?= old('course') ?>"></div>
    <div><label for="area_of_interest">Area of interest</label>
      <select id="area_of_interest" name="area_of_interest" required>
        <option value="">Select...</option>
        <?php foreach ($areas as $a): ?><option<?= sel('area_of_interest', $a) ?>><?= e($a) ?></option><?php endforeach; ?>
      </select></div>
    <div><label for="preferred_county_id">Preferred county</label>
      <select id="preferred_county_id" name="preferred_county_id" required>
        <option value="">Select...</option>
        <?php foreach ($counties as $c): ?><option value="<?= (int)$c['id'] ?>"<?= sel('preferred_county_id', $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div><label for="period_months">Period available</label>
      <select id="period_months" name="period_months" required>
        <option value="">Select...</option>
        <?php foreach ([3, 6, 12] as $p): ?><option value="<?= $p ?>"<?= sel('period_months', $p) ?>><?= $p ?> months</option><?php endforeach; ?>
      </select></div>
    <div class="full"><button class="btn" type="submit">Create account</button></div>
  </form>
  <p class="muted">Already registered? <a href="<?= e(url('auth/login')) ?>">Log in</a></p>
</div>
