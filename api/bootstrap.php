<?php

declare(strict_types=1);

require_once __DIR__ . '/../portal-state.php';

const API_DATA_DIR = __DIR__ . '/../server/data';
const API_USERS_KEY = 'users';
const API_TICKETS_FILE = API_DATA_DIR . '/tickets.json';
const API_SEARCH_INDEX_FILE = API_DATA_DIR . '/search-index.json';
const API_KNOWLEDGE_FILE = API_DATA_DIR . '/knowledge-articles.json';
const API_TESTIMONIALS_FILE = API_DATA_DIR . '/testimonials.json';
const API_CASE_STUDIES_FILE = API_DATA_DIR . '/case-studies.json';

/**
 * Utility helpers
 */
function api_header(string $name, string $value): void
{
    header($name . ': ' . $value);
}

function api_send_json(int $status, array $payload): void
{
    http_response_code($status);
    api_header('Content-Type', 'application/json; charset=utf-8');
    api_header('Access-Control-Allow-Origin', '*');
    api_header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    api_header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function api_send_error(int $status, string $message): void
{
    api_send_json($status, ['error' => $message]);
}

function api_method_not_allowed(array $allowed): void
{
    api_header('Allow', implode(', ', $allowed));
    api_send_error(405, 'Method not allowed.');
}

function api_read_json_body(): array
{
    $raw = file_get_contents('php://input') ?: '';
    if ($raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        api_send_error(400, 'Invalid JSON payload.');
    }

    return $decoded;
}

function api_trim_string(?string $value, string $fallback = ''): string
{
    $trimmed = trim((string) $value);
    return $trimmed === '' ? $fallback : $trimmed;
}

function api_normalise_email(?string $email): string
{
    return strtolower(api_trim_string($email));
}

function api_is_valid_password(?string $password): bool
{
    if (!is_string($password) || strlen($password) < 8) {
        return false;
    }

    return (bool) (
        preg_match('/[a-z]/', $password) &&
        preg_match('/[A-Z]/', $password) &&
        preg_match('/\d/', $password) &&
        preg_match('/[^A-Za-z0-9]/', $password)
    );
}

function api_hash_password(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

function api_verify_password(string $password, ?string $hash): bool
{
    if (!$hash || !is_string($hash) || $hash === '') {
        return false;
    }

    return password_verify($password, $hash);
}

function api_load_state(): array
{
    return portal_load_state();
}

function api_save_state(array $state): void
{
    if (!portal_save_state($state)) {
        api_send_error(500, 'Failed to persist portal state.');
    }
}

function api_with_state(callable $callback): array
{
    $state = api_load_state();
    $updated = $callback($state);
    if (!is_array($updated)) {
        api_send_error(500, 'State mutation did not return an array.');
    }

    api_save_state($updated);

    return $updated;
}

function api_read_users(): array
{
    $state = api_load_state();
    $users = $state[API_USERS_KEY] ?? [];
    if (!is_array($users)) {
        return [];
    }

    return $users;
}

function api_write_users(array $users): void
{
    api_with_state(function (array $state) use ($users): array {
        $state[API_USERS_KEY] = array_map(static function ($user): array {
            if (!is_array($user)) {
                return [];
            }

            $user['id'] = api_trim_string($user['id'] ?? portal_generate_id('usr_'));
            $user['email'] = api_normalise_email($user['email'] ?? '');
            $user['name'] = api_trim_string($user['name'] ?? '');
            $user['role'] = api_trim_string($user['role'] ?? 'employee');
            $user['status'] = api_trim_string($user['status'] ?? 'active');
            $user['phone'] = api_trim_string($user['phone'] ?? '');
            $user['city'] = api_trim_string($user['city'] ?? '');
            $user['created_at'] = $user['created_at'] ?? date('c');
            $user['updated_at'] = $user['updated_at'] ?? $user['created_at'];
            if (!isset($user['password_hash'])) {
                $user['password_hash'] = '';
            }

            return $user;
        }, $users);

        return $state;
    });
}

function api_sanitise_user(array $user): array
{
    unset($user['password_hash']);
    return $user;
}

function api_prepare_users(array $users): array
{
    $prepared = [];
    foreach ($users as $user) {
        if (!is_array($user)) {
            continue;
        }
        $prepared[] = api_sanitise_user($user);
    }

    usort($prepared, static function (array $a, array $b): int {
        $left = strtotime($a['created_at'] ?? '');
        $right = strtotime($b['created_at'] ?? '');
        return $right <=> $left;
    });

    return $prepared;
}

function api_compute_user_stats(array $users): array
{
    $stats = ['total' => 0, 'roles' => []];
    foreach ($users as $user) {
        if (!is_array($user)) {
            continue;
        }
        $stats['total'] += 1;
        $role = $user['role'] ?? 'other';
        $stats['roles'][$role] = ($stats['roles'][$role] ?? 0) + 1;
    }

    return $stats;
}

function api_find_user_index(array $users, string $userId): int
{
    foreach ($users as $index => $user) {
        if (($user['id'] ?? null) === $userId) {
            return (int) $index;
        }
    }

    return -1;
}

function api_count_active_admins(array $users): int
{
    $count = 0;
    foreach ($users as $user) {
        if (!is_array($user)) {
            continue;
        }
        if (($user['role'] ?? '') === 'admin' && ($user['status'] ?? 'active') === 'active') {
            $count++;
        }
    }

    return $count;
}

function api_start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
        ]);
    }
}

