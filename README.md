# DakshayaniEdits

## Portal authentication stack

This project includes a static marketing site alongside a lightweight Node.js service that powers the portal login and role dashboards.

### Prerequisites
- [Node.js](https://nodejs.org/) 18 or newer (ships with the built-in modules used by the server)

### Install & run
1. Install dependencies (none are required, but this keeps `npm` happy):
   ```bash
   npm install
   ```
2. Start the combined static+API server:
   ```bash
   npm start
   ```
   The service listens on `http://localhost:4000` by default.

### WhatsApp quick connect

The homepage "Get Your Free Solar Consultation" form now opens WhatsApp directly on submit.
After a visitor fills out their name, phone, city, and project type, the site launches a
chat with `+91 70702 78178` and pre-fills those details into the message so your team can
continue the conversation instantly.

### API quick check
Use the seeded demo credentials to make sure everything is wired correctly:
```bash
curl -s -X POST http://localhost:4000/api/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@dakshayani.in","password":"Admin@123"}'
```
Copy the `token` value from the response and query the role dashboard:
```bash
curl -s http://localhost:4000/api/dashboard/admin \
  -H "Authorization: Bearer <token>"
```

For a full end-to-end run, open `login.html` in a browser (or via the server above) and sign in with any of the published demo accounts. Each dashboard page will automatically fetch its data once authenticated.

### Sign up vs. log in cheatsheet

1. **Log in** – Choose your portal role on the login form, enter the matching email/password pair, then submit. Successful logins redirect to the role-specific dashboard. When the API is offline (for example on cPanel), the same form validates the bundled demo accounts entirely in the browser.
2. **Sign up** – Complete the “Create a new account” form with your name, email, password, optional phone/city, and preferred role. On self-hosted installs the Node.js API creates the account instantly and signs you in. On static-only deployments the form shows contact details (`connect@dakshayani.co.in`) so the team can activate your access manually.

### Deploying on cPanel

If you are hosting the portal on shared hosting, follow the step-by-step playbook in [`docs/cpanel-backend-setup.md`](docs/cpanel-backend-setup.md). It covers preparing a ZIP archive, uploading it through File Manager, provisioning the Node.js application in Application Manager, and validating that login and signup work for every user role.
