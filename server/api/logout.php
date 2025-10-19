<?php
declare(strict_types=1);

require __DIR__ . '/common.php';

handle_options_preflight();

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    send_error(405, 'Method not allowed.');
}

start_session_from_request();
$_SESSION = [];
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

$cookieParams = session_get_cookie_params();
setcookie(
    session_name(),
    '',
    time() - 3600,
    $cookieParams['path'] ?? '/',
    $cookieParams['domain'] ?? '',
    $cookieParams['secure'] ?? false,
    true
);

send_json(200, ['message' => 'Signed out successfully.']);
