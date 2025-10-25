<?php

declare(strict_types=1);

require_once __DIR__ . '/../server/helpers.php';
require_once __DIR__ . '/../server/modules.php';

/**
 * Centralised bootstrap for the admin control centre.
 */
function admin_portal_bootstrap(): array
{
    server_bootstrap();
    ensure_session();

    $integrity = admin_perform_integrity_checks();

    return [
        'integrity' => $integrity,
        'site_settings' => load_site_settings(),
        'alerts' => load_alerts(),
        'metrics' => load_admin_metrics(),
    ];
}

function admin_perform_integrity_checks(): array
{
    $schemas = admin_expected_data_shapes();
    $results = [
        'backups' => null,
        'repaired' => [],
        'missing' => [],
    ];

    foreach ($schemas as $file => $shape) {
        $path = DATA_PATH . '/' . $file;
        if (!file_exists($path)) {
            json_write($path, $shape['default']);
            $results['missing'][] = $file;
            continue;
        }
        $current = json_read($path, $shape['default']);
        $repaired = admin_repair_data_shape($current, $shape['rules']);
        if ($repaired['modified']) {
            json_write($path, $repaired['data']);
            $results['repaired'][] = $file;
        }
    }

    $results['backups'] = admin_snapshot_backup();

    return $results;
}

function admin_expected_data_shapes(): array
{
    return [
        'users.json' => [
            'default' => [],
            'rules' => [
                ['type' => 'array'],
            ],
        ],
        'customers.json' => [
            'default' => [],
            'rules' => [
                ['type' => 'array'],
            ],
        ],
        'potential_customers.json' => [
            'default' => [],
            'rules' => [
                ['type' => 'array'],
            ],
        ],
        'tickets.json' => [
            'default' => [],
            'rules' => [
                ['type' => 'array'],
            ],
        ],
        'tasks.json' => [
            'default' => [],
            'rules' => [
                ['type' => 'array'],
            ],
        ],
        'approvals.json' => [
            'default' => [],
            'rules' => [
                ['type' => 'array'],
            ],
        ],
        'site_settings.json' => [
            'default' => default_site_settings(),
            'rules' => [
                ['type' => 'assoc'],
            ],
        ],
        'models.json' => [
            'default' => ['default_model' => null, 'models' => []],
            'rules' => [
                ['type' => 'assoc'],
                ['key' => 'models', 'type' => 'array'],
            ],
        ],
        'referrers.json' => [
            'default' => [],
            'rules' => [
                ['type' => 'array'],
            ],
        ],
        'warranty_amc.json' => [
            'default' => ['assets' => []],
            'rules' => [
                ['type' => 'assoc'],
                ['key' => 'assets', 'type' => 'array'],
            ],
        ],
        'documents_index.json' => [
            'default' => ['documents' => [], 'next_sequence' => 1],
            'rules' => [
                ['type' => 'assoc'],
                ['key' => 'documents', 'type' => 'array'],
                ['key' => 'next_sequence', 'type' => 'int', 'default' => 1],
            ],
        ],
        'subsidy_tracker.json' => [
            'default' => [],
            'rules' => [
                ['type' => 'array'],
            ],
        ],
        'alerts.json' => [
            'default' => [],
            'rules' => [
                ['type' => 'array'],
            ],
        ],
        'activity_log.json' => [
            'default' => [],
            'rules' => [
                ['type' => 'array'],
            ],
        ],
        'devices.json' => [
            'default' => [],
            'rules' => [
                ['type' => 'array'],
            ],
        ],
    ];
}

function admin_repair_data_shape($data, array $rules): array
{
    $modified = false;
    foreach ($rules as $rule) {
        $type = $rule['type'] ?? 'array';
        switch ($type) {
            case 'array':
                if (!is_array($data)) {
                    $data = [];
                    $modified = true;
                }
                break;
            case 'assoc':
                if (!is_array($data)) {
                    $data = [];
                    $modified = true;
                }
                break;
            case 'int':
                $key = $rule['key'] ?? null;
                if ($key !== null) {
                    if (!isset($data[$key]) || !is_int($data[$key])) {
                        $data[$key] = (int) ($rule['default'] ?? 0);
                        $modified = true;
                    }
                }
                break;
        }
        if (isset($rule['key']) && isset($rule['type']) && $rule['type'] === 'array') {
            $key = $rule['key'];
            if (!isset($data[$key]) || !is_array($data[$key])) {
                $data[$key] = [];
                $modified = true;
            }
        }
    }

    return ['data' => $data, 'modified' => $modified];
}

