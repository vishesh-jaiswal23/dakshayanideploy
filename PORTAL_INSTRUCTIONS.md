# Dakshayani Enterprises Portal – Demo-only guide

The portal now runs in demo mode only. Login, signup, and logout flows have been removed, so every dashboard loads read-only sample data. Follow the steps below to preview the experience locally or deploy the static assets to hosting.

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

## 2. Start the local server

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
5. Keep this terminal window open while you explore the dashboards.

---

## 3. Explore the dashboards

1. With the server running, open your browser and visit `http://localhost:4000` to load the marketing site.
2. Open any dashboard directly, for example:
   - `http://localhost:4000/admin-dashboard.html`
   - `http://localhost:4000/customer-dashboard.html`
   - `http://localhost:4000/installer-dashboard.html`
3. Each page loads prefilled demo metrics and displays a banner noting that portal sign-in is disabled.
4. The **Sign out** buttons are disabled intentionally; they now show “Portal access disabled”.

---

## 4. Troubleshooting common issues

### Dashboards show placeholders only

- Ensure `portal-demo-data.js` is being served (check the browser console for 404 errors).
- Reload the page after the server finishes starting; the demo data populates on first load.

### WhatsApp consultation button does nothing

- Verify the server log does not report missing environment variables for WhatsApp integration.
- On shared hosting, make sure outgoing requests to the WhatsApp API are allowed or disable the quick connect button in HTML.

---

## 5. Deploying on cPanel (static hosting)

1. **Prepare the upload**
   - Download or clone the project to your computer.
   - Compress the entire folder into a `.zip` file so the directory structure is preserved.
2. **Upload through cPanel**
   - Log into cPanel and open **File Manager**.
   - Navigate to the `public_html` directory (or the folder tied to your subdomain).
   - Use the **Upload** button to send the `.zip` file, then right-click it and choose **Extract**. This keeps the `images/`, `partials/`, and JavaScript files (including `portal-demo-data.js` and `dashboard-auth.js`) in the correct relative paths.
3. **Launch the portal**
   - Visit `https://yourdomain.com/admin-dashboard.html` (replace the domain as needed) to confirm the demo dashboards load.
   - Because authentication has been removed, no environment variables or backend provisioning steps are required.
4. **Optional: run the Node.js backend on cPanel**
   - Some cPanel plans include the “Node.js Selector” or “Application Manager”. If available, you can deploy the `/server` directory as an application listening on a port, then proxy `/api/*` requests to it using `.htaccess` rewrite rules. This enables the WhatsApp lead relay and solar calculator API endpoints.

With these steps you can demonstrate the dashboards instantly without maintaining login infrastructure.
