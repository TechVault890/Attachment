<?php
// php tests/verify_student.php <username>  -> marks a test student verified (skips the document flow)
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../app/core/bootstrap.php';
db()->prepare("UPDATE students s JOIN users u ON u.id = s.user_id SET s.verification_status = 'verified', s.verified_at = NOW() WHERE u.username = ?")
    ->execute([$argv[1] ?? '']);
