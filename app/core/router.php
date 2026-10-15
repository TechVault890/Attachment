<?php
function not_found(): never {
    http_response_code(404);
    view('errors/404', ['title' => 'Page not found']);
    exit;
}
function dispatch(): void {
    csrf_verify();
    $r = $_GET['r'] ?? 'home/index';
    if (!is_string($r) || !preg_match('/^([a-z]+)\/([a-z_]+)$/', $r, $m)) {
        not_found();
    }
    $file = APP_ROOT . '/app/controllers/' . $m[1] . '.php';
    $fn = $m[1] . '_' . $m[2];
    if (!is_file($file)) {
        not_found();
    }
    require_once $file;
    if (!function_exists($fn)) {
        not_found();
    }
    $fn();
}
