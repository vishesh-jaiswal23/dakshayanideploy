<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/portal-config.php';
require_once __DIR__ . '/portal-admin.php';
require_once __DIR__ . '/server/helpers.php';

date_default_timezone_set(PORTAL_TIMEZONE);

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

server_bootstrap();

$authUser = get_authenticated_user();
$actorEmail = $authUser['email'] ?? ($_SESSION['user_email'] ?? 'admin');
log_activity(
    'legacy_admin_dashboard_redirect',
    ['from' => 'admin-dashboard.php', 'to' => '/admin/index.php?view=settings'],
    $actorEmail
);

header('Location: /admin/index.php?view=settings', true, 302);
exit;

portal_admin_bootstrap_files(PORTAL_ADMIN_EMAIL, PORTAL_ADMIN_PASSWORD_HASH);

if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
}

$csrfToken = $_SESSION['csrf_token'];
$actorName = $_SESSION['display_name'] ?? 'Admin';

$allowedViews = [
    'dashboard',
    'users',
    'approvals',
    'customers',
    'complaints',
    'tasks',
    'ledger',
    'ai',
    'settings',
    'activity',
];

$view = $_GET['view'] ?? 'dashboard';
if (!in_array($view, $allowedViews, true)) {
    $view = '404';
}

$messages = [];

function portal_admin_add_message(array &$messages, string $type, string $text): void
{
    $messages[] = ['type' => $type, 'text' => $text];
}

