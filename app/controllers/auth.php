<?php
function auth_login(): void {
    if (current_user()) {
        redirect('dashboard/index');
    }
    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (($_SESSION['lock_until'] ?? 0) > time()) {
            $error = 'Too many failed attempts. Please wait a minute and try again.';
        } else {
            $id = trim((string)($_POST['identity'] ?? ''));
            $pw = (string)($_POST['password'] ?? '');
            $st = db()->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
            $st->execute([$id, $id]);
            $u = $st->fetch();
            if ($u && $u['is_active'] && password_verify($pw, $u['password_hash'])) {
                unset($_SESSION['fails'], $_SESSION['lock_until']);
                login_user($u);
                audit('login', 'user', (int)$u['id'], null, (int)$u['id']);
                redirect('dashboard/index');
            }
            $_SESSION['fails'] = ($_SESSION['fails'] ?? 0) + 1;
            if ($_SESSION['fails'] >= 5) {
                $_SESSION['lock_until'] = time() + 60;
                $_SESSION['fails'] = 0;
            }
            $error = 'Invalid username/email or password.';
        }
    }
    view('auth/login', ['title' => 'Log in', 'error' => $error]);
}

function auth_logout(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        logout_user();
    }
    redirect('auth/login');
}

function auth_register(): void {
    if (current_user()) {
        redirect('dashboard/index');
    }
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $f = [];
        foreach (['full_name', 'username', 'email', 'phone', 'institution_id', 'reg_no', 'course',
                  'area_of_interest', 'preferred_county_id', 'period_months'] as $k) {
            $f[$k] = trim((string)($_POST[$k] ?? ''));
        }
        $pw = (string)($_POST['password'] ?? '');
        $pw2 = (string)($_POST['password_confirm'] ?? '');
        $_SESSION['old'] = $f;

        if (mb_strlen($f['full_name']) < 3 || mb_strlen($f['full_name']) > 120) $errors[] = 'Enter your full name (3 to 120 characters).';
        if (!preg_match('/^[A-Za-z0-9_.]{4,30}$/', $f['username'])) $errors[] = 'Username must be 4 to 30 letters, numbers, dots or underscores.';
        if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL) || strlen($f['email']) > 150) $errors[] = 'Enter a valid email address.';
        if ($f['phone'] !== '' && !preg_match('/^[0-9+\s-]{7,20}$/', $f['phone'])) $errors[] = 'Enter a valid phone number.';
        if (strlen($pw) < 8) $errors[] = 'Password must be at least 8 characters.';
        if ($pw !== $pw2) $errors[] = 'Passwords do not match.';
        if (mb_strlen($f['reg_no']) < 3 || mb_strlen($f['reg_no']) > 40) $errors[] = 'Enter your registration number.';
        if (mb_strlen($f['course']) < 3 || mb_strlen($f['course']) > 150) $errors[] = 'Enter your attachment course.';
        if (!in_array($f['area_of_interest'], areas(), true)) $errors[] = 'Choose an area of interest.';
        if (!in_array($f['period_months'], ['3', '6', '12'], true)) $errors[] = 'Choose a period of 3, 6 or 12 months.';

        $chk = db()->prepare('SELECT COUNT(*) FROM institutions WHERE id = ?');
        $chk->execute([(int)$f['institution_id']]);
        if (!$chk->fetchColumn()) $errors[] = 'Choose your institution.';
        $chk = db()->prepare('SELECT COUNT(*) FROM counties WHERE id = ?');
        $chk->execute([(int)$f['preferred_county_id']]);
        if (!$chk->fetchColumn()) $errors[] = 'Choose your preferred county.';

        if (!$errors) {
            $pdo = db();
            $uid = 0; $sid = 0;
            try {
                $pdo->beginTransaction();
                $pdo->prepare('INSERT INTO users (username, email, password_hash, role, full_name, phone, institution_id) VALUES (?,?,?,?,?,?,?)')
                    ->execute([$f['username'], $f['email'], password_hash($pw, PASSWORD_DEFAULT), 'student',
                        $f['full_name'], $f['phone'] !== '' ? $f['phone'] : null, (int)$f['institution_id']]);
                $uid = (int)$pdo->lastInsertId();
                $pdo->prepare('INSERT INTO students (user_id, institution_id, reg_no, course, area_of_interest, preferred_county_id, period_months) VALUES (?,?,?,?,?,?,?)')
                    ->execute([$uid, (int)$f['institution_id'], $f['reg_no'], $f['course'],
                        $f['area_of_interest'], (int)$f['preferred_county_id'], (int)$f['period_months']]);
                $sid = (int)$pdo->lastInsertId();
                $co = $pdo->prepare("SELECT id FROM users WHERE role = 'coordinator' AND institution_id = ? AND is_active = 1");
                $co->execute([(int)$f['institution_id']]);
                foreach ($co->fetchAll() as $c) {
                    notify((int)$c['id'], 'verification', 'New student awaiting verification',
                        $f['full_name'] . ' (' . $f['reg_no'] . ') has registered.');
                }
                $pdo->commit();
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if ($ex->getCode() === '23000') {
                    $errors[] = 'That username, email or registration number is already registered.';
                } else {
                    throw $ex;
                }
            }
            if (!$errors) {
                audit('register', 'student', $sid, null, $uid);
                unset($_SESSION['old']);
                flash('success', 'Registration successful. Please log in. A coordinator will verify your details.');
                redirect('auth/login');
            }
        }
    }
    view('auth/register', [
        'title' => 'Student registration', 'errors' => $errors,
        'institutions' => institutions(), 'counties' => counties(), 'areas' => areas(),
    ]);
}
