<?php
require_once __DIR__ . '/../models/vacancy.php';
require_once __DIR__ . '/../models/application.php';

function vacancy_browse(): void {
    $county = (int)($_GET['county'] ?? 0);
    $area = (string)($_GET['area'] ?? '');
    $min = (int)($_GET['ministry'] ?? 0);
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per = 10;

    $where = ["v.status = 'open'", 'v.slots_filled < v.slots_total', 'v.closes_on >= CURDATE()'];
    $p = [];
    if ($county > 0) { $where[] = 'v.county_id = ?'; $p[] = $county; }
    if ($area !== '' && in_array($area, areas(), true)) { $where[] = 'v.course_area = ?'; $p[] = $area; } else { $area = ''; }
    if ($min > 0) { $where[] = 'v.ministry_id = ?'; $p[] = $min; }
    $w = implode(' AND ', $where);

    $cnt = db()->prepare("SELECT COUNT(*) FROM vacancies v WHERE $w");
    $cnt->execute($p);
    $total = (int)$cnt->fetchColumn();
    $pages = max(1, (int)ceil($total / $per));
    $page = min($page, $pages);
    $off = ($page - 1) * $per;

    $st = db()->prepare("SELECT v.*, m.name AS ministry, c.name AS county
        FROM vacancies v JOIN ministries m ON m.id = v.ministry_id JOIN counties c ON c.id = v.county_id
        WHERE $w ORDER BY v.closes_on ASC, v.id DESC LIMIT $per OFFSET $off");
    $st->execute($p);

    view('vacancy/browse', [
        'title' => 'Vacancies', 'rows' => $st->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages,
        'county' => $county, 'area' => $area, 'min' => $min,
        'query' => array_filter(['county' => $county ?: null, 'area' => $area ?: null, 'ministry' => $min ?: null]),
        'counties' => counties(), 'areas' => areas(), 'ministries' => ministries(), 'win' => active_window(),
    ]);
}

function vacancy_view(): void {
    $v = get_vacancy((int)($_GET['id'] ?? 0));
    if (!$v) not_found();
    $u = current_user();
    $owner = $u && ($u['role'] === 'admin' || ($u['role'] === 'ministry' && (int)$u['ministry_id'] === (int)$v['ministry_id']));
    if ($v['status'] !== 'open' && !$owner) not_found();
    view('vacancy/view', ['title' => $v['title'], 'v' => $v, 'win' => active_window(), 'slots_left' => open_slots($v)]);
}

function vacancy_manage(): void {
    $u = require_role('ministry');
    $mid = own_ministry_id($u);
    $st = db()->prepare('SELECT v.*, c.name AS county FROM vacancies v JOIN counties c ON c.id = v.county_id
        WHERE v.ministry_id = ? ORDER BY v.created_at DESC, v.id DESC');
    $st->execute([$mid]);
    view('vacancy/manage', ['title' => 'My vacancies', 'rows' => $st->fetchAll()]);
}

function vacancy_create(): void {
    $u = require_role('ministry');
    $mid = own_ministry_id($u);
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $f = read_vacancy_form();
        $errors = validate_vacancy($f, 0, true);
        if (!$errors) {
            db()->prepare('INSERT INTO vacancies (ministry_id, posted_by, title, department, office_location, county_id, course_area, description, slots_total, duration_months, opens_on, closes_on)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$mid, $u['id'], $f['title'], $f['department'], $f['office_location'], (int)$f['county_id'],
                    $f['course_area'], $f['description'] !== '' ? $f['description'] : null, (int)$f['slots_total'],
                    (int)$f['duration_months'], $f['opens_on'], $f['closes_on']]);
            audit('create_vacancy', 'vacancy', (int)db()->lastInsertId(), $f['title']);
            flash('success', 'Vacancy posted.');
            redirect('vacancy/manage');
        }
        $_SESSION['old'] = $f;
    } elseif (!isset($_SESSION['old'])) {
        $_SESSION['old'] = ['opens_on' => date('Y-m-d'), 'closes_on' => date('Y-m-d', strtotime('+30 days')), 'duration_months' => '3'];
    }
    view('vacancy/form', ['title' => 'Post a vacancy', 'errors' => $errors, 'v' => null,
        'counties' => counties(), 'areas' => areas(), 'action' => url('vacancy/create')]);
}

function vacancy_edit(): void {
    $u = require_role('ministry');
    $mid = own_ministry_id($u);
    $v = get_vacancy((int)($_GET['id'] ?? 0));
    if (!$v || (int)$v['ministry_id'] !== $mid) not_found();
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $f = read_vacancy_form();
        $errors = validate_vacancy($f, (int)$v['slots_filled'], false);
        if (!$errors) {
            $status = $v['status'] === 'closed' ? 'closed' : (((int)$v['slots_filled'] >= (int)$f['slots_total']) ? 'filled' : 'open');
            db()->prepare('UPDATE vacancies SET title=?, department=?, office_location=?, county_id=?, course_area=?, description=?, slots_total=?, duration_months=?, opens_on=?, closes_on=?, status=? WHERE id=?')
                ->execute([$f['title'], $f['department'], $f['office_location'], (int)$f['county_id'], $f['course_area'],
                    $f['description'] !== '' ? $f['description'] : null, (int)$f['slots_total'], (int)$f['duration_months'],
                    $f['opens_on'], $f['closes_on'], $status, $v['id']]);
            audit('update_vacancy', 'vacancy', (int)$v['id'], $f['title']);
            flash('success', 'Vacancy updated.');
            redirect('vacancy/manage');
        }
        $_SESSION['old'] = $f;
    } elseif (!isset($_SESSION['old'])) {
        $_SESSION['old'] = [
            'title' => $v['title'], 'department' => $v['department'], 'office_location' => $v['office_location'],
            'county_id' => (string)$v['county_id'], 'course_area' => $v['course_area'], 'description' => (string)$v['description'],
            'slots_total' => (string)$v['slots_total'], 'duration_months' => (string)$v['duration_months'],
            'opens_on' => $v['opens_on'], 'closes_on' => $v['closes_on'],
        ];
    }
    view('vacancy/form', ['title' => 'Edit vacancy', 'errors' => $errors, 'v' => $v,
        'counties' => counties(), 'areas' => areas(), 'action' => url('vacancy/edit', ['id' => $v['id']])]);
}

function vacancy_status(): void {
    $u = require_role('ministry');
    $mid = own_ministry_id($u);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('vacancy/manage');
    $v = get_vacancy((int)($_POST['id'] ?? 0));
    if (!$v || (int)$v['ministry_id'] !== $mid) not_found();
    $to = (string)($_POST['to'] ?? '');
    if ($to === 'close') {
        db()->prepare("UPDATE vacancies SET status='closed' WHERE id=?")->execute([$v['id']]);
        audit('close_vacancy', 'vacancy', (int)$v['id']);
        flash('success', 'Vacancy closed.');
    } elseif ($to === 'reopen') {
        if (open_slots($v) < 1) {
            flash('danger', 'All slots are filled, so this vacancy cannot be reopened. Edit it to add slots first.');
        } elseif ($v['closes_on'] < date('Y-m-d')) {
            flash('danger', 'The closing date has passed. Edit the vacancy to extend it, then reopen.');
        } else {
            db()->prepare("UPDATE vacancies SET status='open' WHERE id=?")->execute([$v['id']]);
            audit('reopen_vacancy', 'vacancy', (int)$v['id']);
            flash('success', 'Vacancy reopened.');
        }
    } else {
        flash('danger', 'Unknown action.');
    }
    redirect('vacancy/manage');
}
