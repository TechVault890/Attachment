<?php
require_once __DIR__ . '/../models/student.php';
require_once __DIR__ . '/../models/application.php';

function application_apply(): void {
    $u = require_role('student');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('application/mine');
    $vid = (int)($_POST['vacancy_id'] ?? 0);
    $v = get_vacancy($vid);
    if (!$v) not_found();
    $ap = apply_state($v, $u);
    if ($ap['state'] !== 'can_apply') {
        if ($v['status'] === 'open') redirect('vacancy/view', ['id' => $vid]);   // the page explains why
        flash('danger', $ap['msg'] ?? 'You cannot apply for this vacancy.');
        redirect('application/mine');
    }
    $s = $ap['student'];
    $score = $ap['match']['total'];
    $pdo = db();
    try {
        $q = $pdo->prepare('SELECT id FROM applications WHERE student_id = ? AND vacancy_id = ?');
        $q->execute([$s['id'], $vid]);
        $existing = $q->fetchColumn();   // only a withdrawn application can exist here
        if ($existing) {
            $pdo->prepare("UPDATE applications SET status='applied', match_score=?, decision_note=NULL, decided_by=NULL, decided_at=NULL, applied_at=NOW() WHERE id=?")
                ->execute([$score, $existing]);
            $appId = (int)$existing;
        } else {
            $pdo->prepare('INSERT INTO applications (student_id, vacancy_id, match_score) VALUES (?,?,?)')->execute([$s['id'], $vid, $score]);
            $appId = (int)$pdo->lastInsertId();
        }
    } catch (PDOException $dup) {
        if ($dup->getCode() === '23000') {
            flash('warning', 'You have already applied to this vacancy.');
            redirect('application/mine');
        }
        throw $dup;
    }
    $q = $pdo->prepare("SELECT id FROM users WHERE role = 'ministry' AND ministry_id = ? AND is_active = 1");
    $q->execute([$v['ministry_id']]);
    foreach ($q->fetchAll() as $m) {
        notify((int)$m['id'], 'application', 'New application',
            $u['full_name'] . ' applied for "' . $v['title'] . '" (match ' . $score . '%).',
            url('application/applicants', ['vacancy' => $vid]));
    }
    audit('apply', 'application', $appId, $v['title'] . ' (match ' . $score . '%)');
    flash('success', 'Application submitted. Track it below.');
    redirect('application/mine');
}

