<?php
// php tests/csvcell.php <file> <first-column value> <column number>  -> prints that column of the first matching row
[, $file, $name, $col] = array_pad($argv, 4, '');
$name = rtrim($name, ',');
$h = @fopen($file, 'r');
if (!$h) exit;
while (($row = fgetcsv($h, 0, ',', '"', '')) !== false) {
    if (isset($row[0]) && ltrim($row[0], "\xEF\xBB\xBF") === $name) {
        echo ($row[(int)$col - 1] ?? ''), "\n";
        break;
    }
}