function api_current_user(): ?array
{
    api_start_session();

    $role = $_SESSION['user_role'] ?? null;
    if (!$role) {
        return null;
    }

    $userId = $_SESSION['user_id'] ?? null;
    $userEmail = $_SESSION['user_email'] ?? '';
    $displayName = $_SESSION['display_name'] ?? 'Portal user';

    if ($role === 'admin' && !$userId) {
        $userId = 'admin-root';
    }

    if ($userId) {
        foreach (api_read_users() as $user) {
            if (($user['id'] ?? '') === $userId) {
                $merged = array_merge($user, [
                    'name' => $user['name'] ?? $displayName,
                    'email' => $user['email'] ?? $userEmail,
                    'role' => $role,
                    'status' => $user['status'] ?? 'active',
                ]);
                return api_sanitise_user($merged);
            }
        }
    }

    return [
        'id' => $userId ?? 'session-user',
        'name' => $displayName,
        'email' => $userEmail,
        'role' => $role,
        'status' => 'active',
        'superAdmin' => $role === 'admin',
    ];
}

function api_require_login(): array
{
    $user = api_current_user();
    if ($user === null) {
        api_send_error(401, 'Unauthorised');
    }

    return $user;
}

function api_require_admin(): array
{
    $user = api_require_login();
    if (($user['role'] ?? '') !== 'admin') {
        api_send_error(403, 'Administrator privileges required.');
    }

    return $user;
}

