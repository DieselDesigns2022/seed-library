<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$name = $argv[1] ?? null;
$email = $argv[2] ?? null;
$password = $argv[3] ?? null;
if (!$name || !$email || !$password) {
    fwrite(STDERR, "Usage: php scripts/create_admin.php \"Admin Name\" admin@example.com 'StrongPassword'\n");
    exit(1);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Invalid email address.\n");
    exit(1);
}
if (strlen($password) < 12) {
    fwrite(STDERR, "Password must be at least 12 characters.\n");
    exit(1);
}
$stmt = db()->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), password_hash=VALUES(password_hash)');
$stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
echo "Admin user ready: {$email}\n";
