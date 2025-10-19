<?php
declare(strict_types=1);

require __DIR__ . '/common.php';

handle_options_preflight();
$actor = require_admin();

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path = trim((string) ($_GET['path'] ?? ''), '/');
$users = read_users();

if ($path === '') {
    if ($method === 'GET') {
        send_json(200, [
            'users' => prepare_users_for_response($users),
            'stats' => compute_user_stats($users),
            'refreshedAt' => gmdate('c'),
        ]);
    }

    if ($method === 'POST') {
        $body = read_request_json();
        $name = trim((string) ($body['name'] ?? ''));
        $email = normalise_email($body['email'] ?? '');
        $password = (string) ($body['password'] ?? '');
        $role = in_array($body['role'] ?? '', ROLE_OPTIONS, true) ? $body['role'] : 'referrer';
        $status = in_array($body['status'] ?? '', USER_STATUSES, true) ? $body['status'] : 'active';
        $phone = trim((string) ($body['phone'] ?? ''));
        $city = trim((string) ($body['city'] ?? ''));

        if ($name === '' || $email === '') {
            send_error(400, 'Name and email are required.');
        }
        if (!is_valid_password($password)) {
            send_error(400, 'Password must be at least 8 characters long.');
        }
        foreach ($users as $existing) {
            if (($existing['email'] ?? '') === $email) {
                send_error(409, 'An account with this email already exists.');
            }
        }

        $timestamp = gmdate('c');
        $user = [
            'id' => 'usr-' . bin2hex(random_bytes(8)),
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'city' => $city,
            'role' => $role,
            'status' => $status,
            'password' => create_password_record($password),
            'createdAt' => $timestamp,
            'updatedAt' => $timestamp,
            'passwordChangedAt' => $timestamp,
            'createdBy' => $actor['id'] ?? null,
            'superAdmin' => false,
        ];

        $users[] = $user;
        write_users($users);
        send_json(201, [
            'user' => sanitize_user($user),
            'stats' => compute_user_stats($users),
        ]);
    }

    send_error(405, 'Method not allowed.');
}

$segments = array_values(array_filter(explode('/', $path), 'strlen'));
$userId = urldecode($segments[0] ?? '');
$action = isset($segments[1]) ? urldecode($segments[1]) : null;

if ($userId === '') {
    send_error(404, 'User not found.');
}

$index = find_user_index($users, $userId);
if ($index === -1) {
    send_error(404, 'User not found.');
}

$target = $users[$index];

if ($action === null) {
    if ($method === 'GET') {
        send_json(200, ['user' => sanitize_user($target)]);
    }

    if ($method === 'PUT') {
        $body = read_request_json();
        $nextName = trim((string) ($body['name'] ?? $target['name'] ?? ''));
        $nextPhone = trim((string) ($body['phone'] ?? $target['phone'] ?? ''));
        $nextCity = trim((string) ($body['city'] ?? $target['city'] ?? ''));
        $nextRole = in_array($body['role'] ?? '', ROLE_OPTIONS, true) ? $body['role'] : ($target['role'] ?? 'referrer');
        $nextStatus = in_array($body['status'] ?? '', USER_STATUSES, true) ? $body['status'] : ($target['status'] ?? 'active');

        if (!empty($target['superAdmin']) && ($actor['id'] ?? '') !== ($target['id'] ?? '')) {
            send_error(403, 'Only the head admin can update this profile.');
        }

        $activeAdmins = count_active_admins($users);
        $targetIsActiveAdmin = ($target['role'] ?? '') === 'admin' && ($target['status'] ?? 'active') !== 'suspended';
        $demotingAdmin = $targetIsActiveAdmin && ($nextRole !== 'admin' || $nextStatus !== 'active');

        if ($demotingAdmin && $activeAdmins <= 1) {
            send_error(400, 'At least one active admin account must remain.');
        }

        if (($actor['id'] ?? '') === ($target['id'] ?? '') && $nextStatus !== 'active') {
            send_error(400, 'You cannot suspend your own admin account.');
        }

        if (!empty($target['superAdmin'])) {
            $nextRole = 'admin';
            $nextStatus = 'active';
        }

        $users[$index]['name'] = $nextName !== '' ? $nextName : ($target['name'] ?? '');
        $users[$index]['phone'] = $nextPhone;
        $users[$index]['city'] = $nextCity;
        $users[$index]['role'] = $nextRole;
        $users[$index]['status'] = $nextStatus;
        $users[$index]['updatedAt'] = gmdate('c');
        $users[$index]['updatedBy'] = $actor['id'] ?? null;

        write_users($users);
        send_json(200, [
            'user' => sanitize_user($users[$index]),
            'stats' => compute_user_stats($users),
        ]);
    }

    if ($method === 'DELETE') {
        if (($actor['id'] ?? '') === ($target['id'] ?? '')) {
            send_error(400, 'You cannot delete your own account.');
        }
        if (!empty($target['superAdmin'])) {
            send_error(403, 'The head admin profile cannot be removed.');
        }
        if (($target['role'] ?? '') === 'admin' && count_active_admins($users) <= 1) {
            send_error(400, 'At least one active admin account must remain.');
        }

        array_splice($users, $index, 1);
        write_users($users);
        send_json(200, ['stats' => compute_user_stats($users)]);
    }

    send_error(405, 'Method not allowed.');
}

if ($action === 'reset-password') {
    if ($method !== 'POST') {
        send_error(405, 'Method not allowed.');
    }
    if (!empty($target['superAdmin']) && ($actor['id'] ?? '') !== ($target['id'] ?? '')) {
        send_error(403, 'Only the head admin can reset this password.');
    }
    $body = read_request_json();
    $password = (string) ($body['password'] ?? '');
    if (!is_valid_password($password)) {
        send_error(400, 'Password must be at least 8 characters long.');
    }

    $users[$index]['password'] = create_password_record($password);
    $users[$index]['passwordChangedAt'] = gmdate('c');
    $users[$index]['updatedAt'] = $users[$index]['passwordChangedAt'];
    $users[$index]['updatedBy'] = $actor['id'] ?? null;
    write_users($users);

    send_json(200, ['user' => sanitize_user($users[$index])]);
}

send_error(404, 'Not found.');
