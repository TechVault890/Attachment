<?php
require __DIR__ . '/../app/core/database.php';
header('Content-Type: text/plain; charset=utf-8');
echo "PHP version: " . PHP_VERSION . "\n";
echo "OS: " . PHP_OS_FAMILY . "\n";
try {
    $v = db()->query('SELECT VERSION() AS v')->fetch()['v'];
    echo "Database: CONNECTED (server $v)\n";
} catch (Throwable $e) {
    echo "Database: FAILED - " . $e->getMessage() . "\n";
}
$up = config()['uploads']['dir'];
echo "Uploads dir writable: " . (is_writable($up) ? 'YES' : 'NO') . "\n";
