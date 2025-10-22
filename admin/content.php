<?php
session_start();

// If the user is not logged in, redirect to the login page.
if (!isset($_SESSION['user'])) {
    header('Location: /admin/login.php');
    exit;
}

require_once __DIR__ . '/../index.php';

$settings = readJsonFile(SITE_SETTINGS_FILE, []);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle the form submission and update the site settings.
    $settings['hero']['title'] = isset($_POST['hero_title']) ? $_POST['hero_title'] : $settings['hero']['title'];
    $settings['hero']['subtitle'] = isset($_POST['hero_subtitle']) ? $_POST['hero_subtitle'] : $settings['hero']['subtitle'];

    writeJsonFile(SITE_SETTINGS_FILE, $settings);

    // Redirect back to the content page with a success message.
    header('Location: /admin/content.php?success=1');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Management</title>
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <header>
            <h1>Content Management</h1>
            <a href="/admin/">Dashboard</a>
            <a href="/admin/logout.php">Logout</a>
        </header>
        <main>
            <?php if (isset($_GET['success'])): ?>
                <p style="color: green;">Content updated successfully!</p>
            <?php endif; ?>
            <form action="/admin/content.php" method="post">
                <h2>Hero Section</h2>
                <div>
                    <label for="hero_title">Title</label>
                    <input type="text" id="hero_title" name="hero_title" value="<?php echo htmlspecialchars($settings['hero']['title']); ?>">
                </div>
                <div>
                    <label for="hero_subtitle">Subtitle</label>
                    <textarea id="hero_subtitle" name="hero_subtitle"><?php echo htmlspecialchars($settings['hero']['subtitle']); ?></textarea>
                </div>
                <div>
                    <button type="submit">Save Changes</button>
                </div>
            </form>
        </main>
    </div>
</body>
</html>
