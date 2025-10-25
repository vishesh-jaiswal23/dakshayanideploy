<?php

declare(strict_types=1);

const SERVER_BASE_PATH = __DIR__;
const DATA_PATH = SERVER_BASE_PATH . '/data';
const LOG_PATH = SERVER_BASE_PATH . '/logs';
const UPLOAD_PATH = SERVER_BASE_PATH . '/uploads';
const ACTIVITY_LOG_FILE = DATA_PATH . '/activity_log.json';
const SYSTEM_ERROR_FILE = LOG_PATH . '/system_errors.log';
const LOGIN_ATTEMPTS_FILE = DATA_PATH . '/login_attempts.json';
const RATE_LIMIT_FILE = DATA_PATH . '/rate_limits.json';
const SITE_SETTINGS_FILE = DATA_PATH . '/site_settings.json';

function server_bootstrap(): void
{
    static $booted = false;
    if ($booted) {
        return;
    }
    $booted = true;

    foreach ([DATA_PATH, LOG_PATH, UPLOAD_PATH] as $directory) {
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
    }

    $htaccess = "Deny from all\n";
    foreach ([DATA_PATH, LOG_PATH, UPLOAD_PATH] as $directory) {
        $htaccessPath = $directory . '/.htaccess';
        if (!file_exists($htaccessPath)) {
            file_put_contents($htaccessPath, $htaccess, LOCK_EX);
        }
    }

    $defaults = [
        ACTIVITY_LOG_FILE => [],
        LOGIN_ATTEMPTS_FILE => [],
        RATE_LIMIT_FILE => [],
        DATA_PATH . '/users.json' => [
            [
                'id' => 'admin-root',
                'name' => 'Dakshayani Admin',
                'email' => 'admin@dakshayani.in',
                'role' => 'admin',
                'status' => 'active',
                'password_hash' => password_hash('Dakshayani@123', PASSWORD_DEFAULT),
                'force_reset' => true,
                'created_at' => now_ist(),
                'updated_at' => now_ist(),
                'last_login' => null,
            ],
        ],
        DATA_PATH . '/customers.json' => [],
        DATA_PATH . '/leads.json' => [],
        DATA_PATH . '/tickets.json' => [],
        DATA_PATH . '/tasks.json' => [],
        DATA_PATH . '/approvals.json' => [],
        DATA_PATH . '/models.json' => [
            'default' => 'gpt-4o-mini',
            'available' => [
                'gpt-4o-mini',
                'gpt-4o',
                'o1-mini',
                'gemini-1.5-flash',
                'claude-3-5-sonnet',
            ],
        ],
        DATA_PATH . '/settings.json' => [],
        SITE_SETTINGS_FILE => default_site_settings(),
    ];

    foreach ($defaults as $path => $default) {
        if (str_ends_with($path, '.log')) {
            if (!file_exists($path)) {
                touch($path);
            }
            continue;
        }

        if (!file_exists($path)) {
            json_write($path, $default);
        }
    }

    if (!file_exists(SYSTEM_ERROR_FILE)) {
        touch(SYSTEM_ERROR_FILE);
    }
}

function default_site_settings(): array
{
    return [
        'Global' => [
            'company_name' => 'Dakshayani Enterprises',
            'support_email' => 'support@dakshayani.in',
            'support_phone' => '+91-00000-00000',
            'address' => 'Hyderabad, Telangana',
        ],
        'Homepage' => [
            'hero_title' => 'Transforming Clean Energy Projects',
            'hero_subtitle' => 'Integrated EPC, RESCO, and Solar Rooftop solutions',
            'cta_text' => 'Book a Consultation',
            'cta_link' => '#contact',
        ],
        'Blog Defaults' => [
            'default_author' => 'Dakshayani Team',
            'default_category' => 'Announcements',
            'reading_time' => '5 min',
        ],
        'Case Study Defaults' => [
            'default_industry' => 'Renewable Energy',
            'highlight_metric' => 'Energy Savings',
            'cta_label' => 'Start Your Project',
        ],
        'Testimonial Defaults' => [
            'headline' => 'Trusted by forward-looking organisations',
            'display_count' => 3,
        ],
        'Theme' => [
            'mode' => 'light',
            'primary_color' => '#2563eb',
            'accent_color' => '#f59e0b',
            'border_radius' => '0.75rem',
        ],
        'AI Settings' => [
            'provider' => 'OpenAI',
            'model' => 'gpt-4o-mini',
            'temperature' => 0.3,
            'system_prompt' => 'You are Dakshayani Enterprises concierge bot.',
        ],
    ];
}

