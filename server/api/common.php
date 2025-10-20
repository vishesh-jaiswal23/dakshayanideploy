<?php
declare(strict_types=1);

function env_string(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false) {
        $value = null;
    }
    if ($value === null) {
        return $default;
    }
    $value = trim((string) $value);
    return $value === '' ? $default : $value;
}

function env_list(array $keys): array
{
    $results = [];
    foreach ($keys as $key) {
        $value = env_string($key);
        if ($value === null) {
            continue;
        }
        $parts = preg_split('/[,\s]+/', $value, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($parts as $part) {
            $item = trim($part);
            if ($item !== '' && !in_array($item, $results, true)) {
                $results[] = $item;
            }
        }
    }
    return $results;
}

const DATA_DIR = __DIR__ . '/../data';
const USERS_FILE = DATA_DIR . '/users.json';
const ROLE_OPTIONS = ['admin', 'customer', 'employee', 'installer', 'referrer'];
const USER_STATUSES = ['active', 'suspended'];
const PASSWORD_ITERATIONS = 100000;
const PASSWORD_LENGTH = 64;
const PASSWORD_ALGO = 'sha512';

if (!defined('JWT_SECRET')) {
    $jwtSecret = env_string('JWT_SECRET', 'change-me') ?? 'change-me';
    define('JWT_SECRET', $jwtSecret);
}

if (!defined('JWT_TTL_SECONDS')) {
    $jwtTtl = (int) (env_string('JWT_TTL', '21600') ?? '21600');
    if ($jwtTtl <= 0) {
        $jwtTtl = 21600;
    }
    define('JWT_TTL_SECONDS', $jwtTtl);
}

if (!defined('GOOGLE_RECAPTCHA_SECRET')) {
    $recaptchaSecret = env_string('GOOGLE_RECAPTCHA_SECRET');
    if ($recaptchaSecret === null) {
        $recaptchaSecret = env_string('RECAPTCHA_SECRET', '');
    }
    define('GOOGLE_RECAPTCHA_SECRET', $recaptchaSecret ?? '');
}

if (!defined('GOOGLE_CLIENT_IDS')) {
    $clientIds = env_list([
        'GOOGLE_CLIENT_ID',
        'GOOGLE_CLIENT_IDS',
        'DAKSHAYANI_GOOGLE_CLIENT_ID',
        'DAKSHAYANI_GOOGLE_CLIENT_IDS',
    ]);
    define('GOOGLE_CLIENT_IDS', $clientIds);
}

function respond_with_cors_headers(): void
{
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
}

function handle_options_preflight(): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        respond_with_cors_headers();
        http_response_code(204);
        header('Content-Length: 0');
        exit;
    }
}

function send_json(int $status, array $payload): void
{
    respond_with_cors_headers();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function send_error(int $status, string $message): void
{
    send_json($status, ['error' => $message]);
}

function read_request_json(): array
{
    $raw = file_get_contents('php://input') ?: '';
    if ($raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        send_error(400, 'Invalid JSON payload.');
    }
    return $decoded;
}

function ensure_data_directory(): void
{
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0755, true);
    }
}

function normalise_email(?string $value): string
{
    return strtolower(trim($value ?? ''));
}

function is_valid_password(?string $password): bool
{
    if (!is_string($password) || strlen($password) < 8) {
        return false;
    }
    return (bool) (preg_match('/[a-z]/', $password)
        && preg_match('/[A-Z]/', $password)
        && preg_match('/\d/', $password)
        && preg_match('/[^A-Za-z0-9]/', $password));
}

function create_password_record(string $password): array
{
    $salt = bin2hex(random_bytes(16));
    $hash = hash_pbkdf2(PASSWORD_ALGO, $password, $salt, PASSWORD_ITERATIONS, PASSWORD_LENGTH * 2);
    return [
        'salt' => $salt,
        'hash' => $hash,
        'iterations' => PASSWORD_ITERATIONS,
        'algorithm' => PASSWORD_ALGO,
    ];
}

