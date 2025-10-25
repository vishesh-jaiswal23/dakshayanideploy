<?php

declare(strict_types=1);


if (!function_exists('portal_default_state')) {
    require_once __DIR__ . '/../portal-state.php';
}

function gemini_normalize_config_key(string $key): string
{
    $withBoundaries = preg_replace('/([a-z\d])([A-Z])/', '$1_$2', $key);
    if (!is_string($withBoundaries)) {
        $withBoundaries = $key;
    }

    $normalized = strtolower((string) $withBoundaries);
    $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized ?? '');
    if (!is_string($normalized)) {
        $normalized = strtolower((string) $withBoundaries);
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized ?? '');
    }

    return trim((string) $normalized, '_');
}

/**
 * @return array{
 *     api_key?: string,
 *     default_model?: string,
 *     default_version?: string,
 *     models?: array<string, string>,
 *     versions?: array<string, string>
 * }
 */
function gemini_load_api_configuration(?string $path = null): array
{
    $path = $path ?? dirname(__DIR__) . '/api.txt';

    $config = [
        'models' => [],
        'versions' => [],
    ];

    if (!is_string($path) || $path === '' || !is_readable($path)) {
        return $config;
    }

    $raw = trim((string) file_get_contents($path));
    if ($raw === '') {
        return $config;
    }

    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $config = gemini_apply_configuration_array($decoded, $config);
    } else {
        $lines = preg_split("/\r\n|\n|\r/", $raw) ?: [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (strpos($line, '=') === false) {
                if (!isset($config['api_key'])) {
                    $config['api_key'] = $line;
                }
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if ($key === '') {
                continue;
            }

            $config = gemini_apply_configuration_pair($config, $key, $value);
        }
    }

    return $config;
}

/**
 * @param array<string, mixed> $data
 * @param array{
 *     api_key?: string,
 *     default_model?: string,
 *     default_version?: string,
 *     models: array<string, string>,
 *     versions: array<string, string>
 * } $config
 * @return array{
 *     api_key?: string,
 *     default_model?: string,
 *     default_version?: string,
 *     models: array<string, string>,
 *     versions: array<string, string>
 * }
 */
function gemini_apply_configuration_array(array $data, array $config): array
{
    foreach ($data as $key => $value) {
        if (is_int($key)) {
            continue;
        }

        $normalizedKey = gemini_normalize_config_key((string) $key);

        if (is_array($value) && $normalizedKey !== '') {
            if (in_array($normalizedKey, ['models', 'model_map'], true)) {
                foreach ($value as $task => $modelValue) {
                    if (!is_string($task) || !is_string($modelValue)) {
                        continue;
                    }

                    $config = gemini_apply_configuration_pair($config, (string) $task . '_model', $modelValue);
                }
                continue;
            }

            if (in_array($normalizedKey, ['versions', 'version_map'], true)) {
                foreach ($value as $task => $versionValue) {
                    if (!is_string($task) || !is_string($versionValue)) {
                        continue;
                    }

                    $config = gemini_apply_configuration_pair($config, (string) $task . '_version', $versionValue);
                }
                continue;
            }
        }

        if (is_scalar($value)) {
            $config = gemini_apply_configuration_pair($config, (string) $key, (string) $value);
        }
    }

    return $config;
}

/**
 * @param array{
 *     api_key?: string,
 *     default_model?: string,
 *     default_version?: string,
 *     models: array<string, string>,
 *     versions: array<string, string>
 * } $config
 * @return array{
 *     api_key?: string,
 *     default_model?: string,
 *     default_version?: string,
 *     models: array<string, string>,
 *     versions: array<string, string>
 * }
 */
function gemini_apply_configuration_pair(array $config, string $key, string $value): array
{
    $normalizedKey = gemini_normalize_config_key($key);
    $value = trim($value);

    if ($value === '') {
        return $config;
    }

    switch ($normalizedKey) {
        case 'api_key':
        case 'gemini_api_key':
        case 'key':
        case 'token':
            $config['api_key'] = $value;
            break;

        case 'model':
        case 'default_model':
        case 'gemini_model':
            $config['default_model'] = $value;
            break;

        case 'api_version':
        case 'version':
        case 'default_version':
        case 'gemini_api_version':
            $config['default_version'] = $value;
            break;

        case 'news_model':
        case 'model_news':
        case 'news':
        case 'news_digest_model':
            $config['models']['news_digest'] = $value;
            break;

        case 'blog_model':
        case 'model_blog':
        case 'blog':
        case 'blog_research_model':
            $config['models']['blog_research'] = $value;
            break;

        case 'operations_model':
        case 'ops_model':
        case 'operations':
        case 'operations_watch_model':
        case 'ops_watch_model':
            $config['models']['operations_watch'] = $value;
            break;

        case 'news_version':
        case 'version_news':
        case 'news_api_version':
            $config['versions']['news_digest'] = $value;
            break;

        case 'blog_version':
        case 'version_blog':
        case 'blog_api_version':
            $config['versions']['blog_research'] = $value;
            break;

        case 'operations_version':
        case 'ops_version':
        case 'operations_api_version':
        case 'ops_api_version':
            $config['versions']['operations_watch'] = $value;
            break;
    }

    return $config;
}

