<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    api_send_error(405, 'Method not allowed');
}

$slugQuery = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';

$state = portal_load_state();
$posts = api_get_published_posts($state['blog_posts'] ?? []);

usort($posts, static function (array $a, array $b): int {
    $aTime = $a['published_at'] ?? $a['updated_at'] ?? '';
    $bTime = $b['published_at'] ?? $b['updated_at'] ?? '';
    return strcmp($bTime, $aTime);
});

$transform = static function (array $post, bool $includeContent = false): array {
    $payload = [
        'id' => $post['id'] ?? '',
        'title' => $post['title'] ?? '',
        'slug' => $post['slug'] ?? '',
        'excerpt' => $post['excerpt'] ?? '',
        'heroImage' => $post['hero_image'] ?? '',
        'tags' => array_values($post['tags'] ?? []),
        'readTimeMinutes' => $post['read_time_minutes'] ?? null,
        'publishedAt' => $post['published_at'] ?? '',
        'author' => [
            'name' => $post['author']['name'] ?? '',
            'role' => $post['author']['role'] ?? '',
        ],
    ];

    if ($includeContent) {
        $payload['content'] = array_values($post['content'] ?? []);
    }

    return $payload;
};

if ($slugQuery !== '') {
    foreach ($posts as $post) {
        if (strcasecmp($post['slug'] ?? '', $slugQuery) === 0) {
            api_send_json(200, ['post' => $transform($post, true)]);
        }
    }
    api_send_error(404, 'Post not found');
}

$list = array_map(static fn(array $post): array => $transform($post, false), $posts);

api_send_json(200, ['posts' => $list]);
