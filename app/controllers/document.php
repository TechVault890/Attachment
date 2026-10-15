<?php
function document_download(): void {
    $u = require_login();
    $id = (int)($_GET['id'] ?? 0);
    $st = db()->prepare('SELECT d.*, s.user_id AS owner_id, s.institution_id FROM documents d JOIN students s ON s.id = d.student_id WHERE d.id = ?');
    $st->execute([$id]);
    $d = $st->fetch();
    if (!$d) not_found();

    $allowed = match ($u['role']) {
        'student'     => (int)$d['owner_id'] === (int)$u['id'],
        'coordinator' => (int)$d['institution_id'] === (int)$u['institution_id'],
        'admin'       => true,
        default       => false,
    };
    if (!$allowed) {
        http_response_code(403);
        view('errors/403', ['title' => 'Access denied']);
        exit;
    }
    if (!preg_match('/^[a-f0-9]{32}\.(pdf|doc|docx)$/', $d['stored_name'])) not_found();
    $path = config()['uploads']['dir'] . DIRECTORY_SEPARATOR . $d['stored_name'];
    if (!is_file($path)) not_found();

    $ext = pathinfo($d['stored_name'], PATHINFO_EXTENSION);
    $clean = preg_replace('/[^A-Za-z0-9._ -]/', '_', $d['original_name']);
    header('Content-Type: ' . upload_rules()[$ext]['mime']);
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: attachment; filename="' . $clean . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}
