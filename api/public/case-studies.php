<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    api_send_error(405, 'Method not allowed');
}

$segmentQuery = isset($_GET['segment']) ? strtolower(trim((string) $_GET['segment'])) : '';

$state = portal_load_state();
$caseStudies = api_get_published_posts($state['case_studies'] ?? []);

if ($segmentQuery !== '') {
    $caseStudies = array_values(array_filter($caseStudies, static function (array $case) use ($segmentQuery): bool {
        return strtolower($case['segment'] ?? '') === $segmentQuery;
    }));
}

usort($caseStudies, static function (array $a, array $b): int {
    $aTime = $a['published_at'] ?? $a['updated_at'] ?? '';
    $bTime = $b['published_at'] ?? $b['updated_at'] ?? '';
    return strcmp($bTime, $aTime);
});

$payload = array_map(static function (array $case): array {
    return [
        'id' => $case['id'] ?? '',
        'title' => $case['title'] ?? '',
        'segment' => $case['segment'] ?? 'residential',
        'location' => $case['location'] ?? '',
        'summary' => $case['summary'] ?? '',
        'capacityKw' => (float) ($case['capacity_kw'] ?? 0),
        'annualGenerationKwh' => (float) ($case['annual_generation_kwh'] ?? 0),
        'co2OffsetTonnes' => (float) ($case['co2_offset_tonnes'] ?? 0),
        'paybackYears' => (float) ($case['payback_years'] ?? 0),
        'highlights' => array_values($case['highlights'] ?? []),
        'image' => [
            'src' => $case['image']['src'] ?? '',
            'alt' => $case['image']['alt'] ?? '',
        ],
        'publishedAt' => $case['published_at'] ?? '',
    ];
}, $caseStudies);

api_send_json(200, ['caseStudies' => $payload]);
