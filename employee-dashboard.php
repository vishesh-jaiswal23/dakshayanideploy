<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'employee') {
  header('Location: login.php');
  exit;
}

require_once __DIR__ . '/portal-state.php';

if (empty($_SESSION['csrf_token'])) {
  try {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  } catch (Exception $e) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
  }
}

$state = portal_load_state();
$currentUserId = $_SESSION['user_id'] ?? '';
$userRecord = null;

foreach ($state['users'] as $user) {
  if (($user['id'] ?? '') === $currentUserId) {
    $userRecord = $user;
    break;
  }
}

$displayName = $_SESSION['display_name'] ?? ($userRecord['name'] ?? 'Team member');
$lastLogin = $_SESSION['last_login'] ?? null;
$userEmail = $_SESSION['user_email'] ?? ($userRecord['email'] ?? '');

$roleLabels = [
  'customer' => 'Customer',
  'employee' => 'Employee',
  'installer' => 'Installer',
  'referrer' => 'Referral partner',
  'admin' => 'Administrator',
];

$roleLabel = $roleLabels[$_SESSION['user_role']] ?? ucfirst($_SESSION['user_role']);
$userPhone = $userRecord['phone'] ?? '—';
$userCity = $userRecord['city'] ?? '—';
$accountId = $userRecord['id'] ?? '—';
$normalizedPhone = $userPhone === '—' ? '' : $userPhone;
$normalizedCity = $userCity === '—' ? '' : $userCity;
$userDeskLocation = $userRecord['desk_location'] ?? '';
$userWorkingHours = $userRecord['working_hours'] ?? '';
$userEmergencyContact = $userRecord['emergency_contact'] ?? '';
$userPreferredChannel = $userRecord['preferred_channel'] ?? 'Phone call';
$userReportingManager = $userRecord['reporting_manager'] ?? '';

portal_ensure_employee_approvals($state);

function employee_flash(string $type, string $message): void
{
  if (!isset($_SESSION['employee_flash'])) {
    $_SESSION['employee_flash'] = ['success' => [], 'error' => []];
  }

  if (!isset($_SESSION['employee_flash'][$type])) {
    $_SESSION['employee_flash'][$type] = [];
  }

  $_SESSION['employee_flash'][$type][] = $message;
}

function employee_redirect(?string $anchor = null): void
{
  $target = 'employee-dashboard.php';
  if ($anchor !== null && $anchor !== '') {
    $target .= $anchor[0] === '#' ? $anchor : '#' . $anchor;
  }

  header('Location: ' . $target);
  exit;
}

function employee_collect_flashes(): array
{
  $flash = $_SESSION['employee_flash'] ?? ['success' => [], 'error' => []];
  unset($_SESSION['employee_flash']);

  if (!is_array($flash)) {
    return ['success' => [], 'error' => []];
  }

  $flash['success'] = isset($flash['success']) && is_array($flash['success']) ? $flash['success'] : [];
  $flash['error'] = isset($flash['error']) && is_array($flash['error']) ? $flash['error'] : [];

  return $flash;
}

