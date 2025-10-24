<?php

declare(strict_types=1);

function portal_sanitize_hex_color(?string $value, string $fallback = '#000000'): string
{
    $candidate = trim((string) $value);
    if ($candidate === '') {
        return strtoupper($fallback);
    }

    if ($candidate[0] !== '#') {
        $candidate = '#' . $candidate;
    }

    if (!preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $candidate)) {
        return strtoupper($fallback);
    }

    if (strlen($candidate) === 4) {
        $candidate = sprintf(
            '#%s%s%s%s%s%s',
            $candidate[1],
            $candidate[1],
            $candidate[2],
            $candidate[2],
            $candidate[3],
            $candidate[3]
        );
    }

    return strtoupper($candidate);
}

function portal_hex_to_rgb(string $hex): array
{
    $hex = ltrim(portal_sanitize_hex_color($hex), '#');

    return [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
    ];
}

function portal_calculate_contrast_text(string $background): string
{
    [$r, $g, $b] = portal_hex_to_rgb($background);

    $r /= 255;
    $g /= 255;
    $b /= 255;

    $r = $r <= 0.03928 ? $r / 12.92 : (($r + 0.055) / 1.055) ** 2.4;
    $g = $g <= 0.03928 ? $g / 12.92 : (($g + 0.055) / 1.055) ** 2.4;
    $b = $b <= 0.03928 ? $b / 12.92 : (($b + 0.055) / 1.055) ** 2.4;

    $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

    return $luminance > 0.5 ? '#111827' : '#FFFFFF';
}

function portal_mix_hex_colors(string $foreground, string $background, float $ratio): string
{
    $ratio = max(0.0, min(1.0, $ratio));
    [$fr, $fg, $fb] = portal_hex_to_rgb($foreground);
    [$br, $bg, $bb] = portal_hex_to_rgb($background);

    $rr = (int) round($fr * (1 - $ratio) + $br * $ratio);
    $rg = (int) round($fg * (1 - $ratio) + $bg * $ratio);
    $rb = (int) round($fb * (1 - $ratio) + $bb * $ratio);

    return sprintf('#%02X%02X%02X', $rr, $rg, $rb);
}

function portal_build_palette_entry(string $background, ?string $text = null, ?string $muted = null): array
{
    $background = portal_sanitize_hex_color($background, '#000000');
    $text = $text !== null ? portal_sanitize_hex_color($text, '#000000') : portal_calculate_contrast_text($background);
    $muted = $muted !== null ? portal_sanitize_hex_color($muted, $text) : portal_mix_hex_colors($text, $background, 0.65);

    return [
        'background' => $background,
        'text' => $text,
        'muted' => $muted,
    ];
}

function portal_parse_datetime(?string $value): ?int
{
    if (!is_string($value)) {
        return null;
    }

    $trimmed = trim($value);
    if ($trimmed === '') {
        return null;
    }

    $timestamp = strtotime($trimmed);
    if ($timestamp === false) {
        return null;
    }

    return $timestamp;
}

function portal_format_datetime(?int $timestamp, string $format = 'j M Y, g:i A'): string
{
    if ($timestamp === null || $timestamp <= 0) {
        return '';
    }

    return date($format, $timestamp);
}

function portal_slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '';

    return trim($value, '-');
}

