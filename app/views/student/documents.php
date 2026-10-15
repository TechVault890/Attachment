<h1>My documents</h1>
<p class="muted">PDF, DOC or DOCX, maximum 2 MB each. All four are required before your coordinator can verify you.</p>
<?php if ($locked): ?><div class="alert alert-warning">Your profile is verified, so your documents are locked.</div><?php endif; ?>
<div class="card table-wrap">
<table class="table">
  <thead><tr><th>Document</th><th>Status</th><th>File</th><th>Upload / replace</th></tr></thead>
  <tbody>
  <?php foreach ($types as $key => $label): $d = $docs[$key] ?? null; ?>
  <tr>
    <td><?= e($label) ?></td>
    <td><?php if ($d): ?><span class="badge badge-verified doc-ok">Uploaded</span><?php else: ?><span class="badge badge-pending">Missing</span><?php endif; ?></td>
    <td><?php if ($d): ?><a href="<?= e(url('document/download', ['id' => $d['id']])) ?>"><?= e($d['original_name']) ?></a><br><span class="muted"><?= e(format_size((int)$d['size_bytes'])) ?> &middot; <?= e($d['uploaded_at']) ?></span><?php else: ?>-<?php endif; ?></td>
    <td><?php if (!$locked): ?><form method="post" enctype="multipart/form-data" action="<?= e(url('student/upload')) ?>"><?= csrf_field() ?><input type="hidden" name="doc_type" value="<?= e($key) ?>"><input type="file" name="file" accept=".pdf,.doc,.docx" required aria-label="Choose file for <?= e($label) ?>"><button class="btn btn-sm" type="submit">Upload</button></form><?php else: ?>Locked<?php endif; ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