function admin_snapshot_backup(): ?string
{
    $backupRoot = SERVER_BASE_PATH . '/backups';
    if (!is_dir($backupRoot)) {
        mkdir($backupRoot, 0775, true);
    }
    $date = date('Ymd');
    $existing = glob($backupRoot . '/' . $date . '*');
    if ($existing) {
        sort($existing);
        return end($existing);
    }
    $stamp = $date . '-' . date('His');
    $destination = $backupRoot . '/' . $stamp;
    if (!is_dir($destination) && !mkdir($destination, 0775, true)) {
        log_system_error('Unable to create backup directory: ' . $destination);
        return null;
    }

    $files = glob(DATA_PATH . '/*.json');
    if ($files === false) {
        return null;
    }

    foreach ($files as $file) {
        $target = $destination . '/' . basename($file);
        copy($file, $target);
    }

    return $destination;
}

function load_alerts(): array
{
    $alerts = json_read(ALERTS_FILE, []);
    if (!is_array($alerts)) {
        $alerts = [];
    }
    usort($alerts, static function ($a, $b) {
        return strtotime($b['created_at'] ?? 'now') <=> strtotime($a['created_at'] ?? 'now');
    });
    return $alerts;
}

function load_admin_metrics(): array
{
    $customers = json_read(DATA_PATH . '/customers.json', []);
    $leads = json_read(DATA_PATH . '/potential_customers.json', []);
    $tickets = json_read(TICKETS_FILE, []);
    $tasks = json_read(DATA_PATH . '/tasks.json', []);
    $warranty = json_read(WARRANTY_AMC_FILE, ['assets' => []]);
    $subsidy = json_read(SUBSIDY_TRACKER_FILE, []);

    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
    $sevenDaysAgo = $now->sub(new DateInterval('P7D'));
    $startMonth = $now->modify('first day of this month midnight');

    $newComplaints = 0;
    foreach ($tickets as $ticket) {
        $created = isset($ticket['created_at']) ? new DateTimeImmutable($ticket['created_at']) : null;
        if ($created && $created >= $sevenDaysAgo && ($ticket['type'] ?? 'complaint') === 'complaint') {
            $newComplaints++;
        }
    }

    $openTasks = 0;
    foreach ($tasks as $task) {
        if (($task['status'] ?? 'open') !== 'completed') {
            $openTasks++;
        }
    }

    $amcDue = 0;
    foreach ($warranty['assets'] ?? [] as $asset) {
        $expiry = isset($asset['amc_due_on']) ? new DateTimeImmutable($asset['amc_due_on']) : null;
        if ($expiry && $expiry <= $now->add(new DateInterval('P14D'))) {
            $amcDue++;
        }
    }

    $subsidyMonth = 0;
    foreach ($subsidy as $record) {
        $submitted = isset($record['submitted_at']) ? new DateTimeImmutable($record['submitted_at']) : null;
        if ($submitted && $submitted >= $startMonth) {
            $subsidyMonth++;
        }
    }

    return [
        'totals' => [
            'customers' => count($customers),
            'leads' => count($leads),
        ],
        'new_complaints' => $newComplaints,
        'open_tasks' => $openTasks,
        'amc_due' => $amcDue,
        'subsidy_month' => $subsidyMonth,
        'tickets' => $tickets,
        'tasks' => $tasks,
        'customers' => $customers,
        'subsidy' => $subsidy,
    ];
}

function admin_views(): array
{
    return [
        'dashboard' => ['title' => 'Dashboard Overview', 'icon' => 'chart-pie'],
        'ai' => ['title' => 'AI Tools', 'icon' => 'sparkles'],
        'crm' => ['title' => 'CRM', 'icon' => 'users'],
        'tickets' => ['title' => 'Tickets & Complaints', 'icon' => 'ticket'],
        'warranty' => ['title' => 'Warranty & AMC', 'icon' => 'shield-check'],
        'vault' => ['title' => 'Document Vault', 'icon' => 'archive'],
        'subsidy' => ['title' => 'Subsidy Tracker', 'icon' => 'sun'],
        'analytics' => ['title' => 'Analytics', 'icon' => 'presentation-chart-line'],
        'customers' => ['title' => 'Customer Portal', 'icon' => 'globe-alt'],
        'alerts' => ['title' => 'Alert Centre', 'icon' => 'bell'],
        'settings' => ['title' => 'Settings', 'icon' => 'cog'],
        'errors' => ['title' => 'Error Monitor', 'icon' => 'exclamation-triangle'],
    ];
}

