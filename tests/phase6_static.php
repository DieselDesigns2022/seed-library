<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => (string)file_get_contents($root . '/' . $path);
$checks = [
    'secure cookie production override' => str_contains($read('app/bootstrap.php'), "config['app']['force_https']"),
    'baseline response security headers' => str_contains($read('app/bootstrap.php'), 'X-Content-Type-Options: nosniff'),
    'accessible skip target' => str_contains($read('app/view.php'), 'href="#main-content"') && str_contains($read('app/view.php'), 'id="main-content"'),
    'navigation toggle has accessible name' => str_contains($read('app/view.php'), 'aria-label="Open navigation"'),
    'login controls have explicit labels' => str_contains($read('public/index.php'), 'for="login-email"') && str_contains($read('public/index.php'), 'for="login-password"'),
    'private import permissions are enforced' => str_contains($read('app/import_export.php'), 'if(!chmod($target,0600))throw new RuntimeException()'),
    'replaced import upload is removed' => str_contains($read('app/import_export.php'), '$previous!==$target&&is_file($previous)'),
    'visible keyboard focus' => str_contains($read('public/assets/app.css'), ':focus-visible'),
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
if ($failed) {
    fwrite(STDERR, "Phase 6 static checks failed:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}
echo 'Phase 6 static checks passed (' . count($checks) . ").\n";
