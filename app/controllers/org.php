<?php
function org_index(): void {
    require_role('admin');
    $inst = db()->query("SELECT i.*,
        (SELECT COUNT(*) FROM students s WHERE s.institution_id = i.id) AS students,
        (SELECT COUNT(*) FROM users u WHERE u.institution_id = i.id AND u.role = 'coordinator' AND u.is_active = 1) AS coordinators
        FROM institutions i ORDER BY i.name")->fetchAll();
    $mins = db()->query("SELECT m.*,
        (SELECT COUNT(*) FROM vacancies v WHERE v.ministry_id = m.id) AS vacancies,
        (SELECT COUNT(*) FROM users u WHERE u.ministry_id = m.id AND u.role = 'ministry' AND u.is_active = 1) AS officers
        FROM ministries m ORDER BY m.name")->fetchAll();
    view('org/index', ['title' => 'Organisations', 'institutions' => $inst, 'ministries' => $mins]);
}

function org_add(): void {
    require_role('admin');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('org/index');
    $type = (string)($_POST['type'] ?? '');
    $name = trim((string)($_POST['name'] ?? ''));
    $code = trim((string)($_POST['code'] ?? ''));
    if (!in_array($type, ['institution', 'ministry'], true)) { flash('danger', 'Unknown type.'); redirect('org/index'); }
    if (mb_strlen($name) < 3 || mb_strlen($name) > 150) { flash('danger', 'Name must be 3 to 150 characters.'); redirect('org/index'); }
    if (mb_strlen($code) > 20) { flash('danger', 'The short code must be 20 characters or fewer.'); redirect('org/index'); }
    $table = $type === 'institution' ? 'institutions' : 'ministries';
    $col = $type === 'institution' ? 'code' : 'abbreviation';
    try {
        db()->prepare("INSERT INTO $table (name, $col) VALUES (?, ?)")->execute([$name, $code !== '' ? $code : null]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') { flash('danger', 'An entry with that name already exists.'); redirect('org/index'); }
        throw $e;
    }
    audit('create_' . $type, $type, (int)db()->lastInsertId(), $name);
    flash('success', ucfirst($type) . ' added.');
    redirect('org/index');
}
