<p><a href="<?= e(url('user/index')) ?>">&larr; Users</a></p>
<div class="card">
  <h1>Create account</h1>
  <p class="muted">Students register themselves. Use this for coordinators, Ministry officers and admins. Fill in only the institution (coordinator) or the Ministry (Ministry officer).</p>
  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <form method="post" action="<?= e(url('user/create')) ?>" class="grid">
    <?= csrf_field() ?>
    <div><label for="role">Role</label>
      <select id="role" name="role" required>
        <?php foreach (['coordinator' => 'Coordinator (institution liaison)', 'ministry' => 'Ministry officer', 'admin' => 'Admin'] as $k => $label): ?>
          <option value="<?= e($k) ?>"<?= sel('role', $k) ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div><label for="full_name">Full name</label><input id="full_name" name="full_name" required maxlength="120" value="<?= old('full_name') ?>"></div>
    <div><label for="username">Username</label><input id="username" name="username" required maxlength="30" value="<?= old('username') ?>"></div>
    <div><label for="email">Email</label><input id="email" name="email" type="email" required maxlength="150" value="<?= old('email') ?>"></div>
    <div><label for="password">Initial password (min 8)</label><input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"></div>
    <div><label for="institution_id">Institution (coordinators)</label>
      <select id="institution_id" name="institution_id"><option value="">None</option>
        <?php foreach ($institutions as $i): ?><option value="<?= (int)$i['id'] ?>"<?= sel('institution_id', $i['id']) ?>><?= e($i['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div><label for="ministry_id">Ministry (Ministry officers)</label>
      <select id="ministry_id" name="ministry_id"><option value="">None</option>
        <?php foreach ($ministries as $m): ?><option value="<?= (int)$m['id'] ?>"<?= sel('ministry_id', $m['id']) ?>><?= e($m['name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="full"><button class="btn" type="submit">Create account</button></div>
  </form>
</div>
