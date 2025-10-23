<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/portal-state.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

const OWNER_EMAIL = 'd.entranchi@gmail.com';
const ADMIN_TICKETS_FILE = __DIR__ . '/server/data/tickets.json';

function flash(string $type, string $message): void
{
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = ['success' => [], 'error' => []];
    }

    $_SESSION['flash'][$type][] = $message;
}

function redirect_with_flash(?string $view = null): void
{
    $target = 'admin-dashboard.php';

    if ($view !== null && $view !== 'overview') {
        $target .= '?view=' . urlencode($view);
    }

    header('Location: ' . $target);
    exit;
}

function parse_newline_list(string $value): array
{
    $lines = preg_split("/\r?\n/", $value);
    if ($lines === false) {
        return [];
    }

    return array_values(array_filter(array_map(static fn($line) => trim((string) $line), $lines), static fn($line) => $line !== ''));
}

function parse_paragraphs(string $value): array
{
    $paragraphs = preg_split("/\n{2,}/", $value);
    if ($paragraphs === false) {
        return [];
    }

    return array_values(array_filter(array_map(static fn($paragraph) => trim((string) $paragraph), $paragraphs), static fn($paragraph) => $paragraph !== ''));
}

function parse_tags_input(string $value): array
{
    $parts = preg_split('/[,\n]+/', $value);
    if ($parts === false) {
        return [];
    }

    return array_values(array_filter(array_map(static fn($tag) => trim((string) $tag), $parts), static fn($tag) => $tag !== ''));
}

function sanitize_slug(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? '';
    return trim($slug, '-');
}

function admin_prepare_palette(array $input, array $defaults): array
{
    $palette = [];

    foreach ($defaults as $key => $defaultEntry) {
        $incoming = $input[$key]['background'] ?? ($input[$key] ?? null);
        $background = portal_sanitize_hex_color(is_string($incoming) ? $incoming : '', $defaultEntry['background']);
        $palette[$key] = portal_build_palette_entry($background);
    }

    return $palette;
}

function admin_mix_color(string $color, string $target, float $ratio): string
{
    return portal_mix_hex_colors($color, $target, $ratio);
}

function admin_parse_csv_file(string $path): array
{
    $rows = [];
    $handle = fopen($path, 'rb');

    if ($handle === false) {
        return $rows;
    }

    while (($data = fgetcsv($handle)) !== false) {
        if ($data === [null] || (count($data) === 1 && trim((string) $data[0]) === '')) {
            continue;
        }
        $rows[] = array_map(static fn($value) => trim((string) $value), $data);
        if (count($rows) >= 1000) {
            break;
        }
    }

    fclose($handle);

    return $rows;
}

function admin_excel_column_index(string $cellReference): int
{
    $letters = strtoupper(preg_replace('/[^A-Z]/', '', $cellReference) ?? '');
    $length = strlen($letters);
    $index = 0;

    for ($i = 0; $i < $length; $i++) {
        $index = $index * 26 + (ord($letters[$i]) - 64);
    }

    return max(0, $index - 1);
}

function admin_parse_xlsx_file(string $path): array
{
    $rows = [];

    if (!class_exists('ZipArchive')) {
        return $rows;
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return $rows;
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml === false) {
        $zip->close();
        return $rows;
    }

    $xml = @simplexml_load_string($sheetXml);
    if ($xml === false) {
        $zip->close();
        return $rows;
    }

    $sharedStrings = [];
    $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedStringsXml !== false) {
        $shared = @simplexml_load_string($sharedStringsXml);
        if ($shared !== false) {
            foreach ($shared->si as $si) {
                $text = '';
                if (isset($si->t)) {
                    $text = (string) $si->t;
                } elseif (isset($si->r)) {
                    foreach ($si->r as $run) {
                        $text .= (string) ($run->t ?? '');
                    }
                }
                $sharedStrings[] = trim((string) $text);
            }
        }
    }

    foreach ($xml->sheetData->row as $row) {
        $cells = [];
        foreach ($row->c as $cell) {
            $ref = (string) $cell['r'];
            $index = admin_excel_column_index($ref);
            $type = (string) $cell['t'];
            $value = '';

            if ($type === 's') {
                $lookup = isset($cell->v) ? (int) $cell->v : null;
                $value = $lookup !== null ? ($sharedStrings[$lookup] ?? '') : '';
            } elseif ($type === 'inlineStr' && isset($cell->is->t)) {
                $value = (string) $cell->is->t;
            } else {
                $value = isset($cell->v) ? (string) $cell->v : '';
            }

            $cells[$index] = trim($value);
        }

        if ($cells === []) {
            continue;
        }

        $maxIndex = max(array_keys($cells));
        $rowValues = [];
        for ($i = 0; $i <= $maxIndex; $i++) {
            $rowValues[] = $cells[$i] ?? '';
        }

        if (count(array_filter($rowValues, static fn($value) => $value !== '')) === 0) {
            continue;
        }

        $rows[] = $rowValues;
        if (count($rows) >= 1000) {
            break;
        }
    }

    $zip->close();

    return $rows;
}

function admin_read_tickets(): array
{
    if (!file_exists(ADMIN_TICKETS_FILE)) {
        return [];
    }

    $json = file_get_contents(ADMIN_TICKETS_FILE);
    if ($json === false || $json === '') {
        return [];
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return [];
    }

    return array_values(array_filter($decoded, static fn($ticket) => is_array($ticket)));
}

function admin_prepare_recent_complaints(array $tickets, int $limit = 8): array
{
    $prepared = [];

    foreach ($tickets as $ticket) {
        if (!is_array($ticket)) {
            continue;
        }

        $createdAt = $ticket['createdAt'] ?? $ticket['created_at'] ?? '';
        $createdTimestamp = $createdAt !== '' ? strtotime($createdAt) : false;
        $statusLabel = ucfirst(strtolower((string) ($ticket['status'] ?? 'open')));
        $priorityLabel = ucfirst(strtolower((string) ($ticket['priority'] ?? 'medium')));
        $issueLabels = [];
        if (isset($ticket['issueLabels']) && is_array($ticket['issueLabels'])) {
            $issueLabels = array_values(array_filter(array_map(static fn($value) => trim((string) $value), $ticket['issueLabels'])));
        }

        $prepared[] = [
            'id' => $ticket['id'] ?? '',
            'subject' => $ticket['subject'] ?? 'Website complaint',
            'status' => $statusLabel,
            'priority' => $priorityLabel,
            'createdAt' => $createdAt,
            'createdAtFormatted' => $createdTimestamp ? date('j M Y, g:i A', $createdTimestamp) : '—',
            'requesterName' => $ticket['requesterName'] ?? 'Customer',
            'requesterPhone' => $ticket['requesterPhone'] ?? '',
            'issueLabels' => $issueLabels,
            'channel' => ucfirst(strtolower((string) ($ticket['channel'] ?? 'web'))),
            'siteAddress' => $ticket['meta']['siteAddress'] ?? '',
        ];
    }

    usort($prepared, static function (array $a, array $b): int {
        return strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? '');
    });

    return array_slice($prepared, 0, $limit);
}

if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
}

$state = portal_load_state();

$viewLabels = [
    'overview' => 'Overview',
    'accounts' => 'Accounts',
    'customers' => 'Customers',
    'approvals' => 'Employee approvals',
    'projects' => 'Projects',
    'tasks' => 'Tasks',
    'content' => 'Content manager',
    'settings' => 'Site settings',
    'activity' => 'Activity log',
];

$currentView = $_GET['view'] ?? 'overview';
if (!array_key_exists($currentView, $viewLabels)) {
    $currentView = 'overview';
}

$actorName = $_SESSION['display_name'] ?? 'Admin';

