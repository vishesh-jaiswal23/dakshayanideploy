<?php
declare(strict_types=1);

require __DIR__ . '/common.php';

handle_options_preflight();

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    send_error(405, 'Method not allowed.');
}

$body = read_request_json();
$idToken = trim((string) ($body['idToken'] ?? $body['credential'] ?? ''));
$recaptchaToken = (string) ($body['recaptchaToken'] ?? '');

if ($idToken === '') {
    send_error(400, 'Missing Google credential.');
}

$recaptcha = verify_recaptcha_token($recaptchaToken, $_SERVER['REMOTE_ADDR'] ?? null);
if (!$recaptcha['success'] && empty($recaptcha['skipped'])) {
    send_error(400, 'reCAPTCHA validation failed. Please try again.');
}

try {
    $profile = verify_google_id_token($idToken);
} catch (Throwable $exception) {
    send_error(401, 'Unable to validate Google Sign-In. Please try again.');
}

$email = normalise_email($profile['email'] ?? '');
if ($email === '') {
    send_error(400, 'Google account does not include a verified email address.');
}

$users = read_users();
$userIndex = null;
foreach ($users as $index => $candidate) {
    if (($candidate['email'] ?? '') === $email) {
        $userIndex = $index;
        break;
    }
}

$timestamp = gmdate('c');
if ($userIndex === null) {
    $user = [
        'id' => 'usr-' . bin2hex(random_bytes(8)),
        'name' => (string) ($profile['name'] ?? $profile['given_name'] ?? 'Google User'),
        'email' => $email,
        'phone' => trim((string) ($profile['phone_number'] ?? '')),
        'city' => trim((string) ($profile['locale'] ?? '')),
        'role' => 'customer',
        'status' => 'active',
        'password' => create_password_record(bin2hex(random_bytes(16))),
        'provider' => 'google',
        'marketingOptIn' => false,
        'createdAt' => $timestamp,
        'updatedAt' => $timestamp,
        'passwordChangedAt' => $timestamp,
        'lastLoginAt' => $timestamp,
    ];
    $users[] = $user;
    $userIndex = array_key_last($users);
} else {
    $users[$userIndex]['updatedAt'] = $timestamp;
    $users[$userIndex]['lastLoginAt'] = $timestamp;
    $user = $users[$userIndex];
}

if (($users[$userIndex]['status'] ?? 'active') !== 'active') {
    send_error(403, 'This account is suspended. Contact the administrator.');
}

write_users($users);

$response = issue_session_tokens($users[$userIndex]);
send_json(200, $response);
