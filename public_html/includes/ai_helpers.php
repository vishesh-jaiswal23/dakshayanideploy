<?php

declare(strict_types=1);

require_once __DIR__ . '/../../server/ai-automation.php';

/**
 * Obtain a PDO connection and ensure the required tables exist.
 */
function ai_get_pdo(): \PDO
{
    static $pdo = null;

    if ($pdo instanceof \PDO) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $database = getenv('DB_NAME') ?: 'dakshayani';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS') ?: '';
    $port = getenv('DB_PORT');

    $dsn = sprintf(
        'mysql:host=%s;%sdbname=%s;charset=utf8mb4',
        $host,
        $port !== false && $port !== '' ? 'port=' . (int) $port . ';' : '',
        $database
    );

    try {
        $pdo = new \PDO(
            $dsn,
            $username,
            $password,
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (\PDOException $exception) {
        throw new \RuntimeException('Database connection failed: ' . $exception->getMessage(), 0, $exception);
    }

    ai_initialize_schema($pdo);

    return $pdo;
}

/**
 * Ensure the ai_logs and posts tables exist with the required structure.
 */
function ai_initialize_schema(\PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS ai_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            run_time DATETIME NOT NULL,
            status ENUM('success','partial','failed') NOT NULL,
            posts_created INT DEFAULT 0,
            error_message TEXT NULL,
            raw_output LONGTEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            summary TEXT,
            body_markdown LONGTEXT,
            category VARCHAR(64) DEFAULT 'Solar',
            tags TEXT,
            cover_image_url TEXT NULL,
            publish_at_ist DATETIME,
            status ENUM('draft','published') DEFAULT 'draft',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

/**
 * Execute the Gemini API call and return the raw response body.
 *
 * @param array{prompt: string, system?: string} $inputs
 */
function ai_call_gemini(array $inputs): string
{
    $prompt = trim((string) ($inputs['prompt'] ?? ''));
    if ($prompt === '') {
        throw new \RuntimeException('Prompt content is required for the Gemini request.');
    }

    $system = trim((string) ($inputs['system'] ?? ''));

    $portalConfig = gemini_load_portal_configuration();
    $fileConfig = gemini_load_api_configuration();

    $apiKey = trim((string) ($inputs['api_key'] ?? ''));
    if ($apiKey === '') {
        $apiKey = isset($portalConfig['api_key']) ? trim((string) $portalConfig['api_key']) : '';
    }
    if ($apiKey === '') {
        $apiKey = isset($fileConfig['api_key']) ? trim((string) $fileConfig['api_key']) : '';
    }
    if ($apiKey === '') {
        $apiKey = trim((string) (
            getenv('GEMINI_API_KEY')
            ?: getenv('GOOGLE_GEMINI_API_KEY')
            ?: getenv('GOOGLE_AI_STUDIO_KEY')
            ?: ''
        ));
    }

    if ($apiKey === '') {
        throw new \RuntimeException('Gemini API key not configured.');
    }

    $model = trim((string) ($inputs['model'] ?? ''));
    if ($model === '' && isset($portalConfig['default_model'])) {
        $model = trim((string) $portalConfig['default_model']);
    }
    if ($model === '' && isset($fileConfig['default_model'])) {
        $model = trim((string) $fileConfig['default_model']);
    }
    if ($model === '') {
        $model = trim((string) (getenv('GEMINI_MODEL') ?: 'gemini-1.5-pro-latest'));
    }
    if ($model === '') {
        $model = 'gemini-1.5-pro-latest';
    }

    $version = trim((string) ($inputs['version'] ?? ''));
    if ($version === '' && isset($portalConfig['default_version'])) {
        $version = trim((string) $portalConfig['default_version']);
    }
    if ($version === '' && isset($fileConfig['default_version'])) {
        $version = trim((string) $fileConfig['default_version']);
    }
    if ($version === '') {
        $version = trim((string) (getenv('GEMINI_API_VERSION') ?: 'v1beta'));
    }
    if ($version === '') {
        $version = 'v1beta';
    }

    $endpoint = sprintf(
        'https://generativelanguage.googleapis.com/%s/models/%s:generateContent?key=%s',
        rawurlencode($version),
        rawurlencode($model),
        rawurlencode($apiKey)
    );

    $schema = [
        'type' => 'object',
        'properties' => [
            'posts' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'slug' => ['type' => 'string'],
                        'category' => ['type' => 'string'],
                        'tags' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                        'summary' => ['type' => 'string'],
                        'body_markdown' => ['type' => 'string'],
                        'cover_image_url' => ['type' => ['string', 'null']],
                        'publish_at_ist' => ['type' => 'string'],
                    ],
                    'required' => [
                        'title',
                        'slug',
                        'category',
                        'tags',
                        'summary',
                        'body_markdown',
                        'cover_image_url',
                        'publish_at_ist',
                    ],
                    'additionalProperties' => false,
                ],
                'minItems' => 1,
                'maxItems' => 2,
            ],
        ],
        'required' => ['posts'],
        'additionalProperties' => false,
    ];

    $payload = [
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => $prompt],
                ],
            ],
        ],
        'generationConfig' => [
            'temperature' => 0.35,
            'topP' => 0.8,
            'topK' => 32,
            'maxOutputTokens' => 2048,
            'responseMimeType' => 'application/json',
        ],
        'responseSchema' => $schema,
    ];

    if ($system !== '') {
        $payload['systemInstruction'] = [
            'role' => 'system',
            'parts' => [
                ['text' => $system],
            ],
        ];
    }

    $encodedPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encodedPayload === false) {
        throw new \RuntimeException('Failed to encode Gemini payload: ' . json_last_error_msg());
    }

    $options = [
        CURLOPT_URL => $endpoint,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json; charset=utf-8',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => $encodedPayload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
    ];

    $ch = curl_init();
    if ($ch === false) {
        throw new \RuntimeException('Unable to initialise the HTTP client for Gemini.');
    }

    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        throw new \RuntimeException('Gemini request failed: ' . ($error !== '' ? $error : 'Unknown error'));
    }

    if ($statusCode >= 400) {
        throw new \RuntimeException(sprintf('Gemini API returned HTTP %d: %s', $statusCode, $response));
    }

    return $response;
}