final class GeminiSchedule
{
    public static function timezone(array $automation): DateTimeZone
    {
        $schedule = $automation['schedule'] ?? [];
        $timezone = is_string($schedule['timezone'] ?? null) ? trim((string) $schedule['timezone']) : '';
        if ($timezone === '') {
            $timezone = 'Asia/Kolkata';
        }

        try {
            return new DateTimeZone($timezone);
        } catch (Throwable $e) {
            return new DateTimeZone('Asia/Kolkata');
        }
    }

    public static function scheduledTime(array $automation): string
    {
        $schedule = $automation['schedule'] ?? [];
        $time = is_string($schedule['time'] ?? null) ? trim($schedule['time']) : '';
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
            $time = '06:00';
        }

        return $time;
    }

    public static function daysOfWeek(array $automation): array
    {
        $schedule = $automation['schedule'] ?? [];
        $days = [];
        if (isset($schedule['daysOfWeek']) && is_array($schedule['daysOfWeek'])) {
            foreach ($schedule['daysOfWeek'] as $day) {
                $dayInt = (int) $day;
                if ($dayInt >= 1 && $dayInt <= 7) {
                    $days[$dayInt] = true;
                }
            }
        }

        $list = array_keys($days);
        sort($list);

        return $list;
    }

    public static function formatSchedule(array $automation): string
    {
        $time = self::scheduledTime($automation);
        $timezone = self::timezone($automation)->getName();
        $days = self::daysOfWeek($automation);

        if ($days === []) {
            $label = 'Daily';
        } else {
            $names = [
                1 => 'Monday',
                2 => 'Tuesday',
                3 => 'Wednesday',
                4 => 'Thursday',
                5 => 'Friday',
                6 => 'Saturday',
                7 => 'Sunday',
            ];
            $label = implode(', ', array_map(static fn(int $day) => $names[$day] ?? (string) $day, $days));
        }

        return sprintf('%s at %s (%s)', $label, $time, $timezone);
    }

    public static function calculateNextRun(array $automation, ?DateTimeImmutable $now = null): ?DateTimeImmutable
    {
        $timezone = self::timezone($automation);
        $now = $now ? $now->setTimezone($timezone) : new DateTimeImmutable('now', $timezone);
        $time = self::scheduledTime($automation);
        $days = self::daysOfWeek($automation);

        $candidate = DateTimeImmutable::createFromFormat('Y-m-d H:i', $now->format('Y-m-d') . ' ' . $time, $timezone);
        if ($candidate === false) {
            return null;
        }

        if ($days === []) {
            if ($candidate <= $now) {
                return $candidate->modify('+1 day');
            }

            return $candidate;
        }

        $currentDay = (int) $now->format('N');
        if (in_array($currentDay, $days, true) && $candidate > $now) {
            return $candidate;
        }

        for ($offset = 1; $offset <= 14; $offset++) {
            $check = $candidate->modify('+' . $offset . ' day');
            if (in_array((int) $check->format('N'), $days, true)) {
                return $check;
            }
        }

        return null;
    }

    public static function isDue(array $automation, DateTimeImmutable $now): bool
    {
        if (!($automation['enabled'] ?? true)) {
            return false;
        }

        $timezone = self::timezone($automation);
        $now = $now->setTimezone($timezone);
        $time = self::scheduledTime($automation);
        $days = self::daysOfWeek($automation);

        if ($days !== [] && !in_array((int) $now->format('N'), $days, true)) {
            return false;
        }

        $scheduled = DateTimeImmutable::createFromFormat('Y-m-d H:i', $now->format('Y-m-d') . ' ' . $time, $timezone);
        if ($scheduled === false || $now < $scheduled) {
            return false;
        }

        $lastRun = $automation['last_run_at'] ?? null;
        if (!is_string($lastRun) || $lastRun === '') {
            return true;
        }

        try {
            $lastRunAt = new DateTimeImmutable($lastRun, $timezone);
        } catch (Throwable $e) {
            return true;
        }

        return $lastRunAt < $scheduled;
    }
}

final class GeminiClient
{
    private string $apiKey;
    private string $model;
    private string $apiVersion;
    /** @var array<string, string> */
    private array $taskModels = [];
    /** @var array<string, string> */
    private array $taskVersions = [];

