<p><a href="<?= e(url('vacancy/view', ['id' => $v['id']])) ?>">&larr; Back to vacancy</a></p>
<h1>Applicants: <?= e($v['title']) ?></h1>
<p><?= badge($v['status']) ?> <span class="muted"><?= e($v['ministry']) ?> &middot; <?= e($v['county']) ?> &middot; <?= (int)$v['slots_filled'] ?> of <?= (int)$v['slots_total'] ?> slots filled (<?= (int)$slots_left ?> open)</span></p>
<p class="muted">Ranked by match score (area 50, county 30, period 20), then by who applied first. The top applicants for the open slots are marked Suggested; you confirm each placement.</p>
<div class="card table-wrap">
<?php if (!$rows): ?><p class="muted">No applications yet.</p><?php else: ?>
<table class="table">
  <thead><tr><th>#</th><th>Applicant</th><th>Course / interest</th><th>Match</th><th>Documents</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody>
  <?php $n = 0; foreach ($rows as $r): $n++; $m = $r['match']; $active = in_array($r['status'], ['applied', 'shortlisted'], true); ?>
  <tr>
    <td><?= $n ?></td>
    <td><strong><?= e($r['full_name']) ?></strong><br><span class="muted"><?= e($r['reg_no']) ?> &middot; <?= e($r['institution']) ?></span><br><span class="muted"><?= e($r['email']) ?><?= $r['phone'] ? ' &middot; ' . e($r['phone']) : '' ?></span></td>
    <td><?= e($r['course']) ?><br><span class="muted"><?= e($r['area_of_interest']) ?> &middot; <?= e($r['pref_county']) ?> &middot; <?= (int)$r['period_months'] ?> months</span></td>
    <td><strong><?= (int)$m['total'] ?>%</strong><br><span class="muted">area <?= $m['area'] ? 'yes' : 'no' ?>, county <?= $m['county'] ? 'yes' : 'no' ?>, period +<?= (int)$m['period'] ?></span></td>
    <td><?php foreach ($types as $key => $label): $d = $r['docs'][$key] ?? null; ?>
        <?php if ($d): ?><a href="<?= e(url('document/download', ['id' => $d['id']])) ?>"><?= e(explode(' ', $label)[0]) ?></a><?php else: ?><span class="muted"><?= e(explode(' ', $label)[0]) ?>: none</span><?php endif; ?><br>
      <?php endforeach; ?></td>
    <td><?= badge($r['status']) ?><?php if ($r['suggested']): ?> <span class="badge badge-suggested">Suggested</span><?php endif; ?>
        <?php if ($r['decision_note']): ?><br><span class="muted"><?= e($r['decision_note']) ?></span><?php endif; ?></td>
    <td>
      <?php if ($active): ?>
        <?php if ($r['status'] === 'applied'): ?>
        <form method="post" action="<?= e(url('application/decide')) ?>" class="inline"><?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$r['app_id'] ?>"><input type="hidden" name="action" value="shortlist">
          <button class="btn btn-sm btn-alt" type="submit">Shortlist</button></form>
        <?php endif; ?>
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
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
</div>