$validUserRoles = ['installer', 'customer', 'referrer', 'employee'];
$validUserStatuses = ['active', 'pending', 'onboarding', 'suspended', 'disabled'];
$projectStatusOptions = ['on-track', 'planning', 'at-risk', 'delayed', 'completed'];
$taskStatusOptions = ['Pending', 'In progress', 'Blocked', 'Completed'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirectView = $_POST['redirect_view'] ?? $currentView;
    if (!array_key_exists($redirectView, $viewLabels)) {
        $redirectView = 'overview';
    }

    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        flash('error', 'Security token mismatch. Please try again.');
        redirect_with_flash($redirectView);
    }

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'update_site_settings':
            $companyFocus = trim($_POST['company_focus'] ?? '');
            $primaryContact = trim($_POST['primary_contact'] ?? '');
            $supportEmail = filter_var(trim($_POST['support_email'] ?? ''), FILTER_VALIDATE_EMAIL);
            $supportPhone = trim($_POST['support_phone'] ?? '');
            $announcement = trim($_POST['announcement'] ?? '');

            if ($companyFocus === '' || $primaryContact === '' || !$supportEmail) {
                flash('error', 'Please provide a company focus, a primary contact, and a valid support email.');
                redirect_with_flash('settings');
            }

            $state['site_settings'] = [
                'company_focus' => $companyFocus,
                'primary_contact' => $primaryContact,
                'support_email' => $supportEmail,
                'support_phone' => $supportPhone,
                'announcement' => $announcement,
            ];

            portal_record_activity($state, 'Updated public site configuration.', $actorName);

            if (portal_save_state($state)) {
                flash('success', 'Site configuration saved.');
            } else {
                flash('error', 'Unable to save the site configuration.');
            }

            redirect_with_flash('settings');

        case 'update_site_theme':
            $themeName = trim($_POST['active_theme'] ?? 'seasonal');
            if ($themeName === '') {
                $themeName = 'seasonal';
            }

            $seasonLabel = trim($_POST['season_label'] ?? '');
            $accentColorInput = trim($_POST['accent_color'] ?? '#2563eb');
            $accentColor = portal_sanitize_hex_color($accentColorInput, $state['site_theme']['palette']['accent']['background'] ?? '#2563EB');
            $backgroundImage = trim($_POST['background_image'] ?? '');
            $themeAnnouncement = trim($_POST['theme_announcement'] ?? '');
            $palettePost = $_POST['palette'] ?? [];
            if (!is_array($palettePost)) {
                $palettePost = [];
            }

            if ($seasonLabel === '') {
                flash('error', 'Provide a headline for the active theme.');
                redirect_with_flash('content');
            }

            $paletteDefaults = $state['site_theme']['palette'] ?? portal_default_state()['site_theme']['palette'];
            $palette = admin_prepare_palette($palettePost, $paletteDefaults);
            $palette['accent'] = portal_build_palette_entry($accentColor);

            $state['site_theme'] = [
                'active_theme' => $themeName,
                'season_label' => $seasonLabel,
                'accent_color' => $accentColor,
                'background_image' => $backgroundImage,
                'announcement' => $themeAnnouncement,
                'palette' => $palette,
            ];

            portal_record_activity($state, 'Updated seasonal theme and styling.', $actorName);

            if (portal_save_state($state)) {
                flash('success', 'Seasonal theme updated successfully.');
            } else {
                flash('error', 'Unable to update the seasonal theme.');
            }

            redirect_with_flash('content');

        case 'reset_site_theme':
            $defaultState = portal_default_state();
            $state['site_theme'] = $defaultState['site_theme'];

            portal_record_activity($state, 'Restored the site theme to default settings.', $actorName);

            if (portal_save_state($state)) {
                flash('success', 'Default theme applied successfully.');
            } else {
                flash('error', 'Unable to restore the default theme.');
            }

            redirect_with_flash('content');

        case 'update_home_hero':
            $heroTitle = trim($_POST['hero_title'] ?? '');
            $heroSubtitle = trim($_POST['hero_subtitle'] ?? '');
            $heroImage = trim($_POST['hero_image'] ?? '');
            $heroImageCaption = trim($_POST['hero_image_caption'] ?? '');
            $heroBubbleHeading = trim($_POST['hero_bubble_heading'] ?? '');
            $heroBubbleBody = trim($_POST['hero_bubble_body'] ?? '');
            $heroBullets = parse_newline_list($_POST['hero_bullets'] ?? '');

            if ($heroTitle === '' || $heroSubtitle === '') {
                flash('error', 'Hero section needs both a title and subtitle.');
                redirect_with_flash('content');
            }

            if ($heroImage === '') {
                $heroImage = $state['home_hero']['image'] ?? 'images/hero/hero.png';
            }

            $state['home_hero'] = [
                'title' => $heroTitle,
                'subtitle' => $heroSubtitle,
                'image' => $heroImage,
                'image_caption' => $heroImageCaption,
                'bubble_heading' => $heroBubbleHeading,
                'bubble_body' => $heroBubbleBody,
                'bullets' => $heroBullets,
            ];

            portal_record_activity($state, 'Updated home page hero messaging.', $actorName);

            if (portal_save_state($state)) {
                flash('success', 'Home hero content updated.');
            } else {
                flash('error', 'Unable to update the hero content.');
            }

            redirect_with_flash('content');

        case 'create_home_section':
            $sectionEyebrow = trim($_POST['section_eyebrow'] ?? '');
            $sectionTitle = trim($_POST['section_title'] ?? '');
            $sectionSubtitle = trim($_POST['section_subtitle'] ?? '');
            $sectionBody = parse_paragraphs($_POST['section_body'] ?? '');
            $sectionBullets = parse_newline_list($_POST['section_bullets'] ?? '');
            $sectionStatus = $_POST['section_status'] ?? 'draft';
            $sectionCtaText = trim($_POST['section_cta_text'] ?? '');
            $sectionCtaUrl = trim($_POST['section_cta_url'] ?? '');
            $sectionMediaType = strtolower(trim($_POST['section_media_type'] ?? 'none'));
            $sectionMediaSrc = trim($_POST['section_media_src'] ?? '');
            $sectionMediaAlt = trim($_POST['section_media_alt'] ?? '');
            $backgroundStyle = strtolower(trim($_POST['section_background_style'] ?? 'section'));
            $displayOrder = (int) ($_POST['section_display_order'] ?? 0);

            if ($sectionTitle === '' && empty($sectionBody) && empty($sectionBullets)) {
                flash('error', 'Provide at least a title or some body content for the section.');
                redirect_with_flash('content');
            }

            if (!in_array($sectionStatus, ['draft', 'published'], true)) {
                $sectionStatus = 'draft';
            }

            $allowedBackgrounds = array_keys($state['site_theme']['palette'] ?? []);
            if (!in_array($backgroundStyle, $allowedBackgrounds, true)) {
                $backgroundStyle = 'section';
            }

            if (!in_array($sectionMediaType, ['image', 'video', 'none'], true)) {
                $sectionMediaType = 'none';
            }

            if ($sectionMediaType === 'none') {
                $sectionMediaSrc = '';
                $sectionMediaAlt = '';
            }

            $state['home_sections'][] = [
                'id' => portal_generate_id('sec_'),
                'eyebrow' => $sectionEyebrow,
                'title' => $sectionTitle,
                'subtitle' => $sectionSubtitle,
                'body' => $sectionBody,
                'bullets' => $sectionBullets,
                'cta' => [
                    'text' => $sectionCtaText,
                    'url' => $sectionCtaUrl,
                ],
                'media' => [
                    'type' => $sectionMediaType,
                    'src' => $sectionMediaSrc,
                    'alt' => $sectionMediaType === 'image' ? $sectionMediaAlt : '',
                ],
                'background_style' => $backgroundStyle,
                'display_order' => $displayOrder,
                'status' => $sectionStatus,
                'updated_at' => date('c'),
            ];

            portal_record_activity($state, "Created homepage section {$sectionTitle}.", $actorName);

            if (portal_save_state($state)) {
                flash('success', 'Homepage section added.');
            } else {
                flash('error', 'Unable to add the new section.');
            }

            redirect_with_flash('content');

        case 'update_home_section':
            $sectionId = $_POST['section_id'] ?? '';
            $sectionEyebrow = trim($_POST['section_eyebrow'] ?? '');
            $sectionTitle = trim($_POST['section_title'] ?? '');
            $sectionSubtitle = trim($_POST['section_subtitle'] ?? '');
            $sectionBody = parse_paragraphs($_POST['section_body'] ?? '');
            $sectionBullets = parse_newline_list($_POST['section_bullets'] ?? '');
            $sectionStatus = $_POST['section_status'] ?? 'draft';
            $sectionCtaText = trim($_POST['section_cta_text'] ?? '');
            $sectionCtaUrl = trim($_POST['section_cta_url'] ?? '');
            $sectionMediaType = strtolower(trim($_POST['section_media_type'] ?? 'none'));
            $sectionMediaSrc = trim($_POST['section_media_src'] ?? '');
            $sectionMediaAlt = trim($_POST['section_media_alt'] ?? '');
            $backgroundStyle = strtolower(trim($_POST['section_background_style'] ?? 'section'));
            $displayOrder = (int) ($_POST['section_display_order'] ?? 0);

            if (!in_array($sectionStatus, ['draft', 'published'], true)) {
                $sectionStatus = 'draft';
            }

            $allowedBackgrounds = array_keys($state['site_theme']['palette'] ?? []);
            if (!in_array($backgroundStyle, $allowedBackgrounds, true)) {
                $backgroundStyle = 'section';
            }

            if (!in_array($sectionMediaType, ['image', 'video', 'none'], true)) {
                $sectionMediaType = 'none';
            }

            if ($sectionMediaType === 'none') {
                $sectionMediaSrc = '';
                $sectionMediaAlt = '';
            }

            $updated = false;
            foreach ($state['home_sections'] as &$section) {
                if (($section['id'] ?? '') === $sectionId) {
                    $section['eyebrow'] = $sectionEyebrow;
                    $section['title'] = $sectionTitle;
                    $section['subtitle'] = $sectionSubtitle;
                    $section['body'] = $sectionBody;
                    $section['bullets'] = $sectionBullets;
                    $section['cta'] = [
                        'text' => $sectionCtaText,
                        'url' => $sectionCtaUrl,
                    ];
                    $section['media'] = [
                        'type' => $sectionMediaType,
                        'src' => $sectionMediaSrc,
                        'alt' => $sectionMediaType === 'image' ? $sectionMediaAlt : '',
                    ];
                    $section['background_style'] = $backgroundStyle;
                    $section['display_order'] = $displayOrder;
                    $section['status'] = $sectionStatus;
                    $section['updated_at'] = date('c');
                    $updated = true;
                    $sectionLabel = $sectionTitle !== '' ? $sectionTitle : $sectionId;
                    portal_record_activity($state, "Updated homepage section {$sectionLabel}.", $actorName);
                    break;
                }
            }
            unset($section);

            if (!$updated) {
                flash('error', 'Section not found.');
                redirect_with_flash('content');
            }

            if (portal_save_state($state)) {
                flash('success', 'Homepage section updated.');
            } else {
                flash('error', 'Unable to update the section.');
            }

            redirect_with_flash('content');

        case 'delete_home_section':
            $sectionId = $_POST['section_id'] ?? '';
            $initialCount = count($state['home_sections']);
            $state['home_sections'] = array_values(array_filter(
                $state['home_sections'],
                static fn(array $section): bool => ($section['id'] ?? '') !== $sectionId
            ));

            if ($initialCount === count($state['home_sections'])) {
                flash('error', 'Section already removed or not found.');
                redirect_with_flash('content');
            }

            portal_record_activity($state, 'Deleted a homepage section.', $actorName);

            if (portal_save_state($state)) {
                flash('success', 'Homepage section deleted.');
            } else {
                flash('error', 'Unable to delete the section.');
            }

            redirect_with_flash('content');

        case 'create_offer':
            $offerTitle = trim($_POST['offer_title'] ?? '');
            $offerDescription = trim($_POST['offer_description'] ?? '');
            $offerBadge = trim($_POST['offer_badge'] ?? '');
            $offerStarts = trim($_POST['offer_starts_on'] ?? '');
            $offerEnds = trim($_POST['offer_ends_on'] ?? '');
            $offerStatus = $_POST['offer_status'] ?? 'draft';
            $offerImage = trim($_POST['offer_image'] ?? '');
            $offerCtaText = trim($_POST['offer_cta_text'] ?? '');
            $offerCtaUrl = trim($_POST['offer_cta_url'] ?? '');

            if ($offerTitle === '' && $offerDescription === '') {
                flash('error', 'Provide at least a title or description for the offer.');
                redirect_with_flash('content');
            }

            if (!in_array($offerStatus, ['draft', 'published'], true)) {
                $offerStatus = 'draft';
            }

            $state['home_offers'][] = [
                'id' => portal_generate_id('off_'),
                'title' => $offerTitle,
                'description' => $offerDescription,
                'badge' => $offerBadge,
                'starts_on' => $offerStarts,
                'ends_on' => $offerEnds,
                'status' => $offerStatus,
                'image' => $offerImage,
                'cta_text' => $offerCtaText,
                'cta_url' => $offerCtaUrl,
                'updated_at' => date('c'),
            ];

            portal_record_activity($state, 'Created a new seasonal offer.', $actorName);

            if (portal_save_state($state)) {
                flash('success', 'Offer added to the home page.');
            } else {
                flash('error', 'Unable to add the new offer.');
            }

            redirect_with_flash('content');

        case 'update_offer':
            $offerId = $_POST['offer_id'] ?? '';
            $offerTitle = trim($_POST['offer_title'] ?? '');
            $offerDescription = trim($_POST['offer_description'] ?? '');
            $offerBadge = trim($_POST['offer_badge'] ?? '');
            $offerStarts = trim($_POST['offer_starts_on'] ?? '');
            $offerEnds = trim($_POST['offer_ends_on'] ?? '');
            $offerStatus = $_POST['offer_status'] ?? 'draft';
            $offerImage = trim($_POST['offer_image'] ?? '');
            $offerCtaText = trim($_POST['offer_cta_text'] ?? '');
            $offerCtaUrl = trim($_POST['offer_cta_url'] ?? '');

            if (!in_array($offerStatus, ['draft', 'published'], true)) {
                $offerStatus = 'draft';
            }

            $updated = false;
            foreach ($state['home_offers'] as &$offer) {
                if (($offer['id'] ?? '') === $offerId) {
                    $offer['title'] = $offerTitle;
                    $offer['description'] = $offerDescription;
                    $offer['badge'] = $offerBadge;
                    $offer['starts_on'] = $offerStarts;
                    $offer['ends_on'] = $offerEnds;
                    $offer['status'] = $offerStatus;
                    $offer['image'] = $offerImage;
                    $offer['cta_text'] = $offerCtaText;
                    $offer['cta_url'] = $offerCtaUrl;
                    $offer['updated_at'] = date('c');
                    $updated = true;
                    portal_record_activity($state, "Updated seasonal offer {$offer['title']}.", $actorName);
                    break;
                }
            }
            unset($offer);

            if (!$updated) {
                flash('error', 'Offer not found.');
                redirect_with_flash('content');
            }

            if (portal_save_state($state)) {
                flash('success', 'Offer updated successfully.');
            } else {
                flash('error', 'Unable to update the offer.');
            }

            redirect_with_flash('content');

        case 'delete_offer':
            $offerId = $_POST['offer_id'] ?? '';
            $initialCount = count($state['home_offers']);
            $state['home_offers'] = array_values(array_filter(
                $state['home_offers'],
                static fn(array $offer): bool => ($offer['id'] ?? '') !== $offerId
            ));

            if ($initialCount === count($state['home_offers'])) {
                flash('error', 'Offer already removed or not found.');
                redirect_with_flash('content');
            }

            portal_record_activity($state, 'Deleted a seasonal offer.', $actorName);

            if (portal_save_state($state)) {
                flash('success', 'Offer removed from the home page.');
            } else {
                flash('error', 'Unable to delete the offer.');
            }

            redirect_with_flash('content');

        case 'create_testimonial':
            $testimonialQuote = trim($_POST['testimonial_quote'] ?? '');
            $testimonialName = trim($_POST['testimonial_name'] ?? '');
            $testimonialRole = trim($_POST['testimonial_role'] ?? '');
            $testimonialLocation = trim($_POST['testimonial_location'] ?? '');
            $testimonialImage = trim($_POST['testimonial_image'] ?? '');
            $testimonialStatus = $_POST['testimonial_status'] ?? 'published';

            if ($testimonialQuote === '' || $testimonialName === '') {
                flash('error', 'Testimonials require a quote and a customer name.');
                redirect_with_flash('content');
            }

            if (!in_array($testimonialStatus, ['draft', 'published'], true)) {
                $testimonialStatus = 'published';
            }

            $state['testimonials'][] = [
                'id' => portal_generate_id('tes_'),
                'quote' => $testimonialQuote,
                'name' => $testimonialName,
                'role' => $testimonialRole,
                'location' => $testimonialLocation,
                'image' => $testimonialImage,
                'status' => $testimonialStatus,
                'updated_at' => date('c'),
            ];

            portal_record_activity($state, 'Added a new testimonial.', $actorName);

            if (portal_save_state($state)) {
                flash('success', 'Testimonial published.');
            } else {
                flash('error', 'Unable to add the testimonial.');
            }

            redirect_with_flash('content');

        case 'update_testimonial':
            $testimonialId = $_POST['testimonial_id'] ?? '';
            $testimonialQuote = trim($_POST['testimonial_quote'] ?? '');
            $testimonialName = trim($_POST['testimonial_name'] ?? '');
            $testimonialRole = trim($_POST['testimonial_role'] ?? '');
            $testimonialLocation = trim($_POST['testimonial_location'] ?? '');
            $testimonialImage = trim($_POST['testimonial_image'] ?? '');
            $testimonialStatus = $_POST['testimonial_status'] ?? 'published';

            if (!in_array($testimonialStatus, ['draft', 'published'], true)) {
                $testimonialStatus = 'published';
            }

            $updated = false;
            foreach ($state['testimonials'] as &$testimonial) {
                if (($testimonial['id'] ?? '') === $testimonialId) {
                    if ($testimonialQuote === '' || $testimonialName === '') {
                        flash('error', 'Testimonials require a quote and a customer name.');
                        redirect_with_flash('content');
                    }

                    $testimonial['quote'] = $testimonialQuote;
                    $testimonial['name'] = $testimonialName;
                    $testimonial['role'] = $testimonialRole;
                    $testimonial['location'] = $testimonialLocation;
                    $testimonial['image'] = $testimonialImage;
                    $testimonial['status'] = $testimonialStatus;
                    $testimonial['updated_at'] = date('c');
                    portal_record_activity($state, "Updated testimonial from {$testimonialName}.", $actorName);
                    $updated = true;
                    break;
                }
            }
            unset($testimonial);

            if (!$updated) {
                flash('error', 'Testimonial not found.');
                redirect_with_flash('content');
            }

            if (portal_save_state($state)) {
                flash('success', 'Testimonial updated successfully.');
            } else {
                flash('error', 'Unable to update the testimonial.');
            }

            redirect_with_flash('content');

        case 'delete_testimonial':
            $testimonialId = $_POST['testimonial_id'] ?? '';
            $initialCount = count($state['testimonials']);
            $state['testimonials'] = array_values(array_filter(
                $state['testimonials'],
                static fn(array $testimonial): bool => ($testimonial['id'] ?? '') !== $testimonialId
            ));

            if ($initialCount === count($state['testimonials'])) {
                flash('error', 'Testimonial already removed or not found.');
                redirect_with_flash('content');
            }

            portal_record_activity($state, 'Removed a testimonial.', $actorName);

            if (portal_save_state($state)) {
                flash('success', 'Testimonial removed.');
            } else {
                flash('error', 'Unable to remove the testimonial.');
            }

            redirect_with_flash('content');

        case 'create_blog_post':
            $postTitle = trim($_POST['post_title'] ?? '');
            $postSlugInput = trim($_POST['post_slug'] ?? '');
            $postSlug = $postSlugInput !== '' ? sanitize_slug($postSlugInput) : sanitize_slug($postTitle);
            $postExcerpt = trim($_POST['post_excerpt'] ?? '');
            $postHeroImage = trim($_POST['post_hero_image'] ?? '');
            $postTags = parse_tags_input($_POST['post_tags'] ?? '');
            $postStatus = $_POST['post_status'] ?? 'draft';
            $postReadTime = (int) ($_POST['post_read_time'] ?? 0);
            $postAuthorName = trim($_POST['post_author_name'] ?? '');
            $postAuthorRole = trim($_POST['post_author_role'] ?? '');
            $postContent = parse_paragraphs($_POST['post_content'] ?? '');

            if ($postTitle === '' || $postSlug === '' || empty($postContent)) {
                flash('error', 'Blog posts need a title, slug, and body content.');
                redirect_with_flash('content');
            }

            foreach ($state['blog_posts'] as $existingPost) {
                if (strcasecmp($existingPost['slug'] ?? '', $postSlug) === 0) {
                    flash('error', 'Another blog post already uses this slug.');
                    redirect_with_flash('content');
                }
            }

            if (!in_array($postStatus, ['draft', 'published'], true)) {
                $postStatus = 'draft';
            }

            $nowIso = date('c');

            $state['blog_posts'][] = [
                'id' => portal_generate_id('blog_'),
                'title' => $postTitle,
                'slug' => $postSlug,
                'excerpt' => $postExcerpt,
                'hero_image' => $postHeroImage,
                'tags' => $postTags,
                'status' => $postStatus,
                'read_time_minutes' => $postReadTime > 0 ? $postReadTime : null,
                'author' => [
                    'name' => $postAuthorName,
                    'role' => $postAuthorRole,
                ],
                'content' => $postContent,
                'published_at' => $postStatus === 'published' ? $nowIso : null,
                'updated_at' => $nowIso,
            ];

            portal_record_activity($state, "Drafted blog post {$postTitle}.", $actorName);

            if (portal_save_state($state)) {
                flash('success', 'Blog post saved.');
            } else {
                flash('error', 'Unable to save the blog post.');
            }

            redirect_with_flash('content');

        case 'update_blog_post':
            $postId = $_POST['post_id'] ?? '';
            $postTitle = trim($_POST['post_title'] ?? '');
            $postSlugInput = trim($_POST['post_slug'] ?? '');
            $postSlug = $postSlugInput !== '' ? sanitize_slug($postSlugInput) : sanitize_slug($postTitle);
            $postExcerpt = trim($_POST['post_excerpt'] ?? '');
            $postHeroImage = trim($_POST['post_hero_image'] ?? '');
            $postTags = parse_tags_input($_POST['post_tags'] ?? '');
            $postStatus = $_POST['post_status'] ?? 'draft';
            $postReadTime = (int) ($_POST['post_read_time'] ?? 0);
            $postAuthorName = trim($_POST['post_author_name'] ?? '');
            $postAuthorRole = trim($_POST['post_author_role'] ?? '');
            $postContent = parse_paragraphs($_POST['post_content'] ?? '');

            if ($postTitle === '' || $postSlug === '' || empty($postContent)) {
                flash('error', 'Blog posts need a title, slug, and body content.');
                redirect_with_flash('content');
            }

            if (!in_array($postStatus, ['draft', 'published'], true)) {
                $postStatus = 'draft';
            }

            $nowIso = date('c');
            $updated = false;
            foreach ($state['blog_posts'] as &$post) {
                if (($post['id'] ?? '') === $postId) {
                    foreach ($state['blog_posts'] as $otherPost) {
                        if ($otherPost['id'] !== $postId && strcasecmp($otherPost['slug'] ?? '', $postSlug) === 0) {
                            flash('error', 'Another blog post already uses this slug.');
                            redirect_with_flash('content');
                        }
                    }

                    $post['title'] = $postTitle;
                    $post['slug'] = $postSlug;
                    $post['excerpt'] = $postExcerpt;
                    $post['hero_image'] = $postHeroImage;
                    $post['tags'] = $postTags;
                    $post['status'] = $postStatus;
                    $post['read_time_minutes'] = $postReadTime > 0 ? $postReadTime : null;
                    $post['author'] = [
                        'name' => $postAuthorName,
                        'role' => $postAuthorRole,
                    ];
                    $post['content'] = $postContent;
                    if ($postStatus === 'published' && empty($post['published_at'])) {
                        $post['published_at'] = $nowIso;
                    }
                    if ($postStatus === 'draft') {
                        $post['published_at'] = null;
                    }
                    $post['updated_at'] = $nowIso;
                    portal_record_activity($state, "Updated blog post {$postTitle}.", $actorName);
                    $updated = true;
                    break;
                }
            }
            unset($post);

            if (!$updated) {
                flash('error', 'Blog post not found.');
                redirect_with_flash('content');
            }

            if (portal_save_state($state)) {
                flash('success', 'Blog post updated.');
            } else {
                flash('error', 'Unable to update the blog post.');
            }

            redirect_with_flash('content');

        case 'delete_blog_post':
            $postId = $_POST['post_id'] ?? '';
            $initialCount = count($state['blog_posts']);
            $state['blog_posts'] = array_values(array_filter(
                $state['blog_posts'],
                static fn(array $post): bool => ($post['id'] ?? '') !== $postId
            ));

            if ($initialCount === count($state['blog_posts'])) {
                flash('error', 'Blog post already removed or not found.');
                redirect_with_flash('content');
            }

            portal_record_activity($state, 'Deleted a blog post.', $actorName);

            if (portal_save_state($state)) {
                flash('success', 'Blog post removed.');
            } else {
                flash('error', 'Unable to remove the blog post.');
            }

            redirect_with_flash('content');

        case 'create_case_study':
            $caseTitle = trim($_POST['case_title'] ?? '');
            $caseSegment = strtolower(trim($_POST['case_segment'] ?? 'residential'));
            $caseLocation = trim($_POST['case_location'] ?? '');
            $caseSummary = trim($_POST['case_summary'] ?? '');
            $caseCapacity = (float) ($_POST['case_capacity_kw'] ?? 0);
            $caseGeneration = (float) ($_POST['case_generation_kwh'] ?? 0);
            $caseCo2 = (float) ($_POST['case_co2_tonnes'] ?? 0);
            $casePayback = (float) ($_POST['case_payback_years'] ?? 0);
            $caseHighlights = parse_newline_list($_POST['case_highlights'] ?? '');
            $caseImageSrc = trim($_POST['case_image'] ?? '');
            $caseImageAlt = trim($_POST['case_image_alt'] ?? '');
            $caseStatus = $_POST['case_status'] ?? 'published';

            $allowedSegments = ['residential', 'commercial', 'industrial', 'agriculture'];
            if (!in_array($caseSegment, $allowedSegments, true)) {
                $caseSegment = 'residential';
            }

            if ($caseTitle === '' || $caseSummary === '') {
                flash('error', 'Case studies require a title and summary.');
                redirect_with_flash('content');
            }

            if (!in_array($caseStatus, ['draft', 'published'], true)) {
                $caseStatus = 'published';
            }

            $nowIso = date('c');
            $state['case_studies'][] = [
                'id' => portal_generate_id('case_'),
                'title' => $caseTitle,
                'segment' => $caseSegment,
                'location' => $caseLocation,
                'summary' => $caseSummary,
                'capacity_kw' => $caseCapacity,
                'annual_generation_kwh' => $caseGeneration,
                'co2_offset_tonnes' => $caseCo2,
                'payback_years' => $casePayback,
                'highlights' => $caseHighlights,
                'image' => [
                    'src' => $caseImageSrc,
                    'alt' => $caseImageAlt,
                ],
                'status' => $caseStatus,
                'published_at' => $caseStatus === 'published' ? $nowIso : null,
                'updated_at' => $nowIso,
            ];

            portal_record_activity($state, "Added case study {$caseTitle}.", $actorName);

            if (portal_save_state($state)) {
                flash('success', 'Case study added.');
            } else {
                flash('error', 'Unable to add the case study.');
            }

            redirect_with_flash('content');

        case 'update_case_study':
            $caseId = $_POST['case_id'] ?? '';
            $caseTitle = trim($_POST['case_title'] ?? '');
            $caseSegment = strtolower(trim($_POST['case_segment'] ?? 'residential'));
            $caseLocation = trim($_POST['case_location'] ?? '');
            $caseSummary = trim($_POST['case_summary'] ?? '');
            $caseCapacity = (float) ($_POST['case_capacity_kw'] ?? 0);
            $caseGeneration = (float) ($_POST['case_generation_kwh'] ?? 0);
            $caseCo2 = (float) ($_POST['case_co2_tonnes'] ?? 0);
            $casePayback = (float) ($_POST['case_payback_years'] ?? 0);
            $caseHighlights = parse_newline_list($_POST['case_highlights'] ?? '');
            $caseImageSrc = trim($_POST['case_image'] ?? '');
            $caseImageAlt = trim($_POST['case_image_alt'] ?? '');
            $caseStatus = $_POST['case_status'] ?? 'published';

            $allowedSegments = ['residential', 'commercial', 'industrial', 'agriculture'];
            if (!in_array($caseSegment, $allowedSegments, true)) {
                $caseSegment = 'residential';
            }

            if (!in_array($caseStatus, ['draft', 'published'], true)) {
                $caseStatus = 'published';
            }

            $nowIso = date('c');
            $updated = false;
            foreach ($state['case_studies'] as &$case) {
                if (($case['id'] ?? '') === $caseId) {
                    if ($caseTitle === '' || $caseSummary === '') {
                        flash('error', 'Case studies require a title and summary.');
                        redirect_with_flash('content');
                    }

                    $case['title'] = $caseTitle;
                    $case['segment'] = $caseSegment;
                    $case['location'] = $caseLocation;
                    $case['summary'] = $caseSummary;
                    $case['capacity_kw'] = $caseCapacity;
                    $case['annual_generation_kwh'] = $caseGeneration;
                    $case['co2_offset_tonnes'] = $caseCo2;
                    $case['payback_years'] = $casePayback;
                    $case['highlights'] = $caseHighlights;
                    $case['image'] = [
                        'src' => $caseImageSrc,
                        'alt' => $caseImageAlt,
                    ];
                    if ($caseStatus === 'published' && empty($case['published_at'])) {
                        $case['published_at'] = $nowIso;
                    }
                    if ($caseStatus === 'draft') {
                        $case['published_at'] = null;
                    }
                    $case['status'] = $caseStatus;
                    $case['updated_at'] = $nowIso;
                    portal_record_activity($state, "Updated case study {$caseTitle}.", $actorName);
                    $updated = true;
                    break;
                }
            }
            unset($case);

            if (!$updated) {
                flash('error', 'Case study not found.');
                redirect_with_flash('content');
            }

            if (portal_save_state($state)) {
                flash('success', 'Case study updated.');
            } else {
                flash('error', 'Unable to update the case study.');
            }

            redirect_with_flash('content');

        case 'delete_case_study':
            $caseId = $_POST['case_id'] ?? '';
            $initialCount = count($state['case_studies']);
            $state['case_studies'] = array_values(array_filter(
                $state['case_studies'],
                static fn(array $case): bool => ($case['id'] ?? '') !== $caseId
            ));

            if ($initialCount === count($state['case_studies'])) {
                flash('error', 'Case study already removed or not found.');
                redirect_with_flash('content');
            }

            portal_record_activity($state, 'Deleted a case study.', $actorName);

            if (portal_save_state($state)) {
                flash('success', 'Case study removed.');
            } else {
                flash('error', 'Unable to remove the case study.');
            }

            redirect_with_flash('content');

        case 'create_user':
            $name = trim($_POST['name'] ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $role = $_POST['role'] ?? 'employee';
            $phone = trim($_POST['phone'] ?? '');
            $status = $_POST['status'] ?? 'active';
            $notes = trim($_POST['notes'] ?? '');
            $password = $_POST['password'] ?? '';
            $passwordConfirm = $_POST['password_confirm'] ?? '';

            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('error', 'Please provide a name and valid email address.');
                redirect_with_flash('accounts');
            }

            if ($role === 'admin') {
                flash('error', 'Only the owner admin account is allowed. Choose a different role.');
                redirect_with_flash('accounts');
            }

            if (!in_array($role, $validUserRoles, true)) {
                flash('error', 'Choose a valid role for the new account.');
                redirect_with_flash('accounts');
            }

            if (!in_array($status, $validUserStatuses, true)) {
                $status = 'active';
            }

            if ($password === '' || $password !== $passwordConfirm) {
                flash('error', 'Enter a password and confirm it to match.');
                redirect_with_flash('accounts');
            }

            if (strlen($password) < 8) {
                flash('error', 'Passwords must be at least 8 characters long.');
                redirect_with_flash('accounts');
            }

            foreach ($state['users'] as $user) {
                if (strcasecmp($user['email'] ?? '', $email) === 0) {
                    flash('error', 'An account with this email already exists.');
                    redirect_with_flash('accounts');
                }
            }

            $state['users'][] = [
                'id' => portal_generate_id('usr_'),
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'phone' => $phone,
                'status' => $status,
                'notes' => $notes,
                'created_at' => date('Y-m-d'),
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ];

            portal_record_activity($state, "Created $role account for $name.", $actorName);

            if (portal_save_state($state)) {
                flash('success', 'Account created. Share the credentials securely with the user.');
            } else {
                flash('error', 'Unable to save the new account.');
            }

            redirect_with_flash('accounts');

        case 'update_user_status':
            $userId = $_POST['user_id'] ?? '';
            $status = $_POST['status'] ?? 'active';
            $notes = trim($_POST['notes'] ?? '');

            if (!in_array($status, $validUserStatuses, true)) {
                $status = 'active';
            }

            $found = false;
            foreach ($state['users'] as &$user) {
                if (($user['id'] ?? '') === $userId) {
                    if (($user['role'] ?? '') === 'admin' && strcasecmp($user['email'] ?? '', OWNER_EMAIL) === 0) {
                        flash('error', 'The owner admin account cannot be edited here.');
                        redirect_with_flash('accounts');
                    }

                    $user['status'] = $status;
                    if ($notes !== '') {
                        $user['notes'] = $notes;
                    }
                    $found = true;
                    portal_record_activity($state, "Updated {$user['name']}'s portal access.", $actorName);
                    break;
                }
            }
            unset($user);

            if (!$found) {
                flash('error', 'Unable to locate the selected account.');
                redirect_with_flash('accounts');
            }

            if (portal_save_state($state)) {
                flash('success', 'Account details updated.');
            } else {
                flash('error', 'Unable to update the account.');
            }

            redirect_with_flash('accounts');

        case 'reset_user_password':
            $userId = $_POST['user_id'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if ($newPassword === '' || $confirmPassword === '') {
                flash('error', 'Provide and confirm the new password.');
                redirect_with_flash('accounts');
            }

            if ($newPassword !== $confirmPassword) {
                flash('error', 'The passwords do not match.');
                redirect_with_flash('accounts');
            }

            if (strlen($newPassword) < 8) {
                flash('error', 'Passwords must be at least 8 characters long.');
                redirect_with_flash('accounts');
            }

            $updated = false;
            foreach ($state['users'] as &$user) {
                if (($user['id'] ?? '') === $userId) {
                    if (($user['role'] ?? '') === 'admin' && strcasecmp($user['email'] ?? '', OWNER_EMAIL) === 0) {
                        flash('error', 'The owner admin password is managed separately.');
                        redirect_with_flash('accounts');
                    }

                    $user['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
                    portal_record_activity($state, "Reset {$user['name']}'s password.", $actorName);
                    $updated = true;
                    break;
                }
            }
            unset($user);

            if (!$updated) {
                flash('error', 'Unable to locate the selected account.');
                redirect_with_flash('accounts');
            }

            if (portal_save_state($state)) {
                flash('success', 'Password updated. Share the new credentials securely.');
            } else {
                flash('error', 'Unable to reset the password.');
            }

            redirect_with_flash('accounts');

        case 'delete_user':
            $userId = $_POST['user_id'] ?? '';

            $initialCount = count($state['users']);
            $state['users'] = array_values(array_filter(
                $state['users'],
                static function (array $user) use ($userId): bool {
                    if (($user['role'] ?? '') === 'admin' && strcasecmp($user['email'] ?? '', OWNER_EMAIL) === 0) {
                        return true;
                    }

                    return ($user['id'] ?? '') !== $userId;
                }
            ));

            if ($initialCount === count($state['users'])) {
                flash('error', 'Account already removed or not found.');
                redirect_with_flash('accounts');
            }

            portal_record_activity($state, 'Removed a portal account.', $actorName);

            if (portal_save_state($state)) {
                flash('success', 'Account removed successfully.');
            } else {
                flash('error', 'Unable to remove the account.');
            }

            redirect_with_flash('accounts');

        case 'create_project':
            $name = trim($_POST['project_name'] ?? '');
            $owner = trim($_POST['project_owner'] ?? '');
            $stage = trim($_POST['project_stage'] ?? '');
            $status = $_POST['project_status'] ?? 'on-track';
            $targetDate = trim($_POST['target_date'] ?? '');

            if ($name === '' || $owner === '' || $stage === '') {
                flash('error', 'Projects need a name, an owner, and a current stage.');
                redirect_with_flash('projects');
            }

            if (!in_array($status, $projectStatusOptions, true)) {
                $status = 'on-track';
            }

            $state['projects'][] = [
                'id' => portal_generate_id('proj_'),
                'name' => $name,
                'owner' => $owner,
                'stage' => $stage,
                'status' => $status,
                'target_date' => $targetDate,
            ];

            portal_record_activity($state, "Logged new project $name.", $actorName);

            if (portal_save_state($state)) {
                flash('success', 'Project added to the tracker.');
            } else {
                flash('error', 'Unable to save the new project.');
            }

            redirect_with_flash('projects');

        case 'update_project':
            $projectId = $_POST['project_id'] ?? '';
            $stage = trim($_POST['stage'] ?? '');
            $status = $_POST['status'] ?? 'on-track';
            $targetDate = trim($_POST['target_date'] ?? '');

            if (!in_array($status, $projectStatusOptions, true)) {
                $status = 'on-track';
            }

            $updated = false;
            foreach ($state['projects'] as &$project) {
                if (($project['id'] ?? '') === $projectId) {
                    if ($stage !== '') {
                        $project['stage'] = $stage;
                    }
                    $project['status'] = $status;
                    if ($targetDate !== '') {
                        $project['target_date'] = $targetDate;
                    }
                    portal_record_activity($state, "Updated {$project['name']} project details.", $actorName);
                    $updated = true;
                    break;
                }
            }
            unset($project);

            if (!$updated) {
                flash('error', 'Project not found.');
                redirect_with_flash('projects');
            }

            if (portal_save_state($state)) {
                flash('success', 'Project updated successfully.');
            } else {
                flash('error', 'Unable to update the project.');
            }

            redirect_with_flash('projects');

        case 'delete_project':
            $projectId = $_POST['project_id'] ?? '';
            $initialCount = count($state['projects']);
            $state['projects'] = array_values(array_filter(
                $state['projects'],
                static fn(array $project): bool => ($project['id'] ?? '') !== $projectId
            ));

            if ($initialCount === count($state['projects'])) {
                flash('error', 'Project already removed or not found.');
                redirect_with_flash('projects');
            }

            portal_record_activity($state, 'Removed a project from the tracker.', $actorName);

            if (portal_save_state($state)) {
                flash('success', 'Project removed.');
            } else {
                flash('error', 'Unable to remove the project.');
            }

            redirect_with_flash('projects');

        case 'create_task':
            $title = trim($_POST['task_title'] ?? '');
            $assignee = trim($_POST['task_assignee'] ?? '');
            $status = $_POST['task_status'] ?? 'Pending';
            $dueDate = trim($_POST['task_due_date'] ?? '');

            if ($title === '' || $assignee === '') {
                flash('error', 'Tasks require a title and an assignee.');
                redirect_with_flash('tasks');
            }

            if (!in_array($status, $taskStatusOptions, true)) {
                $status = 'Pending';
            }

            $state['tasks'][] = [
                'id' => portal_generate_id('task_'),
                'title' => $title,
                'assignee' => $assignee,
                'status' => $status,
                'due_date' => $dueDate,
            ];

            portal_record_activity($state, "Added new task: $title.", $actorName);

            if (portal_save_state($state)) {
                flash('success', 'Task added to the workboard.');
            } else {
                flash('error', 'Unable to save the new task.');
            }

            redirect_with_flash('tasks');

        case 'update_task':
            $taskId = $_POST['task_id'] ?? '';
            $status = $_POST['status'] ?? 'Pending';
            $dueDate = trim($_POST['due_date'] ?? '');

            if (!in_array($status, $taskStatusOptions, true)) {
                $status = 'Pending';
            }

            $updated = false;
            foreach ($state['tasks'] as &$task) {
                if (($task['id'] ?? '') === $taskId) {
                    $task['status'] = $status;
                    if ($dueDate !== '') {
                        $task['due_date'] = $dueDate;
                    }
                    portal_record_activity($state, "Updated task {$task['title']}.", $actorName);
                    $updated = true;
                    break;
                }
            }
            unset($task);

            if (!$updated) {
                flash('error', 'Task not found.');
                redirect_with_flash('tasks');
            }

            if (portal_save_state($state)) {
                flash('success', 'Task updated successfully.');
            } else {
                flash('error', 'Unable to update the task.');
            }

            redirect_with_flash('tasks');

        case 'delete_task':
            $taskId = $_POST['task_id'] ?? '';
            $initialCount = count($state['tasks']);
            $state['tasks'] = array_values(array_filter(
                $state['tasks'],
                static fn(array $task): bool => ($task['id'] ?? '') !== $taskId
            ));

            if ($initialCount === count($state['tasks'])) {
                flash('error', 'Task already removed or not found.');
                redirect_with_flash('tasks');
            }

            portal_record_activity($state, 'Removed a task from the dashboard.', $actorName);

            if (portal_save_state($state)) {
                flash('success', 'Task removed successfully.');
            } else {
                flash('error', 'Unable to remove the task.');
            }

            redirect_with_flash('tasks');

        case 'add_customer_column':
            $segmentSlug = $_POST['segment'] ?? '';
            $columnLabel = trim($_POST['column_label'] ?? '');
            $columnType = $_POST['column_type'] ?? 'text';

            if (!isset($state['customer_registry']['segments'][$segmentSlug])) {
                flash('error', 'Unknown customer segment.');
                redirect_with_flash('customers');
            }

            if ($columnLabel === '') {
                flash('error', 'Provide a column label.');
                redirect_with_flash('customers');
            }

            $columnType = in_array($columnType, ['text', 'date', 'phone', 'number', 'email'], true) ? $columnType : 'text';

            $segment = &$state['customer_registry']['segments'][$segmentSlug];
            $existingColumns = $segment['columns'] ?? [];
            $columnKeys = [];
            foreach ($existingColumns as $column) {
                if (!is_array($column)) {
                    continue;
                }
                $normalizedKey = portal_normalize_column_key($column['key'] ?? '', $column['label'] ?? null);
                if ($normalizedKey !== '') {
                    $columnKeys[$normalizedKey] = true;
                }
            }

            $baseKey = portal_normalize_column_key('', $columnLabel);
            if ($baseKey === '') {
                $baseKey = 'column';
            }
            $newKey = $baseKey;
            $suffix = 2;
            while (isset($columnKeys[$newKey])) {
                $newKey = $baseKey . '_' . $suffix;
                $suffix++;
            }

            $segment['columns'][] = [
                'key' => $newKey,
                'label' => $columnLabel,
                'type' => $columnType,
            ];

            $columnKeys[$newKey] = true;

            if (!isset($segment['entries']) || !is_array($segment['entries'])) {
                $segment['entries'] = [];
            }

            foreach ($segment['entries'] as &$entry) {
                if (!isset($entry['fields']) || !is_array($entry['fields'])) {
                    $entry['fields'] = [];
                }
                $entry['fields'][$newKey] = $entry['fields'][$newKey] ?? '';
                $entry['updated_at'] = $entry['updated_at'] ?? date('c');
            }
            unset($entry);

            portal_record_activity($state, "Added column {$columnLabel} to {$segment['label']} records.", $actorName);

            if (portal_save_state($state)) {
                flash('success', 'Column added to the customer table.');
            } else {
                flash('error', 'Unable to add the new column.');
            }

            redirect_with_flash('customers');

        case 'create_customer_entry':
            $segmentSlug = $_POST['segment'] ?? '';
            if (!isset($state['customer_registry']['segments'][$segmentSlug])) {
                flash('error', 'Unknown customer segment.');
                redirect_with_flash('customers');
            }

            $segment = &$state['customer_registry']['segments'][$segmentSlug];
            $columns = $segment['columns'] ?? [];
            $fieldsInput = $_POST['fields'] ?? [];
            if (!is_array($fieldsInput)) {
                $fieldsInput = [];
            }

            $normalized = [];
            $hasValue = false;
            foreach ($columns as $column) {
                $key = $column['key'];
                $value = isset($fieldsInput[$key]) ? trim((string) $fieldsInput[$key]) : '';
                $normalized[$key] = $value;
                if ($value !== '') {
                    $hasValue = true;
                }
            }

            $notes = trim($_POST['notes'] ?? '');
            $reminderOn = trim($_POST['reminder_on'] ?? '');

            if (!$hasValue && $notes === '') {
                flash('error', 'Enter at least one field before saving the record.');
                redirect_with_flash('customers');
            }

            $segment['entries'][] = [
                'id' => portal_generate_id('cust_'),
                'fields' => $normalized,
                'notes' => $notes,
                'reminder_on' => $reminderOn,
                'created_at' => date('c'),
                'updated_at' => date('c'),
            ];

            portal_record_activity($state, "Added a {$segment['label']} record.", $actorName);

            if (portal_save_state($state)) {
                flash('success', 'Customer record added.');
            } else {
                flash('error', 'Unable to add the customer record.');
            }

            redirect_with_flash('customers');

        case 'update_customer_entry':
            $segmentSlug = $_POST['segment'] ?? '';
            $entryId = $_POST['entry_id'] ?? '';
            if (!isset($state['customer_registry']['segments'][$segmentSlug])) {
                flash('error', 'Unknown customer segment.');
                redirect_with_flash('customers');
            }

            $segment = &$state['customer_registry']['segments'][$segmentSlug];
            $columns = $segment['columns'] ?? [];
            $fieldsInput = $_POST['fields'] ?? [];
            if (!is_array($fieldsInput)) {
                $fieldsInput = [];
            }

            $updated = false;
            foreach ($segment['entries'] as &$entry) {
                if (($entry['id'] ?? '') === $entryId) {
                    $normalized = [];
                    foreach ($columns as $column) {
                        $key = $column['key'];
                        $normalized[$key] = isset($fieldsInput[$key]) ? trim((string) $fieldsInput[$key]) : '';
                    }
                    $entry['fields'] = $normalized;
                    $entry['notes'] = trim($_POST['notes'] ?? '');
                    $entry['reminder_on'] = trim($_POST['reminder_on'] ?? '');
                    $entry['updated_at'] = date('c');
                    $updated = true;
                    portal_record_activity($state, 'Updated a customer record.', $actorName);
                    break;
                }
            }
            unset($entry);

            if (!$updated) {
                flash('error', 'Customer record not found.');
                redirect_with_flash('customers');
            }

            if (portal_save_state($state)) {
                flash('success', 'Customer record updated.');
            } else {
                flash('error', 'Unable to update the record.');
            }

            redirect_with_flash('customers');

        case 'delete_customer_entry':
            $segmentSlug = $_POST['segment'] ?? '';
            $entryId = $_POST['entry_id'] ?? '';
            if (!isset($state['customer_registry']['segments'][$segmentSlug])) {
                flash('error', 'Unknown customer segment.');
                redirect_with_flash('customers');
            }

            $segment = &$state['customer_registry']['segments'][$segmentSlug];
            $initialCount = count($segment['entries'] ?? []);
            $segment['entries'] = array_values(array_filter(
                $segment['entries'] ?? [],
                static fn(array $entry): bool => ($entry['id'] ?? '') !== $entryId
            ));

            if ($initialCount === count($segment['entries'])) {
                flash('error', 'Customer record already removed or not found.');
                redirect_with_flash('customers');
            }

            portal_record_activity($state, 'Deleted a customer record.', $actorName);

            if (portal_save_state($state)) {
                flash('success', 'Customer record deleted.');
            } else {
                flash('error', 'Unable to delete the record.');
            }

            redirect_with_flash('customers');

        case 'import_customer_segment':
            $segmentSlug = $_POST['segment'] ?? '';
            if (!isset($state['customer_registry']['segments'][$segmentSlug])) {
                flash('error', 'Unknown customer segment.');
                redirect_with_flash('customers');
            }

            if (!isset($_FILES['import_file'])) {
                flash('error', 'Upload a CSV or Excel file to import.');
                redirect_with_flash('customers');
            }

            $file = $_FILES['import_file'];
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                flash('error', 'Unable to read the uploaded file.');
                redirect_with_flash('customers');
            }

            if (!is_uploaded_file($file['tmp_name'] ?? '')) {
                flash('error', 'Upload did not complete correctly. Try again.');
                redirect_with_flash('customers');
            }

            if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
                flash('error', 'Import files must be smaller than 5 MB.');
                redirect_with_flash('customers');
            }

            $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
            if ($extension === 'csv') {
                $rows = admin_parse_csv_file($file['tmp_name']);
            } elseif (in_array($extension, ['xlsx', 'xlsm'], true)) {
                $rows = admin_parse_xlsx_file($file['tmp_name']);
            } else {
                flash('error', 'Only CSV or XLSX files are supported for import.');
                redirect_with_flash('customers');
            }

            if (count($rows) === 0) {
                flash('error', 'The uploaded file was empty.');
                redirect_with_flash('customers');
            }

            $headers = array_map(static fn($value) => trim((string) $value), array_shift($rows));
            $headers = array_values(array_filter($headers, static fn($header) => $header !== ''));

            if (empty($headers)) {
                flash('error', 'The first row must contain column headers.');
                redirect_with_flash('customers');
            }

            $segment = &$state['customer_registry']['segments'][$segmentSlug];
            $columns = $segment['columns'] ?? [];
            $columnIndex = [];
            foreach ($columns as $column) {
                $columnIndex[$column['key']] = $column;
            }

            $headerKeys = [];
            foreach ($headers as $headerLabel) {
                $baseKey = portal_slugify($headerLabel);
                if ($baseKey === '') {
                    $baseKey = 'column';
                }
                $key = $baseKey;
                $suffix = 2;
                while (isset($columnIndex[$key]) || in_array($key, array_column($headerKeys, 'key'), true)) {
                    $key = $baseKey . '-' . $suffix;
                    $suffix++;
                }
                if (!isset($columnIndex[$key])) {
                    $column = ['key' => $key, 'label' => $headerLabel, 'type' => 'text'];
                    $columns[] = $column;
                    $columnIndex[$key] = $column;
                }
                $headerKeys[] = ['key' => $key, 'label' => $headerLabel];
            }

            $segment['columns'] = $columns;

            if (!isset($segment['entries']) || !is_array($segment['entries'])) {
                $segment['entries'] = [];
            }

            $columnKeys = array_map(static fn($column) => $column['key'], $columns);
            foreach ($segment['entries'] as &$entry) {
                if (!isset($entry['fields']) || !is_array($entry['fields'])) {
                    $entry['fields'] = [];
                }
                foreach ($columnKeys as $columnKey) {
                    $entry['fields'][$columnKey] = $entry['fields'][$columnKey] ?? '';
                }
            }
            unset($entry);

            $inserted = 0;
            foreach ($rows as $row) {
                $rowValues = array_map(static fn($value) => trim((string) $value), $row);
                if (count(array_filter($rowValues, static fn($value) => $value !== '')) === 0) {
                    continue;
                }

                $fields = array_fill_keys($columnKeys, '');
                foreach ($headerKeys as $index => $headerMeta) {
                    $fields[$headerMeta['key']] = $rowValues[$index] ?? '';
                }

                $segment['entries'][] = [
                    'id' => portal_generate_id('cust_'),
                    'fields' => $fields,
                    'notes' => '',
                    'reminder_on' => '',
                    'created_at' => date('c'),
                    'updated_at' => date('c'),
                ];
                $inserted++;
            }

            $state['customer_registry']['last_import'] = date('c');
            portal_record_activity($state, "Imported {$inserted} records into {$segment['label']}.", $actorName);

            if ($inserted === 0) {
                flash('error', 'No usable rows were found in the file.');
                redirect_with_flash('customers');
            }

            if (portal_save_state($state)) {
                flash('success', "Imported {$inserted} customer records.");
            } else {
                flash('error', 'Unable to save the imported data.');
            }

            redirect_with_flash('customers');

        case 'create_directory_entry':
            $directoryType = $_POST['directory_type'] ?? '';
            $directoryMap = ['installers' => 'Installer', 'referrers' => 'Referrer', 'employees' => 'Employee'];
            if (!array_key_exists($directoryType, $directoryMap)) {
                flash('error', 'Unknown directory type.');
                redirect_with_flash('customers');
            }

            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $region = trim($_POST['region'] ?? '');
            $speciality = trim($_POST['speciality'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            if ($name === '') {
                flash('error', 'Provide a name for the directory entry.');
                redirect_with_flash('customers');
            }

            $state['team_directory'][$directoryType][] = [
                'id' => portal_generate_id('dir_'),
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'region' => $region,
                'speciality' => $speciality,
                'notes' => $notes,
                'updated_at' => date('c'),
            ];

            portal_record_activity($state, "Added {$directoryMap[$directoryType]} {$name} to the team directory.", $actorName);

            if (portal_save_state($state)) {
                flash('success', 'Directory entry added.');
            } else {
                flash('error', 'Unable to add the directory entry.');
            }

            redirect_with_flash('customers');

        case 'update_directory_entry':
            $directoryType = $_POST['directory_type'] ?? '';
            $entryId = $_POST['entry_id'] ?? '';
            $directoryMap = ['installers' => 'Installer', 'referrers' => 'Referrer', 'employees' => 'Employee'];
            if (!array_key_exists($directoryType, $directoryMap)) {
                flash('error', 'Unknown directory type.');
                redirect_with_flash('customers');
            }

            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $region = trim($_POST['region'] ?? '');
            $speciality = trim($_POST['speciality'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            $updated = false;
            foreach ($state['team_directory'][$directoryType] as &$entry) {
                if (($entry['id'] ?? '') === $entryId) {
                    if ($name === '') {
                        flash('error', 'Provide a name for the directory entry.');
                        redirect_with_flash('customers');
                    }
                    $entry['name'] = $name;
                    $entry['email'] = $email;
                    $entry['phone'] = $phone;
                    $entry['region'] = $region;
                    $entry['speciality'] = $speciality;
                    $entry['notes'] = $notes;
                    $entry['updated_at'] = date('c');
                    portal_record_activity($state, "Updated {$directoryMap[$directoryType]} {$name}.", $actorName);
                    $updated = true;
                    break;
                }
            }
            unset($entry);

            if (!$updated) {
                flash('error', 'Directory entry not found.');
                redirect_with_flash('customers');
            }

            if (portal_save_state($state)) {
                flash('success', 'Directory entry updated.');
            } else {
                flash('error', 'Unable to update the directory entry.');
            }

            redirect_with_flash('customers');

        case 'delete_directory_entry':
            $directoryType = $_POST['directory_type'] ?? '';
            $entryId = $_POST['entry_id'] ?? '';
            $directoryMap = ['installers' => 'Installer', 'referrers' => 'Referrer', 'employees' => 'Employee'];
            if (!array_key_exists($directoryType, $directoryMap)) {
                flash('error', 'Unknown directory type.');
                redirect_with_flash('customers');
            }

            $entries = $state['team_directory'][$directoryType] ?? [];
            $initialCount = count($entries);
            $entries = array_values(array_filter(
                $entries,
                static fn(array $entry): bool => ($entry['id'] ?? '') !== $entryId
            ));

            if ($initialCount === count($entries)) {
                flash('error', 'Directory entry already removed or not found.');
                redirect_with_flash('customers');
            }

            $state['team_directory'][$directoryType] = $entries;
            portal_record_activity($state, 'Removed a team directory entry.', $actorName);

            if (portal_save_state($state)) {
                flash('success', 'Directory entry deleted.');
            } else {
                flash('error', 'Unable to delete the directory entry.');
            }

            redirect_with_flash('customers');

        case 'approve_employee_request':
            $requestId = trim($_POST['request_id'] ?? '');
            $decisionNotes = trim($_POST['decision_notes'] ?? '');

            $pendingRequests = &$state['employee_approvals']['pending'];
            $requestIndex = null;
            foreach ($pendingRequests as $index => $pendingRequest) {
                if (($pendingRequest['id'] ?? '') === $requestId) {
                    $requestIndex = $index;
                    break;
                }
            }

            if ($requestIndex === null) {
                flash('error', 'Approval request not found.');
                redirect_with_flash('approvals');
            }

            $requestData = $pendingRequests[$requestIndex];
            $requestType = $requestData['type'] ?? 'general';
            $payload = is_array($requestData['payload'] ?? null) ? $requestData['payload'] : [];
            $activityMessage = "Approved employee request {$requestId}.";

            switch ($requestType) {
                case 'customer_add':
                    $segmentSlug = $payload['segment'] ?? '';
                    if (!isset($state['customer_registry']['segments'][$segmentSlug])) {
                        flash('error', 'Customer segment no longer exists.');
                        redirect_with_flash('approvals');
                    }

                    $segment = &$state['customer_registry']['segments'][$segmentSlug];
                    $columns = $segment['columns'] ?? [];
                    $fieldsInput = is_array($payload['fields'] ?? null) ? $payload['fields'] : [];
                    $normalized = [];
                    $hasValue = false;
                    foreach ($columns as $column) {
                        if (!is_array($column) || !isset($column['key'])) {
                            continue;
                        }
                        $key = $column['key'];
                        $value = trim((string) ($fieldsInput[$key] ?? ''));
                        $normalized[$key] = $value;
                        if ($value !== '') {
                            $hasValue = true;
                        }
                    }

                    $notes = trim((string) ($payload['notes'] ?? ''));
                    $reminderOn = trim((string) ($payload['reminder_on'] ?? ''));

                    if (!$hasValue && $notes === '') {
                        flash('error', 'Cannot add an empty customer record.');
                        redirect_with_flash('approvals');
                    }

                    $segment['entries'][] = [
                        'id' => portal_generate_id('cust_'),
                        'fields' => $normalized,
                        'notes' => $notes,
                        'reminder_on' => $reminderOn,
                        'created_at' => date('c'),
                        'updated_at' => date('c'),
                    ];

                    $activityMessage = sprintf('Approved employee addition to %s (%s).', $segment['label'] ?? ucfirst($segmentSlug), $requestId);
                    break;

                case 'customer_update':
                    $segmentSlug = $payload['segment'] ?? '';
                    $entryId = $payload['entry_id'] ?? '';
                    $targetSegment = $payload['target_segment'] ?? $segmentSlug;

                    if (!isset($state['customer_registry']['segments'][$segmentSlug], $state['customer_registry']['segments'][$targetSegment])) {
                        flash('error', 'Customer segment referenced in the request is unavailable.');
                        redirect_with_flash('approvals');
                    }

                    $originSegment = &$state['customer_registry']['segments'][$segmentSlug];
                    $targetSegmentRef = &$state['customer_registry']['segments'][$targetSegment];
                    $fieldsInput = is_array($payload['fields'] ?? null) ? $payload['fields'] : [];
                    $notes = trim((string) ($payload['notes'] ?? ''));
                    $reminderOn = trim((string) ($payload['reminder_on'] ?? ''));
                    $updated = false;

                    if ($targetSegment === $segmentSlug) {
                        $columns = $originSegment['columns'] ?? [];
                        foreach ($originSegment['entries'] as &$entry) {
                            if (($entry['id'] ?? '') === $entryId) {
                                $normalized = [];
                                foreach ($columns as $column) {
                                    if (!is_array($column) || !isset($column['key'])) {
                                        continue;
                                    }
                                    $key = $column['key'];
                                    $normalized[$key] = trim((string) ($fieldsInput[$key] ?? ''));
                                }
                                $entry['fields'] = $normalized;
                                $entry['notes'] = $notes;
                                $entry['reminder_on'] = $reminderOn;
                                $entry['updated_at'] = date('c');
                                $updated = true;
                                break;
                            }
                        }
                        unset($entry);
                    } else {
                        $columnsTarget = $targetSegmentRef['columns'] ?? [];
                        $movedEntry = null;
                        foreach ($originSegment['entries'] as $idx => $entry) {
                            if (($entry['id'] ?? '') === $entryId) {
                                $movedEntry = $entry;
                                array_splice($originSegment['entries'], $idx, 1);
                                break;
                            }
                        }

                        if ($movedEntry !== null) {
                            $normalized = [];
                            foreach ($columnsTarget as $column) {
                                if (!is_array($column) || !isset($column['key'])) {
                                    continue;
                                }
                                $key = $column['key'];
                                $normalized[$key] = trim((string) ($fieldsInput[$key] ?? ''));
                            }

                            $targetSegmentRef['entries'][] = [
                                'id' => $movedEntry['id'] ?? portal_generate_id('cust_'),
                                'fields' => $normalized,
                                'notes' => $notes,
                                'reminder_on' => $reminderOn,
                                'created_at' => $movedEntry['created_at'] ?? date('c'),
                                'updated_at' => date('c'),
                            ];
                            $updated = true;
                        }
                    }

                    if (!$updated) {
                        flash('error', 'Unable to update the requested customer record.');
                        redirect_with_flash('approvals');
                    }

                    $activityMessage = sprintf('Approved employee update for %s (%s).', $requestData['title'] ?? 'customer record', $requestId);
                    break;

                case 'design_change':
                    $seasonLabel = trim((string) ($payload['season_label'] ?? ''));
                    if ($seasonLabel === '') {
                        flash('error', 'Theme headline is required to approve this change.');
                        redirect_with_flash('approvals');
                    }

                    $accentColor = portal_sanitize_hex_color($payload['accent_color'] ?? ($state['site_theme']['accent_color'] ?? '#2563EB'), $state['site_theme']['accent_color'] ?? '#2563EB');
                    $announcement = trim((string) ($payload['announcement'] ?? ''));
                    $backgroundImage = trim((string) ($payload['background_image'] ?? ''));

                    $state['site_theme']['season_label'] = $seasonLabel;
                    $state['site_theme']['accent_color'] = $accentColor;
                    $state['site_theme']['announcement'] = $announcement;
                    if ($backgroundImage !== '') {
                        $state['site_theme']['background_image'] = $backgroundImage;
                    }

                    $activityMessage = sprintf('Approved employee theme update (%s).', $requestId);
                    break;

                case 'content_change':
                    $scope = strtolower(trim((string) ($payload['scope'] ?? '')));

                    if ($scope === 'hero_update') {
                        $hero = is_array($payload['hero'] ?? null) ? $payload['hero'] : [];
                        $heroTitle = trim((string) ($hero['title'] ?? ''));
                        $heroSubtitle = trim((string) ($hero['subtitle'] ?? ''));

                        if ($heroTitle === '' || $heroSubtitle === '') {
                            flash('error', 'Hero updates require both a title and subtitle.');
                            redirect_with_flash('approvals');
                        }

                        $heroImage = trim((string) ($hero['image'] ?? ($state['home_hero']['image'] ?? 'images/hero/hero.png')));
                        $heroImageCaption = trim((string) ($hero['image_caption'] ?? ''));
                        $heroBubbleHeading = trim((string) ($hero['bubble_heading'] ?? ''));
                        $heroBubbleBody = trim((string) ($hero['bubble_body'] ?? ''));
                        $heroBulletsRaw = $hero['bullets'] ?? [];

                        if (is_string($heroBulletsRaw)) {
                            $heroBullets = array_values(array_filter(array_map('trim', preg_split("/\r?\n/", $heroBulletsRaw) ?: [])));
                        } elseif (is_array($heroBulletsRaw)) {
                            $heroBullets = array_values(array_filter(array_map(static fn($bullet) => trim((string) $bullet), $heroBulletsRaw)));
                        } else {
                            $heroBullets = [];
                        }

                        $state['home_hero'] = [
                            'title' => $heroTitle,
                            'subtitle' => $heroSubtitle,
                            'image' => $heroImage,
                            'image_caption' => $heroImageCaption,
                            'bubble_heading' => $heroBubbleHeading,
                            'bubble_body' => $heroBubbleBody,
                            'bullets' => $heroBullets,
                        ];

                        $activityMessage = sprintf('Approved employee hero update (%s).', $requestId);
                        break;
                    }

                    if ($scope === 'section') {
                        $sectionInput = is_array($payload['section'] ?? null) ? $payload['section'] : [];
                        $mode = strtolower((string) ($payload['mode'] ?? 'create'));
                        if (!in_array($mode, ['create', 'update'], true)) {
                            $mode = 'create';
                        }

                        $targetId = trim((string) ($payload['targetId'] ?? $payload['target_id'] ?? ''));
                        if ($mode === 'update' && $targetId === '') {
                            flash('error', 'Requested homepage section no longer exists.');
                            redirect_with_flash('approvals');
                        }

                        $sectionEyebrow = trim((string) ($sectionInput['eyebrow'] ?? ''));
                        $sectionTitle = trim((string) ($sectionInput['title'] ?? ''));
                        $sectionSubtitle = trim((string) ($sectionInput['subtitle'] ?? ''));

                        $bodyRaw = $sectionInput['body'] ?? [];
                        if (is_string($bodyRaw)) {
                            $sectionBody = array_values(array_filter(array_map('trim', preg_split("/\n{2,}/", $bodyRaw) ?: [])));
                        } elseif (is_array($bodyRaw)) {
                            $sectionBody = array_values(array_filter(array_map(static fn($paragraph) => trim((string) $paragraph), $bodyRaw)));
                        } else {
                            $sectionBody = [];
                        }

                        $bulletsRaw = $sectionInput['bullets'] ?? [];
                        if (is_string($bulletsRaw)) {
                            $sectionBullets = array_values(array_filter(array_map('trim', preg_split("/\r?\n/", $bulletsRaw) ?: [])));
                        } elseif (is_array($bulletsRaw)) {
                            $sectionBullets = array_values(array_filter(array_map(static fn($bullet) => trim((string) $bullet), $bulletsRaw)));
                        } else {
                            $sectionBullets = [];
                        }

                        $ctaInput = is_array($sectionInput['cta'] ?? null) ? $sectionInput['cta'] : [];
                        $ctaText = trim((string) ($ctaInput['text'] ?? ''));
                        $ctaUrl = trim((string) ($ctaInput['url'] ?? ''));

                        $mediaInput = is_array($sectionInput['media'] ?? null) ? $sectionInput['media'] : [];
                        $mediaType = strtolower(trim((string) ($mediaInput['type'] ?? 'none')));
                        if (!in_array($mediaType, ['image', 'video', 'none'], true)) {
                            $mediaType = 'none';
                        }
                        $mediaSrc = trim((string) ($mediaInput['src'] ?? ''));
                        $mediaAlt = $mediaType === 'image' ? trim((string) ($mediaInput['alt'] ?? '')) : '';
                        if ($mediaType === 'none') {
                            $mediaSrc = '';
                            $mediaAlt = '';
                        }

                        $sectionStatus = strtolower(trim((string) ($sectionInput['status'] ?? 'draft')));
                        if (!in_array($sectionStatus, ['draft', 'published'], true)) {
                            $sectionStatus = 'draft';
                        }

                        $backgroundStyle = strtolower(trim((string) ($sectionInput['background_style'] ?? 'section')));
                        $paletteKeys = array_keys($state['site_theme']['palette'] ?? []);
                        if (!in_array($backgroundStyle, $paletteKeys, true) || $backgroundStyle === 'accent') {
                            $backgroundStyle = 'section';
                        }

                        $displayOrder = (int) ($sectionInput['display_order'] ?? 0);

                        if ($mode === 'create' && $sectionTitle === '' && empty($sectionBody) && empty($sectionBullets)) {
                            flash('error', 'Section proposals must include a title or body content.');
                            redirect_with_flash('approvals');
                        }

                        $sectionPayload = [
                            'id' => $mode === 'update' ? $targetId : portal_generate_id('sec_'),
                            'eyebrow' => $sectionEyebrow,
                            'title' => $sectionTitle,
                            'subtitle' => $sectionSubtitle,
                            'body' => $sectionBody,
                            'bullets' => $sectionBullets,
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
                            'status' => $sectionStatus,
                            'updated_at' => date('c'),
                        ];

                        if ($mode === 'update') {
                            $updated = false;
                            foreach ($state['home_sections'] as &$existingSection) {
                                if (($existingSection['id'] ?? '') === $targetId) {
                                    $sectionPayload['created_at'] = $existingSection['created_at'] ?? date('c');
                                    $existingSection = array_merge($existingSection, $sectionPayload);
                                    $updated = true;
                                    break;
                                }
                            }
                            unset($existingSection);

                            if (!$updated) {
                                flash('error', 'Requested homepage section not found.');
                                redirect_with_flash('approvals');
                            }

                            $activityMessage = sprintf('Approved homepage section update (%s).', $requestId);
                        } else {
                            $sectionPayload['created_at'] = date('c');
                            $state['home_sections'][] = $sectionPayload;
                            $activityMessage = sprintf('Approved new homepage section (%s).', $requestId);
                        }

                        break;
                    }

                    $activityMessage = sprintf('Marked employee content request %s as approved.', $requestId);
                    break;

                default:
                    // General requests are logged without system changes.
                    $activityMessage = sprintf('Marked employee request %s as approved.', $requestId);
                    break;
            }

            array_splice($pendingRequests, $requestIndex, 1);
            $requestData['status'] = 'Approved';
            $requestData['resolved_at'] = date('c');
            $requestData['last_update'] = sprintf('Approved on %s by %s', date('j M Y, g:i A'), $actorName);
            $requestData['outcome'] = $decisionNotes !== ''
                ? 'Approved — ' . $decisionNotes
                : sprintf('Approved by %s', $actorName);

            portal_archive_employee_request($state, $requestData);
            portal_record_activity($state, $activityMessage, $actorName);

            if (portal_save_state($state)) {
                flash('success', 'Request approved successfully.');
            } else {
                flash('error', 'Changes were applied but the approval log could not be saved.');
            }

            redirect_with_flash('approvals');

        case 'reject_employee_request':
            $requestId = trim($_POST['request_id'] ?? '');
            $decisionNotes = trim($_POST['decision_notes'] ?? '');

            $pendingRequests = &$state['employee_approvals']['pending'];
            $requestIndex = null;
            foreach ($pendingRequests as $index => $pendingRequest) {
                if (($pendingRequest['id'] ?? '') === $requestId) {
                    $requestIndex = $index;
                    break;
                }
            }

            if ($requestIndex === null) {
                flash('error', 'Approval request not found.');
                redirect_with_flash('approvals');
            }

            $requestData = $pendingRequests[$requestIndex];
            array_splice($pendingRequests, $requestIndex, 1);

            $requestData['status'] = 'Declined';
            $requestData['resolved_at'] = date('c');
            $requestData['last_update'] = sprintf('Declined on %s by %s', date('j M Y, g:i A'), $actorName);
            $requestData['outcome'] = $decisionNotes !== ''
                ? 'Declined — ' . $decisionNotes
                : sprintf('Declined by %s', $actorName);

            portal_archive_employee_request($state, $requestData);
            portal_record_activity($state, sprintf('Declined employee request %s.', $requestId), $actorName);

            if (portal_save_state($state)) {
                flash('success', 'Request marked as declined.');
            } else {
                flash('error', 'Unable to record the decision.');
            }

            redirect_with_flash('approvals');

        default:
            flash('error', 'Unknown action requested.');
            redirect_with_flash($redirectView);
    }
}

$flashMessages = $_SESSION['flash'] ?? ['success' => [], 'error' => []];
unset($_SESSION['flash']);

$users = $state['users'];
$projects = $state['projects'];
$tasks = $state['tasks'];
$siteSettings = $state['site_settings'];
$siteTheme = $state['site_theme'];
$homeHero = $state['home_hero'];
$homeOffers = $state['home_offers'];
$testimonials = $state['testimonials'];
$blogPosts = $state['blog_posts'];
$caseStudies = $state['case_studies'];
$homeSections = $state['home_sections'];
$customerRegistry = $state['customer_registry'];
$teamDirectory = $state['team_directory'];
$activityLog = $state['activity_log'];

$heroBulletsValue = implode("\n", $homeHero['bullets'] ?? []);

usort($homeOffers, static function (array $a, array $b): int {
    return strcmp($b['updated_at'] ?? $b['starts_on'] ?? '', $a['updated_at'] ?? $a['starts_on'] ?? '');
});

usort($testimonials, static function (array $a, array $b): int {
    return strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? '');
});

usort($blogPosts, static function (array $a, array $b): int {
    return strcmp($b['updated_at'] ?? $b['published_at'] ?? '', $a['updated_at'] ?? $a['published_at'] ?? '');
});

usort($caseStudies, static function (array $a, array $b): int {
    return strcmp($b['updated_at'] ?? $b['published_at'] ?? '', $a['updated_at'] ?? $a['published_at'] ?? '');
});

usort($homeSections, static function (array $a, array $b): int {
    $orderA = $a['display_order'] ?? 0;
    $orderB = $b['display_order'] ?? 0;
    if ($orderA === $orderB) {
        return strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? '');
    }

    return $orderA <=> $orderB;
});

