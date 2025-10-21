<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    api_send_error(405, 'Method not allowed');
}

$state = portal_load_state();

$theme = $state['site_theme'] ?? [];
$hero = $state['home_hero'] ?? [];
$offers = $state['home_offers'] ?? [];
$testimonials = $state['testimonials'] ?? [];

$publishedOffers = array_values(array_map(static function (array $offer): array {
    return [
        'id' => $offer['id'] ?? '',
        'title' => $offer['title'] ?? '',
        'description' => $offer['description'] ?? '',
        'badge' => $offer['badge'] ?? '',
        'startsOn' => $offer['starts_on'] ?? '',
        'endsOn' => $offer['ends_on'] ?? '',
        'ctaText' => $offer['cta_text'] ?? '',
        'ctaUrl' => $offer['cta_url'] ?? '',
        'image' => $offer['image'] ?? '',
    ];
}, array_filter($offers, static function (array $offer): bool {
    return ($offer['status'] ?? 'draft') === 'published';
})));

$publishedTestimonials = array_values(array_map(static function (array $testimonial): array {
    return [
        'id' => $testimonial['id'] ?? '',
        'quote' => $testimonial['quote'] ?? '',
        'name' => $testimonial['name'] ?? '',
        'role' => $testimonial['role'] ?? '',
        'location' => $testimonial['location'] ?? '',
        'image' => $testimonial['image'] ?? '',
    ];
}, array_filter($testimonials, static function (array $testimonial): bool {
    return ($testimonial['status'] ?? 'published') === 'published';
})));

api_send_json(200, [
    'theme' => [
        'name' => $theme['active_theme'] ?? 'seasonal',
        'seasonLabel' => $theme['season_label'] ?? '',
        'accentColor' => $theme['accent_color'] ?? '#2563eb',
        'backgroundImage' => $theme['background_image'] ?? '',
        'announcement' => $theme['announcement'] ?? '',
    ],
    'hero' => [
        'title' => $hero['title'] ?? '',
        'subtitle' => $hero['subtitle'] ?? '',
        'image' => $hero['image'] ?? '',
        'imageCaption' => $hero['image_caption'] ?? '',
        'bubbleHeading' => $hero['bubble_heading'] ?? '',
        'bubbleBody' => $hero['bubble_body'] ?? '',
        'bullets' => array_values(array_filter($hero['bullets'] ?? [])),
    ],
    'offers' => $publishedOffers,
    'testimonials' => $publishedTestimonials,
]);
