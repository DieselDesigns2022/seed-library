<?php
return [
    'app' => [
        'name' => 'Seed Library',
        'base_url' => '',
        'session_name' => 'seed_library_session',
        'timezone' => 'America/Detroit',
        'debug' => false,
        // Public portfolio mode permits anonymous browsing of approved pages while
        // rejecting every write and all operational/administrative routes.
        'demo_read_only' => false,
        // Set true in production when HTTPS terminates at a reverse proxy.
        // This forces Secure session cookies without trusting request headers.
        'force_https' => false,
        // Optional absolute writable directory tried before storage/imports.
        // If both are unusable, the app tries a seed-library directory under the system temp path.
        // 'imports_path' => '/var/lib/seed-library/imports',
        // Required by scripts/database_backup.php; keep outside the repository public/ directory.
        'backup_path' => '/var/backups/seed-library',
    ],
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'seed_library',
        'username' => 'seed_library_user',
        'password' => 'change-me',
        'charset' => 'utf8mb4',
    ],
];
