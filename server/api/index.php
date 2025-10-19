<?php
declare(strict_types=1);

require __DIR__ . '/common.php';

handle_options_preflight();

$user = current_user();

send_json(200, [
    'service' => 'Dakshayani Portal PHP API',
    'authenticated' => $user !== null,
    'user' => sanitize_user($user),
]);