function verify_password(string $password, array $record): bool
{
    if (!isset($record['salt'], $record['hash'], $record['iterations'], $record['algorithm'])) {
        return false;
    }
    $expected = $record['hash'];
    $candidate = hash_pbkdf2(
        (string) $record['algorithm'],
        $password,
        (string) $record['salt'],
        (int) $record['iterations'],
        strlen($expected)
    );
    return hash_equals($expected, $candidate);
}

function base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function create_jwt(array $claims, int $ttlSeconds = JWT_TTL_SECONDS): string
{
    $secret = (string) JWT_SECRET;
    if ($secret === '') {
        return '';
    }

    $issuedAt = time();
    $payload = array_merge([
        'iat' => $issuedAt,
        'exp' => $issuedAt + max($ttlSeconds, 60),
    ], $claims);

    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $segments = [
        base64url_encode(json_encode($header, JSON_UNESCAPED_SLASHES)),
        base64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES)),
    ];

    $signature = hash_hmac('sha256', implode('.', $segments), $secret, true);
    $segments[] = base64url_encode($signature);

    return implode('.', $segments);
}

function verify_recaptcha_token(?string $token, ?string $remoteIp = null): array
{
    $secret = (string) GOOGLE_RECAPTCHA_SECRET;
    $trimmedToken = trim((string) $token);

    if ($secret === '') {
        return [
            'success' => true,
            'skipped' => true,
        ];
    }

    if ($trimmedToken === '') {
        return [
            'success' => false,
            'skipped' => false,
            'error' => 'missing-input-response',
        ];
    }

    $payload = http_build_query([
        'secret' => $secret,
        'response' => $trimmedToken,
        'remoteip' => $remoteIp ?? '',
    ], '', '&');

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $payload,
            'timeout' => 6,
        ],
    ]);

    $response = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);
    if ($response === false) {
        return [
            'success' => false,
            'skipped' => false,
            'error' => 'request-failed',
        ];
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        return [
            'success' => false,
            'skipped' => false,
            'error' => 'invalid-response',
        ];
    }

    return [
        'success' => (bool) ($data['success'] ?? false),
        'score' => isset($data['score']) ? (float) $data['score'] : null,
        'action' => isset($data['action']) ? (string) $data['action'] : null,
        'errorCodes' => $data['error-codes'] ?? [],
        'skipped' => false,
    ];
}

function verify_google_id_token(string $token): array
{
    $trimmedToken = trim($token);
    if ($trimmedToken === '') {
        throw new RuntimeException('Missing Google ID token');
    }

    $allowedAudiences = GOOGLE_CLIENT_IDS;
    if (empty($allowedAudiences)) {
        throw new RuntimeException('Google Sign-In is not configured');
    }

    $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($trimmedToken);
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 6,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        throw new RuntimeException('Google token verification failed');
    }

    $profile = json_decode($response, true);
    if (!is_array($profile) || !isset($profile['aud'])) {
        throw new RuntimeException('Invalid Google token response');
    }

    $audience = (string) $profile['aud'];
    if (!in_array($audience, $allowedAudiences, true)) {
        throw new RuntimeException('Google token audience mismatch');
    }

    if (isset($profile['email_verified']) && !$profile['email_verified']) {
        throw new RuntimeException('Google email address is not verified');
    }

    return $profile;
}

