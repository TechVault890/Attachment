<?php
// php tests/csv_test.php  -> checks the spreadsheet-formula protection
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../app/core/bootstrap.php';
$cases = [
    ['=1+1', "'=1+1"], ['+254700000000', "'+254700000000"], ['-5+2', "'-5+2"], ['@SUM(A1)', "'@SUM(A1)"],
    ['Nairobi', 'Nairobi'], ['Mount Kenya University', 'Mount Kenya University'], ['', ''], [null, ''], [5, '5'],
];
$bad = 0;
foreach ($cases as [$in, $want]) {
    if (csv_safe($in) !== $want) { $bad++; echo 'FAIL for ' . var_export($in, true) . "\n"; }
}
echo $bad === 0 ? "all csv_safe cases OK\n" : "$bad csv_safe cases failed\n";