function employee_sanitize_columns(array $columns, array $input): array
{
  $normalized = [];
  $hasValue = false;

  foreach ($columns as $column) {
    if (!is_array($column) || !isset($column['key'])) {
      continue;
    }

    $key = $column['key'];
    $value = isset($input[$key]) ? trim((string) $input[$key]) : '';
    $normalized[$key] = $value;
    if ($value !== '') {
      $hasValue = true;
    }
  }

  return [$normalized, $hasValue];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  $anchor = $_POST['redirect_anchor'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    employee_flash('error', 'Security token mismatch. Please try again.');
    employee_redirect($anchor);
  }

  $action = $_POST['action'] ?? '';

  switch ($action) {
    case 'update_profile':
      $profileFields = [
        'phone' => trim($_POST['phone'] ?? ''),
        'city' => trim($_POST['city'] ?? ''),
        'emergency_contact' => trim($_POST['emergency_contact'] ?? ''),
        'working_hours' => trim($_POST['working_hours'] ?? ''),
        'preferred_channel' => trim($_POST['preferred_channel'] ?? ''),
        'desk_location' => trim($_POST['desk_location'] ?? ''),
        'reporting_manager' => trim($_POST['reporting_manager'] ?? ''),
      ];

      $updated = false;
      foreach ($state['users'] as &$user) {
        if (($user['id'] ?? '') === $currentUserId) {
          foreach ($profileFields as $key => $value) {
            if ($value === '' && in_array($key, ['phone', 'city'], true)) {
              unset($user[$key]);
              continue;
            }

            if ($value === '') {
              $user[$key] = '';
            } else {
              $user[$key] = $value;
            }
          }
          $updated = true;
          break;
        }
      }
      unset($user);

      if ($updated) {
        portal_record_activity($state, 'Updated personal contact preferences via employee dashboard.', $displayName);
        if (portal_save_state($state)) {
          employee_flash('success', 'Profile updated successfully.');
        } else {
          employee_flash('error', 'Unable to save updates right now.');
        }
      } else {
        employee_flash('error', 'Unable to locate your profile details.');
      }

      employee_redirect($anchor);
      break;

    case 'request_add_customer':
      $segmentSlug = $_POST['segment'] ?? '';
      $justification = trim($_POST['justification'] ?? '');
      $notes = trim($_POST['notes'] ?? '');
      $reminderOn = trim($_POST['reminder_on'] ?? '');
      $fieldsInput = $_POST['fields'] ?? [];
      if (!is_array($fieldsInput)) {
        $fieldsInput = [];
      }

      if (!isset($state['customer_registry']['segments'][$segmentSlug])) {
        employee_flash('error', 'Unknown customer segment selected.');
        employee_redirect($anchor);
      }

      $segment = $state['customer_registry']['segments'][$segmentSlug];
      $columns = $segment['columns'] ?? [];
      [$normalizedFields, $hasValue] = employee_sanitize_columns($columns, $fieldsInput);

      if (!$hasValue && $notes === '') {
        employee_flash('error', 'Add at least one field or a note before submitting.');
        employee_redirect($anchor);
      }

      $recordName = '';
      foreach ($columns as $column) {
        $key = $column['key'] ?? null;
        if ($key && ($normalizedFields[$key] ?? '') !== '') {
          $recordName = $normalizedFields[$key];
          break;
        }
      }

      if ($recordName === '') {
        $recordName = ucfirst($segment['label'] ?? 'customer record');
      }

      $requestId = portal_next_employee_request_id($state);
      $submittedAt = date('c');
      $formattedSubmitted = date('j M Y, g:i A', strtotime($submittedAt));

      $request = [
        'id' => $requestId,
        'type' => 'customer_add',
        'title' => 'Add to ' . ($segment['label'] ?? 'Customer registry') . ': ' . $recordName,
        'status' => 'Pending admin review',
        'submitted_at' => $submittedAt,
        'submitted_by' => $displayName,
        'owner' => 'Admin review desk',
        'details' => $justification !== '' ? $justification : 'New customer record proposal awaiting approval.',
        'effective_date' => $reminderOn,
        'last_update' => 'Submitted on ' . $formattedSubmitted,
        'segment' => $segmentSlug,
        'segment_label' => $segment['label'] ?? ucfirst($segmentSlug),
        'payload' => [
          'segment' => $segmentSlug,
          'fields' => $normalizedFields,
          'notes' => $notes,
          'reminder_on' => $reminderOn,
        ],
      ];

      portal_add_employee_request($state, $request);

      if (portal_save_state($state)) {
        employee_flash('success', 'Submitted for admin approval.');
      } else {
        employee_flash('error', 'Unable to log the request right now.');
      }

      employee_redirect($anchor);
      break;

    case 'request_update_customer':
      $segmentSlug = $_POST['segment'] ?? '';
      $entryId = $_POST['entry_id'] ?? '';
      $targetSegment = $_POST['target_segment'] ?? $segmentSlug;
      $justification = trim($_POST['justification'] ?? '');
      $notes = trim($_POST['notes'] ?? '');
      $reminderOn = trim($_POST['reminder_on'] ?? '');
      $fieldsInput = $_POST['fields'] ?? [];
      if (!is_array($fieldsInput)) {
        $fieldsInput = [];
      }

      $registrySegments = $state['customer_registry']['segments'] ?? [];
      if (!isset($registrySegments[$segmentSlug])) {
        employee_flash('error', 'Original segment not found.');
        employee_redirect($anchor);
      }

      if (!isset($registrySegments[$targetSegment])) {
        employee_flash('error', 'Target segment is invalid.');
        employee_redirect($anchor);
      }

      $segment = $registrySegments[$segmentSlug];
      $columns = $segment['columns'] ?? [];
      [$normalizedFields, $hasValue] = employee_sanitize_columns($columns, $fieldsInput);

      if (!$hasValue && $notes === '' && $segmentSlug === $targetSegment) {
        employee_flash('error', 'Provide updated details or notes for the admin team.');
        employee_redirect($anchor);
      }

      $recordName = '';
      foreach ($segment['entries'] ?? [] as $entry) {
        if (($entry['id'] ?? '') === $entryId) {
          foreach ($columns as $column) {
            $key = $column['key'] ?? null;
            if ($key && isset($entry['fields'][$key]) && trim((string) $entry['fields'][$key]) !== '') {
              $recordName = trim((string) $entry['fields'][$key]);
              break 2;
            }
          }
        }
      }

      if ($recordName === '') {
        $recordName = ucfirst($segment['label'] ?? 'customer record');
      }

      $requestId = portal_next_employee_request_id($state);
      $submittedAt = date('c');
      $formattedSubmitted = date('j M Y, g:i A', strtotime($submittedAt));

      $request = [
        'id' => $requestId,
        'type' => 'customer_update',
        'title' => 'Update ' . ($segment['label'] ?? 'Customer record') . ': ' . $recordName,
        'status' => 'Pending admin review',
        'submitted_at' => $submittedAt,
        'submitted_by' => $displayName,
        'owner' => 'Admin review desk',
        'details' => $justification !== '' ? $justification : 'Requested updates to an existing customer entry.',
        'effective_date' => $reminderOn,
        'last_update' => 'Submitted on ' . $formattedSubmitted,
        'segment' => $segmentSlug,
        'segment_label' => $segment['label'] ?? ucfirst($segmentSlug),
        'payload' => [
          'segment' => $segmentSlug,
          'entry_id' => $entryId,
          'target_segment' => $targetSegment,
          'fields' => $normalizedFields,
          'notes' => $notes,
          'reminder_on' => $reminderOn,
        ],
      ];

      portal_add_employee_request($state, $request);

      if (portal_save_state($state)) {
        employee_flash('success', 'Change request recorded for admin action.');
      } else {
        employee_flash('error', 'Unable to record your request.');
      }

      employee_redirect($anchor);
      break;

    case 'submit_general_request':
      $changeType = trim($_POST['change_type'] ?? '');
      $effectiveDate = trim($_POST['effective_date'] ?? '');
      $justification = trim($_POST['justification'] ?? '');

      if ($changeType === '') {
        employee_flash('error', 'Select the type of change you are requesting.');
        employee_redirect($anchor);
      }

      if ($justification === '') {
        employee_flash('error', 'Add a short justification so the admin team can review it.');
        employee_redirect($anchor);
      }

      $requestId = portal_next_employee_request_id($state);
      $submittedAt = date('c');
      $formattedSubmitted = date('j M Y, g:i A', strtotime($submittedAt));

      $request = [
        'id' => $requestId,
        'type' => 'general',
        'title' => $changeType,
        'status' => 'Pending admin review',
        'submitted_at' => $submittedAt,
        'submitted_by' => $displayName,
        'owner' => $changeType === 'Payroll bank update' ? 'Finance & Admin' : 'Admin desk',
        'details' => $justification,
        'effective_date' => $effectiveDate,
        'last_update' => 'Submitted on ' . $formattedSubmitted,
        'payload' => [
          'change_type' => $changeType,
          'effective_date' => $effectiveDate,
          'justification' => $justification,
        ],
      ];

      portal_add_employee_request($state, $request);

      if (portal_save_state($state)) {
        employee_flash('success', 'Your request has been shared with the admin team.');
      } else {
        employee_flash('error', 'Unable to submit the request right now.');
      }

      employee_redirect($anchor);
      break;

    case 'request_theme_change':
      $seasonLabel = trim($_POST['season_label'] ?? '');
      $accentColor = portal_sanitize_hex_color($_POST['accent_color'] ?? '#2563EB', '#2563EB');
      $announcement = trim($_POST['announcement'] ?? '');
      $backgroundImage = trim($_POST['background_image'] ?? '');
      $justification = trim($_POST['justification'] ?? '');
      $effectiveDate = trim($_POST['effective_date'] ?? '');

      if ($seasonLabel === '') {
        employee_flash('error', 'Add a headline for the proposed theme.');
        employee_redirect($anchor);
      }

      if ($justification === '') {
        employee_flash('error', 'Share the reason behind the design change so the admin can review.');
        employee_redirect($anchor);
      }

      $requestId = portal_next_employee_request_id($state);
      $submittedAt = date('c');
      $formattedSubmitted = date('j M Y, g:i A', strtotime($submittedAt));

      $request = [
        'id' => $requestId,
        'type' => 'design_change',
        'title' => 'Theme update: ' . $seasonLabel,
        'status' => 'Pending admin review',
        'submitted_at' => $submittedAt,
        'submitted_by' => $displayName,
        'owner' => 'Site content admins',
        'details' => $justification,
        'effective_date' => $effectiveDate,
        'last_update' => 'Submitted on ' . $formattedSubmitted,
        'payload' => [
          'season_label' => $seasonLabel,
          'accent_color' => $accentColor,
          'announcement' => $announcement,
          'background_image' => $backgroundImage,
          'effective_date' => $effectiveDate,
        ],
      ];

      portal_add_employee_request($state, $request);

      if (portal_save_state($state)) {
        employee_flash('success', 'Design change proposal submitted for admin approval.');
      } else {
        employee_flash('error', 'Unable to record the proposal right now.');
      }

      employee_redirect($anchor);
      break;
  }
}

