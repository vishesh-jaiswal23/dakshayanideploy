<?php
session_start();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

const DATA_FILE = __DIR__ . '/data/portal-state.json';

function default_portal_state(): array
{
    return [
        'last_updated' => date('c'),
        'site_settings' => [
            'company_focus' => 'Utility-scale and rooftop solar EPC projects, O&M, and financing assistance across Jharkhand and Bihar.',
            'primary_contact' => 'Deepak Entranchi',
            'support_email' => 'support@dakshayanienterprises.com',
            'support_phone' => '+91 62030 01452',
            'announcement' => 'Welcome to the live operations console. Track projects, team workload, and customer updates in real time.'
        ],
        'users' => [],
        'projects' => [],
        'tasks' => [],
        'activity_log' => []
    ];
}

function load_portal_state(): array
{
    if (!file_exists(DATA_FILE)) {
        return default_portal_state();
    }

    $json = file_get_contents(DATA_FILE);
    $data = json_decode($json, true);

    if (!is_array($data)) {
        return default_portal_state();
    }

    $data = array_merge(default_portal_state(), $data);

    foreach (['users', 'projects', 'tasks', 'activity_log'] as $key) {
        if (!isset($data[$key]) || !is_array($data[$key])) {
            $data[$key] = [];
        }
    }

    return $data;
}

function save_portal_state(array $state): bool
{
    $state['last_updated'] = date('c');
    $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        return false;
    }

    return (bool) file_put_contents(DATA_FILE, $json, LOCK_EX);
}

function add_activity(array &$state, string $event, string $actor = 'Admin'): void
{
    try {
        $id = 'log_' . bin2hex(random_bytes(4));
    } catch (Exception $e) {
        $id = 'log_' . uniqid();
    }

    array_unshift($state['activity_log'], [
        'id' => $id,
        'event' => $event,
        'actor' => $actor,
        'timestamp' => date('c')
    ]);

    $state['activity_log'] = array_slice($state['activity_log'], 0, 30);
}

function flash(string $type, string $message): void
{
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = ['success' => [], 'error' => []];
    }

    $_SESSION['flash'][$type][] = $message;
}

function redirect_with_flash(): void
{
    header('Location: admin-dashboard.php');
    exit;
}

$state = load_portal_state();

