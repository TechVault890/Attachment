<?php
$who = function (array $r): string {
    return '<strong>' . e($r['full_name']) . '</strong><br><span class="muted">' . e($r['reg_no']) . ' &middot; ' . e($r['institution'])
        . '</span><br><span class="muted">' . e($r['email']) . ($r['phone'] ? ' &middot; ' . e($r['phone']) : '') . '</span>';
};
$interest = function (array $r): string {
    return e($r['course']) . '<br><span class="muted">' . e($r['area_of_interest']) . ' &middot; ' . e($r['pref_county']) . ' &middot; ' . (int)$r['period_months'] . ' months</span>';
};
$matchCell = function (array $r): string {
    $m = $r['match'];
    return '<strong>' . (int)$m['total'] . '%</strong><br><span class="muted">area ' . ($m['area'] ? 'yes' : 'no') . ', county ' . ($m['county'] ? 'yes' : 'no') . ', period +' . (int)$m['period'] . '</span>';
};
$docLinks = function (array $r) use ($types): string {
    $out = '';
    foreach ($types as $key => $label) {
        $d = $r['docs'][$key] ?? null;
        $short = e(explode(' ', $label)[0]);
        $out .= $d ? '<a href="' . e(url('document/download', ['id' => $d['id']])) . '">' . $short . '</a><br>' : '<span class="muted">' . $short . ': none</span><br>';
    }
    return $out;
};
?>
<p><a href="<?= e(url('vacancy/view', ['id' => $v['id']])) ?>">&larr; Back to vacancy</a></p>
<h1>Applicants: <?= e($v['title']) ?></h1>
<p><?= badge($v['status']) ?> <span class="muted"><?= e($v['ministry']) ?> &middot; <?= e($v['county']) ?> &middot; <?= (int)$v['slots_filled'] ?> of <?= (int)$v['slots_total'] ?> slots taken (<?= (int)$slots_left ?> open)</span></p>
<p class="muted">Students whose area of interest matches this vacancy (match <?= (int)$min ?>% or more) are placed automatically, first come first served. You approve or decline each placement. Other applicants wait here for your decision.</p>

<?php if (!$pending && !$waiting && !$decided): ?><div class="card"><p class="muted">No applications yet.</p></div><?php endif; ?>

<?php if ($pending): ?>
<h2>Awaiting your approval (<?= count($pending) ?>)</h2>
<div class="card table-wrap"><table class="table">
  <thead><tr><th>Applicant</th><th>Course / interest</th><th>Match</th><th>Documents</th><th>Applied</th><th>Actions</th></tr></thead><tbody>
  <?php foreach ($pending as $r): ?>
  <tr>
    <td><?= $who($r) ?></td><td><?= $interest($r) ?></td><td><?= $matchCell($r) ?></td><td><?= $docLinks($r) ?></td>
    <td><?= e($r['applied_at']) ?><br><span class="muted">placed automatically</span></td>
    <td>
      <details><summary>Approve</summary>
        <form method="post" action="<?= e(url('application/decide')) ?>"><?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$r['app_id'] ?>"><input type="hidden" name="action" value="approve">
          <label>Start date (optional) <input type="date" name="start_date"></label>
          <label>Supervisor name <input name="supervisor_name" maxlength="120"></label>
          <label>Supervisor phone <input name="supervisor_phone" maxlength="20"></label>
          <label>Supervisor email <input name="supervisor_email" type="email" maxlength="150"></label>
          <button class="btn btn-sm" type="submit">Confirm approval</button>
        </form></details>
      <details><summary>Decline</summary>
        <form method="post" action="<?= e(url('application/decide')) ?>"><?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$r['app_id'] ?>"><input type="hidden" name="action" value="decline">
          <label>Reason (shown to the student) <input name="note" required minlength="5" maxlength="255"></label>
          <button class="btn btn-sm btn-danger" type="submit">Decline placement</button>
        </form></details>
    </td>
  </tr>
  <?php endforeach; ?></tbody></table></div>
<?php endif; ?>

<?php if ($waiting): ?>
<h2>Waiting for your decision (<?= count($waiting) ?>)</h2>
<p class="muted">These applicants did not qualify for automatic placement (their area of interest differs).</p>
<div class="card table-wrap"><table class="table">
  <thead><tr><th>Applicant</th><th>Course / interest</th><th>Match</th><th>Documents</th><th>Applied</th><th>Actions</th></tr></thead><tbody>
  <?php foreach ($waiting as $r): ?>
  <tr>
    <td><?= $who($r) ?></td><td><?= $interest($r) ?></td><td><?= $matchCell($r) ?></td><td><?= $docLinks($r) ?></td>
    <td><?= e($r['applied_at']) ?></td>
    <td>
      <?php if ($slots_left > 0): ?>
      <details><summary>Place</summary>
        <form method="post" action="<?= e(url('application/decide')) ?>"><?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$r['app_id'] ?>"><input type="hidden" name="action" value="place">
          <label>Start date (optional) <input type="date" name="start_date"></label>
          <label>Supervisor name <input name="supervisor_name" maxlength="120"></label>
          <label>Supervisor phone <input name="supervisor_phone" maxlength="20"></label>
          <label>Supervisor email <input name="supervisor_email" type="email" maxlength="150"></label>
          <button class="btn btn-sm" type="submit">Confirm placement</button>
        </form></details>
      <?php endif; ?>
      <details><summary>Reject</summary>
        <form method="post" action="<?= e(url('application/decide')) ?>"><?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$r['app_id'] ?>"><input type="hidden" name="action" value="reject">
          <label>Reason (shown to the student) <input name="note" required minlength="5" maxlength="255"></label>
          <button class="btn btn-sm btn-danger" type="submit">Reject</button>
        </form></details>
    </td>
  </tr>
  <?php endforeach; ?></tbody></table></div>
<?php endif; ?>

<?php if ($decided): ?>
<h2>Decided (<?= count($decided) ?>)</h2>
<div class="card table-wrap"><table class="table">
  <thead><tr><th>Applicant</th><th>Course / interest</th><th>Match</th><th>Status</th></tr></thead><tbody>
  <?php foreach ($decided as $r): ?>
  <tr><td><?= $who($r) ?></td><td><?= $interest($r) ?></td><td><?= $matchCell($r) ?></td>
    <td><?= badge($r['status']) ?><?php if ($r['decision_note']): ?><br><span class="muted"><?= e($r['decision_note']) ?></span><?php endif; ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
<?php endif; ?>