function api_dashboard_payload(array $user): array
{
    $payload = [
        'headline' => '',
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
            $payload['headline'] = 'Company operations overview';
            $payload['metrics'] = [
                ['label' => 'Active dashboards', 'value' => '5', 'helper' => 'All roles accessible'],
                ['label' => 'Pending tickets', 'value' => '9', 'helper' => '3 need escalation'],
                ['label' => 'Monthly revenue', 'value' => '₹42.5L', 'helper' => 'Updated hourly'],
            ];
            $payload['timeline'] = [
                ['label' => 'Board review', 'date' => '15 Oct 2024', 'status' => 'Scheduled'],
                ['label' => 'Compliance audit', 'date' => '22 Oct 2024', 'status' => 'Preparing'],
                ['label' => 'KPI newsletter', 'date' => '25 Oct 2024', 'status' => 'Drafting'],
            ];
            $payload['tasks'] = [
                ['label' => 'Approve installer onboarding requests', 'status' => 'Pending'],
                ['label' => 'Publish Q3 performance summary', 'status' => 'In progress'],
                ['label' => 'Review financing partner contract', 'status' => 'Due Friday'],
            ];
            break;
        case 'customer':
            $payload['headline'] = 'Your solar project status';
            $payload['metrics'] = [
                ['label' => 'Installation progress', 'value' => '80%', 'helper' => 'Net metering pending'],
                ['label' => 'Lifetime energy saved', 'value' => '5120 kWh', 'helper' => 'Updated daily'],
                ['label' => 'Projected savings', 'value' => '₹14,200', 'helper' => 'This quarter'],
            ];
            $payload['timeline'] = [
                ['label' => 'Site survey', 'date' => '30 Sep 2024', 'status' => 'Completed'],
                ['label' => 'Structural approval', 'date' => '10 Oct 2024', 'status' => 'Completed'],
                ['label' => 'Electrical inspection', 'date' => '18 Oct 2024', 'status' => 'Scheduled'],
            ];
            $payload['tasks'] = [
                ['label' => 'Upload latest electricity bill', 'status' => 'Pending'],
                ['label' => 'Confirm installer access window', 'status' => 'Scheduled'],
                ['label' => 'Review financing documents', 'status' => 'In progress'],
            ];
            break;
        case 'employee':
            $payload['headline'] = 'Customer success focus';
            $payload['metrics'] = [
                ['label' => 'Assigned tickets', 'value' => '24', 'helper' => '5 due today'],
                ['label' => 'Customer CSAT', 'value' => '4.7 / 5', 'helper' => 'Rolling 30 days'],
                ['label' => 'Pending escalations', 'value' => '2', 'helper' => 'Awaiting approval'],
            ];
            $payload['timeline'] = [
                ['label' => 'Installer sync', 'date' => '11 Oct 2024, 10:00', 'status' => 'Today'],
                ['label' => 'Success review', 'date' => '13 Oct 2024, 15:30', 'status' => 'Scheduled'],
                ['label' => 'Knowledge base update', 'date' => '19 Oct 2024', 'status' => 'Drafting'],
            ];
            $payload['tasks'] = [
                ['label' => 'Call customer DE-2041 about inspection', 'status' => 'Pending'],
                ['label' => 'Update CRM for project JSR-118', 'status' => 'In progress'],
                ['label' => 'Submit weekly activity summary', 'status' => 'Due Friday'],
            ];
            break;
        case 'installer':
            $payload['headline'] = 'Field execution overview';
            $payload['metrics'] = [
                ['label' => 'Jobs this week', 'value' => '6', 'helper' => '2 require structural clearance'],
                ['label' => 'Average completion time', 'value' => '6.5 hrs', 'helper' => 'Across active jobs'],
                ['label' => 'Safety checklist', 'value' => '100%', 'helper' => 'All audits passed'],
            ];
            $payload['timeline'] = [
                ['label' => 'Ranchi - Verma residence', 'date' => '11 Oct 2024, 09:00', 'status' => 'Team A'],
                ['label' => 'Jamshedpur - Patel industries', 'date' => '12 Oct 2024, 14:00', 'status' => 'Team C'],
                ['label' => 'Bokaro - Singh clinic', 'date' => '14 Oct 2024, 08:30', 'status' => 'Team B'],
            ];
            $payload['tasks'] = [
                ['label' => 'Upload as-built photos for Ranchi site', 'status' => 'Pending'],
                ['label' => 'Collect inverter serial numbers', 'status' => 'In progress'],
                ['label' => 'Confirm material delivery for Dhanbad project', 'status' => 'Scheduled'],
            ];
            break;
        case 'referrer':
            $payload['headline'] = 'Referral programme snapshot';
            $payload['metrics'] = [
                ['label' => 'Active leads', 'value' => '14', 'helper' => '4 new this week'],
                ['label' => 'Conversion rate', 'value' => '28%', 'helper' => 'Trailing 90 days'],
                ['label' => 'Rewards earned', 'value' => '₹36,500', 'helper' => 'Next payout on 20 Oct'],
            ];
            $payload['timeline'] = [
                ['label' => 'Lead RF-882 follow-up', 'date' => '11 Oct 2024', 'status' => 'Call scheduled'],
                ['label' => 'Payout reconciliation', 'date' => '15 Oct 2024', 'status' => 'Processing'],
                ['label' => 'Referral webinar', 'date' => '20 Oct 2024, 17:00', 'status' => 'Open for registration'],
            ];
            $payload['tasks'] = [
                ['label' => 'Share site photos for lead RF-876', 'status' => 'Pending'],
                ['label' => 'Confirm bank details for rewards', 'status' => 'In progress'],
                ['label' => 'Invite 3 new prospects', 'status' => 'Stretch goal'],
            ];
            break;
        default:
            $payload['headline'] = 'Portal overview';
            $payload['metrics'] = [
                ['label' => 'Active items', 'value' => '0', 'helper' => 'No data yet'],
            ];
    }

    return $payload;
}