function portal_normalize_column_key(?string $value, ?string $label = null): string
{
    $candidate = strtolower(trim((string) $value));
    $candidate = preg_replace('/[^a-z0-9_]+/', '_', $candidate) ?? '';
    $candidate = trim($candidate, '_');

    if ($candidate !== '') {
        return $candidate;
    }

    if ($label !== null) {
        $labelCandidate = strtolower(trim((string) $label));
        $labelCandidate = preg_replace('/[^a-z0-9_]+/', '_', $labelCandidate) ?? '';
        $labelCandidate = trim($labelCandidate, '_');

        if ($labelCandidate !== '') {
            return $labelCandidate;
        }
    }

    return '';
}

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
            'announcement' => 'Energy independence for every household and MSME in Jharkhand.',
            'palette' => [
                'page' => portal_build_palette_entry('#0B1120', '#F8FAFC'),
                'hero' => portal_build_palette_entry('#0B1120', '#F8FAFC'),
                'surface' => portal_build_palette_entry('#FFFFFF', '#0F172A'),
                'section' => portal_build_palette_entry('#F1F5F9', '#0F172A'),
                'callout' => portal_build_palette_entry('#2563EB', '#FFFFFF'),
                'footer' => portal_build_palette_entry('#111827', '#E2E8F0'),
                'accent' => portal_build_palette_entry('#2563EB', '#FFFFFF'),
            ],
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
        'home_sections' => [
            [
                'id' => 'sec_story_highlight',
                'eyebrow' => 'Dynamic section',
                'title' => 'Share your latest success on the homepage',
                'subtitle' => 'Use this fully editable block to promote a new milestone, partnership, or subsidy announcement without touching code.',
                'body' => [
                    'Edit this content from the admin console to publish campaigns in minutes. Add product explainers, subsidy updates, or success stories tailored to Jharkhand households and MSMEs.',
                    'You can also pair this section with an optional image and call-to-action button. Duplicate the block for seasonal promotions or upcoming events.',
                ],
                'bullets' => [
                    'Highlight offers, installation drives, or financing tie-ups instantly.',
                    'Assign background themes that follow your selected colour palette.',
                ],
                'cta' => [
                    'text' => 'Talk to our experts',
                    'url' => 'contact.html',
                ],
                'media' => [
                    'type' => 'image',
                    'src' => 'images/hero/hero.png',
                    'alt' => 'Dakshayani rooftop installation team',
                ],
                'background_style' => 'section',
                'display_order' => 100,
                'status' => 'draft',
                'updated_at' => date('c'),
            ],
        ],
        'home_offers' => [],
        'testimonials' => [],
        'blog_posts' => [],
        'case_studies' => [],
        'customer_registry' => [
            'segments' => [
                'potential' => [
                    'label' => 'Potential customers',
                    'description' => 'Pre-installation leads, enquiries, and follow-up reminders.',
                    'columns' => [
                        ['key' => 'prospect_name', 'label' => 'Prospect name', 'type' => 'text'],
                        ['key' => 'contact_number', 'label' => 'Contact number', 'type' => 'phone'],
                        ['key' => 'city', 'label' => 'City / Town', 'type' => 'text'],
                        ['key' => 'requirements', 'label' => 'Requirements', 'type' => 'text'],
                        ['key' => 'next_follow_up', 'label' => 'Next follow-up', 'type' => 'date'],
                        ['key' => 'last_contacted_on', 'label' => 'Last contacted on', 'type' => 'date'],
                        ['key' => 'assigned_owner', 'label' => 'Owner (Installer / Referrer)', 'type' => 'text'],
                        ['key' => 'reminder_notes', 'label' => 'Reminder notes', 'type' => 'text'],
                    ],
                    'entries' => [],
                ],
                'active' => [
                    'label' => 'In-progress installations',
                    'description' => 'Customers currently in design, approvals, or commissioning.',
                    'columns' => [
                        ['key' => 'consumer_number', 'label' => 'Consumer number', 'type' => 'text'],
                        ['key' => 'project_stage', 'label' => 'Stage', 'type' => 'text'],
                        ['key' => 'actual_bill_date', 'label' => 'Actual bill date', 'type' => 'date'],
                        ['key' => 'gst_bill_date', 'label' => 'GST bill date', 'type' => 'date'],
                        ['key' => 'mobile_number', 'label' => 'Mobile number', 'type' => 'phone'],
                        ['key' => 'consumer_login_id', 'label' => 'Consumer login ID', 'type' => 'text'],
                        ['key' => 'installer_lead', 'label' => 'Installer in charge', 'type' => 'text'],
                        ['key' => 'referrer', 'label' => 'Referrer', 'type' => 'text'],
                        ['key' => 'account_manager', 'label' => 'Account manager', 'type' => 'text'],
                    ],
                    'entries' => [],
                ],
                'support' => [
                    'label' => 'Post-installation & complaints',
                    'description' => 'Service tickets, AMC requests, and complaint resolution tracking.',
                    'columns' => [
                        ['key' => 'ticket_number', 'label' => 'Ticket number', 'type' => 'text'],
                        ['key' => 'consumer_number', 'label' => 'Consumer number', 'type' => 'text'],
                        ['key' => 'issue_summary', 'label' => 'Issue summary', 'type' => 'text'],
                        ['key' => 'opened_on', 'label' => 'Opened on', 'type' => 'date'],
                        ['key' => 'status', 'label' => 'Status', 'type' => 'text'],
                        ['key' => 'assigned_employee', 'label' => 'Assigned employee', 'type' => 'text'],
                        ['key' => 'resolution_target', 'label' => 'Resolution target', 'type' => 'date'],
                        ['key' => 'last_update', 'label' => 'Last update', 'type' => 'date'],
                    ],
                    'entries' => [],
                ],
                'completed' => [
                    'label' => 'Completed installations',
                    'description' => 'Capture commissioned rooftop projects with subsidy and billing tracking. Add single installations or bulk upload the CSV template to keep every MNRE/JREDA milestone on record.',
                    'columns' => [
                        ['key' => 'date_of_application', 'label' => 'Date of application', 'type' => 'date'],
                        ['key' => 'application_number', 'label' => 'Application number', 'type' => 'text'],
                        ['key' => 'discom_name', 'label' => 'DISCOM name', 'type' => 'text'],
                        ['key' => 'consumer_number', 'label' => 'Consumer number', 'type' => 'text'],
                        ['key' => 'consumer_name', 'label' => 'Consumer name', 'type' => 'text'],
                        ['key' => 'mobile_number', 'label' => 'Mobile number', 'type' => 'phone'],
                        ['key' => 'capacity_kwp_installed', 'label' => 'Capacity (kWp) installed', 'type' => 'number'],
                        ['key' => 'installation_date', 'label' => 'Installation date', 'type' => 'date'],
                        ['key' => 'solar_system_type', 'label' => 'Type of solar system (ongrid/hybrid/offgrid)', 'type' => 'text'],
                        ['key' => 'subsidy_disbursed', 'label' => 'Subsidy disbursed', 'type' => 'text'],
                        ['key' => 'subsidy_disbursal_date', 'label' => 'Subsidy disbursal date', 'type' => 'date'],
                        ['key' => 'actual_bill_date', 'label' => 'Actual bill date', 'type' => 'date'],
                        ['key' => 'gst_bill_date', 'label' => 'GST bill date', 'type' => 'date'],
                    ],
                    'entries' => [],
                ],
            ],
            'last_import' => null,
        ],
        'team_directory' => [
            'installers' => [
                [
                    'id' => 'dir_installer_sample',
                    'name' => 'Mukesh Kumar',
                    'phone' => '+91 88775 00110',
                    'email' => 'mukesh@dakshayani.co.in',
                    'region' => 'Ranchi & Khunti',
                    'speciality' => 'MNRE certified lead installer',
                    'notes' => 'Handles high-capacity rooftop commissioning and safety audits.',
                    'updated_at' => date('c'),
                ],
            ],
            'referrers' => [
                [
                    'id' => 'dir_referrer_sample',
                    'name' => 'Anita Sharma',
                    'phone' => '+91 99050 22112',
                    'email' => 'anita.partners@dakshayani.co.in',
                    'region' => 'Jamshedpur industrial belt',
                    'speciality' => 'MSME partnerships and subsidy awareness',
                    'notes' => 'Tracks MSME cluster leads and organises awareness drives.',
                    'updated_at' => date('c'),
                ],
            ],
            'employees' => [
                [
                    'id' => 'dir_employee_sample',
                    'name' => 'Raghav Sinha',
                    'phone' => '+91 62030 01452',
                    'email' => 'raghav@dakshayani.co.in',
                    'region' => 'Operations HQ',
                    'speciality' => 'Customer success & subsidy cell',
                    'notes' => 'Coordinates DISCOM submissions, GST invoices, and billing queries.',
                    'updated_at' => date('c'),
                ],
            ],
        ],
        'users' => [],
        'projects' => [],
        'tasks' => [],
        'activity_log' => [],
        'employee_approvals' => [
            'pending' => [],
            'history' => [],
            'counter' => 1100,
        ],
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
        $state['site_theme'] = array_merge($default['site_theme'], $state['site_theme']);
    }

    $state['site_theme']['accent_color'] = portal_sanitize_hex_color(
        $state['site_theme']['accent_color'] ?? $default['site_theme']['accent_color'],
        $default['site_theme']['accent_color']
    );
    $paletteDefault = $default['site_theme']['palette'];
    $paletteInput = $state['site_theme']['palette'] ?? [];
    $normalizedPalette = [];
    if (!is_array($paletteInput)) {
        $paletteInput = [];
    }

    foreach ($paletteDefault as $paletteKey => $paletteEntry) {
        $raw = $paletteInput[$paletteKey] ?? [];
        if (!is_array($raw)) {
            $raw = [];
        }

        $background = portal_sanitize_hex_color($raw['background'] ?? $paletteEntry['background'], $paletteEntry['background']);
        $textCandidate = $raw['text'] ?? $paletteEntry['text'];
        $text = trim((string) $textCandidate) === ''
            ? portal_calculate_contrast_text($background)
            : portal_sanitize_hex_color($textCandidate, $paletteEntry['text']);
        $mutedCandidate = $raw['muted'] ?? $paletteEntry['muted'];
        $muted = trim((string) $mutedCandidate) === ''
            ? portal_mix_hex_colors($text, $background, 0.65)
            : portal_sanitize_hex_color($mutedCandidate, $paletteEntry['muted']);

        $normalizedPalette[$paletteKey] = [
            'background' => $background,
            'text' => $text,
            'muted' => $muted,
        ];
    }

    $state['site_theme']['palette'] = $normalizedPalette;
    $state['site_theme']['accent_color'] = $normalizedPalette['accent']['background'] ?? $state['site_theme']['accent_color'];

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

    if (!isset($state['home_sections']) || !is_array($state['home_sections'])) {
        $state['home_sections'] = $default['home_sections'];
    }

    $state['home_sections'] = array_values(array_filter(array_map(static function ($section) use ($default) {
        if (!is_array($section)) {
            return null;
        }

        $id = isset($section['id']) && is_string($section['id']) && $section['id'] !== ''
            ? $section['id']
            : portal_generate_id('sec_');

        $eyebrow = isset($section['eyebrow']) ? trim((string) $section['eyebrow']) : '';
        $title = isset($section['title']) ? trim((string) $section['title']) : '';
        $subtitle = isset($section['subtitle']) ? trim((string) $section['subtitle']) : '';

        $body = [];
        if (isset($section['body']) && is_array($section['body'])) {
            $body = array_values(array_filter(array_map(static fn($item) => trim((string) $item), $section['body']), static fn($paragraph) => $paragraph !== ''));
        } elseif (isset($section['body']) && is_string($section['body'])) {
            $body = array_values(array_filter(array_map('trim', preg_split("/\n{2,}/", $section['body']) ?: []), static fn($paragraph) => $paragraph !== ''));
        }

        $bullets = [];
        if (isset($section['bullets']) && is_array($section['bullets'])) {
            $bullets = array_values(array_filter(array_map(static fn($bullet) => trim((string) $bullet), $section['bullets']), static fn($bullet) => $bullet !== ''));
        } elseif (isset($section['bullets']) && is_string($section['bullets'])) {
            $lines = preg_split("/\r?\n/", $section['bullets']);
            if ($lines !== false) {
                $bullets = array_values(array_filter(array_map('trim', $lines), static fn($line) => $line !== ''));
            }
        }

        $cta = $section['cta'] ?? [];
        $ctaText = isset($cta['text']) ? trim((string) $cta['text']) : '';
        $ctaUrl = isset($cta['url']) ? trim((string) $cta['url']) : '';

        $media = $section['media'] ?? [];
        if (!is_array($media)) {
            $media = [];
        }
        $mediaType = strtolower(trim((string) ($media['type'] ?? 'none')));
        if (!in_array($mediaType, ['image', 'video', 'none'], true)) {
            $mediaType = 'none';
        }
        $mediaSrc = trim((string) ($media['src'] ?? ''));
        $mediaAlt = trim((string) ($media['alt'] ?? ''));

        $backgroundStyle = strtolower(trim((string) ($section['background_style'] ?? 'section')));
        $allowedBackgrounds = array_keys($default['site_theme']['palette']);
        if (!in_array($backgroundStyle, $allowedBackgrounds, true)) {
            $backgroundStyle = 'section';
        }

        $displayOrder = isset($section['display_order']) ? (int) $section['display_order'] : 0;
        $status = strtolower(trim((string) ($section['status'] ?? 'draft')));
        if (!in_array($status, ['draft', 'published'], true)) {
            $status = 'draft';
        }

        $updatedAt = isset($section['updated_at']) && is_string($section['updated_at']) ? $section['updated_at'] : date('c');

        if ($title === '' && empty($body) && empty($bullets)) {
            return null;
        }

        return [
            'id' => $id,
            'eyebrow' => $eyebrow,
            'title' => $title,
            'subtitle' => $subtitle,
            'body' => $body,
            'bullets' => $bullets,
            'cta' => [
                'text' => $ctaText,
                'url' => $ctaUrl,
            ],
            'media' => [
                'type' => $mediaType,
                'src' => $mediaSrc,
                'alt' => $mediaAlt,
            ],
            'background_style' => $backgroundStyle,
            'display_order' => $displayOrder,
            'status' => $status,
            'updated_at' => $updatedAt,
        ];
    }, $state['home_sections']), static fn($value) => $value !== null));

    if (empty($state['home_sections'])) {
        $state['home_sections'] = $default['home_sections'];
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

    $defaultRegistry = $default['customer_registry'];
    $registry = $state['customer_registry'] ?? $defaultRegistry;
    if (!is_array($registry)) {
        $registry = $defaultRegistry;
    }

    $segmentsInput = $registry['segments'] ?? [];
    if (!is_array($segmentsInput)) {
        $segmentsInput = [];
    }

    $normalizedSegments = [];
    foreach ($segmentsInput as $slug => $segmentData) {
        $normalizedSegments[$slug] = ['slug' => $slug, 'data' => $segmentData];
    }

    foreach ($defaultRegistry['segments'] as $slug => $segmentDefault) {
        if (!isset($normalizedSegments[$slug])) {
            $normalizedSegments[$slug] = ['slug' => $slug, 'data' => $segmentDefault];
        }
    }

    $segments = [];
    foreach ($normalizedSegments as $item) {
        $slug = is_string($item['slug']) && $item['slug'] !== '' ? $item['slug'] : 'segment_' . portal_generate_id('seg_');
        $segment = $item['data'];
        $fallback = $defaultRegistry['segments'][$slug] ?? [
            'label' => ucfirst(str_replace('-', ' ', $slug)),
            'description' => '',
            'columns' => [],
            'entries' => [],
        ];
        if (!is_array($segment)) {
            $segment = $fallback;
        } else {
            $segment = array_merge($fallback, $segment);
        }

        $label = trim((string) ($segment['label'] ?? $fallback['label']));
        $description = trim((string) ($segment['description'] ?? $fallback['description']));

        $columnsRaw = $segment['columns'] ?? [];
        if (!is_array($columnsRaw)) {
            $columnsRaw = [];
        }
        $columnMap = [];
        foreach ($columnsRaw as $column) {
            if (!is_array($column)) {
                continue;
            }
            $labelCandidate = $column['label'] ?? null;
            $key = portal_normalize_column_key($column['key'] ?? '', is_string($labelCandidate) ? $labelCandidate : null);
            if ($key === '') {
                continue;
            }
            $columnMap[$key] = [
                'key' => $key,
                'label' => trim((string) ($labelCandidate ?? ucfirst(str_replace(['-', '_'], ' ', $key)))),
                'type' => in_array($column['type'] ?? 'text', ['text', 'date', 'phone', 'number', 'email'], true) ? ($column['type'] ?? 'text') : 'text',
            ];
        }

        foreach ($fallback['columns'] as $fallbackColumn) {
            if (!is_array($fallbackColumn)) {
                continue;
            }
            $fallbackLabel = $fallbackColumn['label'] ?? null;
            $fallbackKey = portal_normalize_column_key($fallbackColumn['key'] ?? '', is_string($fallbackLabel) ? $fallbackLabel : null);
            if ($fallbackKey === '') {
                continue;
            }
            if (!isset($columnMap[$fallbackKey])) {
                $columnMap[$fallbackKey] = [
                    'key' => $fallbackKey,
                    'label' => trim((string) ($fallbackLabel ?? ucfirst(str_replace(['-', '_'], ' ', $fallbackKey)))),
                    'type' => in_array($fallbackColumn['type'] ?? 'text', ['text', 'date', 'phone', 'number', 'email'], true) ? ($fallbackColumn['type'] ?? 'text') : 'text',
                ];
            }
        }

        $columns = array_values($columnMap);

        $entriesRaw = $segment['entries'] ?? [];
        if (!is_array($entriesRaw)) {
            $entriesRaw = [];
        }

        $entries = [];
        foreach ($entriesRaw as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $id = isset($entry['id']) && is_string($entry['id']) && $entry['id'] !== ''
                ? $entry['id']
                : portal_generate_id('cust_');

            $fields = $entry['fields'] ?? [];
            if (!is_array($fields)) {
                $fields = [];
                foreach ($entry as $key => $value) {
                    if (in_array($key, ['id', 'notes', 'created_at', 'updated_at', 'reminder_on'], true)) {
                        continue;
                    }
                    if (is_string($key)) {
                        $fields[$key] = $value;
                    }
                }
            }

            $normalizedFields = [];
            foreach ($columns as $column) {
                $colKey = $column['key'];
                $rawValue = $fields[$colKey] ?? '';
                if (is_array($rawValue)) {
                    $rawValue = json_encode($rawValue, JSON_UNESCAPED_UNICODE);
                }
                $normalizedFields[$colKey] = trim((string) $rawValue);
            }

            $notes = isset($entry['notes']) ? trim((string) $entry['notes']) : '';
            $reminderOn = isset($entry['reminder_on']) ? trim((string) $entry['reminder_on']) : '';
            $createdAt = isset($entry['created_at']) && is_string($entry['created_at']) ? $entry['created_at'] : date('c');
            $updatedAt = isset($entry['updated_at']) && is_string($entry['updated_at']) ? $entry['updated_at'] : $createdAt;

            $entries[] = [
                'id' => $id,
                'fields' => $normalizedFields,
                'notes' => $notes,
                'reminder_on' => $reminderOn,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ];
        }

        $segments[$slug] = [
            'label' => $label === '' ? ucfirst(str_replace('-', ' ', $slug)) : $label,
            'description' => $description,
            'columns' => $columns,
            'entries' => $entries,
        ];
    }

    $registry['segments'] = $segments;
    $registry['last_import'] = isset($registry['last_import']) && is_string($registry['last_import']) ? $registry['last_import'] : null;
    $state['customer_registry'] = $registry;

    $defaultDirectory = $default['team_directory'];
    $directory = $state['team_directory'] ?? $defaultDirectory;
    if (!is_array($directory)) {
        $directory = $defaultDirectory;
    }

    $directoryCategories = ['installers', 'referrers', 'employees'];
    foreach ($directoryCategories as $category) {
        $entries = $directory[$category] ?? [];
        if (!is_array($entries)) {
            $entries = $defaultDirectory[$category];
        }

        $directory[$category] = array_values(array_filter(array_map(static function ($entry) {
            if (!is_array($entry)) {
                return null;
            }

            $id = isset($entry['id']) && is_string($entry['id']) && $entry['id'] !== ''
                ? $entry['id']
                : portal_generate_id('dir_');

            $name = isset($entry['name']) ? trim((string) $entry['name']) : '';
            if ($name === '') {
                return null;
            }

            $email = isset($entry['email']) ? trim((string) $entry['email']) : '';
            $phone = isset($entry['phone']) ? trim((string) $entry['phone']) : '';
            $region = isset($entry['region']) ? trim((string) $entry['region']) : '';
            $speciality = isset($entry['speciality']) ? trim((string) $entry['speciality']) : '';
            $notes = isset($entry['notes']) ? trim((string) $entry['notes']) : '';
            $updatedAt = isset($entry['updated_at']) && is_string($entry['updated_at']) ? $entry['updated_at'] : date('c');

            return [
                'id' => $id,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'region' => $region,
                'speciality' => $speciality,
                'notes' => $notes,
                'updated_at' => $updatedAt,
            ];
        }, $entries), static fn($value) => $value !== null));

        if (empty($directory[$category])) {
            $directory[$category] = $defaultDirectory[$category];
        }
    }

    $state['team_directory'] = $directory;

    portal_ensure_employee_approvals($state);

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

function portal_ensure_employee_approvals(array &$state): void
{
    $default = portal_default_state()['employee_approvals'];

    if (!isset($state['employee_approvals']) || !is_array($state['employee_approvals'])) {
        $state['employee_approvals'] = $default;
        return;
    }

    $queue = $state['employee_approvals'];

    $pending = $queue['pending'] ?? [];
    if (!is_array($pending)) {
        $pending = [];
    }
    $pending = array_values(array_filter($pending, static function ($entry): bool {
        return is_array($entry) && isset($entry['id']) && is_string($entry['id']) && $entry['id'] !== '';
    }));

    $history = $queue['history'] ?? [];
    if (!is_array($history)) {
        $history = [];
    }
    $history = array_values(array_filter($history, static function ($entry): bool {
        return is_array($entry) && isset($entry['id']) && is_string($entry['id']) && $entry['id'] !== '';
    }));

    $counter = $queue['counter'] ?? $default['counter'];
    if (!is_int($counter)) {
        $counter = (int) $counter;
        if ($counter <= 0) {
            $counter = $default['counter'];
        }
    }

    $state['employee_approvals'] = [
        'pending' => array_slice($pending, 0, 100),
        'history' => array_slice($history, 0, 200),
        'counter' => $counter,
    ];
}

function portal_next_employee_request_id(array &$state): string
{
    portal_ensure_employee_approvals($state);

    $state['employee_approvals']['counter'] += 1;
    $counter = $state['employee_approvals']['counter'];

    return sprintf('APP-%04d', max(0, (int) $counter));
}

function portal_add_employee_request(array &$state, array $request): void
{
    portal_ensure_employee_approvals($state);

    array_unshift($state['employee_approvals']['pending'], $request);
    $state['employee_approvals']['pending'] = array_slice($state['employee_approvals']['pending'], 0, 100);
}

function portal_archive_employee_request(array &$state, array $request): void
{
    portal_ensure_employee_approvals($state);

    array_unshift($state['employee_approvals']['history'], $request);
    $state['employee_approvals']['history'] = array_slice($state['employee_approvals']['history'], 0, 200);
}
