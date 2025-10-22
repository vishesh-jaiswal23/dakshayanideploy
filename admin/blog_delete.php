<?php
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: /admin/login.php');
    exit;
}

require_once __DIR__ . '/../index.php';

$post_id = isset($_GET['id']) ? $_GET['id'] : null;

if ($post_id) {
    $posts = readJsonFile(BLOG_POSTS_FILE, []);
    $updated_posts = [];
    foreach ($posts as $post) {
        if ($post['id'] !== $post_id) {
            $updated_posts[] = $post;
        }
    }
    writeJsonFile(BLOG_POSTS_FILE, $updated_posts);
}

header('Location: /admin/blog.php');
exit;
