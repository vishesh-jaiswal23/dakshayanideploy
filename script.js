/**
 * Client-side enhancements for Dakshayani Enterprises
 * --------------------------------------------------
 * - Injects shared header/footer partials across static pages.
 * - Highlights the active navigation link based on the current URL.
 * - Adds accessible mobile navigation behaviour.
 * - Updates any element with the `data-current-year` attribute.
 */

const PARTIALS = {
  header: 'partials/header.html',
  footer: 'partials/footer.html',
};

const FESTIVAL_THEMES = {
  default: {
    label: 'Standard décor',
    heroImage: '',
    primary: '',
    dark: '',
    overlay: '',
    banner: '',
  },
  diwali: {
    label: 'Diwali lights',
    heroImage: 'images/4.png',
    primary: '#f97316',
    dark: '#c2410c',
    overlay: 'rgba(17, 24, 39, 0.88)',
    banner: 'Happy Diwali! May your rooftops shine bright with clean solar energy and prosperity.',
    bannerBg: 'linear-gradient(120deg, rgba(249, 115, 22, 0.9), rgba(245, 158, 11, 0.78))',
    bannerColor: '#fff7ed',
  },
  holi: {
    label: 'Holi colours',
    heroImage: 'images/collage.jpg',
    primary: '#ec4899',
    dark: '#be123c',
    overlay: 'rgba(76, 29, 149, 0.72)',
    banner: 'Rang Barse! Celebrate Holi with vibrant savings and sustainable power from Dakshayani.',
    bannerBg: 'linear-gradient(120deg, rgba(236, 72, 153, 0.9), rgba(59, 130, 246, 0.75))',
    bannerColor: '#fdf2f8',
  },
  christmas: {
    label: 'Christmas glow',
    heroImage: 'images/team.jpg',
    primary: '#16a34a',
    dark: '#0f766e',
    overlay: 'rgba(15, 23, 42, 0.82)',
    banner: 'Season’s greetings! Spread warmth and clean energy this Christmas with Dakshayani Enterprises.',
    bannerBg: 'linear-gradient(120deg, rgba(20, 83, 45, 0.92), rgba(14, 116, 144, 0.8))',
    bannerColor: '#ecfdf5',
  },
};

const FESTIVAL_THEME_KEY = 'dakshayaniFestivalTheme';
let festivalPanel;
let festivalSelect;
let festivalLauncher;

/**
 * Resolve a partial path relative to the location of this script file.
 * This keeps partial loading working from nested directories such as `/pilot`.
 */
function resolvePartialUrl(relativePath) {
  const scriptEl = document.querySelector('script[src*="script.js"]');
  if (!scriptEl) return relativePath;
  const scriptUrl = new URL(scriptEl.getAttribute('src'), window.location.href);
  const baseUrl = new URL('./', scriptUrl);
  return new URL(relativePath, baseUrl).toString();
}

/**
 * Determine the canonical page key from the current location.
 */
function getPageKey(pathname = window.location.pathname) {
  const cleaned = pathname.replace(/\/+$/, '');
  const fileName = cleaned.split('/').pop() || 'index.html';
  return fileName.replace(/\.(html|php)$/i, '') || 'index';
}

/**
 * Fetch a HTML partial and inject it into the provided host element.
 */
async function injectPartial(selector, partialPath) {
  const host = document.querySelector(selector);
  if (!host) return;

  const url = resolvePartialUrl(partialPath);

  try {
    const response = await fetch(url, { cache: 'no-cache' });
    if (!response.ok) throw new Error(`${response.status} ${response.statusText}`);

    host.innerHTML = await response.text();

    if (selector === 'header.site-header') {
      enhanceHeaderNavigation(host);
    }
  } catch (error) {
    console.error(`Failed to load partial: ${url}`, error);
  }
}

/**
 * Highlight the active navigation entry and wire up the mobile menu.
 */
