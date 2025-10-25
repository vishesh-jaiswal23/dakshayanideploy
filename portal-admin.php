<?php

declare(strict_types=1);

/**
 * Shared helper functions for the Dakshayani admin dashboard.
 */

const PORTAL_DATA_DIRECTORY = __DIR__ . '/data';
const PORTAL_ACTIVITY_FILE = 'activity-log.json';
const PORTAL_SYSTEM_ERROR_FILE = 'system-errors.log';
const PORTAL_LOGIN_ATTEMPTS_FILE = 'login-attempts.json';

/**
 * Ensure the data directory exists and seed required files with defaults.
 */
function portal_admin_bootstrap_files(string $adminEmail, string $adminPasswordHash): void
{
    $directory = PORTAL_DATA_DIRECTORY;
    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    $defaults = [
        'users.json' => [
            [
                'id' => 'admin-root',
                'name' => 'Dakshayani Admin',
                'email' => $adminEmail,
                'role' => 'admin',
                'status' => 'active',
                'password_hash' => $adminPasswordHash,
                'force_reset' => false,
                'created_at' => date('c'),
                'updated_at' => date('c'),
                'last_login' => null,
            ],
        ],
        'approvals.json' => [],
        'customers.json' => [],
        'complaints.json' => [],
        'tasks.json' => [],
        'ledger.json' => [],
        'settings.json' => [
            'global' => [
                'phone' => '',
                'email' => '',
                'address' => '',
                'banner_text' => '',
            ],
            'homepage' => [
                'hero_text' => '',
                'highlight_offers' => '',
            ],
            'blog_defaults' => [
                'author' => '',
                'summary' => '',
            ],
            'case_studies' => [
                'summary' => '',
                'cta' => '',
            ],
            'testimonials' => [
                'headline' => '',
                'body' => '',
            ],
            'ai' => [
                'api_key' => null,
                'model' => 'gemini-1.5-flash',
            ],
            'complaints' => [
                'public_intake_enabled' => false,
            ],
        ],
        'blog-posts.json' => [],
        'case-studies.json' => [],
        'testimonials.json' => [],
        PORTAL_ACTIVITY_FILE => [],
        'ai-history.json' => [],
        PORTAL_LOGIN_ATTEMPTS_FILE => [],
    ];

    foreach ($defaults as $file => $default) {
        $path = portal_admin_data_path($file);
        if (!file_exists($path)) {
            if (is_array($default)) {
                portal_admin_save_json($file, $default);
            } else {
                file_put_contents($path, (string) $default);
            }
        }
    }

    $systemLog = portal_admin_data_path(PORTAL_SYSTEM_ERROR_FILE);
    if (!file_exists($systemLog)) {
        touch($systemLog);
    }
}

/**
 * Return the absolute path for a data file.
 */
function portal_admin_data_path(string $file): string
{
    return rtrim(PORTAL_DATA_DIRECTORY, '/\\') . '/' . $file;
}

/**
 * Read JSON data from disk.
 */
function portal_admin_load_json(string $file, $default = []): array
{
    $path = portal_admin_data_path($file);
    if (!is_file($path)) {
        if (is_array($default)) {
            portal_admin_save_json($file, $default);
        }
        return is_array($default) ? $default : [];
    }

    $handle = fopen($path, 'r');
    if ($handle === false) {
        portal_admin_log_error("Unable to open {$file} for reading.");
        return is_array($default) ? $default : [];
    }

    try {
        if (!flock($handle, LOCK_SH)) {
            portal_admin_log_error("Unable to acquire shared lock for {$file}.");
            return is_array($default) ? $default : [];
        }

        $contents = stream_get_contents($handle);
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }

    if ($contents === false || $contents === '') {
        return is_array($default) ? $default : [];
    }

    $decoded = json_decode($contents, true);
    if (!is_array($decoded)) {
        portal_admin_log_error("Invalid JSON in {$file}. Resetting to default.");
        if (is_array($default)) {
            portal_admin_save_json($file, $default);
        }
        return is_array($default) ? $default : [];
    }

    return $decoded;
}

/**
 * Persist JSON data to disk using atomic writes.
 */
