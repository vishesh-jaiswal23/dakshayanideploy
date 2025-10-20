<?php
session_start();

date_default_timezone_set('Asia/Kolkata');

const ADMIN_EMAIL = 'd.entranchi@gmail.com';
const ADMIN_PASSWORD_HASH = '$2y$12$P60S2OQ/W/h453B6A6hROuX96Ec3wVBQ8hG7Dx.G9QOkSV8D/hob.';

if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    header('Location: admin-dashboard.php');
    exit;
}

$error = null;
$role = $_POST['role'] ?? 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?: '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'admin';

    if (!in_array($role, ['admin', 'installer', 'customer', 'referrer', 'employee'], true)) {
        $error = 'Please select a valid account type.';
    } elseif ($role !== 'admin') {
        $error = 'Logins for this account type will be activated soon. Please ask the admin team to create your access.';
    } elseif (strcasecmp($email, ADMIN_EMAIL) !== 0 || !password_verify($password, ADMIN_PASSWORD_HASH)) {
        $error = 'Incorrect email or password. Please try again.';
    } else {
        $_SESSION['user_role'] = 'admin';
        $_SESSION['display_name'] = 'Dakshayani Admin';
        $_SESSION['last_login'] = date('j F Y, g:i A');
        header('Location: admin-dashboard.php');
        exit;
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
      <p>Choose your account type to sign in to the Dakshayani Enterprises portal. At present only the admin workspace is active.</p>
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
          <option value="admin" <?= $role === 'admin' ? 'selected' : ''; ?>>Admin</option>
          <option value="installer" <?= $role === 'installer' ? 'selected' : ''; ?> disabled>Installer</option>
          <option value="customer" <?= $role === 'customer' ? 'selected' : ''; ?> disabled>Customer</option>
          <option value="referrer" <?= $role === 'referrer' ? 'selected' : ''; ?> disabled>Customer referrer</option>
          <option value="employee" <?= $role === 'employee' ? 'selected' : ''; ?> disabled>Employee</option>
        </select>
      </div>

      <div class="form-group">
        <label for="email">Email</label>
        <input id="email" type="email" name="email" autocomplete="username" required />
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input id="password" type="password" name="password" autocomplete="current-password" required />
      </div>

      <button type="submit" class="submit-btn">Sign in</button>
    </form>

    <p class="fine-print">Need help accessing the portal? Reach out to the Dakshayani Enterprises leadership team for account setup.</p>
  </main>
</body>
</html>
