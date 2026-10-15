<?php
// Note: helper names avoid the "vacancy_" prefix so they can never be reached as routes.
function get_vacancy(int $id): ?array {
    $st = db()->prepare('SELECT v.*, m.name AS ministry, c.name AS county
        FROM vacancies v JOIN ministries m ON m.id = v.ministry_id
        JOIN counties c ON c.id = v.county_id WHERE v.id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}
function active_window(): ?array {
    $st = db()->query('SELECT * FROM application_windows WHERE is_active = 1
        AND CURDATE() BETWEEN opens_on AND closes_on ORDER BY closes_on DESC LIMIT 1');
    return $st->fetch() ?: null;
}
function open_slots(array $v): int {
    return max(0, (int)$v['slots_total'] - (int)$v['slots_filled']);
}
function read_vacancy_form(): array {
    $f = [];
    foreach (['title', 'department', 'office_location', 'county_id', 'course_area', 'description',
              'slots_total', 'duration_months', 'opens_on', 'closes_on'] as $k) {
        $f[$k] = trim((string)($_POST[$k] ?? ''));
    }
    return $f;
}
function validate_vacancy(array $f, int $filled, bool $isNew): array {
    $e = [];
    if (mb_strlen($f['title']) < 5 || mb_strlen($f['title']) > 150) $e[] = 'Title must be 5 to 150 characters.';
    if (mb_strlen($f['department']) < 2 || mb_strlen($f['department']) > 120) $e[] = 'Enter the department (2 to 120 characters).';
    if (mb_strlen($f['office_location']) < 3 || mb_strlen($f['office_location']) > 150) $e[] = 'Enter the office location (3 to 150 characters).';
    $c = db()->prepare('SELECT COUNT(*) FROM counties WHERE id = ?');
    $c->execute([(int)$f['county_id']]);
    if (!$c->fetchColumn()) $e[] = 'Choose a county.';
    if (!in_array($f['course_area'], areas(), true)) $e[] = 'Choose the course/area this vacancy is for.';
    if (mb_strlen($f['description']) > 2000) $e[] = 'Description must be 2000 characters or fewer.';
    if (!ctype_digit($f['slots_total']) || (int)$f['slots_total'] < 1 || (int)$f['slots_total'] > 500) {
        $e[] = 'Slots must be a whole number from 1 to 500.';
    } elseif ((int)$f['slots_total'] < $filled) {
        $e[] = "Slots cannot be fewer than the $filled already filled.";
    }
    if (!in_array($f['duration_months'], ['3', '6', '12'], true)) $e[] = 'Choose a duration of 3, 6 or 12 months.';
    if (!valid_date($f['opens_on']) || !valid_date($f['closes_on'])) {
        $e[] = 'Enter valid opening and closing dates.';
    } else {
        if ($f['closes_on'] < $f['opens_on']) $e[] = 'The closing date must be on or after the opening date.';
        if ($isNew && $f['closes_on'] < date('Y-m-d')) $e[] = 'The closing date cannot be in the past.';
    }
    return $e;
}
function own_ministry_id(array $u): int {
    if (empty($u['ministry_id'])) {
        http_response_code(403);
        view('errors/403', ['title' => 'Access denied']);
        exit;
    }
    return (int)$u['ministry_id'];
}
