<?php
function notification_index(): void {
    $u = require_login();
    $st = db()->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 50');
    $st->execute([$u['id']]);
    view('notification/index', ['title' => 'Notifications', 'rows' => $st->fetchAll()]);
}

function notification_read(): void {
    $u = require_login();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!empty($_POST['all'])) {
            db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$u['id']]);
        } else {
            db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')->execute([(int)($_POST['id'] ?? 0), $u['id']]);
        }
    }
    redirect('notification/index');
}
