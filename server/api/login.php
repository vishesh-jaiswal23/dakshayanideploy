<?php
declare(strict_types=1);

require __DIR__ . '/common.php';

handle_options_preflight();

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    send_error(405, 'Method not allowed.');
}

$body = read_request_json();
$email = normalise_email($body['email'] ?? '');
$password = (string) ($body['password'] ?? '');

if ($email === '' || $password === '') {
    send_error(400, 'Email and password are required.');
}

$users = read_users();
$target = null;
foreach ($users as $index => $user) {
    if (($user['email'] ?? '') === $email) {
        $target = [$index, $user];
        break;
    }
}

if (!$target) {
    send_error(401, 'Invalid credentials. Check your email and password.');
}

[$userIndex, $user] = $target;
if (!verify_password($password, $user['password'] ?? [])) {
    send_error(401, 'Invalid credentials. Check your email and password.');
}

if (($user['status'] ?? 'active') !== 'active') {
    send_error(403, 'This account is suspended. Contact the administrator.');
}

$users[$userIndex]['lastLoginAt'] = gmdate('c');
$users[$userIndex]['updatedAt'] = $users[$userIndex]['lastLoginAt'];
write_users($users);

$response = issue_session_tokens($users[$userIndex]);
send_json(200, $response);