function ai_extract_json_block(string $raw): string
{
    $clean = trim($raw);
    if ($clean === '') {
        return $clean;
    }

    $clean = preg_replace('/```json|```/i', '', $clean);
    if (!is_string($clean)) {
        $clean = $raw;
    }

    $clean = trim($clean);

    if (preg_match_all('/\{(?:[^{}]|(?R))*\}/s', $clean, $matches) > 0 && isset($matches[0]) && $matches[0] !== []) {
        $largest = '';
        foreach ($matches[0] as $match) {
            if (strlen($match) > strlen($largest)) {
                $largest = $match;
            }
        }
        if ($largest !== '') {
            return trim($largest);
        }
    }

    $start = strpos($clean, '{');
    $end = strrpos($clean, '}');

    if ($start !== false && $end !== false && $end >= $start) {
        return trim(substr($clean, (int) $start, (int) ($end - $start + 1)));
    }

    return $clean;
}

function ai_repair_json(string $json): string
{
    $clean = preg_replace('/```json|```/i', '', $json);
    if (!is_string($clean)) {
        $clean = $json;
    }

    $clean = trim($clean);
    $start = strpos($clean, '{');
    $end = strrpos($clean, '}');
    if ($start !== false && $end !== false && $end >= $start) {
        $clean = substr($clean, (int) $start, (int) ($end - $start + 1));
    }

    $clean = preg_replace('/,\s*([}\]])/m', '$1', $clean);
    if (!is_string($clean)) {
        $clean = $json;
    }

    if (strpos($clean, '"') === false && strpos($clean, "'") !== false) {
        $clean = preg_replace_callback(
            "/'([^'\\]*(?:\\.[^'\\]*)*)'/m",
            static function (array $matches): string {
                return '"' . str_replace('"', '\"', $matches[1]) . '"';
            },
            $clean
        ) ?? $clean;
    }

    $clean = trim($clean);
    $clean = mb_convert_encoding($clean, 'UTF-8', 'UTF-8');

    return $clean;
}

function ai_parse_json(string $raw): array
{
    $block = ai_extract_json_block($raw);

    $decoded = json_decode($block, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        return $decoded;
    }

    $repaired = ai_repair_json($block);
    $decoded = json_decode($repaired, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        return $decoded;
    }

    throw new \RuntimeException('Invalid JSON received from Gemini: ' . json_last_error_msg());
}

