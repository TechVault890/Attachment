<?php
function window_index(): void {
    require_role('admin');
    $today = date('Y-m-d');
    $rows = array_map(function ($r) use ($today) {
        $r['state'] = !$r['is_active'] ? 'inactive' : ($today < $r['opens_on'] ? 'upcoming' : ($today > $r['closes_on'] ? 'ended' : 'open'));
        return $r;
    }, db()->query('SELECT * FROM application_windows ORDER BY opens_on DESC, id DESC')->fetchAll());
    view('window/index', ['title' => 'Application windows', 'rows' => $rows]);
}

function window_save(): void {
    $u = require_role('admin');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('window/index');
    $name = trim((string)($_POST['name'] ?? ''));
    $o = trim((string)($_POST['opens_on'] ?? ''));
    $c = trim((string)($_POST['closes_on'] ?? ''));
    if (mb_strlen($name) < 3 || mb_strlen($name) > 100) { flash('danger', 'Name must be 3 to 100 characters.'); redirect('window/index'); }
    if (!valid_date($o) || !valid_date($c)) { flash('danger', 'Enter valid opening and closing dates.'); redirect('window/index'); }
    if ($c < $o) { flash('danger', 'The closing date must be on or after the opening date.'); redirect('window/index'); }
    db()->prepare('INSERT INTO application_windows (name, opens_on, closes_on, created_by) VALUES (?,?,?,?)')->execute([$name, $o, $c, $u['id']]);
    audit('create_window', 'application_window', (int)db()->lastInsertId(), "$name ($o to $c)");
    flash('success', 'Application window created.');
    redirect('window/index');
}

function window_toggle(): void {
    require_role('admin');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('window/index');
    $st = db()->prepare('SELECT * FROM application_windows WHERE id = ?');
    $st->execute([(int)($_POST['id'] ?? 0)]);
    $w = $st->fetch();
    if (!$w) not_found();
    $new = $w['is_active'] ? 0 : 1;
    db()->prepare('UPDATE application_windows SET is_active = ? WHERE id = ?')->execute([$new, $w['id']]);
    audit($new ? 'activate_window' : 'deactivate_window', 'application_window', (int)$w['id'], $w['name']);
    flash('success', 'Window ' . ($new ? 'activated.' : 'deactivated.'));
    redirect('window/index');
}
