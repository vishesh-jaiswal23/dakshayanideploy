<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'employee') {
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

    <div class="status-banner" data-dashboard-status hidden></div>

    <section class="panel">
      <h2>Focus for the week</h2>
      <p class="lead" data-dashboard-headline>Loading overview…</p>
    </section>

    <section class="panel">
      <h2>Key metrics</h2>
      <div class="metric-grid" data-metrics></div>
    </section>

    <section class="panel">
      <h2>Pipeline breakdown</h2>
      <p class="lead">Live view of where your active tickets sit across the success workflow.</p>
      <div class="pipeline-grid" data-pipeline-list></div>
    </section>

    <section class="panel">
      <h2>Customer sentiment watchlist</h2>
      <p class="lead">Accounts that need a personalised follow-up to protect CSAT.</p>
      <div class="sentiment-list" data-sentiment-list></div>
    </section>

    <section class="panel">
      <h2>Upcoming meetings</h2>
      <div class="timeline-list" data-timeline></div>
    </section>

    <section class="panel">
      <h2>Priority tasks</h2>
      <div class="task-list" data-tasks></div>
    </section>

    <section class="panel">
      <h2>Ticket queue health</h2>
      <div class="board-grid">
        <article class="board-card" data-tone="warning">
          <p class="board-title">Due today</p>
          <p class="board-value">5 tickets</p>
          <p class="board-helper">Focus on DE-2041, JSR-118, and PKL-552 escalations.</p>
        </article>
        <article class="board-card" data-tone="success">
          <p class="board-title">Resolution time</p>
          <p class="board-value">3.8 hrs</p>
          <p class="board-helper">Running average across the last seven closed tickets.</p>
        </article>
        <article class="board-card">
          <p class="board-title">SLA at risk</p>
          <p class="board-value">2 cases</p>
          <p class="board-helper">Schedule callbacks for Ranchi net-metering and Bokaro upgrade.</p>
        </article>
      </div>
    </section>

    <section class="panel">
      <h2>Team updates &amp; announcements</h2>
      <div class="update-list">
        <article class="update-card">
          <strong>Installer huddle</strong>
          <p class="update-meta">Crew feedback is due before 18:00 IST so we can adjust the weekend rota.</p>
        </article>
        <article class="update-card">
          <strong>Knowledge base refresh</strong>
          <p class="update-meta">New article covering the Jharkhand DISCOM subsidy workflow is live now.</p>
        </article>
        <article class="update-card">
          <strong>Leadership note</strong>
            <p class="update-meta">CSAT crossed 4.7 — share one success story in tomorrow's stand-up.</p>
        </article>
      </div>
    </section>

    <section class="panel">
      <h2>Customer care shortcuts</h2>
      <div class="resource-grid">
        <article class="resource-card">
          <h3>Escalation directory</h3>
          <p class="update-meta">Contact sheet for on-call engineering, finance, and compliance approvers.</p>
          <div class="resource-actions">
            <a href="#">Download PDF</a>
            <a href="#">View in Drive</a>
          </div>
        </article>
        <article class="resource-card">
          <h3>CRM board</h3>
          <p class="update-meta">Quick link to the "Customer Success · October" Kanban board.</p>
          <div class="resource-actions">
            <a href="#">Open board</a>
            <a href="#">Create task</a>
          </div>
        </article>
        <article class="resource-card">
          <h3>Voice templates</h3>
          <p class="update-meta">Scripts for inspection scheduling, payment reminders, and referral upgrades.</p>
          <div class="resource-actions">
            <a href="#">Browse scripts</a>
            <a href="#">Share feedback</a>
          </div>
        </article>
      </div>
    </section>

    <section class="panel">
      <h2>Your profile</h2>
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
          <span data-profile-field="phone"><?= htmlspecialchars($normalizedPhone === '' ? '—' : $normalizedPhone); ?></span>
        </div>
        <div>
          <strong>Email</strong>
          <span><?= htmlspecialchars($userEmail); ?></span>
        </div>
        <div>
          <strong>City / service cluster</strong>
          <span data-profile-field="city"><?= htmlspecialchars($normalizedCity === '' ? '—' : $normalizedCity); ?></span>
        </div>
        <div>
          <strong>Desk location</strong>
          <span data-profile-field="deskLocation">—</span>
        </div>
        <div>
          <strong>Working hours preference</strong>
          <span data-profile-field="workingHours">—</span>
        </div>
        <div>
          <strong>Emergency contact</strong>
          <span data-profile-field="emergencyContact">—</span>
        </div>
        <div>
          <strong>Preferred channel</strong>
          <span data-profile-field="preferredChannel">—</span>
        </div>
        <div>
          <strong>Reporting manager</strong>
          <span data-profile-field="reportingManager">—</span>
        </div>
      </div>
    </section>

    <section class="panel">
      <h2>Update your contact details</h2>
      <p class="lead">Keep your day-to-day contact preferences current for faster escalations and callbacks.</p>
      <form data-profile-form>
        <div class="form-grid">
          <div class="form-group">
            <label for="profile-phone">Primary phone number</label>
            <input id="profile-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" data-profile-input="phone" placeholder="e.g. +91 98765 43210" />
          </div>
          <div class="form-group">
            <label for="profile-city">City / service cluster</label>
            <input id="profile-city" name="city" type="text" autocomplete="address-level2" data-profile-input="city" placeholder="e.g. Jamshedpur &amp; Bokaro" />
          </div>
          <div class="form-group">
            <label for="profile-emergency">Emergency contact</label>
            <input id="profile-emergency" name="emergencyContact" type="text" data-profile-input="emergencyContact" placeholder="Name &amp;middot; Phone number" />
          </div>
          <div class="form-group">
            <label for="profile-hours">Working hours preference</label>
            <input id="profile-hours" name="workingHours" type="text" data-profile-input="workingHours" placeholder="e.g. 10:00 &ndash; 18:30 IST" />
          </div>
          <div class="form-group">
            <label for="profile-channel">Preferred communication channel</label>
            <select id="profile-channel" name="preferredChannel" data-profile-input="preferredChannel">
              <option value="Phone call">Phone call</option>
              <option value="WhatsApp">WhatsApp</option>
              <option value="Email summary">Email summary</option>
            </select>
          </div>
        </div>
        <p class="form-note">Changes save instantly for the operations roster. Sensitive updates such as bank details or access scopes should go through the approval workflow below.</p>
        <div class="form-actions">
          <button class="primary-btn" type="submit">Save updates</button>
        </div>
        <p class="form-feedback" data-profile-feedback></p>
      </form>
    </section>

    <section class="panel">
      <h2>Requests requiring admin approval</h2>
      <p class="lead">Track sensitive changes that route to the admin desk and log new requests when you need support.</p>
      <div class="approval-list" data-approval-list></div>
      <p class="form-note" data-approval-summary></p>
      <h3>Log a new approval request</h3>
      <form data-approval-form>
        <div class="form-grid">
          <div class="form-group">
            <label for="approval-change-type">Change type</label>
            <select id="approval-change-type" name="changeType" required>
              <option value="" disabled selected>Choose a request</option>
              <option value="Payroll bank update">Payroll bank update</option>
              <option value="Access scope change">Access scope change</option>
              <option value="Desk relocation">Desk relocation</option>
              <option value="Long leave / remote work">Long leave / remote work</option>
            </select>
          </div>
          <div class="form-group">
            <label for="approval-effective-date">Target effective date</label>
            <input id="approval-effective-date" name="effectiveDate" type="date" />
          </div>
        </div>
        <div class="form-group">
          <label for="approval-justification">Summary for the admin team</label>
          <textarea id="approval-justification" name="justification" placeholder="Add context, ticket IDs, or the reason for the change"></textarea>
        </div>
        <div class="form-actions">
          <button class="primary-btn" type="submit">Submit for approval</button>
        </div>
        <p class="form-feedback" data-approval-feedback></p>
      </form>
      <h3>Recent admin decisions</h3>
      <div class="history-list" data-approval-history></div>
    </section>
  </main>

  <script src="portal-demo-data.js"></script>
  <script src="dashboard-auth.js"></script>
  <script>
    (function () {
      const portalData = window.DAKSHAYANI_PORTAL_DEMO?.employeePortal || {};
      const pipelineContainer = document.querySelector('[data-pipeline-list]');
      const sentimentContainer = document.querySelector('[data-sentiment-list]');
      const approvalsContainer = document.querySelector('[data-approval-list]');
      const approvalSummary = document.querySelector('[data-approval-summary]');
      const approvalHistoryContainer = document.querySelector('[data-approval-history]');
      const profileForm = document.querySelector('[data-profile-form]');
      const profileFeedback = document.querySelector('[data-profile-feedback]');
      const approvalForm = document.querySelector('[data-approval-form]');
      const approvalFeedback = document.querySelector('[data-approval-feedback]');
      const profileDisplayNodes = document.querySelectorAll('[data-profile-field]');
      const profileInputs = profileForm ? Array.from(profileForm.querySelectorAll('[data-profile-input]')) : [];

      const phpPhone = <?= json_encode($normalizedPhone); ?>;
      const phpCity = <?= json_encode($normalizedCity); ?>;

      const profileState = {
        phone: phpPhone || portalData.profile?.phone || '',
        city: phpCity || portalData.profile?.serviceRegion || portalData.profile?.city || '',
        emergencyContact: portalData.profile?.emergencyContact || '',
        workingHours: portalData.profile?.workingHours || '',
        preferredChannel: portalData.profile?.preferredChannel || 'Phone call',
        deskLocation: portalData.profile?.deskLocation || '',
        reportingManager: portalData.profile?.reportingManager || ''
      };

      const pipelineData = Array.isArray(portalData.pipeline) ? portalData.pipeline.slice() : [];
      const sentimentData = Array.isArray(portalData.sentimentWatch) ? portalData.sentimentWatch.slice() : [];
      const approvalsState = Array.isArray(portalData.approvals) ? portalData.approvals.map((item) => ({ ...item })) : [];
      const approvalHistoryState = Array.isArray(portalData.approvalHistory) ? portalData.approvalHistory.map((item) => ({ ...item })) : [];

      const profileDisplayMap = {};
      profileDisplayNodes.forEach((node) => {
        const field = node.dataset.profileField;
        if (!field) return;
        profileDisplayMap[field] = profileDisplayMap[field] || [];
        profileDisplayMap[field].push(node);
      });

      const profileInputMap = {};
      profileInputs.forEach((input) => {
        const field = input.dataset.profileInput;
        if (!field) return;
        profileInputMap[field] = input;
        const value = profileState[field] || '';
        if (input.tagName === 'SELECT') {
          const values = Array.from(input.options).map((option) => option.value);
          if (values.includes(value)) {
            input.value = value;
          }
        } else {
          input.value = value;
        }
      });

      function formatDisplay(value) {
        if (value === null || value === undefined) {
          return '—';
        }
        const trimmed = String(value).trim();
        return trimmed.length ? trimmed : '—';
      }

      function syncProfileDisplay() {
        Object.entries(profileDisplayMap).forEach(([field, nodes]) => {
          let current = profileState[field];
          if (!current) {
            if (field === 'city') {
              current = portalData.profile?.serviceRegion || portalData.profile?.city || '';
            } else if (field === 'deskLocation') {
              current = portalData.profile?.deskLocation || '';
            } else if (field === 'workingHours') {
              current = portalData.profile?.workingHours || '';
            } else if (field === 'emergencyContact') {
              current = portalData.profile?.emergencyContact || '';
            } else if (field === 'preferredChannel') {
              current = portalData.profile?.preferredChannel || '';
            } else if (field === 'reportingManager') {
              current = portalData.profile?.reportingManager || '';
            }
          }
          nodes.forEach((node) => {
            node.textContent = formatDisplay(current);
          });
        });
      }

      function setFeedback(node, tone, message) {
        if (!node) return;
        node.textContent = message || '';
        if (!message) {
          node.hidden = true;
          delete node.dataset.tone;
          return;
        }
        node.hidden = false;
        if (tone && tone !== 'info') {
          node.dataset.tone = tone;
        } else {
          delete node.dataset.tone;
        }
      }

      function renderPipeline() {
        if (!pipelineContainer) return;
        pipelineContainer.innerHTML = '';
        if (!pipelineData.length) {
          const empty = document.createElement('p');
          empty.className = 'empty';
          empty.textContent = 'No pipeline data yet.';
          pipelineContainer.appendChild(empty);
          return;
        }
        pipelineData.forEach((item) => {
          const card = document.createElement('article');
          card.className = 'pipeline-card';
          const stage = document.createElement('p');
          stage.className = 'pipeline-stage';
          stage.textContent = item.stage || 'Stage';
          const count = document.createElement('p');
          count.className = 'pipeline-count';
          count.textContent = item.count != null ? item.count : '—';
          card.appendChild(stage);
          card.appendChild(count);
          if (item.note) {
            const note = document.createElement('p');
            note.className = 'pipeline-note';
            note.textContent = item.note;
            card.appendChild(note);
          }
          if (item.sla) {
            const sla = document.createElement('p');
            sla.className = 'pipeline-note';
            sla.textContent = item.sla;
            card.appendChild(sla);
          }
          pipelineContainer.appendChild(card);
        });
      }

      function resolveTone(label = '') {
        const value = String(label).toLowerCase();
        if (value.includes('risk') || value.includes('pending') || value.includes('attention')) return 'warning';
        if (value.includes('approved') || value.includes('completed') || value.includes('improving') || value.includes('active')) return 'success';
        if (value.includes('declined') || value.includes('rejected') || value.includes('blocked')) return 'error';
        return 'info';
      }

      function createStatusPill(text, tone) {
        const pill = document.createElement('span');
        pill.className = 'status-pill';
        pill.textContent = text;
        if (tone && tone !== 'info') {
          pill.dataset.tone = tone;
        }
        return pill;
      }

      function renderSentiments() {
        if (!sentimentContainer) return;
        sentimentContainer.innerHTML = '';
        if (!sentimentData.length) {
          const empty = document.createElement('p');
          empty.className = 'empty';
          empty.textContent = 'No customers flagged for follow-up today.';
          sentimentContainer.appendChild(empty);
          return;
        }
        sentimentData.forEach((item) => {
          const card = document.createElement('article');
          card.className = 'sentiment-card';
          if (item.customer) {
            const title = document.createElement('p');
            title.innerHTML = `<strong>${item.customer}</strong>`;
            card.appendChild(title);
          }
          if (item.nextStep) {
            const next = document.createElement('p');
            next.className = 'approval-meta';
            next.textContent = item.nextStep;
            card.appendChild(next);
          }
          const tags = document.createElement('div');
          tags.className = 'sentiment-tags';
          if (item.sentiment) {
            tags.appendChild(createStatusPill(item.sentiment, resolveTone(item.sentiment)));
          }
          if (item.trend) {
            tags.appendChild(createStatusPill(item.trend, resolveTone(item.trend)));
          }
          if (item.owner) {
            tags.appendChild(createStatusPill(`Owner: ${item.owner}`, 'info'));
          }
          if (tags.childNodes.length) {
            card.appendChild(tags);
          }
          if (item.due) {
            const due = document.createElement('p');
            due.className = 'approval-meta';
            due.textContent = `Next check-in: ${item.due}`;
            card.appendChild(due);
          }
          sentimentContainer.appendChild(card);
        });
      }

      function renderApprovals() {
        if (!approvalsContainer) return;
        approvalsContainer.innerHTML = '';
        if (!approvalsState.length) {
          const empty = document.createElement('p');
          empty.className = 'empty';
          empty.textContent = 'No active approval requests yet.';
          approvalsContainer.appendChild(empty);
        } else {
          approvalsState.forEach((item) => {
            const card = document.createElement('article');
            card.className = 'approval-card';
            const header = document.createElement('div');
            header.className = 'approval-header';
            const title = document.createElement('p');
            title.className = 'approval-title';
            title.textContent = item.title || 'Change request';
            header.appendChild(title);
            if (item.status) {
              header.appendChild(createStatusPill(item.status, resolveTone(item.status)));
            }
            card.appendChild(header);
            const metaParts = [];
            if (item.id) metaParts.push(item.id);
            if (item.submitted) metaParts.push(`Submitted ${item.submitted}`);
            if (item.owner) metaParts.push(`Routed to ${item.owner}`);
            if (metaParts.length) {
              const meta = document.createElement('p');
              meta.className = 'approval-meta';
              meta.textContent = metaParts.join(' • ');
              card.appendChild(meta);
            }
            if (item.details) {
              const body = document.createElement('p');
              body.className = 'approval-details';
              body.textContent = item.details;
              card.appendChild(body);
            }
            if (item.effectiveDate) {
              const effective = document.createElement('p');
              effective.className = 'approval-meta';
              effective.textContent = `Requested effective date: ${item.effectiveDate}`;
              card.appendChild(effective);
            }
            if (item.lastUpdate) {
              const update = document.createElement('p');
              update.className = 'approval-meta';
              update.textContent = item.lastUpdate;
              card.appendChild(update);
            }
            approvalsContainer.appendChild(card);
          });
        }

        if (approvalSummary) {
          if (!approvalsState.length) {
            approvalSummary.textContent = 'No approvals are awaiting action.';
          } else {
            const pending = approvalsState.filter((item) => String(item.status || '').toLowerCase().includes('pending')).length;
            const approved = approvalsState.filter((item) => String(item.status || '').toLowerCase().includes('approved')).length;
            approvalSummary.textContent = `Pending: ${pending} · Approved: ${approved} · Total tracked: ${approvalsState.length}.`;
          }
        }
      }

      function renderHistory() {
        if (!approvalHistoryContainer) return;
        approvalHistoryContainer.innerHTML = '';
        if (!approvalHistoryState.length) {
          const empty = document.createElement('p');
          empty.className = 'empty';
          empty.textContent = 'No previous admin decisions logged.';
          approvalHistoryContainer.appendChild(empty);
          return;
        }
        approvalHistoryState.forEach((item) => {
          const row = document.createElement('article');
          row.className = 'history-item';
          const header = document.createElement('div');
          header.className = 'approval-header';
          const title = document.createElement('p');
          title.className = 'approval-title';
          title.textContent = `${item.title || 'Request'}${item.id ? ` · ${item.id}` : ''}`;
          header.appendChild(title);
          header.appendChild(createStatusPill(item.status || 'Completed', resolveTone(item.status)));
          row.appendChild(header);
          if (item.resolved || item.status) {
            const meta = document.createElement('p');
            meta.className = 'approval-meta';
            const statusText = item.status || 'Completed';
            meta.textContent = item.resolved ? `${statusText} on ${item.resolved}` : statusText;
            row.appendChild(meta);
          }
          if (item.outcome) {
            const outcome = document.createElement('p');
            outcome.className = 'approval-meta';
            outcome.textContent = item.outcome;
            row.appendChild(outcome);
          }
          approvalHistoryContainer.appendChild(row);
        });
      }

      function extractApprovalCounter() {
        const numbers = []
          .concat(approvalsState, approvalHistoryState)
          .map((item) => {
            const match = /APP-(\d+)/.exec(item?.id || '');
            return match ? parseInt(match[1], 10) : null;
          })
          .filter((value) => Number.isFinite(value));
        return numbers.length ? Math.max(...numbers) : 1100;
      }

      let approvalCounter = extractApprovalCounter();

      function generateApprovalId() {
        approvalCounter += 1;
        return `APP-${approvalCounter}`;
      }

      function handleProfileSubmit(event) {
        event.preventDefault();
        if (!profileForm) return;
        let updated = false;
        Object.entries(profileInputMap).forEach(([field, input]) => {
          if (!input) return;
          let nextValue = input.value;
          if (input.tagName === 'INPUT' || input.tagName === 'TEXTAREA') {
            nextValue = nextValue.trim();
          }
          if (profileState[field] !== nextValue) {
            profileState[field] = nextValue;
            updated = true;
          }
        });
        syncProfileDisplay();
        if (updated) {
          setFeedback(profileFeedback, 'success', 'Contact preferences updated. Operations will reference the latest details.');
        } else {
          setFeedback(profileFeedback, 'info', 'No changes detected — everything is already up to date.');
        }
      }

      function handleApprovalSubmit(event) {
        event.preventDefault();
        if (!approvalForm) return;
        const formData = new FormData(approvalForm);
        const changeType = (formData.get('changeType') || '').toString();
        const effectiveDate = (formData.get('effectiveDate') || '').toString();
        const justification = (formData.get('justification') || '').toString().trim();
        if (!changeType) {
          setFeedback(approvalFeedback, 'error', 'Select the type of change that needs approval.');
          return;
        }
        if (!justification) {
          setFeedback(approvalFeedback, 'error', 'Add a short justification so the admin team can review quickly.');
          return;
        }
        const now = new Date();
        const formatter = new Intl.DateTimeFormat('en-IN', { dateStyle: 'medium', timeStyle: 'short' });
        let effectiveDisplay = '';
        if (effectiveDate) {
          const parsed = new Date(`${effectiveDate}T00:00:00`);
          if (!Number.isNaN(parsed.getTime())) {
            effectiveDisplay = formatter.format(parsed);
          }
        }
        const routedTo =
          changeType === 'Payroll bank update'
            ? 'Finance & Admin'
            : changeType === 'Access scope change'
            ? 'Admin desk'
            : changeType === 'Desk relocation'
            ? 'Workplace support'
            : 'People operations';

        approvalsState.unshift({
          id: generateApprovalId(),
          title: changeType,
          status: 'Pending admin review',
          submitted: formatter.format(now),
          owner: routedTo,
          details: justification,
          effectiveDate: effectiveDisplay,
          lastUpdate: 'Waiting for admin triage'
        });

        renderApprovals();
        setFeedback(approvalFeedback, 'success', 'Request submitted. You will be notified once an admin reviews it.');
        approvalForm.reset();
      }

      if (profileForm) {
        profileForm.addEventListener('submit', handleProfileSubmit);
        setFeedback(profileFeedback, null, '');
      }

      if (approvalForm) {
        approvalForm.addEventListener('submit', handleApprovalSubmit);
        setFeedback(approvalFeedback, null, '');
      }

      syncProfileDisplay();
      renderPipeline();
      renderSentiments();
      renderApprovals();
      renderHistory();
    })();
  </script>
</body>
</html>
