<?php
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: /admin/login.php');
    exit;
}

require_once __DIR__ . '/../index.php';

$posts = readJsonFile(BLOG_POSTS_FILE, []);
$post_id = isset($_GET['id']) ? $_GET['id'] : null;
$post = null;

if ($post_id) {
    foreach ($posts as $p) {
        if ($p['id'] === $post_id) {
            $post = $p;
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_post = [
        'id' => $post_id ? $post_id : uniqid('post_', true),
        'title' => isset($_POST['title']) ? $_POST['title'] : '',
        'content' => isset($_POST['content']) ? $_POST['content'] : '',
        'status' => isset($_POST['status']) ? $_POST['status'] : 'draft',
    ];

    if ($post_id) {
        foreach ($posts as &$p) {
            if ($p['id'] === $post_id) {
                $p = array_merge($p, $new_post);
                break;
            }
        }
    } else {
        $posts[] = $new_post;
    }

    writeJsonFile(BLOG_POSTS_FILE, $posts);

    header('Location: /admin/blog.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $post ? 'Edit Blog Post' : 'Create Blog Post'; ?></title>
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <header>
            <h1><?php echo $post ? 'Edit Blog Post' : 'Create Blog Post'; ?></h1>
            <a href="/admin/blog.php">Back to Blog Management</a>
        </header>
        <main>
            <form method="post">
                <div>
                    <label for="title">Title</label>
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($post['title'] ?? ''); ?>" required>
                </div>
                <div>
                    <label for="content">Content</label>
                    <textarea id="content" name="content" required><?php echo htmlspecialchars($post['content'] ?? ''); ?></textarea>
                </div>
                <div>
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="draft" <?php echo ($post['status'] ?? 'draft') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="published" <?php echo ($post['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Published</option>
                    </select>
                </div>
                <button type="submit"><?php echo $post ? 'Update Post' : 'Create Post'; ?></button>
            </form>
        </main>
    </div>
</body>
</html>
