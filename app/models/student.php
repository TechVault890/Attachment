<?php
function get_student_by_user(int $uid): ?array {
    $st = db()->prepare('SELECT s.*, u.full_name, u.email, u.phone, i.name AS institution, c.name AS county
        FROM students s JOIN users u ON u.id = s.user_id
        JOIN institutions i ON i.id = s.institution_id
        JOIN counties c ON c.id = s.preferred_county_id WHERE s.user_id = ?');
    $st->execute([$uid]);
    return $st->fetch() ?: null;
}
function get_student_by_id(int $id): ?array {
    $st = db()->prepare('SELECT s.*, u.full_name, u.email, u.phone, i.name AS institution, c.name AS county
        FROM students s JOIN users u ON u.id = s.user_id
        JOIN institutions i ON i.id = s.institution_id
        JOIN counties c ON c.id = s.preferred_county_id WHERE s.id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}
function get_documents(int $studentId): array {
    $st = db()->prepare('SELECT * FROM documents WHERE student_id = ?');
    $st->execute([$studentId]);
    $out = [];
    foreach ($st->fetchAll() as $d) {
        $out[$d['doc_type']] = $d;
    }
    return $out;
}
function format_size(int $b): string {
    return $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, (int)round($b / 1024)) . ' KB';
}
