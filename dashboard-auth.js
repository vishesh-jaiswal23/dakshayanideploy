(function () {
  const API_BASE = window.PORTAL_API_BASE || '';
  const sessionKey = 'dakshayaniPortalSession';
  const demoData = window.DAKSHAYANI_PORTAL_DEMO || {};
  const dashboards = demoData.dashboards || {};
  const demoUsers = Array.isArray(demoData.users) ? demoData.users : [];
  const role = document.body?.dataset?.role;

  const roleLabels = {
    admin: 'Administrator',
    customer: 'Customer',
    employee: 'Employee',
    installer: 'Installer',
    referrer: 'Referral partner'
  };

  const roleOptions = Object.entries(roleLabels).map(([value, label]) => ({ value, label }));

  const statusLabels = {
    active: 'Active',
    suspended: 'Suspended'
  };

  const statusOptions = Object.entries(statusLabels).map(([value, label]) => ({ value, label }));

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

  const userAdminPanel = document.querySelector('[data-user-admin-panel]');
  const userListContainer = userAdminPanel?.querySelector('[data-user-list]');
  const userPanelFeedback = userAdminPanel?.querySelector('[data-user-panel-feedback]');
  const userStatsContainer = userAdminPanel?.querySelector('[data-user-stats]');
  const userTotals = {
    total: userStatsContainer?.querySelector('[data-user-total]'),
    admin: userStatsContainer?.querySelector('[data-user-total-admin]'),
    customer: userStatsContainer?.querySelector('[data-user-total-customer]'),
    employee: userStatsContainer?.querySelector('[data-user-total-employee]'),
    installer: userStatsContainer?.querySelector('[data-user-total-installer]'),
    referrer: userStatsContainer?.querySelector('[data-user-total-referrer]')
  };
  const userFilterRole = userAdminPanel?.querySelector('[data-user-filter-role]');
  const userSearchInput = userAdminPanel?.querySelector('[data-user-search]');
  const userRefreshButton = userAdminPanel?.querySelector('[data-user-refresh]');
  const userCreateForm = userAdminPanel?.querySelector('[data-user-create-form]');
  const userCreateFeedback = userAdminPanel?.querySelector('[data-user-create-feedback]');

  const blogAdminPanel = document.querySelector('[data-blog-admin-panel]');
  const blogListContainer = blogAdminPanel?.querySelector('[data-blog-post-list]');
  const blogFeedback = blogAdminPanel?.querySelector('[data-blog-feedback]');
  const blogPermissionMessage = blogAdminPanel?.querySelector('[data-blog-permission]');
  const blogForm = blogAdminPanel?.querySelector('[data-blog-form]');
  const blogFormFeedback = blogAdminPanel?.querySelector('[data-blog-form-feedback]');
  const blogCreateButton = blogAdminPanel?.querySelector('[data-blog-create]');
  const blogRefreshButton = blogAdminPanel?.querySelector('[data-blog-refresh]');
  const blogDeleteButton = blogAdminPanel?.querySelector('[data-blog-delete]');
  const blogIdField = blogForm?.querySelector('[data-blog-id]');
  const blogFields = blogForm ? Array.from(blogForm.querySelectorAll('[data-blog-field]')) : [];

  const dateFormatter = new Intl.DateTimeFormat('en-IN', { dateStyle: 'medium', timeStyle: 'short' });

  let siteSettingsInitialised = false;
  let userAdminInitialised = false;
  let currentSiteSettings = null;
  let cachedUsers = [];
  let blogAdminInitialised = false;
  let blogPosts = [];
  let selectedBlogId = null;
  let blogEditingEnabled = false;

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

  function setFormFeedback(target, tone, message) {
    if (!target) return;
    target.classList.add('form-feedback');
    target.textContent = message || '';
    if (tone) {
      target.dataset.tone = tone;
    } else {
      delete target.dataset.tone;
    }
  }

  function clearFormFeedback(target) {
    setFormFeedback(target, null, '');
  }

  function setBlogPanelFeedback(target, tone, message) {
    if (!target) return;
    setFormFeedback(target, tone, message);
  }

  function ensureBlogPermission() {
    blogEditingEnabled = Boolean(session?.user?.superAdmin);
    if (blogPermissionMessage) {
      if (blogEditingEnabled) {
        blogPermissionMessage.hidden = true;
        blogPermissionMessage.textContent = '';
        delete blogPermissionMessage.dataset.tone;
      } else {
        blogPermissionMessage.hidden = false;
        blogPermissionMessage.dataset.tone = 'error';
        blogPermissionMessage.textContent = 'Only the head admin (Vishesh) can publish or edit posts. Sign in with your super admin login to continue.';
      }
    }
    if (blogCreateButton) {
      blogCreateButton.disabled = !blogEditingEnabled;
    }
    toggleBlogForm(!blogEditingEnabled);
  }

  function toggleBlogForm(disabled) {
    if (!blogForm) return;
    const elements = blogForm.querySelectorAll('input, textarea, select, button[type="submit"]');
    elements.forEach((element) => {
      element.disabled = disabled;
    });
    if (blogDeleteButton) {
      blogDeleteButton.disabled = disabled || !selectedBlogId;
    }
  }

  function clearBlogForm() {
    if (!blogForm) return;
    blogForm.reset();
    if (blogIdField) {
      blogIdField.value = '';
    }
    blogFields.forEach((field) => {
      if (field.tagName === 'SELECT') {
        field.value = field.options[0]?.value || '';
      } else {
        field.value = '';
      }
    });
    if (blogDeleteButton) {
      blogDeleteButton.disabled = true;
    }
  }

  function populateBlogForm(post) {
    if (!blogForm) return;
    if (blogIdField) {
      blogIdField.value = post?.id || '';
    }
    blogFields.forEach((field) => {
      const key = field.dataset.blogField;
      if (!key) return;
      let value = '';
      if (key === 'tags') {
        value = Array.isArray(post?.tags) ? post.tags.join(', ') : '';
      } else if (key === 'readTimeMinutes') {
        value = post?.readTimeMinutes || '';
      } else {
        value = post?.[key] ?? '';
      }
      field.value = value;
    });
    if (blogDeleteButton) {
      blogDeleteButton.disabled = !blogEditingEnabled || !post?.id;
    }
  }

  function highlightBlogSelection() {
    if (!blogListContainer) return;
    blogListContainer.querySelectorAll('.blog-post-item').forEach((item) => {
      item.dataset.selected = item.dataset.postId === selectedBlogId ? 'true' : 'false';
    });
  }

  function formatBlogListMeta(post) {
    const statusLabel = post.status === 'published' ? 'Published' : 'Draft';
    const timestamp = post.updatedAt || post.publishedAt || post.createdAt || null;
    const when = timestamp ? formatDateTime(timestamp) : '—';
    return `${statusLabel}${when && when !== '—' ? ` · ${when}` : ''}`;
  }

  function renderBlogList(posts) {
    if (!blogListContainer) return;
    blogListContainer.innerHTML = '';
    if (!posts || !posts.length) {
      const message = blogEditingEnabled
        ? 'No posts yet. Click New post to share your first update.'
        : 'No posts available.';
      const paragraph = document.createElement('p');
      paragraph.className = 'empty';
      paragraph.textContent = message;
      blogListContainer.appendChild(paragraph);
      return;
    }

    posts.forEach((post) => {
      const item = document.createElement('div');
      item.className = 'blog-post-item';
      item.dataset.postId = post.id || '';
      if (post.id === selectedBlogId) {
        item.dataset.selected = 'true';
      }
      item.setAttribute('role', 'button');
      item.tabIndex = 0;
      item.innerHTML = `
        <h4>${post.title || 'Untitled post'}</h4>
        <span>${formatBlogListMeta(post)}</span>
      `;
      item.addEventListener('click', () => selectBlogPost(post.id));
      item.addEventListener('keypress', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          selectBlogPost(post.id);
        }
      });
      blogListContainer.appendChild(item);
    });
  }

  function selectBlogPost(postId) {
    if (!blogAdminPanel) return;
    const post = blogPosts.find((entry) => entry.id === postId);
    if (!post) {
      selectedBlogId = null;
      clearBlogForm();
      highlightBlogSelection();
      return;
    }
    selectedBlogId = post.id;
    populateBlogForm(post);
    highlightBlogSelection();
  }

  async function loadBlogPosts() {
    if (!blogAdminPanel) return;
    if (session?.isDemo || !session?.token) {
      useDemoBlogAdmin('Offline demo mode active. Start the API to manage blog posts.');
      return;
    }

    setBlogPanelFeedback(blogFeedback, 'info', 'Loading blog posts…');
    try {
      const data = await request('/api/blog/posts');
      const posts = Array.isArray(data?.posts) ? data.posts : [];
      blogPosts = posts;
      if (!selectedBlogId || !blogPosts.some((post) => post.id === selectedBlogId)) {
        selectedBlogId = blogPosts[0]?.id || null;
      }
      renderBlogList(blogPosts);
      if (selectedBlogId) {
        const current = blogPosts.find((post) => post.id === selectedBlogId);
        populateBlogForm(current);
      } else {
        clearBlogForm();
      }
      highlightBlogSelection();
      setBlogPanelFeedback(blogFeedback, null, '');
    } catch (error) {
      console.error('Unable to load blog posts', error);
      setBlogPanelFeedback(blogFeedback, 'error', error.message || 'Unable to load blog posts.');
      blogPosts = [];
      renderBlogList(blogPosts);
      clearBlogForm();
    }
  }

  function handleBlogCreate() {
    if (!blogEditingEnabled) {
      setBlogPanelFeedback(blogFeedback, 'error', 'Sign in as the head admin to create new posts.');
      return;
    }
    selectedBlogId = null;
    clearBlogForm();
    setBlogPanelFeedback(blogFeedback, null, 'Draft a new post and click Save changes to publish.');
    setBlogPanelFeedback(blogFormFeedback, null, '');
    highlightBlogSelection();
    if (blogDeleteButton) {
      blogDeleteButton.disabled = true;
    }
  }

  async function handleBlogSubmit(event) {
    if (blogForm) {
      event.preventDefault();
    }
    if (!blogEditingEnabled || !blogForm) {
      return;
    }

    const formData = new FormData(blogForm);
    const payload = {
      title: String(formData.get('title') || '').trim(),
      slug: String(formData.get('slug') || '').trim(),
      status: String(formData.get('status') || 'draft'),
      readTimeMinutes: formData.get('readTimeMinutes'),
      tags: String(formData.get('tags') || '').split(',').map((tag) => tag.trim()).filter(Boolean),
      heroImage: String(formData.get('heroImage') || '').trim(),
      excerpt: String(formData.get('excerpt') || '').trim(),
      content: String(formData.get('content') || '').trim(),
    };

    if (!payload.title || !payload.content) {
      setBlogPanelFeedback(blogFormFeedback, 'error', 'Title and full content are required.');
      return;
    }

    const readTime = Number.parseInt(payload.readTimeMinutes, 10);
    if (!Number.isFinite(readTime) || readTime <= 0) {
      delete payload.readTimeMinutes;
    } else {
      payload.readTimeMinutes = readTime;
    }

    if (!payload.tags.length) {
      delete payload.tags;
    }

    toggleBlogForm(true);
    setBlogPanelFeedback(blogFormFeedback, 'info', 'Saving post…');

    try {
      const identifier = blogIdField?.value ? String(blogIdField.value) : null;
      const endpoint = identifier ? `/api/blog/posts/${encodeURIComponent(identifier)}` : '/api/blog/posts';
      const method = identifier ? 'PUT' : 'POST';
      const response = await request(endpoint, { method, body: payload });
      const post = response?.post;
      if (!post) {
        throw new Error('Unexpected response from the portal API.');
      }
      await loadBlogPosts();
      selectedBlogId = post.id;
      populateBlogForm(post);
      highlightBlogSelection();
      setBlogPanelFeedback(blogFormFeedback, 'success', identifier ? 'Post updated successfully.' : 'Post created successfully.');
      setBlogPanelFeedback(blogFeedback, 'success', 'Blog post saved.');
    } catch (error) {
      console.error('Unable to save blog post', error);
      if (error.status === 403) {
        setBlogPanelFeedback(blogFormFeedback, 'error', 'Only the head admin can modify blog posts.');
      } else {
        setBlogPanelFeedback(blogFormFeedback, 'error', error.message || 'Unable to save the blog post.');
      }
    } finally {
      toggleBlogForm(!blogEditingEnabled);
    }
  }

  async function handleBlogDelete() {
    if (!blogEditingEnabled || !blogDeleteButton) {
      return;
    }
    const identifier = blogIdField?.value ? String(blogIdField.value) : null;
    if (!identifier) {
      setBlogPanelFeedback(blogFormFeedback, 'error', 'Select a post before attempting to archive it.');
      return;
    }
    if (!window.confirm('Archive this post? It will disappear from the public blog.')) {
      return;
    }

    toggleBlogForm(true);
    setBlogPanelFeedback(blogFormFeedback, 'info', 'Archiving post…');

    try {
      await request(`/api/blog/posts/${encodeURIComponent(identifier)}`, { method: 'DELETE' });
      selectedBlogId = null;
      await loadBlogPosts();
      clearBlogForm();
      setBlogPanelFeedback(blogFormFeedback, 'success', 'Post archived.');
      setBlogPanelFeedback(blogFeedback, 'success', 'Blog post archived.');
    } catch (error) {
      console.error('Unable to delete blog post', error);
      if (error.status === 403) {
        setBlogPanelFeedback(blogFormFeedback, 'error', 'Only the head admin can archive blog posts.');
      } else {
        setBlogPanelFeedback(blogFormFeedback, 'error', error.message || 'Unable to archive the blog post.');
      }
    } finally {
      toggleBlogForm(!blogEditingEnabled);
    }
  }

  function useDemoBlogAdmin(message) {
    if (!blogAdminPanel) return;
    blogPosts = [];
    renderBlogList(blogPosts);
    clearBlogForm();
    blogEditingEnabled = false;
    ensureBlogPermission();
    setBlogPanelFeedback(blogFeedback, message ? 'error' : null, message || '');
  }

  function formatDateTime(value) {
    if (!value) {
      return '—';
    }
    try {
      return dateFormatter.format(new Date(value));
    } catch (error) {
      return '—';
    }
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

  async function initSiteSettingsControls() {
    if (!siteSettingsForm) return;

    if (!siteSettingsInitialised) {
      siteSettingsInitialised = true;
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

  function setUserPanelFeedback(message, tone) {
    if (!userPanelFeedback) return;
    setFormFeedback(userPanelFeedback, tone, message || '');
  }

  function toggleUserCreateForm(disabled) {
    if (!userCreateForm) return;
    userCreateForm.querySelectorAll('input, select, button').forEach((element) => {
      element.disabled = disabled;
    });
  }

  function toggleUserCard(form, disabled) {
    if (!form) return;
    form.querySelectorAll('input, select, button').forEach((element) => {
      element.disabled = disabled;
    });
  }

  function computeLocalUserStats(users) {
    const stats = { total: 0, roles: {} };
    if (!Array.isArray(users)) {
      return stats;
    }
    stats.total = users.length;
    users.forEach((user) => {
      const role = user.role || 'referrer';
      stats.roles[role] = (stats.roles[role] || 0) + 1;
    });
    return stats;
  }

  function renderUserStats(stats) {
    if (!userStatsContainer) return;
    const totals = {
      total: stats?.total || 0,
      admin: stats?.roles?.admin || 0,
      customer: stats?.roles?.customer || 0,
      employee: stats?.roles?.employee || 0,
      installer: stats?.roles?.installer || 0,
      referrer: stats?.roles?.referrer || 0
    };

    Object.entries(totals).forEach(([key, value]) => {
      const node = userTotals[key];
      if (node) {
        node.textContent = value;
      }
    });
  }

  function createTextInputGroup({ label, name, value = '', placeholder = '', type = 'text' }) {
    const group = document.createElement('div');
    group.className = 'form-group';
    const labelEl = document.createElement('label');
    labelEl.textContent = label;
    group.appendChild(labelEl);
    const input = document.createElement('input');
    input.type = type;
    input.name = name;
    input.value = value || '';
    if (placeholder) {
      input.placeholder = placeholder;
    }
    group.appendChild(input);
    return { group, input };
  }

  function createSelectGroup({ label, name, value, options }) {
    const group = document.createElement('div');
    group.className = 'form-group';
    const labelEl = document.createElement('label');
    labelEl.textContent = label;
    group.appendChild(labelEl);
    const select = document.createElement('select');
    select.name = name;
    options.forEach((option) => {
      const opt = document.createElement('option');
      opt.value = option.value;
      opt.textContent = option.label;
      if (option.value === value) {
        opt.selected = true;
      }
      select.appendChild(opt);
    });
    group.appendChild(select);
    return { group, select };
  }

  function upsertCachedUser(user) {
    if (!user || !user.id) return;
    const index = cachedUsers.findIndex((item) => item.id === user.id);
    if (index === -1) {
      cachedUsers.push(user);
    } else {
      cachedUsers[index] = { ...cachedUsers[index], ...user };
    }
  }

  function roleLabel(value) {
    return roleLabels[value] || value || 'Portal user';
  }

  function statusLabel(value) {
    return statusLabels[value] || value || 'Active';
  }

  function createUserCard(user) {
    const form = document.createElement('form');
    form.className = 'user-card';
    form.dataset.userId = user.id || '';
    form.autocomplete = 'off';

    const header = document.createElement('header');
    const title = document.createElement('h3');
    title.textContent = user.name || 'Portal user';
    header.appendChild(title);

    const badges = document.createElement('div');
    const roleBadge = document.createElement('span');
    roleBadge.className = 'badge';
    roleBadge.dataset.role = user.role || 'referrer';
    roleBadge.textContent = roleLabel(user.role);
    badges.appendChild(roleBadge);

    const statusBadge = document.createElement('span');
    statusBadge.className = 'badge';
    statusBadge.dataset.status = user.status || 'active';
    statusBadge.textContent = statusLabel(user.status);
    badges.appendChild(statusBadge);

    header.appendChild(badges);
    form.appendChild(header);

    const meta = document.createElement('div');
    meta.className = 'user-meta';
    const emailLine = document.createElement('span');
    emailLine.textContent = user.email || '—';
    meta.appendChild(emailLine);
    const createdLine = document.createElement('span');
    createdLine.textContent = user.createdAt ? `Joined ${formatDateTime(user.createdAt)}` : 'Created from demo data';
    meta.appendChild(createdLine);
    form.appendChild(meta);

    const fields = document.createElement('div');
    fields.className = 'user-card-fields';

    const nameField = createTextInputGroup({
      label: 'Full name',
      name: 'name',
      value: user.name || '',
      placeholder: 'Portal user'
    });
    fields.appendChild(nameField.group);

    const phoneField = createTextInputGroup({
      label: 'Phone',
      name: 'phone',
      value: user.phone || '',
      placeholder: '+91 90000 00000',
      type: 'tel'
    });
    fields.appendChild(phoneField.group);

    const cityField = createTextInputGroup({
      label: 'City',
      name: 'city',
      value: user.city || '',
      placeholder: 'Ranchi'
    });
    fields.appendChild(cityField.group);

    const roleField = createSelectGroup({
      label: 'Role',
      name: 'role',
      value: user.role || 'referrer',
      options: roleOptions
    });
    fields.appendChild(roleField.group);

    const statusField = createSelectGroup({
      label: 'Status',
      name: 'status',
      value: user.status || 'active',
      options: statusOptions
    });
    fields.appendChild(statusField.group);

    form.appendChild(fields);

    const feedback = document.createElement('p');
    feedback.className = 'form-feedback';
    form.appendChild(feedback);

    const actions = document.createElement('div');
    actions.className = 'user-card-actions';

    const saveButton = document.createElement('button');
    saveButton.type = 'submit';
    saveButton.className = 'btn-secondary';
    saveButton.textContent = 'Save changes';
    actions.appendChild(saveButton);

    const resetButton = document.createElement('button');
    resetButton.type = 'button';
    resetButton.className = 'btn-link';
    resetButton.textContent = 'Reset password';
    resetButton.dataset.action = 'reset';
    actions.appendChild(resetButton);

    const deleteButton = document.createElement('button');
    deleteButton.type = 'button';
    deleteButton.className = 'btn-ghost';
    deleteButton.textContent = 'Delete';
    deleteButton.dataset.action = 'delete';
    actions.appendChild(deleteButton);

    form.appendChild(actions);

    form.addEventListener('submit', handleUserUpdate);
    resetButton.addEventListener('click', () => handleUserResetPassword(form));
    deleteButton.addEventListener('click', () => handleUserDelete(form));

    return form;
  }

  function applyUserFilters() {
    const roleFilterValue = userFilterRole?.value || '';
    const query = userSearchInput?.value?.trim().toLowerCase() || '';
    if (!Array.isArray(cachedUsers)) {
      return [];
    }
    return cachedUsers.filter((user) => {
      const matchesRole = !roleFilterValue || user.role === roleFilterValue;
      if (!matchesRole) return false;
      if (!query) return true;
      const haystack = [user.name, user.email, user.phone, user.city]
        .filter(Boolean)
        .map((value) => String(value).toLowerCase());
      return haystack.some((value) => value.includes(query));
    });
  }

  function renderUserList(users, options = {}) {
    if (!userListContainer) return;
    const { disabled = false, reason = '' } = options;
    userListContainer.innerHTML = '';

    if (!users || !users.length) {
      const empty = document.createElement('p');
      empty.className = 'empty';
      empty.textContent = 'No accounts match your filters yet.';
      userListContainer.appendChild(empty);
      if (disabled && reason) {
        const note = document.createElement('p');
        note.className = 'empty';
        note.textContent = reason;
        userListContainer.appendChild(note);
      }
      return;
    }

    users.forEach((user) => {
      const form = createUserCard(user);
      userListContainer.appendChild(form);
      if (disabled) {
        toggleUserCard(form, true);
        const feedback = form.querySelector('.form-feedback');
        if (feedback && reason) {
          setFormFeedback(feedback, 'error', reason);
        }
      }
    });
  }

  async function loadUsers() {
    if (!userAdminPanel) return;
    let keepDisabled = false;
    setUserPanelFeedback('Loading accounts…', 'info');
    toggleUserCreateForm(true);
    if (userListContainer) {
      userListContainer.querySelectorAll('form.user-card').forEach((form) => toggleUserCard(form, true));
    }

    try {
      const response = await request('/api/admin/users');
      cachedUsers = Array.isArray(response.users) ? response.users : [];
      renderUserStats(response.stats || computeLocalUserStats(cachedUsers));
      renderUserList(applyUserFilters());
      setUserPanelFeedback(`Loaded ${cachedUsers.length} account${cachedUsers.length === 1 ? '' : 's'}.`, 'success');
    } catch (error) {
      if (error.status === 401) {
        removeSession();
        redirectToLogin();
        return;
      }
      if (error.isNetworkError) {
        keepDisabled = true;
        useDemoUserAdmin('API offline. Showing demo accounts — changes are disabled.');
      } else {
        setUserPanelFeedback(error.message || 'Unable to load user accounts.', 'error');
      }
    } finally {
      if (!keepDisabled) {
        toggleUserCreateForm(false);
        if (userListContainer) {
          userListContainer.querySelectorAll('form.user-card').forEach((form) => toggleUserCard(form, false));
        }
      }
    }
  }

  async function handleUserCreate(event) {
    event.preventDefault();
    if (!userCreateForm) return;

    const formData = new FormData(userCreateForm);
    const payload = {
      name: formData.get('name'),
      email: formData.get('email'),
      phone: formData.get('phone'),
      city: formData.get('city'),
      role: formData.get('role'),
      status: formData.get('status'),
      password: formData.get('password')
    };

    toggleUserCreateForm(true);
    setFormFeedback(userCreateFeedback, 'info', 'Creating account…');

    try {
      const response = await request('/api/admin/users', {
        method: 'POST',
        body: payload
      });
      setFormFeedback(userCreateFeedback, 'success', 'Account created. Share the temporary password securely.');
      userCreateForm.reset();
      const roleField = userCreateForm.querySelector('[name="role"]');
      const statusField = userCreateForm.querySelector('[name="status"]');
      if (roleField) roleField.value = 'referrer';
      if (statusField) statusField.value = 'active';
      if (response?.user) {
        upsertCachedUser(response.user);
      }
      await loadUsers();
    } catch (error) {
      if (error.status === 401) {
        removeSession();
        redirectToLogin();
        return;
      }
      setFormFeedback(userCreateFeedback, 'error', error.message || 'Unable to create this account.');
    } finally {
      toggleUserCreateForm(false);
    }
  }

  async function handleUserUpdate(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const userId = form?.dataset?.userId;
    if (!userId) return;

    const formData = new FormData(form);
    const payload = {
      name: formData.get('name'),
      phone: formData.get('phone'),
      city: formData.get('city'),
      role: formData.get('role'),
      status: formData.get('status')
    };

    const feedback = form.querySelector('.form-feedback');
    toggleUserCard(form, true);
    setFormFeedback(feedback, 'info', 'Saving changes…');

    let shouldReenable = true;

    try {
      const response = await request(`/api/admin/users/${encodeURIComponent(userId)}`, {
        method: 'PUT',
        body: payload
      });
      if (response?.user) {
        upsertCachedUser(response.user);
      }
      renderUserStats(response?.stats || computeLocalUserStats(cachedUsers));
      setFormFeedback(feedback, 'success', 'Account updated.');
      shouldReenable = false;
      await loadUsers();
    } catch (error) {
      if (error.status === 401) {
        removeSession();
        redirectToLogin();
        return;
      }
      setFormFeedback(feedback, 'error', error.message || 'Unable to update this account.');
    } finally {
      if (shouldReenable) {
        toggleUserCard(form, false);
      }
    }
  }

  async function handleUserDelete(form) {
    const userId = form?.dataset?.userId;
    if (!userId) return;
    const userName = form.querySelector('h3')?.textContent || 'this account';
    const confirmed = window.confirm(`Delete ${userName}? This action cannot be undone.`);
    if (!confirmed) return;

    const feedback = form.querySelector('.form-feedback');
    toggleUserCard(form, true);
    setFormFeedback(feedback, 'info', 'Removing account…');

    let shouldReenable = true;

    try {
      const response = await request(`/api/admin/users/${encodeURIComponent(userId)}`, {
        method: 'DELETE'
      });
      cachedUsers = cachedUsers.filter((user) => user.id !== userId);
      renderUserStats(response?.stats || computeLocalUserStats(cachedUsers));
      setUserPanelFeedback(`${userName} removed from the portal.`, 'success');
      renderUserList(applyUserFilters());
      shouldReenable = false;
    } catch (error) {
      if (error.status === 401) {
        removeSession();
        redirectToLogin();
        return;
      }
      setFormFeedback(feedback, 'error', error.message || 'Unable to delete this account.');
    } finally {
      if (shouldReenable) {
        toggleUserCard(form, false);
      }
    }
  }

  async function handleUserResetPassword(form) {
    const userId = form?.dataset?.userId;
    if (!userId) return;
    const password = window.prompt('Enter a new temporary password (minimum 8 characters):');
    if (password === null) {
      return;
    }
    if (!password || password.trim().length < 8) {
      window.alert('Password must be at least 8 characters long.');
      return;
    }

    const feedback = form.querySelector('.form-feedback');
    toggleUserCard(form, true);
    setFormFeedback(feedback, 'info', 'Resetting password…');

    try {
      await request(`/api/admin/users/${encodeURIComponent(userId)}/reset-password`, {
        method: 'POST',
        body: { password }
      });
      setFormFeedback(feedback, 'success', 'Password reset. Share the new password securely.');
      setUserPanelFeedback('Password reset successfully.', 'success');
    } catch (error) {
      if (error.status === 401) {
        removeSession();
        redirectToLogin();
        return;
      }
      setFormFeedback(feedback, 'error', error.message || 'Unable to reset the password.');
    } finally {
      toggleUserCard(form, false);
    }
  }

  function useDemoUserAdmin(message) {
    if (!userAdminPanel) return;
    cachedUsers = demoUsers.map((user) => ({
      id: user.id || `demo-${user.role}-${user.email}`,
      name: user.name,
      email: user.email,
      phone: user.phone || '',
      city: user.city || '',
      role: user.role || 'referrer',
      status: 'active',
      createdAt: null
    }));
    renderUserStats(computeLocalUserStats(cachedUsers));
    renderUserList(applyUserFilters(), {
      disabled: true,
      reason: 'Demo mode active. Start the API to manage real accounts.'
    });
    toggleUserCreateForm(true);
    if (message) {
      setUserPanelFeedback(message, 'error');
    }
  }

  async function initBlogAdminControls() {
    if (!blogAdminPanel) return;

    ensureBlogPermission();

    if (!blogAdminInitialised) {
      blogAdminInitialised = true;
      blogRefreshButton?.addEventListener('click', () => {
        loadBlogPosts();
      });
      blogCreateButton?.addEventListener('click', handleBlogCreate);
      blogDeleteButton?.addEventListener('click', handleBlogDelete);
      if (blogForm) {
        blogForm.addEventListener('submit', handleBlogSubmit);
        blogForm.addEventListener('input', () => {
          setBlogPanelFeedback(blogFormFeedback, null, '');
        });
      }
    }

    if (session?.isDemo || !session?.token) {
      useDemoBlogAdmin('Offline demo mode active. Start the API to manage blog posts.');
      return;
    }

    await loadBlogPosts();
  }

  async function initUserAdminControls() {
    if (!userAdminPanel) return;

    if (!userAdminInitialised) {
      userAdminInitialised = true;
      userFilterRole?.addEventListener('change', () => {
        renderUserList(applyUserFilters());
      });
      userSearchInput?.addEventListener('input', () => {
        renderUserList(applyUserFilters());
      });
      userRefreshButton?.addEventListener('click', () => {
        loadUsers();
      });
      if (userCreateForm) {
        userCreateForm.addEventListener('submit', handleUserCreate);
        userCreateForm.addEventListener('input', () => clearFormFeedback(userCreateFeedback));
      }
    }

    if (session?.isDemo || !session?.token) {
      useDemoUserAdmin('Offline demo mode active. Start the API to manage accounts.');
      return;
    }

    await loadUsers();
  }

  async function initAdminControls() {
    await Promise.all([initSiteSettingsControls(), initUserAdminControls(), initBlogAdminControls()]);
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
    if (role === 'admin') {
      useDemoUserAdmin('Offline demo mode active. Start the API server to manage accounts.');
      if (siteSettingsForm) {
        toggleSiteSettingsDisabled(true);
        showSettingsFeedback('Start the API server to load editable site décor controls.', 'error');
      }
      useDemoBlogAdmin('Offline demo mode active. Start the API server to manage blog posts.');
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
        if (role === 'admin') {
          useDemoUserAdmin('API offline. Showing demo accounts — changes are disabled.');
          useDemoBlogAdmin('API offline. Start the server to manage blog posts.');
          if (siteSettingsForm) {
            toggleSiteSettingsDisabled(true);
            showSettingsFeedback('API offline. Start the server to edit décor controls.', 'error');
          }
        }
      } else {
        showStatus(error.message || 'Unable to load dashboard data.', 'error');
      }
    }
  })();
})();