if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        flash('error', 'Security token mismatch. Please try again.');
        redirect_with_flash();
    }

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'update_site_settings':
            $companyFocus = trim($_POST['company_focus'] ?? '');
            $primaryContact = trim($_POST['primary_contact'] ?? '');
            $supportEmail = filter_var(trim($_POST['support_email'] ?? ''), FILTER_VALIDATE_EMAIL);
            $supportPhone = trim($_POST['support_phone'] ?? '');
            $announcement = trim($_POST['announcement'] ?? '');

            if ($companyFocus === '' || $primaryContact === '' || !$supportEmail) {
                flash('error', 'Please provide the focus, primary contact, and a valid support email.');
                redirect_with_flash();
            }

            $state['site_settings'] = [
                'company_focus' => $companyFocus,
                'primary_contact' => $primaryContact,
                'support_email' => $supportEmail,
                'support_phone' => $supportPhone,
                'announcement' => $announcement
            ];
            add_activity($state, 'Updated site configuration and public contact details.', $_SESSION['display_name'] ?? 'Admin');
            if (save_portal_state($state)) {
                flash('success', 'Site settings saved successfully.');
            } else {
                flash('error', 'Unable to save site settings. Please retry.');
            }
            redirect_with_flash();
        case 'create_user':
            $name = trim($_POST['name'] ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $role = $_POST['role'] ?? 'employee';
            $phone = trim($_POST['phone'] ?? '');
            $status = $_POST['status'] ?? 'active';
            $notes = trim($_POST['notes'] ?? '');

            $validRoles = ['admin', 'installer', 'customer', 'referrer', 'employee'];
            $validStatuses = ['active', 'pending', 'onboarding', 'suspended', 'disabled'];

            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, $validRoles, true)) {
                flash('error', 'Please provide a name, valid email, and role for the new account.');
                redirect_with_flash();
            }

            if (!in_array($status, $validStatuses, true)) {
                $status = 'active';
            }

            foreach ($state['users'] as $user) {
                if (strcasecmp($user['email'], $email) === 0) {
                    flash('error', 'An account with this email already exists.');
                    redirect_with_flash();
                }
            }

            try {
                $id = 'usr_' . bin2hex(random_bytes(4));
            } catch (Exception $e) {
                $id = 'usr_' . uniqid();
            }

            $state['users'][] = [
                'id' => $id,
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'phone' => $phone,
                'status' => $status,
                'notes' => $notes,
                'created_at' => date('Y-m-d')
            ];
            add_activity($state, "Created $role account for $name.", $_SESSION['display_name'] ?? 'Admin');
            if (save_portal_state($state)) {
                flash('success', 'New account created successfully. Share credentials with the user.');
            } else {
                flash('error', 'Unable to save the new account. Please try again.');
            }
            redirect_with_flash();
        case 'update_user_status':
            $userId = $_POST['user_id'] ?? '';
            $status = $_POST['status'] ?? 'active';
            $notes = trim($_POST['notes'] ?? '');
            $validStatuses = ['active', 'pending', 'onboarding', 'suspended', 'disabled'];

            $found = false;
            foreach ($state['users'] as &$user) {
                if ($user['id'] === $userId) {
                    $found = true;
                    $oldStatus = $user['status'] ?? '';
                    if (!in_array($status, $validStatuses, true)) {
                        $status = $oldStatus;
                    }
                    $user['status'] = $status;
                    if ($notes !== '') {
                        $user['notes'] = $notes;
                    }
                    add_activity($state, "Updated {$user['name']}'s status to {$user['status']}.", $_SESSION['display_name'] ?? 'Admin');
                    break;
                }
            }
            unset($user);

            if (!$found) {
                flash('error', 'Unable to locate the selected account.');
                redirect_with_flash();
            }

            if (save_portal_state($state)) {
                flash('success', 'Account details updated.');
            } else {
                flash('error', 'Failed to update the account. Please retry.');
            }
            redirect_with_flash();
        case 'delete_user':
            $userId = $_POST['user_id'] ?? '';
            $initialCount = count($state['users']);
            $state['users'] = array_values(array_filter($state['users'], static fn($user) => $user['id'] !== $userId));

            if ($initialCount === count($state['users'])) {
                flash('error', 'Account already removed or not found.');
                redirect_with_flash();
            }

            add_activity($state, 'Removed a user account from the portal.', $_SESSION['display_name'] ?? 'Admin');
            if (save_portal_state($state)) {
                flash('success', 'Account removed successfully.');
            } else {
                flash('error', 'Unable to remove the account. Please retry.');
            }
            redirect_with_flash();
        case 'create_project':
            $name = trim($_POST['project_name'] ?? '');
            $owner = trim($_POST['project_owner'] ?? '');
            $stage = trim($_POST['project_stage'] ?? '');
            $status = $_POST['project_status'] ?? 'on-track';
            $targetDate = trim($_POST['target_date'] ?? '');

            if ($name === '' || $owner === '' || $stage === '') {
                flash('error', 'Projects need a name, stage, and owner.');
                redirect_with_flash();
            }

            $validStatuses = ['on-track', 'planning', 'at-risk', 'delayed', 'completed'];
            if (!in_array($status, $validStatuses, true)) {
                $status = 'on-track';
            }

            try {
                $id = 'proj_' . bin2hex(random_bytes(4));
            } catch (Exception $e) {
                $id = 'proj_' . uniqid();
            }

            $state['projects'][] = [
                'id' => $id,
                'name' => $name,
                'owner' => $owner,
                'stage' => $stage,
                'status' => $status,
                'target_date' => $targetDate
            ];

            add_activity($state, "Logged new project $name.", $_SESSION['display_name'] ?? 'Admin');
            if (save_portal_state($state)) {
                flash('success', 'Project added to the tracker.');
            } else {
                flash('error', 'Unable to save the new project. Please try again.');
            }
            redirect_with_flash();
        case 'update_project':
            $projectId = $_POST['project_id'] ?? '';
            $stage = trim($_POST['stage'] ?? '');
            $status = $_POST['status'] ?? 'on-track';
            $targetDate = trim($_POST['target_date'] ?? '');

            $validStatuses = ['on-track', 'planning', 'at-risk', 'delayed', 'completed'];
            if (!in_array($status, $validStatuses, true)) {
                $status = 'on-track';
            }

            $found = false;
            foreach ($state['projects'] as &$project) {
                if ($project['id'] === $projectId) {
                    $found = true;
                    $project['stage'] = $stage !== '' ? $stage : $project['stage'];
                    $project['status'] = $status;
                    if ($targetDate !== '') {
                        $project['target_date'] = $targetDate;
                    }
                    add_activity($state, "Updated {$project['name']} project details.", $_SESSION['display_name'] ?? 'Admin');
                    break;
                }
            }
            unset($project);

            if (!$found) {
                flash('error', 'Project not found.');
                redirect_with_flash();
            }

            if (save_portal_state($state)) {
                flash('success', 'Project updated successfully.');
            } else {
                flash('error', 'Unable to update the project. Please retry.');
            }
            redirect_with_flash();
        case 'create_task':
            $title = trim($_POST['task_title'] ?? '');
            $assignee = trim($_POST['task_assignee'] ?? '');
            $status = $_POST['task_status'] ?? 'Pending';
            $dueDate = trim($_POST['task_due_date'] ?? '');

            if ($title === '' || $assignee === '') {
                flash('error', 'Tasks require a title and an assignee.');
                redirect_with_flash();
            }

            $validStatuses = ['Pending', 'In progress', 'Blocked', 'Completed'];
            if (!in_array($status, $validStatuses, true)) {
                $status = 'Pending';
            }

            try {
                $id = 'task_' . bin2hex(random_bytes(4));
            } catch (Exception $e) {
                $id = 'task_' . uniqid();
            }

            $state['tasks'][] = [
                'id' => $id,
                'title' => $title,
                'assignee' => $assignee,
                'status' => $status,
                'due_date' => $dueDate
            ];

            add_activity($state, "Added new task: $title.", $_SESSION['display_name'] ?? 'Admin');
            if (save_portal_state($state)) {
                flash('success', 'Task added to the queue.');
            } else {
                flash('error', 'Unable to save the new task. Please try again.');
            }
            redirect_with_flash();
        case 'update_task':
            $taskId = $_POST['task_id'] ?? '';
            $status = $_POST['status'] ?? 'Pending';
            $dueDate = trim($_POST['due_date'] ?? '');

            $validStatuses = ['Pending', 'In progress', 'Blocked', 'Completed'];
            if (!in_array($status, $validStatuses, true)) {
                $status = 'Pending';
            }

            $found = false;
            foreach ($state['tasks'] as &$task) {
                if ($task['id'] === $taskId) {
                    $found = true;
                    $task['status'] = $status;
                    if ($dueDate !== '') {
                        $task['due_date'] = $dueDate;
                    }
                    add_activity($state, "Updated task {$task['title']}.", $_SESSION['display_name'] ?? 'Admin');
                    break;
                }
            }
            unset($task);

            if (!$found) {
                flash('error', 'Task not found.');
                redirect_with_flash();
            }

            if (save_portal_state($state)) {
                flash('success', 'Task updated successfully.');
            } else {
                flash('error', 'Unable to update the task.');
            }
            redirect_with_flash();
        case 'delete_task':
            $taskId = $_POST['task_id'] ?? '';
            $initialCount = count($state['tasks']);
            $state['tasks'] = array_values(array_filter($state['tasks'], static fn($task) => $task['id'] !== $taskId));

            if ($initialCount === count($state['tasks'])) {
                flash('error', 'Task already removed or not found.');
                redirect_with_flash();
            }

            add_activity($state, 'Removed a task from the dashboard.', $_SESSION['display_name'] ?? 'Admin');
            if (save_portal_state($state)) {
                flash('success', 'Task removed successfully.');
            } else {
                flash('error', 'Unable to remove the task.');
            }
            redirect_with_flash();
        default:
            flash('error', 'Unknown action requested.');
            redirect_with_flash();
    }
}

