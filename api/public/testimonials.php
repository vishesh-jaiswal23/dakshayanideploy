<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    api_send_error(405, 'Method not allowed');
}

$state = portal_load_state();
$testimonials = api_get_published_posts($state['testimonials'] ?? []);

$payload = array_map(static function (array $testimonial): array {
    return [
        'id' => $testimonial['id'] ?? '',
        'quote' => $testimonial['quote'] ?? '',
        'name' => $testimonial['name'] ?? '',
        'role' => $testimonial['role'] ?? '',
        'location' => $testimonial['location'] ?? '',
        'image' => $testimonial['image'] ?? '',
    ];
}, $testimonials);

api_send_json(200, ['testimonials' => $payload]);