    public function __construct(?string $apiKey = null, ?string $model = null, ?string $apiVersion = null)
    {
        $fileConfig = gemini_load_api_configuration();

        $candidate = is_string($apiKey) ? trim($apiKey) : '';
        if ($candidate === '' && isset($fileConfig['api_key'])) {
            $candidate = trim((string) $fileConfig['api_key']);
        }

        if ($candidate === '') {
            $candidate = trim((string) (getenv('GEMINI_API_KEY')
                ?: getenv('GOOGLE_GEMINI_API_KEY')
                ?: getenv('GOOGLE_AI_STUDIO_KEY')
                ?: ''));
        }

        if ($candidate === '') {
            throw new RuntimeException('Gemini API key not configured. Set GEMINI_API_KEY or provide api.txt.');
        }

        $this->apiKey = $candidate;

        $this->taskModels = array_map(static fn($value) => trim((string) $value), $fileConfig['models'] ?? []);
        $this->taskVersions = array_map(static fn($value) => trim((string) $value), $fileConfig['versions'] ?? []);

        $modelCandidate = is_string($model) ? trim($model) : '';
        if ($modelCandidate === '' && isset($fileConfig['default_model'])) {
            $modelCandidate = trim((string) $fileConfig['default_model']);
        }

        if ($modelCandidate === '') {
            $modelCandidate = trim((string) (getenv('GEMINI_MODEL') ?: 'gemini-1.5-pro-latest'));
        }

        $this->model = $modelCandidate;
        $versionCandidate = is_string($apiVersion) ? trim($apiVersion) : '';
        if ($versionCandidate === '' && isset($fileConfig['default_version'])) {
            $versionCandidate = trim((string) $fileConfig['default_version']);
        }

        if ($versionCandidate === '') {
            $versionCandidate = trim((string) (getenv('GEMINI_API_VERSION') ?: 'v1beta'));
        }

        if ($versionCandidate === '') {
            $versionCandidate = 'v1beta';
        }

        $this->apiVersion = $versionCandidate;
    }

    public function generateJson(array $messages, ?string $systemInstruction = null, array $generationConfig = [], ?string $task = null): array
    {
        $contents = [];
        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }
            $role = $message['role'] ?? 'user';
            $parts = $message['parts'] ?? [];
            if (!is_array($parts) || $parts === []) {
                $text = isset($message['text']) ? (string) $message['text'] : '';
                if ($text === '') {
                    continue;
                }
                $parts = [['text' => $text]];
            }

