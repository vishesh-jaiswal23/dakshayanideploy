<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'referrer') {
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

$displayName = $_SESSION['display_name'] ?? ($userRecord['name'] ?? 'Partner');
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Referral Partner Dashboard | Dakshayani Enterprises</title>
  <meta name="description" content="Referral partner dashboard showing leads, payouts, and follow-up tasks." />
  <link rel="icon" href="images/favicon.ico" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <style>
    :root {
      --primary: #f97316;
      --muted: rgba(15, 23, 42, 0.6);
      --border: rgba(15, 23, 42, 0.08);
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: radial-gradient(circle at 100% 0%, rgba(249, 115, 22, 0.15), transparent 55%), #0f172a;
      min-height: 100vh;
      padding: 2.5rem 1.5rem;
      display: flex;
      justify-content: center;
      color: #0f172a;
    }

    .dashboard-shell {
      width: min(1080px, 100%);
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
      letter-spacing: 0.14em;
      font-size: 0.75rem;
      color: var(--primary);
      margin: 0 0 0.45rem;
    }

    h1 {
      margin: 0;
      font-size: clamp(1.7rem, 3vw, 2.3rem);
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
      padding: 0.65rem 1.35rem;
      border-radius: 999px;
      font-weight: 600;
      cursor: pointer;
      box-shadow: 0 20px 35px -25px rgba(249, 115, 22, 0.6);
    }

    .status-banner {
      border-radius: 1.25rem;
      padding: 1rem 1.2rem;
      background: rgba(249, 115, 22, 0.12);
      border: 1px solid rgba(249, 115, 22, 0.2);
      color: #c2410c;
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

    .metric-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
      gap: 1rem;
    }

    .metric-card {
      background: #ffffff;
      border-radius: 1.2rem;
      padding: 1rem 1.1rem;
      border: 1px solid rgba(249, 115, 22, 0.2);
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

    .timeline-list, .task-list, .pipeline-list, .resource-grid {
      display: grid;
      gap: 0.75rem;
    }

    .timeline-row, .task-row, .pipeline-row, .resource-card, .reward-card {
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

    .timeline-date, .timeline-status, .task-status, .lead-meta {
      margin: 0;
      font-size: 0.85rem;
      color: var(--muted);
    }

    .pipeline-row {
      gap: 0.5rem 1rem;
      flex-wrap: wrap;
      align-items: center;
    }

    .lead-name {
      font-weight: 600;
      color: #0f172a;
      margin: 0;
    }

    .lead-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      border-radius: 999px;
      padding: 0.3rem 0.75rem;
      font-size: 0.78rem;
      font-weight: 600;
      background: rgba(249, 115, 22, 0.16);
      color: #c2410c;
    }

    .reward-card {
      flex-direction: column;
      align-items: flex-start;
      gap: 0.6rem;
      min-height: 160px;
    }

    .progress-track {
      width: 100%;
      height: 10px;
      border-radius: 999px;
      background: rgba(15, 23, 42, 0.08);
      overflow: hidden;
    }

    .progress-fill {
      height: 100%;
      border-radius: inherit;
      background: linear-gradient(120deg, #f97316, #fb923c);
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

    @media (max-width: 720px) {
      body { padding: 1.5rem; }
      .dashboard-shell { border-radius: 1.5rem; }
    }
  </style>
</head>
<body data-role="referrer">
  <main class="dashboard-shell">
    <header>
      <div>
        <p class="eyebrow">Referral partner portal</p>
        <h1>Hello, <?= htmlspecialchars($displayName); ?></h1>
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

    <div class="status-banner" data-dashboard-status hidden></div>

    <section class="panel">
      <h2>Programme snapshot</h2>
      <p class="lead" data-dashboard-headline>Loading overview…</p>
    </section>

    <section class="panel">
      <h2>Key metrics</h2>
      <div class="metric-grid" data-metrics></div>
    </section>

    <section class="panel">
      <h2>Upcoming follow-ups</h2>
      <div class="timeline-list" data-timeline></div>
    </section>

    <section class="panel">
      <h2>Action checklist</h2>
      <div class="task-list" data-tasks></div>
    </section>

    <section class="panel">
      <h2>Lead pipeline</h2>
      <div class="pipeline-list">
        <article class="pipeline-row">
          <p class="lead-name">RF-882 · Manoj Sinha</p>
          <p class="lead-meta">3 kW rooftop · Ranchi</p>
          <span class="lead-badge">Follow-up today</span>
        </article>
        <article class="pipeline-row">
          <p class="lead-name">RF-876 · Kavya Industries</p>
          <p class="lead-meta">25 kW factory · Bokaro</p>
          <span class="lead-badge">Site visit pending</span>
        </article>
        <article class="pipeline-row">
          <p class="lead-name">RF-901 · Sneha Residency</p>
          <p class="lead-meta">8 kW apartment · Jamshedpur</p>
          <span class="lead-badge">Proposal shared</span>
        </article>
      </div>
    </section>

    <section class="panel">
      <h2>Rewards progress</h2>
      <article class="reward-card">
        <p class="lead-name">This quarter's bonus target</p>
        <p class="lead-meta">Close 6 qualified deals to unlock the additional ₹10,000 payout.</p>
        <div class="progress-track" aria-hidden="true">
          <div class="progress-fill" style="width: 66%"></div>
        </div>
        <p class="lead-meta">You have closed <strong>4 of 6</strong> required conversions.</p>
      </article>
    </section>

    <section class="panel">
      <h2>Marketing toolkit</h2>
      <div class="resource-grid">
        <article class="resource-card">
          <h3>Referral pitch deck</h3>
          <p class="lead-meta">Updated slides with October pricing and financing calculators.</p>
          <div class="resource-actions">
            <a href="#">Download PPT</a>
            <a href="#">Share link</a>
          </div>
        </article>
        <article class="resource-card">
          <h3>Social media pack</h3>
          <p class="lead-meta">Canva templates for Instagram, WhatsApp, and LinkedIn outreach.</p>
          <div class="resource-actions">
            <a href="#">Open Canva</a>
            <a href="#">Request custom post</a>
          </div>
        </article>
        <article class="resource-card">
          <h3>Payment status</h3>
          <p class="lead-meta">Next reward payout: 20 Oct · Bank account ending 5526.</p>
          <div class="resource-actions">
            <a href="#">View ledger</a>
            <a href="#">Update bank</a>
          </div>
        </article>
      </div>
    </section>

    <section class="panel">
      <h2>Your contact details</h2>
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

  <script src="portal-demo-data.js"></script>
  <script src="dashboard-auth.js"></script>
</body>
</html>
