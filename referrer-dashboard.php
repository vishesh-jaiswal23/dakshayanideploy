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

$lastDataRefreshLabel = '';
$lastDataRefreshTimestamp = portal_parse_datetime($state['last_updated'] ?? null);
if ($lastDataRefreshTimestamp === null) {
  $stateFileTimestamp = file_exists(PORTAL_DATA_FILE) ? filemtime(PORTAL_DATA_FILE) : false;
  if ($stateFileTimestamp !== false) {
    $lastDataRefreshTimestamp = $stateFileTimestamp;
  }
}

if ($lastDataRefreshTimestamp !== null) {
  $lastDataRefreshLabel = portal_format_datetime($lastDataRefreshTimestamp);
}
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

  <link rel="stylesheet" href="dashboard-modern.css" />
  <style></style>
</head>
<body data-role="referrer">
  <div class="dashboard-app">
    <header class="dashboard-topbar">
      <div class="topbar-brand">
        <span class="brand-mark" aria-hidden="true">D</span>
        <span class="brand-text">
          <span class="brand-name">Dakshayani</span>
          <span class="brand-tagline">Referral partner</span>
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
        <aside class="dashboard-sidebar" aria-label="Referral navigation">
          <div class="sidebar-header">
            <span class="sidebar-label">Navigation</span>
            <p class="sidebar-title">Growth pipeline</p>
          </div>
          <nav class="sidebar-nav">
            <a class="sidebar-link" href="#programme-overview" aria-current="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 5h18" />
                <path d="M3 12h18" />
                <path d="M3 19h18" />
              </svg>
              Programme snapshot
            </a>
            <a class="sidebar-link" href="#referrer-metrics">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M5 12.5l4-4 4 4 6-6" />
                <path d="M5 19h14" />
              </svg>
              Performance metrics
            </a>
            <a class="sidebar-link" href="#referrer-analytics">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="9" />
                <path d="M12 7v6l3 3" />
              </svg>
              Analytics &amp; charts
            </a>
            <a class="sidebar-link" href="#follow-ups">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="3" y="4" width="18" height="18" rx="4" />
                <path d="M16 2v4" />
                <path d="M8 2v4" />
                <path d="M3 10h18" />
              </svg>
              Follow-ups
            </a>
            <a class="sidebar-link" href="#referrer-tasks">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M4 6h9" />
                <path d="M4 12h9" />
                <path d="M4 18h9" />
                <path d="m16 6 2 2 3-3" />
              </svg>
              Action checklist
            </a>
            <a class="sidebar-link" href="#pipeline">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M4 4h16v6H4z" />
                <path d="M2 14h20" />
                <path d="M7 20h10" />
              </svg>
              Lead pipeline
            </a>
            <a class="sidebar-link" href="#rewards">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 2v7" />
                <circle cx="12" cy="14" r="8" />
                <path d="m9 14 2 2 4-4" />
              </svg>
              Rewards progress
            </a>
            <a class="sidebar-link" href="#toolkit">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M4 19.5V5a2 2 0 0 1 2-2h9" />
                <path d="M20 7v12.5a1.5 1.5 0 0 1-1.5 1.5H6" />
                <path d="M9 3v5h5" />
              </svg>
              Marketing toolkit
            </a>
            <a class="sidebar-link" href="#referrer-profile">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="7" r="4" />
                <path d="M5.5 21a7.5 7.5 0 0 1 13 0" />
              </svg>
              Account details
            </a>
          </nav>
          <p class="sidebar-footnote">
            Convert faster with live analytics, reminders, and ready-to-share marketing assets tailored for partners.
          </p>
        </aside>
        <div class="dashboard-main">
          <header class="page-header">
            <div class="page-header__content">
              <p class="eyebrow">Referral partner portal</p>
              <h1>Hello, <?= htmlspecialchars($displayName); ?></h1>
              <p class="subhead">
                Track your leads, payouts, and programme updates in one place.
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
                      <path d="M8 2v4" />
                      <path d="M16 2v4" />
                      <rect x="3" y="5" width="18" height="18" rx="2" />
                      <path d="M3 10h18" />
                    </svg>
                  </span>
                  <p class="hero-card__title">New leads this week</p>
                  <p class="hero-card__value">4</p>
                  <p class="hero-card__note">Two prospects already scheduled for site visits.</p>
                </article>
                <article class="hero-card">
                  <span class="hero-card__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M12 1v4" />
                      <path d="m4.93 4.93 3.54 3.54" />
                      <path d="M2 12h4" />
                      <path d="m4.93 19.07 3.54-3.54" />
                      <path d="M12 15v8" />
                      <circle cx="12" cy="12" r="3" />
                    </svg>
                  </span>
                  <p class="hero-card__title">Rewards in queue</p>
                  <p class="hero-card__value">₹36,500</p>
                  <p class="hero-card__note">Next payout hits your bank on 20 Oct.</p>
                </article>
                <article class="hero-card">
                  <span class="hero-card__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M13 2H6a2 2 0 0 0-2 2v16l5-3 5 3V4" />
                      <path d="M17 7h4" />
                      <path d="M19 5v4" />
                    </svg>
                  </span>
                  <p class="hero-card__title">Conversion streak</p>
                  <p class="hero-card__value">3 wins</p>
                  <p class="hero-card__note">Three consecutive deals closed in the last 10 days.</p>
                </article>
              </div>
            </div>
          </header>

          <div class="status-banner" data-dashboard-status hidden></div>

          <section class="panel" id="programme-overview">
            <div class="panel-header">
              <div>
                <span class="panel-header__meta">Overview</span>
                <h2>Programme snapshot</h2>
                <p>Overview of lead health and payouts for the current quarter.</p>
              </div>
            </div>
            <p class="panel-intro" data-dashboard-headline>Loading overview…</p>
          </section>

          <section class="panel" id="referrer-metrics">
            <div class="panel-header">
              <div>
                <span class="panel-header__meta">Live KPIs</span>
                <h2>Key metrics</h2>
                <p>Conversions, earnings, and performance targets in real time.</p>
              </div>
            </div>
            <div class="metric-grid" data-metrics></div>
          </section>

          <section class="panel" id="referrer-analytics">
            <div class="panel-header">
              <div>
                <span class="panel-header__meta">Insight</span>
                <h2>Performance analytics</h2>
                <p>See how your funnel is moving and how rewards are trending.</p>
              </div>
            </div>
            <div class="charts-grid">
              <article class="chart-card">
                <h3>Lead conversion journey</h3>
                <div class="chart-card__canvas">
                  <canvas id="referrer-funnel-chart" aria-label="Lead conversion chart"></canvas>
                </div>
                <ul class="chart-legend">
                  <li><span style="background:#0ea5e9"></span>Leads</li>
                  <li><span style="background:#38bdf8"></span>Qualified</li>
                  <li><span style="background:#94a3b8"></span>Proposal</li>
                  <li><span style="background:#22c55e"></span>Closed</li>
                </ul>
              </article>
              <article class="chart-card">
                <h3>Payout velocity</h3>
                <div class="chart-card__canvas">
                  <canvas id="referrer-payout-chart" aria-label="Payout timeline chart"></canvas>
                </div>
                <ul class="chart-legend">
                  <li><span style="background:#0ea5e9"></span>Reward earnings</li>
                </ul>
              </article>
            </div>
          </section>

          <section class="panel" id="follow-ups">
            <div class="panel-header">
              <div>
                <span class="panel-header__meta">Schedule</span>
                <h2>Upcoming follow-ups</h2>
                <p>Prioritise conversations and make sure every lead hears from you.</p>
              </div>
            </div>
            <div class="timeline-list" data-timeline></div>
          </section>

          <section class="panel" id="referrer-tasks">
            <div class="panel-header">
              <div>
                <span class="panel-header__meta">Momentum</span>
                <h2>Action checklist</h2>
                <p>Complete these items to unlock faster conversions and payouts.</p>
              </div>
            </div>
            <div class="task-list" data-tasks></div>
          </section>

          <section class="panel" id="pipeline">
            <div class="panel-header">
              <div>
                <span class="panel-header__meta">Pipeline</span>
                <h2>Lead pipeline</h2>
                <p>Snapshot of your hottest prospects and the next promised action.</p>
              </div>
            </div>
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

          <section class="panel" id="rewards">
            <div class="panel-header">
              <div>
                <span class="panel-header__meta">Rewards</span>
                <h2>Rewards progress</h2>
                <p>Hit your quarter target to unlock the bonus payout.</p>
              </div>
            </div>
            <article class="reward-card">
              <p class="lead-name">This quarter's bonus target</p>
              <p class="lead-meta">Close 6 qualified deals to unlock the additional ₹10,000 payout.</p>
              <div class="progress-track" aria-hidden="true">
                <div class="progress-fill" style="width: 66%"></div>
              </div>
              <p class="lead-meta">You have closed <strong>4 of 6</strong> required conversions.</p>
            </article>
          </section>

          <section class="panel" id="toolkit">
            <div class="panel-header">
              <div>
                <span class="panel-header__meta">Resources</span>
                <h2>Marketing toolkit</h2>
                <p>Share resources instantly with your prospects.</p>
              </div>
            </div>
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

          <section class="panel" id="referrer-profile">
            <div class="panel-header">
              <div>
                <span class="panel-header__meta">Profile</span>
                <h2>Your contact details</h2>
                <p>Update information so the Dakshayani team can reach you quickly.</p>
              </div>
            </div>
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
        </div>
      </div>
    </main>
  </div>

  <script src="portal-demo-data.js"></script>
  <script src="dashboard-auth.js"></script>
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

      const funnelCtx = document.getElementById('referrer-funnel-chart');
      if (funnelCtx) {
        new Chart(funnelCtx, {
          type: 'bar',
          data: {
            labels: ['Leads', 'Qualified', 'Proposal', 'Closed'],
            datasets: [
              {
                data: [14, 9, 6, 4],
                backgroundColor: ['#0ea5e9', '#38bdf8', '#94a3b8', '#22c55e'],
                borderRadius: 12,
                maxBarThickness: 52
              }
            ]
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

      const payoutCtx = document.getElementById('referrer-payout-chart');
      if (payoutCtx) {
        new Chart(payoutCtx, {
          type: 'line',
          data: {
            labels: ['Jun', 'Jul', 'Aug', 'Sep', 'Oct'],
            datasets: [
              {
                label: 'Payouts',
                data: [18000, 22000, 26500, 32000, 36500],
                borderColor: '#0ea5e9',
                backgroundColor: 'rgba(14, 165, 233, 0.18)',
                tension: 0.4,
                fill: true,
                pointRadius: 3,
                pointBackgroundColor: '#0ea5e9'
              }
            ]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
              x: { ticks: { color: '#64748b' }, grid: { display: false } },
              y: { ticks: { color: '#64748b' }, grid: { color: 'rgba(148, 163, 184, 0.2)', drawBorder: false } }
            }
          }
        });
      }
    });
  </script>
</body>
</html>
