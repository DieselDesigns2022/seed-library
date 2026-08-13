<?php
declare(strict_types=1);

const BASE_PATH = __DIR__ . '/..';

$configPath = BASE_PATH . '/config.php';
if (!file_exists($configPath)) {
    $configPath = BASE_PATH . '/config.example.php';
}
$config = require $configPath;
date_default_timezone_set($config['app']['timezone'] ?? 'UTC');
ini_set('session.use_strict_mode', '1');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name($config['app']['session_name'] ?? 'seed_library_session');
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function config(string $key, mixed $default = null): mixed
{
    global $config;
    $parts = explode('.', $key);
    $value = $config;
    foreach ($parts as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $db = config('db');
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $db['host'], $db['port'], $db['database'], $db['charset']);
    $pdo = new PDO($dsn, $db['username'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    $base = rtrim((string)config('app.base_url', ''), '/');
    return $base . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function is_post(): bool
{
    return request_method() === 'POST';
}

function require_post(): void
{
    if (!is_post()) {
        http_response_code(405);
        header('Allow: POST');
        exit('Method Not Allowed');
    }
}

function app_debug(): bool
{
    return (bool)config('app.debug', false);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $user = null;
    if ($user === null) {
        $stmt = db()->prepare('SELECT id, name, email FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: false;
    }
    return $user ?: null;
}

function require_auth(): void
{
    if (!current_user()) {
        redirect('login');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function setting(string $key, string $default = ''): string
{
    $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : (string)$value;
}

function save_setting(string $key, string $value): void
{
    $stmt = db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->execute([$key, $value]);
}

function date_label(?int $month, ?int $day): string
{
    if (!$month || !$day) {
        return '';
    }
    return DateTime::createFromFormat('!m-d', sprintf('%02d-%02d', $month, $day))->format('M j');
}


function setting_date_label(string $key, string $default): string
{
    $parts = array_map('intval', explode('-', setting($key, $default)));
    return date_label($parts[0] ?? null, $parts[1] ?? null);
}

function month_name(int $month): string
{
    return DateTime::createFromFormat('!m', (string)$month)->format('F');
}

function plantable_in_month_sql(string $alias = 's'): string
{
    return "(FIND_IN_SET(?, {$alias}.plantable_months) > 0 OR (({$alias}.plantable_months IS NULL OR {$alias}.plantable_months = '') AND {$alias}.planting_start_month IS NOT NULL AND {$alias}.planting_end_month IS NOT NULL AND (({$alias}.planting_start_month <= {$alias}.planting_end_month AND ? BETWEEN {$alias}.planting_start_month AND {$alias}.planting_end_month) OR ({$alias}.planting_start_month > {$alias}.planting_end_month AND (? >= {$alias}.planting_start_month OR ? <= {$alias}.planting_end_month)))))";
}

function reference_data(): array
{
    return [
        'categories' => db()->query('SELECT * FROM categories ORDER BY name')->fetchAll(),
        'families' => db()->query('SELECT * FROM plant_families ORDER BY name')->fetchAll(),
        'uses' => db()->query('SELECT * FROM uses ORDER BY name')->fetchAll(),
        'statuses' => db()->query('SELECT * FROM statuses ORDER BY name')->fetchAll(),
    ];
}

function seed_columns(): array
{
    return ['seed_number','name','variety','category_id','plant_family_id','status_id','plant_type','planting_method','days_to_germination_min','days_to_germination_max','days_to_maturity','planting_start_month','planting_start_day','planting_end_month','planting_end_day','plantable_months','indoor_start_month','indoor_start_day','indoor_end_month','indoor_end_day','direct_sow_start_month','direct_sow_start_day','direct_sow_end_month','direct_sow_end_day','transplant_start_month','transplant_start_day','transplant_end_month','transplant_end_day','indoor_start_weeks_before_frost','transplant_weeks_after_frost','succession_days','sun_requirements','water_requirements','soil_requirements','spacing','sowing_depth','ideal_soil_temperature','row_spacing','thin_to_spacing','minimum_container_size','plant_height','container_friendly','pollinator_friendly','medicinal','perennial','frost_tolerant','heat_tolerant','drought_tolerant','trellis_needed','perennial_status','seed_source','packet_year','quantity','purchase_date','expiration_year','notes'];
}

function seed_boolean_columns(): array
{
    return ['container_friendly','pollinator_friendly','medicinal','perennial','frost_tolerant','heat_tolerant','drought_tolerant','trellis_needed'];
}

function seed_integer_columns(): array
{
    return ['category_id','plant_family_id','status_id','days_to_germination_min','days_to_germination_max','days_to_maturity','planting_start_month','planting_start_day','planting_end_month','planting_end_day','indoor_start_month','indoor_start_day','indoor_end_month','indoor_end_day','direct_sow_start_month','direct_sow_start_day','direct_sow_end_month','direct_sow_end_day','transplant_start_month','transplant_start_day','transplant_end_month','transplant_end_day','indoor_start_weeks_before_frost','transplant_weeks_after_frost','succession_days','packet_year','expiration_year'];
}


function valid_month_day(?int $month, ?int $day): bool
{
    if (!$month || !$day) {
        return false;
    }
    return checkdate($month, $day, 2000);
}

function valid_mmdd(string $value): bool
{
    if (!preg_match('/^\d{2}-\d{2}$/', $value)) {
        return false;
    }
    [$month, $day] = array_map('intval', explode('-', $value));
    return valid_month_day($month, $day);
}

function record_exists(string $table, int $id): bool
{
    $allowed = ['categories', 'plant_families', 'statuses', 'uses', 'seeds'];
    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException('Unsupported lookup table.');
    }
    $stmt = db()->prepare("SELECT COUNT(*) FROM {$table} WHERE id = ?");
    $stmt->execute([$id]);
    return (int)$stmt->fetchColumn() > 0;
}

function valid_date_string(?string $value): bool
{
    if ($value === null || $value === '') {
        return true;
    }
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date instanceof DateTime && $date->format('Y-m-d') === $value;
}

function bool_value(string $key): int
{
    return isset($_POST[$key]) ? 1 : 0;
}

function nullable_int(mixed $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    return (int)$value;
}

function log_history(?int $seedId, string $action, array $changes = []): void
{
    $stmt = db()->prepare('INSERT INTO seed_history (seed_id, user_id, action, changes_json) VALUES (?, ?, ?, ?)');
    $stmt->execute([$seedId, $_SESSION['user_id'] ?? null, $action, json_encode($changes, JSON_THROW_ON_ERROR)]);
}
