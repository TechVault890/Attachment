<?php
// Helper names use the "rpt_" prefix so they can never be reached as routes.
// Placement figures count only APPROVED placements (status <> 'pending').
function rpt_range(): array {
    $from = trim((string)($_GET['from'] ?? ''));
    $to = trim((string)($_GET['to'] ?? ''));
    $errors = [];
    if ($from !== '' && !valid_date($from)) { $errors[] = 'The start date is not valid.'; $from = ''; }
    if ($to !== '' && !valid_date($to)) { $errors[] = 'The end date is not valid.'; $to = ''; }
    if ($from !== '' && $to !== '' && $to < $from) {
        $errors[] = 'The end date must be on or after the start date.';
        $from = $to = '';
    }
    return ['from' => $from, 'to' => $to, 'errors' => $errors];
}
// Returns " AND col >= ? AND col < ?" fragments (col is always an internal constant) and appends params.
function rpt_range_sql(array $r, string $col, array &$params): string {
    $sql = '';
    if ($r['from'] !== '') { $sql .= " AND $col >= ?"; $params[] = $r['from'] . ' 00:00:00'; }
    if ($r['to'] !== '') { $sql .= " AND $col < DATE_ADD(?, INTERVAL 1 DAY)"; $params[] = $r['to']; }
    return $sql;
}
function rpt_by_county(array $r): array {
    $p = [];
    $sql = "SELECT c.name AS county, COUNT(p.id) AS placements
        FROM placements p JOIN vacancies v ON v.id = p.vacancy_id JOIN counties c ON c.id = v.county_id
        WHERE p.status <> 'pending'" . rpt_range_sql($r, 'p.confirmed_at', $p) . ' GROUP BY c.id, c.name ORDER BY placements DESC, c.name';
    $st = db()->prepare($sql);
    $st->execute($p);
    return $st->fetchAll();
}
function rpt_by_ministry(array $r): array {
    $p = [];
    $on = rpt_range_sql($r, 'p.confirmed_at', $p);
    $st = db()->prepare("SELECT m.name AS ministry, COUNT(p.id) AS placements
        FROM ministries m LEFT JOIN vacancies v ON v.ministry_id = m.id
        LEFT JOIN placements p ON p.vacancy_id = v.id AND p.status <> 'pending'$on
        GROUP BY m.id, m.name ORDER BY placements DESC, m.name");
    $st->execute($p);
    return $st->fetchAll();
}
function rpt_by_institution(array $r): array {
    $p = [];
    $on = rpt_range_sql($r, 'p.confirmed_at', $p);
    $st = db()->prepare("SELECT i.name AS institution,
        (SELECT COUNT(*) FROM students s WHERE s.institution_id = i.id) AS students,
        (SELECT COUNT(*) FROM students s WHERE s.institution_id = i.id AND s.verification_status = 'verified') AS verified,
        COUNT(p.id) AS placements
        FROM institutions i LEFT JOIN students st ON st.institution_id = i.id
        LEFT JOIN placements p ON p.student_id = st.id AND p.status <> 'pending'$on
        GROUP BY i.id, i.name ORDER BY placements DESC, i.name");
    $st->execute($p);
    return $st->fetchAll();
}
function rpt_slots(): array {
    $rows = db()->query("SELECT m.name AS ministry, COUNT(v.id) AS vacancies,
        COALESCE(SUM(v.slots_total), 0) AS total, COALESCE(SUM(v.slots_filled), 0) AS filled,
        COALESCE(SUM(CASE WHEN v.status = 'open' THEN v.slots_total - v.slots_filled ELSE 0 END), 0) AS open_slots
        FROM ministries m LEFT JOIN vacancies v ON v.ministry_id = m.id GROUP BY m.id, m.name ORDER BY m.name")->fetchAll();
    foreach ($rows as &$r) {
        foreach (['vacancies', 'total', 'filled', 'open_slots'] as $k) $r[$k] = (int)$r[$k];
        $r['rate'] = $r['total'] > 0 ? (int)round($r['filled'] * 100 / $r['total']) : 0;
    }
    unset($r);
    return $rows;
}
function rpt_pipeline(): array {
    $out = [
        'students'     => ['pending' => 0, 'verified' => 0, 'rejected' => 0],
        'applications' => ['applied' => 0, 'shortlisted' => 0, 'placed' => 0, 'unsuccessful' => 0, 'withdrawn' => 0],
        'vacancies'    => ['open' => 0, 'filled' => 0, 'closed' => 0],
    ];
    $map = ['students' => ['students', 'verification_status'], 'applications' => ['applications', 'status'], 'vacancies' => ['vacancies', 'status']];
    foreach ($map as $key => [$table, $col]) {
        foreach (db()->query("SELECT $col AS s, COUNT(*) AS n FROM $table GROUP BY $col")->fetchAll() as $r) {
            $out[$key][$r['s']] = (int)$r['n'];
        }
    }
    return $out;
}
function rpt_placement_rows(array $r): array {
    $p = [];
    $sql = "SELECT u.full_name, s.reg_no, i.name AS institution, s.course, m.name AS ministry, v.title, v.office_location,
        c.name AS county, p.start_date, p.end_date, p.supervisor_name, p.supervisor_phone, p.confirmed_at
        FROM placements p JOIN students s ON s.id = p.student_id JOIN users u ON u.id = s.user_id
        JOIN institutions i ON i.id = s.institution_id JOIN vacancies v ON v.id = p.vacancy_id
        JOIN ministries m ON m.id = v.ministry_id JOIN counties c ON c.id = v.county_id
        WHERE p.status <> 'pending'" . rpt_range_sql($r, 'p.confirmed_at', $p) . ' ORDER BY p.confirmed_at DESC, p.id DESC';
    $st = db()->prepare($sql);
    $st->execute($p);
    return $st->fetchAll();
}
