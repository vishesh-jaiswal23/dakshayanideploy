<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/portal-state.php';

function api_send_json(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function api_send_error(int $status, string $message): void
{
    api_send_json($status, ['error' => $message]);
}

function api_get_published_posts(array $posts): array
{
    return array_values(array_filter($posts, static function (array $post): bool {
        return ($post['status'] ?? 'draft') === 'published';
    }));
}
