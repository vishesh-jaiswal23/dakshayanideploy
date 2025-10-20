<?php

declare(strict_types=1);

const PORTAL_DATA_FILE = __DIR__ . '/data/portal-state.json';

function portal_default_state(): array
{
    return [
        'last_updated' => date('c'),
        'site_settings' => [
            'company_focus' => 'Utility-scale and rooftop solar EPC projects, O&M, and financing assistance across Jharkhand and Bihar.',
            'primary_contact' => 'Deepak Entranchi',
            'support_email' => 'support@dakshayanienterprises.com',
            'support_phone' => '+91 62030 01452',
            'announcement' => 'Welcome to the live operations console. Track projects, team workload, and customer updates in real time.'
        ],
        'users' => [],
        'projects' => [],
        'tasks' => [],
        'activity_log' => []
    ];
}

function portal_load_state(): array
{
    if (!file_exists(PORTAL_DATA_FILE)) {
        return portal_default_state();
    }

    $json = file_get_contents(PORTAL_DATA_FILE);
    if ($json === false) {
        return portal_default_state();
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        return portal_default_state();
    }

    $default = portal_default_state();
    $state = array_merge($default, $data);

    foreach (['users', 'projects', 'tasks', 'activity_log'] as $key) {
        if (!isset($state[$key]) || !is_array($state[$key])) {
            $state[$key] = $default[$key];
        }
    }

    $state['users'] = array_map(static function (array $user): array {
        if (!isset($user['id']) || !is_string($user['id']) || $user['id'] === '') {
            $user['id'] = portal_generate_id('usr_');
        }

        if (!isset($user['email']) || !is_string($user['email'])) {
            $user['email'] = '';
        }

        if (!isset($user['role']) || !is_string($user['role'])) {
            $user['role'] = 'employee';
        }

        $user['password_hash'] = isset($user['password_hash']) && is_string($user['password_hash'])
            ? $user['password_hash']
            : '';

        if (isset($user['last_login']) && !is_string($user['last_login'])) {
            unset($user['last_login']);
        }

        return $user;
    }, $state['users']);

    $state['activity_log'] = array_values(array_filter($state['activity_log'], static function ($entry) {
        return is_array($entry) && isset($entry['event'], $entry['timestamp']);
    }));

    return $state;
}

function portal_save_state(array $state): bool
{
    $state['last_updated'] = date('c');
    $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        return false;
    }

    return (bool) file_put_contents(PORTAL_DATA_FILE, $json, LOCK_EX);
}

function portal_record_activity(array &$state, string $event, string $actor = 'System'): void
{
    $state['activity_log'] ??= [];

    try {
        $id = 'log_' . bin2hex(random_bytes(4));
    } catch (Exception $e) {
        $id = 'log_' . uniqid();
    }

    array_unshift($state['activity_log'], [
        'id' => $id,
        'event' => $event,
        'actor' => $actor,
        'timestamp' => date('c')
    ]);

    $state['activity_log'] = array_slice($state['activity_log'], 0, 50);
}

function portal_generate_id(string $prefix = 'id_'): string
{
    try {
        return $prefix . bin2hex(random_bytes(4));
    } catch (Exception $e) {
        return $prefix . uniqid();
    }
}