function api_ensure_data_dir(): void
{
    if (!is_dir(API_DATA_DIR)) {
        mkdir(API_DATA_DIR, 0755, true);
    }
}

function api_read_json_file(string $path, $fallback)
{
    api_ensure_data_dir();
    if (!file_exists($path)) {
        return $fallback;
    }

    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        return $fallback;
    }

    $decoded = json_decode($raw, true);
    if ($decoded === null || json_last_error() !== JSON_ERROR_NONE) {
        return $fallback;
    }

    return $decoded;
}

function api_write_json_file(string $path, $data): void
{
    api_ensure_data_dir();
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    file_put_contents($path, $json, LOCK_EX);
}

function api_read_search_index(): array
{
    $index = api_read_json_file(API_SEARCH_INDEX_FILE, []);
    return is_array($index) ? $index : [];
}

function api_read_knowledge(): array
{
    $data = api_read_json_file(API_KNOWLEDGE_FILE, ['categories' => [], 'articles' => []]);
    return is_array($data) ? $data : ['categories' => [], 'articles' => []];
}

function api_read_testimonials(): array
{
    $items = api_read_json_file(API_TESTIMONIALS_FILE, []);
    return is_array($items) ? $items : [];
}

function api_read_case_studies(): array
{
    $items = api_read_json_file(API_CASE_STUDIES_FILE, []);
    return is_array($items) ? $items : [];
}

function api_read_tickets(): array
{
    $tickets = api_read_json_file(API_TICKETS_FILE, []);
    return is_array($tickets) ? $tickets : [];
}

function api_write_tickets(array $tickets): void
{
    api_write_json_file(API_TICKETS_FILE, array_values($tickets));
}

function api_slugify(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? '';
    return trim($slug, '-');
}

function api_read_blog_posts(): array
{
    $state = api_load_state();
    $posts = $state['blog_posts'] ?? [];
    if (!is_array($posts)) {
        return [];
    }

    return array_map(static function (array $post): array {
        $content = '';
        if (isset($post['content']) && is_array($post['content'])) {
            $content = implode("\n\n", $post['content']);
        } elseif (isset($post['content']) && is_string($post['content'])) {
            $content = $post['content'];
        }

        return [
            'id' => $post['id'] ?? portal_generate_id('blog_'),
            'title' => $post['title'] ?? 'Untitled update',
            'slug' => $post['slug'] ?? api_slugify($post['title'] ?? ''),
            'excerpt' => $post['excerpt'] ?? '',
            'heroImage' => $post['hero_image'] ?? '',
            'tags' => $post['tags'] ?? [],
            'readTimeMinutes' => $post['read_time_minutes'] ?? null,
            'status' => $post['status'] ?? 'draft',
            'content' => $content,
            'author' => $post['author'] ?? ['name' => 'Editorial', 'role' => 'Team'],
            'publishedAt' => $post['published_at'] ?? null,
            'updatedAt' => $post['updated_at'] ?? $post['published_at'] ?? null,
        ];
    }, $posts);
}

