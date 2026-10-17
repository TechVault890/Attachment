<?php
// php tests/dbq.php "SELECT ..."  -> prints the first column of the first row
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../app/core/bootstrap.php';
$v = db()->query($argv[1] ?? 'SELECT 0')->fetchColumn();
echo ($v === false ? '0' : $v), "\n";