            $contents[] = [
                'role' => $role,
                'parts' => array_values(array_filter($parts, static function ($part) {
                    return is_array($part) && (isset($part['text']) || isset($part['inlineData']) || isset($part['functionCall']));
                })),
            ];
        }

        if ($contents === []) {
            throw new RuntimeException('At least one message is required for the Gemini request.');
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => array_merge([
                'temperature' => 0.45,
                'topP' => 0.8,
                'topK' => 32,
                'maxOutputTokens' => 1024,
                'responseMimeType' => 'application/json',
            ], $generationConfig),
        ];

        if ($systemInstruction !== null && trim($systemInstruction) !== '') {
            $payload['systemInstruction'] = [
                'role' => 'system',
                'parts' => [[
                    'text' => trim($systemInstruction),
                ]],
            ];
        }

        $response = $this->postJson($payload, $this->buildEndpoint($task));

        if (!is_array($response)) {
            throw new RuntimeException('Unexpected response from Gemini API.');
        }

        if (isset($response['error']['message'])) {
            $message = (string) $response['error']['message'];
            $code = (string) ($response['error']['code'] ?? '');
            throw new RuntimeException(sprintf('Gemini API error %s%s', $code !== '' ? $code . ': ' : '', $message));
        }

        return $this->extractJson($response);
    }

    private function buildEndpoint(?string $task): string
    {
        $model = $this->model;
        $version = $this->apiVersion;

        if ($task !== null) {
            $normalizedTask = $this->normalizeTaskKey($task);

            if (isset($this->taskModels[$task]) && $this->taskModels[$task] !== '') {
                $model = $this->taskModels[$task];
            } elseif (isset($this->taskModels[$normalizedTask]) && $this->taskModels[$normalizedTask] !== '') {
                $model = $this->taskModels[$normalizedTask];
            }

            if (isset($this->taskVersions[$task]) && $this->taskVersions[$task] !== '') {
                $version = $this->taskVersions[$task];
            } elseif (isset($this->taskVersions[$normalizedTask]) && $this->taskVersions[$normalizedTask] !== '') {
                $version = $this->taskVersions[$normalizedTask];
            }
        }

        if ($model === '') {
            $model = 'gemini-1.5-pro-latest';
        }

        if ($version === '') {
            $version = 'v1beta';
        }

        return sprintf(
            'https://generativelanguage.googleapis.com/%s/models/%s:generateContent?key=%s',
            rawurlencode($version),
            rawurlencode($model),
            rawurlencode($this->apiKey)
        );
    }

    private function normalizeTaskKey(string $task): string
    {
        if (isset($this->taskModels[$task]) || isset($this->taskVersions[$task])) {
            return $task;
        }

        $map = [
            'news' => 'news_digest',
            'news_digest' => 'news_digest',
            'blog' => 'blog_research',
            'blog_research' => 'blog_research',
            'operations' => 'operations_watch',
            'ops' => 'operations_watch',
            'operations_watch' => 'operations_watch',
        ];

        $normalized = strtolower(trim($task));

        return $map[$normalized] ?? $task;
    }

    private function postJson(array $payload, string $endpoint): array
    {
        $json = json_encode($payload);
        if ($json === false) {
            throw new RuntimeException('Failed to encode request payload for Gemini API.');
        }

        if (function_exists('curl_init')) {
            $handle = curl_init($endpoint);
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $json,
                CURLOPT_TIMEOUT => 45,
            ]);

            $raw = curl_exec($handle);
            if ($raw === false) {
                $error = curl_error($handle);
                curl_close($handle);
                throw new RuntimeException('Gemini API request failed: ' . $error);
            }

            $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE) ?: 0;
            curl_close($handle);

            if ($status >= 400) {
                $decodedError = json_decode((string) $raw, true);
                if (is_array($decodedError) && isset($decodedError['error']['message'])) {
                    $message = (string) $decodedError['error']['message'];
                    if (stripos($message, 'not found') !== false && stripos($message, 'models/') !== false) {
                        $message .= ' (Set GEMINI_MODEL to a supported model such as gemini-1.5-pro-latest.)';
                    }

                    throw new RuntimeException(sprintf('Gemini API responded with HTTP %d: %s', $status, $message));
                }

                throw new RuntimeException(sprintf('Gemini API responded with HTTP %d: %s', $status, $raw));
            }

            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                throw new RuntimeException('Failed to decode Gemini API response.');
            }

            return $decoded;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $json,
                'timeout' => 45,
            ],
        ]);

        $raw = file_get_contents($endpoint, false, $context);
        if ($raw === false) {
            throw new RuntimeException('Gemini API request failed.');
        }

        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $line) {
                if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/', (string) $line, $matches)) {
                    $code = (int) $matches[1];
                    if ($code >= 400) {
                        $decodedError = json_decode((string) $raw, true);
                        if (is_array($decodedError) && isset($decodedError['error']['message'])) {
                            $message = (string) $decodedError['error']['message'];
                            if (stripos($message, 'not found') !== false && stripos($message, 'models/') !== false) {
                                $message .= ' (Set GEMINI_MODEL to a supported model such as gemini-1.5-pro-latest.)';
                            }

                            throw new RuntimeException(sprintf('Gemini API responded with HTTP %d: %s', $code, $message));
                        }

                        throw new RuntimeException(sprintf('Gemini API responded with HTTP %d: %s', $code, $raw));
                    }
                    break;
                }
            }
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Failed to decode Gemini API response.');
        }

        return $decoded;
    }

    private function extractJson(array $response): array
    {
        $candidates = $response['candidates'] ?? [];
        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            $parts = $candidate['content']['parts'] ?? [];
            if (!is_array($parts)) {
                continue;
            }

            foreach ($parts as $part) {
                if (!is_array($part)) {
                    continue;
                }

                if (isset($part['text'])) {
                    $text = trim((string) $part['text']);
                    if ($text === '') {
                        continue;
                    }

                    $decoded = json_decode($text, true);
                    if (is_array($decoded)) {
                        return $decoded;
                    }
                }

                if (isset($part['inlineData']['data'])) {
                    $payload = base64_decode((string) $part['inlineData']['data'], true);
                    if ($payload !== false) {
                        $decoded = json_decode($payload, true);
                        if (is_array($decoded)) {
                            return $decoded;
                        }
                    }
                }
            }
        }

        throw new RuntimeException('Gemini response did not include parsable JSON content.');
    }
}

final class GeminiAutomation
{
    private array $state;
    private GeminiClient $client;

    public function __construct(array &$state, GeminiClient $client)
    {
        $this->state =& $state;
        $this->client = $client;

        $defaults = portal_default_state()['ai_automation'];
        $this->state['ai_automation'] = portal_normalize_ai_automation($this->state['ai_automation'] ?? [], $defaults);
    }

    /**
     * @return array<string, mixed>
     */
    public function runDueAutomations(DateTimeImmutable $now, array $targets = [], bool $force = false): array
    {
        $keys = $targets === [] ? ['news_digest', 'blog_research', 'operations_watch'] : array_values(array_unique($targets));
        $results = [];

        foreach ($keys as $key) {
            switch ($key) {
                case 'news_digest':
                    $results[] = $this->runNewsDigest($now, $force);
                    break;
                case 'blog_research':
                    $results[] = $this->runBlogResearch($now, $force);
                    break;
                case 'operations_watch':
                    $results[] = $this->runOperationsWatch($now, $force);
                    break;
                default:
                    $results[] = [
                        'automation' => $key,
                        'status' => 'skipped',
                        'message' => sprintf('Unknown automation key "%s".', (string) $key),
                        'ran' => false,
                        'stateChanged' => false,
                    ];
                    break;
            }
        }

        return $results;
    }

