<?php
function get_placement_detail(int $id): ?array {
    $st = db()->prepare('SELECT p.*, s.institution_id, s.reg_no, s.course, s.user_id AS student_user_id,
        u.full_name, u.phone, u.email, v.title, v.department, v.office_location, m.name AS ministry, c.name AS county
        FROM placements p JOIN students s ON s.id = p.student_id JOIN users u ON u.id = s.user_id
        JOIN vacancies v ON v.id = p.vacancy_id JOIN ministries m ON m.id = v.ministry_id
        JOIN counties c ON c.id = v.county_id WHERE p.id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}
function get_assessments(int $placementId): array {
    $st = db()->prepare('SELECT a.*, u.full_name AS coordinator FROM assessments a
        LEFT JOIN users u ON u.id = a.coordinator_id WHERE a.placement_id = ? ORDER BY a.assessed_on DESC, a.id DESC');
    $st->execute([$placementId]);
    return $st->fetchAll();
}
