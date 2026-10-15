<?php
function current_user(): ?array {
    static $cache = ['id' => null, 'row' => null];
    $uid = $_SESSION['uid'] ?? null;
    if (!$uid) {
        return null;
    }
    if ($cache['id'] !== $uid) {
        $st = db()->prepare('SELECT id, username, email, role, full_name, institution_id, ministry_id, is_active FROM users WHERE id = ?');
        $st->execute([$uid]);
        $row = $st->fetch() ?: null;
        $cache = ['id' => $uid, 'row' => $row];
    }
    if (!$cache['row'] || !$cache['row']['is_active']) {
        unset($_SESSION['uid']);
        return null;
    }
    return $cache['row'];
}
function login_user(array $u): void {
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$u['id']]);
}
function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
function require_login(): array {
    $u = current_user();
    if (!$u) {
        flash('warning', 'Please log in to continue.');
        redirect('auth/login');
    }
    return $u;
}
function require_role(string ...$roles): array {
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        view('errors/403', ['title' => 'Access denied']);
        exit;
    }
    return $u;
}