function ensure_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

function now_ist(): string
{
    $tz = new DateTimeZone('Asia/Kolkata');
    $date = new DateTime('now', $tz);
    return $date->format(DateTime::ATOM);
}

function uuid(string $prefix = 'id'): string
{
    try {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    } catch (Throwable $exception) {
        $uuid = uniqid($prefix . '-', true);
    }

    return $prefix . '-' . $uuid;
}

function json_read(string $path, $default = [])
{
    if (!file_exists($path)) {
        return $default;
    }

    $handle = @fopen($path, 'r');
    if ($handle === false) {
        log_system_error('Unable to open file for reading: ' . $path);
        return $default;
    }

    try {
        if (!flock($handle, LOCK_SH)) {
            log_system_error('Unable to acquire shared lock for: ' . $path);
            return $default;
        }
        $contents = stream_get_contents($handle);
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }

    if ($contents === false || $contents === '') {
        return $default;
    }

    $decoded = json_decode($contents, true);
    if (json_last_error() !== JSON_ERROR_NONE || $decoded === null) {
        log_system_error('Invalid JSON encountered at: ' . $path);
        return $default;
    }

    return $decoded;
}

function json_write(string $path, $data): bool
{
    $directory = dirname($path);
    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        log_system_error('JSON encoding failed for: ' . $path);
        return false;
    }

    $handle = @fopen($path, 'c+');
    if ($handle === false) {
        log_system_error('Unable to open file for writing: ' . $path);
        return false;
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            log_system_error('Unable to acquire exclusive lock for: ' . $path);
            return false;
        }

        if (!rewind($handle)) {
            log_system_error('Unable to rewind file pointer for: ' . $path);
            flock($handle, LOCK_UN);
            return false;
        }

        if (!ftruncate($handle, 0)) {
            log_system_error('Unable to truncate file before writing: ' . $path);
            flock($handle, LOCK_UN);
            return false;
        }

        $bytes = fwrite($handle, $encoded . "\n");
        if ($bytes === false) {
            log_system_error('Unable to write JSON contents to: ' . $path);
            flock($handle, LOCK_UN);
            return false;
        }

        $position = ftell($handle);
        if ($position === false) {
            $position = strlen($encoded) + 1;
        }
        ftruncate($handle, $position);
        fflush($handle);
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }

    return true;
}

function log_activity(string $action, string $details, string $actor = 'system'): void
{
    $log = json_read(ACTIVITY_LOG_FILE, []);
    $log[] = [
        'id' => uuid('act'),
        'timestamp' => now_ist(),
        'action' => $action,
        'details' => $details,
        'actor' => $actor,
    ];

    if (count($log) > 500) {
        $log = array_slice($log, -500);
    }

    json_write(ACTIVITY_LOG_FILE, $log);
}

