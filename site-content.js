(function () {
  const heroTitle = document.querySelector('[data-hero-title]');
  const heroSubtitle = document.querySelector('[data-hero-subtitle]');
  const heroImage = document.querySelector('[data-hero-main-image]');
  const heroCaption = document.querySelector('[data-hero-main-caption]');
  const bubbleHeading = document.querySelector('[data-hero-bubble-heading]');
  const bubbleBody = document.querySelector('[data-hero-bubble-body]');
  const heroPoints = document.querySelector('[data-hero-points]');
  const heroAnnouncement = document.querySelector('[data-hero-announcement]');
  const heroSection = document.querySelector('#hero');
  const offersList = document.querySelector('[data-offers-list]');
  const testimonialList = document.querySelector('[data-testimonial-list]');

  function renderEmptyState(container, message, className = 'site-search-empty') {
    if (!container) return;
    container.innerHTML = '';
    const paragraph = document.createElement('p');
    paragraph.className = className;
    paragraph.textContent = message;
    container.appendChild(paragraph);
  }

  function updateHero(hero) {
    if (!hero) return;

    if (heroTitle) {
      heroTitle.textContent = hero.title || heroTitle.textContent;
    }
    if (heroSubtitle) {
      heroSubtitle.textContent = hero.subtitle || heroSubtitle.textContent;
    }
    if (heroImage && hero.image) {
      heroImage.src = hero.image;
      heroImage.alt = hero.title || 'Dakshayani rooftop solar project';
    }
    if (heroCaption) {
      heroCaption.textContent = hero.imageCaption || '';
    }
    if (bubbleHeading) {
      bubbleHeading.textContent = hero.bubbleHeading || '';
    }
    if (bubbleBody) {
      bubbleBody.textContent = hero.bubbleBody || '';
    }
    if (heroPoints && Array.isArray(hero.bullets) && hero.bullets.length) {
      heroPoints.innerHTML = '';
      hero.bullets.forEach((point) => {
        if (typeof point !== 'string' || point.trim() === '') return;
        const li = document.createElement('li');
        const icon = document.createElement('i');
        icon.className = 'fa-solid fa-circle-check';
        icon.setAttribute('aria-hidden', 'true');
        li.appendChild(icon);
        li.appendChild(document.createTextNode(point));
        heroPoints.appendChild(li);
      });
    }
  }

  function renderOffers(offers) {
    if (!offersList) return;
    if (!Array.isArray(offers) || !offers.length) {
      renderEmptyState(offersList, 'Admin will publish seasonal offers here soon.');
      return;
    }

    offersList.innerHTML = '';
    const fragment = document.createDocumentFragment();

    offers.forEach((offer) => {
      const card = document.createElement('article');
      card.className = 'offer-card';

      if (offer.image) {
        const media = document.createElement('img');
        media.className = 'offer-illustration';
        media.src = offer.image;
        media.alt = offer.title || 'Seasonal offer';
        media.loading = 'lazy';
        card.appendChild(media);
      }

      const header = document.createElement('div');
      header.className = 'offer-card-header';
      if (offer.badge) {
        const badge = document.createElement('span');
        badge.className = 'offer-badge';
        badge.textContent = offer.badge;
        header.appendChild(badge);
      }
      const title = document.createElement('h3');
      title.textContent = offer.title || 'Limited time offer';
      header.appendChild(title);
      card.appendChild(header);

      if (offer.description) {
        const description = document.createElement('p');
        description.textContent = offer.description;
        card.appendChild(description);
      }

      const validityParts = [];
      if (offer.startsOn) validityParts.push(`From ${offer.startsOn}`);
      if (offer.endsOn) validityParts.push(`Till ${offer.endsOn}`);
      if (validityParts.length) {
        const validity = document.createElement('p');
        validity.className = 'offer-validity';
        validity.textContent = validityParts.join(' · ');
        card.appendChild(validity);
      }

      if (offer.ctaText && offer.ctaUrl) {
        const actions = document.createElement('div');
        actions.className = 'offer-actions';
        const link = document.createElement('a');
        link.className = 'btn btn-secondary';
        link.href = offer.ctaUrl;
        link.target = '_blank';
        link.rel = 'noopener';
        link.textContent = offer.ctaText;
        actions.appendChild(link);
        card.appendChild(actions);
      }

      fragment.appendChild(card);
    });

    offersList.appendChild(fragment);
  }

  function renderTestimonials(items) {
    if (!testimonialList) return;
    if (!Array.isArray(items) || !items.length) {
      renderEmptyState(testimonialList, 'Testimonials will appear here soon.');
      return;
    }

    testimonialList.innerHTML = '';
    const fragment = document.createDocumentFragment();

    items.forEach((item) => {
      const card = document.createElement('div');
      card.className = 'testimonial-card text-left';

      const quote = document.createElement('div');
      quote.className = 'quote-text';
      quote.textContent = item.quote || '';
      card.appendChild(quote);

      const author = document.createElement('div');
      author.className = 'author';

      if (item.image) {
        const avatar = document.createElement('img');
        avatar.src = item.image;
        avatar.alt = item.name || 'Customer photo';
        avatar.loading = 'lazy';
        avatar.className = 'author-avatar';
        author.appendChild(avatar);
      }

      const info = document.createElement('div');
      info.className = 'author-info';
      const name = document.createElement('p');
      name.className = 'author-name';
      const locationSuffix = item.location ? ` (${item.location})` : '';
      name.textContent = `${item.name || 'Customer'}${locationSuffix}`;
      info.appendChild(name);
      if (item.role) {
        const role = document.createElement('p');
        role.className = 'author-title';
        role.textContent = item.role;
        info.appendChild(role);
      }
      author.appendChild(info);
      card.appendChild(author);
      fragment.appendChild(card);
    });

    testimonialList.appendChild(fragment);
  }

  function applyTheme(theme) {
    if (!theme) return;
    if (theme.accentColor) {
      document.documentElement.style.setProperty('--primary-main', theme.accentColor);
      document.documentElement.style.setProperty('--primary-dark', theme.accentColor);
      document.documentElement.style.setProperty('--accent-blue-main', theme.accentColor);
      document.documentElement.style.setProperty('--accent-blue-dark', theme.accentColor);
    }
    if (heroSection) {
      if (theme.backgroundImage) {
        heroSection.style.backgroundImage = `linear-gradient(135deg, rgba(15, 23, 42, 0.85), rgba(15, 23, 42, 0.65)), url(${theme.backgroundImage})`;
        heroSection.style.backgroundSize = 'cover';
        heroSection.style.backgroundPosition = 'center';
      } else {
        heroSection.style.removeProperty('background-image');
      }
    }
    if (heroAnnouncement) {
      if (theme.announcement) {
        heroAnnouncement.textContent = theme.announcement;
        heroAnnouncement.hidden = false;
      } else {
        heroAnnouncement.hidden = true;
        heroAnnouncement.textContent = '';
      }
    }
  }

  fetch('/api/public/site-content.php')
    .then((response) => {
      if (!response.ok) throw new Error('Failed to load site content');
      return response.json();
    })
    .then((data) => {
      applyTheme(data.theme || {});
      updateHero(data.hero || {});
      renderOffers(data.offers || []);
      renderTestimonials(data.testimonials || []);
    })
    .catch(() => {
      renderEmptyState(offersList, 'Unable to load offers at the moment.');
      renderEmptyState(testimonialList, 'Unable to load testimonials at the moment.');
    });
})();
