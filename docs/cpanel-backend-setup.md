# Deploying the Dakshayani PHP backend on cPanel

This guide explains how to upload the Dakshayani marketing site and the
new PHP API to a cPanel account (icons such as **File Manager**, **Terminal**,
**Git Version Control**, **Subdomains**, **SSL/TLS Status**, etc.). No Node.js
runtime or Application Manager setup is required.

---

## 1. Prepare the project locally

1. Install PHP 8.1 or newer on your workstation (optional, for local tests).
2. Clone or download this repository.
3. (Optional) Preview the site by running:
   ```bash
   php -S 127.0.0.1:8000
   ```
   Then open `http://127.0.0.1:8000` in your browser.
4. Compress the project folder into a `.zip` file so it is easy to upload via
   the cPanel File Manager.

---

## 2. Upload to cPanel

1. Sign in to **cPanel** and open **File Manager**.
2. Decide on a deployment directory (for example `public_html/portal`). Create
   the folder if it does not already exist.
3. Click **Upload**, select your `.zip`, and wait for the upload to finish.
4. With the archive highlighted, choose **Extract** and confirm the target
   directory from step 2.
5. After extraction your folder should look like:
   ```
   portal/
     api/
       index.php
       bootstrap.php
     admin-dashboard.php
     login.php
     images/
     ...
   ```
6. Delete the uploaded `.zip` to save space.

---

## 3. Configure PHP and rewrites

1. Open **MultiPHP Manager** and ensure the domain/subdomain points to
   **PHP 8.1** or newer.
2. Edit (or create) the root `.htaccess` file and add the rewrite rules that
   forward `/api/*` requests to the PHP router:
   ```apacheconf
   RewriteEngine On
   RewriteRule ^api/?$ api/index.php [QSA,L]
   RewriteRule ^api/(.*)$ api/index.php [QSA,L]
   ```
3. If you plan to use Google reCAPTCHA, the Google Solar API, or WhatsApp
   notifications, set the corresponding environment variables. You can use
   `.htaccess` `SetEnv` directives or the **Environment Variables** panel in
   cPanel’s **Cron Jobs** section. Supported keys include:
   - `GOOGLE_RECAPTCHA_SECRET`
   - `GOOGLE_SOLAR_API_KEY`
   - `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_ACCESS_TOKEN`,
     `WHATSAPP_RECIPIENT_NUMBER`

---

## 4. Map your domain

- For a dedicated subdomain: go to **Domains → Subdomains**, create one
  (e.g. `solar.example.com`), and set the document root to the folder used in
  step 2.
- For the primary domain: ensure the domain’s document root points to the
  uploaded folder.
- Use **SSL/TLS Status** to issue (or re-run) AutoSSL so the site loads over
  HTTPS.

---

## 5. Smoke test the deployment

1. Visit `https://yourdomain/login.php` and sign in with the admin credentials
   configured in `login.php`.
2. Navigate through each dashboard and confirm data loads. The admin view now
   includes live theme previews and full CRUD interfaces for users and blog
   posts.
3. Hit `https://yourdomain/api/me` in a new tab to verify the API responds with
   the current session information.
4. Submit the consultation form on the homepage to confirm the WhatsApp lead
   relay works (requires the environment variables mentioned earlier).

---

## 6. Troubleshooting

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| `/api/*` returns 404 | Rewrite rules missing | Add the rules shown above to the root `.htaccess`. |
| Dashboards redirect to login repeatedly | Session not persisted | Ensure PHP sessions are writable (check `tmp/` permissions) and cookies are not blocked. |
| WhatsApp lead fails | Environment variables not set | Define the WhatsApp API credentials or disable the quick connect button. |
| Solar estimator errors | Missing Google API key | Configure `GOOGLE_SOLAR_API_KEY` or disable the feature. |

---

Once these steps are complete the PHP backend mirrors every endpoint from
the previous Node.js implementation while remaining compatible with standard
shared-hosting environments.
