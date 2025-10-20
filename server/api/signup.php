<?php
declare(strict_types=1);

require __DIR__ . '/common.php';

handle_options_preflight();

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    send_error(405, 'Method not allowed.');
}

$allowSignup = env_bool('PORTAL_ALLOW_SELF_SIGNUP', false);
if (!$allowSignup) {
    send_error(403, 'Self-service signups are disabled. Please contact connect@dakshayani.co.in for access.');
}

$body = read_request_json();
$name = trim((string) ($body['name'] ?? ''));
$email = normalise_email($body['email'] ?? '');
$password = (string) ($body['password'] ?? '');
$role = (string) ($body['role'] ?? 'referrer');
$phone = trim((string) ($body['phone'] ?? ''));
$city = trim((string) ($body['city'] ?? ''));
$consent = filter_var($body['consent'] ?? false, FILTER_VALIDATE_BOOLEAN);
$marketing = filter_var($body['marketing'] ?? false, FILTER_VALIDATE_BOOLEAN);
$recaptchaToken = (string) ($body['recaptchaToken'] ?? '');

if ($name === '' || $email === '' || $password === '') {
    send_error(400, 'Name, email, and password are required.');
}

if (!$consent) {
    send_error(400, 'Please agree to the privacy and data consent terms.');
}

if (!is_valid_password($password)) {
    send_error(400, 'Password must be at least 8 characters and include uppercase, lowercase, number, and symbol.');
}

$recaptcha = verify_recaptcha_token($recaptchaToken, $_SERVER['REMOTE_ADDR'] ?? null);
if (!$recaptcha['success'] && empty($recaptcha['skipped'])) {
    send_error(400, 'reCAPTCHA validation failed. Please refresh and try again.');
}

$roleValue = in_array($role, ROLE_OPTIONS, true) ? $role : 'referrer';
$users = read_users();
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
    'role' => $roleValue,
    'status' => 'active',
    'password' => create_password_record($password),
    'provider' => 'local',
    'marketingOptIn' => $marketing,
    'createdAt' => $timestamp,
    'updatedAt' => $timestamp,
    'passwordChangedAt' => $timestamp,
];

$secretCode = null;
if ($roleValue !== 'admin') {
    $secretCode = generate_secret_code();
    $user['signupSecret'] = [
        'code' => $secretCode,
        'generatedAt' => $timestamp,
    ];
}

$users[] = $user;
write_users($users);

$response = issue_session_tokens($user);
if ($secretCode !== null) {
    $response['secretCode'] = $secretCode;
}

send_json(201, $response);