function ai_generate_posts_payload(): array
{
    $system = <<<PROMPT
Respond ONLY with valid JSON. No markdown. No commentary.
Schema:
{
  "posts": [
    {
      "title": "string",
      "slug": "kebab-case string",
      "category": "string",
      "tags": ["string", "..."],
      "summary": "string",
      "body_markdown": "string",
      "cover_image_url": "string or empty",
      "publish_at_ist": "ISO 8601 date-time in Asia/Kolkata"
    }
  ]
}
Return ONLY JSON. No extra text.
PROMPT;

    $today = (new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata')))->format('F j, Y');

    $prompt = <<<PROMPT
You are an expert solar energy content strategist for Jharkhand. Draft 1-2 high quality blog posts (120-300 words each) focusing on rooftop solar adoption in Jharkhand, PM Surya Ghar updates, DISCOM notifications, financing or EMI schemes, and maintenance tips for households. Each post must:
- Use "Solar" as the category.
- Provide 3-6 relevant tags.
- Include a concise summary and a markdown-ready body with headings and bullet points when useful.
- Prefer publish_at_ist set to today at 06:00 Asia/Kolkata if not sure.
- Avoid duplicate slugs and keep them kebab-case.
Today is {$today}. Return ONLY JSON. No extra text.
PROMPT;

    return [
        'system' => $system,
        'prompt' => $prompt,
    ];
}

/**
 * @param array<string, mixed> $data
 * @return array<int, array<string, mixed>>
 */
function ai_min_schema_check(array $data): array
{
    if (!isset($data['posts']) || !is_array($data['posts'])) {
        throw new \RuntimeException('Response missing posts array.');
    }

    $normalized = [];

    foreach ($data['posts'] as $post) {
        if (!is_array($post)) {
            continue;
        }

        $title = trim((string) ($post['title'] ?? ''));
        $slug = trim((string) ($post['slug'] ?? ''));
        $category = trim((string) ($post['category'] ?? 'Solar'));
        $summary = trim((string) ($post['summary'] ?? ''));
        $body = trim((string) ($post['body_markdown'] ?? ''));
        $cover = trim((string) ($post['cover_image_url'] ?? ''));
        $publish = trim((string) ($post['publish_at_ist'] ?? ''));
        $tags = $post['tags'] ?? [];

        if ($title === '' || $summary === '' || $body === '') {
            throw new \RuntimeException('Post is missing title, summary, or body content.');
        }

        if ($slug === '') {
            $slug = ai_slugify($title);
        }

        if (!is_array($tags)) {
            if (is_string($tags) && $tags !== '') {
                $tags = array_map('trim', explode(',', $tags));
            } else {
                $tags = [];
            }
        }

        $tags = array_values(array_filter(array_map(
            static fn($tag) => trim((string) $tag),
            $tags
        ), static fn(string $tag): bool => $tag !== ''));

        if (count($tags) < 3) {
            throw new \RuntimeException('Post must contain at least 3 tags.');
        }

        $normalized[] = [
            'title' => $title,
            'slug' => ai_slugify($slug),
            'category' => $category !== '' ? $category : 'Solar',
            'summary' => $summary,
            'body_markdown' => $body,
            'cover_image_url' => $cover,
            'publish_at_ist' => $publish,
            'tags' => $tags,
        ];
    }

    if ($normalized === []) {
        throw new \RuntimeException('No valid posts found in the Gemini response.');
    }

    return $normalized;
}

function ai_slugify(string $value): string
{
    $slug = strtolower($value);
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? '';
    $slug = trim($slug, '-');

    return $slug !== '' ? $slug : bin2hex(random_bytes(6));
}

/**
 * @param array<string, mixed> $post
 */
function posts_save_or_update(array $post): int
{
    $pdo = ai_get_pdo();

    $slug = ai_slugify((string) ($post['slug'] ?? ''));
    $baseSlug = $slug;
    $attempt = 1;

    $checkStmt = $pdo->prepare('SELECT id FROM posts WHERE slug = :slug LIMIT 1');

    while (true) {
        $checkStmt->execute(['slug' => $slug]);
        $existing = $checkStmt->fetchColumn();
        if ($existing === false) {
            break;
        }
        $attempt++;
        $slug = $baseSlug . '-' . $attempt;
    }

    $publishIso = (string) ($post['publish_at_ist'] ?? '');
    $publishDate = null;
    if ($publishIso !== '') {
        try {
            $publishDate = new DateTimeImmutable($publishIso, new DateTimeZone('Asia/Kolkata'));
        } catch (Throwable $exception) {
            $publishDate = null;
        }
    }

    if ($publishDate === null) {
        $publishDate = new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
    }

    $publishFormatted = $publishDate->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('Y-m-d H:i:s');
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
    $status = $publishDate <= $now ? 'published' : 'draft';

    $insert = $pdo->prepare(
        'INSERT INTO posts (title, slug, summary, body_markdown, category, tags, cover_image_url, publish_at_ist, status)
         VALUES (:title, :slug, :summary, :body, :category, :tags, :cover, :publish_at_ist, :status)'
    );

    $insert->execute([
        'title' => $post['title'],
        'slug' => $slug,
        'summary' => $post['summary'],
        'body' => $post['body_markdown'],
        'category' => $post['category'] ?? 'Solar',
        'tags' => json_encode($post['tags'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'cover' => $post['cover_image_url'] !== '' ? $post['cover_image_url'] : null,
        'publish_at_ist' => $publishFormatted,
        'status' => $status,
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * Execute the end-to-end Gemini workflow and return the outcome without logging.
 *
 * @return array{
 *     status: 'success'|'partial'|'failed',
 *     posts_created: int,
 *     error_message: ?string,
 *     raw_output: ?string,
 *     posts: array<int, array<string, mixed>>,
 *     errors: array<int, string>
 * }
 */
function ai_run_blog_generation(): array
{
    $payload = ai_generate_posts_payload();

    try {
        $raw = ai_call_gemini($payload);
    } catch (Throwable $exception) {
        return [
            'status' => 'failed',
            'posts_created' => 0,
            'error_message' => 'Request error: ' . $exception->getMessage(),
            'raw_output' => null,
            'posts' => [],
            'errors' => [$exception->getMessage()],
        ];
    }

    $rawForLog = $raw;
    $data = null;

    try {
        $data = ai_parse_json($raw);
    } catch (Throwable $exception) {
        $retryPayload = $payload;
        $retryPayload['prompt'] .= "\nReturn ONLY valid JSON matching the schema. No markdown.";

        try {
            $rawRetry = ai_call_gemini($retryPayload);
            $data = ai_parse_json($rawRetry);
            $rawForLog = $rawRetry;
        } catch (Throwable $retryException) {
            return [
                'status' => 'failed',
                'posts_created' => 0,
                'error_message' => 'Parse error: ' . $retryException->getMessage(),
                'raw_output' => $raw,
                'posts' => [],
                'errors' => [$retryException->getMessage()],
            ];
        }
    }

    try {
        $posts = ai_min_schema_check($data ?? []);
    } catch (Throwable $exception) {
        $schemaRaw = $rawForLog;
        if (is_array($data)) {
            $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded !== false) {
                $schemaRaw = $encoded;
            }
        }

        return [
            'status' => 'failed',
            'posts_created' => 0,
            'error_message' => 'Schema error: ' . $exception->getMessage(),
            'raw_output' => $schemaRaw,
            'posts' => [],
            'errors' => [$exception->getMessage()],
        ];
    }

    $count = 0;
    $errors = [];
    $savedPosts = [];

    foreach ($posts as $post) {
        try {
            if (trim((string) ($post['publish_at_ist'] ?? '')) === '') {
                $post['publish_at_ist'] = ist_today_6am_iso();
            }

            posts_save_or_update($post);
            $savedPosts[] = $post;
            $count++;
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
    }

    $status = 'success';
    $errorMessage = null;

    if ($errors !== []) {
        $status = $count > 0 ? 'partial' : 'failed';
        $errorMessage = implode(' | ', array_unique($errors));
    }

    $rawEncoded = null;
    if (is_array($data)) {
        $rawEncoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($rawEncoded === false) {
            $rawEncoded = null;
        }
    }

    return [
        'status' => $status,
        'posts_created' => $count,
        'error_message' => $errorMessage,
        'raw_output' => $rawEncoded,
        'posts' => $savedPosts,
        'errors' => $errors,
    ];
}

function ai_log_run(string $status, int $count, ?string $errorMsg = null, ?string $rawOutput = null): void
{
    $pdo = ai_get_pdo();
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));

    $stmt = $pdo->prepare(
        'INSERT INTO ai_logs (run_time, status, posts_created, error_message, raw_output)
         VALUES (:run_time, :status, :posts_created, :error_message, :raw_output)'
    );

    $stmt->execute([
        'run_time' => $now->format('Y-m-d H:i:s'),
        'status' => $status,
        'posts_created' => $count,
        'error_message' => $errorMsg !== null ? mb_substr($errorMsg, 0, 2000) : null,
        'raw_output' => $rawOutput,
    ]);
}

/**
 * @return array<int, array<string, mixed>>
 */
function ai_fetch_recent_logs(int $limit = 10): array
{
    try {
        $pdo = ai_get_pdo();
    } catch (\RuntimeException $exception) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT * FROM ai_logs ORDER BY run_time DESC LIMIT :limit');
    $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll() ?: [];
}

/**
 * @return array<string, mixed>|null
 */
function ai_get_last_log(): ?array
{
    $logs = ai_fetch_recent_logs(1);
    return $logs[0] ?? null;
}

function ist_today_6am_iso(): string
{
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
    $sixAm = $now->setTime(6, 0, 0);

    return $sixAm->format(DateTimeInterface::ATOM);
}
