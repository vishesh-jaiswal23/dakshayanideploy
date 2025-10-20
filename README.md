# DakshayaniEdits

## Overview

This project bundles the Dakshayani Enterprises marketing site with a lightweight Node.js service for handling public forms and integrations. Portal login, signup, and related dashboards have been removed.

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

### Deploying on cPanel

If you are hosting the portal on shared hosting, follow the step-by-step playbook in [`docs/cpanel-backend-setup.md`](docs/cpanel-backend-setup.md). It covers preparing a ZIP archive, uploading it through File Manager, and provisioning the Node.js application in Application Manager.