function enhanceHeaderNavigation(headerEl) {
  const overrideKey = document.body?.dataset?.activeNav;
  const pageKey = (overrideKey || getPageKey()).toLowerCase();
  const links = headerEl.querySelectorAll('a[href]');

  links.forEach((link) => {
    const href = link.getAttribute('href');
    if (!href || href.startsWith('http')) return;
    const targetKey = getPageKey(href.split('#')[0]).toLowerCase();
    if (targetKey && targetKey === pageKey) {
      link.classList.add('active');
      link.setAttribute('aria-current', 'page');

      const dropdown = link.closest('.nav-dropdown');
      if (dropdown) {
        dropdown.classList.add('is-active');
        const toggle = dropdown.querySelector('.nav-dropdown-toggle');
        if (toggle) {
          toggle.classList.add('active');
        }
      }
    }
  });

  const dropdowns = Array.from(headerEl.querySelectorAll('.nav-dropdown'));

  const closeDropdowns = (except) => {
    dropdowns.forEach((dropdown) => {
      if (dropdown === except) return;
      dropdown.classList.remove('is-open');
      const toggle = dropdown.querySelector('.nav-dropdown-toggle');
      if (toggle) {
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  };

  dropdowns.forEach((dropdown) => {
    const toggle = dropdown.querySelector('.nav-dropdown-toggle');
    const menu = dropdown.querySelector('.nav-dropdown-menu');
    if (!toggle || !menu) return;

    toggle.addEventListener('click', (event) => {
      event.preventDefault();
      const isOpen = dropdown.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', String(isOpen));
      if (isOpen) {
        closeDropdowns(dropdown);
      }
    });

    dropdown.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        dropdown.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.focus();
      }
    });

    menu.querySelectorAll('a').forEach((item) => {
      item.addEventListener('click', () => {
        dropdown.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
      });
    });
  });

  document.addEventListener('click', (event) => {
    if (!headerEl.contains(event.target)) {
      closeDropdowns();
    }
  });

  const menuButton = headerEl.querySelector('#mobile-menu-button');
  const mobileMenu = document.getElementById('mobile-menu');

  if (!menuButton || !mobileMenu) return;

  const closeMenu = () => {
    mobileMenu.classList.remove('show');
    menuButton.setAttribute('aria-expanded', 'false');
  };

  menuButton.addEventListener('click', () => {
    const isOpen = mobileMenu.classList.toggle('show');
    menuButton.setAttribute('aria-expanded', String(isOpen));
    menuButton.classList.toggle('is-active', isOpen);
  });

  mobileMenu.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => closeMenu());
  });

  window.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeMenu();
    }
  });
}

/**
 * Update the text content of any element tagged with `data-current-year`.
 */
function stampCurrentYear() {
  const year = String(new Date().getFullYear());
  document.querySelectorAll('[data-current-year]').forEach((node) => {
    node.textContent = year;
  });
}

function ensureFestivalBanner() {
  let banner = document.querySelector('[data-festival-banner]');
  if (!banner) {
    banner = document.createElement('div');
    banner.className = 'festival-banner';
    banner.setAttribute('data-festival-banner', '');
    banner.hidden = true;
    document.body?.insertBefore(banner, document.body.firstChild || null);
  }
  return banner;
}

function applyFestivalTheme(themeKey, { persist = true } = {}) {
  const theme = FESTIVAL_THEMES[themeKey] || FESTIVAL_THEMES.default;
  const root = document.documentElement;
  const banner = ensureFestivalBanner();

  const encodedHero = theme.heroImage ? `url('${encodeURI(theme.heroImage)}')` : '';

  if (theme.primary) root.style.setProperty('--primary-main', theme.primary); else root.style.removeProperty('--primary-main');
  if (theme.dark) root.style.setProperty('--primary-dark', theme.dark); else root.style.removeProperty('--primary-dark');
  if (theme.overlay) root.style.setProperty('--hero-overlay-color', theme.overlay); else root.style.removeProperty('--hero-overlay-color');
  if (theme.bannerBg) root.style.setProperty('--festival-banner-bg', theme.bannerBg); else root.style.removeProperty('--festival-banner-bg');
  if (theme.bannerColor) root.style.setProperty('--festival-banner-color', theme.bannerColor); else root.style.removeProperty('--festival-banner-color');

  if (encodedHero) {
    root.style.setProperty('--hero-image-url', encodedHero);
  } else {
    root.style.removeProperty('--hero-image-url');
  }

  if (theme.banner) {
    banner.textContent = theme.banner;
    banner.hidden = false;
  } else {
    banner.textContent = '';
    banner.hidden = true;
  }

  if (themeKey === 'default') {
    delete document.body.dataset.festivalTheme;
  } else {
    document.body.dataset.festivalTheme = themeKey;
  }

  if (festivalSelect && festivalSelect.value !== themeKey) {
    festivalSelect.value = themeKey;
  }

  if (festivalLauncher) {
    const label = festivalLauncher.querySelector('[data-festival-label]');
    if (label) {
      label.textContent = themeKey === 'default' ? 'Festival décor' : `${theme.label} décor`;
    }
  }

  if (persist) {
    try {
      localStorage.setItem(FESTIVAL_THEME_KEY, themeKey);
    } catch (error) {
      console.warn('Unable to persist festival theme', error);
    }
  }
}

