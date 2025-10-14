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

### Forward consultation leads to WhatsApp

The homepage "Get Your Free Solar Consultation" form can forward every submission to a
WhatsApp chat using Meta's Cloud API. Configure the following environment variables before
starting the server (for example via a `.env` loader or inline with the `npm start` command):

| Variable | Purpose |
| --- | --- |
| `WHATSAPP_PHONE_NUMBER_ID` | The phone number ID from your Meta app |
| `WHATSAPP_ACCESS_TOKEN` | A permanent or temporary access token with the `whatsapp_business_messaging` permission |
| `WHATSAPP_RECIPIENT_NUMBER` | The WhatsApp number (in international format) that should receive the lead alert |

Example:

```bash
WHATSAPP_PHONE_NUMBER_ID=1234567890 \
WHATSAPP_ACCESS_TOKEN="EAAG..." \
WHATSAPP_RECIPIENT_NUMBER=917070278178 \
npm start
```

When the variables are present the server posts a WhatsApp text message containing the name,
phone, city, project type, and timestamp as soon as someone submits the consultation form.
If the integration is not configured, the visitor will see a friendly error message and the
request will not leave the server.

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