function api_write_blog_posts(array $posts): void
{
    api_with_state(static function (array $state) use ($posts): array {
        $state['blog_posts'] = array_map(static function ($post): array {
            if (!is_array($post)) {
                return [];
            }

            $content = $post['content'] ?? '';
            if (is_string($content)) {
                $paragraphs = array_filter(array_map('trim', preg_split("/\n{2,}/", $content) ?: []));
                $post['content'] = array_values($paragraphs);
            } elseif (is_array($content)) {
                $post['content'] = array_values(array_filter(array_map('trim', $content)));
            } else {
                $post['content'] = [];
            }

            $post['id'] = api_trim_string($post['id'] ?? portal_generate_id('blog_'));
            $post['title'] = api_trim_string($post['title'] ?? '');
            $post['slug'] = api_trim_string($post['slug'] ?? api_slugify($post['title']));
            $post['excerpt'] = api_trim_string($post['excerpt'] ?? '');
            $post['hero_image'] = api_trim_string($post['heroImage'] ?? $post['hero_image'] ?? '');
            $post['tags'] = isset($post['tags']) && is_array($post['tags'])
                ? array_values(array_filter(array_map('trim', $post['tags'])))
                : [];
            $post['read_time_minutes'] = isset($post['readTimeMinutes'])
                ? (int) $post['readTimeMinutes']
                : (isset($post['read_time_minutes']) ? (int) $post['read_time_minutes'] : null);
            $post['status'] = in_array($post['status'] ?? 'draft', ['draft', 'published'], true)
                ? $post['status']
                : 'draft';
            $post['author'] = isset($post['author']) && is_array($post['author'])
                ? [
                    'name' => api_trim_string($post['author']['name'] ?? ''),
                    'role' => api_trim_string($post['author']['role'] ?? ''),
                ]
                : ['name' => 'Editorial', 'role' => 'Team'];
            $post['published_at'] = $post['publishedAt'] ?? $post['published_at'] ?? null;
            $post['updated_at'] = $post['updatedAt'] ?? $post['updated_at'] ?? date('c');

            return $post;
        }, $posts);

        return $state;
    });
}

function api_read_site_settings(): array
{
    $state = api_load_state();
    $settings = $state['site_settings'] ?? [];
    $theme = $state['site_theme'] ?? [];
    $hero = $state['home_hero'] ?? [];
    $sections = $state['home_sections'] ?? [];

    return [
        'settings' => $settings,
        'theme' => $theme,
        'hero' => $hero,
        'sections' => $sections,
    ];
}

function api_write_site_settings(array $payload): array
{
    $updated = api_with_state(static function (array $state) use ($payload): array {
        if (isset($payload['settings']) && is_array($payload['settings'])) {
            $state['site_settings'] = array_merge($state['site_settings'] ?? [], $payload['settings']);
        }

        if (isset($payload['theme']) && is_array($payload['theme'])) {
            $state['site_theme'] = array_merge($state['site_theme'] ?? [], $payload['theme']);
        }

        if (isset($payload['hero']) && is_array($payload['hero'])) {
            $state['home_hero'] = array_merge($state['home_hero'] ?? [], $payload['hero']);
        }

        if (isset($payload['sections']) && is_array($payload['sections'])) {
            $state['home_sections'] = $payload['sections'];
        }

        return $state;
    });

    return api_read_site_settings();
}

function api_verify_recaptcha(?string $token): array
{
    $secret = trim((string) (getenv('GOOGLE_RECAPTCHA_SECRET') ?: getenv('RECAPTCHA_SECRET') ?: ''));
    $token = trim((string) $token);

    if ($secret === '') {
        return ['success' => true, 'skipped' => true];
    }

    if ($token === '') {
        return ['success' => false, 'error' => 'missing-token'];
    }

    $payload = http_build_query([
        'secret' => $secret,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);

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
        return ['success' => false, 'error' => 'request-failed'];
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        return ['success' => false, 'error' => 'invalid-response'];
    }

    return $data + ['skipped' => false];
}

