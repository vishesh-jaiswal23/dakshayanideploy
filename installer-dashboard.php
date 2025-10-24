<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'installer') {
  header('Location: login.php');
  exit;
}

require_once __DIR__ . '/portal-state.php';

$state = portal_load_state();
$currentUserId = $_SESSION['user_id'] ?? '';
$userRecord = null;

foreach ($state['users'] as $user) {
  if (($user['id'] ?? '') === $currentUserId) {
    $userRecord = $user;
    break;
  }
}

$displayName = $_SESSION['display_name'] ?? ($userRecord['name'] ?? 'Installer');
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Installer Dashboard | Dakshayani Enterprises</title>
  <meta name="description" content="Installer dashboard for Dakshayani Enterprises field teams. View jobs, schedule, and tasks." />
  <link rel="icon" href="images/favicon.ico" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <style>
    :root {
      color-scheme: light;
      --page-bg: #eef2f9;
      --topbar-bg: linear-gradient(135deg, #0b1f3a, #123262);
      --topbar-text: #f8fafc;
      --surface: #ffffff;
      --surface-subtle: #f7f9fd;
      --border: rgba(15, 23, 42, 0.08);
      --border-strong: rgba(14, 165, 233, 0.2);
      --primary: #0ea5e9;
      --primary-soft: rgba(14, 165, 233, 0.14);
      --primary-strong: #0284c7;
      --muted: rgba(15, 23, 42, 0.6);
      --success: #16a34a;
      --warning: #f59e0b;
      --danger: #dc2626;
      --shadow-card: 0 38px 70px -48px rgba(15, 23, 42, 0.55);
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: var(--page-bg);
      color: #0f172a;
    }

    .dashboard-app {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      background: linear-gradient(180deg, rgba(15, 23, 42, 0.06), transparent 30%) no-repeat;
    }

    .dashboard-topbar {
      background: var(--topbar-bg);
      color: var(--topbar-text);
      padding: 1.2rem clamp(1.5rem, 5vw, 2.75rem);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1.5rem;
      flex-wrap: wrap;
      box-shadow: 0 24px 48px -32px rgba(11, 31, 58, 0.65);
      position: relative;
      z-index: 2;
    }

    .topbar-brand {
      display: flex;
      align-items: center;
      gap: 0.85rem;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
    }

    .brand-mark {
      width: 44px;
      height: 44px;
      border-radius: 14px;
      background: rgba(255, 255, 255, 0.18);
      display: grid;
      place-items: center;
      font-size: 1.25rem;
    }

    .brand-text {
      display: flex;
      flex-direction: column;
      line-height: 1.1;
    }

    .brand-name {
      font-size: 1rem;
      letter-spacing: 0.05em;
    }

    .brand-tagline {
      font-size: 0.75rem;
      font-weight: 500;
      text-transform: none;
      opacity: 0.75;
    }

    .topbar-actions {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 1rem;
      flex-wrap: wrap;
    }

    .role-chip {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      padding: 0.35rem 0.85rem;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.16);
      font-size: 0.75rem;
      font-weight: 600;
      letter-spacing: 0.08em;
    }

    .user-pill {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.45rem 0.85rem 0.45rem 0.45rem;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.1);
    }

    .user-avatar {
      width: 40px;
      height: 40px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.18);
      display: grid;
      place-items: center;
      font-weight: 600;
      font-size: 1rem;
      letter-spacing: 0.02em;
    }

    .user-meta {
      display: flex;
      flex-direction: column;
      line-height: 1.2;
    }

    .user-name {
      font-weight: 600;
      font-size: 0.9rem;
      color: var(--topbar-text);
    }

    .user-email {
      font-size: 0.75rem;
      opacity: 0.75;
      color: var(--topbar-text);
    }

    .topbar-actions form {
      margin: 0;
    }

    .topbar-logout {
      border: none;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.18);
      color: var(--topbar-text);
      padding: 0.55rem 1.25rem;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.2s ease, transform 0.2s ease;
    }

    .topbar-logout:hover,
    .topbar-logout:focus {
      background: rgba(255, 255, 255, 0.28);
      transform: translateY(-1px);
    }

    .dashboard-shell {
      width: min(1100px, calc(100% - 3rem));
      background: var(--surface);
      border-radius: 24px;
      padding: clamp(1.85rem, 4vw, 3rem);
      box-shadow: var(--shadow-card);
      display: grid;
      gap: clamp(1.4rem, 3vw, 2.4rem);
      margin: clamp(-3.5rem, -6vw, -2.75rem) auto 3rem;
      position: relative;
      z-index: 1;
    }

    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      flex-wrap: wrap;
      gap: 1.25rem;
    }

    .eyebrow {
      text-transform: uppercase;
      font-weight: 600;
      letter-spacing: 0.18em;
      font-size: 0.75rem;
      color: var(--primary-strong);
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

    .status-banner {
      border-radius: 1.25rem;
      padding: 1rem 1.2rem;
      background: var(--primary-soft);
      border: 1px solid rgba(14, 165, 233, 0.24);
      color: var(--primary-strong);
      font-size: 0.95rem;
    }

    .status-banner[data-tone="error"] {
      background: rgba(220, 38, 38, 0.08);
      color: #b91c1c;
      border-color: rgba(220, 38, 38, 0.22);
    }

    .status-banner[data-tone="success"] {
      background: rgba(22, 163, 74, 0.12);
      border-color: rgba(22, 163, 74, 0.22);
      color: #15803d;
    }

    .panel {
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: clamp(1.4rem, 2.6vw, 2rem);
      background: var(--surface-subtle);
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

    .metric-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 1rem;
    }

    .metric-card {
      background: #ffffff;
      border-radius: 18px;
      padding: 1rem 1.1rem;
      border: 1px solid rgba(14, 165, 233, 0.22);
      box-shadow: 0 16px 28px -24px rgba(14, 165, 233, 0.35);
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

    .timeline-list, .task-list, .job-grid, .resource-grid, .checklist {
      display: grid;
      gap: 0.75rem;
    }

    .timeline-row, .task-row, .job-card, .resource-card, .checklist-item {
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

    .timeline-date, .timeline-status, .task-status, .job-meta {
      margin: 0;
      font-size: 0.85rem;
      color: var(--muted);
    }

    .job-grid {
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }

    .job-card {
      flex-direction: column;
      align-items: flex-start;
      gap: 0.5rem;
      min-height: 150px;
    }

    .job-card h3 {
      margin: 0;
      font-size: 1.1rem;
      font-weight: 600;
      color: #0f172a;
    }

    .job-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      border-radius: 999px;
      padding: 0.35rem 0.75rem;
      font-size: 0.8rem;
      font-weight: 600;
      background: rgba(14, 165, 233, 0.16);
      color: #0ea5e9;
    }

    .checklist {
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }

    .checklist-item {
      flex-direction: column;
      align-items: flex-start;
      gap: 0.5rem;
    }

    .checklist-item[data-state="done"] {
      border-color: rgba(34, 197, 94, 0.28);
    }

    .checklist-item[data-state="action"] {
      border-color: rgba(249, 115, 22, 0.35);
      box-shadow: 0 14px 24px -22px rgba(249, 115, 22, 0.55);
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

    @media (max-width: 900px) {
      .dashboard-shell {
        width: calc(100% - 2rem);
        margin: -2.75rem auto 2.5rem;
      }
    }

    @media (max-width: 720px) {
      .dashboard-topbar {
        flex-direction: column;
        align-items: flex-start;
      }

      .topbar-actions {
        width: 100%;
        justify-content: space-between;
      }

      .dashboard-shell {
        border-radius: 20px;
        margin: -2.25rem auto 1.5rem;
        padding: 1.5rem;
      }
    }
  </style>
</head>
<body data-role="installer">
  <div class="dashboard-app">
    <header class="dashboard-topbar">
      <div class="topbar-brand">
        <span class="brand-mark" aria-hidden="true">D</span>
        <span class="brand-text">
          <span class="brand-name">Dakshayani</span>
          <span class="brand-tagline">Installer portal</span>
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
      <header class="page-header">
        <div>
          <p class="eyebrow">Installer portal</p>
          <h1>Hey, <?= htmlspecialchars($displayName); ?></h1>
          <p class="subhead">
            Review your site schedule and action items for today.
            <?php if ($lastLogin): ?>
              Last sign-in <?= htmlspecialchars($lastLogin); ?>.
            <?php endif; ?>
          </p>
        </div>
      </header>

      <div class="status-banner" data-dashboard-status hidden></div>

    <section class="panel">
      <h2>Field overview</h2>
      <p class="lead" data-dashboard-headline>Loading overview…</p>
    </section>

    <section class="panel">
      <h2>Key metrics</h2>
      <div class="metric-grid" data-metrics></div>
    </section>

    <section class="panel">
      <h2>Upcoming jobs</h2>
      <div class="timeline-list" data-timeline></div>
    </section>

    <section class="panel">
      <h2>Today's tasks</h2>
      <div class="task-list" data-tasks></div>
    </section>

    <section class="panel">
      <h2>Today's crew plan</h2>
      <div class="job-grid">
        <article class="job-card">
          <h3>Team A · Ranchi</h3>
          <p class="job-meta">Lead: <strong>Sunita Singh</strong></p>
          <p class="job-meta">Crew: 4 technicians · Crane slot 09:00–12:00</p>
          <span class="job-badge">Install &amp; wiring</span>
        </article>
        <article class="job-card">
          <h3>Team B · Bokaro</h3>
          <p class="job-meta">Lead: <strong>Akash Mehra</strong></p>
          <p class="job-meta">Crew: 3 technicians · Safety audit at 14:00</p>
          <span class="job-badge">Commissioning</span>
        </article>
        <article class="job-card">
          <h3>Team C · Jamshedpur</h3>
          <p class="job-meta">Lead: <strong>Priya Das</strong></p>
          <p class="job-meta">Crew: 5 technicians · Roof access cleared</p>
          <span class="job-badge">Pre-install survey</span>
        </article>
      </div>
    </section>

    <section class="panel">
      <h2>Site readiness checklist</h2>
      <div class="checklist">
        <article class="checklist-item" data-state="done">
          <h3>Safety briefing</h3>
          <p class="job-meta">Use the latest PPE inspection log shared yesterday.</p>
        </article>
        <article class="checklist-item" data-state="action">
          <h3>Material receipts</h3>
          <p class="job-meta">Awaiting POD upload for inverter batch INV-44521.</p>
        </article>
        <article class="checklist-item">
          <h3>Quality photos</h3>
          <p class="job-meta">Capture DC isolator close-ups after wiring run completion.</p>
        </article>
        <article class="checklist-item" data-state="done">
          <h3>Permit display</h3>
          <p class="job-meta">Work permits printed and carried by all supervisors.</p>
        </article>
      </div>
    </section>

    <section class="panel">
      <h2>Resources &amp; quick links</h2>
      <div class="resource-grid">
        <article class="resource-card">
          <h3>Installation handbook</h3>
          <p class="job-meta">Latest SOP for Dakshayani rooftop systems including torque specs.</p>
          <div class="resource-actions">
            <a href="#">Download PDF</a>
            <a href="#">View change log</a>
          </div>
        </article>
        <article class="resource-card">
          <h3>Inventory tracker</h3>
          <p class="job-meta">Live sheet of module, inverter, and BOS stock across warehouses.</p>
          <div class="resource-actions">
            <a href="#">Open tracker</a>
            <a href="#">Report shortage</a>
          </div>
        </article>
        <article class="resource-card">
          <h3>Tool maintenance</h3>
          <p class="job-meta">Submit service requests for crimpers, testers, and safety gear.</p>
          <div class="resource-actions">
            <a href="#">Book service</a>
            <a href="#">Checklist</a>
          </div>
        </article>
      </div>
    </section>

      <section class="panel">
        <h2>Your crew details</h2>
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
          <strong>Phone</strong>
          <span><?= htmlspecialchars($userPhone === '' ? '—' : $userPhone); ?></span>
        </div>
        <div>
          <strong>Email</strong>
          <span><?= htmlspecialchars($userEmail); ?></span>
        </div>
        </div>
      </section>
    </main>
  </div>

  <script src="portal-demo-data.js"></script>
  <script src="dashboard-auth.js"></script>
</body>
</html>
