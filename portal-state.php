<?php

declare(strict_types=1);

const PORTAL_DATA_FILE = __DIR__ . '/data/portal-state.json';

function portal_default_state(): array
{
    return [
        'last_updated' => date('c'),
        'site_settings' => [
            'company_focus' => 'Utility-scale and rooftop solar EPC projects, O&M, and financing assistance across Jharkhand and Bihar.',
            'primary_contact' => 'Deepak Entranchi',
            'support_email' => 'support@dakshayanienterprises.com',
            'support_phone' => '+91 62030 01452',
            'announcement' => 'Welcome to the live operations console. Track projects, team workload, and customer updates in real time.'
        ],
        'site_theme' => [
            'active_theme' => 'evergreen',
            'season_label' => 'Evergreen Solar Savings',
            'accent_color' => '#2563eb',
            'background_image' => '',
            'announcement' => 'Energy independence for every household and MSME in Jharkhand.'
        ],
        'home_hero' => [
            'title' => 'Cut Your Electricity Bills. Power Your Future.',
            'subtitle' => 'Join 500+ Jharkhand families saving lakhs with dependable rooftop and hybrid solar solutions designed around you.',
            'image' => 'images/hero/hero.png',
            'image_caption' => 'Live commissioning | Ranchi',
            'bubble_heading' => '24/7 monitoring',
            'bubble_body' => 'Hybrid + storage ready',
            'bullets' => [
                'Guaranteed MNRE/JREDA subsidy filing & DISCOM approvals in 21 days',
                'Bank tie-ups for hassle-free EMI plans and zero-cost rooftop upgrades',
                'Hybrid-ready systems with 24×7 monitoring and annual health audits'
            ]
        ],
        'home_offers' => [],
        'testimonials' => [],
        'blog_posts' => [],
        'case_studies' => [],
        'users' => [],
        'projects' => [],
        'tasks' => [],
        'activity_log' => []
    ];
}