function portal_admin_validate_csrf(string $token): bool
{
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function portal_admin_require_csrf(array &$messages): bool
{
    $token = $_POST['csrf_token'] ?? '';
    if (!portal_admin_validate_csrf($token)) {
        portal_admin_add_message($messages, 'error', 'Your session security token is invalid or expired. Please refresh the page and try again.');
        return false;
    }
    return true;
}

function portal_admin_encrypt_api_key(string $value): ?string
{
    if ($value === '') {
        return null;
    }
    $key = hash('sha256', PORTAL_ADMIN_PASSWORD_HASH, true);
    try {
        $iv = random_bytes(16);
    } catch (Exception $e) {
        $iv = substr(hash('sha256', uniqid('', true)), 0, 16);
    }
    $cipher = openssl_encrypt($value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) {
        portal_admin_log_error('Failed to encrypt AI API key.');
        return null;
    }
    return base64_encode($iv . $cipher);
}

function portal_admin_decrypt_api_key(?string $value): ?string
{
    if (!$value) {
        return null;
    }
    $decoded = base64_decode($value, true);
    if ($decoded === false || strlen($decoded) < 17) {
        return null;
    }
    $iv = substr($decoded, 0, 16);
    $cipher = substr($decoded, 16);
    $key = hash('sha256', PORTAL_ADMIN_PASSWORD_HASH, true);
    $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return $plain === false ? null : $plain;
}

function portal_admin_generate_blog_preview(string $prompt): array
{
    $cleanPrompt = trim($prompt);
    if ($cleanPrompt === '') {
        return ['title' => 'Draft blog post', 'body' => ''];
    }

    $sentences = preg_split('/(?<=[.!?])\s+/', $cleanPrompt) ?: [$cleanPrompt];
    $titleSource = $sentences[0];
    $title = ucwords(mb_strtolower(substr($titleSource, 0, 80)));
    $title = preg_replace('/[^A-Za-z0-9\s]/', '', $title) ?: 'Blog Update';

    $bodyParts = [];
    foreach ($sentences as $sentence) {
        $sentence = trim($sentence);
        if ($sentence === '') {
            continue;
        }
        $bodyParts[] = $sentence;
        if (count($bodyParts) >= 6) {
            break;
        }
    }
    if (count($bodyParts) < 3) {
        $bodyParts[] = 'This update provides actionable steps for the Dakshayani operations and leadership teams.';
        $bodyParts[] = 'Share this insight with customers, installers, and partners through the Dakshayani knowledge hub.';
    }

    $paragraphs = array_chunk($bodyParts, 2);
    $body = '';
    foreach ($paragraphs as $chunk) {
        $body .= '<p>' . portal_admin_e(implode(' ', $chunk)) . '</p>';
    }

    return ['title' => $title, 'body' => $body];
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    switch ($action) {
        case 'add_user':
            if (!portal_admin_require_csrf($messages)) {
                break;
            }
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = filter_var((string) ($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
            $role = $_POST['role'] ?? 'employee';
            $status = $_POST['status'] ?? 'active';
            $password = (string) ($_POST['password'] ?? '');
            $forceReset = isset($_POST['force_reset']);

            if (!$email) {
                portal_admin_add_message($messages, 'error', 'Please provide a valid email address.');
                break;
            }
            if (!isset(PORTAL_ROLE_LABELS[$role])) {
                portal_admin_add_message($messages, 'error', 'Please choose a valid role.');
                break;
            }
            if ($password === '') {
                portal_admin_add_message($messages, 'error', 'Please set a secure password for the new user.');
                break;
            }
            $users = portal_admin_load_json('users.json', []);
            $duplicate = false;
            foreach ($users as $user) {
                if (strcasecmp($user['email'] ?? '', $email) === 0) {
                    portal_admin_add_message($messages, 'error', 'A user with this email already exists.');
                    $duplicate = true;
                    break;
                }
            }
            if ($duplicate) {
                break;
            }
            $id = portal_admin_generate_id('user');
            $now = date('c');
            $users[] = [
                'id' => $id,
                'name' => $name ?: 'Portal user',
                'email' => $email,
                'role' => $role,
                'status' => $status,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'force_reset' => $forceReset,
                'created_at' => $now,
                'updated_at' => $now,
                'last_login' => null,
            ];
            portal_admin_save_json('users.json', $users);
            portal_admin_log_activity('user.created', "Created {$email} ({$role})", $actorName);
            portal_admin_add_message($messages, 'success', 'User created successfully.');
            break;

        case 'update_user':
            if (!portal_admin_require_csrf($messages)) {
                break;
            }
            $userId = $_POST['user_id'] ?? '';
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = filter_var((string) ($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
            $role = $_POST['role'] ?? 'employee';
            $status = $_POST['status'] ?? 'active';
            $password = (string) ($_POST['password'] ?? '');
            $forceReset = isset($_POST['force_reset']);
            $users = portal_admin_load_json('users.json', []);
            $found = false;
            foreach ($users as &$user) {
                if (($user['id'] ?? '') === $userId) {
                    if (!$email) {
                        portal_admin_add_message($messages, 'error', 'Please provide a valid email address.');
                        $found = true;
                        break;
                    }
                    if (!isset(PORTAL_ROLE_LABELS[$role])) {
                        portal_admin_add_message($messages, 'error', 'Please choose a valid role.');
                        $found = true;
                        break;
                    }
                    $user['name'] = $name ?: $user['name'];
                    $user['email'] = $email;
                    $user['role'] = $role;
                    $user['status'] = $status;
                    $user['force_reset'] = $forceReset;
                    if ($password !== '') {
                        $user['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                        $user['force_reset'] = false;
                    }
                    $user['updated_at'] = date('c');
                    $found = true;
                    break;
                }
            }
            unset($user);
            if ($found) {
                portal_admin_save_json('users.json', $users);
                portal_admin_log_activity('user.updated', "Updated account {$email}", $actorName);
                portal_admin_add_message($messages, 'success', 'User updated successfully.');
            } else {
                portal_admin_add_message($messages, 'error', 'User not found.');
            }
            break;

        case 'delete_user':
            if (!portal_admin_require_csrf($messages)) {
                break;
            }
            $userId = $_POST['user_id'] ?? '';
            $users = portal_admin_load_json('users.json', []);
            $remainingAdmins = 0;
            foreach ($users as $record) {
                if (($record['role'] ?? '') === 'admin') {
                    $remainingAdmins++;
                }
            }
            foreach ($users as $index => $record) {
                if (($record['id'] ?? '') === $userId) {
                    if (($record['role'] ?? '') === 'admin' && $remainingAdmins <= 1) {
                        portal_admin_add_message($messages, 'error', 'You cannot delete the only admin account.');
                        break 2;
                    }
                    $email = $record['email'] ?? $record['id'];
                    unset($users[$index]);
                    portal_admin_save_json('users.json', array_values($users));
                    portal_admin_log_activity('user.deleted', "Deleted account {$email}", $actorName);
                    portal_admin_add_message($messages, 'success', 'User deleted successfully.');
                    break;
                }
            }
            break;

        case 'force_reset_password':
            if (!portal_admin_require_csrf($messages)) {
                break;
            }
            $userId = $_POST['user_id'] ?? '';
            $users = portal_admin_load_json('users.json', []);
            $found = false;
            foreach ($users as &$record) {
                if (($record['id'] ?? '') === $userId) {
                    $record['force_reset'] = true;
                    $record['updated_at'] = date('c');
                    $found = true;
                    portal_admin_log_activity('user.force_reset', "Password reset required for {$record['email']}", $actorName);
                    break;
                }
            }
            unset($record);
            if ($found) {
                portal_admin_save_json('users.json', $users);
                portal_admin_add_message($messages, 'success', 'The user will be asked to reset their password on next login.');
            } else {
                portal_admin_add_message($messages, 'error', 'User not found.');
            }
            break;

        case 'approve_change':
            if (!portal_admin_require_csrf($messages)) {
                break;
            }
            $requestId = $_POST['request_id'] ?? '';
            $approvals = portal_admin_load_json('approvals.json', []);
            $foundIndex = null;
            foreach ($approvals as $index => $request) {
                if (($request['id'] ?? '') === $requestId) {
                    $foundIndex = $index;
                    break;
                }
            }
            if ($foundIndex === null) {
                portal_admin_add_message($messages, 'error', 'Request not found.');
                break;
            }
            $request = $approvals[$foundIndex];
            $targetType = $request['target_type'] ?? '';
            $targetId = $request['target_id'] ?? '';
            $changes = $request['changes'] ?? [];
            $success = false;
            switch ($targetType) {
                case 'user':
                    $users = portal_admin_load_json('users.json', []);
                    foreach ($users as &$record) {
                        if (($record['id'] ?? '') === $targetId) {
                            foreach ($changes as $field => $change) {
                                $record[$field] = $change['new'] ?? $record[$field] ?? null;
                            }
                            $record['updated_at'] = date('c');
                            $success = true;
                            break;
                        }
                    }
                    unset($record);
                    if ($success) {
                        portal_admin_save_json('users.json', $users);
                    }
                    break;
                case 'customer':
                    $customers = portal_admin_load_json('customers.json', []);
                    foreach ($customers as &$record) {
                        if (($record['id'] ?? '') === $targetId) {
                            foreach ($changes as $field => $change) {
                                $record[$field] = $change['new'] ?? $record[$field] ?? null;
                            }
                            $record['updated_at'] = date('c');
                            $success = true;
                            break;
                        }
                    }
                    unset($record);
                    if ($success) {
                        portal_admin_save_json('customers.json', $customers);
                    }
                    break;
                default:
                    portal_admin_add_message($messages, 'error', 'Unsupported change request type.');
                    break;
            }
            if ($success) {
                unset($approvals[$foundIndex]);
                portal_admin_save_json('approvals.json', array_values($approvals));
                portal_admin_log_activity('approval.approved', "Approved {$targetType} change {$requestId}", $actorName);
                portal_admin_add_message($messages, 'success', 'Change request approved and applied.');
            }
            break;

        case 'reject_change':
            if (!portal_admin_require_csrf($messages)) {
                break;
            }
            $requestId = $_POST['request_id'] ?? '';
            $approvals = portal_admin_load_json('approvals.json', []);
            foreach ($approvals as $index => $request) {
                if (($request['id'] ?? '') === $requestId) {
                    unset($approvals[$index]);
                    portal_admin_save_json('approvals.json', array_values($approvals));
                    portal_admin_log_activity('approval.rejected', "Rejected request {$requestId}", $actorName);
                    portal_admin_add_message($messages, 'success', 'Change request rejected.');
                    break;
                }
            }
            break;

        case 'add_customer':
            if (!portal_admin_require_csrf($messages)) {
                break;
            }
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = filter_var((string) ($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: null;
            $phone = trim((string) ($_POST['phone'] ?? ''));
            $status = $_POST['status'] ?? 'active';
            $tags = array_filter(array_map('trim', explode(',', (string) ($_POST['tags'] ?? ''))));
            $address = trim((string) ($_POST['address'] ?? ''));
            $notes = trim((string) ($_POST['notes'] ?? ''));
            if ($name === '') {
                portal_admin_add_message($messages, 'error', 'Customer name is required.');
                break;
            }
            $customers = portal_admin_load_json('customers.json', []);
            $id = portal_admin_generate_id('customer');
            $now = date('c');
            $customers[] = [
                'id' => $id,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'status' => $status,
                'tags' => $tags,
                'address' => $address,
                'notes' => $notes,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            portal_admin_save_json('customers.json', $customers);
            portal_admin_log_activity('customer.created', "Added customer {$name}", $actorName);
            portal_admin_add_message($messages, 'success', 'Customer added successfully.');
            break;

        case 'update_customer':
            if (!portal_admin_require_csrf($messages)) {
                break;
            }
            $customerId = $_POST['customer_id'] ?? '';
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = filter_var((string) ($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: null;
            $phone = trim((string) ($_POST['phone'] ?? ''));
            $status = $_POST['status'] ?? 'active';
            $tags = array_filter(array_map('trim', explode(',', (string) ($_POST['tags'] ?? ''))));
            $address = trim((string) ($_POST['address'] ?? ''));
            $notes = trim((string) ($_POST['notes'] ?? ''));
            $customers = portal_admin_load_json('customers.json', []);
            $found = false;
            foreach ($customers as &$customer) {
                if (($customer['id'] ?? '') === $customerId) {
                    $customer['name'] = $name ?: $customer['name'];
                    $customer['email'] = $email;
                    $customer['phone'] = $phone;
                    $customer['status'] = $status;
                    $customer['tags'] = $tags;
                    $customer['address'] = $address;
                    $customer['notes'] = $notes;
                    $customer['updated_at'] = date('c');
                    $found = true;
                    break;
                }
            }
            unset($customer);
            if ($found) {
                portal_admin_save_json('customers.json', $customers);
                portal_admin_log_activity('customer.updated', "Updated customer {$name}", $actorName);
                portal_admin_add_message($messages, 'success', 'Customer updated successfully.');
            } else {
                portal_admin_add_message($messages, 'error', 'Customer not found.');
            }
            break;

        case 'delete_customer':
            if (!portal_admin_require_csrf($messages)) {
                break;
            }
            $customerId = $_POST['customer_id'] ?? '';
            $customers = portal_admin_load_json('customers.json', []);
            foreach ($customers as $index => $customer) {
                if (($customer['id'] ?? '') === $customerId) {
                    unset($customers[$index]);
                    portal_admin_save_json('customers.json', array_values($customers));
                    portal_admin_log_activity('customer.deleted', "Deleted customer {$customerId}", $actorName);
                    portal_admin_add_message($messages, 'success', 'Customer removed successfully.');
                    break;
                }
            }
            break;

        case 'create_complaint':
            if (!portal_admin_require_csrf($messages)) {
                break;
            }
            $settings = portal_admin_load_json('settings.json', []);
            $complaintsSettings = $settings['complaints'] ?? ['public_intake_enabled' => false];
            $source = $_POST['source'] ?? 'internal';
            if ($source === 'public' && empty($complaintsSettings['public_intake_enabled'])) {
                portal_admin_add_message($messages, 'error', 'Public complaint intake is currently disabled.');
                break;
            }
            $customerName = trim((string) ($_POST['customer_name'] ?? ''));
            $customerEmail = filter_var((string) ($_POST['customer_email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: null;
            $customerPhone = trim((string) ($_POST['customer_phone'] ?? ''));
            $category = trim((string) ($_POST['category'] ?? 'General'));
            $description = trim((string) ($_POST['description'] ?? ''));
            $priority = $_POST['priority'] ?? 'normal';
            if ($customerName === '' || $description === '') {
                portal_admin_add_message($messages, 'error', 'Please provide the customer name and description.');
                break;
            }
            $complaints = portal_admin_load_json('complaints.json', []);
            $now = date('c');
            $complaints[] = [
                'id' => portal_admin_generate_id('ticket'),
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'customer_phone' => $customerPhone,
                'category' => $category,
                'description' => $description,
                'status' => 'New',
                'priority' => $priority,
                'assignee' => $_POST['assignee'] ?? '',
                'source' => $source,
                'notes' => [],
                'created_at' => $now,
                'updated_at' => $now,
            ];
            portal_admin_save_json('complaints.json', $complaints);
            portal_admin_log_activity('complaint.created', "New complaint for {$customerName}", $actorName);
            portal_admin_add_message($messages, 'success', 'Complaint ticket created successfully.');
            break;

        case 'update_complaint':
            if (!portal_admin_require_csrf($messages)) {
                break;
            }
            $complaintId = $_POST['complaint_id'] ?? '';
            $status = $_POST['status'] ?? 'New';
            $assignee = trim((string) ($_POST['assignee'] ?? ''));
            $complaints = portal_admin_load_json('complaints.json', []);
            $found = false;
            foreach ($complaints as &$complaint) {
                if (($complaint['id'] ?? '') === $complaintId) {
                    $complaint['status'] = $status;
                    $complaint['assignee'] = $assignee;
                    $complaint['updated_at'] = date('c');
                    $found = true;
                    break;
                }
            }
            unset($complaint);
            if ($found) {
                portal_admin_save_json('complaints.json', $complaints);
                portal_admin_log_activity('complaint.updated', "Updated complaint {$complaintId}", $actorName);
                portal_admin_add_message($messages, 'success', 'Complaint updated successfully.');
            } else {
                portal_admin_add_message($messages, 'error', 'Complaint not found.');
            }
            break;

        case 'add_complaint_note':
            if (!portal_admin_require_csrf($messages)) {
                break;
            }
            $complaintId = $_POST['complaint_id'] ?? '';
            $note = trim((string) ($_POST['note'] ?? ''));
            if ($note === '') {
                portal_admin_add_message($messages, 'error', 'Please provide a note.');
                break;
            }
            $complaints = portal_admin_load_json('complaints.json', []);
            $found = false;
            foreach ($complaints as &$complaint) {
                if (($complaint['id'] ?? '') === $complaintId) {
                    if (!isset($complaint['notes']) || !is_array($complaint['notes'])) {
                        $complaint['notes'] = [];
                    }
                    $complaint['notes'][] = [
                        'timestamp' => date('c'),
                        'author' => $actorName,
                        'message' => $note,
                    ];
                    $complaint['updated_at'] = date('c');
                    $found = true;
                    break;
                }
            }
            unset($complaint);
            if ($found) {
                portal_admin_save_json('complaints.json', $complaints);
                portal_admin_log_activity('complaint.note', "Added note to {$complaintId}", $actorName);
                portal_admin_add_message($messages, 'success', 'Note added to complaint.');
            } else {
                portal_admin_add_message($messages, 'error', 'Complaint not found.');
            }
            break;

        case 'delete_complaint':
            if (!portal_admin_require_csrf($messages)) {
                break;
            }
            $complaintId = $_POST['complaint_id'] ?? '';
            $complaints = portal_admin_load_json('complaints.json', []);
            foreach ($complaints as $index => $complaint) {
                if (($complaint['id'] ?? '') === $complaintId) {
                    unset($complaints[$index]);
                    portal_admin_save_json('complaints.json', array_values($complaints));
                    portal_admin_log_activity('complaint.deleted', "Deleted complaint {$complaintId}", $actorName);
                    portal_admin_add_message($messages, 'success', 'Complaint deleted.');
                    break;
                }
            }
            break;

        case 'add_task':
            if (!portal_admin_require_csrf($messages)) {
                break;
            }
            $title = trim((string) ($_POST['title'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $priority = $_POST['priority'] ?? 'medium';
            $assignee = trim((string) ($_POST['assignee'] ?? ''));
            if ($title === '') {
                portal_admin_add_message($messages, 'error', 'Task title is required.');
                break;
            }
            $tasks = portal_admin_load_json('tasks.json', []);
            $now = date('c');
            $tasks[] = [
                'id' => portal_admin_generate_id('task'),
                'title' => $title,
                'description' => $description,
                'priority' => $priority,
                'status' => 'To Do',
                'assignee' => $assignee,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            portal_admin_save_json('tasks.json', $tasks);
            portal_admin_log_activity('task.created', "New task {$title}", $actorName);
            portal_admin_add_message($messages, 'success', 'Task added successfully.');
            break;

        case 'update_task':
            if (!portal_admin_require_csrf($messages)) {
                break;
            }
            $taskId = $_POST['task_id'] ?? '';
            $status = $_POST['status'] ?? 'To Do';
            $assignee = trim((string) ($_POST['assignee'] ?? ''));
            $title = trim((string) ($_POST['title'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $priority = $_POST['priority'] ?? 'medium';
            $tasks = portal_admin_load_json('tasks.json', []);
            $found = false;
            foreach ($tasks as &$task) {
                if (($task['id'] ?? '') === $taskId) {
                    $task['status'] = $status;
                    $task['assignee'] = $assignee;
                    $task['title'] = $title ?: $task['title'];
                    $task['description'] = $description;
                    $task['priority'] = $priority;
                    $task['updated_at'] = date('c');
                    $found = true;
                    break;
                }
            }
            unset($task);
            if ($found) {
                portal_admin_save_json('tasks.json', $tasks);
                portal_admin_log_activity('task.updated', "Updated task {$taskId}", $actorName);
                portal_admin_add_message($messages, 'success', 'Task updated successfully.');
            } else {
                portal_admin_add_message($messages, 'error', 'Task not found.');
            }
            break;

        case 'delete_task':
            if (!portal_admin_require_csrf($messages)) {
                break;
            }
            $taskId = $_POST['task_id'] ?? '';
            $tasks = portal_admin_load_json('tasks.json', []);
            foreach ($tasks as $index => $task) {
                if (($task['id'] ?? '') === $taskId) {
                    unset($tasks[$index]);
                    portal_admin_save_json('tasks.json', array_values($tasks));
                    portal_admin_log_activity('task.deleted', "Deleted task {$taskId}", $actorName);
                    portal_admin_add_message($messages, 'success', 'Task removed.');
                    break;
                }
            }
            break;

        case 'add_ledger_entry':
            if (!portal_admin_require_csrf($messages)) {
                break;
            }
            $date = $_POST['date'] ?? date('Y-m-d');
            $description = trim((string) ($_POST['description'] ?? ''));
            $type = $_POST['type'] ?? 'Income';
            $amount = (float) ($_POST['amount'] ?? 0);
            $party = trim((string) ($_POST['party'] ?? ''));
            if ($description === '' || $amount <= 0) {
                portal_admin_add_message($messages, 'error', 'Please provide a description and a valid amount.');
                break;
            }
            $ledger = portal_admin_load_json('ledger.json', []);
            $now = date('c');
            $ledger[] = [
                'id' => portal_admin_generate_id('ledger'),
                'date' => $date,
                'description' => $description,
                'type' => $type === 'Expense' ? 'Expense' : 'Income',
                'amount' => $amount,
                'party' => $party,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            portal_admin_save_json('ledger.json', $ledger);
            portal_admin_log_activity('ledger.created', "Recorded {$type} of ₹{$amount}", $actorName);
            portal_admin_add_message($messages, 'success', 'Ledger entry recorded.');
            break;

        case 'update_ledger_entry':
            if (!portal_admin_require_csrf($messages)) {
                break;
            }
            $entryId = $_POST['entry_id'] ?? '';
            $date = $_POST['date'] ?? date('Y-m-d');
            $description = trim((string) ($_POST['description'] ?? ''));
            $type = $_POST['type'] ?? 'Income';
            $amount = (float) ($_POST['amount'] ?? 0);
            $party = trim((string) ($_POST['party'] ?? ''));
            $ledger = portal_admin_load_json('ledger.json', []);
            $found = false;
            foreach ($ledger as &$entry) {
                if (($entry['id'] ?? '') === $entryId) {
                    $entry['date'] = $date;
                    $entry['description'] = $description ?: $entry['description'];
                    $entry['type'] = $type === 'Expense' ? 'Expense' : 'Income';
                    $entry['amount'] = $amount > 0 ? $amount : $entry['amount'];
                    $entry['party'] = $party;
                    $entry['updated_at'] = date('c');
                    $found = true;
                    break;
                }
            }
            unset($entry);
            if ($found) {
                portal_admin_save_json('ledger.json', $ledger);
                portal_admin_log_activity('ledger.updated', "Updated ledger entry {$entryId}", $actorName);
                portal_admin_add_message($messages, 'success', 'Ledger entry updated.');
            } else {
                portal_admin_add_message($messages, 'error', 'Ledger entry not found.');
            }
            break;

        case 'delete_ledger_entry':
            if (!portal_admin_require_csrf($messages)) {
                break;
            }
            $entryId = $_POST['entry_id'] ?? '';
            $ledger = portal_admin_load_json('ledger.json', []);
            foreach ($ledger as $index => $entry) {
                if (($entry['id'] ?? '') === $entryId) {
                    unset($ledger[$index]);
                    portal_admin_save_json('ledger.json', array_values($ledger));
                    portal_admin_log_activity('ledger.deleted', "Deleted ledger entry {$entryId}", $actorName);
                    portal_admin_add_message($messages, 'success', 'Ledger entry deleted.');
                    break;
                }
            }
            break;

        case 'save_ai_settings':
            if (!portal_admin_require_csrf($messages)) {
                break;
            }
            $apiKey = trim((string) ($_POST['api_key'] ?? ''));
            $model = trim((string) ($_POST['model'] ?? '')) ?: 'gemini-1.5-flash';
            $settings = portal_admin_load_json('settings.json', []);
            $settings['ai']['model'] = $model;
            if ($apiKey !== '') {
                $settings['ai']['api_key'] = portal_admin_encrypt_api_key($apiKey);
            }
            portal_admin_save_json('settings.json', $settings);
            portal_admin_log_activity('ai.settings', 'Updated AI automation settings', $actorName);
            portal_admin_add_message($messages, 'success', 'AI settings updated successfully.');
            break;

        case 'generate_blog_preview':
            if (!portal_admin_require_csrf($messages)) {
                break;
            }
            $prompt = (string) ($_POST['prompt'] ?? '');
            $preview = portal_admin_generate_blog_preview($prompt);
            $_SESSION['ai_preview'] = $preview;
            portal_admin_log_activity('ai.preview', 'Generated AI blog preview', $actorName);
            portal_admin_add_message($messages, 'success', 'Blog preview generated. Review it below.');
            break;

        case 'publish_blog_preview':
            if (!portal_admin_require_csrf($messages)) {
                break;
            }
            $preview = $_SESSION['ai_preview'] ?? null;
            if (!$preview) {
                portal_admin_add_message($messages, 'error', 'No blog preview is available to publish.');
                break;
            }
            $title = trim((string) ($_POST['title'] ?? ($preview['title'] ?? 'Blog Update')));
            $body = trim((string) ($_POST['body'] ?? ''));
            if ($body === '') {
                $body = strip_tags($preview['body'] ?? '');
            }
            $posts = portal_admin_load_json('blog-posts.json', []);
            $now = date('c');
            $posts[] = [
                'id' => portal_admin_generate_id('post'),
                'title' => $title,
                'content' => $body,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            portal_admin_save_json('blog-posts.json', $posts);
            $history = portal_admin_load_json('ai-history.json', []);
            $history[] = [
                'timestamp' => $now,
                'action' => 'Published blog post',
                'title' => $title,
            ];
            portal_admin_save_json('ai-history.json', $history);
            portal_admin_log_activity('ai.publish', "Published AI blog '{$title}'", $actorName);
            unset($_SESSION['ai_preview']);
            portal_admin_add_message($messages, 'success', 'Blog post published successfully.');
            break;

        case 'save_settings':
            if (!portal_admin_require_csrf($messages)) {
                break;
            }
            $settings = portal_admin_load_json('settings.json', []);
            $settings['global']['phone'] = trim((string) ($_POST['global_phone'] ?? ''));
            $settings['global']['email'] = trim((string) ($_POST['global_email'] ?? ''));
            $settings['global']['address'] = trim((string) ($_POST['global_address'] ?? ''));
            $settings['global']['banner_text'] = trim((string) ($_POST['global_banner'] ?? ''));
            $settings['homepage']['hero_text'] = trim((string) ($_POST['homepage_hero'] ?? ''));
            $settings['homepage']['highlight_offers'] = trim((string) ($_POST['homepage_highlights'] ?? ''));
            $settings['blog_defaults']['author'] = trim((string) ($_POST['blog_author'] ?? ''));
            $settings['blog_defaults']['summary'] = trim((string) ($_POST['blog_summary'] ?? ''));
            $settings['case_studies']['summary'] = trim((string) ($_POST['case_summary'] ?? ''));
            $settings['case_studies']['cta'] = trim((string) ($_POST['case_cta'] ?? ''));
            $settings['testimonials']['headline'] = trim((string) ($_POST['testimonial_headline'] ?? ''));
            $settings['testimonials']['body'] = trim((string) ($_POST['testimonial_body'] ?? ''));
            $settings['complaints']['public_intake_enabled'] = isset($_POST['public_intake_enabled']);
            portal_admin_save_json('settings.json', $settings);
            portal_admin_log_activity('settings.updated', 'Updated site and content settings', $actorName);
            portal_admin_add_message($messages, 'success', 'Settings saved successfully.');
            break;

        default:
            break;
    }
}

if (isset($_GET['export']) && $_GET['export'] === 'customers') {
    $token = $_GET['token'] ?? '';
    if (portal_admin_validate_csrf($token)) {
        $customers = portal_admin_load_json('customers.json', []);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="customers-export.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Name', 'Email', 'Phone', 'Status', 'Tags', 'Address', 'Notes', 'Updated']);
        foreach ($customers as $customer) {
            fputcsv($output, [
                $customer['name'] ?? '',
                $customer['email'] ?? '',
                $customer['phone'] ?? '',
                $customer['status'] ?? '',
                implode('; ', $customer['tags'] ?? []),
                $customer['address'] ?? '',
                $customer['notes'] ?? '',
                $customer['updated_at'] ?? '',
            ]);
        }
        fclose($output);
        exit;
    }
}

$users = portal_admin_load_json('users.json', []);
$approvals = portal_admin_load_json('approvals.json', []);
$customers = portal_admin_load_json('customers.json', []);
$complaints = portal_admin_load_json('complaints.json', []);
$tasks = portal_admin_load_json('tasks.json', []);
$ledger = portal_admin_load_json('ledger.json', []);
$settings = portal_admin_load_json('settings.json', []);
$activityLog = portal_admin_load_json(PORTAL_ACTIVITY_FILE, []);
$aiHistory = portal_admin_load_json('ai-history.json', []);
$blogPreview = $_SESSION['ai_preview'] ?? null;

function portal_admin_format_datetime(?string $value): string
{
    if (!$value) {
        return '—';
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return portal_admin_e($value);
    }
    return date('d M Y, g:i A', $timestamp);
}

function portal_admin_task_counts(array $tasks): array
{
    $counts = ['To Do' => 0, 'In Progress' => 0, 'Done' => 0];
    foreach ($tasks as $task) {
        $status = $task['status'] ?? 'To Do';
        if (!isset($counts[$status])) {
            $counts[$status] = 0;
        }
        $counts[$status]++;
    }
    return $counts;
}

function portal_admin_ledger_totals(array $ledger): array
{
    $income = 0.0;
    $expense = 0.0;
    foreach ($ledger as $entry) {
        $amount = (float) ($entry['amount'] ?? 0);
        if (($entry['type'] ?? '') === 'Expense') {
            $expense += $amount;
        } else {
            $income += $amount;
        }
    }
    return [
        'income' => $income,
        'expense' => $expense,
        'balance' => $income - $expense,
    ];
}

$taskCounts = portal_admin_task_counts($tasks);
$ledgerTotals = portal_admin_ledger_totals($ledger);
$newComplaints = array_filter($complaints, static function (array $complaint): bool {
    $created = strtotime($complaint['created_at'] ?? '');
    return $created !== false && $created >= strtotime('-7 days');
});
$pendingApprovals = array_filter($approvals, static function (array $approval): bool {
    return ($approval['status'] ?? 'pending') === 'pending';
});
$openTasks = array_filter($tasks, static function (array $task): bool {
    return ($task['status'] ?? '') !== 'Done';
});
$recentActivity = array_slice(array_reverse($activityLog), 0, 5);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Dakshayani Admin Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <style>
        :root {
            color-scheme: light;
            --bg: #f8fafc;
            --surface: #ffffff;
            --muted: #475569;
            --border: rgba(15, 23, 42, 0.08);
            --accent: #2563eb;
            --accent-strong: #1d4ed8;
            --danger: #dc2626;
            --success: #16a34a;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg);
            color: #0f172a;
        }
        header {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .container {
            width: min(1200px, 100%);
            margin: 0 auto;
            padding: 1.5rem;
        }
        .layout {
            display: grid;
            grid-template-columns: 260px 1fr;
            min-height: 100vh;
        }
        nav {
            background: var(--surface);
            border-right: 1px solid var(--border);
            padding: 1.5rem;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }
        nav h2 {
            margin-top: 0;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
        }
        nav a {
            display: block;
            padding: 0.6rem 0.8rem;
            color: inherit;
            text-decoration: none;
            border-radius: 0.75rem;
            margin-bottom: 0.25rem;
        }
        nav a.active {
            background: rgba(37, 99, 235, 0.12);
            color: var(--accent-strong);
            font-weight: 600;
        }
        nav a:hover { background: rgba(15, 23, 42, 0.05); }
        main {
            padding: 2rem;
        }
        .card-grid {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }
        .card {
            background: var(--surface);
            border-radius: 1rem;
            border: 1px solid var(--border);
            padding: 1.25rem;
            box-shadow: 0 10px 30px -25px rgba(15, 23, 42, 0.3);
        }
        .card h3 {
            margin: 0 0 0.5rem;
            font-size: 1rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        th, td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--border);
            text-align: left;
            vertical-align: top;
        }
        th { font-weight: 600; }
        .actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            border-radius: 0.75rem;
            border: none;
            padding: 0.55rem 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-primary {
            background: var(--accent);
            color: #fff;
        }
        .btn-primary:hover { background: var(--accent-strong); }
        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: inherit;
        }
        .btn-danger {
            background: var(--danger);
            color: #fff;
        }
        form {
            display: grid;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        label {
            display: grid;
            gap: 0.35rem;
            font-weight: 600;
            color: var(--muted);
            font-size: 0.9rem;
        }
        input[type='text'],
        input[type='email'],
        input[type='password'],
        input[type='date'],
        input[type='number'],
        select,
        textarea {
            width: 100%;
            border-radius: 0.75rem;
            border: 1px solid var(--border);
            padding: 0.65rem 0.75rem;
            font: inherit;
            background: #fff;
        }
        textarea { min-height: 120px; resize: vertical; }
        .messages {
            margin-bottom: 1.5rem;
            display: grid;
            gap: 0.75rem;
        }
        .message {
            border-radius: 0.75rem;
            padding: 0.85rem 1rem;
            border: 1px solid transparent;
        }
        .message.success {
            background: rgba(22, 163, 74, 0.12);
            border-color: rgba(22, 163, 74, 0.24);
        }
        .message.error {
            background: rgba(220, 38, 38, 0.12);
            border-color: rgba(220, 38, 38, 0.24);
        }
        .section-title {
            margin: 2rem 0 1rem;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.2rem 0.6rem;
            border-radius: 9999px;
            background: rgba(15, 23, 42, 0.08);
            font-size: 0.75rem;
        }
        .status-open { background: rgba(234, 179, 8, 0.18); }
        .status-done { background: rgba(22, 163, 74, 0.18); }
        .status-new { background: rgba(37, 99, 235, 0.18); }
        .note-list {
            display: grid;
            gap: 0.5rem;
            margin: 0.5rem 0 0;
            padding: 0;
            list-style: none;
        }
        .note-list li {
            background: rgba(15, 23, 42, 0.04);
            border-radius: 0.75rem;
            padding: 0.5rem 0.75rem;
        }
        @media (max-width: 1024px) {
            .layout { grid-template-columns: 1fr; }
            nav { position: static; height: auto; display: flex; overflow-x: auto; }
            nav a { margin-right: 0.75rem; white-space: nowrap; }
        }
        @media (max-width: 640px) {
            main { padding: 1.5rem 1rem 3rem; }
            header .container { padding: 1rem; }
        }
    </style>
</head>
<body>
    <header>
        <div class="container" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;">
            <div>
                <h1 style="margin:0;font-size:1.5rem;">Dakshayani Admin Control Center</h1>
                <p style="margin:0;color:var(--muted);font-size:0.95rem;">Signed in as <?= portal_admin_e($actorName); ?> · <span><?= portal_admin_format_datetime($_SESSION['last_login'] ?? null); ?></span></p>
            </div>
            <div class="actions">
                <a class="btn btn-outline" href="?view=activity">Activity log</a>
                <a class="btn btn-outline" href="logout.php">Log out</a>
            </div>
        </div>
    </header>

    <div class="layout">
        <nav>
            <h2>Navigation</h2>
            <?php
            $navItems = [
                'dashboard' => 'Overview',
                'users' => 'Users',
                'approvals' => 'Change approvals',
                'customers' => 'Customers',
                'complaints' => 'Complaints',
                'tasks' => 'Tasks',
                'ledger' => 'Accounts ledger',
                'ai' => 'AI automation',
                'settings' => 'Site settings',
                'activity' => 'Activity log',
            ];
            foreach ($navItems as $slug => $label):
                $isActive = $view === $slug;
            ?>
                <a href="?view=<?= portal_admin_e($slug); ?>" class="<?= $isActive ? 'active' : ''; ?>"><?= portal_admin_e($label); ?></a>
            <?php endforeach; ?>
        </nav>

        <main>
            <?php if ($view === '404'): ?>
                <section class="card">
                    <h2>Page not found</h2>
                    <p>The requested dashboard view could not be found. Please choose an option from the menu.</p>
                </section>
            <?php else: ?>
                <?php if ($messages): ?>
                    <div class="messages" aria-live="polite">
                        <?php foreach ($messages as $message): ?>
                            <div class="message <?= portal_admin_e($message['type']); ?>"><?= portal_admin_e($message['text']); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($view === 'dashboard'): ?>
                    <section class="card-grid">
                        <div class="card">
                            <h3>Total users</h3>
                            <p style="font-size:2rem;font-weight:700;"><?= count($users); ?></p>
                        </div>
                        <div class="card">
                            <h3>New complaints (7 days)</h3>
                            <p style="font-size:2rem;font-weight:700;"><?= count($newComplaints); ?></p>
                        </div>
                        <div class="card">
                            <h3>Pending approvals</h3>
                            <p style="font-size:2rem;font-weight:700;"><?= count($pendingApprovals); ?></p>
                        </div>
                        <div class="card">
                            <h3>Open tasks</h3>
                            <p style="font-size:2rem;font-weight:700;"><?= count($openTasks); ?></p>
                        </div>
                    </section>
                    <section class="card" style="margin-top:2rem;">
                        <h2 class="section-title" style="margin-top:0;">Recent activity</h2>
                        <?php if (!$recentActivity): ?>
                            <p>No activity logged yet.</p>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr><th>When</th><th>User</th><th>Action</th><th>Details</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentActivity as $entry): ?>
                                        <tr>
                                            <td><?= portal_admin_format_datetime($entry['timestamp'] ?? null); ?></td>
                                            <td><?= portal_admin_e($entry['user'] ?? 'System'); ?></td>
                                            <td><?= portal_admin_e($entry['action'] ?? ''); ?></td>
                                            <td><?= portal_admin_e($entry['details'] ?? ''); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </section>
                <?php elseif ($view === 'users'): ?>
                    <section class="card">
                        <h2 class="section-title" style="margin-top:0;">User directory</h2>
                        <table>
                            <thead>
                                <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last updated</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?= portal_admin_e($user['name'] ?? ''); ?></td>
                                        <td><?= portal_admin_e($user['email'] ?? ''); ?></td>
                                        <td><?= portal_admin_e(PORTAL_ROLE_LABELS[$user['role']] ?? $user['role'] ?? ''); ?></td>
                                        <td><span class="badge"><?= portal_admin_e(ucfirst($user['status'] ?? 'active')); ?></span></td>
                                        <td><?= portal_admin_format_datetime($user['updated_at'] ?? null); ?></td>
                                        <td>
                                            <details>
                                                <summary class="btn btn-outline" style="display:inline-flex;cursor:pointer;">Manage</summary>
                                                <div style="padding:0.75rem 0;">
                                                    <form method="post" style="margin:0;" onsubmit="return confirm('Apply these changes to the user?');">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>" />
                                                        <input type="hidden" name="action" value="update_user" />
                                                        <input type="hidden" name="user_id" value="<?= portal_admin_e($user['id'] ?? ''); ?>" />
                                                        <label>Name<input type="text" name="name" value="<?= portal_admin_e($user['name'] ?? ''); ?>" /></label>
                                                        <label>Email<input type="email" name="email" value="<?= portal_admin_e($user['email'] ?? ''); ?>" required /></label>
                                                        <label>Role
                                                            <select name="role">
                                                                <?php foreach (PORTAL_ROLE_LABELS as $value => $label): ?>
                                                                    <option value="<?= portal_admin_e($value); ?>" <?= (($user['role'] ?? '') === $value) ? 'selected' : ''; ?>><?= portal_admin_e($label); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </label>
                                                        <label>Status
                                                            <select name="status">
                                                                <?php foreach (['active','invited','suspended'] as $status): ?>
                                                                    <option value="<?= $status; ?>" <?= (($user['status'] ?? 'active') === $status) ? 'selected' : ''; ?>><?= ucfirst($status); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </label>
                                                        <label>New password<input type="password" name="password" placeholder="Leave blank to keep current" /></label>
                                                        <label><input type="checkbox" name="force_reset" <?= !empty($user['force_reset']) ? 'checked' : ''; ?> /> Require password reset</label>
                                                        <div class="actions" style="margin-top:0.75rem;">
                                                            <button type="submit" class="btn btn-primary">Save changes</button>
                                                        </div>
                                                    </form>
                                                    <form method="post" style="margin-top:0.75rem;" onsubmit="return confirm('Force a password reset for this user?');">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>" />
                                                        <input type="hidden" name="action" value="force_reset_password" />
                                                        <input type="hidden" name="user_id" value="<?= portal_admin_e($user['id'] ?? ''); ?>" />
                                                        <button type="submit" class="btn btn-outline">Flag password reset</button>
                                                    </form>
                                                    <form method="post" style="margin-top:0.75rem;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>" />
                                                        <input type="hidden" name="action" value="delete_user" />
                                                        <input type="hidden" name="user_id" value="<?= portal_admin_e($user['id'] ?? ''); ?>" />
                                                        <button type="submit" class="btn btn-danger">Delete user</button>
                                                    </form>
                                                </div>
                                            </details>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <h3 class="section-title">Create new user</h3>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>" />
                            <input type="hidden" name="action" value="add_user" />
                            <label>Name<input type="text" name="name" required /></label>
                            <label>Email<input type="email" name="email" required /></label>
                            <label>Role
                                <select name="role">
                                    <?php foreach (PORTAL_ROLE_LABELS as $value => $label): ?>
                                        <option value="<?= portal_admin_e($value); ?>"><?= portal_admin_e($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>Status
                                <select name="status">
                                    <option value="active">Active</option>
                                    <option value="invited">Invited</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </label>
                            <label>Password<input type="password" name="password" required /></label>
                            <label><input type="checkbox" name="force_reset" /> Require password reset at next login</label>
                            <button type="submit" class="btn btn-primary">Create user</button>
                        </form>
                    </section>
                <?php elseif ($view === 'approvals'): ?>
                    <section class="card">
                        <h2 class="section-title" style="margin-top:0;">Pending change requests</h2>
                        <?php if (!$approvals): ?>
                            <p>No pending approvals at the moment.</p>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr><th>Request</th><th>Target</th><th>Changes</th><th>Actions</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($approvals as $request): ?>
                                        <tr>
                                            <td><strong><?= portal_admin_e($request['id'] ?? ''); ?></strong><br><span class="badge status-new">Pending</span></td>
                                            <td><?= portal_admin_e(($request['target_type'] ?? '') . ' → ' . ($request['target_id'] ?? '')); ?></td>
                                            <td>
                                                <?php foreach (($request['changes'] ?? []) as $field => $change): ?>
                                                    <p><strong><?= portal_admin_e($field); ?></strong>: <?= portal_admin_e($change['old'] ?? ''); ?> → <span style="color:var(--accent-strong);font-weight:600;"><?= portal_admin_e($change['new'] ?? ''); ?></span></p>
                                                <?php endforeach; ?>
                                            </td>
                                            <td>
                                                <form method="post" onsubmit="return confirm('Approve and apply this change?');">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>" />
                                                    <input type="hidden" name="action" value="approve_change" />
                                                    <input type="hidden" name="request_id" value="<?= portal_admin_e($request['id'] ?? ''); ?>" />
                                                    <button type="submit" class="btn btn-primary">Approve</button>
                                                </form>
                                                <form method="post" style="margin-top:0.5rem;" onsubmit="return confirm('Reject this change request?');">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>" />
                                                    <input type="hidden" name="action" value="reject_change" />
                                                    <input type="hidden" name="request_id" value="<?= portal_admin_e($request['id'] ?? ''); ?>" />
                                                    <button type="submit" class="btn btn-danger">Reject</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </section>
                <?php elseif ($view === 'customers'): ?>
                    <section class="card">
                        <h2 class="section-title" style="margin-top:0;">Customers</h2>
                        <div class="actions" style="margin-bottom:1rem;">
                            <a class="btn btn-outline" href="?view=customers&amp;export=customers&amp;token=<?= $csrfToken; ?>">Export to CSV</a>
                        </div>
                        <table>
                            <thead>
                                <tr><th>Name</th><th>Contact</th><th>Status</th><th>Tags</th><th>Updated</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customers as $customer): ?>
                                    <tr>
                                        <td><?= portal_admin_e($customer['name'] ?? ''); ?></td>
                                        <td>
                                            <?= portal_admin_e($customer['email'] ?? ''); ?><br />
                                            <?= portal_admin_e($customer['phone'] ?? ''); ?>
                                        </td>
                                        <td><span class="badge status-open"><?= portal_admin_e(ucfirst($customer['status'] ?? 'active')); ?></span></td>
                                        <td><?= portal_admin_e(implode(', ', $customer['tags'] ?? [])); ?></td>
                                        <td><?= portal_admin_format_datetime($customer['updated_at'] ?? null); ?></td>
                                        <td>
                                            <details>
                                                <summary class="btn btn-outline" style="display:inline-flex;cursor:pointer;">Edit</summary>
                                                <div style="padding:0.75rem 0;">
                                                    <form method="post" style="margin:0;" onsubmit="return confirm('Save changes to this customer?');">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>" />
                                                        <input type="hidden" name="action" value="update_customer" />
                                                        <input type="hidden" name="customer_id" value="<?= portal_admin_e($customer['id'] ?? ''); ?>" />
                                                        <label>Name<input type="text" name="name" value="<?= portal_admin_e($customer['name'] ?? ''); ?>" /></label>
                                                        <label>Email<input type="email" name="email" value="<?= portal_admin_e($customer['email'] ?? ''); ?>" /></label>
                                                        <label>Phone<input type="text" name="phone" value="<?= portal_admin_e($customer['phone'] ?? ''); ?>" /></label>
                                                        <label>Status
                                                            <select name="status">
                                                                <?php foreach (['active','prospect','inactive'] as $status): ?>
                                                                    <option value="<?= $status; ?>" <?= (($customer['status'] ?? 'active') === $status) ? 'selected' : ''; ?>><?= ucfirst($status); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </label>
                                                        <label>Tags (comma separated)<input type="text" name="tags" value="<?= portal_admin_e(implode(', ', $customer['tags'] ?? [])); ?>" /></label>
                                                        <label>Address<textarea name="address"><?= portal_admin_e($customer['address'] ?? ''); ?></textarea></label>
                                                        <label>Notes<textarea name="notes"><?= portal_admin_e($customer['notes'] ?? ''); ?></textarea></label>
                                                        <button type="submit" class="btn btn-primary">Save changes</button>
                                                    </form>
                                                    <form method="post" style="margin-top:0.75rem;" onsubmit="return confirm('Delete this customer? This cannot be undone.');">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>" />
                                                        <input type="hidden" name="action" value="delete_customer" />
                                                        <input type="hidden" name="customer_id" value="<?= portal_admin_e($customer['id'] ?? ''); ?>" />
                                                        <button type="submit" class="btn btn-danger">Delete</button>
                                                    </form>
                                                </div>
                                            </details>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <h3 class="section-title">Add customer</h3>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>" />
                            <input type="hidden" name="action" value="add_customer" />
                            <label>Name<input type="text" name="name" required /></label>
                            <label>Email<input type="email" name="email" /></label>
                            <label>Phone<input type="text" name="phone" /></label>
                            <label>Status
                                <select name="status">
                                    <option value="active">Active</option>
                                    <option value="prospect">Prospect</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </label>
                            <label>Tags<input type="text" name="tags" placeholder="solar, priority" /></label>
                            <label>Address<textarea name="address"></textarea></label>
                            <label>Notes<textarea name="notes"></textarea></label>
                            <button type="submit" class="btn btn-primary">Add customer</button>
                        </form>
                    </section>
                <?php elseif ($view === 'complaints'): ?>
                    <section class="card">
                        <h2 class="section-title" style="margin-top:0;">Complaints and tickets</h2>
                        <table>
                            <thead>
                                <tr><th>Ticket</th><th>Customer</th><th>Status</th><th>Assignee</th><th>Updated</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($complaints as $complaint): ?>
                                    <tr>
                                        <td><strong><?= portal_admin_e($complaint['id'] ?? ''); ?></strong><br><span class="badge status-new"><?= portal_admin_e($complaint['status'] ?? 'New'); ?></span></td>
                                        <td><?= portal_admin_e($complaint['customer_name'] ?? ''); ?><br><?= portal_admin_e($complaint['customer_email'] ?? ''); ?></td>
                                        <td><?= portal_admin_e($complaint['status'] ?? 'New'); ?></td>
                                        <td><?= portal_admin_e($complaint['assignee'] ?? 'Unassigned'); ?></td>
                                        <td><?= portal_admin_format_datetime($complaint['updated_at'] ?? null); ?></td>
                                        <td>
                                            <details>
                                                <summary class="btn btn-outline" style="display:inline-flex;cursor:pointer;">Manage</summary>
                                                <div style="padding:0.75rem 0;">
                                                    <p><strong>Category:</strong> <?= portal_admin_e($complaint['category'] ?? ''); ?></p>
                                                    <p><strong>Description:</strong> <?= portal_admin_e($complaint['description'] ?? ''); ?></p>
                                                    <form method="post" onsubmit="return confirm('Save updates to this complaint?');">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>" />
                                                        <input type="hidden" name="action" value="update_complaint" />
                                                        <input type="hidden" name="complaint_id" value="<?= portal_admin_e($complaint['id'] ?? ''); ?>" />
                                                        <label>Status
                                                            <select name="status">
                                                                <?php foreach (['New','In Progress','Awaiting Reply','Resolved'] as $status): ?>
                                                                    <option value="<?= $status; ?>" <?= (($complaint['status'] ?? 'New') === $status) ? 'selected' : ''; ?>><?= $status; ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </label>
                                                        <label>Assignee<input type="text" name="assignee" value="<?= portal_admin_e($complaint['assignee'] ?? ''); ?>" /></label>
                                                        <button type="submit" class="btn btn-primary">Update ticket</button>
                                                    </form>
                                                    <form method="post" style="margin-top:0.75rem;">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>" />
                                                        <input type="hidden" name="action" value="add_complaint_note" />
                                                        <input type="hidden" name="complaint_id" value="<?= portal_admin_e($complaint['id'] ?? ''); ?>" />
                                                        <label>Add internal note<textarea name="note" required></textarea></label>
                                                        <button type="submit" class="btn btn-outline">Add note</button>
                                                    </form>
                                                    <form method="post" style="margin-top:0.75rem;" onsubmit="return confirm('Delete this complaint?');">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>" />
                                                        <input type="hidden" name="action" value="delete_complaint" />
                                                        <input type="hidden" name="complaint_id" value="<?= portal_admin_e($complaint['id'] ?? ''); ?>" />
                                                        <button type="submit" class="btn btn-danger">Delete</button>
                                                    </form>
                                                    <?php if (!empty($complaint['notes'])): ?>
                                                        <h4 class="section-title" style="margin:1rem 0 0.5rem;">Timeline</h4>
                                                        <ul class="note-list">
                                                            <?php foreach ($complaint['notes'] as $note): ?>
                                                                <li>
                                                                    <strong><?= portal_admin_e($note['author'] ?? ''); ?></strong>
                                                                    <small style="display:block;color:var(--muted);"><?= portal_admin_format_datetime($note['timestamp'] ?? null); ?></small>
                                                                    <p style="margin:0;"><?= portal_admin_e($note['message'] ?? ''); ?></p>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php endif; ?>
                                                </div>
                                            </details>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <h3 class="section-title">Create complaint</h3>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>" />
                            <input type="hidden" name="action" value="create_complaint" />
                            <label>Customer name<input type="text" name="customer_name" required /></label>
                            <label>Customer email<input type="email" name="customer_email" /></label>
                            <label>Customer phone<input type="text" name="customer_phone" /></label>
                            <label>Category<input type="text" name="category" value="General" /></label>
                            <label>Priority
                                <select name="priority">
                                    <option value="normal">Normal</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </label>
                            <label>Assign to<input type="text" name="assignee" placeholder="Team member" /></label>
                            <label>Description<textarea name="description" required></textarea></label>
                            <label>Source
                                <select name="source">
                                    <option value="internal">Internal</option>
                                    <option value="public">Public</option>
                                </select>
                            </label>
                            <button type="submit" class="btn btn-primary">Create ticket</button>
                        </form>
                    </section>
                <?php elseif ($view === 'tasks'): ?>
                    <section class="card">
                        <h2 class="section-title" style="margin-top:0;">Task board</h2>
                        <div class="card-grid">
                            <div class="card">
                                <h3>To Do <span class="badge status-new"><?= $taskCounts['To Do'] ?? 0; ?></span></h3>
                                <ul class="note-list">
                                    <?php foreach ($tasks as $task): ?>
                                        <?php if (($task['status'] ?? 'To Do') === 'To Do'): ?>
                                            <li>
                                                <strong><?= portal_admin_e($task['title'] ?? ''); ?></strong>
                                                <small><?= portal_admin_e($task['priority'] ?? 'medium'); ?> · <?= portal_admin_e($task['assignee'] ?? 'Unassigned'); ?></small>
                                            </li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <div class="card">
                                <h3>In Progress <span class="badge status-open"><?= $taskCounts['In Progress'] ?? 0; ?></span></h3>
                                <ul class="note-list">
                                    <?php foreach ($tasks as $task): ?>
                                        <?php if (($task['status'] ?? '') === 'In Progress'): ?>
                                            <li>
                                                <strong><?= portal_admin_e($task['title'] ?? ''); ?></strong>
                                                <small><?= portal_admin_e($task['priority'] ?? 'medium'); ?> · <?= portal_admin_e($task['assignee'] ?? 'Unassigned'); ?></small>
                                            </li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <div class="card">
                                <h3>Done <span class="badge status-done"><?= $taskCounts['Done'] ?? 0; ?></span></h3>
                                <ul class="note-list">
                                    <?php foreach ($tasks as $task): ?>
                                        <?php if (($task['status'] ?? '') === 'Done'): ?>
                                            <li>
                                                <strong><?= portal_admin_e($task['title'] ?? ''); ?></strong>
                                                <small><?= portal_admin_e($task['priority'] ?? 'medium'); ?> · <?= portal_admin_e($task['assignee'] ?? 'Unassigned'); ?></small>
                                            </li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                        <h3 class="section-title">Add task</h3>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>" />
                            <input type="hidden" name="action" value="add_task" />
                            <label>Title<input type="text" name="title" required /></label>
                            <label>Description<textarea name="description"></textarea></label>
                            <label>Priority
                                <select name="priority">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </label>
                            <label>Assignee<input type="text" name="assignee" /></label>
                            <button type="submit" class="btn btn-primary">Add task</button>
                        </form>
                        <h3 class="section-title">Update existing task</h3>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>" />
                            <input type="hidden" name="action" value="update_task" />
                            <label>Task ID<input type="text" name="task_id" required /></label>
                            <label>Title<input type="text" name="title" /></label>
                            <label>Description<textarea name="description"></textarea></label>
                            <label>Status
                                <select name="status">
                                    <option value="To Do">To Do</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Done">Done</option>
                                </select>
                            </label>
                            <label>Priority
                                <select name="priority">
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </label>
                            <label>Assignee<input type="text" name="assignee" /></label>
                            <button type="submit" class="btn btn-outline">Update task</button>
                        </form>
                        <h3 class="section-title">Delete task</h3>
                        <form method="post" onsubmit="return confirm('Delete this task?');">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>" />
                            <input type="hidden" name="action" value="delete_task" />
                            <label>Task ID<input type="text" name="task_id" required /></label>
                            <button type="submit" class="btn btn-danger">Delete task</button>
                        </form>
                    </section>
                <?php elseif ($view === 'ledger'): ?>
                    <section class="card">
                        <h2 class="section-title" style="margin-top:0;">Accounts ledger</h2>
                        <div class="card-grid">
                            <div class="card"><h3>Total income</h3><p style="font-size:1.5rem;font-weight:700;">₹<?= number_format($ledgerTotals['income'], 2); ?></p></div>
                            <div class="card"><h3>Total expenses</h3><p style="font-size:1.5rem;font-weight:700;">₹<?= number_format($ledgerTotals['expense'], 2); ?></p></div>
                            <div class="card"><h3>Current balance</h3><p style="font-size:1.5rem;font-weight:700;">₹<?= number_format($ledgerTotals['balance'], 2); ?></p></div>
                        </div>
                        <table>
                            <thead>
                                <tr><th>Date</th><th>Description</th><th>Type</th><th>Amount</th><th>Party</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ledger as $entry): ?>
                                    <tr>
                                        <td><?= portal_admin_e($entry['date'] ?? ''); ?></td>
                                        <td><?= portal_admin_e($entry['description'] ?? ''); ?></td>
                                        <td><?= portal_admin_e($entry['type'] ?? ''); ?></td>
                                        <td>₹<?= number_format((float) ($entry['amount'] ?? 0), 2); ?></td>
                                        <td><?= portal_admin_e($entry['party'] ?? ''); ?></td>
                                        <td>
                                            <details>
                                                <summary class="btn btn-outline" style="display:inline-flex;cursor:pointer;">Edit</summary>
                                                <div style="padding:0.75rem 0;">
                                                    <form method="post" onsubmit="return confirm('Save changes to this ledger entry?');">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>" />
                                                        <input type="hidden" name="action" value="update_ledger_entry" />
                                                        <input type="hidden" name="entry_id" value="<?= portal_admin_e($entry['id'] ?? ''); ?>" />
                                                        <label>Date<input type="date" name="date" value="<?= portal_admin_e($entry['date'] ?? date('Y-m-d')); ?>" /></label>
                                                        <label>Description<input type="text" name="description" value="<?= portal_admin_e($entry['description'] ?? ''); ?>" /></label>
                                                        <label>Type
                                                            <select name="type">
                                                                <option value="Income" <?= (($entry['type'] ?? 'Income') === 'Income') ? 'selected' : ''; ?>>Income</option>
                                                                <option value="Expense" <?= (($entry['type'] ?? '') === 'Expense') ? 'selected' : ''; ?>>Expense</option>
                                                            </select>
                                                        </label>
                                                        <label>Amount<input type="number" step="0.01" name="amount" value="<?= portal_admin_e((string) ($entry['amount'] ?? 0)); ?>" /></label>
                                                        <label>Party<input type="text" name="party" value="<?= portal_admin_e($entry['party'] ?? ''); ?>" /></label>
                                                        <button type="submit" class="btn btn-primary">Update entry</button>
                                                    </form>
                                                    <form method="post" style="margin-top:0.75rem;" onsubmit="return confirm('Delete this ledger entry?');">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>" />
                                                        <input type="hidden" name="action" value="delete_ledger_entry" />
                                                        <input type="hidden" name="entry_id" value="<?= portal_admin_e($entry['id'] ?? ''); ?>" />
                                                        <button type="submit" class="btn btn-danger">Delete</button>
                                                    </form>
                                                </div>
                                            </details>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <h3 class="section-title">Add ledger entry</h3>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>" />
                            <input type="hidden" name="action" value="add_ledger_entry" />
                            <label>Date<input type="date" name="date" value="<?= date('Y-m-d'); ?>" /></label>
                            <label>Description<input type="text" name="description" required /></label>
                            <label>Type
                                <select name="type">
                                    <option value="Income">Income</option>
                                    <option value="Expense">Expense</option>
                                </select>
                            </label>
                            <label>Amount<input type="number" step="0.01" name="amount" required /></label>
                            <label>Party<input type="text" name="party" /></label>
                            <button type="submit" class="btn btn-primary">Record entry</button>
                        </form>
                    </section>
                <?php elseif ($view === 'ai'): ?>
                    <section class="card">
                        <h2 class="section-title" style="margin-top:0;">AI automation</h2>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>" />
                            <input type="hidden" name="action" value="save_ai_settings" />
                            <label>AI API key<input type="text" name="api_key" placeholder="Enter new key to update" /></label>
                            <label>Model name<input type="text" name="model" value="<?= portal_admin_e($settings['ai']['model'] ?? 'gemini-1.5-flash'); ?>" /></label>
                            <button type="submit" class="btn btn-primary">Save settings</button>
                        </form>
                        <h3 class="section-title">Generate blog preview</h3>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>" />
                            <input type="hidden" name="action" value="generate_blog_preview" />
                            <label>Prompt<textarea name="prompt" placeholder="Summarise the latest installation or update"></textarea></label>
                            <button type="submit" class="btn btn-outline">Generate preview</button>
                        </form>
                        <?php if ($blogPreview): ?>
                            <div class="card" style="margin-top:1.5rem;">
                                <h3>AI draft: <?= portal_admin_e($blogPreview['title'] ?? 'Draft blog post'); ?></h3>
                                <div><?= $blogPreview['body'] ?? ''; ?></div>
                                <form method="post" style="margin-top:1rem;">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>" />
                                    <input type="hidden" name="action" value="publish_blog_preview" />
                                    <label>Title<input type="text" name="title" value="<?= portal_admin_e($blogPreview['title'] ?? 'Draft blog post'); ?>" /></label>
                                    <label>Final content<textarea name="body"><?= strip_tags($blogPreview['body'] ?? ''); ?></textarea></label>
                                    <button type="submit" class="btn btn-primary">Publish to blog list</button>
                                </form>
                            </div>
                        <?php endif; ?>
                        <h3 class="section-title">Recent AI actions</h3>
                        <?php if (!$aiHistory): ?>
                            <p>No AI actions logged yet.</p>
                        <?php else: ?>
                            <table>
                                <thead><tr><th>When</th><th>Action</th><th>Details</th></tr></thead>
                                <tbody>
                                    <?php foreach (array_slice(array_reverse($aiHistory), 0, 10) as $entry): ?>
                                        <tr>
                                            <td><?= portal_admin_format_datetime($entry['timestamp'] ?? null); ?></td>
                                            <td><?= portal_admin_e($entry['action'] ?? ''); ?></td>
                                            <td><?= portal_admin_e($entry['title'] ?? ''); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </section>
                <?php elseif ($view === 'settings'): ?>
                    <section class="card">
                        <h2 class="section-title" style="margin-top:0;">Site and content settings</h2>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>" />
                            <input type="hidden" name="action" value="save_settings" />
                            <h3>Global</h3>
                            <label>Phone<input type="text" name="global_phone" value="<?= portal_admin_e($settings['global']['phone'] ?? ''); ?>" /></label>
                            <label>Email<input type="email" name="global_email" value="<?= portal_admin_e($settings['global']['email'] ?? ''); ?>" /></label>
                            <label>Address<textarea name="global_address"><?= portal_admin_e($settings['global']['address'] ?? ''); ?></textarea></label>
                            <label>Banner text<textarea name="global_banner"><?= portal_admin_e($settings['global']['banner_text'] ?? ''); ?></textarea></label>
                            <h3>Homepage</h3>
                            <label>Hero text<textarea name="homepage_hero"><?= portal_admin_e($settings['homepage']['hero_text'] ?? ''); ?></textarea></label>
                            <label>Highlight offers<textarea name="homepage_highlights"><?= portal_admin_e($settings['homepage']['highlight_offers'] ?? ''); ?></textarea></label>
                            <h3>Blog defaults</h3>
                            <label>Default author<input type="text" name="blog_author" value="<?= portal_admin_e($settings['blog_defaults']['author'] ?? ''); ?>" /></label>
                            <label>Default summary<textarea name="blog_summary"><?= portal_admin_e($settings['blog_defaults']['summary'] ?? ''); ?></textarea></label>
                            <h3>Case studies</h3>
                            <label>Summary<textarea name="case_summary"><?= portal_admin_e($settings['case_studies']['summary'] ?? ''); ?></textarea></label>
                            <label>CTA text<input type="text" name="case_cta" value="<?= portal_admin_e($settings['case_studies']['cta'] ?? ''); ?>" /></label>
                            <h3>Testimonials</h3>
                            <label>Headline<input type="text" name="testimonial_headline" value="<?= portal_admin_e($settings['testimonials']['headline'] ?? ''); ?>" /></label>
                            <label>Body<textarea name="testimonial_body"><?= portal_admin_e($settings['testimonials']['body'] ?? ''); ?></textarea></label>
                            <label><input type="checkbox" name="public_intake_enabled" <?= !empty($settings['complaints']['public_intake_enabled']) ? 'checked' : ''; ?> /> Enable public complaint intake</label>
                            <button type="submit" class="btn btn-primary">Save all settings</button>
                        </form>
                    </section>
                <?php elseif ($view === 'activity'): ?>
                    <section class="card">
                        <h2 class="section-title" style="margin-top:0;">Activity log</h2>
                        <table>
                            <thead><tr><th>When</th><th>User</th><th>Action</th><th>Details</th></tr></thead>
                            <tbody>
                                <?php foreach (array_reverse($activityLog) as $entry): ?>
                                    <tr>
                                        <td><?= portal_admin_format_datetime($entry['timestamp'] ?? null); ?></td>
                                        <td><?= portal_admin_e($entry['user'] ?? 'System'); ?></td>
                                        <td><?= portal_admin_e($entry['action'] ?? ''); ?></td>
                                        <td><?= portal_admin_e($entry['details'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </section>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