function ensure_user_shape(array $user): array
{
    $email = normalise_email($user['email'] ?? '');
    $user['email'] = $email;
    if (!$email) {
        return $user;
    }
    if (empty($user['id'])) {
        $user['id'] = 'usr-' . bin2hex(random_bytes(8));
    }
    if (!in_array($user['role'] ?? '', ROLE_OPTIONS, true)) {
        $user['role'] = 'referrer';
    }
    $user['status'] = in_array($user['status'] ?? '', USER_STATUSES, true) ? $user['status'] : 'active';
    $user['provider'] = $user['provider'] ?? 'local';
    $user['marketingOptIn'] = !empty($user['marketingOptIn']);
    $user['superAdmin'] = !empty($user['superAdmin']) && $user['role'] === 'admin';
    $now = gmdate('c');
    $createdAt = $user['createdAt'] ?? $now;
    $user['createdAt'] = $createdAt;
    $user['updatedAt'] = $user['updatedAt'] ?? $createdAt;
    $user['passwordChangedAt'] = $user['passwordChangedAt'] ?? $user['updatedAt'];
    $user['lastLoginAt'] = $user['lastLoginAt'] ?? null;
    if (!isset($user['password']) || !is_array($user['password']) || empty($user['password']['salt'])) {
        $user['password'] = create_password_record('ChangeMe@123');
    }
    return $user;
}

function seed_users(array $existing): array
{
    $users = [];
    $byEmail = [];
    foreach ($existing as $candidate) {
        if (!is_array($candidate)) {
            continue;
        }
        $shaped = ensure_user_shape($candidate);
        if (!$shaped['email']) {
            continue;
        }
        $users[] = $shaped;
        $byEmail[$shaped['email']] = true;
    }

    $seedTimestamp = gmdate('c');
    $seeders = [
        'd.entranchi@gmail.com' => function () use ($seedTimestamp) {
            return [
                'id' => 'usr-head-admin',
                'name' => 'Vishesh Entranchi',
                'email' => 'd.entranchi@gmail.com',
                'phone' => '+91 70702 78178',
                'city' => 'Ranchi',
                'role' => 'admin',
                'status' => 'active',
                'superAdmin' => true,
                'password' => create_password_record('Dakshayani@2311'),
                'createdAt' => $seedTimestamp,
                'updatedAt' => $seedTimestamp,
                'passwordChangedAt' => $seedTimestamp,
            ];
        },
        'admin@dakshayani.in' => function () {
            $timestamp = gmdate('c');
            return [
                'id' => 'usr-admin-1',
                'name' => 'Dakshayani Admin',
                'email' => 'admin@dakshayani.in',
                'phone' => '+91 70000 00000',
                'city' => 'Ranchi',
                'role' => 'admin',
                'status' => 'active',
                'password' => create_password_record('Admin@123'),
                'createdAt' => $timestamp,
                'updatedAt' => $timestamp,
            ];
        },
        'customer@dakshayani.in' => function () {
            $timestamp = gmdate('c');
            return [
                'id' => 'usr-customer-1',
                'name' => 'Asha Verma',
                'email' => 'customer@dakshayani.in',
                'phone' => '+91 90000 00000',
                'city' => 'Jamshedpur',
                'role' => 'customer',
                'status' => 'active',
                'password' => create_password_record('Customer@123'),
                'createdAt' => $timestamp,
                'updatedAt' => $timestamp,
            ];
        },
        'employee@dakshayani.in' => function () {
            $timestamp = gmdate('c');
            return [
                'id' => 'usr-employee-1',
                'name' => 'Rohit Kumar',
                'email' => 'employee@dakshayani.in',
                'phone' => '+91 88000 00000',
                'city' => 'Bokaro',
                'role' => 'employee',
                'status' => 'active',
                'password' => create_password_record('Employee@123'),
                'createdAt' => $timestamp,
                'updatedAt' => $timestamp,
            ];
        },
        'installer@dakshayani.in' => function () {
            $timestamp = gmdate('c');
            return [
                'id' => 'usr-installer-1',
                'name' => 'Sunita Singh',
                'email' => 'installer@dakshayani.in',
                'phone' => '+91 86000 00000',
                'city' => 'Dhanbad',
                'role' => 'installer',
                'status' => 'active',
                'password' => create_password_record('Installer@123'),
                'createdAt' => $timestamp,
                'updatedAt' => $timestamp,
            ];
        },
        'referrer@dakshayani.in' => function () {
            $timestamp = gmdate('c');
            return [
                'id' => 'usr-referrer-1',
                'name' => 'Sanjay Patel',
                'email' => 'referrer@dakshayani.in',
                'phone' => '+91 94000 00000',
                'city' => 'Hazaribagh',
                'role' => 'referrer',
                'status' => 'active',
                'password' => create_password_record('Referrer@123'),
                'createdAt' => $timestamp,
                'updatedAt' => $timestamp,
            ];
        },
    ];

    foreach ($seeders as $email => $builder) {
        if (!isset($byEmail[$email])) {
            $seeded = ensure_user_shape($builder());
            $users[] = $seeded;
            $byEmail[$seeded['email']] = true;
        }
    }

    return array_values($users);
}

