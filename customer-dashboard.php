<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'customer') {
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

$displayName = $_SESSION['display_name'] ?? ($userRecord['name'] ?? 'Customer');
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
  <title>Customer Dashboard | Dakshayani Enterprises</title>
  <meta name="description" content="Customer dashboard showing your solar project status, appointments, and action items." />
  <link rel="icon" href="images/favicon.ico" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />

  <link rel="stylesheet" href="dashboard-modern.css" />
  <style>
    .customer-energy-note {
      display: block;
      margin-top: 0.25rem;
      font-size: 0.85rem;
      color: var(--muted);
    }
  </style>
</head>
<body data-role="customer">
  <div class="dashboard-app">
    <header class="dashboard-topbar">
      <div class="topbar-brand">
        <span class="brand-mark" aria-hidden="true">D</span>
        <span class="brand-text">
          <span class="brand-name">Dakshayani</span>
          <span class="brand-tagline">Customer portal</span>
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
        <aside class="dashboard-sidebar" aria-label="Customer navigation">
          <div class="sidebar-header">
            <span class="sidebar-label">Navigation</span>
            <p class="sidebar-title">Customer journey</p>
          </div>
          <nav class="sidebar-nav">
            <a class="sidebar-link" href="#project-overview" aria-current="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M4 5h16M4 12h16M4 19h16" />
              </svg>
              Project overview
            </a>
            <a class="sidebar-link" href="#key-metrics">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M5 12.5l4-4 4 4 6-6" />
                <path d="M5 19h14" />
              </svg>
              Performance metrics
            </a>
            <a class="sidebar-link" href="#analytics">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 18h18" />
                <path d="M7 18V9" />
                <path d="M12 18V5" />
                <path d="M17 18v-7" />
              </svg>
              Analytics &amp; charts
            </a>
            <a class="sidebar-link" href="#appointments">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="3" y="4" width="18" height="18" rx="4" />
                <path d="M16 2v4" />
                <path d="M8 2v4" />
                <path d="M3 10h18" />
              </svg>
              Appointments
            </a>
            <a class="sidebar-link" href="#tasks">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M4 6h7" />
                <path d="M4 12h7" />
                <path d="M4 18h7" />
                <path d="M15 6l2 2 4-4" />
              </svg>
              Action items
            </a>
            <a class="sidebar-link" href="#tracker">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 6v6l4 2" />
                <circle cx="12" cy="12" r="9" />
              </svg>
              Installation tracker
            </a>
            <a class="sidebar-link" href="#resources">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M4 19.5V5a2 2 0 0 1 2-2h9" />
                <path d="M20 7v12.5a1.5 1.5 0 0 1-1.5 1.5H6" />
                <path d="M9 3v5h5" />
              </svg>
              Resources
            </a>
            <a class="sidebar-link" href="#account">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="7" r="4" />
                <path d="M5.5 21a7.5 7.5 0 0 1 13 0" />
              </svg>
              Account details
            </a>
          </nav>
          <p class="sidebar-footnote">
            Track every milestone, monitor production trends, and contact your Dakshayani crew in one place.
          </p>
        </aside>
        <div class="dashboard-main">
          <header class="page-header">
            <div class="page-header__content">
              <p class="eyebrow">Customer portal</p>
              <h1>Welcome back, <?= htmlspecialchars($displayName); ?></h1>
              <p class="subhead">
                Keep an eye on your rooftop project and next steps.
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
                      <path d="M12 2v5" />
                      <path d="m4.93 4.93 3.54 3.54" />
                      <path d="M2 12h5" />
                      <path d="m4.93 19.07 3.54-3.54" />
                      <path d="M12 17v5" />
                      <path d="m19.07 19.07-3.54-3.54" />
                      <path d="M17 12h5" />
                      <path d="m19.07 4.93-3.54 3.54" />
                      <circle cx="12" cy="12" r="3" />
                    </svg>
                  </span>
                  <p class="hero-card__title">System status</p>
                  <p class="hero-card__value">Net metering pending</p>
                  <p class="hero-card__note">Inspection is booked — once cleared we will switch on monitoring for you.</p>
                </article>
                <article class="hero-card">
                  <span class="hero-card__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5Z" />
                      <path d="M20 21a8 8 0 0 0-16 0" />
                    </svg>
                  </span>
                  <p class="hero-card__title">Your crew lead</p>
                  <p class="hero-card__value">Rohit Kumar</p>
                  <p class="hero-card__note">Project manager · +91 88000 00000 · care@dakshayani.co.in</p>
                </article>
                <article class="hero-card">
                  <span class="hero-card__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M12 8c-2.21 0-4 1.79-4 4v6h8v-6c0-2.21-1.79-4-4-4Z" />
                      <path d="M8 12h8" />
                      <path d="M9 21h6" />
                      <path d="M12 3v2" />
                    </svg>
                  </span>
                  <p class="hero-card__title">Savings achieved</p>
                  <p class="hero-card__value">₹ 58,400</p>
                  <p class="hero-card__note">Since commissioning your solar journey with Dakshayani.</p>
                </article>
              </div>
            </div>
          </header>

          <div class="status-banner" data-dashboard-status hidden></div>

          <section class="panel" id="project-overview">
            <div class="panel-header">
              <div>
                <span class="panel-header__meta">Overview</span>
                <h2>Your project snapshot</h2>
                <p>Live summary of where your rooftop installation stands today.</p>
              </div>
            </div>
            <p class="panel-intro" data-dashboard-headline>Loading overview…</p>
          </section>

          <section class="panel" id="key-metrics">
            <div class="panel-header">
              <div>
                <span class="panel-header__meta">Live KPIs</span>
                <h2>Key metrics</h2>
                <p>The latest production and savings indicators for your home system.</p>
              </div>
            </div>
            <div class="metric-grid" data-metrics></div>
          </section>

          <section class="panel" id="analytics">
            <div class="panel-header">
              <div>
                <span class="panel-header__meta">Insight</span>
                <h2>Performance analytics</h2>
                <p>Understand generation trends and monitor your installation milestones.</p>
              </div>
            </div>
            <div class="charts-grid">
              <article class="chart-card">
                <h3>Generation (last 6 months)</h3>
                <div class="chart-card__canvas">
                  <canvas id="customer-generation-chart" aria-label="Monthly energy generation chart"></canvas>
                </div>
                <ul class="chart-legend">
                  <li><span style="background:#0ea5e9"></span>Actual generation</li>
                  <li><span style="background:#94a3b8"></span>Projected</li>
                </ul>
                <span class="customer-energy-note">Goal for this quarter: 5,600 kWh saved from the grid.</span>
              </article>
              <article class="chart-card">
                <h3>Installation progress</h3>
                <div class="chart-card__canvas">
                  <canvas id="customer-progress-chart" aria-label="Installation progress chart"></canvas>
                </div>
                <ul class="chart-legend">
                  <li><span style="background:#0ea5e9"></span>Completed</li>
                  <li><span style="background:#dbeafe"></span>Remaining</li>
                </ul>
              </article>
            </div>
          </section>

          <section class="panel" id="appointments">
            <div class="panel-header">
              <div>
                <span class="panel-header__meta">Schedule</span>
                <h2>Upcoming appointments</h2>
                <p>We will keep you posted about every site visit and review.</p>
              </div>
            </div>
            <div class="timeline-list" data-timeline></div>
          </section>

          <section class="panel" id="tasks">
            <div class="panel-header">
              <div>
                <span class="panel-header__meta">Next steps</span>
                <h2>Things to complete</h2>
                <p>Finish these items to keep your project on schedule.</p>
              </div>
            </div>
            <div class="task-list" data-tasks></div>
          </section>

          <section class="panel" id="tracker">
            <div class="panel-header">
              <div>
                <span class="panel-header__meta">Milestones</span>
                <h2>Installation tracker</h2>
                <p>See what’s done and what’s next on the path to commissioning.</p>
              </div>
            </div>
            <div class="step-list">
              <article class="step-card" data-state="done">
                <div class="step-header">
                  <span class="step-index">1</span>
                  <p class="step-title">Design package approved</p>
                </div>
                <p class="step-helper">Final schematics and layout shared on 28 Sep 2024.</p>
              </article>
              <article class="step-card" data-state="done">
                <div class="step-header">
                  <span class="step-index">2</span>
                  <p class="step-title">Equipment dispatched</p>
                </div>
                <p class="step-helper">Modules and inverters reached the site warehouse on 05 Oct 2024.</p>
              </article>
              <article class="step-card" data-state="action">
                <div class="step-header">
                  <span class="step-index">3</span>
                  <p class="step-title">Net metering documents</p>
                </div>
                <p class="step-helper">Upload the signed application so we can submit it to the DISCOM.</p>
              </article>
              <article class="step-card" data-state="pending">
                <div class="step-header">
                  <span class="step-index">4</span>
                  <p class="step-title">Commissioning walkthrough</p>
                </div>
                <p class="step-helper">Installer will confirm your preferred slot after the electrical inspection.</p>
              </article>
            </div>
          </section>

          <section class="panel" id="resources">
            <div class="panel-header">
              <div>
                <span class="panel-header__meta">Support</span>
                <h2>Documents &amp; support</h2>
                <p>Quick links to paperwork, support channels, and learning resources.</p>
              </div>
            </div>
            <div class="resource-grid">
              <article class="resource-card">
                <h3 class="resource-title">Project paperwork</h3>
                <p class="resource-meta"><strong>Latest uploads:</strong> Survey report, structural assessment, financing agreement.</p>
                <div class="resource-actions">
                  <a href="#">Download ZIP</a>
                  <a href="#">Share feedback</a>
                </div>
              </article>
              <article class="resource-card">
                <h3 class="resource-title">Your Dakshayani crew</h3>
                <p class="resource-meta">Project manager: <strong>Rohit Kumar</strong> · +91 88000 00000</p>
                <p class="resource-meta">Support hours: Mon–Sat, 9:00–19:00 IST</p>
                <div class="resource-actions">
                  <a href="mailto:care@dakshayani.co.in">Email support</a>
                  <a href="#">Book a call</a>
                </div>
              </article>
              <article class="resource-card">
                <h3 class="resource-title">Knowledge centre</h3>
                <p class="resource-meta">Guides for monitoring apps, warranty claims, and maintenance checklists.</p>
                <div class="resource-actions">
                  <a href="#">View guides</a>
                  <a href="#">WhatsApp updates</a>
                </div>
              </article>
            </div>
          </section>

          <section class="panel" id="account">
            <div class="panel-header">
              <div>
                <span class="panel-header__meta">Profile</span>
                <h2>Your contact details</h2>
                <p>Keep your information current so we can reach you quickly.</p>
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

      const generationCtx = document.getElementById('customer-generation-chart');
      if (generationCtx) {
        new Chart(generationCtx, {
          type: 'line',
          data: {
            labels: ['May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct'],
            datasets: [
              {
                label: 'Actual generation',
                data: [720, 760, 810, 835, 870, 910],
                borderColor: '#0ea5e9',
                backgroundColor: 'rgba(14, 165, 233, 0.18)',
                tension: 0.4,
                fill: true,
                pointRadius: 3,
                pointBackgroundColor: '#0ea5e9'
              },
              {
                label: 'Projected',
                data: [700, 740, 790, 820, 860, 900],
                borderColor: '#94a3b8',
                borderDash: [6, 4],
                tension: 0.4,
                fill: false,
                pointRadius: 0
              }
            ]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: { display: false }
            },
            scales: {
              x: {
                ticks: { color: '#64748b' },
                grid: { display: false }
              },
              y: {
                ticks: { color: '#64748b' },
                grid: { color: 'rgba(148, 163, 184, 0.2)', drawBorder: false }
              }
            }
          }
        });
      }

      const progressCtx = document.getElementById('customer-progress-chart');
      if (progressCtx) {
        new Chart(progressCtx, {
          type: 'doughnut',
          data: {
            labels: ['Completed', 'Remaining'],
            datasets: [
              {
                data: [80, 20],
                backgroundColor: ['#0ea5e9', '#dbeafe'],
                borderWidth: 0
              }
            ]
          },
          options: {
            responsive: true,
            cutout: '68%',
            maintainAspectRatio: false,
            plugins: {
              legend: { display: false },
              tooltip: {
                callbacks: {
                  label: function (context) {
                    return context.label + ': ' + context.parsed + '%';
                  }
                }
              }
            }
          }
        });
      }
    });
  </script>
</body>
</html>
