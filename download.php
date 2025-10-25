<?php

declare(strict_types=1);

require_once __DIR__ . '/server/helpers.php';
require_once __DIR__ . '/server/modules.php';

$type = $_GET['type'] ?? null;
$file = null;

if ($type === 'blog_image') {
    $blogId = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
    if ($blogId === '') {
        http_response_code(404);
        echo 'File not specified.';
        exit;
    }
    server_bootstrap();
    $post = blog_post_find($blogId);
    if (!$post) {
        http_response_code(404);
        echo 'File not found.';
        exit;
    }
    $meta = is_array($post['meta'] ?? null) ? $post['meta'] : [];
    $file = $meta['cover_file'] ?? blog_placeholder_image();
} else {
    $token = $_GET['token'] ?? '';
    if (!verify_csrf_token(is_string($token) ? $token : null)) {
        http_response_code(403);
        echo 'Access denied.';
        exit;
    }
    $file = $_GET['file'] ?? '';
}

if (!is_string($file) || trim($file) === '') {
    http_response_code(404);
    echo 'File not specified.';
    exit;
}

$sanitized = str_replace(['..', '\\'], '', (string) $file);
$sanitized = ltrim($sanitized, '/');
$fullPath = realpath(UPLOAD_PATH . '/' . $sanitized);
$uploadsRoot = realpath(UPLOAD_PATH);

if ($fullPath === false || $uploadsRoot === false || strpos($fullPath, $uploadsRoot) !== 0 || !is_file($fullPath)) {
    http_response_code(404);
    echo 'File not found.';
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($fullPath) ?: 'application/octet-stream';
$downloadName = $_GET['name'] ?? basename($sanitized);
$disposition = (isset($_GET['inline']) && $_GET['inline'] === '1') ? 'inline' : 'attachment';

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . basename($downloadName) . '"');
header('Content-Length: ' . filesize($fullPath));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=60');

$handle = fopen($fullPath, 'rb');
if ($handle === false) {
    http_response_code(500);
    echo 'Unable to open file.';
    exit;
}

while (!feof($handle)) {
    echo fread($handle, 8192);
    flush();
}

fclose($handle);
exit;
