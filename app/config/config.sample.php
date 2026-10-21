<?php
// Copy to config.php and edit. config.php is NOT committed to Git.
return [
    'app' => [
        'name'     => 'Government Internship/Attachment System',
        'base_url' => '',            // e.g. '' for php -S, '/internship-system/public' under XAMPP
        'env'      => 'development',
    ],
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'internship_db',
        'user'    => 'internship_user',   // XAMPP default: 'root'
        'pass'    => 'ChangeMe_2026!',    // XAMPP default: ''
        'charset' => 'utf8mb4',
    ],
    'placement' => [
        'auto_min_match' => 50,   // 50 = area of interest must match; 80 = area and county
    ],
    'uploads' => [
        'dir'      => dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads',
        'max_size' => 2 * 1024 * 1024,    // 2 MB
    ],
];