    public function runNewsDigest(DateTimeImmutable $now, bool $force = false): array
    {
        $automation =& $this->state['ai_automation']['news_digest'];

        if (!$force && !GeminiSchedule::isDue($automation, $now)) {
            return [
                'automation' => 'news_digest',
                'status' => 'skipped',
                'message' => 'Daily news digest is already up to date for today.',
                'ran' => false,
                'stateChanged' => false,
            ];
        }

        try {
            $digest = $this->generateNewsDigest($now, $automation);
        } catch (RuntimeException $exception) {
            $automation['last_error'] = [
                'message' => $exception->getMessage(),
                'occurred_at' => $now->format(DateTimeInterface::ATOM),
            ];

            return [
                'automation' => 'news_digest',
                'status' => 'error',
                'message' => $exception->getMessage(),
                'ran' => false,
                'stateChanged' => true,
            ];
        }

        $automation['last_error'] = null;
        $automation['last_run_at'] = $now->format(DateTimeInterface::ATOM);
        $automation['last_digest'] = $digest;
        $automation['history'] = $this->prependHistoryEntry($automation['history'], $digest, 'id', 10);

        portal_record_activity($this->state, 'Gemini published the solar & renewable energy news digest.', 'Gemini automation');

        return [
            'automation' => 'news_digest',
            'status' => 'success',
            'message' => 'Daily news digest prepared and published.',
            'ran' => true,
            'stateChanged' => true,
            'payload' => $digest,
        ];
    }

    public function runBlogResearch(DateTimeImmutable $now, bool $force = false): array
    {
        $automation =& $this->state['ai_automation']['blog_research'];

        if (!$force && !GeminiSchedule::isDue($automation, $now)) {
            return [
                'automation' => 'blog_research',
                'status' => 'skipped',
                'message' => 'Scheduled blog post already generated for the current slot.',
                'ran' => false,
                'stateChanged' => false,
            ];
        }

        try {
            $blogPlan = $this->generateBlogPlan($now, $automation);
            $post = $this->persistBlogPost($now, $blogPlan);
        } catch (RuntimeException $exception) {
            $automation['last_error'] = [
                'message' => $exception->getMessage(),
                'occurred_at' => $now->format(DateTimeInterface::ATOM),
            ];

            return [
                'automation' => 'blog_research',
                'status' => 'error',
                'message' => $exception->getMessage(),
                'ran' => false,
                'stateChanged' => true,
            ];
        }

        $automation['last_error'] = null;
        $automation['last_run_at'] = $now->format(DateTimeInterface::ATOM);
        $automation['last_blog'] = $post;
        $automation['history'] = $this->prependHistoryEntry($automation['history'], $post, 'id', 8);

        portal_record_activity($this->state, sprintf('Gemini published a blog article: %s.', $post['title'] ?? 'New insight'), 'Gemini automation');

        return [
            'automation' => 'blog_research',
            'status' => 'success',
            'message' => sprintf('Blog post "%s" published via automation.', $post['title'] ?? 'New insight'),
            'ran' => true,
            'stateChanged' => true,
            'payload' => $post,
        ];
    }

    public function runOperationsWatch(DateTimeImmutable $now, bool $force = false): array
    {
        $automation =& $this->state['ai_automation']['operations_watch'];

        if (!$force && !GeminiSchedule::isDue($automation, $now)) {
            return [
                'automation' => 'operations_watch',
                'status' => 'skipped',
                'message' => 'Operations review already completed for today.',
                'ran' => false,
                'stateChanged' => false,
            ];
        }

        $snapshot = $this->buildOperationsSnapshot($now);

        try {
            $report = $this->generateOperationsReport($now, $snapshot);
        } catch (RuntimeException $exception) {
            $automation['last_error'] = [
                'message' => $exception->getMessage(),
                'occurred_at' => $now->format(DateTimeInterface::ATOM),
            ];

            return [
                'automation' => 'operations_watch',
                'status' => 'error',
                'message' => $exception->getMessage(),
                'ran' => false,
                'stateChanged' => true,
            ];
        }

        $automation['last_error'] = null;
        $automation['last_run_at'] = $now->format(DateTimeInterface::ATOM);
        $automation['last_report'] = $report;
        $automation['history'] = $this->prependHistoryEntry($automation['history'], $report, 'id', 6);

        portal_record_activity($this->state, 'Gemini reviewed dashboard activity and shared recommendations.', 'Gemini automation');

        return [
            'automation' => 'operations_watch',
            'status' => 'success',
            'message' => 'Operations oversight report generated.',
            'ran' => true,
            'stateChanged' => true,
            'payload' => $report,
        ];
    }

