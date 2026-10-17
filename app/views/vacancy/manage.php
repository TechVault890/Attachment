<h1>My vacancies</h1>
<p><a class="btn" href="<?= e(url('vacancy/create')) ?>">Post a vacancy</a></p>
<div class="card table-wrap">
<?php if (!$rows): ?><p class="muted">You have not posted any vacancies yet.</p><?php else: ?>
<table class="table">
  <thead><tr><th>Title</th><th>County</th><th>Area</th><th>Slots</th><th>Closes</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><a href="<?= e(url('vacancy/view', ['id' => $r['id']])) ?>"><?= e($r['title']) ?></a></td>
    <td><?= e($r['county']) ?></td><td><?= e($r['course_area']) ?></td>
    <td><?= (int)$r['slots_filled'] ?>/<?= (int)$r['slots_total'] ?></td>
    <td><?= e($r['closes_on']) ?></td><td><?= badge($r['status']) ?></td>
    <td>
      <a href="<?= e(url('vacancy/edit', ['id' => $r['id']])) ?>">Edit</a>
      <a href="<?= e(url('application/applicants', ['vacancy' => $r['id']])) ?>">Applicants</a>
      <form method="post" action="<?= e(url('vacancy/status')) ?>" class="inline">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <?php if ($r['status'] === 'closed'): ?><input type="hidden" name="to" value="reopen"><button class="btn btn-sm" type="submit">Reopen</button>
        <?php else: ?><input type="hidden" name="to" value="close"><button class="btn btn-sm btn-danger" type="submit">Close</button><?php endif; ?>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
</div>
