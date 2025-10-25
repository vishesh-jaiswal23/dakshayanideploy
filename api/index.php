<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../server/ai-automation.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'OPTIONS') {
    api_header('Access-Control-Allow-Origin', '*');
    api_header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    api_header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
    http_response_code(204);
    exit;
}

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = preg_replace('#^/api#', '', $uriPath);
$path = '/' . trim($path, '/');

if (strpos($path, '/index.php') === 0) {
    $path = substr($path, strlen('/index.php')) ?: '';
    $path = '/' . trim($path, '/');
}

$routeQuery = trim((string) ($_GET['route'] ?? ''), '/');
if ($routeQuery !== '') {
    $path = '/' . $routeQuery;
}

if ($path === '') {
    $path = '/';
}

if ($path === '/' || $path === '') {
    $user = api_current_user();
    api_send_json(200, [
        'service' => 'Dakshayani Portal PHP API',
        'authenticated' => $user !== null,
        'user' => $user,
    ]);
}

switch (true) {
    case $path === '/public/site-settings' && $method === 'GET':
        $settings = api_read_site_settings();
        api_send_json(200, ['settings' => $settings]);
        break;

    case $path === '/public/site-content' && $method === 'GET':
        api_send_json(200, api_collect_site_content());
        break;

    case $path === '/public/customer-template' && $method === 'GET':
        $segment = api_trim_string($_GET['segment'] ?? 'potential');
        if ($segment === '') {
            $segment = 'potential';
        }
        $format = strtolower(api_trim_string($_GET['format'] ?? 'excel'));
        $state = api_load_state();
        $segments = $state['customer_registry']['segments'] ?? [];
        if (!isset($segments[$segment]) || !is_array($segments[$segment])) {
            $defaults = portal_default_state()['customer_registry']['segments'];
            $segmentData = $defaults[$segment] ?? reset($defaults);
            $segment = array_key_first($defaults) ?? 'potential';
        } else {
            $segmentData = $segments[$segment];
        }
        $columns = is_array($segmentData['columns'] ?? null) ? $segmentData['columns'] : portal_default_state()['customer_registry']['segments']['potential']['columns'];
        $headers = [];
        foreach ($columns as $column) {
            if (!is_array($column)) {
                continue;
            }
            $headers[] = $column['label'] ?? ($column['key'] ?? 'Column');
        }
        $headers[] = 'Notes';
        $headers[] = 'Reminder on (YYYY-MM-DD)';
        $fileSegment = trim(preg_replace('/[^a-z0-9\-]+/i', '-', $segment) ?? 'customers', '-');
        if ($fileSegment === '') {
            $fileSegment = 'customers';
        }
        if ($format === 'csv') {
            api_header('Content-Type', 'text/csv; charset=utf-8');
            api_header('Content-Disposition', 'attachment; filename="dakshayani-' . $fileSegment . '-template.csv"');
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                api_send_error(500, 'Unable to initialise download stream.');
            }
            fputcsv($handle, $headers, ',', "\"", "\\");
            fclose($handle);
            exit;
        }
        $worksheetName = ucwords(str_replace('-', ' ', $segment));
        api_header('Content-Type', 'application/vnd.ms-excel; charset=utf-8');
        api_header('Content-Disposition', 'attachment; filename="dakshayani-' . $fileSegment . '-template.xls"');
        echo '<?xml version="1.0" encoding="UTF-8"?>