$flashMessages = employee_collect_flashes();
$customerRegistry = $state['customer_registry'] ?? ['segments' => []];
$customerSegments = $customerRegistry['segments'] ?? [];
$customerSegmentStats = [];
foreach ($customerSegments as $slug => $segment) {
  $count = count($segment['entries'] ?? []);
  $customerSegmentStats[$slug] = [
    'label' => $segment['label'] ?? ucfirst(str_replace('-', ' ', $slug)),
    'count' => $count,
    'description' => $segment['description'] ?? '',
  ];
}

$approvalQueue = $state['employee_approvals'] ?? ['pending' => [], 'history' => []];
$pendingApprovals = $approvalQueue['pending'] ?? [];
$approvalHistory = $approvalQueue['history'] ?? [];

$formatDateTime = static function (?string $value, string $fallback = '—'): string {
  if (!$value) {
    return $fallback;
  }
  $timestamp = strtotime($value);
  if ($timestamp === false) {
    return $fallback;
  }

  return date('j M Y, g:i A', $timestamp);
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Employee Dashboard | Dakshayani Enterprises</title>
  <meta name="description" content="Employee dashboard for the Dakshayani Enterprises team. Track tickets, meetings, and tasks." />
  <link rel="icon" href="images/favicon.ico" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <style>
    :root {
      --primary: #6366f1;
      --muted: rgba(15, 23, 42, 0.6);
      --border: rgba(15, 23, 42, 0.08);
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: radial-gradient(circle at 80% 10%, rgba(99, 102, 241, 0.18), transparent 55%), #0f172a;
      min-height: 100vh;
      padding: 2.5rem 1.5rem;
      display: flex;
      justify-content: center;
      color: #0f172a;
    }

    .dashboard-shell {
      width: min(1100px, 100%);
      background: #ffffff;
      border-radius: 2rem;
      padding: clamp(2rem, 4vw, 3rem);
      box-shadow: 0 40px 80px -45px rgba(15, 23, 42, 0.6);
      display: grid;
      gap: clamp(1.4rem, 3vw, 2.4rem);
    }

    header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      flex-wrap: wrap;
      gap: 1.25rem;
    }

    .eyebrow {
      text-transform: uppercase;
      font-weight: 600;
      letter-spacing: 0.16em;
      font-size: 0.75rem;
      color: var(--primary);
      margin: 0 0 0.5rem;
    }

    h1 {
      margin: 0;
      font-size: clamp(1.75rem, 3vw, 2.35rem);
      font-weight: 700;
    }

    .subhead {
      margin: 0;
      font-size: 0.95rem;
      color: var(--muted);
    }

    .logout-btn {
      border: none;
      background: var(--primary);
      color: #f8fafc;
      padding: 0.65rem 1.4rem;
      border-radius: 999px;
      font-weight: 600;
      cursor: pointer;
      box-shadow: 0 20px 35px -25px rgba(99, 102, 241, 0.7);
    }

    .status-banner {
      border-radius: 1.25rem;
      padding: 1rem 1.2rem;
      background: rgba(99, 102, 241, 0.12);
      border: 1px solid rgba(99, 102, 241, 0.2);
      color: #4338ca;
      font-size: 0.95rem;
    }

    .status-banner[data-tone="error"] {
      background: #fee2e2;
      color: #b91c1c;
      border-color: #fecaca;
    }

    .panel {
      border: 1px solid var(--border);
      border-radius: 1.5rem;
      padding: clamp(1.4rem, 2.6vw, 2rem);
      background: #f8fafc;
      display: grid;
      gap: 1rem;
    }

    .panel h2 {
      margin: 0;
      font-size: 1.15rem;
      font-weight: 600;
    }

    .panel .lead {
      margin: 0;
      font-size: 0.95rem;
      color: var(--muted);
    }

    .table-wrapper {
      overflow-x: auto;
    }

    .customer-table {
      width: 100%;
      border-collapse: collapse;
      min-width: 640px;
    }

    .customer-table th,
    .customer-table td {
      padding: 0.6rem 0.75rem;
      border-bottom: 1px solid var(--border);
      text-align: left;
      font-size: 0.9rem;
    }

    .customer-table th {
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: rgba(15, 23, 42, 0.55);
    }

    .request-block {
      border: 1px solid var(--border);
      border-radius: 1rem;
      background: #ffffff;
      padding: 0.9rem 1rem;
    }

    .request-block + .request-block {
      margin-top: 0.75rem;
    }

    .request-block summary {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 0.75rem;
      cursor: pointer;
      font-weight: 600;
      color: #0f172a;
    }

    .request-block summary::-webkit-details-marker {
      display: none;
    }

    .request-block[open] summary {
      margin-bottom: 0.75rem;
    }

    .request-form {
      display: grid;
      gap: 1rem;
    }

    .form-divider {
      height: 1px;
      background: rgba(15, 23, 42, 0.08);
      margin: 1rem 0;
    }

    .form-span {
      grid-column: 1 / -1;
    }

    .metric-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 1rem;
    }

    .metric-card {
      background: #ffffff;
      border-radius: 1.2rem;
      padding: 1rem 1.2rem;
      border: 1px solid rgba(99, 102, 241, 0.18);
    }

    .metric-label {
      text-transform: uppercase;
      letter-spacing: 0.06em;
      font-size: 0.75rem;
      color: rgba(15, 23, 42, 0.55);
      margin: 0 0 0.3rem;
    }

    .metric-value {
      margin: 0;
      font-size: 1.55rem;
      font-weight: 700;
      color: #0f172a;
    }

    .metric-helper {
      margin: 0.35rem 0 0;
      font-size: 0.85rem;
      color: var(--muted);
    }

    .timeline-list, .task-list, .board-grid, .update-list, .resource-grid {
      display: grid;
      gap: 0.75rem;
    }

    .timeline-row, .task-row, .board-card, .update-card, .resource-card {
      background: #ffffff;
      border-radius: 1rem;
      border: 1px solid var(--border);
      padding: 0.85rem 1rem;
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem 1rem;
      justify-content: space-between;
      align-items: center;
    }

    .timeline-label {
      font-weight: 600;
      margin: 0;
      color: #0f172a;
    }

    .timeline-date, .timeline-status, .task-status, .update-meta {
      margin: 0;
      font-size: 0.85rem;
      color: var(--muted);
    }

    .board-grid {
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }

    .board-card {
      flex-direction: column;
      align-items: flex-start;
      gap: 0.6rem;
      min-height: 150px;
    }

    .board-card[data-tone="warning"] {
      border-color: rgba(249, 115, 22, 0.3);
      box-shadow: 0 16px 28px -22px rgba(249, 115, 22, 0.55);
    }

    .board-card[data-tone="success"] {
      border-color: rgba(34, 197, 94, 0.3);
    }

    .board-title {
      margin: 0;
      font-weight: 600;
      color: #0f172a;
    }

    .board-value {
      margin: 0;
      font-size: 1.7rem;
      font-weight: 700;
      color: #0f172a;
    }

    .board-helper {
      margin: 0;
      font-size: 0.9rem;
      color: var(--muted);
    }

    .update-card {
      flex-direction: column;
      align-items: flex-start;
      gap: 0.4rem;
    }

    .update-card strong {
      color: #0f172a;
    }

    .resource-grid {
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    }

    .resource-card {
      flex-direction: column;
      align-items: flex-start;
      gap: 0.6rem;
      min-height: 160px;
    }

    .resource-card h3 {
      margin: 0;
      font-weight: 600;
      color: #0f172a;
    }

    .resource-actions {
      margin-top: auto;
      display: flex;
      gap: 0.75rem;
      flex-wrap: wrap;
    }

    .resource-actions a {
      font-weight: 600;
      font-size: 0.9rem;
      color: var(--primary);
      text-decoration: none;
    }

    .resource-actions a:hover,
    .resource-actions a:focus {
      text-decoration: underline;
    }

    .pipeline-grid,
    .sentiment-list,
    .approval-list,
    .history-list {
      display: grid;
      gap: 0.75rem;
    }

    .pipeline-grid {
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }

    .pipeline-card,
    .sentiment-card,
    .approval-card,
    .history-item {
      background: #ffffff;
      border: 1px solid var(--border);
      border-radius: 1rem;
      padding: 1rem;
      display: grid;
      gap: 0.5rem;
    }

    .pipeline-stage,
    .approval-title {
      margin: 0;
      font-weight: 600;
      color: #0f172a;
    }

    .pipeline-count {
      margin: 0;
      font-size: 1.6rem;
      font-weight: 700;
      color: #0f172a;
    }

    .pipeline-note,
    .approval-meta,
    .approval-details {
      margin: 0;
      font-size: 0.85rem;
      color: var(--muted);
    }

    .sentiment-card strong {
      color: #0f172a;
    }

    .sentiment-tags {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
    }

    .status-pill {
      display: inline-flex;
      align-items: center;
      border-radius: 999px;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      padding: 0.25rem 0.65rem;
      background: rgba(99, 102, 241, 0.12);
      color: #4338ca;
    }

    .status-pill[data-tone="warning"] {
      background: rgba(249, 115, 22, 0.12);
      color: #c2410c;
    }

    .status-pill[data-tone="success"] {
      background: rgba(34, 197, 94, 0.12);
      color: #15803d;
    }

    .status-pill[data-tone="error"] {
      background: rgba(239, 68, 68, 0.12);
      color: #b91c1c;
    }

    .approval-header {
      display: flex;
      flex-wrap: wrap;
      gap: 0.75rem;
      justify-content: space-between;
      align-items: center;
    }

    .history-item {
      border-style: dashed;
    }

    .panel h3 {
      margin: 0.5rem 0 0;
      font-size: 1rem;
      font-weight: 600;
    }

    form {
      margin: 0;
    }

    .form-grid {
      display: grid;
      gap: 1rem;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }

    .form-group {
      display: grid;
      gap: 0.35rem;
    }

    .form-group label {
      font-size: 0.85rem;
      font-weight: 600;
      color: #0f172a;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      border-radius: 0.75rem;
      border: 1px solid rgba(15, 23, 42, 0.12);
      background: #ffffff;
      padding: 0.6rem 0.75rem;
      font-size: 0.95rem;
      font-family: inherit;
      color: #0f172a;
    }

    .form-group textarea {
      min-height: 96px;
      resize: vertical;
    }

    .form-note {
      margin: 0.25rem 0 0;
      font-size: 0.85rem;
      color: var(--muted);
    }

    .form-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 0.75rem;
      align-items: center;
      margin-top: 1rem;
    }

    .primary-btn {
      border: none;
      background: var(--primary);
      color: #f8fafc;
      padding: 0.6rem 1.3rem;
      border-radius: 999px;
      font-weight: 600;
      cursor: pointer;
      box-shadow: 0 16px 32px -28px rgba(99, 102, 241, 0.8);
    }

    .primary-btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }

    .form-feedback {
      font-size: 0.85rem;
      margin: 0.5rem 0 0;
      color: var(--muted);
    }

    .form-feedback[data-tone="success"] {
      color: #15803d;
    }

    .form-feedback[data-tone="error"] {
      color: #b91c1c;
    }

    .form-feedback[data-tone="info"] {
      color: #4338ca;
    }

    .details-grid {
      display: grid;
      gap: 0.75rem;
      grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    }

    .details-grid span {
      display: block;
      font-size: 0.85rem;
      color: var(--muted);
    }

    .details-grid strong {
      display: block;
      font-weight: 600;
      margin-bottom: 0.2rem;
      color: #0f172a;
    }

    .empty {
      margin: 0;
      color: var(--muted);
      font-size: 0.9rem;
    }

    @media (max-width: 720px) {
      body { padding: 1.5rem; }
      .dashboard-shell { border-radius: 1.5rem; }
    }
  </style>