function read_users(): array
{
    ensure_data_directory();
    if (!file_exists(USERS_FILE)) {
        $seeded = seed_users([]);
        write_users($seeded);
        return $seeded;
    }
    $raw = file_get_contents(USERS_FILE);
    if ($raw === false || trim($raw) === '') {
        $seeded = seed_users([]);
        write_users($seeded);
        return $seeded;
    }
    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        $decoded = [];
    }
    $seeded = seed_users($decoded);
    if (count($seeded) !== count($decoded)) {
        write_users($seeded);
    }
    return $seeded;
}

function write_users(array $users): void
{
    ensure_data_directory();
    $json = json_encode(array_values($users), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $tempFile = USERS_FILE . '.tmp';
    file_put_contents($tempFile, $json, LOCK_EX);
    rename($tempFile, USERS_FILE);
}

function sanitize_user(?array $user): ?array
{
    if (!$user) {
        return null;
    }
    $safe = $user;
    unset($safe['password'], $safe['signupSecret'], $safe['secretCode']);
    return $safe;
}

function compute_user_stats(array $users): array
{
    $stats = ['total' => 0, 'roles' => []];
    foreach ($users as $user) {
        if (!is_array($user)) {
            continue;
        }
        $stats['total'] += 1;
        $role = in_array($user['role'] ?? '', ROLE_OPTIONS, true) ? $user['role'] : 'other';
        $stats['roles'][$role] = ($stats['roles'][$role] ?? 0) + 1;
    }
    return $stats;
}

function prepare_users_for_response(array $users): array
{
    $sanitised = [];
    foreach ($users as $user) {
        $sanitised[] = sanitize_user($user);
    }
    usort($sanitised, function ($left, $right) {
        $leftTime = isset($left['createdAt']) ? strtotime((string) $left['createdAt']) : 0;
        $rightTime = isset($right['createdAt']) ? strtotime((string) $right['createdAt']) : 0;
        return $rightTime <=> $leftTime;
    });
    return $sanitised;
}

function count_active_admins(array $users): int
{
    $count = 0;
    foreach ($users as $user) {
        if (($user['role'] ?? '') === 'admin' && ($user['status'] ?? 'active') !== 'suspended') {
            $count++;
        }
    }
    return $count;
}

function get_authorization_token(): ?string
{
    $headers = [];
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    } else {
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$name] = $value;
            }
        }
    }
    foreach ($headers as $name => $value) {
        if (strtolower($name) === 'authorization') {
            if (preg_match('/Bearer\s+(.*)$/i', $value, $matches)) {
                return trim($matches[1]);
            }
        }
    }
    if (!empty($_GET['token']) && is_string($_GET['token'])) {
        return $_GET['token'];
    }
    return null;
}

function start_session_from_request(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $token = get_authorization_token();
    if (!$token && isset($_COOKIE[session_name()])) {
        $token = (string) $_COOKIE[session_name()];
    }
    if ($token) {
        @session_id($token);
    }
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

function current_user(): ?array
{
    start_session_from_request();
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        return null;
    }
    $users = read_users();
    foreach ($users as $user) {
        if (($user['id'] ?? null) === $userId) {
            return $user;
        }
    }
    return null;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        send_error(401, 'Unauthorised');
    }
    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if (($user['role'] ?? '') !== 'admin') {
        send_error(403, 'You are not allowed to manage users.');
    }
    return $user;
}

