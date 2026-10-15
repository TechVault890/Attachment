<h1>Welcome, <?= e($u['full_name']) ?></h1>
<?php if ($s): ?>
<div class="cards">
  <div class="card"><h2>Verification status</h2>
    <p><?= badge($s['verification_status']) ?></p>
    <?php if ($s['verification_status'] === 'rejected' && $s['verification_reason']): ?>
      <p class="muted">Reason: <?= e($s['verification_reason']) ?></p>
    <?php elseif ($s['verification_status'] === 'pending'): ?>
      <p class="muted">Upload all four documents; your coordinator will then review you.</p>
    <?php endif; ?>
    <p><a class="btn btn-sm" href="<?= e(url('student/documents')) ?>">Documents (<?= (int)$s['doc_count'] ?>/4)</a>
       <a class="btn btn-sm btn-alt" href="<?= e(url('student/profile')) ?>">Edit profile</a></p></div>
  <div class="card"><h2>My details</h2>
    <p><strong>Institution:</strong> <?= e($s['institution']) ?></p>
    <p><strong>Reg. no:</strong> <?= e($s['reg_no']) ?></p>
    <p><strong>Course:</strong> <?= e($s['course']) ?></p>
    <p><strong>Area of interest:</strong> <?= e($s['area_of_interest']) ?></p>
    <p><strong>Preferred county:</strong> <?= e($s['county']) ?></p>
    <p><strong>Period:</strong> <?= (int)$s['period_months'] ?> months</p></div>
</div>
<?php else: ?><div class="alert alert-danger">Student profile not found.</div><?php endif; ?>
