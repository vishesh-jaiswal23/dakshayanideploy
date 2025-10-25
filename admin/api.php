<?php

declare(strict_types=1);

require_once __DIR__ . '/../server/helpers.php';

ensure_session();
server_bootstrap();

$user = get_authenticated_user();
if (!$user || ($user['role'] ?? '') !== 'admin') {
    respond_json(['status' => 'error', 'message' => 'Authentication required.'], 401);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    require_csrf_token();
} else {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!rate_limit_get('api:' . $ip . ':' . $action, 90, 300)) {
        respond_json(['status' => 'error', 'message' => 'Too many requests. Please slow down.'], 429);
    }
}

$input = [];
if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $input = $decoded;
        }
    }
}

try {
    switch ($action) {
        case 'get_site_settings':
            $settings = load_site_settings();
            respond_json(['status' => 'ok', 'settings' => $settings, 'csrf' => issue_csrf_token()]);
            break;

        case 'save_site_settings':
            $section = (string) ($input['section'] ?? '');
            $data = $input['data'] ?? null;
            if ($section === '' || !is_array($data)) {
                respond_json(['status' => 'error', 'message' => 'Invalid payload for site settings.'], 422);
            }
            $current = load_site_settings();
            if (!isset($current[$section])) {
                respond_json(['status' => 'error', 'message' => 'Unknown settings section.'], 404);
            }
            $current[$section] = array_merge($current[$section], $data);
            if (!save_site_settings($current)) {
                throw new RuntimeException('Unable to persist updated settings.');
            }
            log_activity('settings.save', 'Updated settings section ' . $section, $user['email'] ?? 'admin');
            respond_json(['status' => 'ok']);
            break;

        case 'reset_site_settings':
            $section = (string) ($input['section'] ?? '');
            if ($section === '') {
                respond_json(['status' => 'error', 'message' => 'Section is required.'], 422);
            }
            if (!reset_site_settings_section($section)) {
                respond_json(['status' => 'error', 'message' => 'Unable to reset section or section missing.'], 404);
            }
            log_activity('settings.reset', 'Reset settings section ' . $section, $user['email'] ?? 'admin');
            respond_json(['status' => 'ok']);
            break;

        default:
            respond_json(['status' => 'error', 'message' => 'Unknown action.'], 400);
    }
} catch (Throwable $exception) {
    log_system_error('API exception: ' . $exception->getMessage(), [
        'action' => $action,
        'method' => $method,
    ]);
    respond_json(['status' => 'error', 'message' => 'Unexpected server error.'], 500);
}