foreach ($customerRegistry['segments'] as &$segment) {
    if (!isset($segment['entries']) || !is_array($segment['entries'])) {
        $segment['entries'] = [];
        continue;
    }

    usort($segment['entries'], static function (array $a, array $b): int {
        return strcmp($b['updated_at'] ?? $b['created_at'] ?? '', $a['updated_at'] ?? $a['created_at'] ?? '');
    });
}
unset($segment);

foreach ($teamDirectory as &$directoryEntries) {
    if (!is_array($directoryEntries)) {
        $directoryEntries = [];
        continue;
    }

    usort($directoryEntries, static function (array $a, array $b): int {
        return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
    });
}
unset($directoryEntries);

$customerSegmentStats = [];
foreach ($customerRegistry['segments'] as $slug => $segment) {
    $customerSegmentStats[$slug] = [
        'label' => $segment['label'] ?? ucfirst(str_replace('-', ' ', $slug)),
        'count' => count($segment['entries'] ?? []),
    ];
}

$employeeApprovalsState = $state['employee_approvals'] ?? ['pending' => [], 'history' => []];
$pendingEmployeeRequests = array_values($employeeApprovalsState['pending'] ?? []);
$employeeApprovalHistory = array_values($employeeApprovalsState['history'] ?? []);

usort($pendingEmployeeRequests, static function (array $a, array $b): int {
    return strcmp($b['submitted_at'] ?? '', $a['submitted_at'] ?? '');
});