function portal_admin_save_json(string $file, array $data): void
{
    $path = portal_admin_data_path($file);
    $directory = dirname($path);
    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        portal_admin_log_error("Failed to encode JSON for {$file}.");
        return;
    }

    $temp = tempnam($directory, 'portal');
    if ($temp === false) {
        portal_admin_log_error("Unable to create temporary file for {$file}.");
        return;
    }

    $bytes = file_put_contents($temp, $encoded . "\n", LOCK_EX);
    if ($bytes === false) {
        portal_admin_log_error("Failed to write temporary file for {$file}.");
        @unlink($temp);
        return;
    }

    if (!@rename($temp, $path)) {
        portal_admin_log_error("Failed to replace {$file} atomically.");
        @unlink($temp);
        return;
    }
}

/**
 * Append an entry to the activity log.
 */
function portal_admin_log_activity(string $action, string $details, string $actor): void
{
    $log = portal_admin_load_json(PORTAL_ACTIVITY_FILE, []);
    $log[] = [
        'timestamp' => date('c'),
        'user' => $actor,
        'action' => $action,
        'details' => $details,
    ];
    if (count($log) > 500) {
        $log = array_slice($log, -500);
    }
    portal_admin_save_json(PORTAL_ACTIVITY_FILE, $log);
}

/**
 * Record a system level error message.
 */
function portal_admin_log_error(string $message): void
{
    $path = portal_admin_data_path(PORTAL_SYSTEM_ERROR_FILE);
    $entry = sprintf("[%s] %s\n", date('c'), $message);
    file_put_contents($path, $entry, FILE_APPEND | LOCK_EX);
}

/**
 * Generate a unique identifier with a prefix.
 */
function portal_admin_generate_id(string $prefix): string
{
    try {
        $random = bin2hex(random_bytes(8));
    } catch (Exception $e) {
        $random = str_replace('.', '', uniqid('', true));
    }

    return strtolower($prefix . '-' . $random);
}

/**
 * Retrieve the failed login attempts map.
 */
function portal_admin_load_login_attempts(): array
{
    return portal_admin_load_json(PORTAL_LOGIN_ATTEMPTS_FILE, []);
}

/**
 * Persist the failed login attempts map.
 */
function portal_admin_save_login_attempts(array $attempts): void
{
    portal_admin_save_json(PORTAL_LOGIN_ATTEMPTS_FILE, $attempts);
}

/**
 * Register a failed login attempt.
 */
function portal_admin_register_failed_login(string $ip, int $limit, int $windowSeconds, int $blockSeconds): array
{
    $attempts = portal_admin_load_login_attempts();
    $now = time();

    if (!isset($attempts[$ip])) {
        $attempts[$ip] = [
            'count' => 0,
            'last_attempt' => 0,
            'blocked_until' => 0,
        ];
    }

    $record = $attempts[$ip];
    if ($record['blocked_until'] > $now) {
        return $record;
    }

    if ($record['last_attempt'] < $now - $windowSeconds) {
        $record['count'] = 0;
    }

    $record['count'] += 1;
    $record['last_attempt'] = $now;
    if ($record['count'] >= $limit) {
        $record['blocked_until'] = $now + $blockSeconds;
        $record['count'] = 0;
    }

    $attempts[$ip] = $record;
    portal_admin_save_login_attempts($attempts);

    return $record;
}

/**
 * Reset the failed login attempts for an IP.
 */
function portal_admin_reset_login_attempts(string $ip): void
{
    $attempts = portal_admin_load_login_attempts();
    if (isset($attempts[$ip])) {
        unset($attempts[$ip]);
        portal_admin_save_login_attempts($attempts);
    }
}

/**
 * Determine if an IP is currently blocked from logging in.
 */
function portal_admin_is_ip_blocked(string $ip): ?int
{
    $attempts = portal_admin_load_login_attempts();
    if (!isset($attempts[$ip])) {
        return null;
    }

    $blockedUntil = (int) ($attempts[$ip]['blocked_until'] ?? 0);
    return $blockedUntil > time() ? $blockedUntil : null;
}

/**
 * Escape output for safe HTML rendering.
 */
function portal_admin_e(string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Helper to fetch a field from an array safely.
 */
function portal_admin_array_get(array $data, string $key, $default = null)
{
    return $data[$key] ?? $default;
}