function log_system_error(string $message, array $context = []): void
{
    $line = sprintf('[%s] %s', now_ist(), $message);
    if (!empty($context)) {
        $line .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    $line .= "\n";
    file_put_contents(SYSTEM_ERROR_FILE, $line, FILE_APPEND | LOCK_EX);
}

function issue_csrf_token(): string
{
    ensure_session();
    if (empty($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Throwable $exception) {
            $fallback = null;
            if (function_exists('openssl_random_pseudo_bytes')) {
                $fallback = openssl_random_pseudo_bytes(16);
            }
            if ($fallback === false || $fallback === null) {
                $fallback = random_int(0, PHP_INT_MAX) . microtime(true);
            }
            $_SESSION['csrf_token'] = bin2hex(hash('sha256', (string) $fallback, true));
        }
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $token): bool
{
    ensure_session();
    if (empty($_SESSION['csrf_token']) || !is_string($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function require_csrf_token(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? null);
    if (!verify_csrf_token(is_string($token) ? $token : null)) {
        http_response_code(419);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token.']);
        exit;
    }
}

function validator_email(string $value): bool
{
    return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
}

function validator_string($value, int $maxLength = 255, bool $allowEmpty = false): bool
{
    if (!is_string($value)) {
        return false;
    }
    $trimmed = trim($value);
    if (!$allowEmpty && $trimmed === '') {
        return false;
    }
    return mb_strlen($trimmed) <= $maxLength;
}

function validator_boolean($value): bool
{
    return is_bool($value) || $value === 1 || $value === 0 || $value === '1' || $value === '0';
}

function get_authenticated_user(): ?array
{
    ensure_session();
    return $_SESSION['auth_user'] ?? null;
}

function set_authenticated_user(array $user): void
{
    ensure_session();
    $_SESSION['auth_user'] = [
        'id' => $user['id'] ?? null,
        'name' => $user['name'] ?? 'User',
        'email' => $user['email'] ?? null,
        'role' => $user['role'] ?? 'user',
    ];
    issue_csrf_token();
}

function clear_authenticated_user(): void
{
    ensure_session();
    unset($_SESSION['auth_user'], $_SESSION['csrf_token']);
    session_regenerate_id(true);
}

function is_authenticated(): bool
{
    return get_authenticated_user() !== null;
}

function load_site_settings(): array
{
    return json_read(SITE_SETTINGS_FILE, default_site_settings());
}

function save_site_settings(array $settings): bool
{
    return json_write(SITE_SETTINGS_FILE, $settings);
}

function reset_site_settings_section(string $section): bool
{
    $defaults = default_site_settings();
    $current = load_site_settings();
    if (!isset($defaults[$section])) {
        return false;
    }
    $current[$section] = $defaults[$section];
    return save_site_settings($current);
}

function track_login_attempt(string $key, int $limit, int $window, int $blockDuration): array
{
    $attempts = json_read(LOGIN_ATTEMPTS_FILE, []);
    $now = time();
    $record = $attempts[$key] ?? ['count' => 0, 'blocked_until' => 0, 'last_attempt' => 0];

    if ($record['blocked_until'] > $now) {
        return $record;
    }

    if ($record['last_attempt'] < $now - $window) {
        $record['count'] = 0;
    }

    $record['count']++;
    $record['last_attempt'] = $now;
    if ($record['count'] >= $limit) {
        $record['blocked_until'] = $now + $blockDuration;
        $record['count'] = 0;
    }

    $attempts[$key] = $record;
    json_write(LOGIN_ATTEMPTS_FILE, $attempts);

    return $record;
}

function reset_login_attempts(string $key): void
{
    $attempts = json_read(LOGIN_ATTEMPTS_FILE, []);
    if (isset($attempts[$key])) {
        unset($attempts[$key]);
        json_write(LOGIN_ATTEMPTS_FILE, $attempts);
    }
}

function rate_limit_get(string $key, int $limit = 120, int $windowSeconds = 300): bool
{
    $limits = json_read(RATE_LIMIT_FILE, []);
    $now = time();
    $record = $limits[$key] ?? ['count' => 0, 'reset' => $now + $windowSeconds];

    if ($record['reset'] < $now) {
        $record = ['count' => 0, 'reset' => $now + $windowSeconds];
    }

    if ($record['count'] >= $limit) {
        $record['count']++;
        $limits[$key] = $record;
        json_write(RATE_LIMIT_FILE, $limits);
        return false;
    }

    $record['count']++;
    $limits[$key] = $record;
    json_write(RATE_LIMIT_FILE, $limits);

    return true;
}

function respond_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

server_bootstrap();