function portal_load_state(): array
{
    if (!file_exists(PORTAL_DATA_FILE)) {
        return portal_default_state();
    }

    $json = file_get_contents(PORTAL_DATA_FILE);
    if ($json === false) {
        return portal_default_state();
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        return portal_default_state();
    }

    $default = portal_default_state();
    $state = array_merge($default, $data);

    foreach (['users', 'projects', 'tasks', 'activity_log', 'blog_posts', 'case_studies', 'home_offers', 'testimonials'] as $key) {
        if (!isset($state[$key]) || !is_array($state[$key])) {
            $state[$key] = $default[$key];
        }
    }

    if (!isset($state['site_theme']) || !is_array($state['site_theme'])) {
        $state['site_theme'] = $default['site_theme'];
    } else {
        $state['site_theme'] = array_merge($default['site_theme'], array_intersect_key($state['site_theme'], $default['site_theme']));
    }

    if (!isset($state['home_hero']) || !is_array($state['home_hero'])) {
        $state['home_hero'] = $default['home_hero'];
    } else {
        $state['home_hero'] = array_merge($default['home_hero'], $state['home_hero']);
        if (!isset($state['home_hero']['bullets']) || !is_array($state['home_hero']['bullets'])) {
            $state['home_hero']['bullets'] = $default['home_hero']['bullets'];
        } else {
            $state['home_hero']['bullets'] = array_values(array_filter(array_map('trim', $state['home_hero']['bullets']), static function ($item) {
                return $item !== '';
            }));
        }
    }

    $state['users'] = array_map(static function (array $user): array {
        if (!isset($user['id']) || !is_string($user['id']) || $user['id'] === '') {
            $user['id'] = portal_generate_id('usr_');
        }

        if (!isset($user['email']) || !is_string($user['email'])) {
            $user['email'] = '';
        }

        if (!isset($user['role']) || !is_string($user['role'])) {
            $user['role'] = 'employee';
        }

        $user['password_hash'] = isset($user['password_hash']) && is_string($user['password_hash'])
            ? $user['password_hash']
            : '';

        if (isset($user['last_login']) && !is_string($user['last_login'])) {
            unset($user['last_login']);
        }

        return $user;
    }, $state['users']);

    $state['home_offers'] = array_values(array_filter(array_map(static function ($offer) {
        if (!is_array($offer)) {
            return null;
        }

        $offer['id'] = isset($offer['id']) && is_string($offer['id']) && $offer['id'] !== ''
            ? $offer['id']
            : portal_generate_id('off_');
        $offer['title'] = isset($offer['title']) ? trim((string) $offer['title']) : '';
        $offer['description'] = isset($offer['description']) ? trim((string) $offer['description']) : '';
        $offer['badge'] = isset($offer['badge']) ? trim((string) $offer['badge']) : '';
        $offer['cta_text'] = isset($offer['cta_text']) ? trim((string) $offer['cta_text']) : '';
        $offer['cta_url'] = isset($offer['cta_url']) ? trim((string) $offer['cta_url']) : '';
        $offer['image'] = isset($offer['image']) ? trim((string) $offer['image']) : '';
        $offer['starts_on'] = isset($offer['starts_on']) ? trim((string) $offer['starts_on']) : '';
        $offer['ends_on'] = isset($offer['ends_on']) ? trim((string) $offer['ends_on']) : '';
        $offer['status'] = in_array($offer['status'] ?? 'draft', ['draft', 'published'], true) ? $offer['status'] : 'draft';
        $offer['updated_at'] = isset($offer['updated_at']) && is_string($offer['updated_at']) ? $offer['updated_at'] : null;

        if ($offer['title'] === '' && $offer['description'] === '') {
            return null;
        }

        return $offer;
    }, $state['home_offers']), static fn($value) => $value !== null));

    $state['testimonials'] = array_values(array_filter(array_map(static function ($testimonial) {
        if (!is_array($testimonial)) {
            return null;
        }

        $testimonial['id'] = isset($testimonial['id']) && is_string($testimonial['id']) && $testimonial['id'] !== ''
            ? $testimonial['id']
            : portal_generate_id('tes_');
        $testimonial['quote'] = isset($testimonial['quote']) ? trim((string) $testimonial['quote']) : '';
        $testimonial['name'] = isset($testimonial['name']) ? trim((string) $testimonial['name']) : '';
        $testimonial['role'] = isset($testimonial['role']) ? trim((string) $testimonial['role']) : '';
        $testimonial['location'] = isset($testimonial['location']) ? trim((string) $testimonial['location']) : '';
        $testimonial['image'] = isset($testimonial['image']) ? trim((string) $testimonial['image']) : '';
        $testimonial['status'] = in_array($testimonial['status'] ?? 'published', ['published', 'draft'], true) ? $testimonial['status'] : 'published';
        $testimonial['updated_at'] = isset($testimonial['updated_at']) && is_string($testimonial['updated_at']) ? $testimonial['updated_at'] : null;

        if ($testimonial['quote'] === '' || $testimonial['name'] === '') {
            return null;
        }

        return $testimonial;
    }, $state['testimonials']), static fn($value) => $value !== null));

    $state['blog_posts'] = array_values(array_filter(array_map(static function ($post) {
        if (!is_array($post)) {
            return null;
        }

        $post['id'] = isset($post['id']) && is_string($post['id']) && $post['id'] !== ''
            ? $post['id']
            : portal_generate_id('blog_');
        $post['title'] = isset($post['title']) ? trim((string) $post['title']) : '';
        $post['slug'] = isset($post['slug']) ? trim((string) $post['slug']) : '';
        $post['excerpt'] = isset($post['excerpt']) ? trim((string) $post['excerpt']) : '';
        $post['hero_image'] = isset($post['hero_image']) ? trim((string) $post['hero_image']) : '';
        $post['tags'] = isset($post['tags']) && is_array($post['tags'])
            ? array_values(array_filter(array_map(static fn($tag) => trim((string) $tag), $post['tags']), static fn($tag) => $tag !== ''))
            : [];
        $post['read_time_minutes'] = isset($post['read_time_minutes']) ? (int) $post['read_time_minutes'] : null;
        $post['status'] = in_array($post['status'] ?? 'draft', ['draft', 'published'], true) ? $post['status'] : 'draft';
        $post['author'] = isset($post['author']) && is_array($post['author'])
            ? [
                'name' => trim((string) ($post['author']['name'] ?? '')),
                'role' => trim((string) ($post['author']['role'] ?? '')),
            ]
            : ['name' => '', 'role' => ''];
        $post['published_at'] = isset($post['published_at']) && is_string($post['published_at']) ? $post['published_at'] : null;
        $post['updated_at'] = isset($post['updated_at']) && is_string($post['updated_at']) ? $post['updated_at'] : null;

        if (isset($post['content']) && is_array($post['content'])) {
            $post['content'] = array_values(array_filter(array_map('trim', $post['content']), static fn($paragraph) => $paragraph !== ''));
        } else {
            $paragraphs = array_filter(array_map('trim', preg_split("/\n{2,}/", (string) ($post['content'] ?? ''))));
            $post['content'] = array_values($paragraphs);
        }

        if ($post['title'] === '' || $post['slug'] === '') {
            return null;
        }

        return $post;
    }, $state['blog_posts']), static fn($value) => $value !== null));

    $state['case_studies'] = array_values(array_filter(array_map(static function ($case) {
        if (!is_array($case)) {
            return null;
        }

        $case['id'] = isset($case['id']) && is_string($case['id']) && $case['id'] !== ''
            ? $case['id']
            : portal_generate_id('case_');
        $case['title'] = isset($case['title']) ? trim((string) $case['title']) : '';
        $case['segment'] = isset($case['segment']) ? trim((string) $case['segment']) : 'residential';
        $case['location'] = isset($case['location']) ? trim((string) $case['location']) : '';
        $case['summary'] = isset($case['summary']) ? trim((string) $case['summary']) : '';
        $case['capacity_kw'] = isset($case['capacity_kw']) ? (float) $case['capacity_kw'] : 0.0;
        $case['annual_generation_kwh'] = isset($case['annual_generation_kwh']) ? (float) $case['annual_generation_kwh'] : 0.0;
        $case['co2_offset_tonnes'] = isset($case['co2_offset_tonnes']) ? (float) $case['co2_offset_tonnes'] : 0.0;
        $case['payback_years'] = isset($case['payback_years']) ? (float) $case['payback_years'] : 0.0;
        $case['highlights'] = isset($case['highlights']) && is_array($case['highlights'])
            ? array_values(array_filter(array_map(static fn($point) => trim((string) $point), $case['highlights']), static fn($point) => $point !== ''))
            : [];
        $caseImage = $case['image'] ?? [];
        if (is_array($caseImage)) {
            $case['image'] = [
                'src' => trim((string) ($caseImage['src'] ?? '')),
                'alt' => trim((string) ($caseImage['alt'] ?? '')),
            ];
        } else {
            $case['image'] = [
                'src' => trim((string) $caseImage),
                'alt' => '',
            ];
        }
        $case['status'] = in_array($case['status'] ?? 'published', ['draft', 'published'], true) ? $case['status'] : 'published';
        $case['published_at'] = isset($case['published_at']) && is_string($case['published_at']) ? $case['published_at'] : null;
        $case['updated_at'] = isset($case['updated_at']) && is_string($case['updated_at']) ? $case['updated_at'] : null;

        if ($case['title'] === '' || $case['summary'] === '') {
            return null;
        }

        return $case;
    }, $state['case_studies']), static fn($value) => $value !== null));

    $state['activity_log'] = array_values(array_filter($state['activity_log'], static function ($entry) {
        return is_array($entry) && isset($entry['event'], $entry['timestamp']);
    }));

    return $state;
}

function portal_save_state(array $state): bool
{
    $state['last_updated'] = date('c');
    $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        return false;
    }

    return (bool) file_put_contents(PORTAL_DATA_FILE, $json, LOCK_EX);
}

function portal_record_activity(array &$state, string $event, string $actor = 'System'): void
{
    $state['activity_log'] ??= [];

    try {
        $id = 'log_' . bin2hex(random_bytes(4));
    } catch (Exception $e) {
        $id = 'log_' . uniqid();
    }

    array_unshift($state['activity_log'], [
        'id' => $id,
        'event' => $event,
        'actor' => $actor,
        'timestamp' => date('c')
    ]);

    $state['activity_log'] = array_slice($state['activity_log'], 0, 50);
}

function portal_generate_id(string $prefix = 'id_'): string
{
    try {
        return $prefix . bin2hex(random_bytes(4));
    } catch (Exception $e) {
        return $prefix . uniqid();
    }
}
