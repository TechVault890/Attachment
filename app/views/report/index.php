<?php
$q = array_filter(['from' => $range['from'], 'to' => $range['to']]);
$pct = fn(int $n, int $max): int => $max > 0 ? (int)round($n * 100 / $max) : 0;
$counts = fn(array $rows, string $k): array => array_map('intval', array_column($rows, $k));
$totalPlacements = array_sum($counts($byCounty, 'placements'));
$maxCounty = max(array_merge([0], $counts($byCounty, 'placements')));
$maxMin = max(array_merge([0], $counts($byMinistry, 'placements')));
$maxInst = max(array_merge([0], $counts($byInstitution, 'placements')));
$rangeText = ($range['from'] !== '' || $range['to'] !== '')
    ? 'Placements confirmed' . ($range['from'] !== '' ? ' from ' . $range['from'] : '') . ($range['to'] !== '' ? ' to ' . $range['to'] : '')
    : 'Placements: all time';
?>
<h1>Reports</h1>
<p class="print-only muted">Generated <?= e(date('Y-m-d H:i')) ?>. <?= e($rangeText) ?>.</p>
<?php foreach ($range['errors'] as $er): ?><div class="alert alert-danger"><?= e($er) ?></div><?php endforeach; ?>
<form method="get" action="<?= e(base_url() . '/index.php') ?>" class="card noprint">
  <input type="hidden" name="r" value="report/index">
  <div class="grid">
    <div><label for="from">Placements confirmed from</label><input id="from" name="from" type="date" value="<?= e($range['from']) ?>"></div>
    <div><label for="to">to</label><input id="to" name="to" type="date" value="<?= e($range['to']) ?>"></div>
  </div>
  <button class="btn btn-sm" type="submit">Apply filter</button>
  <a class="btn btn-sm btn-alt" href="<?= e(url('report/index')) ?>">Clear</a>
  <button class="btn btn-sm btn-alt" type="button" onclick="window.print()">Print</button>
  <a class="btn btn-sm btn-alt" href="<?= e(url('report/export', ['type' => 'placements'] + $q)) ?>">Export all placements (CSV)</a>
</form>
<p class="muted"><?= e($rangeText) ?></p>

<div class="cards">
  <div class="card stat"><span class="num"><?= (int)$totalPlacements ?></span> Placements</div>
  <div class="card stat"><span class="num"><?= (int)$tot['vacancies'] ?></span> Vacancies posted</div>
  <div class="card stat"><span class="num"><?= (int)$tot['filled'] ?> / <?= (int)$tot['total'] ?></span> Slots filled</div>
  <div class="card stat"><span class="num"><?= (int)$tot['open_slots'] ?></span> Open slots available</div>
</div>

<div class="card table-wrap">
  <h2>Placements per county <a class="btn btn-sm btn-alt noprint" href="<?= e(url('report/export', ['type' => 'county'] + $q)) ?>">Export CSV</a></h2>
  <?php if (!$byCounty): ?><p class="muted">No placements in this period.</p><?php else: ?>
  <table class="table"><thead><tr><th>County</th><th>Placements</th><th></th></tr></thead><tbody>
  <?php foreach ($byCounty as $r): ?>
    <tr><td><?= e($r['county']) ?></td><td><?= (int)$r['placements'] ?></td>
      <td><div class="bar"><span style="width:<?= $pct((int)$r['placements'], $maxCounty) ?>%"></span></div></td></tr>
  <?php endforeach; ?></tbody></table>
  <?php endif; ?>
</div>

<div class="card table-wrap">
  <h2>Placements per Ministry <a class="btn btn-sm btn-alt noprint" href="<?= e(url('report/export', ['type' => 'ministry'] + $q)) ?>">Export CSV</a></h2>
  <table class="table"><thead><tr><th>Ministry</th><th>Placements</th><th></th></tr></thead><tbody>
  <?php foreach ($byMinistry as $r): ?>
    <tr><td><?= e($r['ministry']) ?></td><td><?= (int)$r['placements'] ?></td>
      <td><div class="bar"><span style="width:<?= $pct((int)$r['placements'], $maxMin) ?>%"></span></div></td></tr>
  <?php endforeach; ?></tbody></table>
</div>

<div class="card table-wrap">
  <h2>Placements per institution <a class="btn btn-sm btn-alt noprint" href="<?= e(url('report/export', ['type' => 'institution'] + $q)) ?>">Export CSV</a></h2>
  <table class="table"><thead><tr><th>Institution</th><th>Students</th><th>Verified</th><th>Placements</th><th></th></tr></thead><tbody>
  <?php foreach ($byInstitution as $r): ?>
    <tr><td><?= e($r['institution']) ?></td><td><?= (int)$r['students'] ?></td><td><?= (int)$r['verified'] ?></td><td><?= (int)$r['placements'] ?></td>
      <td><div class="bar"><span style="width:<?= $pct((int)$r['placements'], $maxInst) ?>%"></span></div></td></tr>
  <?php endforeach; ?></tbody></table>
</div>

<div class="card table-wrap">
  <h2>Open vs filled slots <a class="btn btn-sm btn-alt noprint" href="<?= e(url('report/export', ['type' => 'slots'])) ?>">Export CSV</a></h2>
  <p class="muted">A snapshot of all vacancies today. Open slots counts only vacancies that are currently open.</p>
  <table class="table"><thead><tr><th>Ministry</th><th>Vacancies</th><th>Total slots</th><th>Filled</th><th>Open slots</th><th>Fill rate</th></tr></thead><tbody>
  <?php foreach ($slots as $r): ?>
    <tr><td><?= e($r['ministry']) ?></td><td><?= $r['vacancies'] ?></td><td><?= $r['total'] ?></td><td><?= $r['filled'] ?></td><td><?= $r['open_slots'] ?></td>
      <td><div class="bar"><span style="width:<?= (int)$r['rate'] ?>%"></span></div><?= (int)$r['rate'] ?>%</td></tr>
  <?php endforeach; ?>
    <tr><td><strong>Total</strong></td><td><strong><?= $tot['vacancies'] ?></strong></td><td><strong><?= $tot['total'] ?></strong></td>
      <td><strong><?= $tot['filled'] ?></strong></td><td><strong><?= $tot['open_slots'] ?></strong></td><td></td></tr>
  </tbody></table>
</div>

<div class="cards">
  <div class="card"><h2>Students</h2>
    <?php foreach ($pipe['students'] as $k => $n): ?><p><?= badge($k) ?> <?= (int)$n ?></p><?php endforeach; ?></div>
  <div class="card"><h2>Applications</h2>
    <?php foreach ($pipe['applications'] as $k => $n): ?><p><?= badge($k) ?> <?= (int)$n ?></p><?php endforeach; ?></div>
  <div class="card"><h2>Vacancies</h2>
    <?php foreach ($pipe['vacancies'] as $k => $n): ?><p><?= badge($k) ?> <?= (int)$n ?></p><?php endforeach; ?></div>
</div>
