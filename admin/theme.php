<?php
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: /admin/login.php');
    exit;
}

require_once __DIR__ . '/../index.php';

$settings = readJsonFile(SITE_SETTINGS_FILE, []);
$allowed_themes = ['default', 'diwali', 'holi', 'christmas'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_theme = isset($_POST['theme']) ? $_POST['theme'] : 'default';
    if (in_array($selected_theme, $allowed_themes)) {
        $settings['festivalTheme'] = $selected_theme;
        writeJsonFile(SITE_SETTINGS_FILE, $settings);
    }
    header('Location: /admin/theme.php?success=1');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theme Customization</title>
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <header>
            <h1>Theme Customization</h1>
            <a href="/admin/">Dashboard</a>
            <a href="/admin/logout.php">Logout</a>
        </header>
        <main>
            <?php if (isset($_GET['success'])): ?>
                <p style="color: green;">Theme updated successfully!</p>
            <?php endif; ?>
            <form method="post">
                <div>
                    <label for="theme">Select Festival Theme</label>
                    <select id="theme" name="theme">
                        <?php foreach ($allowed_themes as $theme): ?>
                            <option value="<?php echo $theme; ?>" <?php echo ($settings['festivalTheme'] ?? 'default') === $theme ? 'selected' : ''; ?>>
                                <?php echo ucfirst($theme); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit">Save Theme</button>
            </form>
        </main>
    </div>
</body>
</html>
