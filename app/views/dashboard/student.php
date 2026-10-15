<h1>Welcome, <?= e($u['full_name']) ?></h1>
<?php if ($s): ?>
<div class="cards">
  <div class="card"><h2>Verification status</h2>
    <p><?= badge($s['verification_status']) ?></p>
    <?php if ($s['verification_status'] === 'rejected' && $s['verification_reason']): ?>
      <p class="muted">Reason: <?= e($s['verification_reason']) ?></p>
    <?php elseif ($s['verification_status'] === 'pending'): ?>
      <p class="muted">Your institution coordinator will review your details.</p>
    <?php endif; ?></div>
  <div class="card"><h2>My details</h2>
    <p><strong>Institution:</strong> <?= e($s['institution']) ?></p>
    <p><strong>Reg. no:</strong> <?= e($s['reg_no']) ?></p>
    <p><strong>Course:</strong> <?= e($s['course']) ?></p>
    <p><strong>Area of interest:</strong> <?= e($s['area_of_interest']) ?></p>
    <p><strong>Preferred county:</strong> <?= e($s['county']) ?></p>
    <p><strong>Period:</strong> <?= (int)$s['period_months'] ?> months</p></div>
</div>
<?php else: ?><div class="alert alert-danger">Student profile not found.</div><?php endif; ?>
