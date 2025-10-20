# Deploying the Dakshayani Backend on cPanel

This guide walks you through uploading the Dakshayani site (static front end + Node.js backend API for public forms and integrations) to a cPanel hosting account that looks like the tool layout in the screenshot you shared (icons such as **File Manager**, **Terminal**, **Git Version Control**, **Setup Node.js App**, **Subdomains**, **SSL/TLS Status**, etc.).

With portal authentication retired, the Node.js server now focuses on contact flows (WhatsApp leads, calculators, etc.). You will:

1. Prepare the project locally.
2. Upload the codebase to your hosting space with **File Manager** (or **Git Version Control** if you prefer).
3. Provision the Node.js application from **Setup Node.js App** (found under the **Software** section of your screenshot).
4. Connect your domain or subdomain with the **Domains → Subdomains** or **Domains** icons.
5. Issue SSL with **SSL/TLS Status** and confirm the marketing site and public API endpoints respond as expected.

> **Tip for first-timers:** Keep a text editor open with these instructions while you work inside cPanel so you can tick each step off as you go.

---

## 1. Prepare the project locally

1. **Install Node.js 18+ on your computer** (download from [nodejs.org](https://nodejs.org/) if you do not already have it).
2. **Clone or download** this repository to a working folder.
3. Open a terminal in the project root and install dependencies (none are required, but this generates the `package-lock.json` expected by cPanel’s dependency installer):
   ```bash
   npm install
   ```
4. (Optional) Run the project locally to familiarise yourself with the flows:
   ```bash
   npm start
   ```
   Visit `http://localhost:4000` and confirm the homepage, calculators, and contact forms behave as expected.

5. Zip everything (including the `server` folder and the root HTML files) so that you have an archive ready to upload.

---

## 2. Upload the code to cPanel

1. Sign in to **cPanel** and open **File Manager** (first item in the **Files** group of your screenshot).
2. Decide on a deployment path:
   * For a dedicated subdomain, create a folder like `backend` under your home directory (e.g. `/home/username/backend`).
   * For the primary domain, you can deploy inside `public_html` (recommended: use a subdirectory such as `portal` so static files and the API live together).
3. Use the **Upload** button in File Manager to upload your ZIP archive.
4. After the upload completes, select the ZIP file and click **Extract**. Confirm the extraction path matches the directory you created in the previous step.
5. Confirm the structure looks like:
   ```
   portal/
     index.html
     server/
       index.js
       data/
         users.json
         site-settings.json
     package.json
     package-lock.json
     ...
   ```
6. Remove the ZIP file once extraction succeeds to save space.

---

## 3. Provision the Node.js app via “Setup Node.js App”

1. Back on the cPanel home screen, scroll to the **Software** section and click **Setup Node.js App** (exactly as shown in the screenshot).
2. Click **Create Application** and fill in:
   * **Application Mode:** `Production`
   * **Node.js Version:** Choose `18` if available (the selector in this interface lists versions that are already provisioned on the server).
   * **Application Root:** Browse to the folder you extracted above (e.g. `/home/username/portal`).
   * **Application URL:** Pick the domain/subdomain that should serve the portal. If you have not created a subdomain yet, you can select your main domain for now and adjust later.
   * **Application Startup File:** `server/index.js`
   * Leave the **Environment Variables** table empty for now; the application runs with defaults.
3. Click **Create**. cPanel will provision the environment and show you the **Application Manager** screen for this app. (On some hosts this page is still titled “Application Manager”; the entry point, however, is the **Setup Node.js App** icon you can see on your dashboard.)
4. In the **NPM** section press **Run NPM Install**. Even though the project has no third-party dependencies, running this step initialises the environment so Passenger knows everything is ready.
5. Once `npm install` finishes, click **Restart App** near the top right. A success toast confirms the Node.js process is online.

The Node.js process now listens on the internal port chosen by Passenger and proxies requests from your chosen domain. If your cPanel plan does not show a **Setup Node.js App** icon, you will need to ask your hosting provider to enable it or upgrade to a plan that supports Node.js. Without it there is no persistent process manager to keep the backend running.

---

## 4. Map a domain or subdomain (if needed)

If you created a new subdomain for the portal:

1. Go back to the cPanel home screen.
2. Open **Domains → Subdomains** (icon available in the **Domains** group of the screenshot) and create a subdomain (e.g. `solar` under `example.com`). Point its document root to the same directory used in Setup Node.js App.
3. Wait for DNS to propagate (usually instant for an existing domain managed in the same cPanel account).

For primary domain usage, ensure the document root you configured is the one serving your website.

> **SSL/TLS:** Use the **SSL/TLS Status** icon (right-hand column in your screenshot) to issue a free AutoSSL certificate so that the app works on HTTPS. Click “Run AutoSSL” if the certificate is not already provisioned.

---

## 5. Smoke-test the deployment

1. Open the URL configured in Application Manager. The static homepage should load over HTTPS.
2. Submit the consultation form on the homepage and confirm WhatsApp launches with the prefilled message.
3. Visit `calculator.html` and ensure the savings calculator renders.
4. Load one of the sample dashboards directly (e.g. `/admin-dashboard.html`). You should see demo data along with a notice that portal sign-in is disabled.
5. Monitor the **View Logs** panel in Application Manager for any JSON parsing errors after each interaction.

---

## 6. Optional hardening & housekeeping

* **Change default passwords**: Edit `server/data/users.json` or sign in as admin and reset passwords via the API to secure the seeded accounts.
* **Set environment variables**: Define values such as `PORT=8080` or `HOST=0.0.0.0` in Application Manager if you need to override the defaults used by Passenger.
* **Automated backups**: Use cPanel’s backup tools or configure Git Version Control to keep a copy of your data files.
* **Log monitoring**: Application Manager exposes a **View Logs** button. Use it to tail real-time stdout and catch any JSON parsing errors from malformed requests.

---

## 7. Troubleshooting

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| Visiting the URL downloads a file instead of rendering HTML | Node app not running; Apache is serving the raw file. | Ensure Application Manager shows the app as running and restart it. |
| 503 Service Unavailable | Node process crashed or is restarting. | Check **View Logs** for stack traces, run **Restart App**. |
| WhatsApp lead does not send | Integration not configured on the server. | Enable the WhatsApp access token or disable the quick connect button. |

---

## 8. Where to go next

* Hook up transactional email for password resets using cPanel’s Email Deliverability tools.
* Migrate the JSON files to MySQL via cPanel’s **MySQL® Database Wizard** if you need multi-user concurrency or reporting.
* Enable Cloudflare or another CDN in front of your domain for global performance and caching of the static assets.

You now have the Dakshayani marketing site and supporting APIs running on cPanel. Keep this document handy for future deployments or when onboarding teammates.
