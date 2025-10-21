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
$sections = $state['home_sections'] ?? [];

$palette = [];
if (isset($theme['palette']) && is_array($theme['palette'])) {
    foreach ($theme['palette'] as $key => $entry) {
        if (!is_array($entry)) {
            $entry = [];
        }

        $palette[$key] = [
            'background' => $entry['background'] ?? '#FFFFFF',
            'text' => $entry['text'] ?? '#0F172A',
            'muted' => $entry['muted'] ?? '#475569',
        ];
    }
}

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

$publishedSections = array_values(array_filter($sections, static function (array $section): bool {
    return ($section['status'] ?? 'draft') === 'published';
}));

usort($publishedSections, static function (array $a, array $b): int {
    $orderA = (int) ($a['display_order'] ?? 0);
    $orderB = (int) ($b['display_order'] ?? 0);

    if ($orderA === $orderB) {
        return strcmp($a['updated_at'] ?? '', $b['updated_at'] ?? '');
    }

    return $orderA <=> $orderB;
});

$preparedSections = array_values(array_map(static function (array $section): array {
    $cta = $section['cta'] ?? [];
    $media = $section['media'] ?? [];

    return [
        'id' => $section['id'] ?? '',
        'eyebrow' => $section['eyebrow'] ?? '',
        'title' => $section['title'] ?? '',
        'subtitle' => $section['subtitle'] ?? '',
        'body' => array_values(array_filter($section['body'] ?? [], static fn($paragraph) => is_string($paragraph) && $paragraph !== '')),
        'bullets' => array_values(array_filter($section['bullets'] ?? [], static fn($bullet) => is_string($bullet) && $bullet !== '')),
        'cta' => [
            'text' => $cta['text'] ?? '',
            'url' => $cta['url'] ?? '',
        ],
        'media' => [
            'type' => $media['type'] ?? 'none',
            'src' => $media['src'] ?? '',
            'alt' => $media['alt'] ?? '',
        ],
        'backgroundStyle' => $section['background_style'] ?? 'section',
    ];
}, $publishedSections));

api_send_json(200, [
    'theme' => [
        'name' => $theme['active_theme'] ?? 'seasonal',
        'seasonLabel' => $theme['season_label'] ?? '',
        'accentColor' => $theme['accent_color'] ?? '#2563eb',
        'backgroundImage' => $theme['background_image'] ?? '',
        'announcement' => $theme['announcement'] ?? '',
        'palette' => $palette,
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
    'sections' => $preparedSections,
    'offers' => $publishedOffers,
    'testimonials' => $publishedTestimonials,
]);
