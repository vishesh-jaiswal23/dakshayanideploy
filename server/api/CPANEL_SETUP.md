# Dakshayani Portal PHP API – cPanel deployment checklist

Follow these steps when uploading the PHP backend to GoDaddy (or any Apache-based cPanel host). Completing every item ensures the frontend calls such as `/api/login` reach the new PHP scripts under `/portal/api/` instead of returning a **404**.

## 1. Upload the PHP API folder
1. Log into cPanel → **File Manager** → open `public_html`.
2. Create the folders `portal/api` if they do not already exist.
3. Upload everything from this project’s `server/api/` directory into `public_html/portal/api/` (keep the same filenames and the included `.htaccess`).
4. Upload `server/data/users.json` to `public_html/portal/server/data/users.json`. The PHP helpers will seed the default accounts automatically if this file is empty or missing, but placing the file now keeps any existing data.

## 2. Ensure PHP can write to users.json
- Right-click `public_html/portal/server/data/users.json` → **Permissions** → set to `0644` or `0664` depending on your host. This allows PHP to update the file when people log in or you add users from the admin panel.
- If you created the `server/data` folder manually, ensure the folder itself is at least `0755` so PHP can open it.

## 3. Bridge `/api/*` requests to the new folder
The frontend JavaScript still calls URLs like `/api/login`. Because the PHP files live in `/portal/api/`, you need one rewrite rule at the site root to forward those requests straight to the PHP scripts.

1. Download the helper file `server/portal.htaccess` from this project.
2. Upload it to `public_html/` and rename it to `.htaccess` (merge it with any existing directives if you already have a `.htaccess`).
3. The relevant rules look like this:

   ```apache
   RewriteEngine On

   # Map each API endpoint to its PHP script under /portal/api/
   RewriteRule ^api/?$ portal/api/index.php [QSA,L]
   RewriteRule ^api/(login|signup|me|logout)/?$ portal/api/$1.php [QSA,L]
   RewriteRule ^api/auth/google/?$ portal/api/auth.google.php [QSA,L]
   RewriteRule ^api/admin/users/?$ portal/api/admin.users.php [QSA,L]
   RewriteRule ^api/admin/users/(.*)$ portal/api/admin.users.php?path=$1 [QSA,L]
   RewriteRule ^api/dashboard/([a-z]+)/?$ portal/api/dashboard.$1.php [QSA,L]
   ```

   After saving, requests to `https://yourdomain.com/api/login` will automatically execute `public_html/portal/api/login.php`. Because the `.php` extension is appended directly in the rewrite, the inner `.htaccess` inside `portal/api/` is only used for optional niceties.

   > **Tip:** If you use reCAPTCHA v3 or Google One Tap on the login page, set the environment variables `GOOGLE_RECAPTCHA_SECRET` and `GOOGLE_CLIENT_ID` in the same root `.htaccess` file using `SetEnv` so the PHP API can validate those tokens server-side.

## 4. Verify the API
1. Visit `https://yourdomain.com/api` in your browser. You should see a JSON response confirming the API is reachable.
2. Visit `https://yourdomain.com/api/me`. You should see a JSON response with `{ "authenticated": false }`.
3. Open the developer console on the login page and log in with the main admin (`d.entranchi@gmail.com / Dakshayani@2311`).
4. The Network tab should show a `200` response for `/api/login`. Any `404` at this stage means the root `.htaccess` bridge is either missing or not saved correctly.

If you run into other issues, share the exact response body and the contents of both `.htaccess` files so we can troubleshoot further.
