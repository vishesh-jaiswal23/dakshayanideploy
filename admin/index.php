<?php
session_start();

// If the user is not logged in, redirect to the login page.
if (!isset($_SESSION['user'])) {
    header('Location: /admin/login.php');
    exit;
}

// This will be the main admin dashboard page.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <header>
            <h1>Admin Dashboard</h1>
            <a href="/admin/logout.php">Logout</a>
        </header>
        <main>
            <p>Welcome to the admin dashboard, <?php echo htmlspecialchars($_SESSION['user']['name']); ?>!</p>
            <ul>
                <li><a href="/admin/content.php">Manage Website Content</a></li>
                <li><a href="/admin/blog.php">Manage Blog Posts</a></li>
                <li><a href="/admin/theme.php">Customize Theme</a></li>
                <li><a href="/admin/users.php">Manage Users</a></li>
            </ul>
        </main>
    </div>
</body>
</html>
