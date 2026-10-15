<?php
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}
function csrf_verify(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $t = $_POST['_csrf'] ?? '';
        if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
            http_response_code(419);
            exit('Invalid or expired form. Go back, refresh the page and try again.');
        }
    }
}
