<?php
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: /admin/login.php');
    exit;
}

require_once __DIR__ . '/../index.php';

$posts = readJsonFile(BLOG_POSTS_FILE, []);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Management</title>
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <header>
            <h1>Blog Management</h1>
            <a href="/admin/">Dashboard</a>
            <a href="/admin/logout.php">Logout</a>
        </header>
        <main>
            <a href="/admin/blog_edit.php">Create New Post</a>
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($posts as $post): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($post['title']); ?></td>
                            <td><?php echo htmlspecialchars($post['status']); ?></td>
                            <td>
                                <a href="/admin/blog_edit.php?id=<?php echo htmlspecialchars($post['id']); ?>">Edit</a>
                                <a href="/admin/blog_delete.php?id=<?php echo htmlspecialchars($post['id']); ?>" onclick="return confirm('Are you sure you want to delete this post?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </main>
    </div>
</body>
</html>
