<?php
session_start();

date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/portal-state.php';

const ADMIN_EMAIL = 'd.entranchi@gmail.com';
const ADMIN_PASSWORD_HASH = '$2y$12$P60S2OQ/W/h453B6A6hROuX96Ec3wVBQ8hG7Dx.G9QOkSV8D/hob.';

$dashboardRoutes = [
    'admin' => 'admin-dashboard.php',
    'installer' => 'installer-dashboard.php',
    'customer' => 'customer-dashboard.php',
    'referrer' => 'referrer-dashboard.php',
    'employee' => 'employee-dashboard.php',
];

$roleLabels = [
    'admin' => 'Admin',
    'installer' => 'Installer',
    'customer' => 'Customer',
    'referrer' => 'Referral partner',
    'employee' => 'Employee',
];

if (isset($_SESSION['user_role']) && isset($dashboardRoutes[$_SESSION['user_role']])) {
    header('Location: ' . $dashboardRoutes[$_SESSION['user_role']]);
    exit;
}

$error = null;
$role = $_POST['role'] ?? 'admin';
$emailInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailInput = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?: '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'admin';

    if (!isset($dashboardRoutes[$role])) {
        $error = 'Please select a valid account type.';
    } elseif ($role === 'admin') {
        if (strcasecmp($emailInput, ADMIN_EMAIL) !== 0 || !password_verify($password, ADMIN_PASSWORD_HASH)) {
            $error = 'Incorrect email or password. Please try again.';
        } else {
            $_SESSION['user_role'] = 'admin';
            $_SESSION['user_id'] = 'admin-root';
            $_SESSION['display_name'] = 'Dakshayani Admin';
            $_SESSION['user_email'] = ADMIN_EMAIL;
            $_SESSION['last_login'] = date('j F Y, g:i A');
            try {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } catch (Exception $e) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
            }
            header('Location: ' . $dashboardRoutes['admin']);
            exit;
        }
    } else {
        $state = portal_load_state();
        $matchedUser = null;
        foreach ($state['users'] as $user) {
            if (strcasecmp($user['email'] ?? '', $emailInput) === 0 && ($user['role'] ?? '') === $role) {
                $matchedUser = $user;
                break;
            }
        }

        if ($matchedUser === null) {
            $error = 'No account found for this email and role. Please contact the admin team.';
        } elseif (($matchedUser['status'] ?? 'active') !== 'active') {
            $statusLabel = ucwords(str_replace('-', ' ', $matchedUser['status'] ?? 'inactive'));
            $error = "This account is currently {$statusLabel}. Please ask the admin to enable access.";
        } elseif (empty($matchedUser['password_hash'])) {
            $error = 'This account does not have a password yet. Please ask the admin to reset it for you.';
        } elseif (!password_verify($password, $matchedUser['password_hash'])) {
            $error = 'Incorrect email or password. Please try again.';
        } else {
            $_SESSION['user_role'] = $role;
            $_SESSION['user_id'] = $matchedUser['id'];
            $_SESSION['display_name'] = $matchedUser['name'] ?? 'Portal user';
            $_SESSION['user_email'] = $matchedUser['email'] ?? $emailInput;
            $_SESSION['last_login'] = date('j F Y, g:i A');
            try {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } catch (Exception $e) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
            }

            $loginIso = date('c');
            foreach ($state['users'] as &$user) {
                if (($user['id'] ?? '') === $matchedUser['id']) {
                    $user['last_login'] = $loginIso;
                    break;
                }
            }
            unset($user);

            portal_record_activity($state, sprintf('%s signed in as %s', $matchedUser['name'] ?? $emailInput, $roleLabels[$role] ?? ucfirst($role)), $matchedUser['name'] ?? 'Portal user');
            portal_save_state($state);

            header('Location: ' . $dashboardRoutes[$role]);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Portal Login | Dakshayani Enterprises</title>
  <meta name="description" content="Secure access for Dakshayani Enterprises portal users including administrators, installers, and customers." />
  <link rel="icon" href="images/favicon.ico" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <style>
    :root {
      color-scheme: light;
      --surface: #ffffff;
      --muted: rgba(15, 23, 42, 0.65);
      --border: rgba(15, 23, 42, 0.08);
      --primary: #2563eb;
      --primary-dark: #1d4ed8;
      --error: #dc2626;
      --background: linear-gradient(160deg, #0f172a 0%, #1d4ed8 45%, #0f172a 100%);
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: var(--background);
      color: #0f172a;
      padding: 1.5rem;
    }

    .login-shell {
      width: min(440px, 100%);
      background: var(--surface);
      border-radius: 1.75rem;
      padding: clamp(2rem, 5vw, 3rem);
      box-shadow: 0 40px 80px -50px rgba(15, 23, 42, 0.8);
      display: grid;
      gap: 1.5rem;
    }

    header h1 {
      margin: 0;
      font-size: clamp(1.8rem, 3vw, 2.3rem);
      font-weight: 700;
      color: #0f172a;
    }

    header p {
      margin: 0.5rem 0 0;
      color: var(--muted);
      line-height: 1.5;
    }

    form {
      display: grid;
      gap: 1.2rem;
    }

    .form-group {
      display: grid;
      gap: 0.5rem;
    }

    label {
      font-size: 0.85rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: rgba(15, 23, 42, 0.72);
    }

    input,
    select {
      border-radius: 0.85rem;
      border: 1px solid rgba(148, 163, 184, 0.35);
      padding: 0.7rem 0.9rem;
      font-size: 0.95rem;
      font-family: inherit;
      background: #f8fafc;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    input:focus,
    select:focus {
      outline: none;
      border-color: rgba(37, 99, 235, 0.8);
      box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.18);
    }

    .submit-btn {
      border: none;
      border-radius: 999px;
      background: var(--primary);
      color: #f8fafc;
      font-weight: 600;
      font-size: 1rem;
      padding: 0.85rem 1.4rem;
      cursor: pointer;
      transition: background 0.2s ease, transform 0.2s ease;
      box-shadow: 0 24px 45px -30px rgba(37, 99, 235, 0.85);
    }

    .submit-btn:hover,
    .submit-btn:focus {
      background: var(--primary-dark);
      transform: translateY(-1px);
    }

    .error-message {
      background: rgba(220, 38, 38, 0.08);
      color: var(--error);
      border: 1px solid rgba(220, 38, 38, 0.18);
      border-radius: 1rem;
      padding: 0.9rem 1rem;
      font-size: 0.9rem;
      line-height: 1.4;
    }

    .fine-print {
      font-size: 0.8rem;
      color: rgba(15, 23, 42, 0.5);
      margin: 0;
      line-height: 1.5;
    }

    @media (max-width: 520px) {
      body {
        padding: 1rem;
      }

      .login-shell {
        border-radius: 1.25rem;
        padding: 1.75rem;
      }
    }
  </style>
</head>
<body>
  <main class="login-shell">
    <header>
      <h1>Portal sign in</h1>
      <p>Choose your account type to access the Dakshayani Enterprises workspace. Installers, employees, customers, and referral partners can now use their assigned credentials.</p>
    </header>

    <?php if ($error): ?>
      <div class="error-message" role="alert">
        <?= htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>">
      <div class="form-group">
        <label for="role">Account type</label>
        <select id="role" name="role" required>
          <?php foreach ($dashboardRoutes as $value => $route): ?>
            <option value="<?= htmlspecialchars($value); ?>" <?= $role === $value ? 'selected' : ''; ?>><?= htmlspecialchars($roleLabels[$value] ?? ucfirst($value)); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="<?= htmlspecialchars($emailInput); ?>" autocomplete="username" required />
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input id="password" type="password" name="password" autocomplete="current-password" required />
      </div>

      <button type="submit" class="submit-btn">Sign in</button>
    </form>

    <p class="fine-print">Need help accessing the portal? Reach out to the Dakshayani Enterprises leadership team at <?= htmlspecialchars(ADMIN_EMAIL); ?> for account setup or password resets.</p>
  </main>
</body>
</html>