function admin_search(string $term): array
{
    $term = trim($term);
    if ($term === '') {
        return ['customers' => [], 'tickets' => [], 'documents' => [], 'referrers' => []];
    }
    $termLower = mb_strtolower($term);

    $customers = array_slice(array_values(array_filter(json_read(DATA_PATH . '/customers.json', []), static function ($customer) use ($termLower) {
        $haystack = mb_strtolower(($customer['name'] ?? '') . ' ' . ($customer['email'] ?? '') . ' ' . ($customer['phone'] ?? ''));
        return $haystack !== '' && str_contains($haystack, $termLower);
    })), 0, 8);

    $tickets = array_slice(array_values(array_filter(json_read(TICKETS_FILE, []), static function ($ticket) use ($termLower) {
        $haystack = mb_strtolower(($ticket['title'] ?? '') . ' ' . ($ticket['id'] ?? '') . ' ' . ($ticket['customer_name'] ?? ''));
        return $haystack !== '' && str_contains($haystack, $termLower);
    })), 0, 8);

    $documentsIndex = json_read(DOCUMENTS_INDEX_FILE, ['documents' => []]);
    $documents = array_slice(array_values(array_filter($documentsIndex['documents'] ?? [], static function ($document) use ($termLower) {
        $haystack = mb_strtolower(($document['name'] ?? '') . ' ' . ($document['tags'] ?? ''));
        return $haystack !== '' && str_contains($haystack, $termLower);
    })), 0, 8);

    $referrers = array_slice(array_values(array_filter(json_read(REFERRERS_FILE, []), static function ($referrer) use ($termLower) {
        $haystack = mb_strtolower(($referrer['name'] ?? '') . ' ' . ($referrer['email'] ?? '') . ' ' . ($referrer['phone'] ?? ''));
        return $haystack !== '' && str_contains($haystack, $termLower);
    })), 0, 8);

    return [
        'customers' => $customers,
        'tickets' => $tickets,
        'documents' => $documents,
        'referrers' => $referrers,
    ];
}

function admin_notification_summary(array $alerts): array
{
    $unread = array_filter($alerts, static fn ($alert) => ($alert['read'] ?? false) === false);
    return [
        'total' => count($alerts),
        'unread' => count($unread),
    ];
}

function admin_active_sessions(): array
{
    $devices = json_read(DATA_PATH . '/devices.json', []);
    if (!is_array($devices)) {
        $devices = [];
    }
    return $devices;
}

function admin_system_health(): array
{
    $total = @disk_total_space(SERVER_BASE_PATH) ?: 0;
    $free = @disk_free_space(SERVER_BASE_PATH) ?: 0;
    $percent = $total > 0 ? round(($free / $total) * 100, 2) : 0;

    $errors = 0;
    if (file_exists(SYSTEM_ERROR_FILE)) {
        $lines = file(SYSTEM_ERROR_FILE, FILE_IGNORE_NEW_LINES);
        if ($lines !== false) {
            $cutoff = time() - 86400;
            foreach ($lines as $line) {
                if (preg_match('/\[(.*?)\]/', $line, $match)) {
                    $timestamp = strtotime($match[1]);
                    if ($timestamp !== false && $timestamp >= $cutoff) {
                        $errors++;
                    }
                }
            }
        }
    }

    $records = 0;
    foreach (glob(DATA_PATH . '/*.json') as $file) {
        $data = json_read($file, []);
        if (is_array($data)) {
            $isAssoc = array_keys($data) !== range(0, count($data) - 1);
            $records += $isAssoc ? count($data) : count($data);
        }
    }

    return [
        'disk' => $total > 0 ? sprintf('%.2f%% free', $percent) : 'n/a',
        'errors' => $errors,
        'records' => $records,
    ];
}

?>
