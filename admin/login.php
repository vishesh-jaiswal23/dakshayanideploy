<?php
session_start();

// If the user is already logged in, redirect to the dashboard.
if (isset($_SESSION['user'])) {
    header('Location: /admin/');
    exit;
}

require_once __DIR__ . '/../index.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? $_POST['email'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    $users = readJsonFile(USERS_FILE, []);
    $authenticated_user = null;

    foreach ($users as $user) {
        if (isset($user['email']) && $user['email'] === $email) {
            if (isset($user['password']) && password_verify($password, $user['password'])) {
                $authenticated_user = $user;
                break;
            }
        }
    }

    if ($authenticated_user) {
        $_SESSION['user'] = $authenticated_user;
        header('Location: /admin/');
        exit;
    } else {
        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <header>
            <h1>Admin Login</h1>
        </header>
        <main>
            <?php if ($error): ?>
                <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <form action="/admin/login.php" method="post">
                <div>
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div>
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div>
                    <button type="submit">Login</button>
                </div>
            </form>
        </main>
    </div>
</body>
</html>
