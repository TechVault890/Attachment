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
