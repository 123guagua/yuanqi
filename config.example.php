<?php
declare(strict_types=1);

/*
 * Copy this file to config.php and edit the values.
 * Never commit config.php or real database credentials to GitHub.
 */
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'YOUR_DATABASE_NAME',
        'user' => 'YOUR_DATABASE_USER',
        'pass' => 'CHANGE_THIS_DATABASE_PASSWORD',
        'charset' => 'utf8mb4',
    ],

    'app' => [
        'name' => 'SOCKS5 Proxy Directory',
        'timezone' => 'Asia/Shanghai',
        'items_per_page' => 20,
        'max_upload_bytes' => 2097152, // 2 MB
        'max_import_rows' => 5000,
        'admin_username' => 'admin',
        // Generate with password_hash('your-strong-password', PASSWORD_DEFAULT).
        'admin_password_hash' => 'REPLACE_WITH_PASSWORD_HASH',
    ],
];