';
        echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
        echo '<Worksheet ss:Name="' . htmlspecialchars($worksheetName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
        echo '<Table>';
        echo '<Row>';
        foreach ($headers as $headerLabel) {
            echo '<Cell><Data ss:Type="String">' . htmlspecialchars($headerLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</Data></Cell>';
        }
        echo '</Row>';
        echo '<Row>';
        foreach ($headers as $headerLabel) {
            echo '<Cell><Data ss:Type="String">' . htmlspecialchars('Enter ' . strtolower($headerLabel), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</Data></Cell>';
        }
        echo '</Row>';
        echo '</Table>';
        echo '</Worksheet>';
        echo '</Workbook>';
        exit;

    case $path === '/public/customer-lookup' && $method === 'GET':
        $phoneQuery = api_normalise_phone($_GET['phone'] ?? $_GET['mobile'] ?? '');
        if ($phoneQuery === '') {
            api_send_error(400, 'Provide a valid phone number to search for customer records.');
        }

        $matches = api_lookup_customer_by_phone($phoneQuery);
        if ($matches === []) {
            api_send_error(404, 'No customer records found for this phone number.');
        }

        api_send_json(200, [
            'found' => true,
            'customer' => $matches[0],
            'matches' => $matches,
        ]);
        break;

    case $path === '/public/complaints' && $method === 'POST':
        $body = api_read_json_body();
        $name = api_trim_string($body['name'] ?? $body['customer_name'] ?? '');
        $phone = api_normalise_phone($body['phone'] ?? $body['mobile'] ?? '');

        if ($name === '' || $phone === '') {
            api_send_error(400, 'Customer name and phone number are required.');
        }

        $email = api_normalise_email($body['email'] ?? $body['customer_email'] ?? '');
        $installType = strtolower(api_trim_string($body['installType'] ?? $body['install_type'] ?? ''));
        if (!in_array($installType, ['pmsgby', 'private', 'government'], true)) {
            $installType = $installType !== '' ? $installType : 'private';
        }

        $applicationNumber = api_trim_string($body['applicationNumber'] ?? $body['application_number'] ?? '');
        $siteAddress = api_trim_string($body['siteAddress'] ?? $body['site_address'] ?? '');
        $systemSize = api_trim_string($body['systemSize'] ?? $body['system_size'] ?? '');
        $preferredContact = api_trim_string($body['preferredContact'] ?? $body['preferred_contact'] ?? '');

        $schemeType = strtolower(api_trim_string($body['schemeType'] ?? $body['scheme_type'] ?? ''));
        if ($schemeType === '') {
            $schemeType = $installType === 'pmsgby' ? 'pmsgby' : 'other';
        }
        if (!in_array($schemeType, ['pmsgby', 'other'], true)) {
            $schemeType = $installType === 'pmsgby' ? 'pmsgby' : 'other';
        }
        $schemeLabel = api_trim_string($body['schemeLabel'] ?? $body['scheme_label'] ?? '');
        if ($schemeLabel === '') {
            $schemeLabel = $schemeType === 'pmsgby' ? 'PM Surya Ghar Muft Bijli Yojana' : 'Other installation';
        }

        $systemConfigurationInput = $body['systemConfiguration'] ?? $body['system_configuration'] ?? '';
        $systemConfiguration = strtolower(api_trim_string((string) $systemConfigurationInput));
        $validConfigurations = ['ongrid', 'hybrid', 'offgrid'];
        if (!in_array($systemConfiguration, $validConfigurations, true)) {
            $systemConfiguration = api_normalise_system_configuration($systemConfiguration);
        }
        $systemConfigurationLabel = api_trim_string($body['systemConfigurationLabel'] ?? $body['system_configuration_label'] ?? '');
        if ($systemConfigurationLabel === '') {
            $systemConfigurationLabel = api_system_configuration_label($systemConfiguration);
        }

        $description = api_trim_string($body['description'] ?? $body['issue'] ?? $body['details'] ?? '');
        $issueLabels = $body['issueLabels'] ?? $body['issue_labels'] ?? [];
        $issueValues = $body['issues'] ?? $body['issue_types'] ?? [];

        if (!is_array($issueValues)) {
            $issueValues = [];
        }
        if (!is_array($issueLabels)) {
            $issueLabels = [];
        }

        $issues = array_values(array_filter(array_map(static fn($value) => api_trim_string((string) $value), $issueValues), static fn($value) => $value !== ''));
        $issueLabelList = array_values(array_filter(array_map(static fn($value) => api_trim_string((string) $value), $issueLabels), static fn($value) => $value !== ''));

        if ($issues === [] && $description === '') {
            api_send_error(400, 'Select at least one affected component or describe the issue.');
        }

        $priority = 'medium';
        foreach ($issues as $issue) {
            if (in_array($issue, ['net-metering', 'inverter', 'battery'], true)) {
                $priority = 'high';
                break;
            }
        }
        if ($priority !== 'high' && stripos($description, 'not working') !== false) {
            $priority = 'high';
        }

        $subjectParts = [];
        if ($issueLabelList !== []) {
            $subjectParts[] = implode(', ', array_slice($issueLabelList, 0, 2));
        }
        if ($systemSize !== '') {
            $subjectParts[] = $systemSize;
        }

        $subject = $subjectParts !== []
            ? 'Service issue: ' . implode(' • ', $subjectParts)
            : 'Website service complaint';

        $tickets = api_read_tickets();
        try {
            $ticketId = 'tkt-' . bin2hex(random_bytes(6));
        } catch (Exception $exception) {
            $ticketId = 'tkt-' . uniqid();
        }

        $timestamp = date('c');
        $timelineLines = [];
        if ($issueLabelList !== []) {
            $timelineLines[] = 'Issues: ' . implode(', ', $issueLabelList);
        }
        if ($description !== '') {
            $timelineLines[] = $description;
        }
        if ($schemeLabel !== '') {
            $timelineLines[] = 'Scheme: ' . $schemeLabel;
        }
        if ($systemConfigurationLabel !== '') {
            $timelineLines[] = 'Configuration: ' . $systemConfigurationLabel;
        }

        $ticket = [
            'id' => $ticketId,
            'subject' => $subject,
            'description' => $description !== ''
                ? $description
                : ($issueLabelList !== [] ? 'Reported issues: ' . implode(', ', $issueLabelList) : 'Service complaint logged via website form.'),
            'priority' => $priority,
            'status' => 'open',
            'requesterId' => null,
            'requesterName' => $name,
            'requesterRole' => 'customer',
            'requesterPhone' => $phone,
            'requesterEmail' => $email,
            'channel' => api_trim_string($body['channel'] ?? 'web') ?: 'web',
            'source' => 'website',
            'tags' => $issues,
            'issueLabels' => $issueLabelList,
            'createdAt' => $timestamp,
            'updatedAt' => $timestamp,
            'timeline' => [
                [
                    'type' => 'created',
                    'actor' => $name,
                    'actorId' => null,
                    'at' => $timestamp,
                    'message' => $timelineLines !== [] ? implode("\n\n", $timelineLines) : 'Complaint recorded via website.',
                ],
            ],
            'meta' => [
                'installType' => $installType,
                'installLabel' => api_trim_string($body['installLabel'] ?? $body['install_label'] ?? ''),
                'applicationNumber' => $applicationNumber,
                'siteAddress' => $siteAddress,
                'systemSize' => $systemSize,
                'preferredContact' => $preferredContact,
                'phoneRaw' => (string) ($body['phone'] ?? $body['mobile'] ?? ''),
                'loggedAt' => $body['loggedAt'] ?? $timestamp,
                'schemeType' => $schemeType,
                'schemeLabel' => $schemeLabel,
                'systemConfiguration' => $systemConfiguration,
                'systemConfigurationLabel' => $systemConfigurationLabel,
                'systemConfigurationRaw' => api_trim_string((string) $systemConfigurationInput),
            ],
        ];

        $tickets[] = $ticket;
        api_write_tickets($tickets);

        api_upsert_customer_record([
            'phone' => $phone,
            'rawPhone' => $body['phone'] ?? $body['mobile'] ?? '',
            'name' => $name,
            'email' => $email,
            'siteAddress' => $siteAddress,
            'systemSize' => $systemSize,
            'installType' => $installType,
            'preferredContact' => $preferredContact,
            'applicationNumber' => $applicationNumber,
            'schemeType' => $schemeType,
            'schemeLabel' => $schemeLabel,
            'systemConfiguration' => $systemConfiguration,
            'systemConfigurationLabel' => $systemConfigurationLabel,
            'systemConfigurationRaw' => $systemConfigurationRaw,
            'issues' => $issues,
            'notes' => $description,
            'lastTicketId' => $ticketId,
            'segment' => 'service',
            'segmentLabel' => 'Service complaints',
        ]);

        api_send_json(201, ['ticket' => $ticket]);
        break;

    case $path === '/public/search' && $method === 'GET':
        $query = strtolower(api_trim_string($_GET['q'] ?? ''));
        $segment = strtolower(api_trim_string($_GET['segment'] ?? ''));
        $limit = (int) ($_GET['limit'] ?? 25);
        if ($limit <= 0) {
            $limit = 25;
        }
        $limit = min($limit, 50);

        $results = [];
        foreach (api_read_search_index() as $item) {
            if (!is_array($item)) {
                continue;
            }
            $haystack = strtolower(implode(' ', [
                $item['title'] ?? '',
                $item['excerpt'] ?? '',
                implode(' ', $item['tags'] ?? []),
            ]));
            if ($query !== '' && strpos($haystack, $query) === false) {
                continue;
            }
            if ($segment !== '' && !in_array($segment, array_map('strtolower', $item['tags'] ?? []), true)) {
                continue;
            }
            $results[] = $item;
            if (count($results) >= $limit) {
                break;
            }
        }

        api_send_json(200, ['results' => $results, 'total' => count($results), 'query' => $query]);
        break;

    case $path === '/public/knowledge' && $method === 'GET':
        api_send_json(200, api_read_knowledge());
        break;

    case $path === '/public/testimonials' && $method === 'GET':
        $testimonials = array_values(array_filter(api_read_testimonials(), static function ($testimonial): bool {
            return is_array($testimonial) && ($testimonial['status'] ?? 'published') === 'published';
        }));
        api_send_json(200, ['testimonials' => $testimonials]);
        break;

    case $path === '/public/solar-advisory' && $method === 'POST':
        $body = api_read_json_body();

        $size = (float) ($body['size'] ?? $body['systemSize'] ?? 0);
        if ($size <= 0) {
            api_send_error(400, 'Provide a valid solar system size in kWp.');
        }

        $systemType = strtolower(api_trim_string($body['systemType'] ?? $body['type'] ?? 'residential'));
        if (!in_array($systemType, ['residential', 'commercial', 'industrial'], true)) {
            $systemType = 'residential';
        }

        $grossCost = (float) ($body['grossCost'] ?? $body['projectCost'] ?? 0);
        $netCost = (float) ($body['netCost'] ?? $body['investment'] ?? 0);
        $subsidy = (float) ($body['subsidy'] ?? ($grossCost > 0 && $netCost > 0 ? $grossCost - $netCost : 0));
        $annualSavings = (float) ($body['annualSavings'] ?? $body['savings'] ?? 0);
        $monthlyConsumption = (float) ($body['monthlyConsumption'] ?? 0);
        $unitRate = (float) ($body['unitRate'] ?? 0);

        if ($grossCost <= 0 && $netCost > 0 && $subsidy > 0) {
            $grossCost = $netCost + $subsidy;
        }

        if ($netCost <= 0 && $grossCost > 0) {
            $netCost = max(0.0, $grossCost - max(0.0, $subsidy));
        }

        if ($annualSavings <= 0 && $monthlyConsumption > 0 && $unitRate > 0) {
            $annualSavings = $monthlyConsumption * $unitRate * 12;
        }

        if ($annualSavings <= 0) {
            api_send_error(400, 'Provide the estimated annual savings for the system.');
        }

        $unitsPerYear = (int) round($size * 150 * 12);
        if ($unitsPerYear < 0) {
            $unitsPerYear = 0;
        }

        $paybackYears = null;
        if ($netCost > 0 && $annualSavings > 0) {
            $paybackYears = round($netCost / $annualSavings, 1);
            if ($paybackYears < 0) {
                $paybackYears = null;
            }
        }

        $formatCurrency = static function (float $value): string {
            return number_format((float) round($value));
        };

        $systemLabels = [
            'residential' => 'PM Surya Ghar rooftop system',
            'commercial' => 'commercial solar plant',
            'industrial' => 'industrial solar installation',
        ];
        $systemLabel = $systemLabels[$systemType] ?? 'solar system';

        $investmentLabel = $netCost > 0
            ? '₹' . $formatCurrency($netCost) . ' net investment'
            : 'minimal upfront investment';

        $subsidyClause = $subsidy > 0
            ? ' after factoring an estimated subsidy of ₹' . $formatCurrency($subsidy)
            : '';

        $paybackSentence = $paybackYears !== null
            ? 'Expect payback in roughly ' . $paybackYears . ' years, keeping your cash flow comfortable.'
            : 'Savings start immediately because the subsidy or financing covers most of the spend.';

        $advisory = sprintf(
            'Plan for a %.1f kWp %s to offset about %s units a year. With %s%s you unlock annual savings close to ₹%s. %s Let’s schedule a site survey this week so our engineers can finalise mounting design and subsidy paperwork.',
            $size,
            $systemLabel,
            $unitsPerYear > 0 ? number_format($unitsPerYear) : 'significant',
            $investmentLabel,
            $subsidyClause,
            $formatCurrency($annualSavings),
            $paybackSentence
        );

        $financialPoints = [];
        $financialPoints[] = sprintf('System size: %.1f kWp (%s)', $size, $systemLabel);
        if ($grossCost > 0) {
            $financialPoints[] = sprintf(
                'Project cost: approx. ₹%s%s',
                $formatCurrency($grossCost),
                $subsidy > 0 ? ' with subsidy eligibility near ₹' . $formatCurrency($subsidy) : ''
            );
        } else {
            $financialPoints[] = 'Project cost: Covered via subsidy/financing';
        }
        $financialPoints[] = sprintf(
            'Net investment: %s%s',
            $netCost > 0 ? '₹' . $formatCurrency($netCost) : 'Covered via subsidy',
            $paybackYears !== null ? ' · Payback ~' . $paybackYears . ' years' : ''
        );
        $financialPoints[] = sprintf(
            'Annual savings: about ₹%s · Book a survey to lock paperwork.',
            $formatCurrency($annualSavings)
        );

        $aiMeta = [
            'provider' => 'gemini',
            'model' => '',
            'version' => '',
            'status' => 'skipped',
        ];
        $followUp = '';

        try {
            $state = api_load_state();
            gemini_set_portal_state($state);

            $client = new GeminiClient();
            $resolved = $client->describeModel('calculator_advisory');

            $promptPayload = [
                'company' => 'Dakshayani Enterprises',
                'region' => 'Jharkhand, India',
                'systemType' => $systemType,
                'systemLabel' => $systemLabel,
                'sizeKw' => $size,
                'grossCost' => $grossCost,
                'netCost' => $netCost,
                'subsidy' => $subsidy,
                'annualSavings' => $annualSavings,
                'monthlyConsumption' => $monthlyConsumption,
                'unitRate' => $unitRate,
                'paybackYears' => $paybackYears,
                'fallbackAdvisory' => $advisory,
                'fallbackFinancialPoints' => $financialPoints,
            ];

            $systemInstruction = 'You are an experienced solar finance advisor for Dakshayani Enterprises in Jharkhand, India. Use the provided project metrics to craft guidance that is immediately useful for the customer. Return JSON with keys: advisory (string), financialPoints (array of 3-5 concise strings), followUp (string call-to-action). Reference Indian currency, policies, and the Dakshayani team. If data is incomplete, rely on the fallback fields.';

            $responseSchema = [
                'type' => 'object',
                'required' => ['advisory'],
                'properties' => [
                    'advisory' => ['type' => 'string'],
                    'financialPoints' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                    'followUp' => ['type' => 'string'],
                ],
                'additionalProperties' => true,
            ];

            $aiResponse = $client->generateJson(
                [[
                    'role' => 'user',
                    'parts' => [[
                        'text' => json_encode($promptPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                    ]],
                ]],
                $systemInstruction,
                [
                    'temperature' => 0.35,
                    'maxOutputTokens' => 640,
                ],
                'calculator_advisory',
                $responseSchema
            );

            $aiAdvisory = isset($aiResponse['advisory']) ? trim((string) $aiResponse['advisory']) : '';
            if ($aiAdvisory !== '') {
                $advisory = $aiAdvisory;
            }

            if (isset($aiResponse['financialPoints']) && is_array($aiResponse['financialPoints'])) {
                $points = array_values(array_filter(array_map(static function ($point) {
                    return is_string($point) ? trim($point) : '';
                }, $aiResponse['financialPoints']), static function ($point) {
                    return $point !== '';
                }));

                if ($points !== []) {
                    $financialPoints = $points;
                }
            }

            $followUpCandidate = isset($aiResponse['followUp']) ? trim((string) $aiResponse['followUp']) : '';
            if ($followUpCandidate !== '') {
                $followUp = $followUpCandidate;
            }

            $aiMeta = [
                'provider' => 'gemini',
                'model' => $resolved['model'],
                'version' => $resolved['version'],
                'status' => 'success',
            ];
        } catch (RuntimeException $exception) {
            $aiMeta['status'] = 'error';
            $aiMeta['message'] = $exception->getMessage();
        } finally {
            gemini_set_portal_state(null);
        }

        api_send_json(200, [
            'advisory' => $advisory,
            'financial' => [
                'language' => 'English',
                'points' => $financialPoints,
            ],
            'ai' => $aiMeta,
            'followUp' => $followUp,
        ]);
        break;

    case $path === '/public/viaan-chat' && $method === 'POST':
        $body = api_read_json_body();

        $message = api_trim_string($body['message'] ?? '');
        if ($message === '') {
            api_send_error(400, 'Ask a question for Viaan.');
        }

        $historyEntries = [];
        if (isset($body['history']) && is_array($body['history'])) {
            foreach ($body['history'] as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $text = api_trim_string($entry['text'] ?? ($entry['message'] ?? ''));
                if ($text === '') {
                    continue;
                }

                $role = strtolower(api_trim_string($entry['role'] ?? 'user'));

                $historyEntries[] = [
                    'role' => $role === 'model' ? 'model' : 'user',
                    'parts' => [['text' => $text]],
                ];
            }
        }

        if ($historyEntries !== []) {
            $historyEntries = array_slice($historyEntries, -10);
        }

        $messages = $historyEntries;
        $messages[] = [
            'role' => 'user',
            'parts' => [['text' => $message]],
        ];

        $systemInstruction = "You are Viaan, Dakshayani Enterprises' virtual solar expert. Only answer questions about Dakshayani Enterprises, our solar solutions, policies, financing, RESCO services, Meera GH2, EV charging, and other official ventures. If a query falls outside this scope, politely refuse and guide the user back to solar or Dakshayani topics. Keep answers concise, factual, and grounded in Jharkhand-specific context when possible.";

        $generationConfig = [
            'temperature' => 0.6,
            'topP' => 0.9,
            'maxOutputTokens' => 600,
        ];

        $responseSchema = [
            'type' => 'object',
            'required' => ['answer'],
            'properties' => [
                'answer' => ['type' => 'string'],
                'followUp' => ['type' => 'string'],
            ],
            'additionalProperties' => true,
        ];

        $state = api_load_state();
        gemini_set_portal_state($state);

        try {
            $client = new GeminiClient();
            $resolved = $client->describeModel('viaan_chat');

            $aiResponse = $client->generateJson(
                $messages,
                $systemInstruction,
                $generationConfig,
                'viaan_chat',
                $responseSchema
            );

            $answer = api_trim_string($aiResponse['answer'] ?? '');
            if ($answer === '') {
                throw new RuntimeException('Viaan returned an empty response.');
            }

            $followUp = api_trim_string($aiResponse['followUp'] ?? '');

            gemini_set_portal_state(null);

            api_send_json(200, [
                'answer' => $answer,
                'followUp' => $followUp,
                'model' => $resolved['model'],
                'version' => $resolved['version'],
            ]);
        } catch (RuntimeException $exception) {
            gemini_set_portal_state(null);
            api_send_error(500, $exception->getMessage());
        }

        break;

    case $path === '/public/ai/news' && $method === 'GET':
        $state = api_load_state();
        $automation = isset($state['ai_automation']['news_digest']) && is_array($state['ai_automation']['news_digest'])
            ? $state['ai_automation']['news_digest']
            : [];

        $historyEntries = [];
        if (isset($automation['history']) && is_array($automation['history'])) {
            $historyEntries = $automation['history'];
        }

        $latest = null;
        if (isset($automation['last_digest']) && is_array($automation['last_digest']) && !empty($automation['last_digest']['items'])) {
            $latest = $automation['last_digest'];
        } else {
            foreach ($historyEntries as $entry) {
                if (is_array($entry) && !empty($entry['items'])) {
                    $latest = $entry;
                    break;
                }
            }
        }

        $normalizeDigest = static function ($entry) {
            if (!is_array($entry)) {
                return null;
            }

            $items = [];
            foreach ($entry['items'] ?? [] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $headline = trim((string) ($item['headline'] ?? ''));
                if ($headline === '') {
                    continue;
                }

                $items[] = [
                    'headline' => $headline,
                    'region' => trim((string) ($item['region'] ?? '')),
                    'summary' => trim((string) ($item['summary'] ?? '')),
                    'recommendedAction' => trim((string) ($item['recommendedAction'] ?? '')),
                    'sourceHints' => array_values(array_filter(array_map(static function ($hint) {
                        if (is_array($hint)) {
                            $label = trim((string) ($hint['label'] ?? ''));
                            $url = trim((string) ($hint['url'] ?? ''));
                            if ($label === '' && $url === '') {
                                return null;
                            }

                            return ['label' => $label, 'url' => $url];
                        }

                        $text = trim((string) $hint);
                        return $text === '' ? null : ['label' => $text, 'url' => ''];
                    }, $item['sourceHints'] ?? []))),
                ];
            }

            if ($items === []) {
                return null;
            }

            $generatedAt = isset($entry['generated_at']) ? (string) $entry['generated_at'] : '';
            $timestamp = portal_parse_datetime($generatedAt);
            $timezone = trim((string) ($entry['timezone'] ?? 'Asia/Kolkata'));

            return [
                'id' => (string) ($entry['id'] ?? ''),
                'summary' => trim((string) ($entry['summary'] ?? '')),
                'generatedAt' => $generatedAt,
                'generatedAtEpoch' => $timestamp,
                'generatedAtLabel' => portal_format_datetime($timestamp),
                'timezone' => $timezone !== '' ? $timezone : 'Asia/Kolkata',
                'items' => $items,
            ];
        };

        $latestNormalized = $normalizeDigest($latest);

        $historyPayload = [];
        foreach ($historyEntries as $entry) {
            $normalized = $normalizeDigest($entry);
            if ($normalized === null) {
                continue;
            }

            if ($latestNormalized !== null && $normalized['id'] !== '' && $normalized['id'] === $latestNormalized['id']) {
                continue;
            }

            $historyPayload[] = $normalized;

            if (count($historyPayload) >= 6) {
                break;
            }
        }

        api_send_json(200, [
            'latest' => $latestNormalized,
            'history' => $historyPayload,
        ]);
        break;

    case $path === '/public/case-studies' && $method === 'GET':
        $segment = strtolower(api_trim_string($_GET['segment'] ?? ''));
        $caseStudies = array_values(array_filter(api_read_case_studies(), static function ($case) use ($segment): bool {
            if (!is_array($case)) {
                return false;
            }
            if ($segment === '') {
                return true;
            }
            return strtolower((string) ($case['segment'] ?? '')) === $segment;
        }));
        api_send_json(200, ['caseStudies' => $caseStudies]);
        break;

    case $path === '/public/blog-posts' && $method === 'GET':
        $posts = array_filter(api_read_blog_posts(), static function (array $post): bool {
            return ($post['status'] ?? 'draft') === 'published';
        });

        usort($posts, static function (array $a, array $b): int {
            return strcmp($b['publishedAt'] ?? $b['updatedAt'] ?? '', $a['publishedAt'] ?? $a['updatedAt'] ?? '');
        });

        $slugQuery = api_trim_string($_GET['slug'] ?? '');
        if ($slugQuery !== '') {
            foreach ($posts as $post) {
                if ($post['slug'] === $slugQuery) {
                    api_send_json(200, ['post' => $post]);
                }
            }
            api_send_error(404, 'Post not found.');
        }

        api_send_json(200, ['posts' => array_values($posts)]);
        break;

    case preg_match('#^/public/blog-posts/([A-Za-z0-9\-]+)$#', $path, $matches) && $method === 'GET':
        $slug = $matches[1];
        foreach (api_read_blog_posts() as $post) {
            if (($post['slug'] ?? '') === $slug && ($post['status'] ?? 'draft') === 'published') {
                api_send_json(200, ['post' => $post]);
            }
        }
        api_send_error(404, 'Post not found.');
        break;

    case $path === '/solar/estimate' && $method === 'POST':
        $body = api_read_json_body();
        $latitude = $body['latitude'] ?? null;
        $longitude = $body['longitude'] ?? null;
        $address = api_trim_string($body['address'] ?? '');
        if (($latitude === null || $longitude === null) && $address === '') {
            api_send_error(400, 'Provide latitude/longitude coordinates or a formatted address.');
        }

        $recaptcha = api_verify_recaptcha($body['recaptchaToken'] ?? $body['recaptcha_token'] ?? null);
        if (($recaptcha['skipped'] ?? false) === false && ($recaptcha['success'] ?? false) === false) {
            api_send_error(400, 'reCAPTCHA validation failed. Please try again.');
        }

        $result = api_fetch_solar_estimate([
            'latitude' => $latitude,
            'longitude' => $longitude,
            'address' => $address,
            'systemCapacityKw' => $body['systemCapacityKw'] ?? $body['system_capacity_kw'] ?? null,
        ]);

        api_send_json(200, $result);
        break;

    case $path === '/support/tickets':
        $actor = api_require_login();
        $tickets = api_read_tickets();

        if ($method === 'GET') {
            $filtered = array_values(array_filter($tickets, static function ($ticket) use ($actor): bool {
                if (!is_array($ticket)) {
                    return false;
                }
                if (in_array($actor['role'] ?? '', ['admin', 'employee'], true)) {
                    return true;
                }
                $id = $actor['id'] ?? '';
                return ($ticket['requesterId'] ?? '') === $id || ($ticket['assigneeId'] ?? '') === $id;
            }));

            api_send_json(200, ['tickets' => $filtered]);
        }

        if ($method === 'POST') {
            $body = api_read_json_body();
            $subject = api_trim_string($body['subject'] ?? '');
            $description = api_trim_string($body['description'] ?? '');
            $priority = strtolower(api_trim_string($body['priority'] ?? 'medium'));

            if ($subject === '' || $description === '') {
                api_send_error(400, 'Subject and description are required.');
            }

            if (!in_array($priority, ['low', 'medium', 'high'], true)) {
                $priority = 'medium';
            }

            $ticket = [
                'id' => 'tkt-' . bin2hex(random_bytes(6)),
                'subject' => $subject,
                'description' => $description,
                'priority' => $priority,
                'status' => 'open',
                'requesterId' => $actor['id'] ?? null,
                'requesterName' => $actor['name'] ?? 'Portal user',
                'requesterRole' => $actor['role'] ?? 'user',
                'assigneeId' => $body['assigneeId'] ?? null,
                'channel' => $body['channel'] ?? 'portal',
                'tags' => $body['tags'] ?? [],
                'createdAt' => date('c'),
                'updatedAt' => date('c'),
                'timeline' => [
                    [
                        'type' => 'created',
                        'actor' => $actor['name'] ?? 'Portal user',
                        'actorId' => $actor['id'] ?? null,
                        'at' => date('c'),
                        'message' => $description,
                    ],
                ],
            ];

            $tickets[] = $ticket;
            api_write_tickets($tickets);

            api_send_json(201, ['ticket' => $ticket]);
        }

        api_method_not_allowed(['GET', 'POST']);
        break;

    case $path === '/leads/whatsapp' && $method === 'POST':
        $body = api_read_json_body();
        api_send_whatsapp_lead($body);
        api_send_json(200, ['sent' => true]);
        break;

    case $path === '/admin/users':
        $actor = api_require_admin();
        $users = api_read_users();

        if ($method === 'GET') {
            api_send_json(200, [
                'users' => api_prepare_users($users),
                'stats' => api_compute_user_stats($users),
                'refreshedAt' => date('c'),
            ]);
        }

        if ($method === 'POST') {
            $body = api_read_json_body();
            $name = api_trim_string($body['name'] ?? '');
            $email = api_normalise_email($body['email'] ?? '');
            $password = (string) ($body['password'] ?? '');
            $role = api_trim_string($body['role'] ?? 'referrer');
            $status = api_trim_string($body['status'] ?? 'active');
            $phone = api_trim_string($body['phone'] ?? '');
            $city = api_trim_string($body['city'] ?? '');

            if ($name === '' || $email === '') {
                api_send_error(400, 'Name and email are required.');
            }
            if (!api_is_valid_password($password)) {
                api_send_error(400, 'Password must be at least 8 characters long with mixed characters.');
            }
            foreach ($users as $existing) {
                if (api_normalise_email($existing['email'] ?? '') === $email) {
                    api_send_error(409, 'An account with this email already exists.');
                }
            }

            $timestamp = date('c');
            $user = [
                'id' => 'usr-' . bin2hex(random_bytes(6)),
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'city' => $city,
                'role' => $role,
                'status' => $status,
                'password_hash' => api_hash_password($password),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
                'created_by' => $actor['id'] ?? null,
            ];

            $users[] = $user;
            api_write_users($users);

            api_send_json(201, [
                'user' => api_sanitise_user($user),
                'stats' => api_compute_user_stats($users),
            ]);
        }

        api_method_not_allowed(['GET', 'POST']);
        break;

    case preg_match('#^/admin/users/([^/]+)$#', $path, $matches):
        $actor = api_require_admin();
        $userId = urldecode($matches[1]);
        $users = api_read_users();
        $index = api_find_user_index($users, $userId);

        if ($index === -1) {
            api_send_error(404, 'User not found.');
        }

        if ($method === 'GET') {
            api_send_json(200, ['user' => api_sanitise_user($users[$index])]);
        }

        if ($method === 'PUT' || $method === 'PATCH') {
            $body = api_read_json_body();
            $nextName = api_trim_string($body['name'] ?? $users[$index]['name'] ?? '');
            $nextPhone = api_trim_string($body['phone'] ?? $users[$index]['phone'] ?? '');
            $nextCity = api_trim_string($body['city'] ?? $users[$index]['city'] ?? '');
            $nextRole = api_trim_string($body['role'] ?? $users[$index]['role'] ?? 'referrer');
            $nextStatus = api_trim_string($body['status'] ?? $users[$index]['status'] ?? 'active');

            if (($actor['id'] ?? '') === $users[$index]['id'] && $nextStatus !== 'active') {
                api_send_error(400, 'You cannot suspend your own admin account.');
            }

            if ($users[$index]['role'] === 'admin' && $users[$index]['status'] === 'active' &&
                ($nextRole !== 'admin' || $nextStatus !== 'active') && api_count_active_admins($users) <= 1) {
                api_send_error(400, 'At least one active admin account must remain.');
            }

            $users[$index]['name'] = $nextName;
            $users[$index]['phone'] = $nextPhone;
            $users[$index]['city'] = $nextCity;
            $users[$index]['role'] = $nextRole;
            $users[$index]['status'] = $nextStatus;
            $users[$index]['updated_at'] = date('c');
            $users[$index]['updated_by'] = $actor['id'] ?? null;

            api_write_users($users);
            api_send_json(200, [
                'user' => api_sanitise_user($users[$index]),
                'stats' => api_compute_user_stats($users),
            ]);
        }

        if ($method === 'DELETE') {
            if (($actor['id'] ?? '') === $users[$index]['id']) {
                api_send_error(400, 'You cannot delete your own account.');
            }
            if ($users[$index]['role'] === 'admin' && $users[$index]['status'] === 'active' && api_count_active_admins($users) <= 1) {
                api_send_error(400, 'At least one active admin account must remain.');
            }

            array_splice($users, $index, 1);
            api_write_users($users);
            api_send_json(200, ['stats' => api_compute_user_stats($users)]);
        }

        api_method_not_allowed(['GET', 'PUT', 'PATCH', 'DELETE']);
        break;

    case preg_match('#^/admin/users/([^/]+)/reset-password$#', $path, $matches) && $method === 'POST':
        $actor = api_require_admin();
        $userId = urldecode($matches[1]);
        $users = api_read_users();
        $index = api_find_user_index($users, $userId);
        if ($index === -1) {
            api_send_error(404, 'User not found.');
        }
        $body = api_read_json_body();
        $password = (string) ($body['password'] ?? '');
        if (!api_is_valid_password($password)) {
            api_send_error(400, 'Password must be at least 8 characters long with mixed characters.');
        }

        $users[$index]['password_hash'] = api_hash_password($password);
        $users[$index]['updated_at'] = date('c');
        $users[$index]['password_changed_at'] = $users[$index]['updated_at'];
        $users[$index]['updated_by'] = $actor['id'] ?? null;
        api_write_users($users);
        api_send_json(200, ['user' => api_sanitise_user($users[$index])]);
        break;

    case $path === '/blog/posts':
        $actor = api_require_admin();
        $posts = api_read_blog_posts();

        if ($method === 'GET') {
            api_send_json(200, ['posts' => $posts]);
        }

        if ($method === 'POST') {
            $body = api_read_json_body();
            $title = api_trim_string($body['title'] ?? '');
            $content = (string) ($body['content'] ?? '');
            if ($title === '' || $content === '') {
                api_send_error(400, 'Title and content are required.');
            }

            $slug = api_slugify($body['slug'] ?? $title);
            $existingSlugs = array_column($posts, 'slug');
            $candidate = $slug;
            $suffix = 2;
            while (in_array($candidate, $existingSlugs, true)) {
                $candidate = $slug . '-' . $suffix++;
            }
            $slug = $candidate;

            $timestamp = date('c');
            $post = [
                'id' => 'post-' . bin2hex(random_bytes(6)),
                'title' => $title,
                'slug' => $slug,
                'excerpt' => api_trim_string($body['excerpt'] ?? ''),
                'heroImage' => api_trim_string($body['heroImage'] ?? ''),
                'content' => $content,
                'tags' => isset($body['tags']) && is_array($body['tags']) ? $body['tags'] : [],
                'readTimeMinutes' => isset($body['readTimeMinutes']) ? (int) $body['readTimeMinutes'] : null,
                'status' => in_array($body['status'] ?? 'draft', ['draft', 'published'], true) ? $body['status'] : 'draft',
                'author' => [
                    'name' => $actor['name'] ?? 'Portal Admin',
                    'role' => 'Admin',
                ],
                'publishedAt' => ($body['status'] ?? 'draft') === 'published' ? $timestamp : null,
                'updatedAt' => $timestamp,
            ];

            $posts[] = $post;
            api_write_blog_posts($posts);
            api_send_json(201, ['post' => $post]);
        }

        api_method_not_allowed(['GET', 'POST']);
        break;

    case preg_match('#^/blog/posts/([^/]+)$#', $path, $matches):
        $actor = api_require_admin();
        $postId = urldecode($matches[1]);
        $posts = api_read_blog_posts();
        $index = -1;
        foreach ($posts as $i => $post) {
            if (($post['id'] ?? '') === $postId) {
                $index = $i;
                break;
            }
        }

        if ($index === -1) {
            api_send_error(404, 'Blog post not found.');
        }

        if ($method === 'GET') {
            api_send_json(200, ['post' => $posts[$index]]);
        }

        if ($method === 'PUT' || $method === 'PATCH') {
            $body = api_read_json_body();
            $post = $posts[$index];
            $title = api_trim_string($body['title'] ?? $post['title']);
            $content = (string) ($body['content'] ?? $post['content']);
            if ($title === '' || $content === '') {
                api_send_error(400, 'Title and content are required.');
            }

            $slug = api_trim_string($body['slug'] ?? $post['slug']);
            if ($slug === '') {
                $slug = api_slugify($title);
            }

            $otherSlugs = array_column($posts, 'slug');
            unset($otherSlugs[$index]);
            $candidate = $slug;
            $suffix = 2;
            while (in_array($candidate, $otherSlugs, true)) {
                $candidate = $slug . '-' . $suffix++;
            }
            $slug = $candidate;

            $status = $body['status'] ?? $post['status'];
            if (!in_array($status, ['draft', 'published'], true)) {
                $status = $post['status'];
            }

            $post['title'] = $title;
            $post['slug'] = $slug;
            $post['excerpt'] = api_trim_string($body['excerpt'] ?? $post['excerpt']);
            $post['heroImage'] = api_trim_string($body['heroImage'] ?? $post['heroImage']);
            $post['content'] = $content;
            $post['tags'] = isset($body['tags']) && is_array($body['tags']) ? $body['tags'] : $post['tags'];
            $post['readTimeMinutes'] = isset($body['readTimeMinutes']) ? (int) $body['readTimeMinutes'] : $post['readTimeMinutes'];
            $post['status'] = $status;
            if ($status === 'published' && ($post['publishedAt'] ?? null) === null) {
                $post['publishedAt'] = date('c');
            }
            if ($status !== 'published') {
                $post['publishedAt'] = null;
            }
            $post['updatedAt'] = date('c');
            $post['author'] = $post['author'] ?? ['name' => $actor['name'] ?? 'Portal Admin', 'role' => 'Admin'];

            $posts[$index] = $post;
            api_write_blog_posts($posts);
            api_send_json(200, ['post' => $post]);
        }

        if ($method === 'DELETE') {
            array_splice($posts, $index, 1);
            api_write_blog_posts($posts);
            api_send_json(200, ['deleted' => true]);
        }

        api_method_not_allowed(['GET', 'PUT', 'PATCH', 'DELETE']);
        break;

    case $path === '/admin/site-settings':
        api_require_admin();
        if ($method === 'GET') {
            api_send_json(200, ['settings' => api_read_site_settings()]);
        }
        if ($method === 'PUT') {
            $body = api_read_json_body();
            $saved = api_write_site_settings($body);
            api_send_json(200, ['settings' => $saved]);
        }
        api_method_not_allowed(['GET', 'PUT']);
        break;

    case preg_match('#^/dashboard/([a-z]+)$#', $path, $matches) && $method === 'GET':
        $role = $matches[1];
        $user = api_require_login();
        if (($user['role'] ?? '') !== $role) {
            api_send_error(403, 'You are not allowed to view this dashboard.');
        }
        api_send_json(200, api_dashboard_payload($user));
        break;

    case $path === '/me' && $method === 'GET':
        api_send_json(200, ['user' => api_require_login()]);
        break;
}

api_send_error(404, 'Endpoint not found.');