$flashMessages = $_SESSION['flash'] ?? ['success' => [], 'error' => []];
unset($_SESSION['flash']);

$users = $state['users'];
$projects = $state['projects'];
$tasks = $state['tasks'];
$siteSettings = $state['site_settings'];
$activityLog = $state['activity_log'];

$roleLabels = [
    'admin' => 'Administrator',
    'installer' => 'Installer',
    'customer' => 'Customer',
    'referrer' => 'Referral partner',
    'employee' => 'Employee'
];

$userCounts = [
    'total' => count($users),
    'admin' => 0,
    'installer' => 0,
    'customer' => 0,
    'referrer' => 0,
    'employee' => 0
];

foreach ($users as $user) {
    $role = $user['role'] ?? '';
    if (isset($userCounts[$role])) {
        $userCounts[$role]++;
    }
}

$projectStatuses = [
    'on-track' => 'On track',
    'planning' => 'Planning',
    'at-risk' => 'At risk',
    'delayed' => 'Delayed',
    'completed' => 'Completed'
];

$taskStatuses = ['Pending', 'In progress', 'Blocked', 'Completed'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Dashboard | Dakshayani Enterprises</title>
  <meta name="description" content="Manage Dakshayani Enterprises operations, projects, and portal access from one secure console." />
  <link rel="icon" href="images/favicon.ico" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <style>
    :root {
      color-scheme: light;
      --bg: #0b1120;
      --surface: #ffffff;
      --muted: rgba(15, 23, 42, 0.6);
      --border: rgba(15, 23, 42, 0.08);
      --primary: #2563eb;
      --primary-dark: #1d4ed8;
      --error: #dc2626;
      --success: #16a34a;
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: linear-gradient(180deg, #0f172a 0%, #1e293b 60%, #0f172a 100%);
      min-height: 100vh;
      color: #0f172a;
      padding: 2.5rem 1.5rem;
      display: flex;
      justify-content: center;
    }

    .dashboard-shell {
      width: min(1200px, 100%);
      background: var(--surface);
      border-radius: 2rem;
      box-shadow: 0 40px 80px -45px rgba(15, 23, 42, 0.6);
      padding: clamp(2rem, 4vw, 3rem);
      display: grid;
      gap: clamp(1.5rem, 3vw, 2.5rem);
    }

    header.dashboard-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      flex-wrap: wrap;
      gap: 1.5rem;
    }

    .eyebrow {
      text-transform: uppercase;
      letter-spacing: 0.18em;
      font-size: 0.75rem;
      font-weight: 600;
      color: var(--primary);
      margin: 0 0 0.5rem;
    }

    h1 {
      margin: 0 0 0.35rem;
      font-size: clamp(1.8rem, 3vw, 2.4rem);
      font-weight: 700;
    }

    .subhead {
      margin: 0;
      font-size: 0.95rem;
      color: var(--muted);
    }

    .logout-btn {
      border: none;
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: #f8fafc;
      border-radius: 999px;
      padding: 0.65rem 1.4rem;
      font-weight: 600;
      cursor: pointer;
      box-shadow: 0 20px 35px -25px rgba(37, 99, 235, 0.9);
    }

    .flash-list {
      display: grid;
      gap: 0.75rem;
    }

    .flash-message {
      border-radius: 1rem;
      padding: 0.9rem 1rem;
      font-size: 0.95rem;
      border: 1px solid transparent;
    }

    .flash-message[data-tone="success"] {
      background: rgba(22, 163, 74, 0.12);
      border-color: rgba(22, 163, 74, 0.3);
      color: #166534;
    }

    .flash-message[data-tone="error"] {
      background: rgba(220, 38, 38, 0.12);
      border-color: rgba(220, 38, 38, 0.3);
      color: var(--error);
    }

    .panel {
      border: 1px solid var(--border);
      border-radius: 1.5rem;
      padding: clamp(1.5rem, 2.5vw, 2rem);
      display: grid;
      gap: 1.25rem;
      background: #f8fafc;
    }

    .panel h2 {
      margin: 0;
      font-size: 1.2rem;
      font-weight: 600;
      color: #0f172a;
    }

    .panel .lead {
      margin: 0;
      color: var(--muted);
      font-size: 0.95rem;
    }

    form {
      display: grid;
      gap: 1rem;
    }

    .form-grid {
      display: grid;
      gap: 1rem;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }

    .form-group {
      display: flex;
      flex-direction: column;
      gap: 0.4rem;
    }

    .form-group label {
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: rgba(15, 23, 42, 0.65);
    }

    .form-group input,
    .form-group textarea,
    .form-group select {
      border-radius: 0.85rem;
      border: 1px solid rgba(148, 163, 184, 0.4);
      padding: 0.65rem 0.85rem;
      font-size: 0.95rem;
      font-family: inherit;
      color: #0f172a;
      background: #ffffff;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .form-group textarea {
      min-height: 110px;
      resize: vertical;
    }

    .form-group input:focus,
    .form-group textarea:focus,
    .form-group select:focus {
      outline: none;
      border-color: rgba(37, 99, 235, 0.7);
      box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
    }

    .form-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 0.75rem;
      justify-content: flex-end;
      align-items: center;
    }

    .btn-primary {
      border: none;
      background: linear-gradient(135deg, #1d4ed8, #2563eb);
      color: #f8fafc;
      border-radius: 999px;
      padding: 0.75rem 1.6rem;
      font-weight: 600;
      cursor: pointer;
      box-shadow: 0 20px 35px -25px rgba(37, 99, 235, 0.9);
    }

    .btn-outline {
      border-radius: 999px;
      padding: 0.6rem 1.2rem;
      font-weight: 600;
      border: 1px dashed rgba(37, 99, 235, 0.6);
      background: rgba(241, 245, 249, 0.7);
      color: #1d4ed8;
      cursor: pointer;
    }

    .btn-ghost {
      border: none;
      background: transparent;
      color: #b91c1c;
      font-weight: 600;
      cursor: pointer;
    }

    .metric-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 1rem;
    }

    .metric-card {
      background: #ffffff;
      border-radius: 1.2rem;
      border: 1px solid rgba(37, 99, 235, 0.12);
      padding: 1.1rem 1.2rem;
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
    }

    .metric-label {
      font-size: 0.85rem;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: rgba(15, 23, 42, 0.55);
      margin: 0 0 0.35rem;
    }

    .metric-value {
      font-size: 1.6rem;
      margin: 0;
      font-weight: 700;
      color: #0f172a;
    }

    .metric-helper {
      margin: 0.25rem 0 0;
      font-size: 0.85rem;
      color: var(--muted);
    }

    .two-column {
      display: grid;
      gap: 1.5rem;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    }

    table {
      width: 100%;
      border-collapse: collapse;
      background: #ffffff;
      border-radius: 1.25rem;
      overflow: hidden;
    }

    table thead {
      background: rgba(37, 99, 235, 0.08);
    }

    table th,
    table td {
      padding: 0.85rem 1rem;
      text-align: left;
      font-size: 0.95rem;
      border-bottom: 1px solid rgba(15, 23, 42, 0.05);
    }

    table tbody tr:last-child td {
      border-bottom: none;
    }

    .status-chip {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      border-radius: 999px;
      padding: 0.25rem 0.75rem;
      font-size: 0.85rem;
      font-weight: 600;
      text-transform: capitalize;
    }

    .status-chip[data-status="active"],
    .status-chip[data-status="on-track"],
    .status-chip[data-status="Pending"] {
      background: rgba(16, 185, 129, 0.14);
      color: #047857;
    }

    .status-chip[data-status="pending"],
    .status-chip[data-status="planning"] {
      background: rgba(37, 99, 235, 0.12);
      color: #1d4ed8;
    }

    .status-chip[data-status="suspended"],
    .status-chip[data-status="at-risk"],
    .status-chip[data-status="Blocked"] {
      background: rgba(248, 113, 113, 0.18);
      color: #b91c1c;
    }

    .status-chip[data-status="onboarding"],
    .status-chip[data-status="In progress"] {
      background: rgba(249, 115, 22, 0.16);
      color: #c2410c;
    }

    .status-chip[data-status="Completed"],
    .status-chip[data-status="completed"] {
      background: rgba(37, 99, 235, 0.18);
      color: #1d4ed8;
    }

    .activity-log {
      display: grid;
      gap: 0.75rem;
    }

    .activity-row {
      background: #ffffff;
      border-radius: 1rem;
      border: 1px solid rgba(15, 23, 42, 0.08);
      padding: 0.85rem 1rem;
      display: grid;
      gap: 0.25rem;
    }

    .activity-row small {
      color: var(--muted);
    }

    @media (max-width: 720px) {
      body {
        padding: 1.5rem;
      }

      .dashboard-shell {
        border-radius: 1.5rem;
      }

      table th,
      table td {
        padding: 0.75rem 0.65rem;
        font-size: 0.9rem;
      }
    }
  </style>
