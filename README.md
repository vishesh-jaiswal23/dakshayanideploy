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

### Secure access defaults

- The PHP backend now reads a `.env` file automatically (it looks in the project root and `server/` directories). Define `MAIN_ADMIN_*` there to seed your super administrator without committing secrets to version control.
- Only the main administrator is created automatically. Add additional users from the admin dashboard after signing in.
- Public self-signup and offline demo credentials are disabled to keep production data safe. Prospective users should email `connect@dakshayani.co.in` so the operations team can provision access.
- reCAPTCHA remains optional — if the secret is blank the API skips verification while still requiring valid credentials.

### Deploying on cPanel

If you are hosting the portal on shared hosting, follow the step-by-step playbook in [`docs/cpanel-backend-setup.md`](docs/cpanel-backend-setup.md). It covers preparing a ZIP archive, uploading it through File Manager, provisioning the Node.js application in Application Manager, and validating that login and signup work for every user role.
