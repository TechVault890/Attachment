<?php
// Removes throwaway test data: users smoke*/scoord*/smin*/sadm*, SMOKE vacancies and windows, uploaded test files.
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../app/core/bootstrap.php';
$dir = config()['uploads']['dir'];
$q = db()->query("SELECT d.stored_name FROM documents d JOIN students s ON s.id=d.student_id JOIN users u ON u.id=s.user_id WHERE u.username LIKE 'smoke%'");
foreach ($q->fetchAll() as $r) {
    if (preg_match('/^[a-f0-9]{32}\.(pdf|doc|docx)$/', $r['stored_name'])) @unlink($dir . DIRECTORY_SEPARATOR . $r['stored_name']);
}
$v = db()->exec("DELETE FROM vacancies WHERE title LIKE 'SMOKE %'");
$w = db()->exec("DELETE FROM application_windows WHERE name LIKE 'SMOKE %'");
$n = db()->exec("DELETE FROM users WHERE username LIKE 'smoke%' OR username LIKE 'scoord%' OR username LIKE 'smin%' OR username LIKE 'sadm%'");
echo "Cleanup: removed $n users, $v vacancies, $w windows.\n";
