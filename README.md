# Dakshayani Enterprises Portal

## Overview

This repository contains the Dakshayani Enterprises marketing site and a
session-aware PHP backend that powers the internal portal. The API exposes
the same endpoints as the legacy Node.js service, but now runs entirely on
PHP so it can be deployed on shared hosting and cPanel plans without any
additional runtimes.

### Key features
- Marketing site with solar calculators, case studies, and blog content.
- Secure login backed by PHP sessions and password hashing.
- Unified JSON API under `/api/*` for dashboards, blog management, user
  administration, and WhatsApp lead relays.
- Admin workspace for managing site content, theme palettes, users, and
  knowledge base entries.
- Gemini-powered automations that publish daily solar news, schedule blog
  briefings, and review portal operations for actionable follow-ups.

## Prerequisites

- [PHP 8.1+](https://www.php.net/) with the `openssl` and `curl`
  extensions enabled (both are bundled with the default PHP builds).
- Optional: a web server such as Apache or Nginx. The project also runs via
  PHP’s built-in development server for local testing.
- A Gemini API key (`GEMINI_API_KEY`) if you plan to run the automated news,
  blog, or operations review tasks.

## Local development

1. Open a terminal and change into the project directory:
   ```bash
   cd /path/to/dakshayanideploy
   ```
2. Start PHP’s built-in server from the project root:
   ```bash
   php -S 127.0.0.1:8000
   ```
3. Create an `api.txt` file in the project root that contains your Gemini API
   key. The admin dashboard, CLI helpers, and AI playground will read the key
   from this file automatically (environment variables are still supported if
   you prefer them).
4. Visit `http://127.0.0.1:8000/login.php` and sign in with the admin
   credentials defined in `login.php`.
5. Explore the dashboards for each role. All API requests are served by
   `api/index.php` and use the same session as the web pages.

### Gemini automation tasks

The admin dashboard now surfaces Gemini’s automated outputs under the **AI
automation** view. To run the same jobs manually or wire them into a cron job,
use the helper script:

```bash
php server/ai-gemini.php --task=all
```

Supported tasks are `news` (daily solar digest), `blog` (Monday/Wednesday/Friday
blog research), and `operations` (daily dashboard review). Provide
`--force` to ignore the schedule window. The script reads the API key from the
`GEMINI_API_KEY` environment variable or from an `api.txt` file in the project
root.

> **Tip:** the API responses are human readable. Visit
> `http://127.0.0.1:8000/api/me` while signed in to inspect the payload that
> drives the dashboards.

## Deploying on cPanel

1. Upload the repository contents to `public_html/` (or the folder mapped to
   your domain/subdomain).
2. Ensure PHP 8.1 or newer is selected inside **MultiPHP Manager**.
3. Add the rewrite rules from `server/portal.htaccess` to your root
   `.htaccess` so that `/api/*` requests are routed to `api/index.php`.
4. Configure optional environment variables via **Advanced > Cron Jobs >
   Environment Variables** (or `.htaccess` `SetEnv` directives) if you use
   Google reCAPTCHA, Google Solar API, or WhatsApp notifications.
5. Browse to `https://yourdomain/login.php` and log in with the admin
   credentials.

## WhatsApp quick connect

The homepage "Get Your Free Solar Consultation" form continues to open
WhatsApp directly. When submitted, the `/api/leads/whatsapp` endpoint also
forwards the lead information to the configured WhatsApp Business inbox so
your team can follow up instantly.

## API quick reference

- `GET /api/me` – Current session user information.
- `GET /api/dashboard/{role}` – Metrics for a specific portal role.
- `GET /api/public/*` – Public site data (blog posts, testimonials, search,
  site settings, customer templates, etc.).
- `POST /api/solar/estimate` – Google Solar API proxy with optional
  reCAPTCHA validation.
- `POST /api/leads/whatsapp` – Sends a WhatsApp notification for new leads.
- `GET/POST /api/admin/users` – Manage portal users (admin only).
- `GET/PUT/DELETE /api/blog/posts` – Blog post management (admin only).

All endpoints honour the PHP session cookie, so authenticated requests do
not require separate tokens.
