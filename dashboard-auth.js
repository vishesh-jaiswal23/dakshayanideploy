(function () {
  const API_BASE = window.PORTAL_API_BASE || '';
  const sessionKey = 'dakshayaniPortalSession';
  const demoData = window.DAKSHAYANI_PORTAL_DEMO || {};
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

  let session = parseSession();

  function resolveApi(path) {
    if (!API_BASE) {
      return path;
    }
    try {
      return new URL(path, API_BASE).toString();
    } catch (error) {
      console.warn('Failed to resolve API URL', error);
      return path;
    }
  }

  async function request(path, options = {}) {
    const url = resolveApi(path);
    const config = { method: 'GET', headers: {}, credentials: 'same-origin', ...options };
    config.method = (config.method || 'GET').toUpperCase();
    config.headers = { ...config.headers };

    if (session?.token) {
      config.headers['Authorization'] = `Bearer ${session.token}`;
    }

    if (config.body && typeof config.body !== 'string') {
      config.body = JSON.stringify(config.body);
      if (!config.headers['Content-Type']) {
        config.headers['Content-Type'] = 'application/json';
      }
    }

    let response;
    try {
      response = await fetch(url, config);
    } catch (networkError) {
      const error = new Error('Network error while contacting the portal API.');
      error.isNetworkError = true;
      throw error;
    }

    let data = {};
    try {
      data = await response.json();
    } catch (parseError) {
      data = {};
    }

    if (!response.ok) {
      const error = new Error(data.error || `Request failed with status ${response.status}`);
      error.status = response.status;
      throw error;
    }

    return data;
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

  function storeSession(nextSession) {
    session = nextSession;
    try {
      localStorage.setItem(sessionKey, JSON.stringify(session));
    } catch (error) {
      console.warn('Unable to persist updated session', error);
    }
  }

  function removeSession() {
    try {
      localStorage.removeItem(sessionKey);
    } catch (error) {
      console.warn('Unable to clear session', error);
    }
    session = null;
  }

  function showStatus(message, type = 'info') {
    if (!statusBar) return;
    statusBar.textContent = message;
    statusBar.dataset.tone = type;
    statusBar.hidden = !message;
  }

  function hideStatus() {
    if (!statusBar) return;
    statusBar.hidden = true;
    statusBar.textContent = '';
    delete statusBar.dataset.tone;
  }

  function redirectToLogin() {
    window.location.href = 'login.html?loggedOut=1';
  }

  function bindUserDetails(user) {
    if (!user) return;
    const mapping = {
      '[data-user-name]': user.name || 'Portal user',
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

  function renderDashboard(data, source = 'live') {
    if (headline) {
      headline.textContent = data.headline || data.spotlight?.title || 'Dashboard overview';
    }
    renderMetrics(data.metrics || []);
    renderTimeline(data.timeline || []);
    renderTasks(data.tasks || []);

    if (data.spotlight && data.spotlight.title) {
      showStatus(`${data.spotlight.title}: ${data.spotlight.message}`, 'info');
    } else if (source === 'demo') {
      showStatus('You are viewing cached demo data because the API is offline.', 'info');
    } else {
      hideStatus();
    }
  }

  function useDemoDashboard(message) {
    const demo = dashboards[role];
    if (!demo) {
      showStatus(message || 'Demo data for this dashboard is missing. Contact the site maintainer.', 'error');
      return;
    }
    renderDashboard(demo, 'demo');
    if (message) {
      showStatus(message, 'info');
    }
  }

  function initLogout() {
    logoutButtons.forEach((button) => {
      button.addEventListener('click', () => {
        removeSession();
        redirectToLogin();
      });
    });
  }

  if (!session || !session.user) {
    redirectToLogin();
    return;
  }

  if (session.user.role !== role) {
    showStatus('You opened the wrong portal for this login. Taking you back to the sign in page.', 'error');
    setTimeout(() => redirectToLogin(), 650);
    return;
  }

  bindUserDetails(session.user);
  initLogout();

  if (session.isDemo || !session.token) {
    useDemoDashboard('Offline demo mode active. Start the API server to see live data.');
    return;
  }

  (async () => {
    try {
      const me = await request('/api/me');
      if (me?.user) {
        bindUserDetails(me.user);
        storeSession({ ...session, user: { ...session.user, ...me.user }, isDemo: false });
      }

      const data = await request(`/api/dashboard/${role}`);
      renderDashboard(data, 'live');
    } catch (error) {
      if (error.status === 401) {
        removeSession();
        redirectToLogin();
      } else if (error.isNetworkError) {
        useDemoDashboard('API offline detected. Showing cached demo insights.');
      } else {
        showStatus(error.message || 'Unable to load dashboard data.', 'error');
      }
    }
  })();
})();
