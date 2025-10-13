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

2. The login page now lists the demo email and password for every role. You can also click **Use demo admin** to auto-fill the administrator details.

3. Select the appropriate role from the *Login as* dropdown before signing in.

---

## 4. Demo accounts

These accounts are pre-loaded automatically each time the server starts. Use the credentials below to explore each dashboard:

| Role                | Email                     | Password       |
| ------------------- | ------------------------- | -------------- |
| Administrator       | `admin@dakshayani.in`     | `Admin@123`    |
| Customer            | `customer@dakshayani.in`  | `Customer@123` |
| Employee            | `employee@dakshayani.in`  | `Employee@123` |
| Installer           | `installer@dakshayani.in` | `Installer@123`|
| Referral partner    | `referrer@dakshayani.in`  | `Referrer@123` |

> **Tip:** When you log in successfully, you will be redirected automatically to the matching `*-dashboard.html` page for your role.

---

## 5. Troubleshooting common issues

### "Login failed" or "Unexpected response"

- Confirm the server terminal is still running and shows no error messages.
- Double-check that the email and password match the table above exactly (they are case-sensitive).
- Ensure the *Login as* dropdown matches the account’s role.

### "Please log in again" or redirected back to the login page

- Your session may have expired or you might have switched roles.
- On the dashboard, click **Log out** in the top-right corner and try again.
- If the logout button is unavailable, clear the portal data from your browser:
  - Chrome/Edge: open DevTools → Application tab → Storage → click **Clear site data**.
  - Firefox: open DevTools → Storage → Local Storage → right-click the site → **Delete All**.
- Reload `http://localhost:4000/login.html` and sign in again.

### Reset the demo users

If you want to reset the sample accounts to their default state (for example, after creating new users):

1. Stop the server (`Ctrl + C` in the terminal).
2. Delete the user data file:

   ```bash
   rm server/data/users.json
   ```

3. Restart the server with `npm start`. A fresh copy of the default demo users will be created automatically.

---

## 6. Need more help?

- Check the `server/index.js` file to see how accounts are seeded and how authentication works.
- If you run into other issues, take a screenshot of the error and share it along with any console output. That will make troubleshooting much faster.

Happy exploring!

---

## 7. Deploying the portal on cPanel (static hosting)

If you upload the HTML, CSS, and JS files to a shared host such as cPanel, the Node.js API in `/server` will not run. The portal now includes an offline fallback that still lets you explore every dashboard with the demo accounts. Follow these steps for a smooth deployment:

1. **Prepare the upload**
   - Download or clone the project to your computer.
   - Compress the entire `dakshayani2` folder into a `.zip` file so the directory structure is preserved.

2. **Upload through cPanel**
   - Log into cPanel and open **File Manager**.
   - Navigate to the `public_html` directory.
   - Use the **Upload** button to send the `.zip` file, then right-click it and choose **Extract**. This keeps the `images/`, `partials/`, and JavaScript files (including `portal-demo-data.js`, `portal-auth.js`, and `dashboard-auth.js`) in the correct relative paths.

3. **Launch the portal**
   - Visit `https://yourdomain.com/login.html` (replace the domain as needed).
   - Use the sample credentials listed on the login page. The offline fallback will validate them in the browser and redirect you to the correct `*-dashboard.html` file.
   - Each dashboard loads pre-seeded demo metrics locally, so you can click around without a backend service.

4. **Important things to know**
   - The **Sign Up** form is disabled on static hosts. It will prompt you to email `connect@dakshayani.in` instead. Full registration requires the Node.js API from the `/server` folder.
   - Logging out clears the saved session from your browser storage. If you switch roles, log out first to avoid seeing the wrong dashboard.
   - If the login appears stuck, clear the site data for your domain in the browser settings and refresh.

5. **Optional: run the real backend on cPanel**
   - Some cPanel plans include the “Node.js Selector” or “Application Manager”. If available, you can deploy the `/server` directory as an application listening on a port, then proxy `/api/*` requests to it using `.htaccess` rewrite rules. Check your hosting provider’s documentation for the exact steps.

With these adjustments you can test the dashboards immediately after uploading to cPanel, and later move to the full Node.js API when your hosting plan allows it.

