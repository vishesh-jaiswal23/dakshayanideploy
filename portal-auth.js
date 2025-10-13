(function () {
  const API_BASE = window.PORTAL_API_BASE || '';
  const sessionKey = 'dakshayaniPortalSession';
  const demoData = window.DAKSHAYANI_PORTAL_DEMO || {};
  const demoUsers = Array.isArray(demoData.users) ? demoData.users : [];

  const loginForm = document.querySelector('[data-login-form]');
  const signupForm = document.querySelector('[data-signup-form]');
  const loginFeedback = document.querySelector('[data-login-feedback]');
  const signupFeedback = document.querySelector('[data-signup-feedback]');
  const fillDemoButton = document.querySelector('[data-fill-demo]');

  const redirects = {
    admin: 'admin-dashboard.html',
    customer: 'customer-dashboard.html',
    employee: 'employee-dashboard.html',
    installer: 'installer-dashboard.html',
    referrer: 'referrer-dashboard.html'
  };

  function resolveApi(path) {
    if (!API_BASE) {
      return path;
    }
    try {
      return new URL(path, API_BASE).toString();
    } catch (error) {
      console.warn('Failed to resolve API path, using raw path.', error);
      return path;
    }
  }

  async function request(path, options = {}) {
    const url = resolveApi(path);
    const config = { method: 'GET', headers: {}, credentials: 'same-origin', ...options };
    config.method = (config.method || 'GET').toUpperCase();
    config.headers = { ...config.headers };

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
      const error = new Error('Unable to reach the Dakshayani portal API. Please check your connection or start the server.');
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

  function setFeedback(target, type, message) {
    if (!target) return;
    if (!message) {
      target.textContent = '';
      target.className = 'feedback';
      return;
    }
    const tone = type ? ` ${type}` : '';
    target.textContent = message;
    target.className = `feedback${tone}`;
  }

  function clearFeedback(target) {
    setFeedback(target, null, '');
  }

  function toggleFormDisabled(form, disabled) {
    if (!form) return;
    form.querySelectorAll('input, select, button').forEach((element) => {
      element.disabled = disabled;
    });
  }

  function rememberSession(user, token, options = {}) {
    if (!user || !user.email) {
      return null;
    }

    const session = {
      token: token || null,
      user: {
        id: user.id || '',
        name: user.name || 'Portal user',
        email: user.email,
        role: user.role,
        phone: user.phone || '',
        city: user.city || ''
      },
      createdAt: Date.now(),
      isDemo: Boolean(options.isDemo)
    };

    try {
      localStorage.setItem(sessionKey, JSON.stringify(session));
    } catch (error) {
      console.warn('Unable to persist session to localStorage', error);
    }

    return session;
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

  function redirectToDashboard(role) {
    const target = redirects[role] || 'login.html';
    window.location.href = target;
  }

  function normalise(value) {
    return String(value || '').trim().toLowerCase();
  }

  function findDemoUser(email, password, role) {
    return demoUsers.find((user) => {
      return (
        normalise(user.email) === normalise(email) &&
        String(user.password) === String(password) &&
        (!role || user.role === role)
      );
    }) || null;
  }

  async function loginViaApi(email, password, role) {
    const data = await request('/api/login', {
      method: 'POST',
      body: { email, password }
    });

    if (!data || !data.user) {
      throw new Error('Unexpected response from the portal API.');
    }

    if (role && data.user.role !== role) {
      throw new Error(`This account belongs to the ${data.user.role} portal. Please select the matching role.`);
    }

    const session = rememberSession(data.user, data.token);
    return session ? session.user : data.user;
  }

  function loginWithDemo(email, password, role) {
    const user = findDemoUser(email, password, role);
    if (!user) {
      return null;
    }
    const session = rememberSession(user, null, { isDemo: true });
    return session ? session.user : user;
  }

  async function handleLogin(event) {
    event.preventDefault();
    if (!loginForm) return;

    const formData = new FormData(loginForm);
    const email = formData.get('email');
    const password = formData.get('password');
    const role = formData.get('role');

    if (!email || !password || !role) {
      setFeedback(loginFeedback, 'error', 'Please fill every field before signing in.');
      return;
    }

    toggleFormDisabled(loginForm, true);
    setFeedback(loginFeedback, 'info', 'Signing you in…');

    try {
      const user = await loginViaApi(email, password, role);
      setFeedback(loginFeedback, 'success', `Welcome back ${user.name || ''}! Redirecting to the ${user.role} dashboard…`);
      setTimeout(() => redirectToDashboard(user.role), 450);
    } catch (error) {
      if (error.isNetworkError) {
        const user = loginWithDemo(email, password, role);
        if (user) {
          setFeedback(
            loginFeedback,
            'success',
            `Offline mode: welcome ${user.name || ''}! Redirecting to the ${user.role} dashboard…`
          );
          setTimeout(() => redirectToDashboard(user.role), 500);
        } else {
          setFeedback(
            loginFeedback,
            'error',
            'Portal API is unreachable. Use the demo credentials listed below or try again when connectivity returns.'
          );
        }
      } else {
        setFeedback(loginFeedback, 'error', error.message || 'Unable to sign in right now.');
      }
    } finally {
      toggleFormDisabled(loginForm, false);
    }
  }

  async function handleSignup(event) {
    event.preventDefault();
    if (!signupForm) return;

    const formData = new FormData(signupForm);
    const payload = {
      name: formData.get('name'),
      email: formData.get('email'),
      password: formData.get('password'),
      phone: formData.get('phone') || '',
      city: formData.get('city') || '',
      role: formData.get('role') || 'referrer'
    };

    if (!payload.name || !payload.email || !payload.password || !payload.role) {
      setFeedback(signupFeedback, 'error', 'Please complete every required field to continue.');
      return;
    }

    toggleFormDisabled(signupForm, true);
    setFeedback(signupFeedback, 'info', 'Creating your portal account…');

    try {
      const data = await request('/api/signup', {
        method: 'POST',
        body: payload
      });

      if (!data || !data.user) {
        throw new Error('Unexpected response from the portal API.');
      }

      const session = rememberSession(data.user, data.token);
      setFeedback(
        signupFeedback,
        'success',
        `Welcome aboard ${session?.user?.name || data.user.name}! Redirecting to the ${data.user.role} dashboard…`
      );
      signupForm.reset();
      setTimeout(() => redirectToDashboard(data.user.role), 650);
    } catch (error) {
      if (error.status === 409) {
        setFeedback(signupFeedback, 'error', 'That email already has an account. Try logging in instead.');
      } else if (error.isNetworkError) {
        setFeedback(
          signupFeedback,
          'error',
          'The signup service is offline right now. Please retry in a moment or email connect@dakshayani.co.in.'
        );
      } else {
        setFeedback(signupFeedback, 'error', error.message || 'Unable to create your account right now.');
      }
    } finally {
      toggleFormDisabled(signupForm, false);
    }
  }

  if (fillDemoButton && loginForm) {
    fillDemoButton.addEventListener('click', () => {
      const email = loginForm.querySelector('#login-email');
      const password = loginForm.querySelector('#login-password');
      const role = loginForm.querySelector('#login-role');
      if (email) email.value = 'admin@dakshayani.in';
      if (password) password.value = 'Admin@123';
      if (role) role.value = 'admin';
      clearFeedback(loginFeedback);
    });
  }

  if (loginForm) {
    loginForm.addEventListener('submit', handleLogin);
    loginForm.addEventListener('input', () => clearFeedback(loginFeedback));
  }

  if (signupForm) {
    signupForm.addEventListener('submit', handleSignup);
    signupForm.addEventListener('input', () => clearFeedback(signupFeedback));
  }

  const params = new URLSearchParams(window.location.search);
  if (params.get('loggedOut') === '1') {
    setFeedback(loginFeedback, 'success', 'You have signed out safely. Log in again using one of the accounts below.');
  } else {
    const session = parseSession();
    if (session?.user?.email) {
      setFeedback(
        loginFeedback,
        'info',
        `You are currently signed in as ${session.user.email}. Submit the form to switch accounts.`
      );
    }
  }
})();
