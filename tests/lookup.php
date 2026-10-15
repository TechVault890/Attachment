<?php
// php tests/lookup.php <window|vacancy> "<name or title>"  -> prints the newest matching id
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../app/core/bootstrap.php';
[, $what, $name] = array_pad($argv, 3, '');
[$table, $col] = $what === 'window' ? ['application_windows', 'name'] : ['vacancies', 'title'];
$st = db()->prepare("SELECT id FROM $table WHERE $col = ? ORDER BY id DESC LIMIT 1");
$st->execute([$name]);
echo (int)$st->fetchColumn() . "\n";
