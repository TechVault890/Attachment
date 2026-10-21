<?php
require_once __DIR__ . '/../models/application.php';

function dashboard_index(): void {
    $u = require_login();
    redirect('dashboard/' . $u['role']);
}

function dashboard_student(): void {
    $u = require_role('student');
    $st = db()->prepare('SELECT s.*, i.name AS institution, c.name AS county,
        (SELECT COUNT(*) FROM documents d WHERE d.student_id = s.id) AS doc_count
        FROM students s JOIN institutions i ON i.id = s.institution_id
        JOIN counties c ON c.id = s.preferred_county_id WHERE s.user_id = ?');
    $st->execute([$u['id']]);
    $s = $st->fetch();
    $apps = ['applied' => 0, 'shortlisted' => 0, 'placed' => 0, 'unsuccessful' => 0, 'withdrawn' => 0];
    $placement = null;
    if ($s) {
        $q = db()->prepare('SELECT status, COUNT(*) AS n FROM applications WHERE student_id = ? GROUP BY status');
        $q->execute([$s['id']]);
        foreach ($q->fetchAll() as $r) $apps[$r['status']] = (int)$r['n'];
        $placement = get_placement_for_student((int)$s['id']);
    }
    view('dashboard/student', ['title' => 'My dashboard', 'u' => $u, 's' => $s, 'apps' => $apps, 'placement' => $placement]);
}

function dashboard_coordinator(): void {
    $u = require_role('coordinator');
    $st = db()->prepare('SELECT verification_status AS status, COUNT(*) AS n FROM students WHERE institution_id = ? GROUP BY verification_status');
    $st->execute([$u['institution_id']]);
    $counts = ['pending' => 0, 'verified' => 0, 'rejected' => 0];
    foreach ($st->fetchAll() as $r) $counts[$r['status']] = (int)$r['n'];
    $q = db()->prepare('SELECT COUNT(*) FROM placements p JOIN students s ON s.id = p.student_id WHERE s.institution_id = ?');
    $q->execute([$u['institution_id']]);
    view('dashboard/coordinator', ['title' => 'Coordinator dashboard', 'u' => $u, 'counts' => $counts, 'placed' => (int)$q->fetchColumn()]);
}

function dashboard_ministry(): void {
    $u = require_role('ministry');
    $st = db()->prepare("SELECT COUNT(*) AS vacancies, COALESCE(SUM(slots_total - slots_filled),0) AS open_slots
        FROM vacancies WHERE ministry_id = ? AND status = 'open'");
    $st->execute([$u['ministry_id']]);
    $stats = $st->fetch();
    $q = db()->prepare("SELECT COUNT(*) FROM applications a JOIN vacancies v ON v.id = a.vacancy_id
        WHERE v.ministry_id = ? AND a.status IN ('applied','shortlisted')");
    $q->execute([$u['ministry_id']]);
    $stats['pending'] = (int)$q->fetchColumn();
    view('dashboard/ministry', ['title' => 'Ministry dashboard', 'u' => $u, 'stats' => $stats]);
}

function dashboard_admin(): void {
    $u = require_role('admin');
    $roles = ['student' => 0, 'coordinator' => 0, 'ministry' => 0, 'admin' => 0];
    foreach (db()->query('SELECT role, COUNT(*) AS n FROM users GROUP BY role')->fetchAll() as $r) $roles[$r['role']] = (int)$r['n'];
    $stats = [
        'vacancies'  => (int)db()->query('SELECT COUNT(*) FROM vacancies')->fetchColumn(),
        'placements' => (int)db()->query('SELECT COUNT(*) FROM placements')->fetchColumn(),
    ];
    view('dashboard/admin', ['title' => 'Admin dashboard', 'u' => $u, 'roles' => $roles, 'stats' => $stats]);
}
