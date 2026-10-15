<?php
// Usage: php bin/create_user.php <role> <username> <email> "<Full Name>" [institution_id|ministry_id]
// Roles: admin | coordinator | ministry   (students register through the website)
if (PHP_SAPI !== 'cli') { exit("CLI only.\n"); }
require __DIR__ . '/../app/core/bootstrap.php';

[$self, $role, $username, $email, $name] = array_pad($argv, 5, null);
$orgId = isset($argv[5]) ? (int)$argv[5] : null;

if (!in_array($role, ['admin', 'coordinator', 'ministry'], true) || !$username || !$email || !$name) {
    exit("Usage: php bin/create_user.php <admin|coordinator|ministry> <username> <email> \"Full Name\" [org_id]\n");
}
if (!preg_match('/^[A-Za-z0-9_.]{4,30}$/', $username) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit("Invalid username or email.\n");
}
$instId = $minId = null;
if ($role === 'coordinator') {
    $q = db()->prepare('SELECT COUNT(*) FROM institutions WHERE id = ?'); $q->execute([$orgId]);
    if (!$orgId || !$q->fetchColumn()) exit("Coordinator needs a valid institution_id (e.g. 1 = Mount Kenya University).\n");
    $instId = $orgId;
}
if ($role === 'ministry') {
    $q = db()->prepare('SELECT COUNT(*) FROM ministries WHERE id = ?'); $q->execute([$orgId]);
    if (!$orgId || !$q->fetchColumn()) exit("Ministry user needs a valid ministry_id (e.g. 1 = Ministry of Education).\n");
    $minId = $orgId;
}
function ask(string $prompt): string {
    echo $prompt;
    $hide = PHP_OS_FAMILY !== 'Windows' && function_exists('shell_exec');
    if ($hide) shell_exec('stty -echo 2>/dev/null');
    $line = trim((string)fgets(STDIN));
    if ($hide) { shell_exec('stty echo 2>/dev/null'); echo "\n"; }
    return $line;
}
$pw = ask('Password (min 8 chars): ');
if (strlen($pw) < 8 || $pw !== ask('Confirm password: ')) exit("Passwords too short or do not match.\n");

try {
    db()->prepare('INSERT INTO users (username, email, password_hash, role, full_name, institution_id, ministry_id) VALUES (?,?,?,?,?,?,?)')
        ->execute([$username, $email, password_hash($pw, PASSWORD_DEFAULT), $role, $name, $instId, $minId]);
    $id = (int)db()->lastInsertId();
    audit('create_user', 'user', $id, "role=$role via CLI", null);
    echo "Created $role '$username' (id $id).\n";
} catch (PDOException $e) {
    echo ($e->getCode() === '23000') ? "Username or email already exists.\n" : 'Error: ' . $e->getMessage() . "\n";
}
