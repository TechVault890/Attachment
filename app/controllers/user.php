<?php
function user_index(): void {
    require_role('admin');
    $filter = (string)($_GET['role'] ?? 'staff');
    if (!in_array($filter, ['staff', 'student', 'coordinator', 'ministry', 'admin'], true)) $filter = 'staff';
    $sql = 'SELECT u.id, u.username, u.email, u.role, u.full_name, u.is_active, u.last_login_at,
        i.name AS institution, m.name AS ministry
        FROM users u LEFT JOIN institutions i ON i.id = u.institution_id LEFT JOIN ministries m ON m.id = u.ministry_id';
    $params = [];
    if ($filter === 'staff') {
        $sql .= " WHERE u.role <> 'student'";
    } else {
        $sql .= ' WHERE u.role = ?';
        $params[] = $filter;
    }
    $sql .= ' ORDER BY u.role, u.full_name LIMIT 300';
    $st = db()->prepare($sql);
    $st->execute($params);
    view('user/index', ['title' => 'Users', 'rows' => $st->fetchAll(), 'filter' => $filter]);
}

function user_create(): void {
    $admin = require_role('admin');
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $f = [];
        foreach (['role', 'full_name', 'username', 'email', 'institution_id', 'ministry_id'] as $k) $f[$k] = trim((string)($_POST[$k] ?? ''));
        $pw = (string)($_POST['password'] ?? '');
        $inst = $min = null;
        if (!in_array($f['role'], ['coordinator', 'ministry', 'admin'], true)) $errors[] = 'Choose a role.';
        if (mb_strlen($f['full_name']) < 3 || mb_strlen($f['full_name']) > 120) $errors[] = 'Enter the full name (3 to 120 characters).';
        if (!preg_match('/^[A-Za-z0-9_.]{4,30}$/', $f['username'])) $errors[] = 'Username must be 4 to 30 letters, numbers, dots or underscores.';
        if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL) || strlen($f['email']) > 150) $errors[] = 'Enter a valid email address.';
        if (strlen($pw) < 8) $errors[] = 'Password must be at least 8 characters.';
        if ($f['role'] === 'coordinator') {
            $q = db()->prepare('SELECT COUNT(*) FROM institutions WHERE id = ?');
            $q->execute([(int)$f['institution_id']]);
            if (!$q->fetchColumn()) $errors[] = 'Choose the institution for this coordinator.'; else $inst = (int)$f['institution_id'];
        }
        if ($f['role'] === 'ministry') {
            $q = db()->prepare('SELECT COUNT(*) FROM ministries WHERE id = ?');
            $q->execute([(int)$f['ministry_id']]);
            if (!$q->fetchColumn()) $errors[] = 'Choose the Ministry for this user.'; else $min = (int)$f['ministry_id'];
        }
        if (!$errors) {
            try {
                db()->prepare('INSERT INTO users (username, email, password_hash, role, full_name, institution_id, ministry_id) VALUES (?,?,?,?,?,?,?)')
                    ->execute([$f['username'], $f['email'], password_hash($pw, PASSWORD_DEFAULT), $f['role'], $f['full_name'], $inst, $min]);
                audit('create_user', 'user', (int)db()->lastInsertId(), $f['role'] . ' ' . $f['username']);
                flash('success', 'Account created for ' . $f['full_name'] . '. Give them the username and the password you set.');
                redirect('user/index');
            } catch (PDOException $ex) {
                if ($ex->getCode() !== '23000') throw $ex;
                $errors[] = 'That username or email is already in use.';
            }
        }
        $_SESSION['old'] = $f;
    }
    view('user/create', ['title' => 'Create account', 'errors' => $errors, 'institutions' => institutions(), 'ministries' => ministries()]);
}

function user_toggle(): void {
    $admin = require_role('admin');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('user/index');
    $st = db()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([(int)($_POST['id'] ?? 0)]);
    $t = $st->fetch();
    if (!$t) not_found();
    if ((int)$t['id'] === (int)$admin['id']) {
        flash('danger', 'You cannot deactivate your own account.');
        redirect('user/index');
    }
    if ($t['is_active'] && $t['role'] === 'admin') {
        $q = db()->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1 AND id <> ?");
        $q->execute([$t['id']]);
        if ((int)$q->fetchColumn() < 1) {
            flash('danger', 'There must be at least one active admin.');
            redirect('user/index');
        }
    }
    $new = $t['is_active'] ? 0 : 1;
    db()->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([$new, $t['id']]);
    audit($new ? 'activate_user' : 'deactivate_user', 'user', (int)$t['id'], $t['username']);
    flash('success', $t['full_name'] . ($new ? ' reactivated.' : ' deactivated.'));
    redirect('user/index', $_POST['back'] === 'all' ? ['role' => 'all'] : []);
}