</head>
<body>
  <main class="dashboard-shell">
    <header class="dashboard-header">
      <div>
        <p class="eyebrow">Admin portal</p>
        <h1>Welcome back, <?= htmlspecialchars($_SESSION['display_name'] ?? 'Administrator'); ?></h1>
        <p class="subhead">
          <?php if (!empty($_SESSION['last_login'])): ?>
            Last signed in on <?= htmlspecialchars($_SESSION['last_login']); ?>.
          <?php else: ?>
            You're securely signed in to the Dakshayani Enterprises control centre.
          <?php endif; ?>
        </p>
      </div>
      <form method="post" action="logout.php">
        <button class="logout-btn" type="submit">Log out</button>
      </form>
    </header>

    <?php if (!empty($flashMessages['success']) || !empty($flashMessages['error'])): ?>
      <div class="flash-list">
        <?php foreach ($flashMessages['success'] as $message): ?>
          <div class="flash-message" data-tone="success"><?= htmlspecialchars($message); ?></div>
        <?php endforeach; ?>
        <?php foreach ($flashMessages['error'] as $message): ?>
          <div class="flash-message" data-tone="error"><?= htmlspecialchars($message); ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <section class="panel">
      <h2>Operations snapshot</h2>
      <p class="lead">Monitor how the business is performing today.</p>
      <div class="metric-grid">
        <article class="metric-card">
          <p class="metric-label">Active users</p>
          <p class="metric-value"><?= htmlspecialchars((string) $userCounts['total']); ?></p>
          <p class="metric-helper">Portal accounts with visibility</p>
        </article>
        <article class="metric-card">
          <p class="metric-label">Active projects</p>
          <p class="metric-value"><?= htmlspecialchars((string) count($projects)); ?></p>
          <p class="metric-helper">Projects currently tracked</p>
        </article>
        <article class="metric-card">
          <p class="metric-label">Open tasks</p>
          <p class="metric-value"><?php
            $openTasks = array_reduce($tasks, static function ($carry, $task) {
                return $carry + (strcasecmp($task['status'] ?? '', 'Completed') === 0 ? 0 : 1);
            }, 0);
            echo htmlspecialchars((string) $openTasks);
          ?></p>
          <p class="metric-helper">Items needing action</p>
        </article>
        <article class="metric-card">
          <p class="metric-label">Last updated</p>
          <p class="metric-value" style="font-size:1rem;"><?= htmlspecialchars(date('j M Y, g:i A', strtotime($state['last_updated']))); ?></p>
          <p class="metric-helper">Auto-updates on every change</p>
        </article>
      </div>
    </section>

    <section class="panel">
      <h2>Site configuration</h2>
      <p class="lead">Update what the public website communicates to prospects and customers.</p>
      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
        <input type="hidden" name="action" value="update_site_settings" />
        <div class="form-grid">
          <div class="form-group">
            <label for="company_focus">Company focus</label>
            <textarea id="company_focus" name="company_focus" required><?= htmlspecialchars($siteSettings['company_focus'] ?? ''); ?></textarea>
          </div>
          <div class="form-group">
            <label for="announcement">Homepage announcement</label>
            <textarea id="announcement" name="announcement"><?= htmlspecialchars($siteSettings['announcement'] ?? ''); ?></textarea>
          </div>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label for="primary_contact">Primary contact</label>
            <input id="primary_contact" name="primary_contact" type="text" value="<?= htmlspecialchars($siteSettings['primary_contact'] ?? ''); ?>" required />
          </div>
          <div class="form-group">
            <label for="support_email">Support email</label>
            <input id="support_email" name="support_email" type="email" value="<?= htmlspecialchars($siteSettings['support_email'] ?? ''); ?>" required />
          </div>
          <div class="form-group">
            <label for="support_phone">Support phone</label>
            <input id="support_phone" name="support_phone" type="text" value="<?= htmlspecialchars($siteSettings['support_phone'] ?? ''); ?>" />
          </div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn-primary">Save site settings</button>
        </div>
      </form>
    </section>

    <section class="panel">
      <div class="two-column">
        <div>
          <h2>Portal accounts</h2>
          <p class="lead">Manage who can access dashboards across teams.</p>
          <?php if (empty($users)): ?>
            <p>No user accounts exist yet.</p>
          <?php else: ?>
            <table>
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Role</th>
                  <th>Status</th>
                  <th>Contact</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($users as $user): ?>
                  <tr>
                    <td>
                      <strong><?= htmlspecialchars($user['name']); ?></strong><br />
                      <small><?= htmlspecialchars($user['email']); ?></small>
                    </td>
                    <td><?= htmlspecialchars($roleLabels[$user['role']] ?? ucfirst($user['role'])); ?></td>
                    <td><span class="status-chip" data-status="<?= htmlspecialchars($user['status']); ?>"><?= htmlspecialchars($user['status']); ?></span></td>
                    <td>
                      <?php if (!empty($user['phone'])): ?>
                        <div><?= htmlspecialchars($user['phone']); ?></div>
                      <?php endif; ?>
                      <small>Created <?= htmlspecialchars($user['created_at'] ?? ''); ?></small>
                    </td>
                    <td>
                      <form method="post" style="margin-bottom:0.5rem;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
                        <input type="hidden" name="action" value="update_user_status" />
                        <input type="hidden" name="user_id" value="<?= htmlspecialchars($user['id']); ?>" />
                        <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));">
                          <div class="form-group">
                            <label for="status-<?= htmlspecialchars($user['id']); ?>">Status</label>
                            <select id="status-<?= htmlspecialchars($user['id']); ?>" name="status">
                              <?php foreach (['active', 'pending', 'onboarding', 'suspended', 'disabled'] as $status): ?>
                                <option value="<?= htmlspecialchars($status); ?>" <?= ($user['status'] ?? '') === $status ? 'selected' : ''; ?>><?= htmlspecialchars(ucfirst($status)); ?></option>
                              <?php endforeach; ?>
                            </select>
                          </div>
                          <div class="form-group">
                            <label for="notes-<?= htmlspecialchars($user['id']); ?>">Notes</label>
                            <textarea id="notes-<?= htmlspecialchars($user['id']); ?>" name="notes" placeholder="Optional remarks"><?= htmlspecialchars($user['notes'] ?? ''); ?></textarea>
                          </div>
                        </div>
                        <div class="form-actions">
                          <button type="submit" class="btn-primary">Update</button>
                        </div>
                      </form>
                      <?php if ($user['role'] !== 'admin'): ?>
                        <form method="post" onsubmit="return confirm('Remove this account?');">
                          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
                          <input type="hidden" name="action" value="delete_user" />
                          <input type="hidden" name="user_id" value="<?= htmlspecialchars($user['id']); ?>" />
                          <button type="submit" class="btn-ghost">Remove</button>
                        </form>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
        <aside>
          <h3 style="margin-top:0;">Add a new account</h3>
          <form method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
            <input type="hidden" name="action" value="create_user" />
            <div class="form-group">
              <label for="create-name">Full name</label>
              <input id="create-name" name="name" type="text" placeholder="Aarav Sharma" required />
            </div>
            <div class="form-group">
              <label for="create-email">Email</label>
              <input id="create-email" name="email" type="email" placeholder="user@dakshayani.in" required />
            </div>
            <div class="form-group">
              <label for="create-phone">Phone</label>
              <input id="create-phone" name="phone" type="tel" placeholder="+91 90000 00000" />
            </div>
            <div class="form-group">
              <label for="create-role">Role</label>
              <select id="create-role" name="role">
                <?php foreach ($roleLabels as $key => $label): ?>
                  <option value="<?= htmlspecialchars($key); ?>" <?= $key === 'referrer' ? 'selected' : ''; ?>><?= htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="create-status">Status</label>
              <select id="create-status" name="status">
                <?php foreach (['active', 'pending', 'onboarding', 'suspended', 'disabled'] as $status): ?>
                  <option value="<?= htmlspecialchars($status); ?>" <?= $status === 'active' ? 'selected' : ''; ?>><?= htmlspecialchars(ucfirst($status)); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="create-notes">Internal notes</label>
              <textarea id="create-notes" name="notes" placeholder="e.g. Access limited to Ranchi installs"></textarea>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn-primary">Create account</button>
            </div>
          </form>
        </aside>
      </div>
    </section>

    <section class="panel">
      <div class="two-column">
        <div>
          <h2>Project tracker</h2>
          <p class="lead">Stay on top of EPC delivery deadlines.</p>
          <?php if (empty($projects)): ?>
            <p>No projects have been logged yet.</p>
          <?php else: ?>
            <table>
              <thead>
                <tr>
                  <th>Project</th>
                  <th>Stage</th>
                  <th>Status</th>
                  <th>Target date</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($projects as $project): ?>
                  <tr>
                    <td>
                      <strong><?= htmlspecialchars($project['name']); ?></strong><br />
                      <small>Owner: <?= htmlspecialchars($project['owner']); ?></small>
                    </td>
                    <td><?= htmlspecialchars($project['stage']); ?></td>
                    <td><span class="status-chip" data-status="<?= htmlspecialchars($project['status']); ?>"><?= htmlspecialchars($projectStatuses[$project['status']] ?? ucfirst($project['status'])); ?></span></td>
                    <td><?= htmlspecialchars($project['target_date']); ?></td>
                    <td>
                      <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
                        <input type="hidden" name="action" value="update_project" />
                        <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id']); ?>" />
                        <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));">
                          <div class="form-group">
                            <label for="stage-<?= htmlspecialchars($project['id']); ?>">Stage</label>
                            <input id="stage-<?= htmlspecialchars($project['id']); ?>" type="text" name="stage" value="<?= htmlspecialchars($project['stage']); ?>" />
                          </div>
                          <div class="form-group">
                            <label for="status-<?= htmlspecialchars($project['id']); ?>">Status</label>
                            <select id="status-<?= htmlspecialchars($project['id']); ?>" name="status">
                              <?php foreach ($projectStatuses as $value => $label): ?>
                                <option value="<?= htmlspecialchars($value); ?>" <?= ($project['status'] ?? '') === $value ? 'selected' : ''; ?>><?= htmlspecialchars($label); ?></option>
                              <?php endforeach; ?>
                            </select>
                          </div>
                          <div class="form-group">
                            <label for="target-<?= htmlspecialchars($project['id']); ?>">Target date</label>
                            <input id="target-<?= htmlspecialchars($project['id']); ?>" type="date" name="target_date" value="<?= htmlspecialchars($project['target_date']); ?>" />
                          </div>
                        </div>
                        <div class="form-actions">
                          <button type="submit" class="btn-primary">Update</button>
                        </div>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
        <aside>
          <h3 style="margin-top:0;">Log new project</h3>
          <form method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
            <input type="hidden" name="action" value="create_project" />
            <div class="form-group">
              <label for="project-name">Project name</label>
              <input id="project-name" name="project_name" type="text" placeholder="Smart Rooftop - Ranchi" required />
            </div>
            <div class="form-group">
              <label for="project-owner">Owner</label>
              <input id="project-owner" name="project_owner" type="text" placeholder="Priya Sharma" required />
            </div>
            <div class="form-group">
              <label for="project-stage">Current stage</label>
              <input id="project-stage" name="project_stage" type="text" placeholder="Installation" required />
            </div>
            <div class="form-group">
              <label for="project-status">Status</label>
              <select id="project-status" name="project_status">
                <?php foreach ($projectStatuses as $value => $label): ?>
                  <option value="<?= htmlspecialchars($value); ?>" <?= $value === 'on-track' ? 'selected' : ''; ?>><?= htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="project-target">Target date</label>
              <input id="project-target" name="target_date" type="date" />
            </div>
            <div class="form-actions">
              <button type="submit" class="btn-primary">Add project</button>
            </div>
          </form>
        </aside>
      </div>
    </section>

    <section class="panel">
      <div class="two-column">
        <div>
          <h2>Task board</h2>
          <p class="lead">Keep the leadership team accountable for next steps.</p>
          <?php if (empty($tasks)): ?>
            <p>No tasks recorded. Use the form to add one.</p>
          <?php else: ?>
            <table>
              <thead>
                <tr>
                  <th>Task</th>
                  <th>Assignee</th>
                  <th>Status</th>
                  <th>Due</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($tasks as $task): ?>
                  <tr>
                    <td><?= htmlspecialchars($task['title']); ?></td>
                    <td><?= htmlspecialchars($task['assignee']); ?></td>
                    <td><span class="status-chip" data-status="<?= htmlspecialchars($task['status']); ?>"><?= htmlspecialchars($task['status']); ?></span></td>
                    <td><?= htmlspecialchars($task['due_date']); ?></td>
                    <td>
                      <form method="post" style="margin-bottom:0.5rem;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
                        <input type="hidden" name="action" value="update_task" />
                        <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id']); ?>" />
                        <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));">
                          <div class="form-group">
                            <label for="task-status-<?= htmlspecialchars($task['id']); ?>">Status</label>
                            <select id="task-status-<?= htmlspecialchars($task['id']); ?>" name="status">
                              <?php foreach ($taskStatuses as $status): ?>
                                <option value="<?= htmlspecialchars($status); ?>" <?= ($task['status'] ?? '') === $status ? 'selected' : ''; ?>><?= htmlspecialchars($status); ?></option>
                              <?php endforeach; ?>
                            </select>
                          </div>
                          <div class="form-group">
                            <label for="task-due-<?= htmlspecialchars($task['id']); ?>">Due date</label>
                            <input id="task-due-<?= htmlspecialchars($task['id']); ?>" type="date" name="due_date" value="<?= htmlspecialchars($task['due_date']); ?>" />
                          </div>
                        </div>
                        <div class="form-actions">
                          <button type="submit" class="btn-primary">Update</button>
                        </div>
                      </form>
                      <form method="post" onsubmit="return confirm('Remove this task?');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
                        <input type="hidden" name="action" value="delete_task" />
                        <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id']); ?>" />
                        <button type="submit" class="btn-ghost">Remove</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
        <aside>
          <h3 style="margin-top:0;">Add a task</h3>
          <form method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
            <input type="hidden" name="action" value="create_task" />
            <div class="form-group">
              <label for="task-title">Task title</label>
              <input id="task-title" name="task_title" type="text" placeholder="Approve installer onboarding" required />
            </div>
            <div class="form-group">
              <label for="task-assignee">Assignee</label>
              <input id="task-assignee" name="task_assignee" type="text" placeholder="Deepak Entranchi" required />
            </div>
            <div class="form-group">
              <label for="task-status">Status</label>
              <select id="task-status" name="task_status">
                <?php foreach ($taskStatuses as $status): ?>
                  <option value="<?= htmlspecialchars($status); ?>" <?= $status === 'Pending' ? 'selected' : ''; ?>><?= htmlspecialchars($status); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="task-due-date">Due date</label>
              <input id="task-due-date" name="task_due_date" type="date" />
            </div>
            <div class="form-actions">
              <button type="submit" class="btn-primary">Add task</button>
            </div>
          </form>
        </aside>
      </div>
    </section>

    <section class="panel">
      <h2>Recent activity</h2>
      <p class="lead">Automatic log of important actions across the portal.</p>
      <?php if (empty($activityLog)): ?>
        <p>No activity recorded yet.</p>
      <?php else: ?>
        <div class="activity-log">
          <?php foreach ($activityLog as $log): ?>
            <div class="activity-row">
              <strong><?= htmlspecialchars($log['event']); ?></strong>
              <small><?= htmlspecialchars($log['actor']); ?> · <?= htmlspecialchars(date('j M Y, g:i A', strtotime($log['timestamp']))); ?></small>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