function api_fetch_solar_estimate(array $request): array
{
    $apiKey = trim((string) (getenv('GOOGLE_SOLAR_API_KEY') ?: ''));
    if ($apiKey === '') {
        api_send_error(503, 'Solar API key is not configured.');
    }

    $params = ['requiredQuality' => 'HIGH', 'key' => $apiKey];
    if (!empty($request['latitude']) && !empty($request['longitude'])) {
        $params['location.latitude'] = $request['latitude'];
        $params['location.longitude'] = $request['longitude'];
    }

    if (!empty($request['address'])) {
        $params['address'] = $request['address'];
    }

    $query = http_build_query($params);
    $url = 'https://solar.googleapis.com/v1/buildingInsights:findClosest?' . $query;

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 8,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        api_send_error(502, 'Unable to fetch solar potential at this time.');
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        api_send_error(502, 'Invalid response from solar API.');
    }

    $insights = $data['buildingInsights'] ?? [];
    $potential = $insights['solarPotential'] ?? [];
    $roofSegments = $potential['roofSegmentStats'] ?? [];
    if (!is_array($roofSegments)) {
        $roofSegments = [];
    }

    usort($roofSegments, static function ($a, $b): int {
        $left = $a['yearlyEnergyDcKwh'] ?? 0;
        $right = $b['yearlyEnergyDcKwh'] ?? 0;
        return $right <=> $left;
    });

    $best = $roofSegments[0] ?? [];
    $systemCapacity = $request['systemCapacityKw'] ?? $potential['maxArrayPanelsCount'] ?? null;
    $annualKwh = $potential['maxArrayYearlyEnergyDcKwh'] ?? ($best['yearlyEnergyDcKwh'] ?? 0);

    return [
        'summary' => [
            'address' => $insights['postalAddress']['formattedAddress'] ?? $request['address'] ?? 'Requested site',
            'roofAreaSqm' => $potential['maxArrayAreaMeters2'] ?? ($best['areaMeters2'] ?? 0),
            'optimalAzimuth' => $best['azimuthDegrees'] ?? null,
            'optimalTilt' => $best['tiltDegrees'] ?? null,
            'sunshineHoursPerYear' => $potential['maxSunshineHoursPerYear'] ?? null,
            'recommendedSystemSizeKw' => $systemCapacity,
            'estimatedYearlyGenerationKwh' => $annualKwh,
            'estimatedSavingsInr' => $annualKwh * 6.8,
        ],
        'raw' => $data,
    ];
}

function api_send_whatsapp_lead(array $lead): void
{
    $phoneNumberId = trim((string) (getenv('WHATSAPP_PHONE_NUMBER_ID') ?: ''));
    $accessToken = trim((string) (getenv('WHATSAPP_ACCESS_TOKEN') ?: ''));
    $recipient = trim((string) (getenv('WHATSAPP_RECIPIENT_NUMBER') ?: getenv('WHATSAPP_RECIPIENT') ?: ''));

    if ($phoneNumberId === '' || $accessToken === '' || $recipient === '') {
        api_send_error(503, 'WhatsApp integration is not configured.');
    }

    $messageLines = [
        '*New Solar Consultation Lead*',
        'Name: ' . ($lead['name'] ?? 'Unknown'),
        'Phone: ' . ($lead['phone'] ?? '—'),
        'City: ' . ($lead['city'] ?? '—'),
        'Project Type: ' . ($lead['projectType'] ?? '—'),
    ];

    if (!empty($lead['leadSource'])) {
        $messageLines[] = 'Source: ' . $lead['leadSource'];
    }

    $messageLines[] = 'Received: ' . date('d M Y, h:i A');

    $payload = json_encode([
        'messaging_product' => 'whatsapp',
        'to' => preg_replace('/[^+\d]/', '', $recipient),
        'type' => 'text',
        'text' => [
            'preview_url' => false,
            'body' => implode("\n", $messageLines),
        ],
    ]);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
            ],
            'content' => $payload,
            'timeout' => 6,
        ],
    ]);

    $url = sprintf('https://graph.facebook.com/v20.0/%s/messages', urlencode($phoneNumberId));
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        api_send_error(502, 'Failed to send WhatsApp notification.');
    }
}

