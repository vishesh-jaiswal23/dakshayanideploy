(function () {
  const demoData = window.DAKSHAYANI_PORTAL_DEMO || {};
  const users = Array.isArray(demoData.users) ? demoData.users : [];
  const loginForm = document.querySelector('[data-login-form]');
  const loginFeedback = document.querySelector('[data-login-feedback]');
  const fillDemoButton = document.querySelector('[data-fill-demo]');
  const sessionKey = 'dakshayaniPortalSession';
  const redirects = {
    admin: 'admin-dashboard.html',
    customer: 'customer-dashboard.html',
    employee: 'employee-dashboard.html',
    installer: 'installer-dashboard.html',
    referrer: 'referrer-dashboard.html'
  };

  function setFeedback(type, message) {
    if (!loginFeedback) return;
    loginFeedback.textContent = message;
    loginFeedback.className = `feedback ${type}`;
  }

  function clearFeedback() {
    if (!loginFeedback) return;
    loginFeedback.textContent = '';
    loginFeedback.className = 'feedback';
  }

  function normalise(value) {
    return String(value || '').trim().toLowerCase();
  }

  function findUser(email, password, role) {
    return users.find(
      (user) =>
        normalise(user.email) === normalise(email) &&
        String(user.password) === String(password) &&
        (!role || user.role === role)
    );
  }

  function rememberSession(user) {
    const session = {
      email: user.email,
      role: user.role,
      name: user.name,
      phone: user.phone,
      city: user.city,
      id: user.id,
      createdAt: Date.now()
    };
    localStorage.setItem(sessionKey, JSON.stringify(session));
    return session;
  }

  function redirectToDashboard(role) {
    const target = redirects[role] || 'login.html';
    window.location.href = target;
  }

  if (fillDemoButton && loginForm) {
    fillDemoButton.addEventListener('click', () => {
      const email = loginForm.querySelector('#login-email');
      const password = loginForm.querySelector('#login-password');
      const role = loginForm.querySelector('#login-role');
      if (email) email.value = 'admin@dakshayani.in';
      if (password) password.value = 'Admin@123';
      if (role) role.value = 'admin';
      clearFeedback();
    });
  }

  if (loginForm) {
    loginForm.addEventListener('submit', (event) => {
      event.preventDefault();
      clearFeedback();

      const form = new FormData(loginForm);
      const email = form.get('email');
      const password = form.get('password');
      const role = form.get('role');

      if (!email || !password || !role) {
        setFeedback('error', 'Please fill every field before signing in.');
        return;
      }

      const user = findUser(email, password, role);
      if (!user) {
        setFeedback(
          'error',
          'Those details do not match any demo account. Copy the email, password, and role exactly as shown in the table.'
        );
        return;
      }

      rememberSession(user);
      setFeedback('success', `Welcome back ${user.name}! Redirecting to the ${role} dashboard...`);
      setTimeout(() => redirectToDashboard(role), 350);
    });
  }

  // Show a friendly message if the user came back from logout
  const params = new URLSearchParams(window.location.search);
  if (params.get('loggedOut') === '1') {
    setFeedback('success', 'You have signed out safely. Log in again using one of the demo accounts.');
  }
})();
