(function () {
  const sessionKey = 'dakshayaniPortalSession';
  const demoData = window.DAKSHAYANI_PORTAL_DEMO || {};
  const users = Array.isArray(demoData.users) ? demoData.users : [];
  const dashboards = demoData.dashboards || {};
  const role = document.body?.dataset?.role;

  if (!role) {
    return;
  }

  const statusBar = document.querySelector('[data-dashboard-status]');
  const metricList = document.querySelector('[data-metrics]');
  const timelineList = document.querySelector('[data-timeline]');
  const taskList = document.querySelector('[data-tasks]');
  const headline = document.querySelector('[data-dashboard-headline]');
  const logoutButtons = document.querySelectorAll('[data-logout]');

  function showStatus(message, type = 'info') {
    if (!statusBar) return;
    statusBar.textContent = message;
    statusBar.dataset.tone = type;
    statusBar.hidden = false;
  }

  function hideStatus() {
    if (!statusBar) return;
    statusBar.hidden = true;
    statusBar.textContent = '';
  }

  function redirectToLogin() {
    window.location.href = 'login.html?loggedOut=1';
  }

  function parseSession() {
    try {
      const raw = localStorage.getItem(sessionKey);
      return raw ? JSON.parse(raw) : null;
    } catch (error) {
      console.warn('Unable to parse stored session', error);
      return null;
    }
  }

  function findUserByEmail(email) {
    return users.find((user) => user.email.toLowerCase() === String(email || '').toLowerCase()) || null;
  }

  function bindUserDetails(user) {
    const mapping = {
      '[data-user-name]': user.name || 'Demo user',
      '[data-user-email]': user.email,
      '[data-user-phone]': user.phone || 'Not shared',
      '[data-user-city]': user.city || '—',
      '[data-user-id]': user.id || '—',
      '[data-user-role]': user.role
    };

    Object.entries(mapping).forEach(([selector, value]) => {
      document.querySelectorAll(selector).forEach((element) => {
        element.textContent = value;
      });
    });
  }

  function renderMetrics(items) {
    if (!metricList) return;
    metricList.innerHTML = '';
    if (!items || !items.length) {
      metricList.innerHTML = '<p class="empty">No metrics to display.</p>';
      return;
    }
    items.forEach((metric) => {
      const card = document.createElement('article');
      card.className = 'metric-card';
      card.innerHTML = `
        <p class="metric-label">${metric.label}</p>
        <p class="metric-value">${metric.value}</p>
        <p class="metric-helper">${metric.helper || ''}</p>
      `;
      metricList.appendChild(card);
    });
  }

  function renderTimeline(items) {
    if (!timelineList) return;
    timelineList.innerHTML = '';
    if (!items || !items.length) {
      timelineList.innerHTML = '<p class="empty">Nothing scheduled yet.</p>';
      return;
    }
    items.forEach((item) => {
      const row = document.createElement('div');
      row.className = 'timeline-row';
      row.innerHTML = `
        <p class="timeline-label">${item.label}</p>
        <p class="timeline-date">${item.date}</p>
        <span class="timeline-status">${item.status}</span>
      `;
      timelineList.appendChild(row);
    });
  }

  function renderTasks(items) {
    if (!taskList) return;
    taskList.innerHTML = '';
    if (!items || !items.length) {
      taskList.innerHTML = '<p class="empty">You are all caught up.</p>';
      return;
    }
    items.forEach((item) => {
      const row = document.createElement('div');
      row.className = 'task-row';
      row.innerHTML = `
        <span>${item.label}</span>
        <span class="task-status">${item.status}</span>
      `;
      taskList.appendChild(row);
    });
  }

  function renderDashboard(data) {
    if (headline) {
      headline.textContent = data.headline || 'Dashboard overview';
    }
    renderMetrics(data.metrics || []);
    renderTimeline(data.timeline || []);
    renderTasks(data.tasks || []);
  }

  function initLogout() {
    logoutButtons.forEach((button) => {
      button.addEventListener('click', () => {
        localStorage.removeItem(sessionKey);
        redirectToLogin();
      });
    });
  }

  const session = parseSession();
  if (!session) {
    redirectToLogin();
    return;
  }

  if (session.role !== role) {
    showStatus('You opened the wrong portal for this login. Taking you back to the sign in page.', 'error');
    setTimeout(() => redirectToLogin(), 600);
    return;
  }

  const user = findUserByEmail(session.email) || session;
  bindUserDetails(user);

  const dashboard = dashboards[role];
  if (!dashboard) {
    showStatus('Demo data for this dashboard is missing. Contact the site maintainer.', 'error');
  } else {
    hideStatus();
    renderDashboard(dashboard);
  }

  initLogout();
})();
