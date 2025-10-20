(function () {
  const API_BASE = window.PORTAL_API_BASE || '';
  const sessionKey = 'dakshayaniPortalSession';
  const demoData = window.DAKSHAYANI_PORTAL_DEMO || {};
  const demoUsers = Array.isArray(demoData.users) ? demoData.users : [];
  const RECAPTCHA_SITE_KEY = window.DAKSHAYANI_RECAPTCHA_SITE_KEY || '';
  const GOOGLE_CLIENT_ID = window.DAKSHAYANI_GOOGLE_CLIENT_ID || '';
  const loginForm = document.querySelector('[data-login-form]');
  const signupForm = document.querySelector('[data-signup-form]');
  const loginFeedback = document.querySelector('[data-login-feedback]');
  const signupFeedback = document.querySelector('[data-signup-feedback]');
  const fillDemoButton = document.querySelector('[data-fill-demo]');
  const quickDemoButtons = document.querySelectorAll('[data-demo-role]');
  const passwordMeterBar = document.querySelector('[data-password-meter-bar]');
  const passwordMeterLabel = document.querySelector('[data-password-meter-label]');
  const signupPassword = document.getElementById('signup-password');
  const passwordToggleButtons = document.querySelectorAll('[data-password-toggle]');
  const loginEmailInput = document.getElementById('login-email');
  const loginPasswordInput = document.getElementById('login-password');
  const loginRoleSelect = document.getElementById('login-role');
  const demoHelper = document.querySelector('[data-demo-helper]');
  const defaultDemoHelperText = demoHelper ? demoHelper.textContent : '';
  const googleButton = document.getElementById('googleSignInButton');

  const redirects = {
    admin: 'admin-dashboard.html',
    customer: 'customer-dashboard.html',
    employee: 'employee-dashboard.html',
    installer: 'installer-dashboard.html',
    referrer: 'referrer-dashboard.html'
  };

  const roleLabels = {
    admin: 'Administrator',
    customer: 'Customer',
    employee: 'Employee',
    installer: 'Installer',
    referrer: 'Referral partner'
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

  function buildApiFallbacks(path) {
    if (API_BASE) {
      return [];
    }

    const questionIndex = typeof path === 'string' ? path.indexOf('?') : -1;
    const pathname = questionIndex === -1 ? path : path.slice(0, questionIndex);
    if (typeof pathname !== 'string' || !pathname.startsWith('/api/')) {
      return [];
    }

    const search = questionIndex === -1 ? '' : path.slice(questionIndex + 1);
    const originalParams = new URLSearchParams(search);
    const slug = pathname.slice(5).replace(/^\/+|\/+$/g, '');
    const candidates = [];

    const pushCandidate = (basePath, extraParams) => {
      const params = new URLSearchParams(originalParams);
      if (extraParams && typeof extraParams === 'object') {
        Object.entries(extraParams).forEach(([key, value]) => {
          if (value !== undefined && value !== null) {
            params.set(key, value);
          }
        });
      }
      const query = params.toString();
      const candidate = query ? `${basePath}?${query}` : basePath;
      if (!candidates.includes(candidate)) {
        candidates.push(candidate);
      }
    };

    const scriptMap = {
      '': 'index.php',
      login: 'login.php',
      signup: 'signup.php',
      me: 'me.php',
      logout: 'logout.php',
      'auth/google': 'auth.google.php',
    };

    if (Object.prototype.hasOwnProperty.call(scriptMap, slug)) {
      const script = scriptMap[slug];
      pushCandidate(`/portal/api/${script}`);
      pushCandidate(`/server/api/${script}`);
      return candidates;
    }

    if (slug.startsWith('admin/users')) {
      const remainder = slug.slice('admin/users'.length).replace(/^\/+/, '');
      const extra = remainder ? { path: remainder } : undefined;
      pushCandidate('/portal/api/admin.users.php', extra);
      pushCandidate('/server/api/admin.users.php', extra);
      return candidates;
    }

    if (slug.startsWith('dashboard/')) {
      const role = slug.slice('dashboard/'.length).split('/')[0] || '';
      if (role) {
        const script = `dashboard.${role}.php`;
        pushCandidate(`/portal/api/${script}`);
        pushCandidate(`/server/api/${script}`);
      }
      return candidates;
    }

    return candidates;
  }

  async function request(path, options = {}) {
    const config = { method: 'GET', headers: {}, credentials: 'same-origin', ...options };
    config.method = (config.method || 'GET').toUpperCase();
    config.headers = { ...config.headers };

    if (config.body && typeof config.body !== 'string') {
      config.body = JSON.stringify(config.body);
      if (!config.headers['Content-Type']) {
        config.headers['Content-Type'] = 'application/json';
      }
    }

    const attempts = [path, ...buildApiFallbacks(path)];
    let lastError = null;

    for (let index = 0; index < attempts.length; index += 1) {
      const attemptPath = attempts[index];
      const url = resolveApi(attemptPath);
      const attemptConfig = { ...config, headers: { ...config.headers } };

      let response;
      try {
        response = await fetch(url, attemptConfig);
      } catch (networkError) {
        lastError = networkError;
        continue;
      }

      let data = {};
      try {
        data = await response.json();
      } catch (parseError) {
        data = {};
      }

      if (response.ok) {
        return data;
      }

      if (response.status === 404 && index < attempts.length - 1) {
        lastError = new Error(`Request failed with status ${response.status}`);
        lastError.status = response.status;
        continue;
      }

      const error = new Error(data.error || `Request failed with status ${response.status}`);
      error.status = response.status;
      throw error;
    }

    if (lastError) {
      const networkError = new Error('Unable to reach the Dakshayani portal API. Please check your connection or start the server.');
      networkError.isNetworkError = true;
      throw networkError;
    }

    const fallbackError = new Error('Unable to reach the Dakshayani portal API. Please check your connection or start the server.');
    fallbackError.isNetworkError = true;
    throw fallbackError;
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

  function getRoleLabel(role) {
    if (!role) return '';
    return roleLabels[role] || role.charAt(0).toUpperCase() + role.slice(1);
  }

  function getDemoUserByRole(role) {
    return demoUsers.find((user) => user.role === role) || null;
  }

  function getPasswordToggleButton(targetId) {
    return Array.from(passwordToggleButtons || []).find((button) => button.dataset.passwordToggle === targetId) || null;
  }

  function setPasswordVisibilityState(input, button, visible, options = {}) {
    if (!input) {
      return;
    }
    input.type = visible ? 'text' : 'password';
    if (button) {
      button.textContent = visible ? 'Hide' : 'Show';
      button.setAttribute('aria-pressed', visible ? 'true' : 'false');
    }
    if (visible && options.focus) {
      input.focus();
      if (options.select) {
        input.select();
      }
    }
  }

  function togglePasswordVisibility(button) {
    if (!button) return;
    const targetId = button.dataset.passwordToggle;
    if (!targetId) return;
    const input = document.getElementById(targetId);
    if (!input) return;
    const shouldShow = input.type === 'password';
    setPasswordVisibilityState(input, button, shouldShow, { focus: true });
  }

  function setActiveDemoRole(role) {
    if (!quickDemoButtons || quickDemoButtons.length === 0) {
      return;
    }
    quickDemoButtons.forEach((button) => {
      const isActive = button.dataset.demoRole === role;
      button.classList.toggle('is-active', isActive);
      button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
  }

  function fillDemoCredentials(role, options = {}) {
    if (!role) {
      return null;
    }
    const user = getDemoUserByRole(role);
    if (!user) {
      setActiveDemoRole(null);
      if (demoHelper) {
        demoHelper.textContent = 'Demo credentials are not available for that role right now.';
      }
      return null;
    }

    if (loginEmailInput) {
      loginEmailInput.value = user.email;
    }
    if (loginPasswordInput) {
      const toggleButton = getPasswordToggleButton('login-password');
      setPasswordVisibilityState(loginPasswordInput, toggleButton, false);
      loginPasswordInput.value = user.password;
      loginPasswordInput.focus();
      loginPasswordInput.select();
    }
    if (loginRoleSelect) {
      loginRoleSelect.value = user.role;
    }

    setActiveDemoRole(user.role);
    clearFeedback(loginFeedback);

    if (demoHelper) {
      const label = getRoleLabel(user.role);
      demoHelper.textContent = `${label} demo credentials ready — tick the consent and sign in.`;
    }

    if (options.scrollIntoView && loginForm) {
      loginForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    return user;
  }

  function toggleFormDisabled(form, disabled) {
    if (!form) return;
    form.querySelectorAll('input, select, button').forEach((element) => {
      element.disabled = disabled;
    });
  }

  function hasRecaptchaSupport() {
    return Boolean(RECAPTCHA_SITE_KEY && !/replace-with/i.test(RECAPTCHA_SITE_KEY) && window.grecaptcha);
  }

  function grecaptchaReady() {
    if (!hasRecaptchaSupport() || !window.grecaptcha.ready) {
      return Promise.resolve();
    }
    return new Promise((resolve) => {
      window.grecaptcha.ready(resolve);
    });
  }

  async function executeRecaptcha(action) {
    if (!hasRecaptchaSupport()) {
      return '';
    }
    try {
      await grecaptchaReady();
      return await window.grecaptcha.execute(RECAPTCHA_SITE_KEY, { action: action || 'submit' });
    } catch (error) {
      console.warn('Failed to execute reCAPTCHA', error);
      return '';
    }
  }

  function evaluatePasswordStrength(password) {
    if (!password) return 0;
    let score = 0;
    if (password.length >= 8) score += 1;
    if (/[A-Z]/.test(password)) score += 1;
    if (/[a-z]/.test(password)) score += 1;
    if (/\d/.test(password)) score += 1;
    if (/[^A-Za-z0-9]/.test(password)) score += 1;
    return Math.min(score, 4);
  }

  function updatePasswordMeter(password) {
    if (!passwordMeterBar || !passwordMeterLabel) return;
    const score = evaluatePasswordStrength(password);
    const percentage = (score / 4) * 100;
    passwordMeterBar.style.width = `${percentage}%`;
    let tone = '#ef4444';
    let label = 'Too weak';
    if (score >= 4) {
      tone = '#16a34a';
      label = 'Excellent';
    } else if (score === 3) {
      tone = '#22c55e';
      label = 'Strong';
    } else if (score === 2) {
      tone = '#f97316';
      label = 'Fair';
    }
    passwordMeterBar.style.backgroundColor = tone;
    passwordMeterLabel.textContent = `Password strength: ${label}`;
  }

  function hasGoogleIdentitySupport() {
    return Boolean(GOOGLE_CLIENT_ID && !/replace-with/i.test(GOOGLE_CLIENT_ID));
  }

  function waitForGoogleIdentity() {
    if (!hasGoogleIdentitySupport()) {
      return Promise.reject(new Error('Google Identity not configured.'));
    }
    if (window.google?.accounts?.id) {
      return Promise.resolve();
    }
    return new Promise((resolve, reject) => {
      let attempts = 0;
      const timer = setInterval(() => {
        if (window.google?.accounts?.id) {
          clearInterval(timer);
          resolve();
        }
        attempts += 1;
        if (attempts > 40) {
          clearInterval(timer);
          reject(new Error('Timed out waiting for Google Identity script.'));
        }
      }, 150);
    });
  }

  function rememberSession(user, token, options = {}) {
    if (!user || !user.email) {
      return null;
    }

    const session = {
      token: token || null,
      jwt: options.jwt || null,
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

  async function loginViaApi({ email, password, role, recaptchaToken }) {
    const body = { email, password, role, recaptchaToken };

    const data = await request('/api/login', {
      method: 'POST',
      body,
    });

    if (!data || !data.user) {
      throw new Error('Unexpected response from the portal API.');
    }

    if (role && data.user.role !== role) {
      throw new Error(`This account belongs to the ${data.user.role} portal. Please select the matching role.`);
    }

    const session = rememberSession(data.user, data.token, { jwt: data.jwt });
    return { user: session ? session.user : data.user, message: data.message || '' };
  }

  function loginWithDemo(email, password, role) {
    const user = findDemoUser(email, password, role);
    if (!user) {
      return null;
    }
    const session = rememberSession(user, null, { isDemo: true });
    const resolved = session ? session.user : user;
    return { user: resolved, demo: true };
  }

  async function handleLogin(event) {
    event.preventDefault();
    if (!loginForm) return;

    const formData = new FormData(loginForm);
    const email = String(formData.get('email') || '').trim();
    const password = formData.get('password');
    const role = formData.get('role');
    const consent = formData.get('consent');

    if (!email || !password || !role) {
      setFeedback(loginFeedback, 'error', 'Please fill every field before signing in.');
      return;
    }

    if (!consent) {
      setFeedback(loginFeedback, 'error', 'Please agree to the data consent before continuing.');
      return;
    }

    toggleFormDisabled(loginForm, true);
    setFeedback(loginFeedback, 'info', 'Verifying your credentials…');

    try {
      const recaptchaToken = await executeRecaptcha('portal_login');
      const response = await loginViaApi({ email, password, role, recaptchaToken });

      const user = response.user;
      setFeedback(loginFeedback, 'success', `Welcome back ${user.name || ''}! Redirecting to the ${user.role} dashboard…`);
      setTimeout(() => redirectToDashboard(user.role), 450);
    } catch (error) {
      const fallback = loginWithDemo(email, password, role);
      if (fallback && fallback.user) {
        setFeedback(loginFeedback, 'success', `Demo mode active — redirecting to the ${fallback.user.role} dashboard.`);
        setTimeout(() => redirectToDashboard(fallback.user.role), 500);
        toggleFormDisabled(loginForm, false);
        return;
      }
      setFeedback(loginFeedback, 'error', error.message || 'Unable to sign in right now.');
    } finally {
      toggleFormDisabled(loginForm, false);
    }
  }

  async function handleSignup(event) {
    event.preventDefault();
    if (!signupForm) return;

    const formData = new FormData(signupForm);
    const payload = {
      name: String(formData.get('name') || '').trim(),
      email: String(formData.get('email') || '').trim(),
      password: formData.get('password'),
      confirmPassword: formData.get('confirmPassword'),
      phone: String(formData.get('phone') || '').trim(),
      city: String(formData.get('city') || '').trim(),
      role: formData.get('role') || 'referrer',
      consent: Boolean(formData.get('consent')), 
      marketing: Boolean(formData.get('marketing')),
    };

    if (!payload.name || !payload.email || !payload.password || !payload.role) {
      setFeedback(signupFeedback, 'error', 'Please complete every required field to continue.');
      return;
    }

    if (!payload.consent) {
      setFeedback(signupFeedback, 'error', 'You must agree to the portal data consent before signing up.');
      return;
    }

    if (payload.password !== payload.confirmPassword) {
      setFeedback(signupFeedback, 'error', 'Passwords do not match.');
      return;
    }

    toggleFormDisabled(signupForm, true);
    setFeedback(signupFeedback, 'info', 'Creating your portal account…');

    try {
      const recaptchaToken = await executeRecaptcha('portal_signup');
      const data = await request('/api/signup', {
        method: 'POST',
        body: {
          name: payload.name,
          email: payload.email,
          password: payload.password,
          phone: payload.phone,
          city: payload.city,
          role: payload.role,
          consent: payload.consent,
          marketing: payload.marketing,
          recaptchaToken,
        },
      });

      if (!data || !data.user) {
        throw new Error('Unexpected response from the portal API.');
      }

      const session = rememberSession(data.user, data.token, { jwt: data.jwt });
      const secretCodeMessage = data.secretCode
        ? ` Share this secret code with the Dakshayani admin to complete your onboarding: ${data.secretCode}.`
        : '';
      setFeedback(
        signupFeedback,
        'success',
        `Welcome aboard ${session?.user?.name || data.user.name}! Redirecting to the ${data.user.role} dashboard…${secretCodeMessage}`
      );
      signupForm.reset();
      updatePasswordMeter('');
      ['signup-password', 'signup-password-confirm'].forEach((id) => {
        const input = document.getElementById(id);
        const button = getPasswordToggleButton(id);
        setPasswordVisibilityState(input, button, false);
      });
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
      fillDemoCredentials(fillDemoButton.dataset.demoRole || 'admin');
    });
  }

  if (quickDemoButtons && quickDemoButtons.length > 0) {
    quickDemoButtons.forEach((button) => {
      button.addEventListener('click', () => {
        const shouldScroll = Boolean(button.closest('.quick-demo'));
        fillDemoCredentials(button.dataset.demoRole, { scrollIntoView: shouldScroll });
      });
    });
  }

  if (passwordToggleButtons && passwordToggleButtons.length > 0) {
    passwordToggleButtons.forEach((button) => {
      const targetId = button.dataset.passwordToggle;
      const input = targetId ? document.getElementById(targetId) : null;
      if (input) {
        setPasswordVisibilityState(input, button, input.type === 'text');
      }
      button.addEventListener('click', () => togglePasswordVisibility(button));
    });
  }

  if (loginForm) {
    loginForm.addEventListener('submit', handleLogin);
    loginForm.addEventListener('input', (event) => {
      clearFeedback(loginFeedback);
      if (demoHelper && ['email', 'password'].includes(event.target?.name || '')) {
        demoHelper.textContent = defaultDemoHelperText;
        setActiveDemoRole(null);
      }
    });
  }

  if (signupForm) {
    signupForm.addEventListener('submit', handleSignup);
    signupForm.addEventListener('input', () => clearFeedback(signupFeedback));
  }

  if (signupPassword) {
    signupPassword.addEventListener('input', () => updatePasswordMeter(signupPassword.value));
  }

  function initialiseGoogleSignIn() {
    if (!hasGoogleIdentitySupport() || !googleButton) {
      return;
    }
    waitForGoogleIdentity()
      .then(() => {
        try {
          window.google.accounts.id.initialize({
            client_id: GOOGLE_CLIENT_ID,
            callback: async (credentialResponse) => {
              try {
                const recaptchaToken = await executeRecaptcha('google_signin');
                const data = await request('/api/auth/google', {
                  method: 'POST',
                  body: { idToken: credentialResponse.credential, recaptchaToken },
                });
                if (!data || !data.user) {
                  throw new Error('Unexpected Google Sign-In response.');
                }
                const session = rememberSession(data.user, data.token, { jwt: data.jwt });
                setFeedback(
                  loginFeedback,
                  'success',
                  `Signed in as ${session?.user?.name || data.user.name}. Redirecting to the ${data.user.role} dashboard…`
                );
                setTimeout(() => redirectToDashboard(data.user.role), 450);
              } catch (error) {
                console.error('Google sign-in failed', error);
                setFeedback(loginFeedback, 'error', error.message || 'Google sign-in failed. Try again.');
              }
            },
          });
          window.google.accounts.id.renderButton(googleButton, {
            theme: 'outline',
            size: 'large',
            text: 'continue_with',
            shape: 'rectangular',
            width: 280,
          });
        } catch (error) {
          console.warn('Unable to initialise Google Sign-In', error);
        }
      })
      .catch((error) => {
        console.warn('Google Identity unavailable', error);
      });
  }

  initialiseGoogleSignIn();
  if (signupPassword) {
    updatePasswordMeter(signupPassword.value || '');
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
