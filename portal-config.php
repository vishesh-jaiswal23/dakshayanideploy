<?php

declare(strict_types=1);

const PORTAL_TIMEZONE = 'Asia/Kolkata';
const PORTAL_ADMIN_EMAIL = 'd.entranchi@gmail.com';
const PORTAL_ADMIN_PASSWORD_HASH = '$2y$12$P60S2OQ/W/h453B6A6hROuX96Ec3wVBQ8hG7Dx.G9QOkSV8D/hob.';

const PORTAL_DASHBOARD_ROUTES = [
    'admin' => '/admin/index.php',
    'installer' => '/installer/index.php',
    'customer' => '/customer/index.php',
    'referrer' => '/referrer/index.php',
    'employee' => '/employee/index.php',
];

const PORTAL_ROLE_LABELS = [
    'admin' => 'Admin',
    'installer' => 'Installer',
    'customer' => 'Customer',
    'referrer' => 'Referral partner',
    'employee' => 'Employee',
];