    private function generateNewsDigest(DateTimeImmutable $now, array $automation): array
    {
        $timezone = GeminiSchedule::timezone($automation);
        $timestamp = $now->setTimezone($timezone)->format(DateTimeInterface::ATOM);
        $recentHeadlines = [];
        foreach (array_slice($automation['history'] ?? [], 0, 5) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            foreach ($entry['items'] ?? [] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $headline = trim((string) ($item['headline'] ?? ''));
                if ($headline !== '') {
                    $recentHeadlines[] = $headline;
                }
            }
        }

        $prompt = [
            'role' => 'user',
            'parts' => [[
                'text' => json_encode([
                    'instruction' => 'Generate a 4-item renewable energy news digest for Dakshayani Enterprises.',
                    'focus' => 'Include at least two India-specific updates and two international developments relevant to solar, green hydrogen, storage, or renewable policy.',
                    'output' => 'Return JSON with keys: summary (string), items (array of {headline, region, summary, recommendedAction, sourceHints[]}). Provide concise actionable language ready to publish.',
                    'timestamp' => $timestamp,
                    'avoidHeadlines' => $recentHeadlines,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            ]],
        ];

        $system = "You are an editorial analyst for Dakshayani Enterprises. Source timely solar and renewable energy developments from India and global markets. Focus on insights leaders can act on (policy shifts, financing updates, technology deployments). Return strictly JSON.";

        $response = $this->client->generateJson([$prompt], $system, ['maxOutputTokens' => 768], 'news_digest');

        $items = [];
        foreach ($response['items'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $headline = trim((string) ($item['headline'] ?? ''));
            if ($headline === '') {
                continue;
            }

            $items[] = [
                'headline' => $headline,
                'region' => trim((string) ($item['region'] ?? 'India')),
                'summary' => trim((string) ($item['summary'] ?? '')),
                'recommendedAction' => trim((string) ($item['recommendedAction'] ?? '')),
                'sourceHints' => array_values(array_filter(array_map(static function ($hint) {
                    if (is_array($hint)) {
                        $label = trim((string) ($hint['label'] ?? ''));
                        $url = trim((string) ($hint['url'] ?? ''));
                        return $label !== '' || $url !== '' ? ['label' => $label, 'url' => $url] : null;
                    }

                    $text = trim((string) $hint);
                    return $text !== '' ? ['label' => $text, 'url' => ''] : null;
                }, $item['sourceHints'] ?? []), static fn($value) => $value !== null)),
            ];
        }

        if ($items === []) {
            throw new RuntimeException('Gemini did not return any news items.');
        }

        return [
            'id' => 'news_' . $now->setTimezone($timezone)->format('Ymd'),
            'generated_at' => $timestamp,
            'timezone' => $timezone->getName(),
            'summary' => trim((string) ($response['summary'] ?? '')), 
            'items' => $items,
        ];
    }

    private function generateBlogPlan(DateTimeImmutable $now, array $automation): array
    {
        $timezone = GeminiSchedule::timezone($automation);
        $recentTitles = array_map(static function ($entry) {
            return is_array($entry) ? trim((string) ($entry['title'] ?? '')) : '';
        }, array_slice($automation['history'] ?? [], 0, 5));
        $recentTitles = array_values(array_filter($recentTitles, static fn($value) => $value !== ''));

        $messages = [[
            'role' => 'user',
            'parts' => [[
                'text' => json_encode([
                    'instruction' => 'Research and draft a 700-word blog post for Dakshayani Enterprises.',
                    'audience' => 'Indian solar customers, MSMEs, and policy stakeholders.',
                    'tone' => 'Practical, data-backed, and aligned with Dakshayani Enterprises services.',
                    'structure' => 'Return JSON with keys: title, slug, excerpt, tags[], heroImage {src, alt}, sections[{heading, paragraphs[]}] and keyTakeaways[].',
                    'publishTimestamp' => $now->setTimezone($timezone)->format(DateTimeInterface::ATOM),
                    'avoidTitles' => $recentTitles,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            ]],
        ]];

        $system = "You are the content strategist for Dakshayani Enterprises. Produce policy-aware and data-rich solar blog posts focused on India. Reference Rooftop solar, PM Surya Ghar, hybrid systems, hydrogen pilots, or financing trends as relevant. Return only JSON.";

        $response = $this->client->generateJson($messages, $system, ['maxOutputTokens' => 1400, 'temperature' => 0.5], 'blog_research');

        $title = trim((string) ($response['title'] ?? '')); 
        $slug = trim((string) ($response['slug'] ?? ''));
        $excerpt = trim((string) ($response['excerpt'] ?? ''));
        $tags = array_values(array_filter(array_map(static fn($tag) => trim((string) $tag), $response['tags'] ?? [])));

        if ($title === '' || $excerpt === '') {
            throw new RuntimeException('Gemini did not return a complete blog outline.');
        }

        $hero = $response['heroImage'] ?? [];
        $heroSrc = is_array($hero) ? trim((string) ($hero['src'] ?? '')) : '';
        $heroAlt = is_array($hero) ? trim((string) ($hero['alt'] ?? '')) : '';

        $sections = [];
        foreach ($response['sections'] ?? [] as $section) {
            if (!is_array($section)) {
                continue;
            }
            $heading = trim((string) ($section['heading'] ?? ''));
            $paragraphs = array_values(array_filter(array_map(static fn($paragraph) => trim((string) $paragraph), $section['paragraphs'] ?? [])));
            if ($heading === '' && $paragraphs === []) {
                continue;
            }
            $sections[] = [
                'heading' => $heading,
                'paragraphs' => $paragraphs,
            ];
        }

        if ($sections === []) {
            throw new RuntimeException('Gemini did not return detailed blog sections.');
        }

        $takeaways = array_values(array_filter(array_map(static fn($item) => trim((string) $item), $response['keyTakeaways'] ?? [])));

        return [
            'title' => $title,
            'slug' => $slug,
            'excerpt' => $excerpt,
            'tags' => $tags,
            'hero' => [
                'src' => $heroSrc,
                'alt' => $heroAlt,
            ],
            'sections' => $sections,
            'keyTakeaways' => $takeaways,
        ];
    }

    private function persistBlogPost(DateTimeImmutable $now, array $plan): array
    {
        $posts = $this->state['blog_posts'] ?? [];
        if (!is_array($posts)) {
            $posts = [];
        }

        $existingSlugs = [];
        foreach ($posts as $post) {
            if (is_array($post)) {
                $slugValue = strtolower(trim((string) ($post['slug'] ?? '')));
                if ($slugValue !== '') {
                    $existingSlugs[$slugValue] = true;
                }
            }
        }

        $baseSlug = $plan['slug'] !== '' ? portal_slugify($plan['slug']) : portal_slugify($plan['title']);
        if ($baseSlug === '') {
            $baseSlug = 'dakshayani-solar-update';
        }

        $slug = $baseSlug;
        $counter = 2;
        while (isset($existingSlugs[strtolower($slug)])) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        $paragraphs = [];
        foreach ($plan['sections'] as $section) {
            $heading = trim((string) ($section['heading'] ?? ''));
            $paragraphList = $section['paragraphs'] ?? [];
            if ($heading !== '') {
                $paragraphs[] = $heading;
            }
            foreach ($paragraphList as $paragraph) {
                $text = trim((string) $paragraph);
                if ($text !== '') {
                    $paragraphs[] = $text;
                }
            }
        }

        foreach ($plan['keyTakeaways'] as $takeaway) {
            $text = trim((string) $takeaway);
            if ($text !== '') {
                $paragraphs[] = 'Key takeaway: ' . $text;
            }
        }

        $wordCount = max(200, array_sum(array_map(static function ($paragraph) {
            return str_word_count((string) $paragraph);
        }, $paragraphs)));
        $readMinutes = max(5, (int) ceil($wordCount / 200));

        $timestamp = $now->format(DateTimeInterface::ATOM);

        $post = [
            'id' => portal_generate_id('blog_'),
            'title' => $plan['title'],
            'slug' => $slug,
            'excerpt' => $plan['excerpt'],
            'hero_image' => $plan['hero']['src'] !== '' ? $plan['hero']['src'] : 'images/hero/hero.png',
            'tags' => $plan['tags'],
            'status' => 'published',
            'read_time_minutes' => $readMinutes,
            'author' => [
                'name' => 'Gemini Automation',
                'role' => 'AI Research Analyst',
            ],
            'content' => $paragraphs,
            'published_at' => $timestamp,
            'updated_at' => $timestamp,
        ];

        array_unshift($posts, $post);
        $this->state['blog_posts'] = array_slice($posts, 0, 30);

        return [
            'id' => $post['id'],
            'title' => $post['title'],
            'slug' => $post['slug'],
            'excerpt' => $post['excerpt'],
            'tags' => $post['tags'],
            'hero_image' => $post['hero_image'],
            'read_time_minutes' => $post['read_time_minutes'],
            'published_at' => $post['published_at'],
        ];
    }

    private function buildOperationsSnapshot(DateTimeImmutable $now): array
    {
        $tasks = is_array($this->state['tasks'] ?? null) ? $this->state['tasks'] : [];
        $taskSummary = [
            'total' => count($tasks),
            'byStatus' => [],
            'overdue' => [],
            'upcoming' => [],
        ];

        $timezone = new DateTimeZone('Asia/Kolkata');
        $today = $now->setTimezone($timezone);

        foreach ($tasks as $task) {
            if (!is_array($task)) {
                continue;
            }
            $status = trim((string) ($task['status'] ?? 'Pending'));
            $taskSummary['byStatus'][$status] = ($taskSummary['byStatus'][$status] ?? 0) + 1;

            $due = isset($task['due_date']) ? trim((string) $task['due_date']) : '';
            if ($due === '') {
                continue;
            }

            $dueDate = DateTimeImmutable::createFromFormat('Y-m-d', $due, $timezone);
            if ($dueDate === false) {
                continue;
            }

            $taskRow = [
                'title' => trim((string) ($task['title'] ?? 'Task')), 
                'owner' => trim((string) ($task['owner'] ?? ($task['assignee'] ?? 'Admin team'))),
                'due' => $dueDate->format('Y-m-d'),
                'status' => $status,
            ];

            if ($dueDate < $today && strcasecmp($status, 'Completed') !== 0) {
                $taskSummary['overdue'][] = $taskRow;
            } elseif ($dueDate <= $today->add(new DateInterval('P3D'))) {
                $taskSummary['upcoming'][] = $taskRow;
            }
        }

        $projects = is_array($this->state['projects'] ?? null) ? $this->state['projects'] : [];
        $projectSummary = [
            'total' => count($projects),
            'byStatus' => [],
        ];
        foreach ($projects as $project) {
            if (!is_array($project)) {
                continue;
            }
            $status = trim((string) ($project['status'] ?? 'planning'));
            $projectSummary['byStatus'][$status] = ($projectSummary['byStatus'][$status] ?? 0) + 1;
        }

        $approvals = $this->state['employee_approvals']['pending'] ?? [];
        if (!is_array($approvals)) {
            $approvals = [];
        }

        $tickets = $this->readTickets();
        $ticketSummary = [
            'total' => count($tickets),
            'open' => 0,
            'highPriority' => 0,
        ];
        foreach ($tickets as $ticket) {
            $status = strtolower(trim((string) ($ticket['status'] ?? 'open')));
            if (in_array($status, ['open', 'new', 'pending', 'in-progress'], true)) {
                $ticketSummary['open']++;
            }
            $priority = strtolower(trim((string) ($ticket['priority'] ?? 'medium')));
            if (in_array($priority, ['urgent', 'high'], true)) {
                $ticketSummary['highPriority']++;
            }
        }

        $activity = array_slice(is_array($this->state['activity_log'] ?? null) ? $this->state['activity_log'] : [], 0, 6);

        return [
            'generatedAt' => $now->format(DateTimeInterface::ATOM),
            'tasks' => $taskSummary,
            'projects' => $projectSummary,
            'approvals' => [
                'pending' => count($approvals),
            ],
            'complaints' => $ticketSummary,
            'recentActivity' => $activity,
        ];
    }

    private function generateOperationsReport(DateTimeImmutable $now, array $snapshot): array
    {
        $messages = [[
            'role' => 'user',
            'parts' => [[
                'text' => json_encode([
                    'instruction' => 'Review Dakshayani portal operations snapshot and propose improvements.',
                    'snapshot' => $snapshot,
                    'required' => 'Return JSON with keys: summary, riskFlags[], recommendations[]. Each recommendation should include area, urgency, suggestedActions[].',
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            ]],
        ]];

        $system = "You are Dakshayani Enterprises' operations reviewer. Analyse workloads, complaints, and approvals. Highlight risks and concrete actions to improve execution. Return only JSON.";

        $response = $this->client->generateJson($messages, $system, ['maxOutputTokens' => 900, 'temperature' => 0.35], 'operations_watch');

        $summary = trim((string) ($response['summary'] ?? ''));
        $riskFlags = [];
        foreach ($response['riskFlags'] ?? [] as $flag) {
            if (!is_array($flag)) {
                continue;
            }
            $detail = trim((string) ($flag['detail'] ?? ''));
            if ($detail === '') {
                continue;
            }
            $riskFlags[] = [
                'area' => trim((string) ($flag['area'] ?? 'General')),
                'detail' => $detail,
                'urgency' => trim((string) ($flag['urgency'] ?? 'medium')),
            ];
        }

        $recommendations = [];
        foreach ($response['recommendations'] ?? [] as $recommendation) {
            if (!is_array($recommendation)) {
                continue;
            }
            $area = trim((string) ($recommendation['area'] ?? 'Operations'));
            $actions = array_values(array_filter(array_map(static fn($action) => trim((string) $action), $recommendation['suggestedActions'] ?? [])));
            if ($actions === []) {
                continue;
            }
            $recommendations[] = [
                'area' => $area,
                'urgency' => trim((string) ($recommendation['urgency'] ?? 'medium')),
                'suggestedActions' => $actions,
            ];
        }

        if ($summary === '' && $recommendations === []) {
            throw new RuntimeException('Gemini did not return any operations insights.');
        }

        return [
            'id' => 'ops_' . $now->format('YmdHis'),
            'generated_at' => $now->format(DateTimeInterface::ATOM),
            'summary' => $summary,
            'riskFlags' => $riskFlags,
            'recommendations' => $recommendations,
        ];
    }

    private function readTickets(): array
    {
        $file = __DIR__ . '/data/tickets.json';
        if (!is_file($file)) {
            return [];
        }

        $json = file_get_contents($file);
        if ($json === false || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, static fn($ticket) => is_array($ticket)));
    }

    private function prependHistoryEntry(array $history, array $entry, string $identifierKey, int $limit): array
    {
        $identifier = $entry[$identifierKey] ?? null;

        $filtered = [];
        foreach ($history as $existing) {
            if (!is_array($existing)) {
                continue;
            }

            if ($identifier !== null && isset($existing[$identifierKey]) && $existing[$identifierKey] === $identifier) {
                continue;
            }

            $filtered[] = $existing;
        }

        array_unshift($filtered, $entry);

        return array_slice($filtered, 0, $limit);
    }
}
