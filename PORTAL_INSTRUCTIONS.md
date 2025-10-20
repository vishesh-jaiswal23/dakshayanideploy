# Dakshayani Enterprises Portal – Rookie setup guide

This guide walks you through launching the local demo portal, signing in with sample accounts, and fixing the most common login issues. Follow the steps in order – no prior coding knowledge is required.

---

## 1. Install prerequisites

You only need [Node.js](https://nodejs.org/) version 18 or later. If you do not have it installed:

1. Download the LTS installer for your operating system from the Node.js website.
2. Run the installer and accept the default options.
3. Restart your terminal or command prompt after installation finishes.

To confirm Node.js is ready, run:

```bash
node -v
```

You should see a version number such as `v18.x.x`.

---

## 2. Start the local portal server

1. Open a terminal or command prompt.
2. Change into the project directory (update the path if you downloaded it elsewhere):

   ```bash
   cd /path/to/dakshayani2
   ```

3. Install dependencies (there are no external packages, but this command sets everything up properly and is safe to re-run):

   ```bash
   npm install
   ```

4. Launch the demo API and static file server:

   ```bash
   npm start
   ```

   You should see output similar to `Server listening on http://0.0.0.0:4000`.

5. Keep this terminal window open while you test the dashboards.

---

## 3. Open the portal in your browser

1. With the server running, open your browser and visit:

   ```
   http://localhost:4000/login.html
   ```

2. The login page prompts for the administrator email and password that you configured in the portal `.env` file.

3. Ensure the *Choose a portal* dropdown is set to **Administrator** before signing in.

---

## 4. Secure the main administrator

Create a `.env` file in the project root (or inside `server/`) and add the administrator details that should control the portal:

```ini
MAIN_ADMIN_EMAIL=you@example.com
MAIN_ADMIN_PASSWORD=Use-A-Strong-Password-Here
MAIN_ADMIN_NAME=Head Administrator
MAIN_ADMIN_PHONE=+91 99999 99999
MAIN_ADMIN_CITY=Ranchi
```

The PHP API reads this file automatically and seeds the administrator account on first run. Do not commit the `.env` file to version control or share the password publicly.

---

## 5. Troubleshooting common issues

### "Login failed" or "Unexpected response"

- Confirm the server terminal is still running and shows no error messages.
- Double-check that the `.env` file contains the correct administrator email and password, then restart the API service.
- Ensure the *Choose a portal* dropdown is set to **Administrator** before submitting the form.

### "Please log in again" or redirected back to the login page

- Your session may have expired or you might have switched roles.
- On the dashboard, click **Log out** in the top-right corner and try again.
- If the logout button is unavailable, clear the portal data from your browser:
  - Chrome/Edge: open DevTools → Application tab → Storage → click **Clear site data**.
  - Firefox: open DevTools → Storage → Local Storage → right-click the site → **Delete All**.
- Reload `http://localhost:4000/login.html` and sign in again.

### Reset the portal accounts

If you need to clear all stored users (for example after testing locally):

1. Stop the server (`Ctrl + C` in the terminal).
2. Delete the user data file:

   ```bash
   rm server/data/users.json
   ```

3. Restart the server with `npm start`. A fresh administrator account will be created automatically from the `.env` settings.

---

## 6. Need more help?

- Check the `server/index.js` file to see how accounts are seeded and how authentication works.
- If you run into other issues, take a screenshot of the error and share it along with any console output. That will make troubleshooting much faster.

Happy exploring!

---

## 7. Deploying the portal on cPanel (static hosting)

If you upload the HTML, CSS, and JS files to a shared host such as cPanel, make sure the PHP API in `/server` is reachable — the login form no longer ships with demo credentials. Follow these steps for a smooth deployment:

1. **Prepare the upload**
   - Download or clone the project to your computer.
   - Compress the entire `dakshayani2` folder into a `.zip` file so the directory structure is preserved.

2. **Upload through cPanel**
   - Log into cPanel and open **File Manager**.
   - Navigate to the `public_html` directory.
   - Use the **Upload** button to send the `.zip` file, then right-click it and choose **Extract**. This keeps the `images/`, `partials/`, and JavaScript files (including `portal-demo-data.js`, `portal-auth.js`, and `dashboard-auth.js`) in the correct relative paths.

3. **Launch the portal**
   - Visit `https://yourdomain.com/login.html` (replace the domain as needed).
   - Upload a `.env` file containing your `MAIN_ADMIN_*` values (outside `public_html` if possible) and confirm PHP can read it.
   - Sign in with that administrator account. If the backend API is unavailable the form will report an error instead of falling back to demo credentials.

4. **Important things to know**
   - Self-signup and demo logins are disabled. Share the administrator credentials securely with authorised team members only.
   - Logging out clears the saved session from your browser storage. If you switch roles in the future, log out first to avoid seeing the wrong dashboard.
   - If the login appears stuck, clear the site data for your domain in the browser settings and refresh after confirming the API is running.

5. **Optional: run the real backend on cPanel**
   - Some cPanel plans include the “Node.js Selector” or “Application Manager”. If available, you can deploy the `/server` directory as an application listening on a port, then proxy `/api/*` requests to it using `.htaccess` rewrite rules. Check your hosting provider’s documentation for the exact steps.

With these adjustments you can test the dashboards immediately after uploading to cPanel, and later move to the full Node.js API when your hosting plan allows it.

