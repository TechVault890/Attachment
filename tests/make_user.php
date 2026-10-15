<?php
// php tests/make_user.php <role> <username> <password> <org_id|0>
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../app/core/bootstrap.php';
[, $role, $user, $pw, $org] = array_pad($argv, 5, null);
$org = (int)$org;
db()->prepare('INSERT INTO users (username,email,password_hash,role,full_name,institution_id,ministry_id) VALUES (?,?,?,?,?,?,?)')
    ->execute([$user, "$user@example.com", password_hash($pw, PASSWORD_DEFAULT), $role, "Smoke $role",
        $role === 'coordinator' ? $org : null, $role === 'ministry' ? $org : null]);
echo db()->lastInsertId() . "\n";
