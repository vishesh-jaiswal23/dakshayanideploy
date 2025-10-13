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

  const siteSettingsPanel = document.querySelector('[data-site-settings-panel]');
  const siteSettingsForm = siteSettingsPanel?.querySelector('[data-site-settings-form]');
  const siteSettingsFeedback = siteSettingsPanel?.querySelector('[data-site-settings-feedback]');
  const themeSelect = siteSettingsPanel?.querySelector('[data-settings-theme]');
  const heroFieldNodes = siteSettingsPanel ? Array.from(siteSettingsPanel.querySelectorAll('[data-hero-field]')) : [];
  const heroInputs = heroFieldNodes.reduce((acc, node) => {
    const field = node.dataset.heroField;
    if (field) acc[field] = node;
    return acc;
  }, {});
  const galleryEntries = siteSettingsPanel ? Array.from(siteSettingsPanel.querySelectorAll('[data-gallery-index]')) : [];
  const installsList = siteSettingsPanel?.querySelector('[data-installs-list]');
  const addInstallButton = siteSettingsPanel?.querySelector('[data-add-install]');
  const installTemplate = siteSettingsPanel?.querySelector('#install-form-template');

  let adminControlsInitialised = false;
  let currentSiteSettings = null;

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

  function showSettingsFeedback(message, tone) {
    if (!siteSettingsFeedback) return;
    siteSettingsFeedback.textContent = message || '';
    if (tone) {
      siteSettingsFeedback.dataset.tone = tone;
    } else {
      delete siteSettingsFeedback.dataset.tone;
    }
  }

  function toggleSiteSettingsDisabled(disabled) {
    if (!siteSettingsForm) return;
    siteSettingsForm.querySelectorAll('input, textarea, select, button').forEach((element) => {
      if (element.closest('template')) return;
      element.disabled = disabled;
    });
  }

  function populateGalleryFields(gallery) {
    if (!galleryEntries.length) return;
    galleryEntries.forEach((entry, index) => {
      const item = Array.isArray(gallery) ? gallery[index] || {} : {};
      const imageInput = entry.querySelector('[data-gallery-field="image"]');
      const captionInput = entry.querySelector('[data-gallery-field="caption"]');
      if (imageInput) imageInput.value = item.image || '';
      if (captionInput) captionInput.value = item.caption || '';
    });
  }

  function populateHeroFields(hero) {
    if (!siteSettingsForm) return;
    const data = hero || {};
    Object.entries(heroInputs).forEach(([field, input]) => {
      if (!input) return;
      const value = data[field];
      input.value = typeof value === 'string' ? value : '';
    });
    populateGalleryFields(data.gallery);
  }

  function generateInstallId() {
    return `install-${Date.now()}-${Math.floor(Math.random() * 1000)}`;
  }

  function refreshInstallHeadings() {
    if (!installsList) return;
    Array.from(installsList.querySelectorAll('[data-install-item]')).forEach((row, index) => {
      const heading = row.querySelector('[data-install-heading]');
      const titleInput = row.querySelector('[data-install-field="title"]');
      if (heading) {
        const title = titleInput?.value?.trim();
        heading.textContent = title || `Install #${index + 1}`;
      }
    });
  }

  function createInstallRow(data = {}) {
    if (!installsList) return null;
    let row = null;

    if (installTemplate) {
      const fragment = installTemplate.content.cloneNode(true);
      row = fragment.querySelector('[data-install-item]');
      if (!row) return null;
      row.dataset.installId = data.id || generateInstallId();
      installsList.appendChild(fragment);
    } else {
      row = document.createElement('div');
      row.className = 'install-form-row';
      row.dataset.installItem = '';
      row.dataset.installId = data.id || generateInstallId();
      row.innerHTML = `
        <div class="install-form-header">
          <h3 data-install-heading>Install #1</h3>
          <button type="button" class="btn-ghost" data-remove-install>&times; Remove</button>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label>Project title</label>
            <input type="text" data-install-field="title" />
          </div>
          <div class="form-group">
            <label>Location</label>
            <input type="text" data-install-field="location" />
          </div>
          <div class="form-group">
            <label>Capacity</label>
            <input type="text" data-install-field="capacity" />
          </div>
          <div class="form-group">
            <label>Completion month</label>
            <input type="text" data-install-field="completedOn" />
          </div>
          <div class="form-group">
            <label>Image URL</label>
            <input type="url" data-install-field="image" />
          </div>
        </div>
        <div class="form-group">
          <label>Summary</label>
          <textarea data-install-field="summary"></textarea>
        </div>
      `;
      installsList.appendChild(row);
    }

    if (!row) return null;

    row.dataset.installId = data.id || row.dataset.installId || generateInstallId();

    row.querySelectorAll('[data-install-field]').forEach((input) => {
      const field = input.dataset.installField;
      const value = data[field];
      input.value = typeof value === 'string' ? value : '';
      if (field === 'title') {
        input.addEventListener('input', () => refreshInstallHeadings());
      }
    });

    const removeButton = row.querySelector('[data-remove-install]');
    if (removeButton) {
      removeButton.addEventListener('click', () => {
        row.remove();
        refreshInstallHeadings();
      });
    }

    refreshInstallHeadings();
    return row;
  }

  function renderInstallRows(installs) {
    if (!installsList) return;
    installsList.innerHTML = '';
    const items = Array.isArray(installs) && installs.length ? installs : [{}];
    items.forEach((item) => createInstallRow(item));
    refreshInstallHeadings();
  }

  function gatherGalleryData() {
    return galleryEntries.map((entry) => {
      const imageInput = entry.querySelector('[data-gallery-field="image"]');
      const captionInput = entry.querySelector('[data-gallery-field="caption"]');
      return {
        image: imageInput?.value?.trim() || '',
        caption: captionInput?.value?.trim() || ''
      };
    });
  }

  function gatherHeroData() {
    const hero = {};
    Object.entries(heroInputs).forEach(([field, input]) => {
      hero[field] = input?.value?.trim() || '';
    });
    hero.gallery = gatherGalleryData();
    return hero;
  }

  function gatherInstallData() {
    if (!installsList) return [];
    return Array.from(installsList.querySelectorAll('[data-install-item]')).map((row, index) => {
      const install = { id: row.dataset.installId || `install-${index + 1}` };
      row.querySelectorAll('[data-install-field]').forEach((input) => {
        const field = input.dataset.installField;
        if (!field) return;
        install[field] = input.value.trim();
      });
      return install;
    });
  }

  function gatherSiteSettingsPayload() {
    return {
      festivalTheme: themeSelect?.value || 'default',
      hero: gatherHeroData(),
      installs: gatherInstallData()
    };
  }

  function renderSiteSettingsForm(settings) {
    if (!siteSettingsForm) return;
    currentSiteSettings = settings || {};
    if (themeSelect) {
      const theme = settings?.festivalTheme || 'default';
      themeSelect.value = theme;
    }
    populateHeroFields(settings?.hero || {});
    renderInstallRows(settings?.installs || []);
  }

  async function initAdminControls() {
    if (!siteSettingsForm) return;

    if (!adminControlsInitialised) {
      adminControlsInitialised = true;
      addInstallButton?.addEventListener('click', () => {
        createInstallRow();
      });

      siteSettingsForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        toggleSiteSettingsDisabled(true);
        showSettingsFeedback('Saving design updates…', 'info');
        try {
          const payload = gatherSiteSettingsPayload();
          const response = await request('/api/admin/site-settings', {
            method: 'PUT',
            body: payload
          });
          const updated = response.settings || payload;
          renderSiteSettingsForm(updated);
          showSettingsFeedback('Design updates saved successfully.', 'success');
        } catch (error) {
          if (error.status === 401) {
            removeSession();
            redirectToLogin();
            return;
          }
          const message = error.message || 'Unable to save updates.';
          showSettingsFeedback(message, 'error');
        } finally {
          toggleSiteSettingsDisabled(false);
        }
      });
    }

    toggleSiteSettingsDisabled(true);
    showSettingsFeedback('Loading design settings…', 'info');

    try {
      const response = await request('/api/admin/site-settings');
      const settings = response.settings || {};
      renderSiteSettingsForm(settings);
      showSettingsFeedback('Design settings ready to edit.', 'success');
    } catch (error) {
      const message = error.isNetworkError
        ? 'API offline. Start the server to edit décor controls.'
        : error.message || 'Unable to load design settings.';
      showSettingsFeedback(message, 'error');
    } finally {
      toggleSiteSettingsDisabled(false);
    }
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
    if (role === 'admin' && siteSettingsForm) {
      toggleSiteSettingsDisabled(true);
      showSettingsFeedback('Start the API server to load editable site décor controls.', 'error');
    }
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
      if (role === 'admin') {
        await initAdminControls();
      }
    } catch (error) {
      if (error.status === 401) {
        removeSession();
        redirectToLogin();
      } else if (error.isNetworkError) {
        useDemoDashboard('API offline detected. Showing cached demo insights.');
        if (role === 'admin' && siteSettingsForm) {
          toggleSiteSettingsDisabled(true);
          showSettingsFeedback('API offline. Start the server to edit décor controls.', 'error');
        }
      } else {
        showStatus(error.message || 'Unable to load dashboard data.', 'error');
      }
    }
  })();
})();