usort($employeeApprovalHistory, static function (array $a, array $b): int {
    return strcmp($b['resolved_at'] ?? $b['resolved'] ?? '', $a['resolved_at'] ?? $a['resolved'] ?? '');
});

$employeeApprovalStats = [
    'pending' => count($pendingEmployeeRequests),
    'approved' => 0,
    'declined' => 0,
];

foreach ($employeeApprovalHistory as $historyEntry) {
    $status = strtolower((string) ($historyEntry['status'] ?? ''));
    if (strpos($status, 'approve') !== false || strpos($status, 'complete') !== false) {
        $employeeApprovalStats['approved']++;
    }
    if (strpos($status, 'decline') !== false || strpos($status, 'reject') !== false) {
        $employeeApprovalStats['declined']++;
    }
}

$roleLabels = [
    'admin' => 'Administrator',
    'installer' => 'Installer',
    'customer' => 'Customer',
    'referrer' => 'Referral partner',
    'employee' => 'Employee',
];

$userRoleCounts = array_fill_keys(array_keys($roleLabels), 0);
$userStatusCounts = array_fill_keys($validUserStatuses, 0);

foreach ($users as $user) {
    $role = $user['role'] ?? 'employee';
    if (isset($userRoleCounts[$role])) {
        $userRoleCounts[$role]++;
    }

    $status = $user['status'] ?? 'active';
    if (isset($userStatusCounts[$status])) {
        $userStatusCounts[$status]++;
    }
}

$openTasksList = array_values(array_filter($tasks, static function (array $task): bool {
    return strcasecmp($task['status'] ?? '', 'Completed') !== 0;
}));

usort($openTasksList, static function (array $a, array $b): int {
    $aDue = $a['due_date'] ?? '';
    $bDue = $b['due_date'] ?? '';

    if ($aDue === '' && $bDue === '') {
        return strcmp($a['title'] ?? '', $b['title'] ?? '');
    }

    if ($aDue === '') {
        return 1;
    }

    if ($bDue === '') {
        return -1;
    }

    $aTime = strtotime($aDue) ?: PHP_INT_MAX;
    $bTime = strtotime($bDue) ?: PHP_INT_MAX;

    if ($aTime === $bTime) {
        return strcmp($a['title'] ?? '', $b['title'] ?? '');
    }

    return $aTime <=> $bTime;
});

$nextTask = $openTasksList[0] ?? null;
$openTasksCount = count($openTasksList);

$tickets = admin_read_tickets();
$recentComplaints = admin_prepare_recent_complaints($tickets, 8);
$openComplaintsCount = 0;
foreach ($tickets as $ticket) {
    if (!is_array($ticket)) {
        continue;
    }

    $status = strtolower((string) ($ticket['status'] ?? ''));
    if ($status === '' || in_array($status, ['open', 'pending', 'new', 'in-progress'], true)) {
        $openComplaintsCount++;
    }
}

$projectsByTarget = $projects;
usort($projectsByTarget, static function (array $a, array $b): int {
    $aDate = $a['target_date'] ?? '';
    $bDate = $b['target_date'] ?? '';

    if ($aDate === '' && $bDate === '') {
        return strcmp($a['name'] ?? '', $b['name'] ?? '');
    }

    if ($aDate === '') {
        return 1;
    }

    if ($bDate === '') {
        return -1;
    }

    $aTime = strtotime($aDate) ?: PHP_INT_MAX;
    $bTime = strtotime($bDate) ?: PHP_INT_MAX;

    if ($aTime === $bTime) {
        return strcmp($a['name'] ?? '', $b['name'] ?? '');
    }

    return $aTime <=> $bTime;
});

$upcomingProjects = array_slice($projectsByTarget, 0, 3);

$projectStatusSummary = [];
foreach ($projects as $project) {
    $status = $project['status'] ?? 'on-track';
    $projectStatusSummary[$status] = ($projectStatusSummary[$status] ?? 0) + 1;
}

$recentActivity = array_slice($activityLog, 0, 6);
$lastUpdatedLabel = '';
if (!empty($state['last_updated'])) {
    $timestamp = strtotime($state['last_updated']);
    if ($timestamp !== false) {
        $lastUpdatedLabel = date('j M Y, g:i A', $timestamp);
    }
}

$lastAdminLogin = $_SESSION['last_login'] ?? null;
$siteAnnouncement = trim($siteSettings['announcement'] ?? '');
$siteFocus = trim($siteSettings['company_focus'] ?? '');
$siteContact = trim($siteSettings['primary_contact'] ?? '');
$siteSupportEmail = trim($siteSettings['support_email'] ?? '');
$siteSupportPhone = trim($siteSettings['support_phone'] ?? '');

$themePalette = $siteTheme['palette'] ?? [];
$accentColor = $siteTheme['accent_color'] ?? '#2563EB';
$accentStrong = admin_mix_color($accentColor, '#000000', 0.25);
$accentLight = admin_mix_color($accentColor, '#FFFFFF', 0.4);
$surfaceBackground = $themePalette['surface']['background'] ?? '#FFFFFF';
$surfaceText = $themePalette['surface']['text'] ?? '#0F172A';
$surfaceMuted = $themePalette['surface']['muted'] ?? admin_mix_color($surfaceText, $surfaceBackground, 0.65);
$pageBackground = $themePalette['page']['background'] ?? '#0B1120';
$pageText = $themePalette['page']['text'] ?? '#F8FAFC';
$pageMuted = $themePalette['page']['muted'] ?? admin_mix_color($pageText, $pageBackground, 0.65);
$sectionBackground = $themePalette['section']['background'] ?? '#F1F5F9';
$sectionText = $themePalette['section']['text'] ?? '#0F172A';
$sectionMuted = $themePalette['section']['muted'] ?? admin_mix_color($sectionText, $sectionBackground, 0.65);
$heroBackground = $themePalette['hero']['background'] ?? $pageBackground;
$heroText = $themePalette['hero']['text'] ?? $pageText;
$calloutBackground = $themePalette['callout']['background'] ?? $accentColor;
$calloutText = $themePalette['callout']['text'] ?? '#FFFFFF';
$footerBackground = $themePalette['footer']['background'] ?? '#111827';
$footerText = $themePalette['footer']['text'] ?? '#E2E8F0';
$borderColor = admin_mix_color($surfaceText, $surfaceBackground, 0.85);
$accentText = $themePalette['accent']['text'] ?? '#FFFFFF';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Dashboard | Dakshayani Enterprises</title>
  <meta name="description" content="Manage Dakshayani Enterprises operations, projects, and portal access from one secure console." />
  <link rel="icon" href="images/favicon.ico" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <style>
    :root {
      color-scheme: light;
      --bg: <?= htmlspecialchars($heroBackground); ?>;
      --surface: <?= htmlspecialchars($surfaceBackground); ?>;
      --muted: <?= htmlspecialchars($surfaceMuted); ?>;
      --border: <?= htmlspecialchars($borderColor); ?>;
      --primary: <?= htmlspecialchars($accentColor); ?>;
      --primary-strong: <?= htmlspecialchars($accentStrong); ?>;
      --success: #16a34a;
      --danger: #dc2626;
      --warning: #f97316;
      --primary-light: <?= htmlspecialchars($accentLight); ?>;
      --theme-page-background: <?= htmlspecialchars($pageBackground); ?>;
      --theme-page-text: <?= htmlspecialchars($pageText); ?>;
      --theme-page-muted: <?= htmlspecialchars($pageMuted); ?>;
      --theme-section-background: <?= htmlspecialchars($sectionBackground); ?>;
      --theme-section-text: <?= htmlspecialchars($sectionText); ?>;
      --theme-section-muted: <?= htmlspecialchars($sectionMuted); ?>;
      --theme-surface-background: <?= htmlspecialchars($surfaceBackground); ?>;
      --theme-surface-text: <?= htmlspecialchars($surfaceText); ?>;
      --theme-callout-background: <?= htmlspecialchars($calloutBackground); ?>;
      --theme-callout-text: <?= htmlspecialchars($calloutText); ?>;
      --theme-footer-background: <?= htmlspecialchars($footerBackground); ?>;
      --theme-footer-text: <?= htmlspecialchars($footerText); ?>;
      --hero-text: <?= htmlspecialchars($heroText); ?>;
      --accent-text: <?= htmlspecialchars($accentText); ?>;
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: radial-gradient(circle at top, rgba(37, 99, 235, 0.25), transparent 55%), var(--bg);
      min-height: 100vh;
      display: flex;
      justify-content: center;
      padding: clamp(2rem, 4vw, 3rem) 1.5rem;
      color: var(--theme-surface-text);
    }

    main.dashboard-shell {
      width: min(1200px, 100%);
      background: var(--surface);
      border-radius: 1.75rem;
      box-shadow: 0 40px 70px -45px rgba(15, 23, 42, 0.65);
      display: grid;
      gap: clamp(1.5rem, 3vw, 2.5rem);
      padding: clamp(2rem, 4vw, 3rem);
    }

    header.dashboard-header {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      align-items: flex-start;
      gap: 1.5rem;
    }

    .eyebrow {
      text-transform: uppercase;
      letter-spacing: 0.18em;
      font-size: 0.75rem;
      font-weight: 600;
      margin: 0 0 0.35rem;
      color: var(--primary);
    }

    h1 {
      margin: 0;
      font-size: clamp(1.8rem, 3vw, 2.4rem);
      font-weight: 700;
    }

    .subhead {
      margin: 0.25rem 0 0;
      color: var(--muted);
      font-size: 0.95rem;
      line-height: 1.5;
    }

    .dashboard-header__actions {
      display: flex;
      gap: 0.75rem;
      align-items: center;
    }

    .logout-btn {
      border: none;
      border-radius: 999px;
      padding: 0.65rem 1.35rem;
      font-weight: 600;
      background: rgba(37, 99, 235, 0.12);
      color: var(--primary-strong);
      cursor: pointer;
      transition: background 0.2s ease, transform 0.2s ease;
    }

    .logout-btn:hover,
    .logout-btn:focus {
      background: rgba(37, 99, 235, 0.2);
      transform: translateY(-1px);
    }

    .view-nav {
      display: flex;
      flex-wrap: wrap;
      gap: 0.6rem;
    }

    .view-link {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.55rem 1rem;
      border-radius: 999px;
      border: 1px solid rgba(37, 99, 235, 0.18);
      color: var(--muted);
      font-weight: 600;
      font-size: 0.9rem;
      text-decoration: none;
      transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
    }

    .view-link.is-active {
      background: var(--primary);
      border-color: var(--primary);
      color: var(--accent-text);
    }

    .view-link:not(.is-active):hover,
    .view-link:not(.is-active):focus {
      background: rgba(37, 99, 235, 0.1);
      border-color: rgba(37, 99, 235, 0.25);
      color: var(--primary-strong);
    }

    .flash-list {
      display: grid;
      gap: 0.75rem;
    }

    .flash-message {
      border-radius: 1rem;
      padding: 0.75rem 1rem;
      font-weight: 500;
      font-size: 0.95rem;
      border: 1px solid transparent;
    }

    .flash-message[data-tone="success"] {
      background: rgba(22, 163, 74, 0.12);
      border-color: rgba(22, 163, 74, 0.28);
      color: #166534;
    }

    .flash-message[data-tone="error"] {
      background: rgba(220, 38, 38, 0.12);
      border-color: rgba(220, 38, 38, 0.28);
      color: #991b1b;
    }

    section.panel {
      border: 1px solid var(--border);
      border-radius: 1.5rem;
      padding: clamp(1.5rem, 3vw, 2rem);
      background: var(--theme-section-background);
      color: var(--theme-section-text);
      display: grid;
      gap: 1rem;
    }

    section.panel h2 {
      margin: 0;
      font-size: 1.2rem;
      font-weight: 600;
    }

    .lead {
      margin: 0;
      color: var(--muted);
      font-size: 0.95rem;
    }

    .metric-grid {
      display: grid;
      gap: 1rem;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    }

    .metric-card {
      background: var(--theme-surface-background);
      border-radius: 1.1rem;
      padding: 1rem 1.1rem;
      border: 1px solid var(--border);
      display: grid;
      gap: 0.4rem;
    }

    .metric-label {
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: var(--muted);
      margin: 0;
    }

    .metric-value {
      margin: 0;
      font-size: 1.6rem;
      font-weight: 700;
    }

    .metric-helper {
      margin: 0;
      font-size: 0.85rem;
      color: var(--muted);
    }

    .summary-grid {
      display: grid;
      gap: 1rem;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    }

    .summary-card {
      background: #ffffff;
      border-radius: 1.25rem;
      border: 1px solid rgba(148, 163, 184, 0.25);
      padding: 1.1rem 1.2rem;
      display: grid;
      gap: 0.7rem;
    }

    .summary-card h3 {
      margin: 0;
      font-size: 1.05rem;
      font-weight: 600;
    }

    .summary-list {
      margin: 0;
      padding-left: 1.1rem;
      color: var(--muted);
      font-size: 0.95rem;
      display: grid;
      gap: 0.35rem;
    }

    .link-button {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.4rem;
      border-radius: 999px;
      padding: 0.55rem 1.05rem;
      font-weight: 600;
      text-decoration: none;
      background: rgba(37, 99, 235, 0.12);
      color: var(--primary-strong);
      border: 1px solid rgba(37, 99, 235, 0.25);
      transition: background 0.2s ease, color 0.2s ease;
      width: fit-content;
    }

    .link-button:hover,
    .link-button:focus {
      background: rgba(37, 99, 235, 0.2);
      color: var(--primary-strong);
    }

    .activity-list {
      display: grid;
      gap: 0.6rem;
    }

    .activity-item {
      background: #ffffff;
      border-radius: 1rem;
      border: 1px solid var(--border);
      padding: 0.75rem 1rem;
      display: grid;
      gap: 0.25rem;
    }

    .activity-item small {
      color: var(--muted);
    }

    .workspace-grid {
      display: grid;
      gap: 1.5rem;
      grid-template-columns: minmax(0, 1fr) minmax(0, 320px);
      align-items: start;
    }

    .table-wrapper {
      background: #ffffff;
      border-radius: 1.2rem;
      border: 1px solid rgba(148, 163, 184, 0.25);
      overflow-x: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      min-width: 720px;
    }

    table thead {
      background: rgba(37, 99, 235, 0.08);
    }

    table th,
    table td {
      padding: 0.8rem 1rem;
      text-align: left;
      font-size: 0.95rem;
      border-bottom: 1px solid rgba(148, 163, 184, 0.2);
    }

    table tbody tr:last-child td {
      border-bottom: none;
    }

    .badge {
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      background: rgba(37, 99, 235, 0.12);
      color: var(--primary-strong);
      border-radius: 999px;
      padding: 0.2rem 0.65rem;
      font-size: 0.8rem;
      font-weight: 600;
    }

    .status-chip {
      display: inline-flex;
      align-items: center;
      padding: 0.25rem 0.7rem;
      border-radius: 999px;
      font-weight: 600;
      font-size: 0.85rem;
      text-transform: capitalize;
    }

    .status-chip[data-status="active"],
    .status-chip[data-status="on-track"],
    .status-chip[data-status="Pending"] {
      background: rgba(34, 197, 94, 0.18);
      color: #166534;
    }

    .status-chip[data-status="pending"],
    .status-chip[data-status="planning"] {
      background: rgba(59, 130, 246, 0.18);
      color: #1d4ed8;
    }

    .status-chip[data-status="onboarding"],
    .status-chip[data-status="In progress"] {
      background: rgba(249, 115, 22, 0.18);
      color: #c2410c;
    }

    .status-chip[data-status="suspended"],
    .status-chip[data-status="at-risk"],
    .status-chip[data-status="Blocked"] {
      background: rgba(248, 113, 113, 0.2);
      color: #b91c1c;
    }

    .status-chip[data-status="disabled"],
    .status-chip[data-status="delayed"],
    .status-chip[data-status="Completed"],
    .status-chip[data-status="completed"] {
      background: rgba(37, 99, 235, 0.18);
      color: #1d4ed8;
    }

    details.manage {
      display: block;
    }

    details.manage summary {
      cursor: pointer;
      font-weight: 600;
      color: var(--primary-strong);
      outline: none;
      list-style: none;
    }

    details.manage summary::-webkit-details-marker {
      display: none;
    }

    details.manage[open] summary {
      margin-bottom: 0.6rem;
    }

    .manage-forms {
      display: grid;
      gap: 0.75rem;
      padding: 0.6rem 0.75rem;
      background: rgba(37, 99, 235, 0.05);
      border-radius: 0.9rem;
    }

    form {
      display: grid;
      gap: 0.75rem;
    }

    .form-grid {
      display: grid;
      gap: 0.75rem;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }

    label {
      font-size: 0.85rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: var(--muted);
      display: block;
    }

    input,
    select,
    textarea {
      border-radius: 0.85rem;
      border: 1px solid var(--border);
      padding: 0.6rem 0.75rem;
      font-size: 0.95rem;
      font-family: inherit;
      width: 100%;
      background: var(--theme-surface-background);
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .palette-grid {
      display: grid;
      gap: 1rem;
      grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    }

    .palette-card {
      background: var(--theme-surface-background);
      border: 1px dashed var(--border);
      border-radius: 1rem;
      padding: 0.75rem 1rem;
      display: grid;
      gap: 0.5rem;
    }

    .palette-card__preview {
      border-radius: 0.75rem;
      padding: 0.6rem 0.75rem;
      display: flex;
      flex-direction: column;
      gap: 0.25rem;
      font-size: 0.85rem;
      border: 1px solid rgba(255, 255, 255, 0.25);
    }

    .palette-card__preview span {
      font-weight: 600;
    }

    .palette-card__text {
      font-size: 0.8rem;
      opacity: 0.85;
    }

    .palette-card input[type="color"] {
      width: 100%;
      border: none;
      background: transparent;
      height: 2.5rem;
      padding: 0;
    }

    textarea {
      min-height: 96px;
      resize: vertical;
    }

    input:focus,
    select:focus,
    textarea:focus {
      border-color: rgba(37, 99, 235, 0.8);
      box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.18);
      outline: none;
    }

    .btn-primary,
    .btn-secondary,
    .btn-ghost,
    .btn-destructive {
      border: none;
      border-radius: 999px;
      padding: 0.55rem 1.15rem;
      font-weight: 600;
      font-size: 0.95rem;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.4rem;
      transition: background 0.2s ease, color 0.2s ease, transform 0.2s ease;
    }

    .btn-primary {
      background: var(--primary);
      color: var(--accent-text);
    }

    .btn-primary:hover,
    .btn-primary:focus {
      background: var(--primary-strong);
      transform: translateY(-1px);
    }

    .btn-secondary {
      background: rgba(37, 99, 235, 0.12);
      color: var(--primary-strong);
    }

    .btn-secondary:hover,
    .btn-secondary:focus {
      background: rgba(37, 99, 235, 0.2);
    }

    .btn-ghost {
      background: transparent;
      color: var(--muted);
    }

    .btn-ghost:hover,
    .btn-ghost:focus {
      color: var(--primary-strong);
    }

    .btn-destructive {
      background: rgba(220, 38, 38, 0.12);
      color: #b91c1c;
    }

    .btn-destructive:hover,
    .btn-destructive:focus {
      background: rgba(220, 38, 38, 0.2);
    }

    .form-actions {
      margin-top: 1rem;
      display: flex;
      flex-wrap: wrap;
      gap: 0.75rem;
      align-items: center;
    }

    .reset-theme {
      margin-top: 1rem;
      display: grid;
      gap: 0.4rem;
    }

    .reset-theme button {
      justify-self: start;
    }

    .form-helper {
      font-size: 0.85rem;
      color: var(--muted);
      margin: -0.25rem 0 0;
    }

    .workspace-aside {
      background: #ffffff;
      border-radius: 1.2rem;
      border: 1px solid rgba(148, 163, 184, 0.25);
      padding: 1.1rem 1.2rem;
      display: grid;
      gap: 0.9rem;
    }

    .workspace-aside h3 {
      margin: 0;
      font-size: 1.05rem;
      font-weight: 600;
    }

    .pill-list {
      display: flex;
      flex-wrap: wrap;
      gap: 0.4rem;
      margin: 0;
      padding: 0;
      list-style: none;
    }

    .pill-list li {
      border-radius: 999px;
      padding: 0.35rem 0.8rem;
      background: rgba(37, 99, 235, 0.1);
      color: var(--primary-strong);
      font-size: 0.85rem;
      font-weight: 600;
    }

    .field-list {
      margin: 0.5rem 0 1rem;
      padding-left: 1.2rem;
      color: var(--muted);
    }

    .field-list li {
      margin-bottom: 0.3rem;
    }

    .two-column {
      display: grid;
      gap: 1.2rem;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    }

    @media (max-width: 960px) {
      .workspace-grid {
        grid-template-columns: 1fr;
      }

      table {
        min-width: unset;
      }
    }

    @media (max-width: 720px) {
      body {
        padding: 1.5rem;
      }

      main.dashboard-shell {
        border-radius: 1.5rem;
        padding: 1.75rem;
      }

      .view-nav {
        gap: 0.4rem;
      }

      .view-link {
        font-size: 0.85rem;
        padding: 0.45rem 0.85rem;
      }

      .metric-grid,
      .summary-grid,
      .form-grid,
      .two-column,
      .palette-grid {
        grid-template-columns: 1fr;
      }

      .metric-card,
      .summary-card,
      .workspace-aside,
      .palette-card,
      .table-wrapper {
        width: 100%;
      }
    }
  </style>