function issue_session_tokens(array $user): array
{
    start_session_from_request();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['last_seen'] = time();
    $jwt = create_jwt([
        'sub' => $user['id'],
        'email' => $user['email'] ?? '',
        'role' => $user['role'] ?? 'referrer',
    ]);
    return [
        'token' => session_id(),
        'jwt' => $jwt !== '' ? $jwt : null,
        'user' => sanitize_user($user),
    ];
}

function generate_secret_code(): string
{
    return (string) random_int(100000, 999999);
}

function build_dashboard_payload(array $user): array
{
    $base = [
        'user' => sanitize_user($user),
        'metrics' => [],
        'timeline' => [],
        'tasks' => [],
        'spotlight' => [
            'title' => 'Stay proactive',
            'message' => 'Monitor your daily priorities and reach out if you need adjustments to these workflows.',
        ],
    ];

    switch ($user['role'] ?? '') {
        case 'admin':
            $base['metrics'] = [
                ['label' => 'Active Users', 'value' => 128, 'helper' => '12 added this month'],
                ['label' => 'Open Tickets', 'value' => 9, 'helper' => '3 high priority'],
                ['label' => 'Projects in Flight', 'value' => 18, 'helper' => '5 nearing completion'],
            ];
            $base['timeline'] = [
                ['label' => 'Compliance audit', 'date' => '2024-10-15', 'status' => 'Scheduled'],
                ['label' => 'Quarterly board review', 'date' => '2024-10-22', 'status' => 'Planning'],
                ['label' => 'CRM data refresh', 'date' => '2024-10-28', 'status' => 'In Progress'],
            ];
            $base['tasks'] = [
                ['label' => 'Approve installer onboarding requests', 'status' => 'Pending'],
                ['label' => 'Review finance partner contracts', 'status' => 'In Review'],
                ['label' => 'Publish monthly KPI summary', 'status' => 'Due Friday'],
            ];
            $base['spotlight'] = [
                'title' => 'System health is stable',
                'message' => 'All services are operational. Keep an eye on overdue approvals to maintain SLAs.',
            ];
            break;
        case 'customer':
            $base['metrics'] = [
                ['label' => 'System Progress', 'value' => '80%', 'helper' => 'Net metering approval in progress'],
                ['label' => 'Energy Saved (kWh)', 'value' => 5120, 'helper' => 'This billing cycle'],
                ['label' => 'Projected Savings', 'value' => '₹14,200', 'helper' => 'Estimated this quarter'],
            ];
            $base['timeline'] = [
                ['label' => 'Site survey', 'date' => '2024-09-30', 'status' => 'Completed'],
                ['label' => 'Structural approval', 'date' => '2024-10-10', 'status' => 'Completed'],
                ['label' => 'Electrical inspection', 'date' => '2024-10-18', 'status' => 'Scheduled'],
            ];
            $base['tasks'] = [
                ['label' => 'Upload recent electricity bill', 'status' => 'Pending'],
                ['label' => 'Confirm access for installer team', 'status' => 'Scheduled'],
                ['label' => 'Review financing documents', 'status' => 'In Progress'],
            ];
            $base['spotlight'] = [
                'title' => 'You are almost live!',
                'message' => 'Once the inspection is cleared we will schedule commissioning within 72 hours.',
            ];
            break;
        case 'employee':
            $base['metrics'] = [
                ['label' => 'Assigned Tickets', 'value' => 24, 'helper' => '5 due today'],
                ['label' => 'Customer CSAT', 'value' => '4.7/5', 'helper' => 'Rolling 30-day score'],
                ['label' => 'Pending Escalations', 'value' => 2, 'helper' => 'Awaiting regional lead input'],
            ];
            $base['timeline'] = [
                ['label' => 'Installer coordination sync', 'date' => '2024-10-11 10:00', 'status' => 'Today'],
                ['label' => 'Customer success review', 'date' => '2024-10-13 15:30', 'status' => 'Scheduled'],
                ['label' => 'Knowledge base update', 'date' => '2024-10-19', 'status' => 'Drafting'],
            ];
            $base['tasks'] = [
                ['label' => 'Call customer #DE-2041 regarding inspection', 'status' => 'Pending'],
                ['label' => 'Update CRM notes for project JSR-118', 'status' => 'In Progress'],
                ['label' => 'Submit weekly activity summary', 'status' => 'Due Friday'],
            ];
            $base['spotlight'] = [
                'title' => 'Customer sentiment is strong',
                'message' => 'Maintain quick response times to keep our customer satisfaction above target.',
            ];
            break;
        case 'installer':
            $base['metrics'] = [
                ['label' => 'Jobs This Week', 'value' => 6, 'helper' => '2 require structural clearance'],
                ['label' => 'Avg. Completion Time', 'value' => '6.5 hrs', 'helper' => 'Across active jobs'],
                ['label' => 'Safety Checks', 'value' => '100%', 'helper' => 'All audits submitted'],
            ];
            $base['timeline'] = [
                ['label' => 'Ranchi - Verma Residence', 'date' => '2024-10-11 09:00', 'status' => 'Team A'],
                ['label' => 'Jamshedpur - Patel Industries', 'date' => '2024-10-12 14:00', 'status' => 'Team C'],
                ['label' => 'Bokaro - Singh Clinic', 'date' => '2024-10-14 08:30', 'status' => 'Team B'],
            ];
            $base['tasks'] = [
                ['label' => 'Upload as-built photos for Ranchi site', 'status' => 'Pending'],
                ['label' => 'Collect inverter serial numbers', 'status' => 'In Progress'],
                ['label' => 'Confirm material delivery for Dhanbad project', 'status' => 'Scheduled'],
            ];
            $base['spotlight'] = [
                'title' => 'All materials accounted for',
                'message' => 'Warehouse reports zero shortages. Coordinate closely with logistics for on-time starts.',
            ];
            break;
        case 'referrer':
            $base['metrics'] = [
                ['label' => 'Active Leads', 'value' => 14, 'helper' => '4 new this week'],
                ['label' => 'Conversion Rate', 'value' => '28%', 'helper' => 'Trailing 90 days'],
                ['label' => 'Rewards Earned', 'value' => '₹36,500', 'helper' => 'Awaiting next payout cycle'],
            ];
            $base['timeline'] = [
                ['label' => 'Lead #RF-882 follow-up', 'date' => '2024-10-11', 'status' => 'Call scheduled'],
                ['label' => 'Payout reconciliation', 'date' => '2024-10-15', 'status' => 'Processing'],
                ['label' => 'Referral webinar', 'date' => '2024-10-20 17:00', 'status' => 'Registration open'],
            ];
            $base['tasks'] = [
                ['label' => 'Share site photos for lead #RF-876', 'status' => 'Pending'],
                ['label' => 'Confirm bank details for rewards', 'status' => 'In Progress'],
                ['label' => 'Invite 3 new prospects this week', 'status' => 'Stretch goal'],
            ];
            $base['spotlight'] = [
                'title' => 'Keep nurturing warm leads',
                'message' => 'Timely follow-ups and detailed context boost conversions. Reach out if you need marketing collateral.',
            ];
            break;
        default:
            $base['metrics'] = [
                ['label' => 'Active Items', 'value' => 0, 'helper' => 'No data yet'],
            ];
            break;
    }

    return $base;
}

function respond_dashboard_for_role(string $role): void
{
    $user = require_login();
    if (($user['role'] ?? '') !== $role) {
        send_error(403, 'You are not allowed to view this dashboard.');
    }
    $data = build_dashboard_payload($user);
    send_json(200, $data);
}

function find_user_index(array $users, string $userId): int
{
    foreach ($users as $index => $user) {
        if (($user['id'] ?? '') === $userId) {
            return (int) $index;
        }
    }
    return -1;
}