function application_mine(): void {
    $u = require_role('student');
    $s = get_student_by_user((int)$u['id']);
    if (!$s) not_found();
    $st = db()->prepare('SELECT a.*, v.title, v.office_location, v.duration_months, v.course_area, v.county_id, v.status AS vstatus,
        m.name AS ministry, c.name AS county, p.start_date, p.end_date, p.supervisor_name, p.supervisor_phone, p.supervisor_email
        FROM applications a JOIN vacancies v ON v.id = a.vacancy_id JOIN ministries m ON m.id = v.ministry_id
        JOIN counties c ON c.id = v.county_id LEFT JOIN placements p ON p.application_id = a.id
        WHERE a.student_id = ? ORDER BY a.applied_at DESC, a.id DESC');
    $st->execute([$s['id']]);
    $rows = [];
    foreach ($st->fetchAll() as $r) {
        $r['match'] = match_score($s, $r);
        $rows[] = $r;
    }
    view('application/mine', ['title' => 'My applications', 'rows' => $rows]);
}

function application_withdraw(): void {
    $u = require_role('student');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('application/mine');
    $q = db()->prepare('SELECT a.* FROM applications a JOIN students s ON s.id = a.student_id WHERE a.id = ? AND s.user_id = ?');
    $q->execute([(int)($_POST['id'] ?? 0), $u['id']]);
    $a = $q->fetch();
    if (!$a) not_found();
    if (!in_array($a['status'], ['applied', 'shortlisted'], true)) {
        flash('danger', 'Only active applications can be withdrawn.');
        redirect('application/mine');
    }
    db()->prepare("UPDATE applications SET status='withdrawn', decision_note='Withdrawn by student', decided_at=NOW() WHERE id=?")->execute([$a['id']]);
    audit('withdraw', 'application', (int)$a['id']);
    flash('success', 'Application withdrawn.');
    redirect('application/mine');
}

function application_matches(): void {
    $u = require_role('student');
    $s = get_student_by_user((int)$u['id']);
    if (!$s) not_found();
    view('application/matches', ['title' => 'Recommended vacancies', 's' => $s,
        'list' => suggested_vacancies($s, 10), 'verified' => $s['verification_status'] === 'verified']);
}

function application_applicants(): void {
    $u = require_role('ministry', 'admin');
    $v = get_vacancy((int)($_GET['vacancy'] ?? 0));
    if (!$v) not_found();
    if ($u['role'] === 'ministry' && (int)$u['ministry_id'] !== (int)$v['ministry_id']) not_found();
    $st = db()->prepare("SELECT a.id AS app_id, a.status, a.applied_at, a.decision_note,
        s.id AS student_id, s.reg_no, s.course, s.area_of_interest, s.preferred_county_id, s.period_months,
        u.full_name, u.email, u.phone, i.name AS institution, c.name AS pref_county
        FROM applications a JOIN students s ON s.id = a.student_id JOIN users u ON u.id = s.user_id
        JOIN institutions i ON i.id = s.institution_id JOIN counties c ON c.id = s.preferred_county_id
        WHERE a.vacancy_id = ?");
    $st->execute([$v['id']]);
    $rows = [];
    foreach ($st->fetchAll() as $r) {
        $r['match'] = match_breakdown($r, $v);
        $r['docs'] = get_documents((int)$r['student_id']);
        $rows[] = $r;
    }
    $group = ['placed' => 0, 'applied' => 1, 'shortlisted' => 1, 'unsuccessful' => 2, 'withdrawn' => 3];
    usort($rows, fn($a, $b) => [$group[$a['status']], $b['match']['total'], $a['applied_at'], $a['app_id']]
        <=> [$group[$b['status']], $a['match']['total'], $b['applied_at'], $b['app_id']]);
    $left = open_slots($v);
    $n = 0;
    foreach ($rows as &$r) {
        $r['suggested'] = false;
        if (in_array($r['status'], ['applied', 'shortlisted'], true) && $n < $left) {
            $r['suggested'] = true;
            $n++;
        }
    }
    unset($r);
    view('application/applicants', ['title' => 'Applicants', 'v' => $v, 'rows' => $rows, 'slots_left' => $left, 'types' => doc_types()]);
}

function application_decide(): void {
    $u = require_role('ministry', 'admin');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('dashboard/index');
    $q = db()->prepare('SELECT a.*, v.ministry_id, v.title FROM applications a JOIN vacancies v ON v.id = a.vacancy_id WHERE a.id = ?');
    $q->execute([(int)($_POST['id'] ?? 0)]);
    $a = $q->fetch();
    if (!$a) not_found();
    if ($u['role'] === 'ministry' && (int)$u['ministry_id'] !== (int)$a['ministry_id']) not_found();
    $vid = (int)$a['vacancy_id'];
    $back = fn() => redirect('application/applicants', ['vacancy' => $vid]);
    $action = (string)($_POST['action'] ?? '');
    $s = get_student_by_id((int)$a['student_id']);
    $vac = get_vacancy($vid);
    $active = in_array($a['status'], ['applied', 'shortlisted'], true);

    if ($action === 'shortlist') {
        if (!$active) { flash('danger', 'This application is no longer active.'); $back(); }
        if ($a['status'] === 'applied') {
            db()->prepare("UPDATE applications SET status='shortlisted', decided_by=?, decided_at=NOW() WHERE id=?")->execute([$u['id'], $a['id']]);
            notify((int)$s['user_id'], 'application', 'You have been shortlisted',
                'You have been shortlisted for "' . $a['title'] . '". The Ministry will confirm placements soon.', url('application/mine'));
            audit('shortlist', 'application', (int)$a['id']);
        }
        flash('success', $s['full_name'] . ' shortlisted.');
    } elseif ($action === 'reject') {
        $note = trim((string)($_POST['note'] ?? ''));
        if (!$active) { flash('danger', 'This application is no longer active.'); $back(); }
        if (mb_strlen($note) < 5 || mb_strlen($note) > 255) {
            flash('danger', 'Give a reason for rejection (5 to 255 characters). It is shown to the student.');
            $back();
        }
        db()->prepare("UPDATE applications SET status='unsuccessful', decision_note=?, decided_by=?, decided_at=NOW() WHERE id=?")->execute([$note, $u['id'], $a['id']]);
        notify((int)$s['user_id'], 'application', 'Application unsuccessful',
            'Your application for "' . $a['title'] . '" was not successful. Reason: ' . $note . '. ' . next_matches_text($s),
            url('application/matches'));
        audit('reject_application', 'application', (int)$a['id'], $note);
        flash('success', $s['full_name'] . ' notified that the application was unsuccessful.');
    } elseif ($action === 'place') {
        $d = [];
        foreach (['start_date', 'supervisor_name', 'supervisor_phone', 'supervisor_email'] as $k) $d[$k] = trim((string)($_POST[$k] ?? ''));
        $err = [];
        if ($d['start_date'] !== '' && (!valid_date($d['start_date']) || $d['start_date'] < date('Y-m-d'))) $err[] = 'Start date must be today or later.';
        if (mb_strlen($d['supervisor_name']) > 120) $err[] = 'Supervisor name is too long.';
        if ($d['supervisor_phone'] !== '' && !preg_match('/^[0-9+\s-]{7,20}$/', $d['supervisor_phone'])) $err[] = 'Enter a valid supervisor phone.';
        if ($d['supervisor_email'] !== '' && (!filter_var($d['supervisor_email'], FILTER_VALIDATE_EMAIL) || strlen($d['supervisor_email']) > 150)) $err[] = 'Enter a valid supervisor email.';
        if ($err) { flash('danger', implode(' ', $err)); $back(); }

        $res = confirm_placement((int)$a['id'], (int)$u['id'], $d);
        if (!$res['ok']) { flash('danger', $res['msg']); $back(); }

        $when = $d['start_date'] !== '' ? ' Start date: ' . $d['start_date'] . '.' : '';
        notify((int)$s['user_id'], 'placement', 'Placement confirmed',
            'You have been placed at ' . $vac['ministry'] . ' (' . $vac['office_location'] . ', ' . $vac['county'] . ') for "' . $vac['title'] . '".' . $when,
            url('application/mine'));
        notify_coordinators((int)$s['institution_id'], 'placement', 'Student placed',
            $s['full_name'] . ' (' . $s['reg_no'] . ') has been placed at ' . $vac['ministry'] . ', ' . $vac['county'] . '.', url('dashboard/index'));
        foreach ($res['losers'] as $sid) {
            $ls = get_student_by_id($sid);
            if ($ls) {
                notify((int)$ls['user_id'], 'application', 'Application unsuccessful',
                    'Your application for "' . $vac['title'] . '" was not successful because all slots have been filled. ' . next_matches_text($ls),
                    url('application/matches'));
            }
        }
        audit('place_student', 'application', (int)$a['id'], $vac['title']);
        flash('success', $s['full_name'] . ' placed.' . ($res['is_full'] ? ' All slots are now filled; other applicants were notified.' : ''));
    } else {
        flash('danger', 'Unknown action.');
    }
    $back();
}
