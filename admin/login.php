<?php

declare(strict_types=1);

require_once __DIR__ . '/../server/helpers.php';

ensure_session();
server_bootstrap();

if (is_authenticated() && (get_authenticated_user()['role'] ?? '') === 'admin') {
    header('Location: /admin/settings.php');
    exit;
}

$error = null;
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$attempts = json_read(LOGIN_ATTEMPTS_FILE, []);
$record = $attempts[$ip] ?? ['count' => 0, 'blocked_until' => 0, 'last_attempt' => 0];
$remainingBlock = max(0, ($record['blocked_until'] ?? 0) - time());

if ($remainingBlock > 0) {
    $minutes = (int) ceil($remainingBlock / 60);
    $error = 'Too many failed logins. Please try again in ' . $minutes . ' minute' . ($minutes === 1 ? '' : 's') . '.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === null) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Your session has expired. Please refresh and try again.';
    } else {
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?: '';
        $password = $_POST['password'] ?? '';

        if (!validator_email($email)) {
            $error = 'Please provide a valid email address.';
        } elseif (!validator_string($password, 255)) {
            $error = 'Please provide your password.';
        } else {
            $users = json_read(DATA_PATH . '/users.json', []);
            $matchedUser = null;
            foreach ($users as $user) {
                if (strcasecmp($user['email'] ?? '', $email) === 0 && ($user['role'] ?? '') === 'admin') {
                    $matchedUser = $user;
                    break;
                }
            }

            if (!$matchedUser || empty($matchedUser['password_hash']) || !password_verify($password, $matchedUser['password_hash'])) {
                $attempt = track_login_attempt($ip, 5, 900, 600);
                if (($attempt['blocked_until'] ?? 0) > time()) {
                    $minutes = (int) ceil(((int) $attempt['blocked_until'] - time()) / 60);
                    $error = 'Too many failed logins. Try again in ' . max(1, $minutes) . ' minute' . ($minutes === 1 ? '' : 's') . '.';
                } else {
                    $error = 'Incorrect email or password.';
                }
            } elseif (($matchedUser['status'] ?? 'active') !== 'active') {
                $error = 'Your administrator account is inactive.';
            } else {
                session_regenerate_id(true);
                set_authenticated_user($matchedUser);
                reset_login_attempts($ip);

                $usersList = [];
                foreach ($users as $user) {
                    if (($user['id'] ?? '') === ($matchedUser['id'] ?? null)) {
                        $user['last_login'] = now_ist();
                    }
                    $usersList[] = $user;
                }
                json_write(DATA_PATH . '/users.json', $usersList);

                log_activity('login', 'Administrator signed in', $matchedUser['email'] ?? 'admin');

                header('Location: /admin/settings.php');
                exit;
            }
        }
    }
}

$token = issue_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Login | Dakshayani Enterprises</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.4/dist/tailwind.min.css" rel="stylesheet" />
  </head>
  <body class="min-h-screen bg-slate-900 flex items-center justify-center py-10">
    <div class="w-full max-w-md bg-white/95 rounded-2xl shadow-xl p-8">
      <div class="text-center mb-8">
        <h1 class="text-2xl font-semibold text-slate-900">Dakshayani Portal</h1>
        <p class="text-sm text-slate-500">Administrator access</p>
      </div>
      <?php if ($error): ?>
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          <?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
        </div>
      <?php endif; ?>
      <form method="post" class="space-y-5">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" />
        <div>
          <label class="block text-sm font-medium text-slate-700" for="email">Email</label>
          <input required name="email" id="email" type="email" class="mt-1 w-full rounded-lg border border-slate-200 px-4 py-2.5 focus:border-blue-500 focus:ring-blue-500" placeholder="admin@dakshayani.in" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700" for="password">Password</label>
          <input required name="password" id="password" type="password" class="mt-1 w-full rounded-lg border border-slate-200 px-4 py-2.5 focus:border-blue-500 focus:ring-blue-500" />
        </div>
        <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-white font-medium shadow hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">Sign in</button>
        <p class="text-xs text-center text-slate-400">Default password: <span class="font-medium text-slate-600">Dakshayani@123</span></p>
      </form>
    </div>
  </body>
</html>
