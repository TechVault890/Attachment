<div class="card narrow">
  <h1>Log in</h1>
  <?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= e(url('auth/login')) ?>" autocomplete="on">
    <?= csrf_field() ?>
    <label for="identity">Username or email</label>
    <input id="identity" name="identity" type="text" required autofocus>
    <label for="password">Password</label>
    <input id="password" name="password" type="password" required>
    <button class="btn" type="submit">Log in</button>
  </form>
  <p class="muted">New student? <a href="<?= e(url('auth/register')) ?>">Create an account</a></p>
</div>
