# Dakshayani Enterprises Portal – deployment notes

The portal now ships with a native PHP backend. No Node.js runtime or npm
scripts are required. Use the steps below to preview the dashboards locally
or to deploy them on shared hosting / cPanel.

---

## 1. Requirements

- PHP 8.1 or newer with the `openssl`, `json`, and `curl` extensions.
- (Optional) Apache or Nginx. PHP’s built-in server works for local demos.

---

## 2. Run locally

1. Open a terminal and `cd` into the project root.
2. Start PHP’s built-in web server:
   ```bash
   php -S 127.0.0.1:8000
   ```
3. Visit `http://127.0.0.1:8000/login.php` and sign in with the admin
   credentials defined near the top of `login.php`.
4. Explore the dashboards for each role. All API calls are served from
   `api/index.php` and reuse the same PHP session cookie as the HTML pages.

---

## 3. Deploy on cPanel / shared hosting

1. Upload the repository contents to `public_html/` (or the directory mapped
   to your domain).
2. Ensure PHP 8.1+ is active via **MultiPHP Manager**.
3. Copy the rewrite rules from `server/portal.htaccess` into the root
   `.htaccess`. They route every `/api/*` request to the new PHP router:
   ```apacheconf
   RewriteEngine On
   RewriteRule ^api/?$ api/index.php [QSA,L]
   RewriteRule ^api/(.*)$ api/index.php [QSA,L]
   ```
4. Configure optional environment variables if you plan to use Google
   reCAPTCHA, Google Solar API, or WhatsApp notifications. You can use
   `.htaccess` `SetEnv` directives or the cPanel **Environment Variables** UI.
5. Browse to `https://yourdomain/login.php` and sign in. The dashboards now
   operate against the PHP backend directly on the hosting account.

---

## 4. Troubleshooting

### API returns 401 / redirects to login

- Confirm your browser still has a valid PHP session cookie. Re-authenticate
  via `login.php` if necessary.
- Check that the server clock is correct; session expiry relies on it.

### API endpoints return 404

- Verify the `.htaccess` rewrite rules are installed at the site root.
- Make sure the `api/` directory and `api/index.php` were uploaded.

### Solar estimator errors

- Ensure the `GOOGLE_SOLAR_API_KEY` environment variable is configured.
- Outbound HTTPS requests must be allowed by your hosting provider.

---

With these steps the PHP backend mirrors the legacy Node.js endpoints while
remaining compatible with common shared-hosting environments.
