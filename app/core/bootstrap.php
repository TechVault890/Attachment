<?php
declare(strict_types=1);
define('APP_ROOT', dirname(__DIR__, 2));

require __DIR__ . '/database.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/csrf.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/router.php';

date_default_timezone_set('Africa/Nairobi');

set_exception_handler(function (Throwable $e): void {
    error_log((string)$e);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $e . "\n");
        exit(1);
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    try {
        $dev = (config()['app']['env'] ?? 'production') === 'development';
    } catch (Throwable $x) {
        $dev = false;
    }
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Something went wrong</title></head>';
    echo '<body style="font-family:system-ui,sans-serif;max-width:40rem;margin:3rem auto;padding:0 1rem">';
    echo '<h1>Something went wrong</h1><p>The page could not be completed. Please go back and try again, or contact the administrator if it keeps happening.</p>';
    if ($dev) {
        echo '<pre style="white-space:pre-wrap">' . htmlspecialchars((string)$e, ENT_QUOTES, 'UTF-8') . '</pre>';
    }
    echo '</body></html>';
});

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
