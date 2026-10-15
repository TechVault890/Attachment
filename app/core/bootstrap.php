<?php
declare(strict_types=1);
define('APP_ROOT', dirname(__DIR__, 2));

require __DIR__ . '/database.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/csrf.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/router.php';

date_default_timezone_set('Africa/Nairobi');

if (config()['app']['env'] === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

if (PHP_SAPI !== 'cli') {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => $https,
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_name('internship_sid');
    session_start();
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}
