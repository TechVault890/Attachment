<?php
// php tests/make_coord.php <username> <password> <institution_id>
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../app/core/bootstrap.php';
[, $user, $pw, $inst] = array_pad($argv, 4, null);
db()->prepare("INSERT INTO users (username,email,password_hash,role,full_name,institution_id) VALUES (?,?,?,?,?,?)")
    ->execute([$user, "$user@example.com", password_hash($pw, PASSWORD_DEFAULT), 'coordinator', 'Smoke Coordinator', (int)$inst]);
echo db()->lastInsertId() . "\n";
