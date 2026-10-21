<?php
// php bin/demo_check.php         shows whether the demo accounts exist and accept the demo password
// php bin/demo_check.php --fix   resets their password to Demo@12345 and re-activates them
if (PHP_SAPI !== 'cli') exit("CLI only.\n");
require __DIR__ . '/../app/core/bootstrap.php';
const DEMO_PW = 'Demo@12345';
$fix = in_array('--fix', $argv, true);
$rows = db()->query("SELECT id, username, role, is_active, password_hash FROM users WHERE LEFT(username, 5) = 'demo_' ORDER BY role, username")->fetchAll();
if (!$rows) exit("No demo accounts found. Run: php bin/demo_seed.php --reset\n");
foreach ($rows as $r) {
    $pwOk = password_verify(DEMO_PW, $r['password_hash']);
    printf("%-14s %-12s active=%d password=%s\n", $r['username'], $r['role'], $r['is_active'], $pwOk ? 'OK' : 'WRONG');
    if ($fix && (!$pwOk || !$r['is_active'])) {
        db()->prepare('UPDATE users SET password_hash = ?, is_active = 1 WHERE id = ?')->execute([password_hash(DEMO_PW, PASSWORD_DEFAULT), $r['id']]);
        echo "  -> fixed\n";
    }
}
