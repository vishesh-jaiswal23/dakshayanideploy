# Deploying the Dakshayani Backend on cPanel

This guide walks you through uploading the Dakshayani portal (static front end + Node.js backend API) to a cPanel hosting account. It assumes you want a working login/sign-up experience for the five dashboard personas bundled with the project (admin, customer, employee, installer, referrer).

The server already ships with production-ready endpoints for authentication, dashboards, and administrative user management located at `server/index.js`. You will:

1. Prepare the project locally.
2. Upload the codebase to your hosting space.
3. Provision the Node.js application with the cPanel **Application Manager**.
4. Connect your domain or subdomain to the running app.
5. Verify that log in, sign up, and admin management work.

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
   Visit `http://localhost:4000` and confirm you can:
   * Sign in with the seeded accounts (e.g. `admin@dakshayani.in` / `Admin@123`).
   * Sign up as a new user (defaults to the referrer role).
   * Access `customer-dashboard.html` etc. after logging in.

5. Zip everything (including the `server` folder and the root HTML files) so that you have an archive ready to upload.

---

## 2. Upload the code to cPanel

1. Sign in to **cPanel** and open **File Manager**.
2. Decide on a deployment path:
   * For a dedicated subdomain, create a folder like `backend` under your home directory.
   * For the primary domain, you can deploy inside `public_html` (recommended: use a subdirectory such as `portal`).
3. Use the **Upload** button in File Manager to upload your ZIP archive.
4. After the upload completes, select the ZIP file and click **Extract**. Confirm the extraction path matches the directory you created in the previous step.
5. Confirm the structure looks like:
   ```
   portal/
     index.html
     login.html
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

## 3. Provision the Node.js app in Application Manager

1. In cPanel, search for **“Setup Node.js App”** (also labelled **Application Manager**).
2. Click **Create Application** and fill in:
   * **Application mode:** `Production`
   * **Node.js version:** `18` (or higher if available).
   * **Application root:** The folder you extracted above (e.g. `/home/username/portal`).
   * **Application URL:** Select the domain/subdomain you want to serve (e.g. `https://solar.example.com`).
   * **Application startup file:** `server/index.js`
   * Leave the environment variables blank for now; the defaults baked into the app cover all roles.
3. Click **Create**. cPanel will provision the environment.
4. Once created, scroll to the **NPM** section and click **Run NPM Install**. This installs dependencies listed in `package.json` (there are none, but the step ensures the environment is initialised correctly).
5. After the install completes, click **Restart App** at the top of the page. You should see a green success banner.

The Node.js process now listens on the internal port chosen by Passenger and proxies requests from your chosen domain.

---

## 4. Map a domain or subdomain (if needed)

If you created a new subdomain for the portal:

1. Go back to the cPanel home screen.
2. Open **Domains → Subdomains** and create a subdomain (e.g. `solar` under `example.com`). Point its document root to the same directory used in Application Manager.
3. Wait for DNS to propagate (usually instant for an existing domain managed in the same cPanel account).

For primary domain usage, ensure the document root you configured is the one serving your website.

> **SSL/TLS:** Use cPanel’s **SSL/TLS Status** tool to issue a free AutoSSL certificate so that the app works on HTTPS.

---

## 5. Verify login, signup, and admin management

1. Open the URL configured in Application Manager. The static homepage should load.
2. Navigate to `/login.html` and sign in with the seeded admin account (`admin@dakshayani.in` / `Admin@123`).
3. Confirm that:
   * You can switch to the admin dashboard (links in the UI) and see metrics populated by the API.
   * The browser network tab shows requests hitting `/api/login`, `/api/me`, and `/api/dashboard/admin` successfully.
4. Test the sign-up flow by creating a new account on `/login.html` → “Create account”. A confirmation toast should appear, and you should be redirected to the appropriate dashboard.
5. After signing up, log back in as the admin and call the `/api/admin/users` endpoint via the dashboard or with a tool like `curl`/Postman to verify the new account is listed.
6. Review the `server/data` folder in File Manager – the `users.json` file should now contain the new user, demonstrating that persistence works on the filesystem.

---

## 6. Optional hardening & housekeeping

* **Change default passwords**: Edit `server/data/users.json` or sign in as admin and reset passwords via the API to secure the seeded accounts.
* **Set environment variables**: You can add variables in Application Manager (e.g. `PORT=8080`, `HOST=0.0.0.0`). The application defaults to these values if not specified.
* **Automated backups**: Use cPanel’s backup tools or configure Git Version Control to keep a copy of your data files.
* **Log monitoring**: Application Manager exposes a **View Logs** button. Use it to tail real-time stdout and catch any JSON parsing errors from malformed requests.

---

## 7. Troubleshooting

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| Visiting the URL downloads a file instead of rendering HTML | Node app not running; Apache is serving the raw file. | Ensure Application Manager shows the app as running and restart it. |
| 503 Service Unavailable | Node process crashed or is restarting. | Check **View Logs** for stack traces, run **Restart App**. |
| Signup returns `409` (conflict) | Email already exists in `users.json`. | Use a different email or delete the entry from `users.json`. |
| Admin dashboard shows `401` errors | Browser lost the token. | Clear storage or log back in; tokens are in-memory and invalidated on restart. |

---

## 8. Where to go next

* Hook up transactional email for password resets using cPanel’s Email Deliverability tools.
* Migrate the JSON files to MySQL via cPanel’s **MySQL® Database Wizard** if you need multi-user concurrency or reporting.
* Enable Cloudflare or another CDN in front of your domain for global performance and caching of the static assets.

You now have a fully functioning login/sign-up backend running on cPanel with dashboards for every user role. Keep this document handy for future deployments or when onboarding teammates.
