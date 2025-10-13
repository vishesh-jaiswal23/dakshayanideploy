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

// Wait for the document to be interactive before injecting content.
document.addEventListener('DOMContentLoaded', () => {
  injectPartial('header.site-header', PARTIALS.header);
  injectPartial('footer.site-footer', PARTIALS.footer);
  stampCurrentYear();
});
