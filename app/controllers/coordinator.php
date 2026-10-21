<?php
require_once __DIR__ . '/../models/student.php';

function coordinator_queue(): void {
    $u = require_role('coordinator');
    $filter = (string)($_GET['status'] ?? 'pending');
    if (!in_array($filter, ['pending', 'verified', 'rejected', 'all'], true)) $filter = 'pending';
    $sql = 'SELECT s.id, s.reg_no, s.course, s.area_of_interest, s.verification_status, u.full_name, c.name AS county,
            (SELECT COUNT(*) FROM documents d WHERE d.student_id = s.id) AS docs
            FROM students s JOIN users u ON u.id = s.user_id JOIN counties c ON c.id = s.preferred_county_id
            WHERE s.institution_id = ?';
    $params = [$u['institution_id']];
    if ($filter !== 'all') {
        $sql .= ' AND s.verification_status = ?';
        $params[] = $filter;
    }
    $sql .= ' ORDER BY s.created_at DESC';
    $st = db()->prepare($sql);
    $st->execute($params);
    view('coordinator/queue', ['title' => 'Verification queue', 'rows' => $st->fetchAll(), 'filter' => $filter]);
}

function coordinator_review(): void {
    $u = require_role('coordinator');
    $s = get_student_by_id((int)($_GET['id'] ?? 0));
    if (!$s || (int)$s['institution_id'] !== (int)$u['institution_id']) not_found();
    view('coordinator/review', ['title' => 'Review student', 's' => $s,
        'docs' => get_documents((int)$s['id']), 'types' => doc_types()]);
}

function coordinator_decide(): void {
    $u = require_role('coordinator');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('coordinator/queue');
    $s = get_student_by_id((int)($_POST['id'] ?? 0));
    if (!$s || (int)$s['institution_id'] !== (int)$u['institution_id']) not_found();
    $back = fn() => redirect('coordinator/review', ['id' => $s['id']]);
    $decision = (string)($_POST['decision'] ?? '');
    $reason = trim((string)($_POST['reason'] ?? ''));

    if ($decision === 'verify') {
        if (count(get_documents((int)$s['id'])) < 4) {
            flash('danger', 'All four documents must be uploaded before you can verify this student.');
            $back();
        }
        db()->prepare("UPDATE students SET verification_status='verified', verification_reason=NULL, verified_by=?, verified_at=NOW() WHERE id=?")
            ->execute([$u['id'], $s['id']]);
        notify((int)$s['user_id'], 'verification', 'You have been verified',
            'Your institution has verified your details. You can now apply for vacancies.', url('dashboard/index'));
        audit('verify_student', 'student', (int)$s['id']);
        flash('success', $s['full_name'] . ' has been verified.');
    } elseif ($decision === 'reject') {
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 255) {
            flash('danger', 'Give a reason for rejection (5 to 255 characters).');
            $back();
        }
        db()->prepare("UPDATE students SET verification_status='rejected', verification_reason=?, verified_by=?, verified_at=NOW() WHERE id=?")
            ->execute([$reason, $u['id'], $s['id']]);
        notify((int)$s['user_id'], 'verification', 'Verification unsuccessful',
            'Reason: ' . $reason . '. Update your profile or documents and you will be reviewed again.', url('student/profile'));
        audit('reject_student', 'student', (int)$s['id'], $reason);
        flash('success', $s['full_name'] . ' has been rejected with a reason.');
    } else {
        flash('danger', 'Unknown decision.');
        $back();
    }
    redirect('coordinator/queue');
}

require_once __DIR__ . '/../models/placement.php';

function coordinator_placed(): void {
    $u = require_role('coordinator');
    $county = (int)($_GET['county'] ?? 0);
    $sql = 'SELECT p.id AS placement_id, p.start_date, p.end_date, p.status AS pstatus,
        p.supervisor_name, p.supervisor_phone, p.supervisor_email,
        s.reg_no, s.course, u.full_name, u.phone, v.title, v.department, v.office_location,
        m.name AS ministry, c.name AS county,
        (SELECT COUNT(*) FROM assessments a WHERE a.placement_id = p.id) AS assess_count,
        (SELECT a.score FROM assessments a WHERE a.placement_id = p.id ORDER BY a.assessed_on DESC, a.id DESC LIMIT 1) AS last_score
        FROM placements p JOIN students s ON s.id = p.student_id JOIN users u ON u.id = s.user_id
        JOIN vacancies v ON v.id = p.vacancy_id JOIN ministries m ON m.id = v.ministry_id
        JOIN counties c ON c.id = v.county_id WHERE s.institution_id = ? AND p.status <> \'pending\'';
    $params = [$u['institution_id']];
    if ($county > 0) {
        $sql .= ' AND v.county_id = ?';
        $params[] = $county;
    }
    $sql .= ' ORDER BY c.name, m.name, u.full_name';
    $st = db()->prepare($sql);
    $st->execute($params);
    view('coordinator/placed', ['title' => 'Placed students', 'rows' => $st->fetchAll(),
        'county' => $county, 'counties' => counties()]);
}

function coordinator_assess(): void {
    $u = require_role('coordinator');
    $pl = get_placement_detail((int)($_GET['id'] ?? 0));
    if (!$pl || (int)$pl['institution_id'] !== (int)$u['institution_id'] || $pl['status'] === 'pending') not_found();
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $on = trim((string)($_POST['assessed_on'] ?? ''));
        $score = trim((string)($_POST['score'] ?? ''));
        $comments = trim((string)($_POST['comments'] ?? ''));
        if (!valid_date($on)) {
            $errors[] = 'Enter a valid assessment date.';
        } elseif ($on > date('Y-m-d')) {
            $errors[] = 'The assessment date cannot be in the future.';
        }
        if (!ctype_digit($score) || (int)$score > 100) $errors[] = 'Score must be a whole number from 0 to 100.';
        if (mb_strlen($comments) > 2000) $errors[] = 'Comments must be 2000 characters or fewer.';
        if (!$errors) {
            db()->prepare('INSERT INTO assessments (placement_id, coordinator_id, assessed_on, score, comments) VALUES (?,?,?,?,?)')
                ->execute([$pl['id'], $u['id'], $on, (int)$score, $comments !== '' ? $comments : null]);
            notify((int)$pl['student_user_id'], 'assessment', 'Assessment recorded',
                'Your coordinator recorded an attachment assessment dated ' . $on . '.', url('application/mine'));
            audit('record_assessment', 'placement', (int)$pl['id'], 'score ' . $score);
            flash('success', 'Assessment recorded.');
            redirect('coordinator/assess', ['id' => $pl['id']]);
        }
        $_SESSION['old'] = ['assessed_on' => $on, 'score' => $score, 'comments' => $comments];
    } else {
        $_SESSION['old'] = ['assessed_on' => date('Y-m-d')];
    }
    view('coordinator/assess', ['title' => 'Assessment', 'pl' => $pl, 'errors' => $errors, 'history' => get_assessments((int)$pl['id'])]);
}
