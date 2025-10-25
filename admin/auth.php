<?php

declare(strict_types=1);

require_once __DIR__ . '/../server/helpers.php';

ensure_session();

$user = get_authenticated_user();
if ($user === null || ($user['role'] ?? '') !== 'admin') {
    header('Location: /admin/login.php');
    exit;
}

$CURRENT_USER = $user;
$CSRF_TOKEN = issue_csrf_token();
