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

  <link rel="stylesheet" href="dashboard-modern.css" />
  <style></style>
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
      <div class="dashboard-layout">
        <aside class="dashboard-sidebar" aria-label="Installer navigation">
          <div class="sidebar-header">
            <span class="sidebar-label">Navigation</span>
            <p class="sidebar-title">Field operations</p>
          </div>
          <nav class="sidebar-nav">
            <a class="sidebar-link" href="#field-overview" aria-current="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 12h18" />
                <path d="M3 6h18" />
                <path d="M3 18h18" />
              </svg>
              Field overview
            </a>
            <a class="sidebar-link" href="#installer-metrics">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M4 19h16" />
                <path d="M7 19V9" />
                <path d="M12 19V5" />
                <path d="M17 19v-7" />
              </svg>
              Performance metrics
            </a>
            <a class="sidebar-link" href="#installer-analytics">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="9" />
                <path d="M12 7v5l3 3" />
              </svg>
              Analytics &amp; charts
            </a>
            <a class="sidebar-link" href="#installer-jobs">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="3" y="4" width="18" height="18" rx="4" />
                <path d="M16 2v4" />
                <path d="M8 2v4" />
                <path d="M3 10h18" />
              </svg>
              Upcoming jobs
            </a>
            <a class="sidebar-link" href="#installer-tasks">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M4 6h9" />
                <path d="M4 12h9" />
                <path d="M4 18h9" />
                <path d="m16 6 2 2 3-3" />
              </svg>
              Daily tasks
            </a>
            <a class="sidebar-link" href="#crew-plan">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="9" cy="7" r="4" />
                <path d="M17 11v6" />
                <path d="M21 11v6" />
                <path d="M7 22a4 4 0 0 1 4-4" />
              </svg>
              Crew deployment
            </a>
            <a class="sidebar-link" href="#installer-checklist">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M9 11l3 3L22 4" />
                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
              </svg>
              Site readiness
            </a>
            <a class="sidebar-link" href="#installer-resources">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M4 19.5V5a2 2 0 0 1 2-2h9" />
                <path d="M20 7v12.5a1.5 1.5 0 0 1-1.5 1.5H6" />
                <path d="M9 3v5h5" />
              </svg>
              Resources
            </a>
            <a class="sidebar-link" href="#installer-profile">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="7" r="4" />
                <path d="M5.5 21a7.5 7.5 0 0 1 13 0" />
              </svg>
              Profile details
            </a>
          </nav>
          <p class="sidebar-footnote">
            Stay ahead of inspections, monitor crew utilisation, and surface blockers before the daily stand-up.
          </p>
        </aside>
        <div class="dashboard-main">
          <header class="page-header">
            <div class="page-header__content">
              <p class="eyebrow">Installer portal</p>
              <h1>On-site plan for <?= htmlspecialchars($displayName); ?></h1>
              <p class="subhead">
                Review your site schedule and action items for today.
                <?php if ($lastLogin): ?>
                  Last sign-in <?= htmlspecialchars($lastLogin); ?>.
                <?php endif; ?>
              </p>
            </div>
            <div class="hero-cards">
              <article class="hero-card">
                <span class="hero-card__icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 12h18" />
                    <path d="M3 6h18" />
                    <path d="M3 18h18" />
                  </svg>
                </span>
                <p class="hero-card__title">Today's focus</p>
                <p class="hero-card__value">6 live jobs</p>
                <p class="hero-card__note">Two sites need structural clearance follow-ups before noon.</p>
              </article>
              <article class="hero-card">
                <span class="hero-card__icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 4h16v6H4z" />
                    <path d="M16 2v4" />
                    <path d="M8 2v4" />
                    <path d="M4 14h16" />
                  </svg>
                </span>
                <p class="hero-card__title">Crew check-in</p>
                <p class="hero-card__value">08:30 IST</p>
                <p class="hero-card__note">Huddle at Jamshedpur HQ with safety refresher on harness inspections.</p>
              </article>
              <article class="hero-card">
                <span class="hero-card__icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 1v4" />
                    <path d="m4.93 4.93 2.83 2.83" />
                    <path d="M1 12h4" />
                    <path d="m4.93 19.07 2.83-2.83" />
                    <path d="M12 15v8" />
                    <circle cx="12" cy="12" r="3" />
                  </svg>
                </span>
                <p class="hero-card__title">Safety status</p>
                <p class="hero-card__value">All clear</p>
                <p class="hero-card__note">PPE inspections updated · No incidents reported in the last 14 days.</p>
              </article>
            </div>
          </header>

          <div class="status-banner" data-dashboard-status hidden></div>

          <section class="panel" id="field-overview">
            <div class="panel-header">
              <div>
                <span class="panel-header__meta">Overview</span>
                <h2>Field overview</h2>
                <p>Quick insight into job count, completion pace, and hand-offs.</p>
              </div>
            </div>
            <p class="panel-intro" data-dashboard-headline>Loading overview…</p>
          </section>

          <section class="panel" id="installer-metrics">
            <div class="panel-header">
              <div>
                <span class="panel-header__meta">Live KPIs</span>
                <h2>Key metrics</h2>
                <p>Track workload, completion time, and safety performance at a glance.</p>
              </div>
            </div>
            <div class="metric-grid" data-metrics></div>
          </section>

          <section class="panel" id="installer-analytics">
            <div class="panel-header">
              <div>
                <span class="panel-header__meta">Insight</span>
                <h2>Operational analytics</h2>
                <p>Visualise crew utilisation and how jobs are distributed across cities.</p>
              </div>
            </div>
            <div class="charts-grid">
              <article class="chart-card">
                <h3>Crew utilisation (this week)</h3>
                <canvas id="installer-utilisation-chart" aria-label="Crew utilisation chart"></canvas>
                <ul class="chart-legend">
                  <li><span style="background:#0ea5e9"></span>On-site</li>
                  <li><span style="background:#facc15"></span>Standby</li>
                  <li><span style="background:#94a3b8"></span>Training / admin</li>
                </ul>
              </article>
              <article class="chart-card">
                <h3>Jobs by city</h3>
                <canvas id="installer-city-chart" aria-label="Jobs by city bar chart"></canvas>
                <ul class="chart-legend">
                  <li><span style="background:#0ea5e9"></span>Active jobs</li>
                </ul>
              </article>
            </div>
          </section>

          <section class="panel" id="installer-jobs">
            <div class="panel-header">
              <div>
                <span class="panel-header__meta">Schedule</span>
                <h2>Upcoming jobs</h2>
                <p>Keep tabs on your next mobilisations and logistics checks.</p>
              </div>
            </div>
            <div class="timeline-list" data-timeline></div>
          </section>

          <section class="panel" id="installer-tasks">
            <div class="panel-header">
              <div>
                <span class="panel-header__meta">Execution</span>
                <h2>Today's tasks</h2>
                <p>Finish these checklists before end-of-day sync with operations.</p>
              </div>
            </div>
            <div class="task-list" data-tasks></div>
          </section>

          <section class="panel" id="crew-plan">
            <div class="panel-header">
              <div>
                <span class="panel-header__meta">Deployment</span>
                <h2>Today's crew plan</h2>
                <p>Deployment snapshot with leads, crew strength, and job phase.</p>
              </div>
            </div>
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

          <section class="panel" id="installer-checklist">
            <div class="panel-header">
              <div>
                <span class="panel-header__meta">Quality</span>
                <h2>Site readiness checklist</h2>
                <p>Confirm these before marking a job ready for commissioning.</p>
              </div>
            </div>
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

          <section class="panel" id="installer-resources">
            <div class="panel-header">
              <div>
                <span class="panel-header__meta">Support</span>
                <h2>Resources &amp; quick links</h2>
                <p>Reference documentation, tools, and trackers for the field team.</p>
              </div>
            </div>
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

          <section class="panel" id="installer-profile">
            <div class="panel-header">
              <div>
                <span class="panel-header__meta">Profile</span>
                <h2>Your crew details</h2>
                <p>Contact information that the back office uses for escalations.</p>
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
      if (!window.Chart) {
        return;
      }

      const utilisationCtx = document.getElementById('installer-utilisation-chart');
      if (utilisationCtx) {
        new Chart(utilisationCtx, {
          type: 'doughnut',
          data: {
            labels: ['On-site', 'Standby', 'Training / admin'],
            datasets: [
              {
                data: [68, 20, 12],
                backgroundColor: ['#0ea5e9', '#facc15', '#94a3b8'],
                borderWidth: 0
              }
            ]
          },
          options: {
            responsive: true,
            cutout: '68%',
            plugins: { legend: { display: false } }
          }
        });
      }

      const cityCtx = document.getElementById('installer-city-chart');
      if (cityCtx) {
        new Chart(cityCtx, {
          type: 'bar',
          data: {
            labels: ['Ranchi', 'Jamshedpur', 'Bokaro', 'Dhanbad'],
            datasets: [
              {
                data: [3, 2, 1, 1],
                backgroundColor: '#0ea5e9',
                borderRadius: 12,
                maxBarThickness: 46
              }
            ]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
              x: {
                ticks: { color: '#64748b' },
                grid: { display: false }
              },
              y: {
                ticks: { color: '#64748b' },
                grid: { color: 'rgba(148, 163, 184, 0.2)', drawBorder: false },
                beginAtZero: true,
                suggestedMax: 4
              }
            }
          }
        });
      }
    });
  </script>
</body>
</html>