</head>
<body data-view="<?= htmlspecialchars($currentView); ?>">
  <main class="dashboard-shell">
    <header class="dashboard-header">
      <div>
        <p class="eyebrow">Admin portal</p>
        <h1>Welcome back, <?= htmlspecialchars($_SESSION['display_name'] ?? 'Administrator'); ?></h1>
        <p class="subhead">
          Signed in as <?= htmlspecialchars(OWNER_EMAIL); ?>
          <?php if ($lastAdminLogin): ?>
            · Last login <?= htmlspecialchars($lastAdminLogin); ?>
          <?php endif; ?>
        </p>
      </div>
      <div class="dashboard-header__actions">
        <form method="post" action="logout.php">
          <button class="logout-btn" type="submit">Log out</button>
        </form>
      </div>
    </header>

    <nav class="view-nav" aria-label="Dashboard sections">
      <?php foreach ($viewLabels as $viewKey => $label): ?>
        <?php $href = $viewKey === 'overview' ? 'admin-dashboard.php' : 'admin-dashboard.php?view=' . urlencode($viewKey); ?>
        <a class="view-link <?= $viewKey === $currentView ? 'is-active' : ''; ?>" href="<?= htmlspecialchars($href); ?>"><?= htmlspecialchars($label); ?></a>
      <?php endforeach; ?>
    </nav>

    <?php if (!empty($flashMessages['success']) || !empty($flashMessages['error'])): ?>
      <div class="flash-list" role="status">
        <?php foreach ($flashMessages['success'] as $message): ?>
          <div class="flash-message" data-tone="success"><?= htmlspecialchars($message); ?></div>
        <?php endforeach; ?>
        <?php foreach ($flashMessages['error'] as $message): ?>
          <div class="flash-message" data-tone="error"><?= htmlspecialchars($message); ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($currentView === 'overview'): ?>
      <section class="panel">
        <h2>Today at a glance</h2>
        <p class="lead">High-level summary of Dakshayani Enterprises operations.</p>
        <div class="metric-grid">
          <article class="metric-card">
            <p class="metric-label">Active users</p>
            <p class="metric-value"><?= htmlspecialchars((string) ($userStatusCounts['active'] ?? 0)); ?></p>
            <p class="metric-helper">Portal accounts with access enabled</p>
          </article>
          <article class="metric-card">
            <p class="metric-label">Projects tracked</p>
            <p class="metric-value"><?= htmlspecialchars((string) count($projects)); ?></p>
            <p class="metric-helper">Across all business units</p>
          </article>
          <article class="metric-card">
            <p class="metric-label">Open tasks</p>
            <p class="metric-value"><?= htmlspecialchars((string) $openTasksCount); ?></p>
            <p class="metric-helper">Pending follow-ups for the leadership team</p>
          </article>
          <article class="metric-card">
            <p class="metric-label">Open service complaints</p>
            <p class="metric-value"><?= htmlspecialchars((string) $openComplaintsCount); ?></p>
            <p class="metric-helper">Logged via the website &amp; portal</p>
          </article>
          <article class="metric-card">
            <p class="metric-label">Last data refresh</p>
            <p class="metric-value" style="font-size:1.05rem;">
              <?= $lastUpdatedLabel !== '' ? htmlspecialchars($lastUpdatedLabel) : 'Just now'; ?>
            </p>
            <p class="metric-helper">Automatically updated after each change</p>
          </article>
        </div>
      </section>

      <section class="panel">
        <h2>Highlights</h2>
        <p class="lead">Quick insights with links to the detailed workspaces.</p>
        <div class="summary-grid">
          <article class="summary-card">
            <h3>Portal accounts</h3>
            <ul class="summary-list">
              <li>Total accounts: <strong><?= htmlspecialchars((string) count($users)); ?></strong></li>
              <li>Active now: <strong><?= htmlspecialchars((string) ($userStatusCounts['active'] ?? 0)); ?></strong></li>
              <?php foreach ($userRoleCounts as $role => $count): ?>
                <?php if ($role !== 'admin' && $count > 0): ?>
                  <li><?= htmlspecialchars($roleLabels[$role]); ?>: <strong><?= htmlspecialchars((string) $count); ?></strong></li>
                <?php endif; ?>
              <?php endforeach; ?>
              <?php if (($userStatusCounts['pending'] ?? 0) > 0): ?>
                <li>Pending activation: <strong><?= htmlspecialchars((string) $userStatusCounts['pending']); ?></strong></li>
              <?php endif; ?>
            </ul>
            <a class="link-button" href="admin-dashboard.php?view=accounts">Open accounts workspace</a>
          </article>
          <article class="summary-card">
            <h3>Project tracker</h3>
            <ul class="summary-list">
              <li>Projects in motion: <strong><?= htmlspecialchars((string) count($projects)); ?></strong></li>
              <?php foreach ($projectStatusSummary as $status => $count): ?>
                <li><?= htmlspecialchars(ucwords(str_replace('-', ' ', $status))); ?>: <strong><?= htmlspecialchars((string) $count); ?></strong></li>
              <?php endforeach; ?>
              <?php if (!empty($upcomingProjects)): ?>
                <?php $firstProject = $upcomingProjects[0]; ?>
                <li>Next milestone: <strong><?= htmlspecialchars($firstProject['name']); ?></strong><?php if (!empty($firstProject['target_date'])): ?> · <?= htmlspecialchars(date('j M Y', strtotime($firstProject['target_date']))); ?><?php endif; ?></li>
              <?php endif; ?>
            </ul>
            <a class="link-button" href="admin-dashboard.php?view=projects">Review project details</a>
          </article>
          <article class="summary-card">
            <h3>Leadership tasks</h3>
            <ul class="summary-list">
              <li>Open actions: <strong><?= htmlspecialchars((string) $openTasksCount); ?></strong></li>
              <li>Completed: <strong><?= htmlspecialchars((string) max(count($tasks) - $openTasksCount, 0)); ?></strong></li>
              <?php if ($nextTask): ?>
                <li>Next due: <strong><?= htmlspecialchars($nextTask['title']); ?></strong><?php if (!empty($nextTask['due_date'])): ?> · <?= htmlspecialchars(date('j M Y', strtotime($nextTask['due_date']))); ?><?php endif; ?></li>
              <?php endif; ?>
            </ul>
            <a class="link-button" href="admin-dashboard.php?view=tasks">Manage task board</a>
          </article>
          <article class="summary-card">
            <h3>Site configuration</h3>
            <ul class="summary-list">
              <?php if ($siteAnnouncement !== ''): ?>
                <li>Announcement: <strong><?= htmlspecialchars($siteAnnouncement); ?></strong></li>
              <?php endif; ?>
              <?php if ($siteFocus !== ''): ?>
                <li>Focus: <?= htmlspecialchars($siteFocus); ?></li>
              <?php endif; ?>
              <?php if ($siteContact !== ''): ?>
                <li>Primary contact: <strong><?= htmlspecialchars($siteContact); ?></strong></li>
              <?php endif; ?>
              <?php if ($siteSupportEmail !== ''): ?>
                <li>Email: <?= htmlspecialchars($siteSupportEmail); ?></li>
              <?php endif; ?>
              <?php if ($siteSupportPhone !== ''): ?>
                <li>Phone: <?= htmlspecialchars($siteSupportPhone); ?></li>
              <?php endif; ?>
            </ul>
            <a class="link-button" href="admin-dashboard.php?view=settings">Edit public details</a>
          </article>
        </div>
      </section>

      <section class="panel">
        <h2>Latest service complaints</h2>
        <p class="lead">Complaints submitted through the website are captured here for admin and employee follow-up.</p>
        <?php if (empty($recentComplaints)): ?>
          <p>No service complaints have been logged yet.</p>
        <?php else: ?>
          <div class="table-wrapper">
            <table>
              <thead>
                <tr>
                  <th>Ticket</th>
                  <th>Customer</th>
                  <th>Issues</th>
                  <th>Priority</th>
                  <th>Status</th>
                  <th>Logged</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentComplaints as $complaint): ?>
                  <?php $issuesText = !empty($complaint['issueLabels']) ? implode(', ', $complaint['issueLabels']) : ''; ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($complaint['id'] ?: '—'); ?></strong></td>
                    <td>
                      <strong><?= htmlspecialchars($complaint['requesterName']); ?></strong>
                      <?php if (($complaint['requesterPhone'] ?? '') !== ''): ?>
                        <div class="form-helper">+91 <?= htmlspecialchars($complaint['requesterPhone']); ?></div>
                      <?php endif; ?>
                      <?php if (($complaint['siteAddress'] ?? '') !== ''): ?>
                        <div class="form-helper"><?= htmlspecialchars($complaint['siteAddress']); ?></div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($issuesText !== ''): ?>
                        <?= htmlspecialchars($issuesText); ?>
                      <?php else: ?>
                        <span class="form-helper">—</span>
                      <?php endif; ?>
                    </td>
                    <td><span class="status-chip" data-status="<?= htmlspecialchars(strtolower($complaint['priority'])); ?>"><?= htmlspecialchars($complaint['priority']); ?></span></td>
                    <td><span class="status-chip" data-status="<?= htmlspecialchars(strtolower($complaint['status'])); ?>"><?= htmlspecialchars($complaint['status']); ?></span></td>
                    <td>
                      <?= htmlspecialchars($complaint['channel']); ?><br />
                      <span class="form-helper"><?= htmlspecialchars($complaint['createdAtFormatted']); ?></span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>

      <section class="panel">
        <h2>Recent activity</h2>
        <p class="lead">Automatic log of important actions across the portal.</p>
        <?php if (empty($recentActivity)): ?>
          <p>No activity recorded yet. Actions will appear here automatically.</p>
        <?php else: ?>
          <div class="activity-list">
            <?php foreach ($recentActivity as $log): ?>
              <div class="activity-item">
                <strong><?= htmlspecialchars($log['event']); ?></strong>
                <small><?= htmlspecialchars($log['actor']); ?> · <?= htmlspecialchars(date('j M Y, g:i A', strtotime($log['timestamp']))); ?></small>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    <?php elseif ($currentView === 'accounts'): ?>
      <section class="panel">
        <h2>Portal accounts</h2>
        <p class="lead">Create credentials for installers, referrers, customers, and team members. The owner admin account remains exclusive to <?= htmlspecialchars(OWNER_EMAIL); ?>.</p>
        <div class="workspace-grid">
          <div>
            <?php if (empty($users)): ?>
              <p>No portal users yet. Use the form to add your first collaborator.</p>
            <?php else: ?>
              <div class="table-wrapper">
                <table>
                  <thead>
                    <tr>
                      <th>Name</th>
                      <th>Email</th>
                      <th>Role</th>
                      <th>Status</th>
                      <th>Last login</th>
                      <th>Created</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($users as $user): ?>
                      <?php
                        $role = $user['role'] ?? 'employee';
                        $status = $user['status'] ?? 'active';
                        $statusLabel = ucwords(str_replace('-', ' ', $status));
                        $roleLabel = $roleLabels[$role] ?? ucfirst($role);
                        $isOwnerAdmin = ($role === 'admin' && strcasecmp($user['email'] ?? '', OWNER_EMAIL) === 0);
                        $lastLogin = $user['last_login'] ?? '';
                        $lastLoginFormatted = ($lastLogin && strtotime($lastLogin)) ? date('j M Y, g:i A', strtotime($lastLogin)) : '—';
                        $createdAt = $user['created_at'] ?? '';
                        $createdFormatted = ($createdAt && strtotime($createdAt)) ? date('j M Y', strtotime($createdAt)) : '—';
                      ?>
                      <tr>
                        <td>
                          <strong><?= htmlspecialchars($user['name'] ?? ''); ?></strong>
                          <?php if (!empty($user['phone'])): ?>
                            <div class="form-helper">Phone: <?= htmlspecialchars($user['phone']); ?></div>
                          <?php endif; ?>
                          <?php if (!empty($user['notes'])): ?>
                            <div class="form-helper">Note: <?= htmlspecialchars($user['notes']); ?></div>
                          <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($user['email'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($roleLabel); ?></td>
                        <td><span class="status-chip" data-status="<?= htmlspecialchars($status); ?>"><?= htmlspecialchars($statusLabel); ?></span></td>
                        <td><?= htmlspecialchars($lastLoginFormatted); ?></td>
                        <td><?= htmlspecialchars($createdFormatted); ?></td>
                        <td>
                          <?php if ($isOwnerAdmin): ?>
                            <span class="badge">Owner admin</span>
                          <?php else: ?>
                            <details class="manage">
                              <summary>Manage</summary>
                              <div class="manage-forms">
                                <form method="post">
                                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
                                  <input type="hidden" name="action" value="update_user_status" />
                                  <input type="hidden" name="redirect_view" value="accounts" />
                                  <input type="hidden" name="user_id" value="<?= htmlspecialchars($user['id'] ?? ''); ?>" />
                                  <div class="form-grid">
                                    <div>
                                      <label for="status-<?= htmlspecialchars($user['id'] ?? ''); ?>">Status</label>
                                      <select id="status-<?= htmlspecialchars($user['id'] ?? ''); ?>" name="status">
                                        <?php foreach ($validUserStatuses as $option): ?>
                                          <option value="<?= htmlspecialchars($option); ?>" <?= $status === $option ? 'selected' : ''; ?>><?= htmlspecialchars(ucwords(str_replace('-', ' ', $option))); ?></option>
                                        <?php endforeach; ?>
                                      </select>
                                    </div>
                                    <div>
                                      <label for="notes-<?= htmlspecialchars($user['id'] ?? ''); ?>">Notes</label>
                                      <textarea id="notes-<?= htmlspecialchars($user['id'] ?? ''); ?>" name="notes" placeholder="Status update or context"><?= htmlspecialchars($user['notes'] ?? ''); ?></textarea>
                                    </div>
                                  </div>
                                  <button class="btn-primary" type="submit">Save changes</button>
                                </form>

                                <form method="post">
                                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
                                  <input type="hidden" name="action" value="reset_user_password" />
                                  <input type="hidden" name="redirect_view" value="accounts" />
                                  <input type="hidden" name="user_id" value="<?= htmlspecialchars($user['id'] ?? ''); ?>" />
                                  <div class="form-grid">
                                    <div>
                                      <label for="new-pass-<?= htmlspecialchars($user['id'] ?? ''); ?>">New password</label>
                                      <input id="new-pass-<?= htmlspecialchars($user['id'] ?? ''); ?>" type="password" name="new_password" minlength="8" required />
                                    </div>
                                    <div>
                                      <label for="confirm-pass-<?= htmlspecialchars($user['id'] ?? ''); ?>">Confirm password</label>
                                      <input id="confirm-pass-<?= htmlspecialchars($user['id'] ?? ''); ?>" type="password" name="confirm_password" minlength="8" required />
                                    </div>
                                  </div>
                                  <p class="form-helper">Share the updated password securely with the user.</p>
                                  <button class="btn-secondary" type="submit">Reset password</button>
                                </form>

                                <form method="post" onsubmit="return confirm('Remove this account?');">
                                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
                                  <input type="hidden" name="action" value="delete_user" />
                                  <input type="hidden" name="redirect_view" value="accounts" />
                                  <input type="hidden" name="user_id" value="<?= htmlspecialchars($user['id'] ?? ''); ?>" />
                                  <button class="btn-destructive" type="submit">Delete account</button>
                                </form>
                              </div>
                            </details>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
          <aside class="workspace-aside">
            <h3>Create a portal account</h3>
            <form method="post" autocomplete="off">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
              <input type="hidden" name="action" value="create_user" />
              <input type="hidden" name="redirect_view" value="accounts" />
              <div class="form-grid">
                <div>
                  <label for="new-user-name">Full name</label>
                  <input id="new-user-name" name="name" type="text" placeholder="Priya Sharma" required />
                </div>
                <div>
                  <label for="new-user-email">Email</label>
                  <input id="new-user-email" name="email" type="email" placeholder="name@example.com" required />
                </div>
              </div>
              <div class="form-grid">
                <div>
                  <label for="new-user-role">Role</label>
                  <select id="new-user-role" name="role">
                    <?php foreach ($validUserRoles as $role): ?>
                      <option value="<?= htmlspecialchars($role); ?>"><?= htmlspecialchars(ucwords($role)); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label for="new-user-status">Status</label>
                  <select id="new-user-status" name="status">
                    <?php foreach ($validUserStatuses as $statusOption): ?>
                      <option value="<?= htmlspecialchars($statusOption); ?>" <?= $statusOption === 'active' ? 'selected' : ''; ?>><?= htmlspecialchars(ucwords(str_replace('-', ' ', $statusOption))); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="form-grid">
                <div>
                  <label for="new-user-phone">Phone</label>
                  <input id="new-user-phone" name="phone" type="text" placeholder="+91 90000 00000" />
                </div>
                <div>
                  <label for="new-user-password">Password</label>
                  <input id="new-user-password" name="password" type="password" minlength="8" required />
                </div>
                <div>
                  <label for="new-user-password-confirm">Confirm password</label>
                  <input id="new-user-password-confirm" name="password_confirm" type="password" minlength="8" required />
                </div>
              </div>
              <div>
                <label for="new-user-notes">Notes (optional)</label>
                <textarea id="new-user-notes" name="notes" placeholder="Internal context for this account"></textarea>
              </div>
              <button class="btn-primary" type="submit">Create account</button>
              <p class="form-helper">Share credentials privately. Users can now sign in with their email and password.</p>
            </form>
          </aside>
        </div>
      </section>
    <?php elseif ($currentView === 'customers'): ?>
      <section class="panel">
        <h2>Customer lifecycle</h2>
        <p class="lead">Track every prospect from enquiry to post-installation service. Upload Excel or CSV data, add new fields, and assign reminders for follow-ups.</p>
        <div class="metric-grid">
          <?php foreach ($customerSegmentStats as $slug => $summary): ?>
            <div class="metric-card">
              <p class="metric-label"><?= htmlspecialchars($summary['label']); ?></p>
              <p class="metric-value"><?= htmlspecialchars((string) $summary['count']); ?></p>
              <p class="metric-helper">records</p>
            </div>
          <?php endforeach; ?>
        </div>
        <?php $completedCsvTemplate = '/api/index.php?route=public/customer-template&segment=completed&format=csv'; ?>
        <p>
          Need a template? Every segment below includes instant CSV downloads. For completed installations you can
          <a href="<?= htmlspecialchars($completedCsvTemplate); ?>" target="_blank" rel="noopener">grab the CSV template</a>
          with Date of application, DISCOM, consumer, subsidy, and billing fields ready for bulk upload.
        </p>
      </section>

      <?php foreach ($customerRegistry['segments'] as $segmentSlug => $segmentData): ?>
        <?php
          $segmentLabel = $segmentData['label'] ?? ucfirst(str_replace('-', ' ', $segmentSlug));
          $segmentDescription = $segmentData['description'] ?? '';
          $segmentColumns = $segmentData['columns'] ?? [];
          $segmentEntries = $segmentData['entries'] ?? [];
          $segmentCsvLink = '/api/index.php?route=public/customer-template&segment=' . urlencode((string) $segmentSlug) . '&format=csv';
          $isCompletedSegment = $segmentSlug === 'completed';
        ?>
        <section class="panel" id="segment-<?= htmlspecialchars($segmentSlug); ?>">
          <h2><?= htmlspecialchars($segmentLabel); ?></h2>
          <p class="lead">
            <?= htmlspecialchars($segmentDescription); ?>
            <br />
            <small><a href="<?= htmlspecialchars($segmentCsvLink); ?>" target="_blank" rel="noopener">Download CSV template</a></small>
          </p>
          <?php if ($isCompletedSegment): ?>
            <ul class="field-list">
              <li>Date of application</li>
              <li>Application number</li>
              <li>DISCOM name</li>
              <li>Consumer number &amp; name</li>
              <li>Mobile number</li>
              <li>Capacity (kWp) installed</li>
              <li>Installation date</li>
              <li>Type of solar system (ongrid / hybrid / offgrid)</li>
              <li>Subsidy disbursed &amp; disbursal date</li>
              <li>Actual bill date</li>
              <li>GST bill date</li>
            </ul>
          <?php endif; ?>
          <div class="workspace-grid">
            <div>
              <?php if (empty($segmentEntries)): ?>
                <p><?= $isCompletedSegment ? 'No completed installations added yet. Upload the CSV template for bulk entries or use the form to log a single project.' : 'No records captured yet. Add a record manually or import your existing sheet.'; ?></p>
              <?php else: ?>
                <div class="table-wrapper">
                  <table class="customer-table">
                    <thead>
                      <tr>
                        <?php foreach ($segmentColumns as $column): ?>
                          <th><?= htmlspecialchars($column['label'] ?? ucfirst($column['key'])); ?></th>
                        <?php endforeach; ?>
                        <th>Notes</th>
                        <th>Reminder</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($segmentEntries as $entry): ?>
                        <tr>
                          <?php foreach ($segmentColumns as $column): ?>
                            <?php $columnKey = $column['key']; ?>
                            <td><?= htmlspecialchars($entry['fields'][$columnKey] ?? ''); ?></td>
                          <?php endforeach; ?>
                          <td><?= htmlspecialchars($entry['notes'] ?? ''); ?></td>
                          <td><?= htmlspecialchars($entry['reminder_on'] ?? ''); ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>

                <?php foreach ($segmentEntries as $entry): ?>
                  <?php
                    $displayName = '';
                    foreach ($segmentColumns as $column) {
                        $value = trim((string) ($entry['fields'][$column['key']] ?? ''));
                        if ($value !== '') {
                            $displayName = $value;
                            break;
                        }
                    }
                    if ($displayName === '') {
                        $displayName = $segmentLabel . ' record';
                    }
                  ?>
                  <details class="manage">
                    <summary>
                      <span><?= htmlspecialchars($displayName); ?></span>
                      <span class="status-chip" data-status="draft">Updated <?= htmlspecialchars(date('j M Y', strtotime($entry['updated_at'] ?? $entry['created_at'] ?? date('c')))); ?></span>
                    </summary>
                    <div class="manage-forms">
                      <form method="post" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
                        <input type="hidden" name="action" value="update_customer_entry" />
                        <input type="hidden" name="redirect_view" value="customers" />
                        <input type="hidden" name="segment" value="<?= htmlspecialchars($segmentSlug); ?>" />
                        <input type="hidden" name="entry_id" value="<?= htmlspecialchars($entry['id'] ?? ''); ?>" />
                        <div class="form-grid">
                          <?php foreach ($segmentColumns as $column): ?>
                            <?php
                              $columnKey = $column['key'];
                              $columnType = $column['type'] ?? 'text';
                              $inputType = 'text';
                              switch ($columnType) {
                                case 'date':
                                  $inputType = 'date';
                                  break;
                                case 'phone':
                                  $inputType = 'tel';
                                  break;
                                case 'number':
                                  $inputType = 'number';
                                  break;
                                case 'email':
                                  $inputType = 'email';
                                  break;
                              }
                            ?>
                            <div>
                              <label><?= htmlspecialchars($column['label'] ?? ucfirst($columnKey)); ?></label>
                              <input name="fields[<?= htmlspecialchars($columnKey); ?>]" type="<?= htmlspecialchars($inputType); ?>" <?php if ($inputType === 'number'): ?>step="any"<?php endif; ?> value="<?= htmlspecialchars($entry['fields'][$columnKey] ?? ''); ?>" />
                            </div>
                          <?php endforeach; ?>
                        </div>
                        <div class="form-grid">
                          <div>
                            <label>Notes</label>
                            <textarea name="notes" rows="2" placeholder="Follow-up summary"><?= htmlspecialchars($entry['notes'] ?? ''); ?></textarea>
                          </div>
                          <div>
                            <label>Reminder date</label>
                            <input name="reminder_on" type="date" value="<?= htmlspecialchars($entry['reminder_on'] ?? ''); ?>" />
                          </div>
                        </div>
                        <button class="btn-primary" type="submit"><?= $isCompletedSegment ? 'Update installation' : 'Update record'; ?></button>
                      </form>
                      <form method="post" onsubmit="return confirm('Remove this record?');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
                        <input type="hidden" name="action" value="delete_customer_entry" />
                        <input type="hidden" name="redirect_view" value="customers" />
                        <input type="hidden" name="segment" value="<?= htmlspecialchars($segmentSlug); ?>" />
                        <input type="hidden" name="entry_id" value="<?= htmlspecialchars($entry['id'] ?? ''); ?>" />
                        <button class="btn-destructive" type="submit">Delete record</button>
                      </form>
                    </div>
                  </details>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <aside class="workspace-aside">
              <h3>Manage <?= htmlspecialchars($segmentLabel); ?></h3>
              <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
                <input type="hidden" name="action" value="import_customer_segment" />
                <input type="hidden" name="redirect_view" value="customers" />
                <input type="hidden" name="segment" value="<?= htmlspecialchars($segmentSlug); ?>" />
                <label for="import-<?= htmlspecialchars($segmentSlug); ?>"><?= $isCompletedSegment ? 'Bulk upload completed installations (CSV / XLSX)' : 'Import CSV or Excel'; ?></label>
                <input id="import-<?= htmlspecialchars($segmentSlug); ?>" name="import_file" type="file" accept=".csv,.xlsx" required />
                <p class="form-helper"><?= $isCompletedSegment ? 'Start with the template so every application, subsidy, and billing field is captured for each project.' : 'Headers are matched automatically. New columns are added for you.'; ?></p>
                <button class="btn-primary" type="submit"><?= $isCompletedSegment ? 'Upload installations' : 'Upload file'; ?></button>
              </form>
              <form method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
                <input type="hidden" name="action" value="add_customer_column" />
                <input type="hidden" name="redirect_view" value="customers" />
                <input type="hidden" name="segment" value="<?= htmlspecialchars($segmentSlug); ?>" />
                <label for="new-column-<?= htmlspecialchars($segmentSlug); ?>">Add column label</label>
                <input id="new-column-<?= htmlspecialchars($segmentSlug); ?>" name="column_label" type="text" placeholder="Installer notes" required />
                <label for="new-column-type-<?= htmlspecialchars($segmentSlug); ?>">Data type</label>
                <select id="new-column-type-<?= htmlspecialchars($segmentSlug); ?>" name="column_type">
                  <option value="text" selected>Text</option>
                  <option value="date">Date</option>
                  <option value="phone">Phone</option>
                  <option value="number">Number</option>
                  <option value="email">Email</option>
                </select>
                <button class="btn-secondary" type="submit">Add column</button>
              </form>
              <form method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
                <input type="hidden" name="action" value="create_customer_entry" />
                <input type="hidden" name="redirect_view" value="customers" />
                <input type="hidden" name="segment" value="<?= htmlspecialchars($segmentSlug); ?>" />
                <div class="form-grid">
                  <?php foreach ($segmentColumns as $column): ?>
                    <?php
                      $columnKey = $column['key'];
                      $columnType = $column['type'] ?? 'text';
                      $inputType = 'text';
                      switch ($columnType) {
                        case 'date':
                          $inputType = 'date';
                          break;
                        case 'phone':
                          $inputType = 'tel';
                          break;
                        case 'number':
                          $inputType = 'number';
                          break;
                        case 'email':
                          $inputType = 'email';
                          break;
                      }
                    ?>
                    <div>
                      <label><?= htmlspecialchars($column['label'] ?? ucfirst($columnKey)); ?></label>
                      <input name="fields[<?= htmlspecialchars($columnKey); ?>]" type="<?= htmlspecialchars($inputType); ?>" <?php if ($inputType === 'number'): ?>step="any"<?php endif; ?> />
                    </div>
                  <?php endforeach; ?>
                </div>
                <div class="form-grid">
                  <div>
                    <label>Notes</label>
                    <textarea name="notes" rows="2" placeholder="Add reminder notes"></textarea>
                  </div>
                  <div>
                    <label>Reminder date</label>
                    <input name="reminder_on" type="date" />
                  </div>
                </div>
                <button class="btn-primary" type="submit"><?= $isCompletedSegment ? 'Add installation' : 'Add record'; ?></button>
                <?php if ($isCompletedSegment): ?>
                  <p class="form-helper">Log a single project once the installation and subsidy processing are complete.</p>
                <?php endif; ?>
              </form>
            </aside>
          </div>
        </section>
      <?php endforeach; ?>

    <?php elseif ($currentView === 'approvals'): ?>
      <section class="panel">
        <h2>Employee approvals</h2>
        <p class="lead">Review customer lifecycle updates and design changes submitted by team members. Approvals apply live updates, while declines keep the current state unchanged.</p>
        <div class="metric-grid">
          <div class="metric-card">
            <p class="metric-label">Pending</p>
            <p class="metric-value"><?= htmlspecialchars((string) ($employeeApprovalStats['pending'] ?? 0)); ?></p>
            <p class="metric-helper">Awaiting admin action</p>
          </div>
          <div class="metric-card">
            <p class="metric-label">Approved</p>
            <p class="metric-value"><?= htmlspecialchars((string) ($employeeApprovalStats['approved'] ?? 0)); ?></p>
            <p class="metric-helper">Completed decisions</p>
          </div>
          <div class="metric-card">
            <p class="metric-label">Declined</p>
            <p class="metric-value"><?= htmlspecialchars((string) ($employeeApprovalStats['declined'] ?? 0)); ?></p>
            <p class="metric-helper">Requests turned down</p>
          </div>
        </div>
      </section>

      <?php if (empty($pendingEmployeeRequests)): ?>
        <section class="panel">
          <h3>No pending requests</h3>
          <p class="lead">Employees have no submissions waiting for approval right now.</p>
        </section>
      <?php else: ?>
        <?php foreach ($pendingEmployeeRequests as $request): ?>
          <?php
            $requestId = $request['id'] ?? '';
            $requestTitle = $request['title'] ?? 'Employee request';
            $submittedAt = $request['submitted_at'] ?? $request['submitted'] ?? '';
            $submittedBy = $request['submitted_by'] ?? 'Employee';
            $owner = $request['owner'] ?? 'Admin team';
            $details = $request['details'] ?? '';
            $effectiveDate = $request['effective_date'] ?? '';
            $requestType = $request['type'] ?? 'general';
            $payload = is_array($request['payload'] ?? null) ? $request['payload'] : [];
            $segmentLabel = $request['segment_label'] ?? '';
            $submittedDisplay = $submittedAt && strtotime($submittedAt) ? date('j M Y, g:i A', strtotime($submittedAt)) : '—';
            $typeLabels = [
              'customer_add' => 'Customer addition',
              'customer_update' => 'Customer update',
              'design_change' => 'Design change',
              'general' => 'General request',
            ];
            $typeLabel = $typeLabels[$requestType] ?? ucfirst(str_replace('_', ' ', $requestType));
          ?>
          <section class="panel" id="approval-<?= htmlspecialchars($requestId); ?>">
            <h3><?= htmlspecialchars($requestTitle); ?></h3>
            <p class="lead">ID <?= htmlspecialchars($requestId); ?> · Submitted <?= htmlspecialchars($submittedDisplay); ?> · From <?= htmlspecialchars($submittedBy); ?> · Routed to <?= htmlspecialchars($owner); ?></p>
            <ul class="pill-list">
              <li><?= htmlspecialchars($typeLabel); ?></li>
              <?php if ($segmentLabel !== ''): ?>
                <li><?= htmlspecialchars($segmentLabel); ?></li>
              <?php endif; ?>
            </ul>
            <?php if ($details !== ''): ?>
              <p><?= htmlspecialchars($details); ?></p>
            <?php endif; ?>
            <?php if ($effectiveDate !== ''): ?>
              <p class="form-helper">Requested effective date: <?= htmlspecialchars($effectiveDate); ?></p>
            <?php endif; ?>
            <?php if (!empty($payload['fields']) && is_array($payload['fields'])): ?>
              <div class="table-wrapper" style="margin-top: 0.75rem;">
                <table>
                  <thead>
                    <tr>
                      <th>Field</th>
                      <th>Value</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($payload['fields'] as $fieldKey => $fieldValue): ?>
                      <tr>
                        <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) $fieldKey))); ?></td>
                        <td><?= htmlspecialchars((string) $fieldValue); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
            <?php if (!empty($payload['notes']) || !empty($payload['reminder_on'])): ?>
              <p class="form-helper">
                <?php if (!empty($payload['notes'])): ?>Notes: <?= htmlspecialchars($payload['notes']); ?><?php endif; ?>
                <?php if (!empty($payload['reminder_on'])): ?><?= !empty($payload['notes']) ? ' · ' : '' ?>Reminder: <?= htmlspecialchars($payload['reminder_on']); ?><?php endif; ?>
              </p>
            <?php endif; ?>
            <?php if ($requestType === 'customer_update' && isset($payload['target_segment']) && isset($payload['segment'])): ?>
              <?php if ($payload['target_segment'] !== $payload['segment']): ?>
                <p class="form-helper">Requested move: <?= htmlspecialchars((string) $payload['segment']); ?> → <?= htmlspecialchars((string) $payload['target_segment']); ?></p>
              <?php endif; ?>
            <?php endif; ?>
            <div class="workspace-grid">
              <form method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
                <input type="hidden" name="action" value="approve_employee_request" />
                <input type="hidden" name="redirect_view" value="approvals" />
                <input type="hidden" name="request_id" value="<?= htmlspecialchars($requestId); ?>" />
                <div class="form-group">
                  <label for="approve-notes-<?= htmlspecialchars($requestId); ?>">Decision notes (optional)</label>
                  <textarea id="approve-notes-<?= htmlspecialchars($requestId); ?>" name="decision_notes" rows="2" placeholder="Add audit notes for this approval"></textarea>
                </div>
                <div class="form-actions">
                  <button class="btn-primary" type="submit">Approve &amp; apply</button>
                </div>
              </form>
              <form method="post" autocomplete="off" onsubmit="return confirm('Decline this request?');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
                <input type="hidden" name="action" value="reject_employee_request" />
                <input type="hidden" name="redirect_view" value="approvals" />
                <input type="hidden" name="request_id" value="<?= htmlspecialchars($requestId); ?>" />
                <div class="form-group">
                  <label for="reject-notes-<?= htmlspecialchars($requestId); ?>">Reason for decline</label>
                  <textarea id="reject-notes-<?= htmlspecialchars($requestId); ?>" name="decision_notes" rows="2" placeholder="Explain why this change is being declined"></textarea>
                </div>
                <div class="form-actions">
                  <button class="btn-destructive" type="submit">Decline request</button>
                </div>
              </form>
            </div>
          </section>
        <?php endforeach; ?>
      <?php endif; ?>

      <section class="panel">
        <h2>Decision history</h2>
        <?php if (empty($employeeApprovalHistory)): ?>
          <p>No approval history recorded yet.</p>
        <?php else: ?>
          <div class="table-wrapper">
            <table>
              <thead>
                <tr>
                  <th>Request</th>
                  <th>Status</th>
                  <th>Resolved on</th>
                  <th>Outcome</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($employeeApprovalHistory as $historyEntry): ?>
                  <?php
                    $historyId = $historyEntry['id'] ?? '';
                    $historyTitle = $historyEntry['title'] ?? 'Request';
                    $historyStatus = $historyEntry['status'] ?? 'Completed';
                    $resolvedAt = $historyEntry['resolved_at'] ?? $historyEntry['resolved'] ?? '';
                    $resolvedDisplay = $resolvedAt && strtotime($resolvedAt) ? date('j M Y, g:i A', strtotime($resolvedAt)) : '—';
                    $outcome = $historyEntry['outcome'] ?? '—';
                    $statusSlug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $historyStatus) ?? '');
                  ?>
                  <tr>
                    <td>
                      <strong><?= htmlspecialchars($historyTitle); ?></strong>
                      <div class="form-helper">ID <?= htmlspecialchars($historyId); ?></div>
                    </td>
                    <td><span class="status-chip" data-status="<?= htmlspecialchars($statusSlug); ?>"><?= htmlspecialchars($historyStatus); ?></span></td>
                    <td><?= htmlspecialchars($resolvedDisplay); ?></td>
                    <td><?= htmlspecialchars($outcome); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>

    <?php elseif ($currentView === 'projects'): ?>
      <section class="panel">
        <h2>Project tracker</h2>
        <p class="lead">Monitor EPC and financing engagements, and update progress as teams execute.</p>
        <div class="workspace-grid">
          <div>
            <?php if (empty($projects)): ?>
              <p>No projects logged yet. Use the form to add your first initiative.</p>
            <?php else: ?>
              <div class="table-wrapper">
                <table>
                  <thead>
                    <tr>
                      <th>Project</th>
                      <th>Owner</th>
                      <th>Stage</th>
                      <th>Status</th>
                      <th>Target date</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($projects as $project): ?>
                      <?php
                        $status = $project['status'] ?? 'on-track';
                        $statusLabel = ucwords(str_replace('-', ' ', $status));
                        $targetDate = $project['target_date'] ?? '';
                        $targetFormatted = ($targetDate && strtotime($targetDate)) ? date('j M Y', strtotime($targetDate)) : '—';
                      ?>
                      <tr>
                        <td>
                          <strong><?= htmlspecialchars($project['name'] ?? ''); ?></strong>
                          <?php if (!empty($project['stage'])): ?>
                            <div class="form-helper">Stage: <?= htmlspecialchars($project['stage']); ?></div>
                          <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($project['owner'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($project['stage'] ?? '—'); ?></td>
                        <td><span class="status-chip" data-status="<?= htmlspecialchars($status); ?>"><?= htmlspecialchars($statusLabel); ?></span></td>
                        <td><?= htmlspecialchars($targetFormatted); ?></td>
                        <td>
                          <details class="manage">
                            <summary>Manage</summary>
                            <div class="manage-forms">
                              <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
                                <input type="hidden" name="action" value="update_project" />
                                <input type="hidden" name="redirect_view" value="projects" />
                                <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id'] ?? ''); ?>" />
                                <div class="form-grid">
                                  <div>
                                    <label for="project-stage-<?= htmlspecialchars($project['id'] ?? ''); ?>">Stage</label>
                                    <input id="project-stage-<?= htmlspecialchars($project['id'] ?? ''); ?>" name="stage" type="text" value="<?= htmlspecialchars($project['stage'] ?? ''); ?>" />
                                  </div>
                                  <div>
                                    <label for="project-status-<?= htmlspecialchars($project['id'] ?? ''); ?>">Status</label>
                                    <select id="project-status-<?= htmlspecialchars($project['id'] ?? ''); ?>" name="status">
                                      <?php foreach ($projectStatusOptions as $option): ?>
                                        <option value="<?= htmlspecialchars($option); ?>" <?= $status === $option ? 'selected' : ''; ?>><?= htmlspecialchars(ucwords(str_replace('-', ' ', $option))); ?></option>
                                      <?php endforeach; ?>
                                    </select>
                                  </div>
                                  <div>
                                    <label for="project-target-<?= htmlspecialchars($project['id'] ?? ''); ?>">Target date</label>
                                    <input id="project-target-<?= htmlspecialchars($project['id'] ?? ''); ?>" name="target_date" type="date" value="<?= htmlspecialchars($project['target_date'] ?? ''); ?>" />
                                  </div>
                                </div>
                                <button class="btn-primary" type="submit">Update project</button>
                              </form>
                              <form method="post" onsubmit="return confirm('Remove this project?');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
                                <input type="hidden" name="action" value="delete_project" />
                                <input type="hidden" name="redirect_view" value="projects" />
                                <input type="hidden" name="project_id" value="<?= htmlspecialchars($project['id'] ?? ''); ?>" />
                                <button class="btn-destructive" type="submit">Delete project</button>
                              </form>
                            </div>
                          </details>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
          <aside class="workspace-aside">
            <h3>Log a project</h3>
            <form method="post" autocomplete="off">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
              <input type="hidden" name="action" value="create_project" />
              <input type="hidden" name="redirect_view" value="projects" />
              <div class="form-grid">
                <div>
                  <label for="new-project-name">Project name</label>
                  <input id="new-project-name" name="project_name" type="text" placeholder="Rooftop rollout - Ranchi" required />
                </div>
                <div>
                  <label for="new-project-owner">Owner</label>
                  <input id="new-project-owner" name="project_owner" type="text" placeholder="Priya Sharma" required />
                </div>
              </div>
              <div class="form-grid">
                <div>
                  <label for="new-project-stage">Current stage</label>
                  <input id="new-project-stage" name="project_stage" type="text" placeholder="Installation" required />
                </div>
                <div>
                  <label for="new-project-status">Status</label>
                  <select id="new-project-status" name="project_status">
                    <?php foreach ($projectStatusOptions as $option): ?>
                      <option value="<?= htmlspecialchars($option); ?>" <?= $option === 'on-track' ? 'selected' : ''; ?>><?= htmlspecialchars(ucwords(str_replace('-', ' ', $option))); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div>
                <label for="new-project-date">Target date</label>
                <input id="new-project-date" name="target_date" type="date" />
              </div>
              <button class="btn-primary" type="submit">Add project</button>
            </form>
          </aside>
        </div>
      </section>
    <?php elseif ($currentView === 'tasks'): ?>
      <section class="panel">
        <h2>Leadership task board</h2>
        <p class="lead">Assign follow-ups, track due dates, and clear completed work.</p>
        <div class="workspace-grid">
          <div>
            <?php if (empty($tasks)): ?>
              <p>No tasks recorded. Create one using the form.</p>
            <?php else: ?>
              <div class="table-wrapper">
                <table>
                  <thead>
                    <tr>
                      <th>Task</th>
                      <th>Assignee</th>
                      <th>Status</th>
                      <th>Due</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($tasks as $task): ?>
                      <?php
                        $status = $task['status'] ?? 'Pending';
                        $dueDate = $task['due_date'] ?? '';
                        $dueFormatted = ($dueDate && strtotime($dueDate)) ? date('j M Y', strtotime($dueDate)) : '—';
                      ?>
                      <tr>
                        <td><?= htmlspecialchars($task['title'] ?? ''); ?></td>
                        <td><?= htmlspecialchars($task['assignee'] ?? ''); ?></td>
                        <td><span class="status-chip" data-status="<?= htmlspecialchars($status); ?>"><?= htmlspecialchars($status); ?></span></td>
                        <td><?= htmlspecialchars($dueFormatted); ?></td>
                        <td>
                          <details class="manage">
                            <summary>Manage</summary>
                            <div class="manage-forms">
                              <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
                                <input type="hidden" name="action" value="update_task" />
                                <input type="hidden" name="redirect_view" value="tasks" />
                                <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id'] ?? ''); ?>" />
                                <div class="form-grid">
                                  <div>
                                    <label for="task-status-<?= htmlspecialchars($task['id'] ?? ''); ?>">Status</label>
                                    <select id="task-status-<?= htmlspecialchars($task['id'] ?? ''); ?>" name="status">
                                      <?php foreach ($taskStatusOptions as $option): ?>
                                        <option value="<?= htmlspecialchars($option); ?>" <?= $status === $option ? 'selected' : ''; ?>><?= htmlspecialchars($option); ?></option>
                                      <?php endforeach; ?>
                                    </select>
                                  </div>
                                  <div>
                                    <label for="task-due-<?= htmlspecialchars($task['id'] ?? ''); ?>">Due date</label>
                                    <input id="task-due-<?= htmlspecialchars($task['id'] ?? ''); ?>" name="due_date" type="date" value="<?= htmlspecialchars($task['due_date'] ?? ''); ?>" />
                                  </div>
                                </div>
                                <button class="btn-primary" type="submit">Update task</button>
                              </form>
                              <form method="post" onsubmit="return confirm('Remove this task?');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
                                <input type="hidden" name="action" value="delete_task" />
                                <input type="hidden" name="redirect_view" value="tasks" />
                                <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id'] ?? ''); ?>" />
                                <button class="btn-destructive" type="submit">Delete task</button>
                              </form>
                            </div>
                          </details>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
          <aside class="workspace-aside">
            <h3>Add a task</h3>
            <form method="post" autocomplete="off">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
              <input type="hidden" name="action" value="create_task" />
              <input type="hidden" name="redirect_view" value="tasks" />
              <div class="form-grid">
                <div>
                  <label for="new-task-title">Task title</label>
                  <input id="new-task-title" name="task_title" type="text" placeholder="Approve installer onboarding" required />
                </div>
                <div>
                  <label for="new-task-assignee">Assignee</label>
                  <input id="new-task-assignee" name="task_assignee" type="text" placeholder="Deepak Entranchi" required />
                </div>
              </div>
              <div class="form-grid">
                <div>
                  <label for="new-task-status">Status</label>
                  <select id="new-task-status" name="task_status">
                    <?php foreach ($taskStatusOptions as $option): ?>
                      <option value="<?= htmlspecialchars($option); ?>" <?= $option === 'Pending' ? 'selected' : ''; ?>><?= htmlspecialchars($option); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label for="new-task-due">Due date</label>
                  <input id="new-task-due" name="task_due_date" type="date" />
                </div>
              </div>
              <button class="btn-primary" type="submit">Add task</button>
            </form>
          </aside>
        </div>
      </section>
    <?php elseif ($currentView === 'content'): ?>
      <section class="panel">
        <h2>Seasonal theme &amp; hero</h2>
        <p class="lead">Control the public site colours, headline messages, and hero banner assets.</p>
        <div class="workspace-grid">
          <div>
            <h3>Theme styling</h3>
            <form method="post" autocomplete="off">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
              <input type="hidden" name="action" value="update_site_theme" />
              <input type="hidden" name="redirect_view" value="content" />
              <div class="form-grid">
                <div>
                  <label for="theme-name">Theme name</label>
                  <input id="theme-name" name="active_theme" type="text" value="<?= htmlspecialchars($siteTheme['active_theme'] ?? 'seasonal'); ?>" placeholder="festive-diwali" />
                </div>
                <div>
                  <label for="theme-accent">Accent colour</label>
                  <input id="theme-accent" name="accent_color" type="color" value="<?= htmlspecialchars($accentColor); ?>" />
                  <p class="form-helper">Primary buttons and highlights use this colour. Text auto-adjusts to <?= htmlspecialchars($accentText); ?>.</p>
                </div>
              </div>
              <div class="palette-grid">
                <?php
                $paletteOptions = [
                    'page' => ['label' => 'Site backdrop', 'helper' => 'Overall dashboard background behind the shell.'],
                    'surface' => ['label' => 'Cards & forms', 'helper' => 'Panels, tables, and form controls.'],
                    'section' => ['label' => 'Workspace panels', 'helper' => 'Container backgrounds for each panel.'],
                    'hero' => ['label' => 'Login hero', 'helper' => 'Used for the outer gradient and hero blocks.'],
                    'callout' => ['label' => 'Callouts', 'helper' => 'Announcements and alert strips.'],
                    'footer' => ['label' => 'Footer & dark areas', 'helper' => 'Applies to sticky notes and footer rows.'],
                ];
                foreach ($paletteOptions as $slug => $meta):
                    $entry = $siteTheme['palette'][$slug] ?? ['background' => '#ffffff', 'text' => '#0f172a', 'muted' => '#64748b'];
                ?>
                  <div class="palette-card">
                    <label for="palette-<?= htmlspecialchars($slug); ?>"><?= htmlspecialchars($meta['label']); ?></label>
                    <div class="palette-card__preview" style="background: <?= htmlspecialchars($entry['background']); ?>; color: <?= htmlspecialchars($entry['text']); ?>;">
                      <span><?= htmlspecialchars($entry['background']); ?></span>
                      <span class="palette-card__text">Text: <?= htmlspecialchars($entry['text']); ?></span>
                    </div>
                    <input id="palette-<?= htmlspecialchars($slug); ?>" name="palette[<?= htmlspecialchars($slug); ?>][background]" type="color" value="<?= htmlspecialchars($entry['background']); ?>" />
                    <p class="form-helper"><?= htmlspecialchars($meta['helper']); ?></p>
                  </div>
                <?php endforeach; ?>
              </div>
              <div class="form-grid">
                <div>
                  <label for="theme-label">Season label</label>
                  <input id="theme-label" name="season_label" type="text" value="<?= htmlspecialchars($siteTheme['season_label'] ?? ''); ?>" required />
                </div>
                <div>
                  <label for="theme-background">Background image URL</label>
                  <input id="theme-background" name="background_image" type="url" value="<?= htmlspecialchars($siteTheme['background_image'] ?? ''); ?>" placeholder="https://.../festival-banner.jpg" />
                </div>
              </div>
              <div>
                <label for="theme-announcement">Theme announcement</label>
                <textarea id="theme-announcement" name="theme_announcement" rows="3" placeholder="Optional seasonal headline"><?= htmlspecialchars($siteTheme['announcement'] ?? ''); ?></textarea>
              </div>
              <div class="form-actions">
                <button class="btn-primary" type="submit">Save theme</button>
              </div>
            </form>
            <form method="post" class="reset-theme" autocomplete="off">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
              <input type="hidden" name="action" value="reset_site_theme" />
              <input type="hidden" name="redirect_view" value="content" />
              <button class="btn-ghost" type="submit">Restore default theme</button>
              <p class="form-helper">Reset colours, announcements, and imagery back to the starter defaults.</p>
            </form>
          </div>
          <aside class="workspace-aside">
            <h3>Homepage hero</h3>
            <form method="post" autocomplete="off">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
              <input type="hidden" name="action" value="update_home_hero" />
              <input type="hidden" name="redirect_view" value="content" />
              <div class="form-grid">
                <div>
                  <label for="hero-title">Hero title</label>
                  <input id="hero-title" name="hero_title" type="text" value="<?= htmlspecialchars($homeHero['title'] ?? ''); ?>" required />
                </div>
                <div>
                  <label for="hero-subtitle">Hero subtitle</label>
                  <textarea id="hero-subtitle" name="hero_subtitle" rows="3" required><?= htmlspecialchars($homeHero['subtitle'] ?? ''); ?></textarea>
                </div>
              </div>
              <div class="form-grid">
                <div>
                  <label for="hero-image">Hero image URL</label>
                  <input id="hero-image" name="hero_image" type="url" value="<?= htmlspecialchars($homeHero['image'] ?? ''); ?>" placeholder="images/hero/hero.png" />
                </div>
                <div>
                  <label for="hero-caption">Image caption</label>
                  <input id="hero-caption" name="hero_image_caption" type="text" value="<?= htmlspecialchars($homeHero['image_caption'] ?? ''); ?>" />
                </div>
              </div>
              <div class="form-grid">
                <div>
                  <label for="hero-bubble-heading">Highlight heading</label>
                  <input id="hero-bubble-heading" name="hero_bubble_heading" type="text" value="<?= htmlspecialchars($homeHero['bubble_heading'] ?? ''); ?>" />
                </div>
                <div>
                  <label for="hero-bubble-body">Highlight body</label>
                  <input id="hero-bubble-body" name="hero_bubble_body" type="text" value="<?= htmlspecialchars($homeHero['bubble_body'] ?? ''); ?>" />
                </div>
              </div>
              <div>
                <label for="hero-bullets">Hero bullet points (one per line)</label>
                <textarea id="hero-bullets" name="hero_bullets" rows="4" placeholder="Add savings metric, subsidy highlight, etc."><?= htmlspecialchars($heroBulletsValue); ?></textarea>
              </div>
              <button class="btn-primary" type="submit">Save hero content</button>
            </form>
          </aside>
        </div>
      </section>

      <section class="panel">
        <h2>Homepage sections</h2>
        <p class="lead">Create new homepage blocks or update existing ones without editing code. Draft sections stay hidden.</p>
        <div class="workspace-grid">
          <div>
            <?php if (empty($homeSections)): ?>
              <p>No dynamic sections yet. Use the form to publish the first one.</p>
            <?php else: ?>
              <?php foreach ($homeSections as $section): ?>
                <?php $sectionStatus = $section['status'] ?? 'draft'; ?>
                <details class="manage">
                  <summary>
                    <span><?= htmlspecialchars($section['title'] !== '' ? $section['title'] : 'Untitled section'); ?></span>
                    <span class="status-chip" data-status="<?= htmlspecialchars($sectionStatus); ?>"><?= htmlspecialchars(ucfirst($sectionStatus)); ?></span>
                  </summary>
                  <div class="manage-forms">
                    <form method="post" autocomplete="off">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
                      <input type="hidden" name="action" value="update_home_section" />
                      <input type="hidden" name="redirect_view" value="content" />
                      <input type="hidden" name="section_id" value="<?= htmlspecialchars($section['id'] ?? ''); ?>" />
                      <div class="form-grid">
                        <div>
                          <label>Eyebrow label</label>
                          <input name="section_eyebrow" type="text" value="<?= htmlspecialchars($section['eyebrow'] ?? ''); ?>" />
                        </div>
                        <div>
                          <label>Display order</label>
                          <input name="section_display_order" type="number" value="<?= htmlspecialchars((string) ($section['display_order'] ?? 0)); ?>" />
                        </div>
                      </div>
                      <div class="form-grid">
                        <div>
                          <label>Title</label>
                          <input name="section_title" type="text" value="<?= htmlspecialchars($section['title'] ?? ''); ?>" />
                        </div>
                        <div>
                          <label>Subtitle</label>
                          <input name="section_subtitle" type="text" value="<?= htmlspecialchars($section['subtitle'] ?? ''); ?>" />
                        </div>
                      </div>
                      <div>
                        <label>Body content (paragraphs)</label>
                        <textarea name="section_body" rows="4" placeholder="Add paragraph content here. Separate paragraphs with a blank line."><?= htmlspecialchars(implode("\n\n", $section['body'] ?? [])); ?></textarea>
                      </div>
                      <div>
                        <label>Bullet points (one per line)</label>
                        <textarea name="section_bullets" rows="3" placeholder="Highlight financing options&#10;Showcase O&M support"><?= htmlspecialchars(implode("\n", $section['bullets'] ?? [])); ?></textarea>
                      </div>
                      <div class="form-grid">
                        <div>
                          <label>CTA text</label>
                          <input name="section_cta_text" type="text" value="<?= htmlspecialchars($section['cta']['text'] ?? ''); ?>" />
                        </div>
                        <div>
                          <label>CTA link</label>
                          <input name="section_cta_url" type="url" value="<?= htmlspecialchars($section['cta']['url'] ?? ''); ?>" placeholder="https://..." />
                        </div>
                      </div>
                      <div class="form-grid">
                        <div>
                          <label>Media type</label>
                          <?php $mediaType = strtolower($section['media']['type'] ?? 'none'); ?>
                          <select name="section_media_type">
                            <option value="none" <?= $mediaType === 'none' ? 'selected' : ''; ?>>None</option>
                            <option value="image" <?= $mediaType === 'image' ? 'selected' : ''; ?>>Image</option>
                            <option value="video" <?= $mediaType === 'video' ? 'selected' : ''; ?>>Video</option>
                          </select>
                        </div>
                        <div>
                          <label>Media URL</label>
                          <input name="section_media_src" type="url" value="<?= htmlspecialchars($section['media']['src'] ?? ''); ?>" placeholder="images/sections/spotlight.jpg" />
                        </div>
                        <div>
                          <label>Media alt text</label>
                          <input name="section_media_alt" type="text" value="<?= htmlspecialchars($section['media']['alt'] ?? ''); ?>" />
                        </div>
                      </div>
                      <div class="form-grid">
                        <div>
                          <label>Background style</label>
                          <select name="section_background_style">
                            <?php foreach ($siteTheme['palette'] as $paletteKey => $_paletteEntry): ?>
                              <?php if ($paletteKey === 'accent') { continue; } ?>
                              <option value="<?= htmlspecialchars($paletteKey); ?>" <?= $paletteKey === ($section['background_style'] ?? 'section') ? 'selected' : ''; ?>><?= htmlspecialchars(ucfirst(str_replace('-', ' ', $paletteKey))); ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div>
                          <label>Status</label>
                          <select name="section_status">
                            <option value="draft" <?= $sectionStatus === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="published" <?= $sectionStatus === 'published' ? 'selected' : ''; ?>>Published</option>
                          </select>
                        </div>
                      </div>
                      <button class="btn-primary" type="submit">Update section</button>
                    </form>
                    <form method="post" onsubmit="return confirm('Delete this section?');">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
                      <input type="hidden" name="action" value="delete_home_section" />
                      <input type="hidden" name="redirect_view" value="content" />
                      <input type="hidden" name="section_id" value="<?= htmlspecialchars($section['id'] ?? ''); ?>" />
                      <button class="btn-destructive" type="submit">Delete section</button>
                    </form>
                  </div>
                </details>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <aside class="workspace-aside">
            <h3>Add section</h3>
            <form method="post" autocomplete="off">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
              <input type="hidden" name="action" value="create_home_section" />
              <input type="hidden" name="redirect_view" value="content" />
              <div class="form-grid">
                <div>
                  <label for="new-section-title">Title</label>
                  <input id="new-section-title" name="section_title" type="text" placeholder="Spotlight: Rooftop financing" />
                </div>
                <div>
                  <label for="new-section-order">Display order</label>
                  <input id="new-section-order" name="section_display_order" type="number" value="0" />
                </div>
              </div>
              <div>
                <label for="new-section-subtitle">Subtitle</label>
                <input id="new-section-subtitle" name="section_subtitle" type="text" placeholder="Tailor a new message for leads" />
              </div>
              <div>
                <label for="new-section-body">Body content</label>
                <textarea id="new-section-body" name="section_body" rows="4" placeholder="Describe the new announcement or service."></textarea>
              </div>
              <div>
                <label for="new-section-bullets">Bullet points</label>
                <textarea id="new-section-bullets" name="section_bullets" rows="3" placeholder="Financing approved in 48 hours&#10;Dedicated installation crew"></textarea>
              </div>
              <div class="form-grid">
                <div>
                  <label for="new-section-cta-text">CTA text</label>
                  <input id="new-section-cta-text" name="section_cta_text" type="text" placeholder="Schedule a call" />
                </div>
                <div>
                  <label for="new-section-cta-url">CTA link</label>
                  <input id="new-section-cta-url" name="section_cta_url" type="url" placeholder="https://dakshayani.co.in/contact.html" />
                </div>
              </div>
              <div class="form-grid">
                <div>
                  <label for="new-section-media-type">Media type</label>
                  <select id="new-section-media-type" name="section_media_type">
                    <option value="none" selected>None</option>
                    <option value="image">Image</option>
                    <option value="video">Video</option>
                  </select>
                </div>
                <div>
                  <label for="new-section-media-src">Media URL</label>
                  <input id="new-section-media-src" name="section_media_src" type="url" placeholder="images/sections/custom.jpg" />
                </div>
                <div>
                  <label for="new-section-media-alt">Media alt text</label>
                  <input id="new-section-media-alt" name="section_media_alt" type="text" />
                </div>
              </div>
              <div class="form-grid">
                <div>
                  <label for="new-section-background">Background style</label>
                  <select id="new-section-background" name="section_background_style">
                    <?php foreach ($siteTheme['palette'] as $paletteKey => $_paletteEntry): ?>
                      <?php if ($paletteKey === 'accent') { continue; } ?>
                      <option value="<?= htmlspecialchars($paletteKey); ?>" <?= $paletteKey === 'section' ? 'selected' : ''; ?>><?= htmlspecialchars(ucfirst(str_replace('-', ' ', $paletteKey))); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label for="new-section-status">Status</label>
                  <select id="new-section-status" name="section_status">
                    <option value="draft">Draft</option>
                    <option value="published" selected>Published</option>
                  </select>
                </div>
              </div>
              <button class="btn-primary" type="submit">Create section</button>
            </form>
          </aside>
        </div>
      </section>

      <section class="panel">
        <h2>Seasonal offers</h2>
        <p class="lead">Publish festive or monthly offers on the homepage. Draft entries stay hidden.</p>
        <div class="workspace-grid">
          <div>
            <?php if (empty($homeOffers)): ?>
              <p>No seasonal offers yet. Use the form to create one.</p>
            <?php else: ?>
              <?php foreach ($homeOffers as $offer): ?>
                <?php $offerStatus = $offer['status'] ?? 'draft'; ?>
                <details class="manage">
                  <summary>
                    <span><?= htmlspecialchars($offer['title'] !== '' ? $offer['title'] : 'Untitled offer'); ?></span>
                    <span class="status-chip" data-status="<?= htmlspecialchars($offerStatus); ?>"><?= htmlspecialchars(ucfirst($offerStatus)); ?></span>
                  </summary>
                  <div class="manage-forms">
                    <form method="post" autocomplete="off">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
                      <input type="hidden" name="action" value="update_offer" />
                      <input type="hidden" name="redirect_view" value="content" />
                      <input type="hidden" name="offer_id" value="<?= htmlspecialchars($offer['id'] ?? ''); ?>" />
                      <div class="form-grid">
                        <div>
                          <label>Title</label>
                          <input name="offer_title" type="text" value="<?= htmlspecialchars($offer['title'] ?? ''); ?>" />
                        </div>
                        <div>
                          <label>Badge label</label>
                          <input name="offer_badge" type="text" value="<?= htmlspecialchars($offer['badge'] ?? ''); ?>" placeholder="Diwali" />
                        </div>
                      </div>
                      <div>
                        <label>Description</label>
                        <textarea name="offer_description" rows="3" placeholder="Describe the offer details"><?= htmlspecialchars($offer['description'] ?? ''); ?></textarea>
                      </div>
                      <div class="form-grid">
                        <div>
                          <label>Starts on</label>
                          <input name="offer_starts_on" type="date" value="<?= htmlspecialchars($offer['starts_on'] ?? ''); ?>" />
                        </div>
                        <div>
                          <label>Ends on</label>
                          <input name="offer_ends_on" type="date" value="<?= htmlspecialchars($offer['ends_on'] ?? ''); ?>" />
                        </div>
                      </div>
                      <div class="form-grid">
                        <div>
                          <label>Status</label>
                          <select name="offer_status">
                            <option value="draft" <?= $offerStatus === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="published" <?= $offerStatus === 'published' ? 'selected' : ''; ?>>Published</option>
                          </select>
                        </div>
                        <div>
                          <label>CTA label</label>
                          <input name="offer_cta_text" type="text" value="<?= htmlspecialchars($offer['cta_text'] ?? ''); ?>" placeholder="Book now" />
                        </div>
                      </div>
                      <div class="form-grid">
                        <div>
                          <label>CTA link</label>
                          <input name="offer_cta_url" type="url" value="<?= htmlspecialchars($offer['cta_url'] ?? ''); ?>" placeholder="https://wa.me/..." />
                        </div>
                        <div>
                          <label>Image</label>
                          <input name="offer_image" type="url" value="<?= htmlspecialchars($offer['image'] ?? ''); ?>" placeholder="images/offers/diwali.png" />
                        </div>
                      </div>
                      <button class="btn-primary" type="submit">Update offer</button>
                    </form>
                    <form method="post" onsubmit="return confirm('Delete this offer?');">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
                      <input type="hidden" name="action" value="delete_offer" />
                      <input type="hidden" name="redirect_view" value="content" />
                      <input type="hidden" name="offer_id" value="<?= htmlspecialchars($offer['id'] ?? ''); ?>" />
                      <button class="btn-destructive" type="submit">Delete offer</button>
                    </form>
                  </div>
                </details>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <aside class="workspace-aside">
            <h3>Add seasonal offer</h3>
            <form method="post" autocomplete="off">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
              <input type="hidden" name="action" value="create_offer" />
              <input type="hidden" name="redirect_view" value="content" />
              <div class="form-grid">
                <div>
                  <label for="new-offer-title">Title</label>
                  <input id="new-offer-title" name="offer_title" type="text" placeholder="Festive rooftop cashback" />
                </div>
                <div>
                  <label for="new-offer-badge">Badge</label>
                  <input id="new-offer-badge" name="offer_badge" type="text" placeholder="Holi" />
                </div>
              </div>
              <div>
                <label for="new-offer-description">Description</label>
                <textarea id="new-offer-description" name="offer_description" rows="3" placeholder="Explain the promotion"></textarea>
              </div>
              <div class="form-grid">
                <div>
                  <label for="new-offer-start">Starts on</label>
                  <input id="new-offer-start" name="offer_starts_on" type="date" />
                </div>
                <div>
                  <label for="new-offer-end">Ends on</label>
                  <input id="new-offer-end" name="offer_ends_on" type="date" />
                </div>
              </div>
              <div class="form-grid">
                <div>
                  <label for="new-offer-status">Status</label>
                  <select id="new-offer-status" name="offer_status">
                    <option value="draft" selected>Draft</option>
                    <option value="published">Published</option>
                  </select>
                </div>
                <div>
                  <label for="new-offer-cta-text">CTA label</label>
                  <input id="new-offer-cta-text" name="offer_cta_text" type="text" placeholder="Call now" />
                </div>
              </div>
              <div class="form-grid">
                <div>
                  <label for="new-offer-cta-url">CTA link</label>
                  <input id="new-offer-cta-url" name="offer_cta_url" type="url" placeholder="https://wa.me/917070278178" />
                </div>
                <div>
                  <label for="new-offer-image">Image URL</label>
                  <input id="new-offer-image" name="offer_image" type="url" placeholder="images/offers/offer.png" />
                </div>
              </div>
              <button class="btn-primary" type="submit">Create offer</button>
            </form>
          </aside>
        </div>
      </section>

      <section class="panel">
        <h2>Customer testimonials</h2>
        <p class="lead">Publish social proof for homeowners, MSMEs, and industrial clients.</p>
        <div class="workspace-grid">
          <div>
            <?php if (empty($testimonials)): ?>
              <p>No testimonials saved yet.</p>
            <?php else: ?>
              <?php foreach ($testimonials as $testimonial): ?>
                <?php $testimonialStatus = $testimonial['status'] ?? 'published'; ?>
                <details class="manage">
                  <summary>
                    <span><?= htmlspecialchars($testimonial['name'] ?? 'Unnamed customer'); ?></span>
                    <span class="status-chip" data-status="<?= htmlspecialchars($testimonialStatus); ?>"><?= htmlspecialchars(ucfirst($testimonialStatus)); ?></span>
                  </summary>
                  <div class="manage-forms">
                    <form method="post" autocomplete="off">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
                      <input type="hidden" name="action" value="update_testimonial" />
                      <input type="hidden" name="redirect_view" value="content" />
                      <input type="hidden" name="testimonial_id" value="<?= htmlspecialchars($testimonial['id'] ?? ''); ?>" />
                      <div>
                        <label>Quote</label>
                        <textarea name="testimonial_quote" rows="3" required><?= htmlspecialchars($testimonial['quote'] ?? ''); ?></textarea>
                      </div>
                      <div class="form-grid">
                        <div>
                          <label>Name</label>
                          <input name="testimonial_name" type="text" value="<?= htmlspecialchars($testimonial['name'] ?? ''); ?>" required />
                        </div>
                        <div>
                          <label>Location</label>
                          <input name="testimonial_location" type="text" value="<?= htmlspecialchars($testimonial['location'] ?? ''); ?>" placeholder="Ranchi" />
                        </div>
                      </div>
                      <div class="form-grid">
                        <div>
                          <label>Role or system</label>
                          <input name="testimonial_role" type="text" value="<?= htmlspecialchars($testimonial['role'] ?? ''); ?>" placeholder="8 kW residential" />
                        </div>
                        <div>
                          <label>Status</label>
                          <select name="testimonial_status">
                            <option value="draft" <?= $testimonialStatus === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="published" <?= $testimonialStatus === 'published' ? 'selected' : ''; ?>>Published</option>
                          </select>
                        </div>
                      </div>
                      <div>
                        <label>Image URL</label>
                        <input name="testimonial_image" type="url" value="<?= htmlspecialchars($testimonial['image'] ?? ''); ?>" placeholder="images/testimonials/customer.jpg" />
                      </div>
                      <button class="btn-primary" type="submit">Update testimonial</button>
                    </form>
                    <form method="post" onsubmit="return confirm('Delete this testimonial?');">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
                      <input type="hidden" name="action" value="delete_testimonial" />
                      <input type="hidden" name="redirect_view" value="content" />
                      <input type="hidden" name="testimonial_id" value="<?= htmlspecialchars($testimonial['id'] ?? ''); ?>" />
                      <button class="btn-destructive" type="submit">Delete testimonial</button>
                    </form>
                  </div>
                </details>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <aside class="workspace-aside">
            <h3>Add testimonial</h3>
            <form method="post" autocomplete="off">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
              <input type="hidden" name="action" value="create_testimonial" />
              <input type="hidden" name="redirect_view" value="content" />
              <div>
                <label for="new-testimonial-quote">Quote</label>
                <textarea id="new-testimonial-quote" name="testimonial_quote" rows="3" required></textarea>
              </div>
              <div class="form-grid">
                <div>
                  <label for="new-testimonial-name">Name</label>
                  <input id="new-testimonial-name" name="testimonial_name" type="text" required />
                </div>
                <div>
                  <label for="new-testimonial-location">Location</label>
                  <input id="new-testimonial-location" name="testimonial_location" type="text" />
                </div>
              </div>
              <div class="form-grid">
                <div>
                  <label for="new-testimonial-role">Role or system</label>
                  <input id="new-testimonial-role" name="testimonial_role" type="text" />
                </div>
                <div>
                  <label for="new-testimonial-status">Status</label>
                  <select id="new-testimonial-status" name="testimonial_status">
                    <option value="draft">Draft</option>
                    <option value="published" selected>Published</option>
                  </select>
                </div>
              </div>
              <div>
                <label for="new-testimonial-image">Image URL</label>
                <input id="new-testimonial-image" name="testimonial_image" type="url" placeholder="images/testimonials/customer.jpg" />
              </div>
              <button class="btn-primary" type="submit">Create testimonial</button>
            </form>
          </aside>
        </div>
      </section>

      <section class="panel">
        <h2>Blog posts</h2>
        <p class="lead">Draft insights for the Knowledge Hub and publish when ready.</p>
        <div class="workspace-grid">
          <div>
            <?php if (empty($blogPosts)): ?>
              <p>No blog posts created yet.</p>
            <?php else: ?>
              <?php foreach ($blogPosts as $post): ?>
                <?php $postStatus = $post['status'] ?? 'draft'; ?>
                <details class="manage">
                  <summary>
                    <span><?= htmlspecialchars($post['title'] ?? 'Untitled post'); ?></span>
                    <span class="status-chip" data-status="<?= htmlspecialchars($postStatus); ?>"><?= htmlspecialchars(ucfirst($postStatus)); ?></span>
                  </summary>
                  <div class="manage-forms">
                    <form method="post" autocomplete="off">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
                      <input type="hidden" name="action" value="update_blog_post" />
                      <input type="hidden" name="redirect_view" value="content" />
                      <input type="hidden" name="post_id" value="<?= htmlspecialchars($post['id'] ?? ''); ?>" />
                      <div class="form-grid">
                        <div>
                          <label>Title</label>
                          <input name="post_title" type="text" value="<?= htmlspecialchars($post['title'] ?? ''); ?>" required />
                        </div>
                        <div>
                          <label>Slug</label>
                          <input name="post_slug" type="text" value="<?= htmlspecialchars($post['slug'] ?? ''); ?>" required />
                          <p class="form-helper">Used in the article URL.</p>
                        </div>
                      </div>
                      <div>
                        <label>Excerpt</label>
                        <textarea name="post_excerpt" rows="2" placeholder="Optional teaser paragraph"><?= htmlspecialchars($post['excerpt'] ?? ''); ?></textarea>
                      </div>
                      <div class="form-grid">
                        <div>
                          <label>Hero image URL</label>
                          <input name="post_hero_image" type="url" value="<?= htmlspecialchars($post['hero_image'] ?? ''); ?>" placeholder="images/blog/hero.jpg" />
                        </div>
                        <div>
                          <label>Read time (minutes)</label>
                          <input name="post_read_time" type="number" min="0" value="<?= htmlspecialchars((string) ($post['read_time_minutes'] ?? '')); ?>" />
                        </div>
                      </div>
                      <div class="form-grid">
                        <div>
                          <label>Author name</label>
                          <input name="post_author_name" type="text" value="<?= htmlspecialchars($post['author']['name'] ?? ''); ?>" />
                        </div>
                        <div>
                          <label>Author role</label>
                          <input name="post_author_role" type="text" value="<?= htmlspecialchars($post['author']['role'] ?? ''); ?>" />
                        </div>
                      </div>
                      <div>
                        <label>Tags (comma separated)</label>
                        <input name="post_tags" type="text" value="<?= htmlspecialchars(implode(', ', $post['tags'] ?? [])); ?>" placeholder="Subsidy, Rooftop" />
                      </div>
                      <div>
                        <label>Content (separate paragraphs with blank lines)</label>
                        <textarea name="post_content" rows="8" required><?= htmlspecialchars(implode("\n\n", $post['content'] ?? [])); ?></textarea>
                      </div>
                      <div>
                        <label>Status</label>
                        <select name="post_status">
                          <option value="draft" <?= $postStatus === 'draft' ? 'selected' : ''; ?>>Draft</option>
                          <option value="published" <?= $postStatus === 'published' ? 'selected' : ''; ?>>Published</option>
                        </select>
                      </div>
                      <button class="btn-primary" type="submit">Update post</button>
                    </form>
                    <form method="post" onsubmit="return confirm('Delete this blog post?');">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
                      <input type="hidden" name="action" value="delete_blog_post" />
                      <input type="hidden" name="redirect_view" value="content" />
                      <input type="hidden" name="post_id" value="<?= htmlspecialchars($post['id'] ?? ''); ?>" />
                      <button class="btn-destructive" type="submit">Delete post</button>
                    </form>
                  </div>
                </details>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <aside class="workspace-aside">
            <h3>Create blog post</h3>
            <form method="post" autocomplete="off">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
              <input type="hidden" name="action" value="create_blog_post" />
              <input type="hidden" name="redirect_view" value="content" />
              <div>
                <label for="new-post-title">Title</label>
                <input id="new-post-title" name="post_title" type="text" required />
              </div>
              <div>
                <label for="new-post-slug">Slug</label>
                <input id="new-post-slug" name="post_slug" type="text" placeholder="pm-surya-ghar-guide" />
              </div>
              <div>
                <label for="new-post-excerpt">Excerpt</label>
                <textarea id="new-post-excerpt" name="post_excerpt" rows="2"></textarea>
              </div>
              <div>
                <label for="new-post-hero">Hero image URL</label>
                <input id="new-post-hero" name="post_hero_image" type="url" />
              </div>
              <div>
                <label for="new-post-tags">Tags</label>
                <input id="new-post-tags" name="post_tags" type="text" placeholder="Finance, Rooftop" />
              </div>
              <div>
                <label for="new-post-read-time">Read time (minutes)</label>
                <input id="new-post-read-time" name="post_read_time" type="number" min="0" />
              </div>
              <div class="form-grid">
                <div>
                  <label for="new-post-author">Author name</label>
                  <input id="new-post-author" name="post_author_name" type="text" placeholder="Vishesh Vardhan" />
                </div>
                <div>
                  <label for="new-post-author-role">Author role</label>
                  <input id="new-post-author-role" name="post_author_role" type="text" placeholder="Growth head" />
                </div>
              </div>
              <div>
                <label for="new-post-content">Content</label>
                <textarea id="new-post-content" name="post_content" rows="8" required></textarea>
              </div>
              <div>
                <label for="new-post-status">Status</label>
                <select id="new-post-status" name="post_status">
                  <option value="draft" selected>Draft</option>
                  <option value="published">Published</option>
                </select>
              </div>
              <button class="btn-primary" type="submit">Create post</button>
            </form>
          </aside>
        </div>
      </section>

      <section class="panel">
        <h2>Case studies</h2>
        <p class="lead">Showcase project outcomes with metrics, highlights, and media.</p>
        <div class="workspace-grid">
          <div>
            <?php if (empty($caseStudies)): ?>
              <p>No case studies have been documented yet.</p>
            <?php else: ?>
              <?php foreach ($caseStudies as $caseStudy): ?>
                <?php $caseStatus = $caseStudy['status'] ?? 'published'; ?>
                <details class="manage">
                  <summary>
                    <span><?= htmlspecialchars($caseStudy['title'] ?? 'Untitled case study'); ?></span>
                    <span class="status-chip" data-status="<?= htmlspecialchars($caseStatus); ?>"><?= htmlspecialchars(ucfirst($caseStatus)); ?></span>
                  </summary>
                  <div class="manage-forms">
                    <form method="post" autocomplete="off">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
                      <input type="hidden" name="action" value="update_case_study" />
                      <input type="hidden" name="redirect_view" value="content" />
                      <input type="hidden" name="case_id" value="<?= htmlspecialchars($caseStudy['id'] ?? ''); ?>" />
                      <div class="form-grid">
                        <div>
                          <label>Title</label>
                          <input name="case_title" type="text" value="<?= htmlspecialchars($caseStudy['title'] ?? ''); ?>" required />
                        </div>
                        <div>
                          <label>Segment</label>
                          <select name="case_segment">
                            <?php $segments = ['residential' => 'Residential', 'commercial' => 'Commercial', 'industrial' => 'Industrial', 'agriculture' => 'Agriculture']; ?>
                            <?php foreach ($segments as $segmentValue => $segmentLabel): ?>
                              <option value="<?= htmlspecialchars($segmentValue); ?>" <?= ($caseStudy['segment'] ?? '') === $segmentValue ? 'selected' : ''; ?>><?= htmlspecialchars($segmentLabel); ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                      </div>
                      <div class="form-grid">
                        <div>
                          <label>Location</label>
                          <input name="case_location" type="text" value="<?= htmlspecialchars($caseStudy['location'] ?? ''); ?>" />
                        </div>
                        <div>
                          <label>Status</label>
                          <select name="case_status">
                            <option value="draft" <?= $caseStatus === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="published" <?= $caseStatus === 'published' ? 'selected' : ''; ?>>Published</option>
                          </select>
                        </div>
                      </div>
                      <div>
                        <label>Summary</label>
                        <textarea name="case_summary" rows="3" required><?= htmlspecialchars($caseStudy['summary'] ?? ''); ?></textarea>
                      </div>
                      <div class="form-grid">
                        <div>
                          <label>Capacity (kW)</label>
                          <input name="case_capacity_kw" type="number" step="0.1" value="<?= htmlspecialchars((string) ($caseStudy['capacity_kw'] ?? '')); ?>" />
                        </div>
                        <div>
                          <label>Annual generation (kWh)</label>
                          <input name="case_generation_kwh" type="number" step="0.1" value="<?= htmlspecialchars((string) ($caseStudy['annual_generation_kwh'] ?? '')); ?>" />
                        </div>
                      </div>
                      <div class="form-grid">
                        <div>
                          <label>CO₂ offset (tonnes)</label>
                          <input name="case_co2_tonnes" type="number" step="0.1" value="<?= htmlspecialchars((string) ($caseStudy['co2_offset_tonnes'] ?? '')); ?>" />
                        </div>
                        <div>
                          <label>Payback (years)</label>
                          <input name="case_payback_years" type="number" step="0.1" value="<?= htmlspecialchars((string) ($caseStudy['payback_years'] ?? '')); ?>" />
                        </div>
                      </div>
                      <div>
                        <label>Highlights (one per line)</label>
                        <textarea name="case_highlights" rows="3" placeholder="Subsidy filed in 18 days&#10;O&M with SCADA"><?= htmlspecialchars(implode("\n", $caseStudy['highlights'] ?? [])); ?></textarea>
                      </div>
                      <div class="form-grid">
                        <div>
                          <label>Image URL</label>
                          <input name="case_image" type="url" value="<?= htmlspecialchars($caseStudy['image']['src'] ?? ''); ?>" placeholder="images/projects/case.jpg" />
                        </div>
                        <div>
                          <label>Image alt text</label>
                          <input name="case_image_alt" type="text" value="<?= htmlspecialchars($caseStudy['image']['alt'] ?? ''); ?>" />
                        </div>
                      </div>
                      <button class="btn-primary" type="submit">Update case study</button>
                    </form>
                    <form method="post" onsubmit="return confirm('Delete this case study?');">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
                      <input type="hidden" name="action" value="delete_case_study" />
                      <input type="hidden" name="redirect_view" value="content" />
                      <input type="hidden" name="case_id" value="<?= htmlspecialchars($caseStudy['id'] ?? ''); ?>" />
                      <button class="btn-destructive" type="submit">Delete case study</button>
                    </form>
                  </div>
                </details>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <aside class="workspace-aside">
            <h3>Add case study</h3>
            <form method="post" autocomplete="off">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
              <input type="hidden" name="action" value="create_case_study" />
              <input type="hidden" name="redirect_view" value="content" />
              <div>
                <label for="new-case-title">Title</label>
                <input id="new-case-title" name="case_title" type="text" required />
              </div>
              <div>
                <label for="new-case-segment">Segment</label>
                <select id="new-case-segment" name="case_segment">
                  <option value="residential" selected>Residential</option>
                  <option value="commercial">Commercial</option>
                  <option value="industrial">Industrial</option>
                  <option value="agriculture">Agriculture</option>
                </select>
              </div>
              <div>
                <label for="new-case-location">Location</label>
                <input id="new-case-location" name="case_location" type="text" />
              </div>
              <div>
                <label for="new-case-summary">Summary</label>
                <textarea id="new-case-summary" name="case_summary" rows="3" required></textarea>
              </div>
              <div class="form-grid">
                <div>
                  <label for="new-case-capacity">Capacity (kW)</label>
                  <input id="new-case-capacity" name="case_capacity_kw" type="number" step="0.1" />
                </div>
                <div>
                  <label for="new-case-generation">Annual generation (kWh)</label>
                  <input id="new-case-generation" name="case_generation_kwh" type="number" step="0.1" />
                </div>
              </div>
              <div class="form-grid">
                <div>
                  <label for="new-case-co2">CO₂ offset (tonnes)</label>
                  <input id="new-case-co2" name="case_co2_tonnes" type="number" step="0.1" />
                </div>
                <div>
                  <label for="new-case-payback">Payback (years)</label>
                  <input id="new-case-payback" name="case_payback_years" type="number" step="0.1" />
                </div>
              </div>
              <div>
                <label for="new-case-highlights">Highlights</label>
                <textarea id="new-case-highlights" name="case_highlights" rows="3" placeholder="Use one bullet per line"></textarea>
              </div>
              <div class="form-grid">
                <div>
                  <label for="new-case-image">Image URL</label>
                  <input id="new-case-image" name="case_image" type="url" />
                </div>
                <div>
                  <label for="new-case-image-alt">Image alt text</label>
                  <input id="new-case-image-alt" name="case_image_alt" type="text" />
                </div>
              </div>
              <div>
                <label for="new-case-status">Status</label>
                <select id="new-case-status" name="case_status">
                  <option value="draft">Draft</option>
                  <option value="published" selected>Published</option>
                </select>
              </div>
              <button class="btn-primary" type="submit">Create case study</button>
            </form>
          </aside>
        </div>
      </section>

    <?php elseif ($currentView === 'settings'): ?>
      <section class="panel">
        <h2>Public site configuration</h2>
        <p class="lead">Control the focus statement, contact details, and homepage announcement displayed to prospects.</p>
        <form method="post" autocomplete="off">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>" />
          <input type="hidden" name="action" value="update_site_settings" />
          <input type="hidden" name="redirect_view" value="settings" />
          <div class="form-grid">
            <div>
              <label for="settings-focus">Company focus</label>
              <textarea id="settings-focus" name="company_focus" required><?= htmlspecialchars($siteSettings['company_focus'] ?? ''); ?></textarea>
            </div>
            <div>
              <label for="settings-announcement">Homepage announcement</label>
              <textarea id="settings-announcement" name="announcement" placeholder="Optional update for customers"><?= htmlspecialchars($siteSettings['announcement'] ?? ''); ?></textarea>
            </div>
          </div>
          <div class="form-grid">
            <div>
              <label for="settings-contact">Primary contact</label>
              <input id="settings-contact" name="primary_contact" type="text" value="<?= htmlspecialchars($siteSettings['primary_contact'] ?? ''); ?>" required />
            </div>
            <div>
              <label for="settings-email">Support email</label>
              <input id="settings-email" name="support_email" type="email" value="<?= htmlspecialchars($siteSettings['support_email'] ?? ''); ?>" required />
            </div>
            <div>
              <label for="settings-phone">Support phone</label>
              <input id="settings-phone" name="support_phone" type="text" value="<?= htmlspecialchars($siteSettings['support_phone'] ?? ''); ?>" />
            </div>
          </div>
          <button class="btn-primary" type="submit">Save configuration</button>
        </form>
      </section>
    <?php elseif ($currentView === 'activity'): ?>
      <section class="panel">
        <h2>Audit history</h2>
        <p class="lead">Chronological record of updates made across the admin workspace.</p>
        <?php if (empty($activityLog)): ?>
          <p>No activity recorded yet.</p>
        <?php else: ?>
          <div class="activity-list">
            <?php foreach ($activityLog as $log): ?>
              <div class="activity-item">
                <strong><?= htmlspecialchars($log['event']); ?></strong>
                <small><?= htmlspecialchars($log['actor']); ?> · <?= htmlspecialchars(date('j M Y, g:i A', strtotime($log['timestamp']))); ?></small>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  </main>
  <script>
    (function () {
      function hexToRgb(hex) {
        if (!hex) return [15, 23, 42];
        const value = hex.replace('#', '');
        if (value.length === 3) {
          return [
            parseInt(value[0] + value[0], 16),
            parseInt(value[1] + value[1], 16),
            parseInt(value[2] + value[2], 16)
          ];
        }
        if (value.length === 6) {
          return [
            parseInt(value.slice(0, 2), 16),
            parseInt(value.slice(2, 4), 16),
            parseInt(value.slice(4, 6), 16)
          ];
        }
        return [15, 23, 42];
      }

      function pickContrast(hex) {
        const [r, g, b] = hexToRgb(hex).map((component) => {
          const channel = component / 255;
          return channel <= 0.03928 ? channel / 12.92 : Math.pow((channel + 0.055) / 1.055, 2.4);
        });
        const luminance = 0.2126 * r + 0.7152 * g + 0.0722 * b;
        return luminance > 0.5 ? '#0F172A' : '#FFFFFF';
      }

      document.querySelectorAll('.palette-card').forEach((card) => {
        const input = card.querySelector('input[type="color"]');
        const preview = card.querySelector('.palette-card__preview');
        const valueLabel = preview?.querySelector('span');
        const textLabel = preview?.querySelector('.palette-card__text');
        if (!input || !preview) {
          return;
        }
        input.addEventListener('input', () => {
          const value = input.value;
          const contrast = pickContrast(value);
          preview.style.background = value;
          preview.style.color = contrast;
          if (valueLabel) valueLabel.textContent = value;
          if (textLabel) textLabel.textContent = `Text: ${contrast}`;
        });
      });

      const accentInput = document.getElementById('theme-accent');
      if (accentInput) {
        const helper = accentInput.closest('div')?.querySelector('.form-helper');
        accentInput.addEventListener('input', () => {
          const contrast = pickContrast(accentInput.value);
          if (helper) {
            helper.textContent = `Primary buttons and highlights use this colour. Text auto-adjusts to ${contrast}.`;
          }
        });
      }
    })();
  </script>
</body>
</html>