</head>
<body data-role="employee">
  <main class="dashboard-shell">
    <header>
      <div>
        <p class="eyebrow">Employee portal</p>
        <h1>Hi, <?= htmlspecialchars($displayName); ?></h1>
        <p class="subhead">
          Signed in as <?= htmlspecialchars($userEmail); ?>
          <?php if ($lastLogin): ?>
            · Last login <?= htmlspecialchars($lastLogin); ?>
          <?php endif; ?>
        </p>
      </div>
      <form method="post" action="logout.php">
        <button class="logout-btn" type="submit">Sign out</button>
      </form>
    </header>

    <?php foreach ($flashMessages['success'] as $message): ?>
      <div class="status-banner"><?= htmlspecialchars($message); ?></div>
    <?php endforeach; ?>

    <?php foreach ($flashMessages['error'] as $message): ?>
      <div class="status-banner" data-tone="error"><?= htmlspecialchars($message); ?></div>
    <?php endforeach; ?>

    <section class="panel" id="pipeline-overview">
      <h2>Customer pipeline overview</h2>
      <p class="lead">Monitor every stage of the customer lifecycle. Your updates are routed for admin approval before they publish.</p>
      <div class="metric-grid">
        <?php foreach ($customerSegmentStats as $stat): ?>
          <div class="metric-card">
            <p class="metric-label"><?= htmlspecialchars($stat['label']); ?></p>
            <p class="metric-value"><?= htmlspecialchars((string) $stat['count']); ?></p>
            <p class="metric-helper">records tracked</p>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <?php foreach ($customerSegments as $segmentSlug => $segmentData): ?>
      <?php
        $segmentLabel = $segmentData['label'] ?? ucfirst(str_replace('-', ' ', $segmentSlug));
        $segmentDescription = $segmentData['description'] ?? '';
        $segmentColumns = $segmentData['columns'] ?? [];
        $segmentEntries = $segmentData['entries'] ?? [];
        $segmentAnchor = 'segment-' . $segmentSlug;
      ?>
      <section class="panel" id="<?= htmlspecialchars($segmentAnchor); ?>">
        <h2><?= htmlspecialchars($segmentLabel); ?></h2>
        <p class="lead">
          <?= htmlspecialchars($segmentDescription); ?>
          <br />
          <small>Submit additions or edits for admin approval. Updates go live only after review.</small>
        </p>

        <?php if (empty($segmentEntries)): ?>
          <p>No records captured yet. Use the form below to propose the first entry.</p>
        <?php else: ?>
          <div class="table-wrapper">
            <table class="customer-table">
              <thead>
                <tr>
                  <?php foreach ($segmentColumns as $column): ?>
                    <?php if (!is_array($column) || !isset($column['key'])) { continue; } ?>
                    <th><?= htmlspecialchars($column['label'] ?? ucfirst(str_replace('_', ' ', (string) $column['key']))); ?></th>
                  <?php endforeach; ?>
                  <th>Notes</th>
                  <th>Reminder</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($segmentEntries as $entry): ?>
                  <tr>
                    <?php foreach ($segmentColumns as $column): ?>
                      <?php
                        if (!is_array($column) || !isset($column['key'])) {
                          continue;
                        }
                        $columnKey = $column['key'];
                        $cellValue = trim((string) ($entry['fields'][$columnKey] ?? ''));
                      ?>
                      <td><?= htmlspecialchars($cellValue); ?></td>
                    <?php endforeach; ?>
                    <td><?= htmlspecialchars($entry['notes'] ?? ''); ?></td>
                    <td><?= htmlspecialchars($entry['reminder_on'] ?? ''); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <?php foreach ($segmentEntries as $entry): ?>
            <?php
              $entryFields = $entry['fields'] ?? [];
              $displayName = '';
              foreach ($segmentColumns as $column) {
                $columnKey = $column['key'] ?? null;
                if ($columnKey && isset($entryFields[$columnKey])) {
                  $candidate = trim((string) $entryFields[$columnKey]);
                  if ($candidate !== '') {
                    $displayName = $candidate;
                    break;
                  }
                }
              }
              if ($displayName === '') {
                $displayName = $segmentLabel . ' record';
              }
              $lastUpdated = $formatDateTime($entry['updated_at'] ?? $entry['created_at'] ?? null, 'Recently');
              $currentReminder = $entry['reminder_on'] ?? '—';
            ?>
            <details class="request-block">
              <summary>
                <span><?= htmlspecialchars($displayName); ?></span>
                <span class="status-pill">Prepare update</span>
              </summary>
              <p class="form-note">Current reminder: <?= htmlspecialchars($currentReminder === '' ? '—' : $currentReminder); ?> · Last updated <?= htmlspecialchars($lastUpdated); ?></p>
              <form method="post" class="request-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
                <input type="hidden" name="action" value="request_update_customer" />
                <input type="hidden" name="redirect_anchor" value="<?= htmlspecialchars($segmentAnchor); ?>" />
                <input type="hidden" name="segment" value="<?= htmlspecialchars($segmentSlug); ?>" />
                <input type="hidden" name="entry_id" value="<?= htmlspecialchars($entry['id'] ?? ''); ?>" />
                <div class="form-grid">
                  <?php foreach ($segmentColumns as $column): ?>
                    <?php
                      if (!is_array($column) || !isset($column['key'])) {
                        continue;
                      }
                      $columnKey = $column['key'];
                      $columnLabel = $column['label'] ?? ucfirst(str_replace('_', ' ', (string) $columnKey));
                      $inputType = match ($column['type'] ?? 'text') {
                        'date' => 'date',
                        'phone' => 'tel',
                        'number' => 'number',
                        'email' => 'email',
                        default => 'text',
                      };
                      $value = $entryFields[$columnKey] ?? '';
                    ?>
                    <div class="form-group">
                      <label><?= htmlspecialchars($columnLabel); ?></label>
                      <input name="fields[<?= htmlspecialchars($columnKey); ?>]" type="<?= htmlspecialchars($inputType); ?>" value="<?= htmlspecialchars((string) $value); ?>" />
                    </div>
                  <?php endforeach; ?>
                </div>
                <div class="form-grid">
                  <div class="form-group">
                    <label>Internal notes</label>
                    <textarea name="notes" rows="2" placeholder="Reminder notes or context"><?= htmlspecialchars($entry['notes'] ?? ''); ?></textarea>
                  </div>
                  <div class="form-group">
                    <label>Reminder date</label>
                    <input name="reminder_on" type="date" value="<?= htmlspecialchars($entry['reminder_on'] ?? ''); ?>" />
                  </div>
                  <div class="form-group">
                    <label>Move to segment</label>
                    <select name="target_segment">
                      <?php foreach ($customerSegmentStats as $optionSlug => $option): ?>
                        <option value="<?= htmlspecialchars($optionSlug); ?>" <?= $optionSlug === $segmentSlug ? 'selected' : ''; ?>><?= htmlspecialchars($option['label']); ?></option>
                      <?php endforeach; ?>
                    </select>
                    <p class="form-note">Keep <?= htmlspecialchars(strtolower($segmentLabel)); ?> selected to stay in place.</p>
                  </div>
                </div>
                <div class="form-group form-span">
                  <label>Context for the admin team</label>
                  <textarea name="justification" rows="3" placeholder="Share what changed or why this record should move" required></textarea>
                </div>
                <div class="form-actions">
                  <button class="primary-btn" type="submit">Submit update request</button>
                </div>
              </form>
            </details>
          <?php endforeach; ?>
        <?php endif; ?>

        <div class="form-divider"></div>

        <h3>Propose a new <?= htmlspecialchars($segmentLabel); ?> record</h3>
        <form method="post" class="request-form">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
          <input type="hidden" name="action" value="request_add_customer" />
          <input type="hidden" name="redirect_anchor" value="<?= htmlspecialchars($segmentAnchor); ?>" />
          <input type="hidden" name="segment" value="<?= htmlspecialchars($segmentSlug); ?>" />
          <div class="form-grid">
            <?php foreach ($segmentColumns as $column): ?>
              <?php
                if (!is_array($column) || !isset($column['key'])) {
                  continue;
                }
                $columnKey = $column['key'];
                $columnLabel = $column['label'] ?? ucfirst(str_replace('_', ' ', (string) $columnKey));
                $inputType = match ($column['type'] ?? 'text') {
                  'date' => 'date',
                  'phone' => 'tel',
                  'number' => 'number',
                  'email' => 'email',
                  default => 'text',
                };
              ?>
              <div class="form-group">
                <label><?= htmlspecialchars($columnLabel); ?></label>
                <input name="fields[<?= htmlspecialchars($columnKey); ?>]" type="<?= htmlspecialchars($inputType); ?>" />
              </div>
            <?php endforeach; ?>
          </div>
          <div class="form-grid">
            <div class="form-group">
              <label>Internal notes</label>
              <textarea name="notes" rows="2" placeholder="Reminder notes or context"></textarea>
            </div>
            <div class="form-group">
              <label>Reminder date</label>
              <input name="reminder_on" type="date" />
            </div>
          </div>
          <div class="form-group form-span">
            <label>Context for the admin team</label>
            <textarea name="justification" rows="3" placeholder="Share the background or next steps" required></textarea>
          </div>
          <div class="form-actions">
            <button class="primary-btn" type="submit">Send for approval</button>
          </div>
        </form>
      </section>
    <?php endforeach; ?>

    <section class="panel" id="approvals">
      <h2>Pending admin approvals</h2>
      <p class="lead">Track requests you have raised with the admin team.</p>
      <?php if (empty($pendingApprovals)): ?>
        <p>No approval requests logged yet.</p>
      <?php else: ?>
        <div class="approval-list">
          <?php foreach ($pendingApprovals as $request): ?>
            <?php
              $statusLabel = $request['status'] ?? 'Pending admin review';
              $submittedAt = $formatDateTime($request['submitted_at'] ?? null, '—');
              $owner = $request['owner'] ?? 'Admin team';
              $effectiveDate = $request['effective_date'] ?? '';
            ?>
            <article class="approval-card">
              <div class="approval-header">
                <p class="approval-title"><?= htmlspecialchars($request['title'] ?? 'Request'); ?></p>
                <span class="status-pill"><?= htmlspecialchars($statusLabel); ?></span>
              </div>
              <p class="approval-meta">ID <?= htmlspecialchars($request['id'] ?? '—'); ?> • Submitted <?= htmlspecialchars($submittedAt); ?> • Routed to <?= htmlspecialchars($owner); ?></p>
              <?php if (!empty($request['details'])): ?>
                <p class="approval-details"><?= htmlspecialchars($request['details']); ?></p>
              <?php endif; ?>
              <?php if ($effectiveDate !== ''): ?>
                <p class="approval-meta">Target effective date: <?= htmlspecialchars($effectiveDate); ?></p>
              <?php endif; ?>
              <?php if (!empty($request['last_update'])): ?>
                <p class="approval-meta"><?= htmlspecialchars($request['last_update']); ?></p>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <h3>Submit a general approval request</h3>
      <form method="post" class="request-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
        <input type="hidden" name="action" value="submit_general_request" />
        <input type="hidden" name="redirect_anchor" value="approvals" />
        <div class="form-grid">
          <div class="form-group">
            <label for="change-type">Change type</label>
            <select id="change-type" name="change_type" required>
              <option value="">Choose a request</option>
              <option value="Payroll bank update">Payroll bank update</option>
              <option value="Access scope change">Access scope change</option>
              <option value="Desk relocation">Desk relocation</option>
              <option value="Long leave / remote work">Long leave / remote work</option>
            </select>
          </div>
          <div class="form-group">
            <label for="change-effective">Target effective date</label>
            <input id="change-effective" name="effective_date" type="date" />
          </div>
        </div>
        <div class="form-group form-span">
          <label for="change-justification">Summary for the admin team</label>
          <textarea id="change-justification" name="justification" placeholder="Add context, ticket IDs, or the reason for the change" required></textarea>
        </div>
        <div class="form-actions">
          <button class="primary-btn" type="submit">Submit for approval</button>
        </div>
      </form>

      <h3>Decision history</h3>
      <?php if (empty($approvalHistory)): ?>
        <p>No admin decisions recorded yet.</p>
      <?php else: ?>
        <div class="history-list">
          <?php foreach ($approvalHistory as $record): ?>
            <?php
              $status = $record['status'] ?? 'Completed';
              $resolvedOn = $formatDateTime($record['resolved_at'] ?? $record['resolved'] ?? null, '—');
              $outcome = $record['outcome'] ?? '';
              $tone = 'info';
              $statusLower = strtolower($status);
              if (strpos($statusLower, 'decline') !== false || strpos($statusLower, 'reject') !== false) {
                $tone = 'error';
              } elseif (strpos($statusLower, 'approve') !== false || strpos($statusLower, 'complete') !== false) {
                $tone = 'success';
              }
            ?>
            <article class="history-item">
              <div class="approval-header">
                <p class="approval-title"><?= htmlspecialchars($record['title'] ?? 'Request'); ?> · <?= htmlspecialchars($record['id'] ?? ''); ?></p>
                <span class="status-pill" data-tone="<?= htmlspecialchars($tone); ?>"><?= htmlspecialchars($status); ?></span>
              </div>
              <p class="approval-meta">Resolved <?= htmlspecialchars($resolvedOn); ?></p>
              <?php if ($outcome !== ''): ?>
                <p class="approval-meta"><?= htmlspecialchars($outcome); ?></p>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="panel" id="design-change">
      <h2>Propose website design update</h2>
      <p class="lead">Recommend theme tweaks or seasonal announcements. Admin approval is required before changes go live.</p>
      <form method="post" class="request-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
        <input type="hidden" name="action" value="request_theme_change" />
        <input type="hidden" name="redirect_anchor" value="design-change" />
        <div class="form-grid">
          <div class="form-group">
            <label for="theme-label">Theme headline</label>
            <input id="theme-label" name="season_label" type="text" placeholder="e.g. Winter savings drive" required />
          </div>
          <div class="form-group">
            <label for="theme-color">Accent colour</label>
            <input id="theme-color" name="accent_color" type="text" value="#2563EB" />
            <p class="form-note">Use HEX format, e.g. #2563EB.</p>
          </div>
          <div class="form-group">
            <label for="theme-effective">Planned go-live date</label>
            <input id="theme-effective" name="effective_date" type="date" />
          </div>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label for="theme-background">Hero / background image</label>
            <input id="theme-background" name="background_image" type="text" placeholder="images/hero/winter-campaign.jpg" />
          </div>
          <div class="form-group">
            <label for="theme-announcement">Announcement banner</label>
            <input id="theme-announcement" name="announcement" type="text" placeholder="Short announcement for the homepage" />
          </div>
        </div>
        <div class="form-group form-span">
          <label for="theme-justification">Why this change matters</label>
          <textarea id="theme-justification" name="justification" rows="3" placeholder="Share campaign goals, timelines, or supporting details" required></textarea>
        </div>
        <div class="form-actions">
          <button class="primary-btn" type="submit">Share with admin</button>
        </div>
      </form>
    </section>

    <section class="panel" id="profile">
      <h2>Your profile &amp; contact preferences</h2>
      <div class="details-grid">
        <div>
          <strong>User ID</strong>
          <span><?= htmlspecialchars($accountId); ?></span>
        </div>
        <div>
          <strong>Role</strong>
          <span><?= htmlspecialchars($roleLabel); ?></span>
        </div>
        <div>
          <strong>Email</strong>
          <span><?= htmlspecialchars($userEmail); ?></span>
        </div>
        <div>
          <strong>Last sign-in</strong>
          <span><?= htmlspecialchars($lastLogin ?? '—'); ?></span>
        </div>
      </div>
      <form method="post" class="request-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
        <input type="hidden" name="action" value="update_profile" />
        <input type="hidden" name="redirect_anchor" value="profile" />
        <div class="form-grid">
          <div class="form-group">
            <label for="profile-phone">Primary phone number</label>
            <input id="profile-phone" name="phone" type="tel" value="<?= htmlspecialchars($normalizedPhone); ?>" placeholder="+91 98765 43210" />
          </div>
          <div class="form-group">
            <label for="profile-city">City / service cluster</label>
            <input id="profile-city" name="city" type="text" value="<?= htmlspecialchars($normalizedCity); ?>" placeholder="Jamshedpur &amp; Bokaro" />
          </div>
          <div class="form-group">
            <label for="profile-emergency">Emergency contact</label>
            <input id="profile-emergency" name="emergency_contact" type="text" value="<?= htmlspecialchars($userEmergencyContact); ?>" placeholder="Name · Phone number" />
          </div>
          <div class="form-group">
            <label for="profile-hours">Working hours preference</label>
            <input id="profile-hours" name="working_hours" type="text" value="<?= htmlspecialchars($userWorkingHours); ?>" placeholder="10:00 – 18:30 IST" />
          </div>
          <div class="form-group">
            <label for="profile-channel">Preferred communication channel</label>
            <select id="profile-channel" name="preferred_channel">
              <option value="Phone call" <?= $userPreferredChannel === 'Phone call' ? 'selected' : ''; ?>>Phone call</option>
              <option value="WhatsApp" <?= $userPreferredChannel === 'WhatsApp' ? 'selected' : ''; ?>>WhatsApp</option>
              <option value="Email summary" <?= $userPreferredChannel === 'Email summary' ? 'selected' : ''; ?>>Email summary</option>
            </select>
          </div>
          <div class="form-group">
            <label for="profile-desk">Desk location</label>
            <input id="profile-desk" name="desk_location" type="text" value="<?= htmlspecialchars($userDeskLocation); ?>" placeholder="Operations HQ" />
          </div>
          <div class="form-group">
            <label for="profile-manager">Reporting manager</label>
            <input id="profile-manager" name="reporting_manager" type="text" value="<?= htmlspecialchars($userReportingManager); ?>" placeholder="Manager name" />
          </div>
        </div>
        <div class="form-actions">
          <button class="primary-btn" type="submit">Save updates</button>
        </div>
      </form>
    </section>
  </main>

</body>
</html>
