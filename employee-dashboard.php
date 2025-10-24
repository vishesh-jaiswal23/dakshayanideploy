<?php
declare(strict_types=1);

session_start();

const EMPLOYEE_DEFAULT_VIEW = 'overview';
const EMPLOYEE_TICKETS_FILE = __DIR__ . '/server/data/tickets.json';

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
$initialCharacter = mb_substr($displayName, 0, 1, 'UTF-8');
if ($initialCharacter === false || $initialCharacter === '') {
  $initialCharacter = substr((string) $displayName, 0, 1);
}
$displayInitial = strtoupper($initialCharacter !== '' ? $initialCharacter : 'D');

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

$employeeViews = [
  'overview' => 'Overview',
  'customers' => 'Customer records',
  'approvals' => 'Approvals & requests',
  'content' => 'Content manager',
  'design' => 'Website design updates',
  'complaints' => 'Complaint tracking',
  'profile' => 'Profile & preferences',
];

$employeeSidebarIcons = [
  'overview' => '<path d="M3 6h18M3 12h18M3 18h18" />',
  'customers' => '<path d="M4 4h16v6H4z" /><path d="M2 14h20" /><path d="M7 20h10" />',
  'approvals' => '<path d="M9 11l3 3L22 4" /><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />',
  'content' => '<path d="M4 4h16v16H4z" /><path d="M9 9h6" /><path d="M9 15h6" />',
  'design' => '<path d="m4 19.5 6.5-6.5" /><path d="M3 7h18" /><path d="m14 2 7 7" /><path d="M8 21h8" />',
  'complaints' => '<path d="M12 9v4" /><path d="M12 17h.01" /><circle cx="12" cy="12" r="10" />',
  'profile' => '<circle cx="12" cy="7" r="4" /><path d="M5.5 21a7.5 7.5 0 0 1 13 0" />',
];

$requestedView = $_GET['view'] ?? EMPLOYEE_DEFAULT_VIEW;
if (!is_string($requestedView)) {
  $requestedView = EMPLOYEE_DEFAULT_VIEW;
}

$currentView = array_key_exists($requestedView, $employeeViews) ? $requestedView : EMPLOYEE_DEFAULT_VIEW;

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

function employee_redirect(?string $anchor = null, ?string $view = null): void
{
  $target = 'employee-dashboard.php';
  if ($view !== null && $view !== '' && $view !== EMPLOYEE_DEFAULT_VIEW) {
    $target .= '?view=' . urlencode($view);
  }

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

function employee_parse_paragraphs(string $value): array
{
  $paragraphs = preg_split("/\n{2,}/", $value);
  if ($paragraphs === false) {
    return [];
  }

  return array_values(array_filter(array_map(static fn($paragraph) => trim((string) $paragraph), $paragraphs), static fn($paragraph) => $paragraph !== ''));
}

function employee_parse_newline_list(string $value): array
{
  $lines = preg_split("/\r?\n/", $value);
  if ($lines === false) {
    return [];
  }

  return array_values(array_filter(array_map(static fn($line) => trim((string) $line), $lines), static fn($line) => $line !== ''));
}

function employee_read_tickets(): array
{
  if (!file_exists(EMPLOYEE_TICKETS_FILE)) {
    return [];
  }

  $json = file_get_contents(EMPLOYEE_TICKETS_FILE);
  if ($json === false || $json === '') {
    return [];
  }

  $decoded = json_decode($json, true);
  if (!is_array($decoded)) {
    return [];
  }

  return array_values(array_filter($decoded, static fn($ticket) => is_array($ticket)));
}

function employee_prepare_recent_complaints(array $tickets, int $limit = 6): array
{
  $prepared = [];

  foreach ($tickets as $ticket) {
    if (!is_array($ticket)) {
      continue;
    }

    $createdAt = $ticket['createdAt'] ?? $ticket['created_at'] ?? '';
    $createdAtFormatted = $createdAt !== '' && strtotime($createdAt)
      ? date('j M Y, g:i A', strtotime($createdAt))
      : '—';

    $issueLabels = [];
    if (isset($ticket['issueLabels']) && is_array($ticket['issueLabels'])) {
      $issueLabels = array_values(array_filter(array_map(static fn($value) => trim((string) $value), $ticket['issueLabels'])));
    }

    $prepared[] = [
      'id' => $ticket['id'] ?? '',
      'subject' => $ticket['subject'] ?? 'Website complaint',
      'requesterName' => $ticket['requesterName'] ?? 'Customer',
      'requesterPhone' => $ticket['requesterPhone'] ?? '',
      'issueLabels' => $issueLabels,
      'priority' => ucfirst(strtolower((string) ($ticket['priority'] ?? 'medium'))),
      'status' => ucfirst(strtolower((string) ($ticket['status'] ?? 'open'))),
      'channel' => ucfirst(strtolower((string) ($ticket['channel'] ?? 'web'))),
      'createdAt' => $createdAt,
      'createdAtFormatted' => $createdAtFormatted,
    ];
  }

  usort($prepared, static function (array $a, array $b): int {
    return strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? '');
  });

  return array_slice($prepared, 0, $limit);
}

