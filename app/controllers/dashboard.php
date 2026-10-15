<?php
function dashboard_index(): void {
    $u = require_login();
    redirect('dashboard/' . $u['role']);
}

function dashboard_student(): void {
    $u = require_role('student');
    $st = db()->prepare('SELECT s.*, i.name AS institution, c.name AS county
        FROM students s JOIN institutions i ON i.id = s.institution_id
        JOIN counties c ON c.id = s.preferred_county_id WHERE s.user_id = ?');
    $st->execute([$u['id']]);
    view('dashboard/student', ['title' => 'My dashboard', 'u' => $u, 's' => $st->fetch()]);
}

function dashboard_coordinator(): void {
    $u = require_role('coordinator');
    $st = db()->prepare('SELECT verification_status AS status, COUNT(*) AS n FROM students WHERE institution_id = ? GROUP BY verification_status');
    $st->execute([$u['institution_id']]);
    $counts = ['pending' => 0, 'verified' => 0, 'rejected' => 0];
    foreach ($st->fetchAll() as $r) $counts[$r['status']] = (int)$r['n'];
    view('dashboard/coordinator', ['title' => 'Coordinator dashboard', 'u' => $u, 'counts' => $counts]);
}

function dashboard_ministry(): void {
    $u = require_role('ministry');
    $st = db()->prepare("SELECT COUNT(*) AS vacancies, COALESCE(SUM(slots_total - slots_filled),0) AS open_slots
        FROM vacancies WHERE ministry_id = ? AND status = 'open'");
    $st->execute([$u['ministry_id']]);
    view('dashboard/ministry', ['title' => 'Ministry dashboard', 'u' => $u, 'stats' => $st->fetch()]);
}

function dashboard_admin(): void {
    $u = require_role('admin');
    $roles = ['student' => 0, 'coordinator' => 0, 'ministry' => 0, 'admin' => 0];
    foreach (db()->query('SELECT role, COUNT(*) AS n FROM users GROUP BY role')->fetchAll() as $r) $roles[$r['role']] = (int)$r['n'];
    $stats = [
        'vacancies' => (int)db()->query('SELECT COUNT(*) FROM vacancies')->fetchColumn(),
        'placements' => (int)db()->query('SELECT COUNT(*) FROM placements')->fetchColumn(),
    ];
    view('dashboard/admin', ['title' => 'Admin dashboard', 'u' => $u, 'roles' => $roles, 'stats' => $stats]);
}
