<?php

declare(strict_types=1);

require_once __DIR__ . '/../server/helpers.php';

ensure_session();
if (is_authenticated()) {
    $user = get_authenticated_user();
    log_activity('logout', 'Administrator signed out', $user['email'] ?? 'admin');
}
clear_authenticated_user();

header('Location: /admin/login.php');
exit;
