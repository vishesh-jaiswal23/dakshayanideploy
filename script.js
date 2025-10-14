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

const INLINE_PARTIALS = {
  header: `
    <div class="container header-inner">
      <a href="index.html" class="brand" aria-label="Dakshayani Enterprises home">
        <img src="images/logo/New dakshayani logo centered small.png" alt="Dakshayani Enterprises" class="brand-logo-em" />
        <span class="brand-text">Dakshayani Enterprises</span>
      </a>

      <nav class="nav-desktop" aria-label="Primary navigation">
        <a href="index.html" class="nav-link">Home</a>
        <a href="about.html" class="nav-link">About Us</a>
        <div class="nav-dropdown">
          <button type="button" class="nav-link nav-dropdown-toggle" aria-haspopup="true" aria-expanded="false">
            Solutions
            <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
          </button>
          <div class="nav-dropdown-menu" role="menu">
            <a href="projects.html" class="nav-link" role="menuitem">Solar Projects</a>
            <a href="pm-surya-ghar.html" class="nav-link" role="menuitem">PM Surya Ghar Subsidy</a>
            <a href="meera-gh2.html" class="nav-link" role="menuitem">Meera GH2 Initiative</a>
          </div>
        </div>
        <div class="nav-dropdown">
          <button type="button" class="nav-link nav-dropdown-toggle" aria-haspopup="true" aria-expanded="false">
            Resources
            <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
          </button>
          <div class="nav-dropdown-menu" role="menu">
            <a href="calculator.html" class="nav-link" role="menuitem">Savings Calculator</a>
            <a href="policies.html" class="nav-link" role="menuitem">Policies &amp; Compliance</a>
            <a href="rewards.html" class="nav-link" role="menuitem">Rewards Program</a>
            <a href="blog.html" class="nav-link" role="menuitem">Blog &amp; News</a>
          </div>
        </div>
        <a href="contact.html" class="btn btn-secondary nav-link">Quote / Contact</a>
        <a href="login.html" class="btn btn-primary nav-link">Portal Login</a>
      </nav>

      <button class="menu-btn" aria-label="Open navigation" aria-expanded="false" id="mobile-menu-button">
        <i class="fas fa-bars"></i>
      </button>
    </div>

    <nav id="mobile-menu" class="nav-mobile" aria-label="Mobile navigation">
      <a href="index.html">Home</a>
      <a href="about.html">About Us</a>
      <a href="projects.html">Solar Projects</a>
      <a href="pm-surya-ghar.html">PM Surya Ghar Subsidy</a>
      <a href="meera-gh2.html">Meera GH2 Initiative</a>
      <a href="calculator.html">Savings Calculator</a>
      <a href="policies.html">Policies &amp; Compliance</a>
      <a href="rewards.html">Rewards Program</a>
      <a href="blog.html">Blog &amp; News</a>
      <a href="login.html">User Portal Login</a>
      <a href="contact.html" class="btn btn-primary nav-mobile-cta">Free Quote / Contact</a>
    </nav>
  `.trim(),
  footer: `
    <div class="container footer-content">
      <div>
        <div class="footer-brand">
          <img src="images/logo/New dakshayani logo centered small.png" alt="Dakshayani Enterprises" class="brand-logo-em" />
          <span class="brand-text">Dakshayani Enterprises</span>
        </div>
        <p class="text-sm">
          Your trusted solar EPC partner in Ranchi, expanding clean energy access across Jharkhand,
          Chhattisgarh, Odisha, and Uttar Pradesh with Tier-1 technology and transparent service.
        </p>
        <div class="footer-social" aria-label="Social links">
          <a href="https://wa.me/917070278178" target="_blank" rel="noopener" aria-label="WhatsApp">
            <i class="fa-brands fa-whatsapp"></i>
          </a>
          <a href="mailto:connect@dakshayani.co.in" aria-label="Email">
            <i class="fa-solid fa-envelope"></i>
          </a>
          <a href="tel:+917070278178" aria-label="Call">
            <i class="fa-solid fa-phone"></i>
          </a>
        </div>
      </div>

      <div>
        <h4 class="font-bold text-lg">Solar &amp; Schemes</h4>
        <ul class="footer-links">
          <li><a href="pm-surya-ghar.html">PM Surya Ghar Yojana</a></li>
          <li><a href="financing.html">Financing &amp; Loans</a></li>
          <li><a href="projects.html">Residential Solutions</a></li>
          <li><a href="projects.html#commercial">Commercial / Industrial</a></li>
          <li><a href="calculator.html">Solar Savings Calculator</a></li>
        </ul>
      </div>

      <div>
        <h4 class="font-bold text-lg">Company</h4>
        <ul class="footer-links">
          <li><a href="about.html">About Dakshayani Enterprises</a></li>
          <li><a href="meera-gh2.html">Meera GH2 (Hydrogen)</a></li>
          <li><a href="blog.html">Blog &amp; News</a></li>
          <li><a href="policies.html#terms">T&amp;C / Warranty</a></li>
          <li><a href="contact.html">Contact &amp; Support</a></li>
        </ul>
      </div>
    </div>

    <div class="container footer-bottom">
      <p>
        &copy; <span data-current-year></span> Dakshayani Enterprises. All rights reserved.
        Office: Maa Tara, Kilburn Colony, Hinoo, Ranchi, Jharkhand-834002.
      </p>
    </div>
  `.trim(),
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

const SITE_SETTINGS_ENDPOINT = '/api/public/site-settings';
const LEAD_FORM_ENDPOINT = '/api/leads/whatsapp';
const DEFAULT_SITE_SETTINGS = {
  festivalTheme: 'default',
  hero: {
    title: 'Cut Your Electricity Bills. Power Your Future.',
    subtitle: 'Join 500+ Jharkhand families saving lakhs with dependable rooftop and hybrid solar solutions designed around you.',
    primaryImage: 'images/hero/hero.png',
    primaryAlt: 'Dakshayani engineers installing a rooftop solar plant',
    primaryCaption: 'Live commissioning | Ranchi',
    bubbleHeading: '24/7 monitoring',
    bubbleBody: 'Hybrid + storage ready',
    gallery: [
      { image: 'images/residential pics real/IMG-20230407-WA0011.jpg', caption: 'Residential handover' },
      { image: 'images/finance.jpg', caption: 'Finance desk' }
    ]
  },
  installs: [
    {
      id: 'install-001',
      title: '8 kW Duplex Rooftop',
      location: 'Ranchi, Jharkhand',
      capacity: '8 kW',
      completedOn: 'October 2024',
      image: 'images/residential pics real/IMG-20241028-WA0002.jpg',
      summary: 'Sun-tracking friendly design for an east-west duplex with surge protection and earthing upgrades.'
    },
    {
      id: 'install-002',
      title: '35 kW Manufacturing Retrofit',
      location: 'Adityapur, Jharkhand',
      capacity: '35 kW',
      completedOn: 'August 2024',
      image: 'images/residential pics real/WhatsApp Image 2025-02-10 at 17.44.29_6f1624c9.jpg',
      summary: 'Retrofit on light-gauge roofing with optimisers to balance shading across production bays.'
    },
    {
      id: 'install-003',
      title: 'Solar Irrigation Pump Cluster',
      location: 'Khunti, Jharkhand',
      capacity: '15 HP',
      completedOn: 'July 2024',
      image: 'images/pump.jpg',
      summary: 'High-efficiency AC pump with remote diagnostics energising micro-irrigation for farmers.'
    }
  ]
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
function getPartialKey(partialPath) {
  return Object.entries(PARTIALS).find(([, path]) => path === partialPath)?.[0];
}

async function injectPartial(selector, partialPath) {
  const host = document.querySelector(selector);
  if (!host) return;

  const url = resolvePartialUrl(partialPath);
  const partialKey = getPartialKey(partialPath);

  try {
    const response = await fetch(url, { cache: 'no-cache' });
    if (!response.ok) throw new Error(`${response.status} ${response.statusText}`);

    host.innerHTML = await response.text();

    if (selector === 'header.site-header') {
      enhanceHeaderNavigation(host);
    }
  } catch (error) {
    console.error(`Failed to load partial: ${url}`, error);
    if (partialKey && INLINE_PARTIALS[partialKey]) {
      host.innerHTML = INLINE_PARTIALS[partialKey];
      if (partialKey === 'header') {
        enhanceHeaderNavigation(host);
      }
    }
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

function applyFestivalTheme(themeKey) {
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
}
function normaliseSiteSettings(rawSettings) {
  const defaults = DEFAULT_SITE_SETTINGS;
  if (!rawSettings || typeof rawSettings !== 'object') {
    return JSON.parse(JSON.stringify(defaults));
  }

  const hero = rawSettings.hero && typeof rawSettings.hero === 'object' ? rawSettings.hero : {};
  const gallery = Array.isArray(hero.gallery) ? hero.gallery : [];
  const installs = Array.isArray(rawSettings.installs) ? rawSettings.installs : [];

  return {
    festivalTheme: FESTIVAL_THEMES[rawSettings.festivalTheme] ? rawSettings.festivalTheme : defaults.festivalTheme,
    hero: {
      title: typeof hero.title === 'string' ? hero.title : defaults.hero.title,
      subtitle: typeof hero.subtitle === 'string' ? hero.subtitle : defaults.hero.subtitle,
      primaryImage: typeof hero.primaryImage === 'string' ? hero.primaryImage : defaults.hero.primaryImage,
      primaryAlt: typeof hero.primaryAlt === 'string' ? hero.primaryAlt : defaults.hero.primaryAlt,
      primaryCaption: typeof hero.primaryCaption === 'string' ? hero.primaryCaption : defaults.hero.primaryCaption,
      bubbleHeading: typeof hero.bubbleHeading === 'string' ? hero.bubbleHeading : defaults.hero.bubbleHeading,
      bubbleBody: typeof hero.bubbleBody === 'string' ? hero.bubbleBody : defaults.hero.bubbleBody,
      gallery: gallery.length
        ? gallery.slice(0, 6).map((item, index) => {
            const fallback = defaults.hero.gallery[index % defaults.hero.gallery.length];
            return {
              image: typeof item?.image === 'string' ? item.image : fallback.image,
              caption: typeof item?.caption === 'string' ? item.caption : fallback.caption
            };
          })
        : defaults.hero.gallery
    },
    installs: installs.length
      ? installs.slice(0, 8).map((install, index) => {
          const fallback = defaults.installs[index % defaults.installs.length];
          return {
            id: typeof install?.id === 'string' ? install.id : `install-${index + 1}`,
            title: typeof install?.title === 'string' ? install.title : fallback.title,
            location: typeof install?.location === 'string' ? install.location : fallback.location,
            capacity: typeof install?.capacity === 'string' ? install.capacity : fallback.capacity,
            completedOn: typeof install?.completedOn === 'string' ? install.completedOn : fallback.completedOn,
            image: typeof install?.image === 'string' ? install.image : fallback.image,
            summary: typeof install?.summary === 'string' ? install.summary : fallback.summary
          };
        })
      : defaults.installs
  };
}

function renderHeroGallery(items) {
  const container = document.querySelector('[data-hero-gallery]');
  if (!container) return;
  const galleryItems = Array.isArray(items) && items.length ? items : DEFAULT_SITE_SETTINGS.hero.gallery;
  container.innerHTML = '';
  galleryItems.slice(0, 3).forEach((item) => {
    const figure = document.createElement('figure');
    const img = document.createElement('img');
    img.src = item.image;
    img.alt = item.caption;
    img.loading = 'lazy';

    const caption = document.createElement('figcaption');
    caption.textContent = item.caption;

    figure.appendChild(img);
    figure.appendChild(caption);
    container.appendChild(figure);
  });
}

function applyHeroSettings(hero) {
  const defaults = DEFAULT_SITE_SETTINGS.hero;
  const data = {
    ...defaults,
    ...(hero || {}),
    gallery: Array.isArray(hero?.gallery) ? hero.gallery : defaults.gallery
  };

  const title = document.querySelector('[data-hero-title]');
  const subtitle = document.querySelector('[data-hero-subtitle]');
  const mainImage = document.querySelector('[data-hero-main-image]');
  const mainCaption = document.querySelector('[data-hero-main-caption]');
  const bubbleHeading = document.querySelector('[data-hero-bubble-heading]');
  const bubbleBody = document.querySelector('[data-hero-bubble-body]');
  const root = document.documentElement;
  const activeTheme = document.body?.dataset?.festivalTheme || 'default';

  if (title) title.textContent = data.title;
  if (subtitle) subtitle.textContent = data.subtitle;
  if (mainImage) {
    mainImage.src = data.primaryImage;
    mainImage.alt = data.primaryAlt;
  }
  if (mainCaption) mainCaption.textContent = data.primaryCaption;
  if (bubbleHeading) bubbleHeading.textContent = data.bubbleHeading;
  if (bubbleBody) bubbleBody.textContent = data.bubbleBody;

  if (data.primaryImage && activeTheme === 'default') {
    root.style.setProperty('--hero-image-url', `url('${data.primaryImage}')`);
  }

  renderHeroGallery(data.gallery);
}

function renderNewestInstalls(installs) {
  const section = document.querySelector('[data-installs-section]');
  const grid = document.querySelector('[data-installs-grid]');
  if (!grid) return;

  const items = Array.isArray(installs) && installs.length ? installs : [];
  grid.innerHTML = '';

  if (!items.length) {
    if (section) section.hidden = true;
    return;
  }

  if (section) section.hidden = false;

  items.slice(0, 4).forEach((install) => {
    const card = document.createElement('article');
    card.className = 'install-card';

    const media = document.createElement('div');
    media.className = 'install-media';
    const img = document.createElement('img');
    img.src = install.image;
    img.alt = install.title;
    img.loading = 'lazy';
    media.appendChild(img);

    const body = document.createElement('div');
    body.className = 'install-body';

    const meta = document.createElement('p');
    meta.className = 'install-meta';
    meta.textContent = `${install.capacity} • ${install.completedOn}`;

    const title = document.createElement('h3');
    title.textContent = install.title;

    const location = document.createElement('p');
    location.className = 'install-location';
    const locationIcon = document.createElement('i');
    locationIcon.className = 'fa-solid fa-location-dot';
    location.appendChild(locationIcon);
    location.append(` ${install.location}`);

    const summary = document.createElement('p');
    summary.className = 'install-summary';
    summary.textContent = install.summary;

    body.append(meta, title, location, summary);
    card.append(media, body);
    grid.appendChild(card);
  });
}

function applySiteSettings(settings) {
  const safeSettings = normaliseSiteSettings(settings);
  applyFestivalTheme(safeSettings.festivalTheme);
  applyHeroSettings(safeSettings.hero);
  renderNewestInstalls(safeSettings.installs);
}

async function fetchSiteSettings() {
  try {
    const response = await fetch(SITE_SETTINGS_ENDPOINT, { cache: 'no-store' });
    if (!response.ok) throw new Error(`Request failed: ${response.status}`);
    const data = await response.json();
    return normaliseSiteSettings(data.settings || data);
  } catch (error) {
    console.warn('Falling back to default site settings.', error);
    return JSON.parse(JSON.stringify(DEFAULT_SITE_SETTINGS));
  }
}

async function initSiteSettings() {
  applySiteSettings(DEFAULT_SITE_SETTINGS);
  const settings = await fetchSiteSettings();
  applySiteSettings(settings);
}

function setupLeadForm() {
  const form = document.getElementById('homepage-lead-form');
  if (!form) {
    return;
  }

  const alertEl = document.getElementById('homepage-lead-form-alert');
  const submitButton = form.querySelector('button[type="submit"]');

  const setAlert = (message, variant) => {
    if (!alertEl) {
      return;
    }

    alertEl.textContent = message || '';
    alertEl.classList.remove('is-success', 'is-error', 'is-visible');

    if (message) {
      alertEl.classList.add('is-visible');
      if (variant === 'success') {
        alertEl.classList.add('is-success');
      } else if (variant === 'error') {
        alertEl.classList.add('is-error');
      }
    }
  };

  form.addEventListener('submit', (event) => {
    event.preventDefault();

    if (submitButton) {
      submitButton.disabled = true;
      submitButton.setAttribute('aria-busy', 'true');
    }

    const formData = new FormData(form);
    const payload = {
      name: String(formData.get('name') || '').trim(),
      phone: String(formData.get('phone') || '').trim(),
      city: String(formData.get('city') || '').trim(),
      projectType: String(formData.get('projectType') || '').trim(),
      leadSource: String(formData.get('leadSource') || 'Website Homepage').trim(),
    };

    if (!payload.name || !payload.phone || !payload.city || !payload.projectType) {
      setAlert('Please complete every field before submitting the form.', 'error');
      if (submitButton) {
        submitButton.disabled = false;
        submitButton.removeAttribute('aria-busy');
      }
      return;
    }

    const messageLines = [
      'Hello Dakshayani Enterprises!',
      `Name: ${payload.name}`,
      `Phone: ${payload.phone}`,
      `City: ${payload.city}`,
      `Project Type: ${payload.projectType}`,
      `Source: ${payload.leadSource}`,
    ];
    const whatsappUrl = `https://wa.me/917070278178?text=${encodeURIComponent(messageLines.join('\n'))}`;

    const popup = window.open(whatsappUrl, '_blank');
    if (!popup) {
      window.location.href = whatsappUrl;
    }

    setAlert('Opening WhatsApp… please send us your message there!', 'success');
    form.reset();

    if (submitButton) {
      submitButton.disabled = false;
      submitButton.removeAttribute('aria-busy');
    }
  });
}

// Wait for the document to be interactive before injecting content.
document.addEventListener('DOMContentLoaded', () => {
  injectPartial('header.site-header', PARTIALS.header);
  injectPartial('footer.site-footer', PARTIALS.footer);
  stampCurrentYear();
  initSiteSettings();
  setupLeadForm();
});