function api_collect_site_content(): array
{
    $state = api_load_state();
    $theme = $state['site_theme'] ?? [];
    $hero = $state['home_hero'] ?? [];
    $offers = $state['home_offers'] ?? [];
    $testimonials = $state['testimonials'] ?? [];
    $sections = $state['home_sections'] ?? [];

    $palette = [];
    if (isset($theme['palette']) && is_array($theme['palette'])) {
        foreach ($theme['palette'] as $key => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $palette[$key] = [
                'background' => $entry['background'] ?? '#FFFFFF',
                'text' => $entry['text'] ?? '#0F172A',
                'muted' => $entry['muted'] ?? '#475569',
            ];
        }
    }

    $publishedOffers = array_values(array_filter(array_map(static function (array $offer): array {
        return [
            'id' => $offer['id'] ?? '',
            'title' => $offer['title'] ?? '',
            'description' => $offer['description'] ?? '',
            'badge' => $offer['badge'] ?? '',
            'startsOn' => $offer['starts_on'] ?? '',
            'endsOn' => $offer['ends_on'] ?? '',
            'ctaText' => $offer['cta_text'] ?? '',
            'ctaUrl' => $offer['cta_url'] ?? '',
            'image' => $offer['image'] ?? '',
            'status' => $offer['status'] ?? 'draft',
        ];
    }, $offers), static fn(array $offer): bool => ($offer['status'] ?? 'draft') === 'published'));

    $publishedTestimonials = array_values(array_filter(array_map(static function (array $testimonial): array {
        return [
            'id' => $testimonial['id'] ?? '',
            'quote' => $testimonial['quote'] ?? '',
            'name' => $testimonial['name'] ?? '',
            'role' => $testimonial['role'] ?? '',
            'location' => $testimonial['location'] ?? '',
            'image' => $testimonial['image'] ?? '',
            'status' => $testimonial['status'] ?? 'published',
        ];
    }, $testimonials), static fn(array $testimonial): bool => ($testimonial['status'] ?? 'published') === 'published'));

    $publishedSections = array_values(array_filter($sections, static function ($section): bool {
        return is_array($section) && ($section['status'] ?? 'draft') === 'published';
    }));

    usort($publishedSections, static function (array $a, array $b): int {
        $orderA = (int) ($a['display_order'] ?? 0);
        $orderB = (int) ($b['display_order'] ?? 0);
        if ($orderA === $orderB) {
            return strcmp($a['updated_at'] ?? '', $b['updated_at'] ?? '');
        }
        return $orderA <=> $orderB;
    });

    $preparedSections = array_values(array_map(static function (array $section): array {
        $cta = $section['cta'] ?? [];
        $media = $section['media'] ?? [];
        return [
            'id' => $section['id'] ?? '',
            'eyebrow' => $section['eyebrow'] ?? '',
            'title' => $section['title'] ?? '',
            'subtitle' => $section['subtitle'] ?? '',
            'body' => $section['body'] ?? [],
            'bullets' => $section['bullets'] ?? [],
            'cta' => [
                'text' => $cta['text'] ?? '',
                'url' => $cta['url'] ?? '',
            ],
            'media' => [
                'type' => $media['type'] ?? 'none',
                'src' => $media['src'] ?? '',
                'alt' => $media['alt'] ?? '',
            ],
            'backgroundStyle' => $section['background_style'] ?? 'section',
        ];
    }, $publishedSections));

    return [
        'theme' => [
            'name' => $theme['active_theme'] ?? 'seasonal',
            'seasonLabel' => $theme['season_label'] ?? '',
            'accentColor' => $theme['accent_color'] ?? '#2563eb',
            'backgroundImage' => $theme['background_image'] ?? '',
            'announcement' => $theme['announcement'] ?? '',
            'palette' => $palette,
        ],
        'hero' => [
            'title' => $hero['title'] ?? '',
            'subtitle' => $hero['subtitle'] ?? '',
            'image' => $hero['image'] ?? '',
            'imageCaption' => $hero['image_caption'] ?? '',
            'bubbleHeading' => $hero['bubble_heading'] ?? '',
            'bubbleBody' => $hero['bubble_body'] ?? '',
            'bullets' => $hero['bullets'] ?? [],
        ],
        'sections' => $preparedSections,
        'offers' => $publishedOffers,
        'testimonials' => $publishedTestimonials,
    ];
}