function toggleFestivalPanel(open) {
  if (!festivalPanel || !festivalLauncher) return;
  const nextState = typeof open === 'boolean' ? open : festivalPanel.dataset.open !== 'true';
  festivalPanel.dataset.open = nextState ? 'true' : 'false';
  festivalLauncher.setAttribute('aria-expanded', String(nextState));
}

function createFestivalUi() {
  if (!document.body) return;

  festivalLauncher = document.createElement('button');
  festivalLauncher.type = 'button';
  festivalLauncher.className = 'festival-launcher';
  festivalLauncher.setAttribute('aria-expanded', 'false');
  festivalLauncher.setAttribute('aria-controls', 'festival-switcher-panel');
  festivalLauncher.innerHTML = '<i class="fa-solid fa-star"></i><span data-festival-label>Festival décor</span>';
  document.body.appendChild(festivalLauncher);

  festivalPanel = document.createElement('div');
  festivalPanel.className = 'festival-switcher';
  festivalPanel.id = 'festival-switcher-panel';
  festivalPanel.dataset.open = 'false';
  festivalPanel.innerHTML = `
    <header>
      <strong>Festival décor</strong>
      <button type="button" aria-label="Close décor panel" data-festival-close>&times;</button>
    </header>
    <label for="festival-select">Choose a theme</label>
    <select id="festival-select" data-festival-select></select>
    <div class="festival-actions">
      <button type="button" data-festival-reset>Reset</button>
      <button type="button" data-festival-close>Close</button>
    </div>
  `;
  document.body.appendChild(festivalPanel);

  festivalSelect = festivalPanel.querySelector('[data-festival-select]');
  Object.entries(FESTIVAL_THEMES).forEach(([key, details]) => {
    const option = document.createElement('option');
    option.value = key;
    option.textContent = details.label;
    festivalSelect.appendChild(option);
  });

  const closeButtons = festivalPanel.querySelectorAll('[data-festival-close]');
  const resetButton = festivalPanel.querySelector('[data-festival-reset]');

  festivalLauncher.addEventListener('click', () => toggleFestivalPanel());
  closeButtons.forEach((button) => button.addEventListener('click', () => toggleFestivalPanel(false)));
  document.addEventListener('click', (event) => {
    if (!festivalPanel || festivalPanel.dataset.open !== 'true') return;
    if (festivalPanel.contains(event.target) || event.target === festivalLauncher) return;
    toggleFestivalPanel(false);
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && festivalPanel?.dataset.open === 'true') {
      toggleFestivalPanel(false);
    }
  });

  festivalSelect.addEventListener('change', (event) => {
    applyFestivalTheme(event.target.value);
  });

  resetButton?.addEventListener('click', () => {
    applyFestivalTheme('default');
    toggleFestivalPanel(false);
  });
}

function initFestivalTheming() {
  if (typeof window === 'undefined' || !document.body) return;

  createFestivalUi();
  ensureFestivalBanner();

  const params = new URLSearchParams(window.location.search);
  const requestedTheme = params.get('theme');
  const storedTheme = (() => {
    try {
      return localStorage.getItem(FESTIVAL_THEME_KEY);
    } catch (error) {
      return null;
    }
  })();

  const initialTheme = (requestedTheme && FESTIVAL_THEMES[requestedTheme])
    ? requestedTheme
    : (storedTheme && FESTIVAL_THEMES[storedTheme])
      ? storedTheme
      : 'default';

  applyFestivalTheme(initialTheme, { persist: false });

  if (requestedTheme && FESTIVAL_THEMES[requestedTheme]) {
    try {
      localStorage.setItem(FESTIVAL_THEME_KEY, requestedTheme);
    } catch (error) {
      console.warn('Unable to persist requested festival theme', error);
    }
  }
}

// Wait for the document to be interactive before injecting content.
document.addEventListener('DOMContentLoaded', () => {
  injectPartial('header.site-header', PARTIALS.header);
  injectPartial('footer.site-footer', PARTIALS.footer);
  stampCurrentYear();
  initFestivalTheming();
});