function employee_analyse_tickets(array $tickets): array
{
  $statusCounts = [];
  $priorityCounts = [];
  $channelCounts = [];
  $latestUpdate = null;
  $openStatuses = ['open', 'pending', 'new', 'in-progress'];
  $highPriorityLabels = ['high', 'urgent'];
  $openCount = 0;
  $highPriority = 0;

  foreach ($tickets as $ticket) {
    if (!is_array($ticket)) {
      continue;
    }

    $status = strtolower(trim((string) ($ticket['status'] ?? 'open')));
    if ($status === '') {
      $status = 'open';
    }
    $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
    if (in_array($status, $openStatuses, true)) {
      $openCount++;
    }

    $priority = strtolower(trim((string) ($ticket['priority'] ?? 'medium')));
    if ($priority === '') {
      $priority = 'medium';
    }
    $priorityCounts[$priority] = ($priorityCounts[$priority] ?? 0) + 1;
    if (in_array($priority, $highPriorityLabels, true)) {
      $highPriority++;
    }

    $channel = strtolower(trim((string) ($ticket['channel'] ?? 'web')));
    if ($channel === '') {
      $channel = 'web';
    }
    $channelCounts[$channel] = ($channelCounts[$channel] ?? 0) + 1;

    $updatedAt = $ticket['updatedAt'] ?? $ticket['updated_at'] ?? $ticket['createdAt'] ?? $ticket['created_at'] ?? null;
    if (is_string($updatedAt) && $updatedAt !== '') {
      if ($latestUpdate === null || strcmp($updatedAt, $latestUpdate) > 0) {
        $latestUpdate = $updatedAt;
      }
    }
  }

  ksort($statusCounts);
  ksort($priorityCounts);
  ksort($channelCounts);

  $total = count($tickets);

  return [
    'total' => $total,
    'open' => $openCount,
    'closed' => max($total - $openCount, 0),
    'highPriority' => $highPriority,
    'statusCounts' => $statusCounts,
    'priorityCounts' => $priorityCounts,
    'channelCounts' => $channelCounts,
    'latestUpdate' => $latestUpdate,
  ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  $anchor = $_POST['redirect_anchor'] ?? '';
  $requestedRedirectView = $_POST['redirect_view'] ?? EMPLOYEE_DEFAULT_VIEW;
  if (!is_string($requestedRedirectView) || !isset($employeeViews[$requestedRedirectView])) {
    $requestedRedirectView = EMPLOYEE_DEFAULT_VIEW;
  }
  $redirectView = $requestedRedirectView;
  if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    employee_flash('error', 'Security token mismatch. Please try again.');
    employee_redirect($anchor, $redirectView);
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

      employee_redirect($anchor, $redirectView);
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
        employee_redirect($anchor, $redirectView);
      }

      $segment = $state['customer_registry']['segments'][$segmentSlug];
      $columns = $segment['columns'] ?? [];
      [$normalizedFields, $hasValue] = employee_sanitize_columns($columns, $fieldsInput);

      if (!$hasValue && $notes === '') {
        employee_flash('error', 'Add at least one field or a note before submitting.');
        employee_redirect($anchor, $redirectView);
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

      employee_redirect($anchor, $redirectView);
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
        employee_redirect($anchor, $redirectView);
      }

      if (!isset($registrySegments[$targetSegment])) {
        employee_flash('error', 'Target segment is invalid.');
        employee_redirect($anchor, $redirectView);
      }

      $segment = $registrySegments[$segmentSlug];
      $columns = $segment['columns'] ?? [];
      [$normalizedFields, $hasValue] = employee_sanitize_columns($columns, $fieldsInput);

      if (!$hasValue && $notes === '' && $segmentSlug === $targetSegment) {
        employee_flash('error', 'Provide updated details or notes for the admin team.');
        employee_redirect($anchor, $redirectView);
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

      employee_redirect($anchor, $redirectView);
      break;

    case 'submit_general_request':
      $changeType = trim($_POST['change_type'] ?? '');
      $effectiveDate = trim($_POST['effective_date'] ?? '');
      $justification = trim($_POST['justification'] ?? '');

      if ($changeType === '') {
        employee_flash('error', 'Select the type of change you are requesting.');
        employee_redirect($anchor, $redirectView);
      }

      if ($justification === '') {
        employee_flash('error', 'Add a short justification so the admin team can review it.');
        employee_redirect($anchor, $redirectView);
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

      employee_redirect($anchor, $redirectView);
      break;

    case 'propose_hero_update':
      $heroTitle = trim($_POST['hero_title'] ?? '');
      $heroSubtitle = trim($_POST['hero_subtitle'] ?? '');
      $heroImage = trim($_POST['hero_image'] ?? '');
      $heroImageCaption = trim($_POST['hero_image_caption'] ?? '');
      $heroBubbleHeading = trim($_POST['hero_bubble_heading'] ?? '');
      $heroBubbleBody = trim($_POST['hero_bubble_body'] ?? '');
      $heroBullets = employee_parse_newline_list($_POST['hero_bullets'] ?? '');
      $heroJustification = trim($_POST['hero_justification'] ?? '');
      $heroEffectiveDate = trim($_POST['hero_effective_date'] ?? '');

      if ($heroTitle === '' || $heroSubtitle === '') {
        employee_flash('error', 'Provide both a title and subtitle for the homepage hero.');
        $anchor = $anchor === '' ? 'content-hero' : $anchor;
        $redirectView = 'content';
        employee_redirect($anchor, $redirectView);
      }

      if ($heroJustification === '') {
        employee_flash('error', 'Share the reasoning behind the hero update so the admin can review it.');
        $anchor = $anchor === '' ? 'content-hero' : $anchor;
        $redirectView = 'content';
        employee_redirect($anchor, $redirectView);
      }

      $currentHero = $state['home_hero'] ?? [];
      if ($heroImage === '') {
        $heroImage = $currentHero['image'] ?? 'images/hero/hero.png';
      }

      $requestId = portal_next_employee_request_id($state);
      $submittedAt = date('c');
      $request = [
        'id' => $requestId,
        'type' => 'content_change',
        'title' => 'Homepage hero update',
        'status' => 'Pending admin review',
        'submitted_at' => $submittedAt,
        'submitted_by' => $displayName,
        'owner' => 'Site content admins',
        'details' => $heroJustification,
        'effective_date' => $heroEffectiveDate,
        'last_update' => 'Submitted on ' . date('j M Y, g:i A', strtotime($submittedAt)),
        'payload' => [
          'scope' => 'hero_update',
          'hero' => [
            'title' => $heroTitle,
            'subtitle' => $heroSubtitle,
            'image' => $heroImage,
            'image_caption' => $heroImageCaption,
            'bubble_heading' => $heroBubbleHeading,
            'bubble_body' => $heroBubbleBody,
            'bullets' => $heroBullets,
          ],
          'effective_date' => $heroEffectiveDate,
          'justification' => $heroJustification,
        ],
      ];

      portal_add_employee_request($state, $request);

      if (portal_save_state($state)) {
        employee_flash('success', 'Hero update proposal shared with the admin team.');
      } else {
        employee_flash('error', 'Unable to record the hero update proposal right now.');
      }

      $anchor = $anchor === '' ? 'content-hero' : $anchor;
      $redirectView = 'content';
      employee_redirect($anchor, $redirectView);
      break;

    case 'propose_section_update':
      $mode = strtolower(trim($_POST['section_mode'] ?? 'create')) === 'update' ? 'update' : 'create';
      $targetSection = trim($_POST['target_section'] ?? '');
      $sectionEyebrow = trim($_POST['section_eyebrow'] ?? '');
      $sectionTitle = trim($_POST['section_title'] ?? '');
      $sectionSubtitle = trim($_POST['section_subtitle'] ?? '');
      $sectionBody = employee_parse_paragraphs($_POST['section_body'] ?? '');
      $sectionBullets = employee_parse_newline_list($_POST['section_bullets'] ?? '');
      $sectionStatus = strtolower(trim($_POST['section_status'] ?? 'draft'));
      $sectionDisplayOrder = (int) ($_POST['section_display_order'] ?? 0);
      $sectionCtaText = trim($_POST['section_cta_text'] ?? '');
      $sectionCtaUrl = trim($_POST['section_cta_url'] ?? '');
      $backgroundStyle = strtolower(trim($_POST['section_background_style'] ?? 'section'));
      $mediaType = strtolower(trim($_POST['section_media_type'] ?? 'none'));
      $mediaSrc = trim($_POST['section_media_src'] ?? '');
      $mediaAlt = trim($_POST['section_media_alt'] ?? '');
      $sectionJustification = trim($_POST['section_justification'] ?? '');
      $sectionEffectiveDate = trim($_POST['section_effective_date'] ?? '');

      if ($mode === 'update' && $targetSection === '') {
        employee_flash('error', 'Select the section you want to update.');
        $anchor = $anchor === '' ? 'content-sections' : $anchor;
        $redirectView = 'content';
        employee_redirect($anchor, $redirectView);
      }

      if ($sectionJustification === '') {
        employee_flash('error', 'Share a justification so the admin understands the change.');
        $anchor = $anchor === '' ? 'content-sections' : $anchor;
        $redirectView = 'content';
        employee_redirect($anchor, $redirectView);
      }

      if (!in_array($sectionStatus, ['draft', 'published'], true)) {
        $sectionStatus = 'draft';
      }

      if (!in_array($backgroundStyle, $paletteKeys, true) || $backgroundStyle === 'accent') {
        $backgroundStyle = 'section';
      }

      if (!in_array($mediaType, ['image', 'video', 'none'], true)) {
        $mediaType = 'none';
      }

      if ($mediaType === 'none') {
        $mediaSrc = '';
        $mediaAlt = '';
      }

      $hasContent = $sectionTitle !== '' || $sectionSubtitle !== '' || !empty($sectionBody) || !empty($sectionBullets) || $sectionCtaText !== '' || $sectionCtaUrl !== '';
      if ($mode === 'create' && !$hasContent) {
        employee_flash('error', 'Add the content for the new section before submitting.');
        $anchor = $anchor === '' ? 'content-sections' : $anchor;
        $redirectView = 'content';
        employee_redirect($anchor, $redirectView);
      }

      if ($mode === 'update' && !$hasContent) {
        employee_flash('error', 'Share the updated content you want to publish.');
        $anchor = $anchor === '' ? 'content-sections' : $anchor;
        $redirectView = 'content';
        employee_redirect($anchor, $redirectView);
      }

      $requestId = portal_next_employee_request_id($state);
      $submittedAt = date('c');
      $requestTitle = $mode === 'update'
        ? 'Update section: ' . ($sectionTitle !== '' ? $sectionTitle : $targetSection)
        : 'New homepage section proposal';

      $request = [
        'id' => $requestId,
        'type' => 'content_change',
        'title' => $requestTitle,
        'status' => 'Pending admin review',
        'submitted_at' => $submittedAt,
        'submitted_by' => $displayName,
        'owner' => 'Site content admins',
        'details' => $sectionJustification,
        'effective_date' => $sectionEffectiveDate,
        'last_update' => 'Submitted on ' . date('j M Y, g:i A', strtotime($submittedAt)),
        'payload' => [
          'scope' => 'section',
          'mode' => $mode,
          'targetId' => $mode === 'update' ? $targetSection : null,
          'section' => [
            'eyebrow' => $sectionEyebrow,
            'title' => $sectionTitle,
            'subtitle' => $sectionSubtitle,
            'body' => $sectionBody,
            'bullets' => $sectionBullets,
            'cta' => [
              'text' => $sectionCtaText,
              'url' => $sectionCtaUrl,
            ],
            'media' => [
              'type' => $mediaType,
              'src' => $mediaSrc,
              'alt' => $mediaType === 'image' ? $mediaAlt : '',
            ],
            'background_style' => $backgroundStyle,
            'display_order' => $sectionDisplayOrder,
            'status' => $sectionStatus,
          ],
          'effective_date' => $sectionEffectiveDate,
          'justification' => $sectionJustification,
        ],
      ];

      portal_add_employee_request($state, $request);

      if (portal_save_state($state)) {
        employee_flash('success', 'Homepage section request sent to the admin team.');
      } else {
        employee_flash('error', 'Unable to record the homepage section request right now.');
      }

      $anchor = $anchor === '' ? 'content-sections' : $anchor;
      $redirectView = 'content';
      employee_redirect($anchor, $redirectView);
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
        employee_redirect($anchor, $redirectView);
      }

      if ($justification === '') {
        employee_flash('error', 'Share the reason behind the design change so the admin can review.');
        employee_redirect($anchor, $redirectView);
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

      employee_redirect($anchor, $redirectView);
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

$siteTheme = $state['site_theme'] ?? [];
$homeHero = $state['home_hero'] ?? [];
$homeSections = $state['home_sections'] ?? [];
$homeHeroBulletsValue = implode("\n", $homeHero['bullets'] ?? []);
$paletteKeys = array_keys($siteTheme['palette'] ?? []);
$sortedHomeSections = $homeSections;
usort($sortedHomeSections, static function (array $a, array $b): int {
  $orderA = $a['display_order'] ?? 0;
  $orderB = $b['display_order'] ?? 0;
  if ($orderA === $orderB) {
    return strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? '');
  }

  return $orderA <=> $orderB;
});

$tickets = employee_read_tickets();
$recentComplaints = employee_prepare_recent_complaints($tickets, 6);
$openComplaintsCount = 0;
foreach ($tickets as $ticket) {
  if (!is_array($ticket)) {
    continue;
  }

  $status = strtolower((string) ($ticket['status'] ?? ''));
  if ($status === '' || in_array($status, ['open', 'pending', 'new', 'in-progress'], true)) {
    $openComplaintsCount++;
  }
}

$employeeTicketInsights = employee_analyse_tickets($tickets);
$allEmployeeComplaintRows = employee_prepare_recent_complaints($tickets, count($tickets));

$lastDataRefreshLabel = '';
$lastDataRefreshTimestamp = null;
$ticketFileTimestamp = file_exists(EMPLOYEE_TICKETS_FILE) ? filemtime(EMPLOYEE_TICKETS_FILE) : false;
if ($ticketFileTimestamp !== false) {
  $lastDataRefreshTimestamp = $ticketFileTimestamp;
}

$stateUpdatedTimestamp = portal_parse_datetime($state['last_updated'] ?? null);
if ($stateUpdatedTimestamp !== null) {
  if ($lastDataRefreshTimestamp === null || $stateUpdatedTimestamp > $lastDataRefreshTimestamp) {
    $lastDataRefreshTimestamp = $stateUpdatedTimestamp;
  }
}

if ($lastDataRefreshTimestamp === null) {
  $stateFileTimestamp = file_exists(PORTAL_DATA_FILE) ? filemtime(PORTAL_DATA_FILE) : false;
  if ($stateFileTimestamp !== false) {
    $lastDataRefreshTimestamp = $stateFileTimestamp;
  }
}

if ($lastDataRefreshTimestamp !== null) {
  $lastDataRefreshLabel = portal_format_datetime($lastDataRefreshTimestamp);
}

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

  <link rel="stylesheet" href="dashboard-modern.css" />
  <style></style>
</head>
<body data-role="employee" data-view="<?= htmlspecialchars($currentView); ?>">
  <div class="dashboard-app">
    <header class="dashboard-topbar">
      <div class="topbar-brand">
        <span class="brand-mark" aria-hidden="true">D</span>
        <span class="brand-text">
          <span class="brand-name">Dakshayani</span>
          <span class="brand-tagline">Employee portal</span>
        </span>
      </div>
      <div class="topbar-actions">
        <span class="role-chip"><?= htmlspecialchars($roleLabel); ?></span>
        <div class="user-pill">
          <span class="user-avatar" aria-hidden="true"><?= htmlspecialchars($displayInitial); ?></span>
          <div class="user-meta">
            <span class="user-name"><?= htmlspecialchars($displayName); ?></span>
            <span class="user-email"><?= htmlspecialchars($userEmail); ?></span>
          </div>
        </div>
        <form method="post" action="logout.php">
          <button class="topbar-logout" type="submit">Sign out</button>
        </form>
      </div>
    </header>
    <main class="dashboard-shell">
      <div class="dashboard-layout">
        <aside class="dashboard-sidebar" aria-label="Employee navigation">
          <div class="sidebar-header">
            <span class="sidebar-label">Navigation</span>
            <p class="sidebar-title">Workspace hub</p>
          </div>
          <nav class="sidebar-nav">
            <?php foreach ($employeeViews as $viewKey => $label): ?>
              <?php
                $href = $viewKey === EMPLOYEE_DEFAULT_VIEW ? 'employee-dashboard.php' : 'employee-dashboard.php?view=' . urlencode($viewKey);
                $isActive = $viewKey === $currentView;
                $iconMarkup = $employeeSidebarIcons[$viewKey] ?? $employeeSidebarIcons['overview'];
              ?>
              <a class="sidebar-link<?= $isActive ? ' is-active' : ''; ?>" href="<?= htmlspecialchars($href); ?>"<?= $isActive ? ' aria-current="true"' : ''; ?>>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <?= $iconMarkup; ?>
                </svg>
                <?= htmlspecialchars($label); ?>
              </a>
            <?php endforeach; ?>
          </nav>
          <p class="sidebar-footnote">
            Switch between workspace views, manage tickets, publish updates, and keep customer sentiment in check without leaving this dashboard.
          </p>
        </aside>
        <div class="dashboard-main">
          <header class="page-header">
            <div class="page-header__content">
              <p class="eyebrow">Employee portal</p>
              <h1>Hi, <?= htmlspecialchars($displayName); ?></h1>
              <p class="subhead">
                Coordinate leads, service tickets, and approvals with the admin team.
                <?php if ($lastLogin): ?>
                  Last sign-in <?= htmlspecialchars($lastLogin); ?>.
                <?php endif; ?>
              </p>
            </div>
            <div class="page-header__aside">
              <div class="page-header__actions">
                <button type="button" class="refresh-button" data-refresh-button>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="23 4 23 10 17 10" />
                    <polyline points="1 20 1 14 7 14" />
                    <path d="M3.51 9a9 9 0 0 1 14.13-3.36L23 10" />
                    <path d="M20.49 15A9 9 0 0 1 6.36 18.36L1 14" />
                  </svg>
                  Refresh data
                </button>
                <?php if ($lastDataRefreshLabel !== ''): ?>
                  <span class="refresh-meta">Last updated <?= htmlspecialchars($lastDataRefreshLabel); ?></span>
                <?php endif; ?>
              </div>
              <div class="hero-cards">
                <article class="hero-card">
                  <span class="hero-card__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="12" cy="12" r="10" />
                      <path d="M12 6v6l3 3" />
                    </svg>
                  </span>
                  <p class="hero-card__title">Active tickets</p>
                  <p class="hero-card__value"><?= htmlspecialchars((string) ($employeeTicketInsights['open'] ?? 0)); ?></p>
                  <p class="hero-card__note">Requires updates today across priority queues.</p>
                </article>
                <article class="hero-card">
                  <span class="hero-card__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M9 11l3 3L22 4" />
                      <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
                    </svg>
                  </span>
                  <p class="hero-card__title">Approvals pending</p>
                  <p class="hero-card__value"><?= htmlspecialchars((string) count($pendingApprovals)); ?></p>
                  <p class="hero-card__note">Share context with admin for faster turnaround.</p>
                </article>
                <article class="hero-card">
                  <span class="hero-card__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M20 6H4" />
                      <path d="M20 12H8" />
                      <path d="M20 18H12" />
                    </svg>
                  </span>
                  <p class="hero-card__title">Team sentiment</p>
                  <p class="hero-card__value">4.7 / 5</p>
                  <p class="hero-card__note">Customer CSAT averaged over the last 30 days.</p>
                </article>
              </div>
            </div>
          </header>

    <?php foreach ($flashMessages['success'] as $message): ?>
      <div class="status-banner"><?= htmlspecialchars($message); ?></div>
    <?php endforeach; ?>

    <?php foreach ($flashMessages['error'] as $message): ?>
      <div class="status-banner" data-tone="error"><?= htmlspecialchars($message); ?></div>
    <?php endforeach; ?>

    <?php if ($currentView === 'overview'): ?>
      <?php
        $pipelineDataset = array_values(array_map(static function (array $entry): array {
          return [
            'label' => $entry['label'] ?? '',
            'count' => (int) ($entry['count'] ?? 0)
          ];
        }, $customerSegmentStats));
        $pipelineJson = htmlspecialchars(json_encode($pipelineDataset, JSON_UNESCAPED_UNICODE) ?: '[]');
        $sentimentSummary = [
          ['label' => 'At risk', 'count' => 1],
          ['label' => 'Recovering', 'count' => 1],
          ['label' => 'Monitor', 'count' => 1],
        ];
        $sentimentJson = htmlspecialchars(json_encode($sentimentSummary, JSON_UNESCAPED_UNICODE) ?: '[]');
        $paletteColours = ['#0ea5e9', '#38bdf8', '#94a3b8', '#22d3ee'];
      ?>
      <section class="panel" id="pipeline-overview">
        <div class="panel-header">
          <div>
            <span class="panel-header__meta">Overview</span>
            <h2>Customer pipeline overview</h2>
            <p>Monitor every stage of the customer lifecycle. Your updates are routed for admin approval before they publish.</p>
          </div>
        </div>
        <div class="metric-grid">
          <?php foreach ($customerSegmentStats as $index => $stat): ?>
            <article class="metric-card">
              <p class="metric-label"><?= htmlspecialchars($stat['label']); ?></p>
              <p class="metric-value"><?= htmlspecialchars((string) $stat['count']); ?></p>
              <p class="metric-helper">Records tracked</p>
            </article>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="panel" id="employee-analytics">
        <div class="panel-header">
          <div>
            <span class="panel-header__meta">Insight</span>
            <h2>Experience analytics</h2>
            <p>Visualise pipeline momentum and track sentiment across your assigned customers.</p>
          </div>
        </div>
        <div class="charts-grid">
          <article class="chart-card">
            <h3>Pipeline stages</h3>
            <canvas id="employee-pipeline-chart" data-pipeline='<?= $pipelineJson; ?>' aria-label="Pipeline chart"></canvas>
            <ul class="chart-legend">
              <?php $colorIndex = 0; ?>
              <?php foreach ($customerSegmentStats as $stat): ?>
                <?php $color = $paletteColours[$colorIndex % count($paletteColours)]; $colorIndex++; ?>
                <li><span style="background:<?= htmlspecialchars($color); ?>"></span><?= htmlspecialchars($stat['label']); ?></li>
              <?php endforeach; ?>
            </ul>
          </article>
          <article class="chart-card">
            <h3>Sentiment spotlight</h3>
            <canvas id="employee-sentiment-chart" data-sentiment='<?= $sentimentJson; ?>' aria-label="Sentiment chart"></canvas>
            <ul class="chart-legend">
              <li><span style="background:#ef4444"></span>At risk</li>
              <li><span style="background:#22c55e"></span>Recovering</li>
              <li><span style="background:#f59e0b"></span>Monitor</li>
            </ul>
          </article>
        </div>
      </section>

      <section class="panel" id="recent-complaints">
        <div class="panel-header">
          <div>
            <span class="panel-header__meta">Support</span>
            <h2>Recent service complaints</h2>
            <p>Open complaints awaiting action: <?= htmlspecialchars((string) $openComplaintsCount); ?>.</p>
          </div>
        </div>
        <?php if (empty($recentComplaints)): ?>
          <p class="empty">No complaints have been logged yet.</p>
        <?php else: ?>
          <div class="history-list">
            <?php foreach ($recentComplaints as $complaint): ?>
              <article class="approval-card">
                <div class="approval-header">
                  <p class="approval-title"><?= htmlspecialchars($complaint['subject']); ?></p>
                  <span class="status-pill"><?= htmlspecialchars($complaint['priority']); ?></span>
                </div>
                <p class="approval-meta">Logged by <?= htmlspecialchars($complaint['requesterName']); ?> • <?= htmlspecialchars($complaint['channel']); ?> • <?= htmlspecialchars($complaint['createdAtFormatted']); ?></p>
                <?php if (!empty($complaint['issueLabels'])): ?>
                  <p class="approval-details"><?= htmlspecialchars(implode(', ', $complaint['issueLabels'])); ?></p>
                <?php endif; ?>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if ($currentView === 'complaints'): ?>
      <?php $latestComplaintTimestamp = $employeeTicketInsights['latestUpdate'] ?? null; ?>
      <?php $latestComplaintFormatted = $latestComplaintTimestamp ? date('j M Y, g:i A', strtotime($latestComplaintTimestamp)) : '—'; ?>
      <section class="panel" id="complaint-metrics">
        <h2>Complaint workload</h2>
        <p class="lead">Track every service ticket captured from customers.</p>
        <div class="metric-grid">
          <div class="metric-card">
            <p class="metric-label">Total complaints</p>
            <p class="metric-value"><?= htmlspecialchars((string) ($employeeTicketInsights['total'] ?? 0)); ?></p>
            <p class="metric-helper">Recorded in the ticket log</p>
          </div>
          <div class="metric-card">
            <p class="metric-label">Open complaints</p>
            <p class="metric-value"><?= htmlspecialchars((string) ($employeeTicketInsights['open'] ?? 0)); ?></p>
            <p class="metric-helper">Follow up before closing</p>
          </div>
          <div class="metric-card">
            <p class="metric-label">High-priority</p>
            <p class="metric-value"><?= htmlspecialchars((string) ($employeeTicketInsights['highPriority'] ?? 0)); ?></p>
            <p class="metric-helper">Inverter / battery / net-metering</p>
          </div>
          <div class="metric-card">
            <p class="metric-label">Last update</p>
            <p class="metric-value"><?= htmlspecialchars($latestComplaintFormatted); ?></p>
            <p class="metric-helper">Latest customer touchpoint</p>
          </div>
        </div>
      </section>

      <section class="panel" id="complaint-summary">
        <h3>Ticket summary</h3>
        <?php if (empty($employeeTicketInsights['statusCounts']) && empty($employeeTicketInsights['channelCounts'])): ?>
          <p class="empty">No complaints have been logged yet.</p>
        <?php else: ?>
          <div class="details-grid">
            <?php foreach ($employeeTicketInsights['statusCounts'] as $statusKey => $count): ?>
              <span><strong>Status: <?= htmlspecialchars(ucfirst(str_replace('-', ' ', $statusKey))); ?></strong> <?= htmlspecialchars((string) $count); ?></span>
            <?php endforeach; ?>
            <?php foreach ($employeeTicketInsights['priorityCounts'] as $priorityKey => $count): ?>
              <span><strong>Priority: <?= htmlspecialchars(ucfirst($priorityKey)); ?></strong> <?= htmlspecialchars((string) $count); ?></span>
            <?php endforeach; ?>
            <?php foreach ($employeeTicketInsights['channelCounts'] as $channelKey => $count): ?>
              <span><strong>Channel: <?= htmlspecialchars(ucfirst($channelKey)); ?></strong> <?= htmlspecialchars((string) $count); ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <section class="panel" id="complaint-list">
        <h3>All complaints</h3>
        <p class="lead">Work through each ticket and leave updates for the admin team.</p>
        <?php if (empty($allEmployeeComplaintRows)): ?>
          <p class="empty">No complaints have been logged yet.</p>
        <?php else: ?>
          <div class="history-list">
            <?php foreach ($allEmployeeComplaintRows as $complaint): ?>
              <article class="approval-card">
                <div class="approval-header">
                  <p class="approval-title">#<?= htmlspecialchars($complaint['id'] ?: '—'); ?> · <?= htmlspecialchars($complaint['subject']); ?></p>
                  <span class="status-pill"><?= htmlspecialchars($complaint['priority']); ?></span>
                </div>
                <p class="approval-meta"><?= htmlspecialchars($complaint['requesterName']); ?> · <?= htmlspecialchars($complaint['channel']); ?> · <?= htmlspecialchars($complaint['createdAtFormatted']); ?></p>
                <div class="details-grid">
                  <?php if (!empty($complaint['requesterPhone'])): ?>
                    <span><strong>Phone</strong> +91 <?= htmlspecialchars($complaint['requesterPhone']); ?></span>
                  <?php endif; ?>
                  <span><strong>Status</strong><?= htmlspecialchars($complaint['status']); ?></span>
                </div>
                <?php if (!empty($complaint['issueLabels'])): ?>
                  <p class="approval-details">Issues: <?= htmlspecialchars(implode(', ', $complaint['issueLabels'])); ?></p>
                <?php endif; ?>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if ($currentView === 'customers'): ?>
      <?php if (empty($customerSegments)): ?>
        <section class="panel">
          <h2>Customer records</h2>
          <p class="lead">No customer segments configured yet. Check back soon.</p>
        </section>
      <?php else: ?>
        <?php foreach ($customerSegments as $segmentSlug => $segmentData): ?>
      <?php
        $segmentLabel = $segmentData['label'] ?? ucfirst(str_replace('-', ' ', $segmentSlug));
        $segmentDescription = $segmentData['description'] ?? '';
        $segmentColumns = $segmentData['columns'] ?? [];
        $segmentEntries = $segmentData['entries'] ?? [];
        $segmentAnchor = 'segment-' . $segmentSlug;
        $segmentCsvLink = '/api/index.php?route=public/customer-template&segment=' . urlencode((string) $segmentSlug) . '&format=csv';
      ?>
      <section class="panel" id="<?= htmlspecialchars($segmentAnchor); ?>">
        <h2><?= htmlspecialchars($segmentLabel); ?></h2>
        <p class="lead">
          <?= htmlspecialchars($segmentDescription); ?>
          <br />
          <small>Submit additions or edits for admin approval. Updates go live only after review.</small>
          <br />
          <small><a href="<?= htmlspecialchars($segmentCsvLink); ?>" target="_blank" rel="noopener">Download CSV template</a> for faster data entry.</small>
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
              $entryDisplayName = '';
              foreach ($segmentColumns as $column) {
                $columnKey = $column['key'] ?? null;
                if ($columnKey && isset($entryFields[$columnKey])) {
                  $candidate = trim((string) $entryFields[$columnKey]);
                  if ($candidate !== '') {
                    $entryDisplayName = $candidate;
                    break;
                  }
                }
              }
              if ($entryDisplayName === '') {
                $entryDisplayName = $segmentLabel . ' record';
              }
              $lastUpdated = $formatDateTime($entry['updated_at'] ?? $entry['created_at'] ?? null, 'Recently');
              $currentReminder = $entry['reminder_on'] ?? '—';
            ?>
            <details class="request-block">
              <summary>
                <span><?= htmlspecialchars($entryDisplayName); ?></span>
                <span class="status-pill">Prepare update</span>
              </summary>
              <p class="form-note">Current reminder: <?= htmlspecialchars($currentReminder === '' ? '—' : $currentReminder); ?> · Last updated <?= htmlspecialchars($lastUpdated); ?></p>
              <form method="post" class="request-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
                <input type="hidden" name="action" value="request_update_customer" />
                <input type="hidden" name="redirect_anchor" value="<?= htmlspecialchars($segmentAnchor); ?>" />
                <input type="hidden" name="redirect_view" value="<?= htmlspecialchars($currentView); ?>" />
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
          <input type="hidden" name="redirect_view" value="<?= htmlspecialchars($currentView); ?>" />
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
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($currentView === 'approvals'): ?>
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
          <input type="hidden" name="redirect_view" value="<?= htmlspecialchars($currentView); ?>" />
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
    <?php endif; ?>

    <?php if ($currentView === 'content'): ?>
      <section class="panel" id="content-hero">
        <h2>Homepage hero update</h2>
        <p class="lead">Review the current hero copy and propose improvements. All changes remain pending until an admin approves them.</p>
        <form method="post" class="request-form">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
          <input type="hidden" name="action" value="propose_hero_update" />
          <input type="hidden" name="redirect_view" value="<?= htmlspecialchars($currentView); ?>" />
          <input type="hidden" name="redirect_anchor" value="content-hero" />
          <div class="form-grid">
            <div class="form-group">
              <label for="hero-title-field">Hero title</label>
              <input id="hero-title-field" name="hero_title" type="text" value="<?= htmlspecialchars($homeHero['title'] ?? ''); ?>" required />
            </div>
            <div class="form-group">
              <label for="hero-subtitle-field">Hero subtitle</label>
              <textarea id="hero-subtitle-field" name="hero_subtitle" rows="3" required><?= htmlspecialchars($homeHero['subtitle'] ?? ''); ?></textarea>
            </div>
          </div>
          <div class="form-grid">
            <div class="form-group">
              <label for="hero-image-field">Hero image URL</label>
              <input id="hero-image-field" name="hero_image" type="text" value="<?= htmlspecialchars($homeHero['image'] ?? ''); ?>" />
              <p class="form-helper">Leave blank to keep the current hero image.</p>
            </div>
            <div class="form-group">
              <label for="hero-caption-field">Image caption</label>
              <input id="hero-caption-field" name="hero_image_caption" type="text" value="<?= htmlspecialchars($homeHero['image_caption'] ?? ''); ?>" />
            </div>
          </div>
          <div class="form-grid">
            <div class="form-group">
              <label for="hero-bubble-heading">Highlight badge</label>
              <input id="hero-bubble-heading" name="hero_bubble_heading" type="text" value="<?= htmlspecialchars($homeHero['bubble_heading'] ?? ''); ?>" />
            </div>
            <div class="form-group">
              <label for="hero-bubble-body">Highlight detail</label>
              <input id="hero-bubble-body" name="hero_bubble_body" type="text" value="<?= htmlspecialchars($homeHero['bubble_body'] ?? ''); ?>" />
            </div>
          </div>
          <div class="form-group">
            <label for="hero-bullets">Bullet points (one per line)</label>
            <textarea id="hero-bullets" name="hero_bullets" rows="3" placeholder="Hybrid ready systems&#10;Real-time monitoring updates"><?= htmlspecialchars($homeHeroBulletsValue); ?></textarea>
          </div>
          <div class="form-grid">
            <div class="form-group">
              <label for="hero-effective-date">Target go-live date</label>
              <input id="hero-effective-date" name="hero_effective_date" type="date" />
            </div>
            <div class="form-group">
              <label for="hero-justification">Why this update?</label>
              <textarea id="hero-justification" name="hero_justification" rows="3" placeholder="Explain the campaign, offer, or seasonal announcement" required></textarea>
            </div>
          </div>
          <div class="form-actions">
            <button class="primary-btn" type="submit">Send for admin approval</button>
          </div>
        </form>
      </section>

      <section class="panel" id="content-sections">
        <h2>Homepage sections</h2>
        <p class="lead">Browse the current sections and submit new copy or layouts. Admin approval is required before anything is published.</p>
        <?php if (empty($sortedHomeSections)): ?>
          <p>No homepage sections have been published yet. Propose the first one using the form below.</p>
        <?php else: ?>
          <div class="history-list">
            <?php foreach ($sortedHomeSections as $section): ?>
              <?php
                $sectionId = $section['id'] ?? '—';
                $sectionTitle = $section['title'] ?? 'Untitled section';
                $sectionStatusLabel = strtoupper($section['status'] ?? 'draft');
                $sectionUpdated = $formatDateTime($section['updated_at'] ?? $section['created_at'] ?? null, 'Recently');
              ?>
              <article class="approval-card">
                <div class="approval-header">
                  <p class="approval-title"><?= htmlspecialchars($sectionTitle); ?></p>
                  <span class="status-pill"><?= htmlspecialchars($sectionStatusLabel); ?></span>
                </div>
                <p class="approval-meta">ID <?= htmlspecialchars($sectionId); ?> • Updated <?= htmlspecialchars($sectionUpdated); ?></p>
                <?php if (!empty($section['subtitle'])): ?>
                  <p class="approval-details"><?= htmlspecialchars($section['subtitle']); ?></p>
                <?php endif; ?>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form method="post" class="request-form">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
          <input type="hidden" name="action" value="propose_section_update" />
          <input type="hidden" name="redirect_view" value="<?= htmlspecialchars($currentView); ?>" />
          <input type="hidden" name="redirect_anchor" value="content-sections" />
          <div class="form-grid">
            <div class="form-group">
              <label for="section-mode">Request type</label>
              <select id="section-mode" name="section_mode">
                <option value="create">Propose new section</option>
                <option value="update">Update existing section</option>
              </select>
            </div>
            <div class="form-group">
              <label for="section-target">Target section (for updates)</label>
              <select id="section-target" name="target_section">
                <option value="">Select section to update</option>
                <?php foreach ($sortedHomeSections as $section): ?>
                  <option value="<?= htmlspecialchars($section['id'] ?? ''); ?>"><?= htmlspecialchars(($section['title'] ?? 'Untitled') . ' · ' . strtoupper($section['status'] ?? 'draft')); ?></option>
                <?php endforeach; ?>
              </select>
              <p class="form-helper">Leave blank when proposing a brand-new section.</p>
            </div>
          </div>
          <div class="form-grid">
            <div class="form-group">
              <label for="section-eyebrow">Eyebrow / tag</label>
              <input id="section-eyebrow" name="section_eyebrow" type="text" placeholder="e.g. Financing spotlight" />
            </div>
            <div class="form-group">
              <label for="section-title">Section title</label>
              <input id="section-title" name="section_title" type="text" />
            </div>
          </div>
          <div class="form-group">
            <label for="section-subtitle">Section subtitle</label>
            <input id="section-subtitle" name="section_subtitle" type="text" />
          </div>
          <div class="form-group">
            <label for="section-body">Body content (paragraphs)</label>
            <textarea id="section-body" name="section_body" rows="4" placeholder="Write full paragraphs. Separate each paragraph with a blank line."></textarea>
          </div>
          <div class="form-group">
            <label for="section-bullets">Bullet points (optional)</label>
            <textarea id="section-bullets" name="section_bullets" rows="3" placeholder="Highlight 1&#10;Highlight 2"></textarea>
          </div>
          <div class="form-grid">
            <div class="form-group">
              <label for="section-cta-text">CTA text</label>
              <input id="section-cta-text" name="section_cta_text" type="text" placeholder="e.g. Schedule a consult" />
            </div>
            <div class="form-group">
              <label for="section-cta-url">CTA link</label>
              <input id="section-cta-url" name="section_cta_url" type="url" placeholder="https://..." />
            </div>
          </div>
          <div class="form-grid">
            <div class="form-group">
              <label for="section-display-order">Display order</label>
              <input id="section-display-order" name="section_display_order" type="number" value="0" />
            </div>
            <div class="form-group">
              <label for="section-status">Publish status</label>
              <select id="section-status" name="section_status">
                <option value="draft">Draft</option>
                <option value="published">Published</option>
              </select>
            </div>
            <div class="form-group">
              <label for="section-background-style">Background style</label>
              <select id="section-background-style" name="section_background_style">
                <?php foreach ($paletteKeys as $paletteKey): ?>
                  <?php if ($paletteKey === 'accent') { continue; } ?>
                  <option value="<?= htmlspecialchars($paletteKey); ?>"><?= htmlspecialchars(ucfirst(str_replace('-', ' ', $paletteKey))); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-grid">
            <div class="form-group">
              <label for="section-media-type">Media type</label>
              <select id="section-media-type" name="section_media_type">
                <option value="none">No media</option>
                <option value="image">Image</option>
                <option value="video">Video</option>
              </select>
            </div>
            <div class="form-group">
              <label for="section-media-src">Media source URL</label>
              <input id="section-media-src" name="section_media_src" type="text" placeholder="images/sections/offer.jpg" />
            </div>
            <div class="form-group">
              <label for="section-media-alt">Media alt text</label>
              <input id="section-media-alt" name="section_media_alt" type="text" />
            </div>
          </div>
          <div class="form-grid">
            <div class="form-group">
              <label for="section-effective-date">Target go-live date</label>
              <input id="section-effective-date" name="section_effective_date" type="date" />
            </div>
            <div class="form-group">
              <label for="section-justification">Context for the admin</label>
              <textarea id="section-justification" name="section_justification" rows="3" placeholder="Explain the change, launch plan, or supporting campaign" required></textarea>
            </div>
          </div>
          <div class="form-actions">
            <button class="primary-btn" type="submit">Submit section proposal</button>
          </div>
        </form>
      </section>
    <?php endif; ?>

    <?php if ($currentView === 'design'): ?>
      <section class="panel" id="design-change">
        <h2>Propose website design update</h2>
        <p class="lead">Recommend theme tweaks or seasonal announcements. Admin approval is required before changes go live.</p>
        <form method="post" class="request-form">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
          <input type="hidden" name="action" value="request_theme_change" />
          <input type="hidden" name="redirect_anchor" value="design-change" />
          <input type="hidden" name="redirect_view" value="<?= htmlspecialchars($currentView); ?>" />
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
    <?php endif; ?>

    <?php if ($currentView === 'profile'): ?>
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
          <input type="hidden" name="redirect_view" value="<?= htmlspecialchars($currentView); ?>" />
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
    <?php endif; ?>
        </div>
      </div>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      document.querySelectorAll('[data-refresh-button]').forEach(function (button) {
        button.addEventListener('click', function () {
          button.classList.add('is-refreshing');
          button.disabled = true;
          window.location.reload();
        });
      });

      if (!window.Chart) {
        return;
      }

      const pipelineCanvas = document.getElementById('employee-pipeline-chart');
      if (pipelineCanvas) {
        let points = [];
        try {
          points = JSON.parse(pipelineCanvas.dataset.pipeline || '[]');
        } catch (error) {
          points = [];
        }
        if (points.length) {
          const labels = points.map((entry) => entry.label || '');
          const values = points.map((entry) => entry.count || 0);
          const colors = ['#0ea5e9', '#38bdf8', '#94a3b8', '#22d3ee'];
          new Chart(pipelineCanvas, {
            type: 'bar',
            data: {
              labels,
              datasets: [{
                data: values,
                backgroundColor: labels.map((_, index) => colors[index % colors.length]),
                borderRadius: 12,
                maxBarThickness: 52
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: { legend: { display: false } },
              scales: {
                x: { ticks: { color: '#64748b' }, grid: { display: false } },
                y: { ticks: { color: '#64748b' }, grid: { color: 'rgba(148, 163, 184, 0.2)', drawBorder: false }, beginAtZero: true }
              }
            }
          });
        }
      }

      const sentimentCanvas = document.getElementById('employee-sentiment-chart');
      if (sentimentCanvas) {
        let sentiment = [];
        try {
          sentiment = JSON.parse(sentimentCanvas.dataset.sentiment || '[]');
        } catch (error) {
          sentiment = [];
        }
        if (sentiment.length) {
          new Chart(sentimentCanvas, {
            type: 'doughnut',
            data: {
              labels: sentiment.map((entry) => entry.label || ''),
              datasets: [{
                data: sentiment.map((entry) => entry.count || 0),
                backgroundColor: ['#ef4444', '#22c55e', '#f59e0b'],
                borderWidth: 0
              }]
            },
            options: {
              responsive: true,
              cutout: '62%',
              plugins: {
                legend: { display: false },
                tooltip: {
                  callbacks: {
                    label: function (context) {
                      const values = context.dataset.data;
                      const total = values.reduce((sum, value) => sum + value, 0) || 1;
                      const value = context.parsed;
                      const percentage = Math.round((value / total) * 100);
                      return context.label + ': ' + value + ' (' + percentage + '%)';
                    }
                  }
                }
              }
            }
          });
        }
      }
    });
  </script>
</body>
</html>
