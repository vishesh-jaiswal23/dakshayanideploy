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

### Configure the main administrator
Before starting the server, provide your production admin credentials via environment variables or a `.env` file:

```ini
MAIN_ADMIN_EMAIL=you@example.com
MAIN_ADMIN_PASSWORD=Use-A-Strong-Password-Here
MAIN_ADMIN_NAME=Head Administrator
MAIN_ADMIN_PHONE=+91 99999 99999
MAIN_ADMIN_CITY=Ranchi
```

The server seeds this account on startup and marks it as the super administrator. Both the Node.js and PHP backends use the same environment variables, so you only need to define them once.

### API quick check
After configuring the admin account and starting the server, verify authentication with a quick `curl` request (replace the placeholders with your actual credentials):
```bash
curl -s -X POST http://localhost:4000/api/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"<your-admin-email>","password":"<your-admin-password>"}'
```
Copy the `token` value from the response and query the role dashboard:
```bash
curl -s http://localhost:4000/api/dashboard/admin \
  -H "Authorization: Bearer <token>"
```

### Demo credentials for non-admin roles

| Role             | Email                    | Password        |
| ---------------- | ------------------------ | --------------- |
| Customer         | `customer@dakshayani.in` | `Customer@123`  |
| Employee         | `employee@dakshayani.in` | `Employee@123`  |
| Installer        | `installer@dakshayani.in`| `Installer@123` |
| Referral partner | `referrer@dakshayani.in` | `Referrer@123`  |

For a full end-to-end run, open `login.html` in a browser (or via the server above) and sign in with the published demo accounts. Each dashboard page will automatically fetch its data once authenticated.

### Sign up vs. log in cheatsheet

1. **Log in** – Choose your portal role on the login form, enter the matching email/password pair, then submit. Successful logins redirect to the role-specific dashboard. When the API is offline (for example on cPanel), the same form validates the bundled demo accounts entirely in the browser.
2. **Sign up** – Complete the “Create a new account” form with your name, email, password, optional phone/city, and preferred role. On self-hosted installs the Node.js API creates the account instantly and signs you in. On static-only deployments the form shows contact details (`connect@dakshayani.co.in`) so the team can activate your access manually.

> **Tip:** reCAPTCHA is optional. When the site key and secret are left at their placeholders (common on cPanel while you finish DNS verification), the API now treats missing tokens as a demo-mode bypass so real logins and signups still work.

### Deploying on cPanel

If you are hosting the portal on shared hosting, follow the step-by-step playbook in [`docs/cpanel-backend-setup.md`](docs/cpanel-backend-setup.md). It covers preparing a ZIP archive, uploading it through File Manager, provisioning the Node.js application in Application Manager, and validating that login and signup work for every user role.
