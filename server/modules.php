<?php

declare(strict_types=1);

/**
 * Models Registry helpers
 */
function models_registry_load(): array
{
    $registry = json_read(MODELS_REGISTRY_FILE, ['default_model' => null, 'models' => []]);
    if (!is_array($registry)) {
        $registry = ['default_model' => null, 'models' => []];
    }
    if (!isset($registry['default_model'])) {
        $registry['default_model'] = null;
    }
    if (!isset($registry['models']) || !is_array($registry['models'])) {
        $registry['models'] = [];
    }
    foreach ($registry['models'] as &$model) {
        if (!isset($model['id'])) {
            $model['id'] = uuid('model');
        }
        $model['params'] = $model['params'] ?? [];
        if (!is_array($model['params'])) {
            $model['params'] = [];
        }
        $model['api_key_masked'] = mask_sensitive($model['api_key'] ?? null);
    }
    unset($model);
    return $registry;
}

function models_registry_save(array $registry): bool
{
    foreach ($registry['models'] as &$model) {
        $model['api_key_masked'] = mask_sensitive($model['api_key'] ?? null);
    }
    unset($model);
    return json_write(MODELS_REGISTRY_FILE, $registry);
}

function models_registry_present(array $model): array
{
    return [
        'id' => $model['id'],
        'nickname' => $model['nickname'] ?? '',
        'type' => $model['type'] ?? 'Text',
        'model_code' => $model['model_code'] ?? '',
        'status' => $model['status'] ?? 'inactive',
        'last_tested' => $model['last_tested'] ?? null,
        'params' => is_array($model['params'] ?? null) ? $model['params'] : [],
        'created_at' => $model['created_at'] ?? null,
        'updated_at' => $model['updated_at'] ?? null,
        'api_key_masked' => mask_sensitive($model['api_key'] ?? null),
        'has_api_key' => !empty($model['api_key']),
    ];
}

function models_registry_list(): array
{
    $registry = models_registry_load();
    $models = array_map('models_registry_present', $registry['models']);
    return [
        'default_model' => $registry['default_model'],
        'models' => $models,
    ];
}

function models_registry_find(string $id): ?array
{
    $registry = models_registry_load();
    foreach ($registry['models'] as $model) {
        if ($model['id'] === $id) {
            return $model;
        }
    }
    return null;
}

function models_registry_create(array $payload, string $actor): array
{
    $registry = models_registry_load();
    $nickname = sanitize_string($payload['nickname'] ?? '', 120);
    $type = strtoupper((string) ($payload['type'] ?? 'TEXT'));
    $modelCode = sanitize_string($payload['model_code'] ?? '', 160);
    $status = strtolower((string) ($payload['status'] ?? 'inactive'));
    $params = $payload['params'] ?? [];
    $apiKey = sanitize_string($payload['api_key'] ?? '', 512, true);

    if ($nickname === null || $nickname === '') {
        throw new InvalidArgumentException('Nickname is required.');
    }
    if ($modelCode === null || $modelCode === '') {
        throw new InvalidArgumentException('Model code is required.');
    }
    $validTypes = ['TEXT', 'IMAGE', 'TTS'];
    if (!in_array($type, $validTypes, true)) {
        throw new InvalidArgumentException('Invalid model type.');
    }
    if (!is_array($params)) {
        $params = [];
    }

    $now = now_ist();
    $model = [
        'id' => uuid('model'),
        'nickname' => $nickname,
        'type' => ucfirst(strtolower($type)),
        'model_code' => $modelCode,
        'status' => $status,
        'last_tested' => null,
        'api_key' => $apiKey !== '' ? $apiKey : null,
        'params' => $params,
        'created_at' => $now,
        'updated_at' => $now,
    ];
    $model['api_key_masked'] = mask_sensitive($model['api_key']);

    $registry['models'][] = $model;
    models_registry_save($registry);
    log_activity('models.create', 'Registered model ' . $nickname, $actor);

    return models_registry_present($model);
}

function models_registry_update(string $id, array $payload, string $actor): array
{
    $registry = models_registry_load();
    $updatedModel = null;
    foreach ($registry['models'] as &$model) {
        if ($model['id'] !== $id) {
            continue;
        }
        if (isset($payload['nickname'])) {
            $nickname = sanitize_string($payload['nickname'], 120);
            if ($nickname === null || $nickname === '') {
                throw new InvalidArgumentException('Nickname cannot be empty.');
            }
            $model['nickname'] = $nickname;
        }
        if (isset($payload['type'])) {
            $type = strtoupper((string) $payload['type']);
            if (!in_array($type, ['TEXT', 'IMAGE', 'TTS'], true)) {
                throw new InvalidArgumentException('Invalid model type.');
            }
            $model['type'] = ucfirst(strtolower($type));
        }
        if (isset($payload['model_code'])) {
            $code = sanitize_string($payload['model_code'], 160);
            if ($code === null || $code === '') {
                throw new InvalidArgumentException('Model code cannot be empty.');
            }
            $model['model_code'] = $code;
        }
        if (isset($payload['status'])) {
            $model['status'] = strtolower((string) $payload['status']);
        }
        if (array_key_exists('api_key', $payload)) {
            $apiKey = sanitize_string($payload['api_key'], 512, true);
            $model['api_key'] = $apiKey !== null && $apiKey !== '' ? $apiKey : null;
        }
        if (isset($payload['params'])) {
            $params = $payload['params'];
            if (!is_array($params)) {
                throw new InvalidArgumentException('Params must be an object or array.');
            }
            $model['params'] = $params;
        }
        $model['updated_at'] = now_ist();
        $model['api_key_masked'] = mask_sensitive($model['api_key']);
        $updatedModel = $model;
        break;
    }
    unset($model);

    if ($updatedModel === null) {
        throw new RuntimeException('Model not found.');
    }

    models_registry_save($registry);
    log_activity('models.update', 'Updated model ' . ($updatedModel['nickname'] ?? $id), $actor);

    return models_registry_present($updatedModel);
}

function models_registry_delete(string $id, string $actor): void
{
    $registry = models_registry_load();
    $originalCount = count($registry['models']);
    $registry['models'] = array_values(array_filter($registry['models'], fn ($model) => ($model['id'] ?? '') !== $id));
    if ($originalCount === count($registry['models'])) {
        throw new RuntimeException('Model not found.');
    }
    if ($registry['default_model'] === $id) {
        $registry['default_model'] = null;
    }
    models_registry_save($registry);
    log_activity('models.delete', 'Removed model ' . $id, $actor);
}

function models_registry_set_default(string $id, string $actor): array
{
    $registry = models_registry_load();
    $exists = false;
    foreach ($registry['models'] as $model) {
        if ($model['id'] === $id) {
            $exists = true;
            break;
        }
    }
    if (!$exists) {
        throw new RuntimeException('Model not found.');
    }
    $registry['default_model'] = $id;
    models_registry_save($registry);
    log_activity('models.default', 'Set default model to ' . $id, $actor);
    return models_registry_list();
}

function models_registry_test(string $id, string $actor): array
{
    $registry = models_registry_load();
    $result = null;
    foreach ($registry['models'] as &$model) {
        if ($model['id'] !== $id) {
            continue;
        }
        $hasKey = !empty($model['api_key']);
        $result = $hasKey ? 'pass' : 'fail';
        $model['last_tested'] = now_ist();
        $model['status'] = $result === 'pass' ? 'ready' : 'missing-key';
        $model['updated_at'] = now_ist();
        $model['api_key_masked'] = mask_sensitive($model['api_key']);
        break;
    }
    unset($model);

    if ($result === null) {
        throw new RuntimeException('Model not found.');
    }

    models_registry_save($registry);
    log_activity('models.test', 'Tested model ' . $id . ' result: ' . $result, $actor);

    return ['result' => $result, 'status' => $result === 'pass' ? 'ready' : 'missing-key'];
}

function models_registry_export(bool $includeKeys = false): string
{
    $registry = models_registry_load();
    $export = [
        'default_model' => $registry['default_model'],
        'models' => [],
    ];
    foreach ($registry['models'] as $model) {
        $record = $model;
        if (!$includeKeys) {
            unset($record['api_key']);
        }
        $record['api_key_masked'] = mask_sensitive($model['api_key'] ?? null);
        $export['models'][] = $record;
    }
    return json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

function models_registry_import(array $models, bool $includeKeys, string $actor): array
{
    $registry = models_registry_load();
    $existingByCode = [];
    foreach ($registry['models'] as $model) {
        $existingByCode[$model['model_code']] = $model['id'];
    }
    $created = 0;
    $updated = 0;
    foreach ($models as $incoming) {
        if (!is_array($incoming)) {
            continue;
        }
        $code = sanitize_string($incoming['model_code'] ?? '', 160);
        $nickname = sanitize_string($incoming['nickname'] ?? '', 120);
        $type = strtoupper((string) ($incoming['type'] ?? 'TEXT'));
        if ($code === null || $code === '' || $nickname === null || $nickname === '') {
            continue;
        }
        $payload = [
            'nickname' => $nickname,
            'type' => $type,
            'model_code' => $code,
        ];
        if (array_key_exists('status', $incoming)) {
            $payload['status'] = $incoming['status'];
        }
        if (array_key_exists('params', $incoming)) {
            $payload['params'] = $incoming['params'];
        }
        if ($includeKeys && isset($incoming['api_key'])) {
            $payload['api_key'] = $incoming['api_key'];
        }
        if (isset($existingByCode[$code])) {
            $updated++;
            models_registry_update($existingByCode[$code], $payload, $actor);
        } else {
            $created++;
            $model = models_registry_create($payload, $actor);
            $existingByCode[$code] = $model['id'];
        }
    }
    $summary = "Models import => created: {$created}, updated: {$updated}";
    log_activity('models.import', $summary, $actor);
    return ['created' => $created, 'updated' => $updated];
}

/**
 * AI Blog helpers
 */
function blog_posts_load(): array
{
    $posts = json_read(BLOG_POSTS_FILE, []);
    if (!is_array($posts)) {
        return [];
    }
    return $posts;
}

function blog_posts_save(array $posts): void
{
    json_write(BLOG_POSTS_FILE, $posts);
}

function blog_posts_list(array $filters = []): array
{
    $posts = blog_posts_load();
    $statusFilter = $filters['status'] ?? null;
    $search = strtolower(trim((string) ($filters['search'] ?? '')));
    return array_values(array_filter($posts, function ($post) use ($statusFilter, $search) {
        if ($statusFilter && ($post['status'] ?? '') !== $statusFilter) {
            return false;
        }
        if ($search !== '') {
            $haystack = strtolower(($post['title'] ?? '') . ' ' . ($post['content'] ?? ''));
            return str_contains($haystack, $search);
        }
        return true;
    }));
}

function blog_post_upsert(array $payload, string $actor): array
{
    $posts = blog_posts_load();
    $id = $payload['id'] ?? null;
    $title = sanitize_string($payload['title'] ?? '', 160);
    $content = sanitize_string($payload['content'] ?? '', 64000);
    $tags = $payload['tags'] ?? [];
    if ($title === null || $title === '') {
        throw new InvalidArgumentException('Title is required.');
    }
    if ($content === null || $content === '') {
        throw new InvalidArgumentException('Content is required.');
    }
    if (!is_array($tags)) {
        $tags = [];
    }
    $tags = array_values(array_filter(array_map(fn ($tag) => sanitize_string($tag ?? '', 50), $tags)));
    $now = now_ist();
    $status = $payload['status'] ?? 'draft';
    if ($id) {
        $found = false;
        $updated = null;
        foreach ($posts as &$post) {
            if (($post['id'] ?? '') === $id) {
                $post['title'] = $title;
                $post['content'] = $content;
                $post['tags'] = $tags;
                $post['status'] = $status;
                $post['model_id'] = $payload['model_id'] ?? ($post['model_id'] ?? null);
                $post['updated_at'] = $now;
                $found = true;
                $updated = $post;
                break;
            }
        }
        unset($post);
        if (!$found || $updated === null) {
            throw new RuntimeException('Blog post not found.');
        }
        blog_posts_save($posts);
        log_activity('blog.update', 'Updated blog post ' . $id, $actor);
        return $updated;
    }

    $post = [
        'id' => uuid('blog'),
        'title' => $title,
        'content' => $content,
        'tags' => $tags,
        'status' => $status,
        'model_id' => $payload['model_id'] ?? null,
        'created_at' => $now,
        'updated_at' => $now,
    ];
    $posts[] = $post;
    blog_posts_save($posts);
    log_activity('blog.create', 'Created blog post ' . $post['id'], $actor);
    return $post;
}

function blog_post_publish(string $id, string $actor): array
{
    $posts = blog_posts_load();
    foreach ($posts as &$post) {
        if (($post['id'] ?? '') === $id) {
            $post['status'] = 'published';
            $post['published_at'] = now_ist();
            $post['updated_at'] = now_ist();
            $updated = $post;
            blog_posts_save($posts);
            log_activity('blog.publish', 'Published blog post ' . $id, $actor);
            return $updated;
        }
    }
    throw new RuntimeException('Blog post not found.');
}

function ai_generate_blog(array $payload, string $actor): array
{
    $prompt = sanitize_string($payload['prompt'] ?? '', 4000);
    if ($prompt === null || $prompt === '') {
        throw new InvalidArgumentException('Prompt is required.');
    }
    $modelId = $payload['model_id'] ?? null;
    $model = $modelId ? models_registry_find($modelId) : null;
    $usedMock = !$model || empty($model['api_key']);
    $title = $payload['title'] ?? null;
    if (!$title) {
        $title = mb_strimwidth($prompt, 0, 60, '...');
    }
    $content = "Generated narrative based on prompt:\n\n" . $prompt;
    if ($usedMock) {
        $content .= "\n\n(Mock Gemini response - configure API key for live data.)";
    } else {
        $content .= "\n\n(Content prepared using model " . ($model['nickname'] ?? $model['model_code'] ?? 'model') . ".)";
    }
    $post = blog_post_upsert([
        'title' => $title,
        'content' => $content,
        'status' => 'draft',
        'model_id' => $modelId,
        'tags' => $payload['tags'] ?? [],
    ], $actor);
    $post['used_mock'] = $usedMock;
    return $post;
}

/**
 * AI Image helpers
 */
function ai_image_generate(array $payload, string $actor): array
{
    $prompt = sanitize_string($payload['prompt'] ?? '', 2000);
    if ($prompt === null || $prompt === '') {
        throw new InvalidArgumentException('Prompt is required for image generation.');
    }
    $modelId = $payload['model_id'] ?? null;
    $model = $modelId ? models_registry_find($modelId) : null;
    $usedMock = !$model || empty($model['api_key']);

    ensure_directory(AI_IMAGE_UPLOAD_PATH);
    $filename = 'ai-image-' . uniqid() . '.png';
    $path = AI_IMAGE_UPLOAD_PATH . '/' . $filename;
    $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQI12NgYGBgAAAABQABDQottAAAAABJRU5ErkJggg==', true);
    file_put_contents($path, $pixel, LOCK_EX);

    $record = [
        'id' => uuid('image'),
        'prompt' => $prompt,
        'model_id' => $modelId,
        'file' => 'uploads/ai_images/' . $filename,
        'created_at' => now_ist(),
        'mock' => $usedMock,
    ];
    $images = json_read(AI_IMAGES_FILE, []);
    $images[] = $record;
    json_write(AI_IMAGES_FILE, $images);
    log_activity('ai.image', 'Generated image ' . $record['id'], $actor);
    return $record;
}

function generate_silence_wav(int $durationMs = 1000): string
{
    $sampleRate = 8000;
    $samples = (int) round($sampleRate * ($durationMs / 1000));
    $data = str_repeat(chr(128), $samples);
    $subchunk2Size = strlen($data);
    $chunkSize = 36 + $subchunk2Size;
    $header = 'RIFF'
        . pack('V', $chunkSize)
        . 'WAVEfmt '
        . pack('V', 16)
        . pack('v', 1)
        . pack('v', 1)
        . pack('V', $sampleRate)
        . pack('V', $sampleRate)
        . pack('v', 1)
        . pack('v', 8)
        . 'data'
        . pack('V', $subchunk2Size);
    return $header . $data;
}

function ai_tts_generate(array $payload, string $actor): array
{
    $text = sanitize_string($payload['text'] ?? '', 4000);
    if ($text === null || $text === '') {
        throw new InvalidArgumentException('Input text is required for TTS.');
    }
    $voice = sanitize_string($payload['voice'] ?? 'default', 80) ?? 'default';
    $modelId = $payload['model_id'] ?? null;
    $model = $modelId ? models_registry_find($modelId) : null;
    $usedMock = !$model || empty($model['api_key']);

    ensure_directory(AI_AUDIO_UPLOAD_PATH);
    $filename = 'ai-tts-' . uniqid() . '.wav';
    $path = AI_AUDIO_UPLOAD_PATH . '/' . $filename;
    $audio = generate_silence_wav();
    file_put_contents($path, $audio, LOCK_EX);

    $record = [
        'id' => uuid('tts'),
        'model_id' => $modelId,
        'voice' => $voice,
        'text' => $text,
        'file' => 'uploads/ai_audio/' . $filename,
        'created_at' => now_ist(),
        'mock' => $usedMock,
    ];
    $clips = json_read(AI_TTS_FILE, []);
    $clips[] = $record;
    json_write(AI_TTS_FILE, $clips);
    log_activity('ai.tts', 'Generated TTS clip ' . $record['id'], $actor);
    return $record;
}

/**
 * Potential customers (leads) helpers
 */
function leads_load(): array
{
    $leads = json_read(POTENTIAL_CUSTOMERS_FILE, []);
    return is_array($leads) ? $leads : [];
}

function leads_save(array $leads): void
{
    json_write(POTENTIAL_CUSTOMERS_FILE, $leads);
}

function validate_lead_payload(array $payload): array
{
    $fullName = sanitize_string($payload['full_name'] ?? '', 160);
    $email = sanitize_string($payload['email'] ?? '', 160, true);
    $phone = sanitize_string($payload['phone'] ?? '', 40, true);
    $state = sanitize_string($payload['state'] ?? '', 80, true);
    $source = sanitize_string($payload['source'] ?? 'web', 40, true) ?? 'web';
    $status = sanitize_string($payload['status'] ?? 'new', 40, true) ?? 'new';
    $budget = sanitize_string($payload['budget'] ?? '', 40, true);
    $notes = sanitize_string($payload['notes'] ?? '', 2000, true) ?? '';
    $assignedTo = sanitize_string($payload['assigned_to'] ?? '', 120, true);

    if ($fullName === null || $fullName === '') {
        throw new InvalidArgumentException('Full name is required.');
    }
    if ($email !== null && $email !== '' && !validator_email($email)) {
        throw new InvalidArgumentException('Invalid email address.');
    }

    return [
        'full_name' => $fullName,
        'email' => $email ?: null,
        'phone' => $phone ?: null,
        'state' => $state ?: null,
        'source' => $source,
        'status' => $status,
        'budget' => $budget ?: null,
        'notes' => $notes,
        'assigned_to' => $assignedTo ?: null,
    ];
}

function leads_list(array $filters = []): array
{
    $leads = leads_load();
    $state = strtolower((string) ($filters['state'] ?? ''));
    $status = strtolower((string) ($filters['status'] ?? ''));
    $source = strtolower((string) ($filters['source'] ?? ''));
    $assigned = strtolower((string) ($filters['assigned_to'] ?? ''));
    $search = strtolower(trim((string) ($filters['search'] ?? '')));

    return array_values(array_filter($leads, function ($lead) use ($state, $status, $source, $assigned, $search) {
        if ($state !== '' && strtolower((string) ($lead['state'] ?? '')) !== $state) {
            return false;
        }
        if ($status !== '' && strtolower((string) ($lead['status'] ?? '')) !== $status) {
            return false;
        }
        if ($source !== '' && strtolower((string) ($lead['source'] ?? '')) !== $source) {
            return false;
        }
        if ($assigned !== '' && strtolower((string) ($lead['assigned_to'] ?? '')) !== $assigned) {
            return false;
        }
        if ($search !== '') {
            $haystack = strtolower(($lead['full_name'] ?? '') . ' ' . ($lead['email'] ?? '') . ' ' . ($lead['phone'] ?? ''));
            if (!str_contains($haystack, $search)) {
                return false;
            }
        }
        return true;
    }));
}

function lead_create(array $payload, string $actor): array
{
    $validated = validate_lead_payload($payload);
    $leads = leads_load();
    $lead = array_merge($validated, [
        'id' => uuid('lead'),
        'created_at' => now_ist(),
        'updated_at' => now_ist(),
    ]);
    $leads[] = $lead;
    leads_save($leads);
    log_activity('lead.create', 'Created lead ' . $lead['id'], $actor);
    return $lead;
}

function lead_update(string $id, array $payload, string $actor): array
{
    $validated = validate_lead_payload($payload);
    $leads = leads_load();
    foreach ($leads as &$lead) {
        if (($lead['id'] ?? '') === $id) {
            $lead = array_merge($lead, $validated);
            $lead['updated_at'] = now_ist();
            leads_save($leads);
            log_activity('lead.update', 'Updated lead ' . $id, $actor);
            return $lead;
        }
    }
    throw new RuntimeException('Lead not found.');
}

function lead_delete(string $id, string $actor): void
{
    $leads = leads_load();
    $count = count($leads);
    $leads = array_values(array_filter($leads, fn ($lead) => ($lead['id'] ?? '') !== $id));
    if ($count === count($leads)) {
        throw new RuntimeException('Lead not found.');
    }
    leads_save($leads);
    log_activity('lead.delete', 'Deleted lead ' . $id, $actor);
}

/**
 * Customers helpers with PM Surya Ghar Yojana details
 */
function customers_load(): array
{
    $customers = json_read(DATA_PATH . '/customers.json', []);
    return is_array($customers) ? $customers : [];
}

function customers_save(array $customers): void
{
    json_write(DATA_PATH . '/customers.json', $customers);
}

function validate_customer_payload(array $payload): array
{
    $fullName = sanitize_string($payload['full_name'] ?? '', 160);
    if ($fullName === null || $fullName === '') {
        throw new InvalidArgumentException('Full name is required.');
    }
    $email = sanitize_string($payload['email'] ?? '', 160, true);
    if ($email && !validator_email($email)) {
        throw new InvalidArgumentException('Invalid email.');
    }
    $phone = sanitize_string($payload['phone'] ?? '', 40, true);
    $state = sanitize_string($payload['state'] ?? '', 80);
    if ($state === null || $state === '') {
        throw new InvalidArgumentException('State is required.');
    }
    $district = sanitize_string($payload['district'] ?? '', 120, true);
    $application = sanitize_string($payload['pmsg_application_no'] ?? '', 120);
    if ($application === null || $application === '') {
        throw new InvalidArgumentException('PMSGY application number is required.');
    }
    $subsidyCategory = sanitize_string($payload['subsidy_category'] ?? '', 80, true);
    $sanctionStatus = sanitize_string($payload['sanction_status'] ?? 'pending', 40, true) ?? 'pending';
    $inspectionStatus = sanitize_string($payload['inspection_status'] ?? 'scheduled', 40, true) ?? 'scheduled';
    $disbursementStatus = sanitize_string($payload['disbursement_status'] ?? 'pending', 40, true) ?? 'pending';
    $capacity = (float) ($payload['installation_capacity_kw'] ?? 0);
    $subsidyAmount = (float) ($payload['subsidy_amount'] ?? 0);
    $documentsVerified = normalize_bool($payload['documents_verified'] ?? false);
    $notes = sanitize_string($payload['notes'] ?? '', 4000, true) ?? '';

    return [
        'full_name' => $fullName,
        'email' => $email ?: null,
        'phone' => $phone ?: null,
        'state' => $state,
        'district' => $district ?: null,
        'pmsg_application_no' => $application,
        'subsidy_category' => $subsidyCategory ?: null,
        'sanction_status' => $sanctionStatus,
        'inspection_status' => $inspectionStatus,
        'disbursement_status' => $disbursementStatus,
        'installation_capacity_kw' => $capacity,
        'subsidy_amount' => $subsidyAmount,
        'documents_verified' => $documentsVerified,
        'notes' => $notes,
    ];
}

function customers_list(array $filters = []): array
{
    $customers = customers_load();
    $state = strtolower((string) ($filters['state'] ?? ''));
    $sanction = strtolower((string) ($filters['sanction_status'] ?? ''));
    $disbursement = strtolower((string) ($filters['disbursement_status'] ?? ''));
    $verified = $filters['documents_verified'] ?? null;
    if ($verified === '' || $verified === null) {
        $verified = null;
    }
    $search = strtolower(trim((string) ($filters['search'] ?? '')));

    return array_values(array_filter($customers, function ($customer) use ($state, $sanction, $disbursement, $verified, $search) {
        if ($state !== '' && strtolower((string) ($customer['state'] ?? '')) !== $state) {
            return false;
        }
        if ($sanction !== '' && strtolower((string) ($customer['sanction_status'] ?? '')) !== $sanction) {
            return false;
        }
        if ($disbursement !== '' && strtolower((string) ($customer['disbursement_status'] ?? '')) !== $disbursement) {
            return false;
        }
        if ($verified !== null) {
            $expected = normalize_bool($verified);
            if ((bool) ($customer['documents_verified'] ?? false) !== $expected) {
                return false;
            }
        }
        if ($search !== '') {
            $haystack = strtolower(($customer['full_name'] ?? '') . ' ' . ($customer['pmsg_application_no'] ?? '') . ' ' . ($customer['email'] ?? ''));
            if (!str_contains($haystack, $search)) {
                return false;
            }
        }
        return true;
    }));
}

function customer_create(array $payload, string $actor): array
{
    $validated = validate_customer_payload($payload);
    $customers = customers_load();
    $customer = array_merge($validated, [
        'id' => uuid('cust'),
        'created_at' => now_ist(),
        'updated_at' => now_ist(),
        'converted_from_lead' => $payload['converted_from_lead'] ?? null,
    ]);
    $customers[] = $customer;
    customers_save($customers);
    log_activity('customer.create', 'Created customer ' . $customer['id'], $actor);
    return $customer;
}

function customer_update(string $id, array $payload, string $actor): array
{
    $validated = validate_customer_payload($payload);
    $customers = customers_load();
    foreach ($customers as &$customer) {
        if (($customer['id'] ?? '') === $id) {
            $customer = array_merge($customer, $validated);
            $customer['updated_at'] = now_ist();
            customers_save($customers);
            log_activity('customer.update', 'Updated customer ' . $id, $actor);
            return $customer;
        }
    }
    throw new RuntimeException('Customer not found.');
}

function customer_delete(string $id, string $actor): void
{
    $customers = customers_load();
    $count = count($customers);
    $customers = array_values(array_filter($customers, fn ($customer) => ($customer['id'] ?? '') !== $id));
    if ($count === count($customers)) {
        throw new RuntimeException('Customer not found.');
    }
    customers_save($customers);
    log_activity('customer.delete', 'Deleted customer ' . $id, $actor);
}

function lead_convert_to_customer(string $id, array $payload, string $actor): array
{
    $leads = leads_load();
    $lead = null;
    $leadIndex = null;
    foreach ($leads as $index => $candidate) {
        if (($candidate['id'] ?? '') === $id) {
            $lead = $candidate;
            $leadIndex = $index;
            break;
        }
    }
    if ($lead === null) {
        throw new RuntimeException('Lead not found.');
    }

    $customerPayload = array_merge([
        'full_name' => $lead['full_name'] ?? '',
        'email' => $lead['email'] ?? null,
        'phone' => $lead['phone'] ?? null,
        'state' => $payload['state'] ?? ($lead['state'] ?? ''),
        'district' => $payload['district'] ?? null,
        'notes' => $payload['notes'] ?? ($lead['notes'] ?? ''),
    ], $payload);
    $customerPayload['converted_from_lead'] = $id;

    $customer = customer_create($customerPayload, $actor);
    if ($leadIndex !== null) {
        unset($leads[$leadIndex]);
        leads_save(array_values($leads));
    }
    log_activity('lead.convert', 'Converted lead ' . $id . ' to customer ' . $customer['id'], $actor);
    return $customer;
}

/**
 * Bulk import/export helpers
 */
function leads_export_csv(array $filters = []): string
{
    $leads = leads_list($filters);
    $headers = ['id', 'full_name', 'email', 'phone', 'state', 'source', 'status', 'budget', 'notes', 'assigned_to', 'created_at', 'updated_at'];
    if (!$leads) {
        return csv_encode([], $headers);
    }
    return csv_encode($leads, $headers);
}

function customers_export_csv(array $filters = []): string
{
    $customers = customers_list($filters);
    $headers = ['id', 'full_name', 'email', 'phone', 'state', 'district', 'pmsg_application_no', 'subsidy_category', 'sanction_status', 'inspection_status', 'disbursement_status', 'installation_capacity_kw', 'subsidy_amount', 'documents_verified', 'notes', 'created_at', 'updated_at'];
    if (!$customers) {
        return csv_encode([], $headers);
    }
    return csv_encode($customers, $headers);
}

function leads_import_csv(string $csv, string $duplicateStrategy, string $actor): array
{
    $rows = csv_decode($csv);
    $existing = leads_load();
    $indexByEmail = [];
    foreach ($existing as $idx => $lead) {
        $email = strtolower((string) ($lead['email'] ?? ''));
        if ($email !== '') {
            $indexByEmail[$email] = $idx;
        }
    }
    $created = 0;
    $updated = 0;
    $skipped = 0;
    $errors = [];

    foreach ($rows as $row) {
        try {
            $validated = validate_lead_payload($row);
        } catch (Throwable $exception) {
            $row['error'] = $exception->getMessage();
            $errors[] = $row;
            continue;
        }
        $emailKey = strtolower((string) ($validated['email'] ?? ''));
        $now = now_ist();
        if ($emailKey !== '' && isset($indexByEmail[$emailKey])) {
            if ($duplicateStrategy === 'skip') {
                $skipped++;
                continue;
            }
            if ($duplicateStrategy === 'update') {
                $idx = $indexByEmail[$emailKey];
                $existing[$idx] = array_merge($existing[$idx], $validated);
                $existing[$idx]['updated_at'] = $now;
                $updated++;
                continue;
            }
        }
        $lead = array_merge($validated, [
            'id' => uuid('lead'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $existing[] = $lead;
        if ($emailKey !== '') {
            $indexByEmail[$emailKey] = array_key_last($existing);
        }
        $created++;
    }

    leads_save($existing);
    $summary = "Leads import => created: {$created}, updated: {$updated}, skipped: {$skipped}, errors: " . count($errors);
    log_activity('lead.import', $summary, $actor);

    return [
        'created' => $created,
        'updated' => $updated,
        'skipped' => $skipped,
        'errors' => $errors,
        'error_csv' => $errors ? base64_encode(csv_encode($errors)) : null,
    ];
}

function customers_import_csv(string $csv, string $duplicateStrategy, string $actor): array
{
    $rows = csv_decode($csv);
    $existing = customers_load();
    $indexByApplication = [];
    foreach ($existing as $idx => $customer) {
        $key = strtolower((string) ($customer['pmsg_application_no'] ?? ''));
        if ($key !== '') {
            $indexByApplication[$key] = $idx;
        }
    }
    $created = 0;
    $updated = 0;
    $skipped = 0;
    $errors = [];

    foreach ($rows as $row) {
        try {
            $validated = validate_customer_payload($row);
        } catch (Throwable $exception) {
            $row['error'] = $exception->getMessage();
            $errors[] = $row;
            continue;
        }
        $key = strtolower($validated['pmsg_application_no']);
        $now = now_ist();
        if ($key !== '' && isset($indexByApplication[$key])) {
            if ($duplicateStrategy === 'skip') {
                $skipped++;
                continue;
            }
            if ($duplicateStrategy === 'update') {
                $idx = $indexByApplication[$key];
                $existing[$idx] = array_merge($existing[$idx], $validated);
                $existing[$idx]['updated_at'] = $now;
                $updated++;
                continue;
            }
        }
        $customer = array_merge($validated, [
            'id' => uuid('cust'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $existing[] = $customer;
        $indexByApplication[$key] = array_key_last($existing);
        $created++;
    }

    customers_save($existing);
    $summary = "Customers import => created: {$created}, updated: {$updated}, skipped: {$skipped}, errors: " . count($errors);
    log_activity('customer.import', $summary, $actor);

    return [
        'created' => $created,
        'updated' => $updated,
        'skipped' => $skipped,
        'errors' => $errors,
        'error_csv' => $errors ? base64_encode(csv_encode($errors)) : null,
    ];
}

/**
 * Referrer registry helpers
 */
function referrers_load(): array
{
    $referrers = json_read(REFERRERS_FILE, []);
    return is_array($referrers) ? $referrers : [];
}

function referrers_save(array $referrers): void
{
    foreach ($referrers as &$referrer) {
        $referrer['bank_account_masked'] = mask_sensitive($referrer['bank_account'] ?? null);
    }
    unset($referrer);
    json_write(REFERRERS_FILE, $referrers);
}

function validate_referrer_payload(array $payload): array
{
    $name = sanitize_string($payload['name'] ?? '', 160);
    if ($name === null || $name === '') {
        throw new InvalidArgumentException('Name is required.');
    }
    $kycStatus = sanitize_string($payload['kyc_status'] ?? 'pending', 40, true) ?? 'pending';
    $gstin = sanitize_string($payload['gstin'] ?? '', 40, true);
    $pan = sanitize_string($payload['pan'] ?? '', 20, true);
    $bankAccount = sanitize_string($payload['bank_account'] ?? '', 40, true);
    $ifsc = sanitize_string($payload['ifsc'] ?? '', 20, true);
    $upi = sanitize_string($payload['upi_id'] ?? '', 80, true);
    $email = sanitize_string($payload['contact_email'] ?? '', 160, true);
    if ($email && !validator_email($email)) {
        throw new InvalidArgumentException('Invalid contact email.');
    }
    $phone = sanitize_string($payload['contact_phone'] ?? '', 30, true);
    $address = sanitize_string($payload['address'] ?? '', 400, true) ?? '';
    $notes = sanitize_string($payload['notes'] ?? '', 4000, true) ?? '';

    return [
        'name' => $name,
        'kyc_status' => $kycStatus,
        'gstin' => $gstin ?: null,
        'pan' => $pan ?: null,
        'bank_account' => $bankAccount ?: null,
        'ifsc' => $ifsc ?: null,
        'upi_id' => $upi ?: null,
        'contact_email' => $email ?: null,
        'contact_phone' => $phone ?: null,
        'address' => $address,
        'notes' => $notes,
    ];
}

function referrer_present(array $referrer): array
{
    return [
        'id' => $referrer['id'] ?? null,
        'name' => $referrer['name'] ?? '',
        'kyc_status' => $referrer['kyc_status'] ?? 'pending',
        'gstin' => $referrer['gstin'] ?? null,
        'pan' => $referrer['pan'] ?? null,
        'bank_account_masked' => $referrer['bank_account_masked'] ?? mask_sensitive($referrer['bank_account'] ?? null),
        'has_bank_account' => !empty($referrer['bank_account']),
        'ifsc' => $referrer['ifsc'] ?? null,
        'upi_id' => $referrer['upi_id'] ?? null,
        'contact_email' => $referrer['contact_email'] ?? null,
        'contact_phone' => $referrer['contact_phone'] ?? null,
        'address' => $referrer['address'] ?? '',
        'notes' => $referrer['notes'] ?? '',
        'created_at' => $referrer['created_at'] ?? null,
        'updated_at' => $referrer['updated_at'] ?? null,
    ];
}

function referrers_list(): array
{
    $referrers = referrers_load();
    return array_map('referrer_present', $referrers);
}

function referrer_create(array $payload, string $actor): array
{
    $validated = validate_referrer_payload($payload);
    $referrers = referrers_load();
    $record = array_merge($validated, [
        'id' => uuid('ref'),
        'created_at' => now_ist(),
        'updated_at' => now_ist(),
    ]);
    $referrers[] = $record;
    referrers_save($referrers);
    log_activity('referrer.create', 'Added referrer ' . $record['id'], $actor);
    return referrer_present($record);
}

function referrer_update(string $id, array $payload, string $actor): array
{
    $validated = validate_referrer_payload($payload);
    $referrers = referrers_load();
    foreach ($referrers as &$referrer) {
        if (($referrer['id'] ?? '') === $id) {
            $referrer = array_merge($referrer, $validated);
            $referrer['updated_at'] = now_ist();
            referrers_save($referrers);
            log_activity('referrer.update', 'Updated referrer ' . $id, $actor);
            return referrer_present($referrer);
        }
    }
    throw new RuntimeException('Referrer not found.');
}

function referrer_delete(string $id, string $actor): void
{
    $referrers = referrers_load();
    $count = count($referrers);
    $referrers = array_values(array_filter($referrers, fn ($referrer) => ($referrer['id'] ?? '') !== $id));
    if ($count === count($referrers)) {
        throw new RuntimeException('Referrer not found.');
    }
    referrers_save($referrers);
    log_activity('referrer.delete', 'Removed referrer ' . $id, $actor);
}

function referrers_export_csv(): string
{
    $referrers = array_map(function ($referrer) {
        if (!is_array($referrer)) {
            return $referrer;
        }
        $referrer['bank_account_masked'] = $referrer['bank_account_masked'] ?? mask_sensitive($referrer['bank_account'] ?? null);
        unset($referrer['bank_account']);
        return $referrer;
    }, referrers_load());
    $headers = ['id', 'name', 'kyc_status', 'gstin', 'pan', 'bank_account_masked', 'ifsc', 'upi_id', 'contact_email', 'contact_phone', 'address', 'notes', 'created_at', 'updated_at'];
    if (!$referrers) {
        return csv_encode([], $headers);
    }
    return csv_encode($referrers, $headers);
}

/**
 * Ticketing and complaints helpers
 */
function ticket_allowed_statuses(): array
{
    return ['open', 'in_progress', 'on_hold', 'resolved', 'closed'];
}

function ticket_allowed_priorities(): array
{
    return ['low', 'medium', 'high', 'urgent'];
}

function ticket_normalize(array $ticket): array
{
    $ticket['notes'] = array_values(array_filter(is_array($ticket['notes'] ?? null) ? $ticket['notes'] : [], 'is_array'));
    foreach ($ticket['notes'] as &$note) {
        $note['id'] = $note['id'] ?? uuid('tn');
        $note['body'] = (string) ($note['body'] ?? '');
        $note['author'] = $note['author'] ?? 'system';
        $note['visibility'] = $note['visibility'] ?? 'internal';
        $note['created_at'] = $note['created_at'] ?? now_ist();
    }
    unset($note);

    $ticket['history'] = array_values(array_filter(is_array($ticket['history'] ?? null) ? $ticket['history'] : [], 'is_array'));
    foreach ($ticket['history'] as &$entry) {
        $entry['id'] = $entry['id'] ?? uuid('th');
        $entry['event'] = $entry['event'] ?? 'update';
        $entry['actor'] = $entry['actor'] ?? 'system';
        $entry['recorded_at'] = $entry['recorded_at'] ?? now_ist();
        if (!isset($entry['data']) || !is_array($entry['data'])) {
            $entry['data'] = [];
        }
    }
    unset($entry);

    $ticket['status'] = strtolower((string) ($ticket['status'] ?? 'open'));
    if (!in_array($ticket['status'], ticket_allowed_statuses(), true)) {
        $ticket['status'] = 'open';
    }

    $ticket['priority'] = strtolower((string) ($ticket['priority'] ?? 'medium'));
    if (!in_array($ticket['priority'], ticket_allowed_priorities(), true)) {
        $ticket['priority'] = 'medium';
    }

    if (isset($ticket['tags']) && is_array($ticket['tags'])) {
        $ticket['tags'] = array_values(array_filter(array_unique(array_map(function ($tag) {
            return strtolower(trim((string) $tag));
        }, $ticket['tags'])), static function ($tag) {
            return $tag !== '';
        }));
    } else {
        $ticket['tags'] = [];
    }

    if (!isset($ticket['metadata']) || !is_array($ticket['metadata'])) {
        $ticket['metadata'] = [];
    }

    return $ticket;
}

function ticket_present(array $ticket): array
{
    $ticket = ticket_normalize($ticket);
    return [
        'id' => $ticket['id'] ?? null,
        'subject' => $ticket['subject'] ?? '',
        'description' => $ticket['description'] ?? '',
        'status' => $ticket['status'],
        'priority' => $ticket['priority'],
        'assignee' => $ticket['assignee'] ?? null,
        'customer_id' => $ticket['customer_id'] ?? null,
        'customer_name' => $ticket['customer_name'] ?? null,
        'customer_phone' => $ticket['customer_phone'] ?? null,
        'customer_email' => $ticket['customer_email'] ?? null,
        'channel' => $ticket['channel'] ?? null,
        'due_date' => $ticket['due_date'] ?? null,
        'tags' => $ticket['tags'],
        'metadata' => $ticket['metadata'],
        'notes' => $ticket['notes'],
        'history' => $ticket['history'],
        'created_at' => $ticket['created_at'] ?? null,
        'updated_at' => $ticket['updated_at'] ?? null,
        'closed_at' => $ticket['closed_at'] ?? null,
    ];
}

function tickets_load(): array
{
    $tickets = json_read(TICKETS_FILE, []);
    if (!is_array($tickets)) {
        return [];
    }
    return array_map('ticket_normalize', $tickets);
}

function tickets_save(array $tickets): void
{
    $normalised = array_map('ticket_normalize', $tickets);
    json_write(TICKETS_FILE, array_values($normalised));
}

function ticket_append_history(array &$ticket, string $event, array $data, string $actor): void
{
    if (!isset($ticket['history']) || !is_array($ticket['history'])) {
        $ticket['history'] = [];
    }
    $ticket['history'][] = [
        'id' => uuid('th'),
        'event' => $event,
        'data' => $data,
        'actor' => $actor,
        'recorded_at' => now_ist(),
    ];
}

function validate_ticket_payload(array $payload, bool $partial = false): array
{
    $subject = sanitize_string($payload['subject'] ?? '', 180);
    if (!$partial && ($subject === null || $subject === '')) {
        throw new InvalidArgumentException('Ticket subject is required.');
    }

    $description = sanitize_string($payload['description'] ?? '', 5000, true);
    $status = sanitize_string($payload['status'] ?? '', 40, true);
    if ($status !== null && $status !== '' && !in_array(strtolower($status), ticket_allowed_statuses(), true)) {
        throw new InvalidArgumentException('Invalid ticket status provided.');
    }

    $priority = sanitize_string($payload['priority'] ?? '', 40, true);
    if ($priority !== null && $priority !== '' && !in_array(strtolower($priority), ticket_allowed_priorities(), true)) {
        throw new InvalidArgumentException('Invalid ticket priority provided.');
    }

    $assignee = sanitize_string($payload['assignee'] ?? '', 160, true);
    $customerId = sanitize_string($payload['customer_id'] ?? '', 160, true);
    $customerName = sanitize_string($payload['customer_name'] ?? '', 160, true);
    $customerPhone = sanitize_string($payload['customer_phone'] ?? '', 40, true);
    $customerEmail = sanitize_string($payload['customer_email'] ?? '', 160, true);
    if ($customerEmail && !validator_email($customerEmail)) {
        throw new InvalidArgumentException('Customer email is invalid.');
    }

    $channel = sanitize_string($payload['channel'] ?? '', 80, true);
    $dueDate = sanitize_string($payload['due_date'] ?? '', 20, true);
    if ($dueDate) {
        if (!validator_date($dueDate)) {
            throw new InvalidArgumentException('Due date must use YYYY-MM-DD format.');
        }
    }

    $tags = [];
    if (isset($payload['tags'])) {
        if (!is_array($payload['tags'])) {
            throw new InvalidArgumentException('Tags must be an array of strings.');
        }
        foreach ($payload['tags'] as $tag) {
            $clean = sanitize_string($tag, 60, true);
            if ($clean) {
                $tags[] = strtolower($clean);
            }
        }
    }

    $metadata = [];
    if (isset($payload['metadata'])) {
        if (is_array($payload['metadata'])) {
            $metadata = $payload['metadata'];
        } else {
            throw new InvalidArgumentException('Metadata must be an object.');
        }
    }

    $validated = [];
    if ($subject !== null) {
        $validated['subject'] = $subject;
    }
    if ($description !== null) {
        $validated['description'] = $description ?? '';
    }
    if ($status) {
        $validated['status'] = strtolower($status);
    }
    if ($priority) {
        $validated['priority'] = strtolower($priority);
    }
    if ($assignee !== null) {
        $validated['assignee'] = $assignee ?: null;
    }
    if ($customerId !== null) {
        $validated['customer_id'] = $customerId ?: null;
    }
    if ($customerName !== null) {
        $validated['customer_name'] = $customerName ?: null;
    }
    if ($customerPhone !== null) {
        $validated['customer_phone'] = $customerPhone ?: null;
    }
    if ($customerEmail !== null) {
        $validated['customer_email'] = $customerEmail ?: null;
    }
    if ($channel !== null) {
        $validated['channel'] = $channel ?: null;
    }
    if ($dueDate !== null) {
        $validated['due_date'] = $dueDate ?: null;
    }
    if ($tags) {
        $validated['tags'] = array_values(array_unique($tags));
    }
    if ($metadata) {
        $validated['metadata'] = $metadata;
    }

    return $validated;
}

function tickets_list(array $filters = []): array
{
    $tickets = tickets_load();
    $status = strtolower((string) ($filters['status'] ?? ''));
    $priority = strtolower((string) ($filters['priority'] ?? ''));
    $assignee = strtolower((string) ($filters['assignee'] ?? ''));
    $customerId = strtolower((string) ($filters['customer_id'] ?? ''));
    $search = strtolower(trim((string) ($filters['search'] ?? '')));
    $tag = strtolower((string) ($filters['tag'] ?? ''));

    $filtered = array_filter($tickets, static function ($ticket) use ($status, $priority, $assignee, $customerId, $search, $tag) {
        $ticket = ticket_normalize($ticket);
        if ($status !== '' && strtolower((string) ($ticket['status'] ?? '')) !== $status) {
            return false;
        }
        if ($priority !== '' && strtolower((string) ($ticket['priority'] ?? '')) !== $priority) {
            return false;
        }
        if ($assignee !== '' && strtolower((string) ($ticket['assignee'] ?? '')) !== $assignee) {
            return false;
        }
        if ($customerId !== '' && strtolower((string) ($ticket['customer_id'] ?? '')) !== $customerId) {
            return false;
        }
        if ($tag !== '' && !in_array($tag, $ticket['tags'] ?? [], true)) {
            return false;
        }
        if ($search !== '') {
            $haystack = strtolower(($ticket['subject'] ?? '') . ' ' . ($ticket['description'] ?? '') . ' ' . ($ticket['customer_name'] ?? '') . ' ' . ($ticket['customer_phone'] ?? ''));
            if (!str_contains($haystack, $search)) {
                return false;
            }
        }
        return true;
    });

    return array_values(array_map('ticket_present', $filtered));
}

function ticket_create(array $payload, string $actor): array
{
    $validated = validate_ticket_payload($payload);
    $settings = load_site_settings();
    $defaults = $settings['Complaints'] ?? [];

    if (!isset($validated['priority']) && isset($defaults['default_priority'])) {
        $validated['priority'] = strtolower((string) $defaults['default_priority']);
    }
    if (!isset($validated['assignee']) && !empty($defaults['auto_assign_to'])) {
        $validated['assignee'] = $defaults['auto_assign_to'];
    }

    $tickets = tickets_load();
    $ticket = array_merge([
        'id' => uuid('ticket'),
        'status' => 'open',
        'priority' => 'medium',
        'notes' => [],
        'history' => [],
        'tags' => [],
        'metadata' => [],
        'created_at' => now_ist(),
        'updated_at' => now_ist(),
    ], $validated);

    ticket_append_history($ticket, 'created', ['subject' => $ticket['subject'] ?? ''], $actor);

    $tickets[] = $ticket;
    tickets_save($tickets);
    log_activity('ticket.create', 'Created ticket ' . ($ticket['id'] ?? ''), $actor);

    return ticket_present($ticket);
}

function ticket_update(string $id, array $payload, string $actor): array
{
    $validated = validate_ticket_payload($payload, true);
    $tickets = tickets_load();
    foreach ($tickets as &$ticket) {
        if (($ticket['id'] ?? '') !== $id) {
            continue;
        }
        $original = ticket_normalize($ticket);
        $ticket = array_merge($ticket, $validated);
        $ticket['updated_at'] = now_ist();

        if (($validated['status'] ?? null) && $validated['status'] !== $original['status']) {
            if ($validated['status'] === 'resolved' || $validated['status'] === 'closed') {
                $ticket['closed_at'] = $ticket['closed_at'] ?? now_ist();
            }
            ticket_append_history($ticket, 'status_change', ['from' => $original['status'], 'to' => $validated['status']], $actor);
        }
        if (($validated['assignee'] ?? null) !== null && ($validated['assignee'] ?? null) !== ($original['assignee'] ?? null)) {
            ticket_append_history($ticket, 'assignee_change', ['from' => $original['assignee'] ?? null, 'to' => $validated['assignee']], $actor);
        }
        if (($validated['priority'] ?? null) && $validated['priority'] !== $original['priority']) {
            ticket_append_history($ticket, 'priority_change', ['from' => $original['priority'], 'to' => $validated['priority']], $actor);
        }

        tickets_save($tickets);
        log_activity('ticket.update', 'Updated ticket ' . $id, $actor);
        return ticket_present($ticket);
    }
    unset($ticket);

    throw new RuntimeException('Ticket not found.');
}

function ticket_delete(string $id, string $actor): void
{
    $tickets = tickets_load();
    $before = count($tickets);
    $tickets = array_values(array_filter($tickets, static function ($ticket) use ($id) {
        return ($ticket['id'] ?? '') !== $id;
    }));

    if ($before === count($tickets)) {
        throw new RuntimeException('Ticket not found.');
    }

    tickets_save($tickets);
    log_activity('ticket.delete', 'Deleted ticket ' . $id, $actor);
}

function ticket_add_note(string $ticketId, array $payload, string $actor): array
{
    $noteBody = sanitize_string($payload['body'] ?? '', 4000);
    if ($noteBody === null || $noteBody === '') {
        throw new InvalidArgumentException('Note body cannot be empty.');
    }
    $visibility = strtolower((string) sanitize_string($payload['visibility'] ?? 'internal', 40, true));
    if (!in_array($visibility, ['internal', 'external'], true)) {
        $visibility = 'internal';
    }

    $tickets = tickets_load();
    foreach ($tickets as &$ticket) {
        if (($ticket['id'] ?? '') !== $ticketId) {
            continue;
        }
        $ticket = ticket_normalize($ticket);
        $note = [
            'id' => uuid('tn'),
            'body' => $noteBody,
            'author' => $actor,
            'visibility' => $visibility,
            'created_at' => now_ist(),
        ];
        $ticket['notes'][] = $note;
        $ticket['updated_at'] = now_ist();
        ticket_append_history($ticket, 'note_added', ['visibility' => $visibility], $actor);

        // Propagate to communication log for unified timeline
        communication_log_add([
            'customer_id' => $ticket['customer_id'] ?? null,
            'ticket_id' => $ticketId,
            'channel' => $visibility === 'external' ? 'customer-update' : 'internal-note',
            'summary' => mb_substr($noteBody, 0, 240),
            'details' => $noteBody,
        ], $actor);

        $tickets = array_map('ticket_normalize', $tickets);
        tickets_save($tickets);
        log_activity('ticket.note', 'Added note to ticket ' . $ticketId, $actor);
        return ticket_present($ticket);
    }
    unset($ticket);

    throw new RuntimeException('Ticket not found.');
}

/**
 * Warranty and AMC tracker helpers
 */
function warranty_registry_load(): array
{
    $data = json_read(WARRANTY_AMC_FILE, ['assets' => []]);
    if (!is_array($data)) {
        return ['assets' => []];
    }
    if (!isset($data['assets']) || !is_array($data['assets'])) {
        $data['assets'] = [];
    }
    $data['assets'] = array_values(array_map(static function ($asset) {
        if (!is_array($asset)) {
            return [];
        }
        if (!isset($asset['service_visits']) || !is_array($asset['service_visits'])) {
            $asset['service_visits'] = [];
        }
        if (!isset($asset['reminders']) || !is_array($asset['reminders'])) {
            $asset['reminders'] = [];
        }
        if (!isset($asset['geo_photos']) || !is_array($asset['geo_photos'])) {
            $asset['geo_photos'] = [];
        }
        return $asset;
    }, $data['assets']));
    return $data;
}

function warranty_registry_save(array $data): void
{
    if (!isset($data['assets']) || !is_array($data['assets'])) {
        $data['assets'] = [];
    }
    json_write(WARRANTY_AMC_FILE, ['assets' => array_values($data['assets'])]);
}

function warranty_asset_present(array $asset): array
{
    return [
        'id' => $asset['id'] ?? null,
        'customer_id' => $asset['customer_id'] ?? null,
        'customer_name' => $asset['customer_name'] ?? null,
        'segment' => $asset['segment'] ?? null,
        'asset_type' => $asset['asset_type'] ?? null,
        'serial_number' => $asset['serial_number'] ?? null,
        'installation_date' => $asset['installation_date'] ?? null,
        'warranty_expiry' => $asset['warranty_expiry'] ?? null,
        'amc_expiry' => $asset['amc_expiry'] ?? null,
        'location' => $asset['location'] ?? null,
        'pincode' => $asset['pincode'] ?? null,
        'latitude' => $asset['latitude'] ?? null,
        'longitude' => $asset['longitude'] ?? null,
        'capacity_kw' => $asset['capacity_kw'] ?? null,
        'notes' => $asset['notes'] ?? '',
        'service_visits' => $asset['service_visits'] ?? [],
        'reminders' => $asset['reminders'] ?? [],
        'geo_photos' => $asset['geo_photos'] ?? [],
        'documents' => $asset['documents'] ?? [],
        'created_at' => $asset['created_at'] ?? null,
        'updated_at' => $asset['updated_at'] ?? null,
    ];
}

function validate_warranty_asset_payload(array $payload, bool $partial = false): array
{
    $customerId = sanitize_string($payload['customer_id'] ?? '', 160, true);
    $customerName = sanitize_string($payload['customer_name'] ?? '', 160, true);
    if (!$partial && ($customerName === null || $customerName === '')) {
        throw new InvalidArgumentException('Customer name is required for warranty assets.');
    }

    $segment = sanitize_string($payload['segment'] ?? '', 80, true);
    $assetType = sanitize_string($payload['asset_type'] ?? '', 120, true);
    if (!$partial && ($assetType === null || $assetType === '')) {
        throw new InvalidArgumentException('Asset type is required.');
    }
    $serialNumber = sanitize_string($payload['serial_number'] ?? '', 160, true);
    $installationDate = sanitize_string($payload['installation_date'] ?? '', 20, true);
    if ($installationDate && !validator_date($installationDate)) {
        throw new InvalidArgumentException('Installation date must use YYYY-MM-DD format.');
    }
    $warrantyExpiry = sanitize_string($payload['warranty_expiry'] ?? '', 20, true);
    if ($warrantyExpiry && !validator_date($warrantyExpiry)) {
        throw new InvalidArgumentException('Warranty expiry must use YYYY-MM-DD format.');
    }
    $amcExpiry = sanitize_string($payload['amc_expiry'] ?? '', 20, true);
    if ($amcExpiry && !validator_date($amcExpiry)) {
        throw new InvalidArgumentException('AMC expiry must use YYYY-MM-DD format.');
    }

    $location = sanitize_string($payload['location'] ?? '', 240, true);
    $pincode = sanitize_string($payload['pincode'] ?? '', 20, true);
    if ($pincode) {
        $settings = load_site_settings();
        $pattern = $settings['Data Quality']['pincode_regex'] ?? null;
        if (!validator_pincode($pincode, is_string($pattern) ? $pattern : null)) {
            throw new InvalidArgumentException('Pincode must be six digits.');
        }
    }

    $latitude = isset($payload['latitude']) ? (float) $payload['latitude'] : null;
    $longitude = isset($payload['longitude']) ? (float) $payload['longitude'] : null;
    $capacity = isset($payload['capacity_kw']) ? (float) $payload['capacity_kw'] : null;
    $notes = sanitize_string($payload['notes'] ?? '', 4000, true);

    $documents = [];
    if (isset($payload['documents'])) {
        if (!is_array($payload['documents'])) {
            throw new InvalidArgumentException('Documents must be an array.');
        }
        $documents = array_values($payload['documents']);
    }

    $validated = [];
    if ($customerId !== null) {
        $validated['customer_id'] = $customerId ?: null;
    }
    if ($customerName !== null) {
        $validated['customer_name'] = $customerName ?: null;
    }
    if ($segment !== null) {
        $validated['segment'] = $segment ?: null;
    }
    if ($assetType !== null) {
        $validated['asset_type'] = $assetType ?: null;
    }
    if ($serialNumber !== null) {
        $validated['serial_number'] = $serialNumber ?: null;
    }
    if ($installationDate !== null) {
        $validated['installation_date'] = $installationDate ?: null;
    }
    if ($warrantyExpiry !== null) {
        $validated['warranty_expiry'] = $warrantyExpiry ?: null;
    }
    if ($amcExpiry !== null) {
        $validated['amc_expiry'] = $amcExpiry ?: null;
    }
    if ($location !== null) {
        $validated['location'] = $location ?: null;
    }
    if ($pincode !== null) {
        $validated['pincode'] = $pincode ?: null;
    }
    if ($latitude !== null) {
        $validated['latitude'] = $latitude;
    }
    if ($longitude !== null) {
        $validated['longitude'] = $longitude;
    }
    if ($capacity !== null) {
        $validated['capacity_kw'] = $capacity;
    }
    if ($notes !== null) {
        $validated['notes'] = $notes ?? '';
    }
    if ($documents) {
        $validated['documents'] = $documents;
    }

    return $validated;
}

function warranty_assets_list(array $filters = []): array
{
    $registry = warranty_registry_load();
    $customerId = strtolower((string) ($filters['customer_id'] ?? ''));
    $segment = strtolower((string) ($filters['segment'] ?? ''));
    $search = strtolower(trim((string) ($filters['search'] ?? '')));
    $dueBefore = (string) ($filters['due_before'] ?? '');

    $assets = array_filter($registry['assets'], static function ($asset) use ($customerId, $segment, $search, $dueBefore) {
        $customerMatch = $customerId === '' || strtolower((string) ($asset['customer_id'] ?? '')) === $customerId;
        $segmentMatch = $segment === '' || strtolower((string) ($asset['segment'] ?? '')) === $segment;
        if (!$customerMatch || !$segmentMatch) {
            return false;
        }
        if ($search !== '') {
            $haystack = strtolower(($asset['customer_name'] ?? '') . ' ' . ($asset['serial_number'] ?? '') . ' ' . ($asset['location'] ?? ''));
            if (!str_contains($haystack, $search)) {
                return false;
            }
        }
        if ($dueBefore !== '') {
            $targets = array_filter([
                $asset['warranty_expiry'] ?? null,
                $asset['amc_expiry'] ?? null,
            ], static function ($date) {
                return is_string($date) && $date !== '';
            });
            foreach ($targets as $date) {
                if ($date <= $dueBefore) {
                    return true;
                }
            }
            return false;
        }
        return true;
    });

    return array_values(array_map('warranty_asset_present', $assets));
}

function warranty_asset_create(array $payload, string $actor): array
{
    $validated = validate_warranty_asset_payload($payload);
    $registry = warranty_registry_load();
    $asset = array_merge([
        'id' => uuid('asset'),
        'service_visits' => [],
        'reminders' => [],
        'geo_photos' => [],
        'documents' => [],
        'created_at' => now_ist(),
        'updated_at' => now_ist(),
    ], $validated);

    $registry['assets'][] = $asset;
    warranty_registry_save($registry);
    log_activity('warranty.create', 'Registered warranty asset ' . $asset['id'], $actor);

    return warranty_asset_present($asset);
}

function warranty_asset_update(string $id, array $payload, string $actor): array
{
    $validated = validate_warranty_asset_payload($payload, true);
    $registry = warranty_registry_load();
    foreach ($registry['assets'] as &$asset) {
        if (($asset['id'] ?? '') !== $id) {
            continue;
        }
        $asset = array_merge($asset, $validated);
        $asset['updated_at'] = now_ist();
        warranty_registry_save($registry);
        log_activity('warranty.update', 'Updated warranty asset ' . $id, $actor);
        return warranty_asset_present($asset);
    }
    unset($asset);

    throw new RuntimeException('Warranty asset not found.');
}

function warranty_asset_delete(string $id, string $actor): void
{
    $registry = warranty_registry_load();
    $count = count($registry['assets']);
    $registry['assets'] = array_values(array_filter($registry['assets'], static function ($asset) use ($id) {
        return ($asset['id'] ?? '') !== $id;
    }));
    if ($count === count($registry['assets'])) {
        throw new RuntimeException('Warranty asset not found.');
    }
    warranty_registry_save($registry);
    log_activity('warranty.delete', 'Removed warranty asset ' . $id, $actor);
}

function warranty_asset_add_visit(string $id, array $payload, string $actor): array
{
    $visitDate = sanitize_string($payload['visit_date'] ?? '', 20);
    if ($visitDate === null || $visitDate === '' || !validator_date($visitDate)) {
        throw new InvalidArgumentException('Visit date is required in YYYY-MM-DD format.');
    }
    $technician = sanitize_string($payload['technician'] ?? '', 160);
    if ($technician === null || $technician === '') {
        throw new InvalidArgumentException('Technician name is required.');
    }
    $outcome = sanitize_string($payload['outcome'] ?? '', 4000, true);
    $nextSteps = sanitize_string($payload['next_steps'] ?? '', 4000, true);
    $photos = [];
    if (isset($payload['photos'])) {
        if (!is_array($payload['photos'])) {
            throw new InvalidArgumentException('Photos must be an array.');
        }
        foreach ($payload['photos'] as $photo) {
            if (!is_array($photo)) {
                continue;
            }
            $photos[] = [
                'id' => $photo['id'] ?? uuid('aphoto'),
                'file' => $photo['file'] ?? null,
                'latitude' => isset($photo['latitude']) ? (float) $photo['latitude'] : null,
                'longitude' => isset($photo['longitude']) ? (float) $photo['longitude'] : null,
                'captured_at' => $photo['captured_at'] ?? now_ist(),
            ];
        }
    }

    $registry = warranty_registry_load();
    foreach ($registry['assets'] as &$asset) {
        if (($asset['id'] ?? '') !== $id) {
            continue;
        }
        if (!isset($asset['service_visits']) || !is_array($asset['service_visits'])) {
            $asset['service_visits'] = [];
        }
        $visit = [
            'id' => uuid('visit'),
            'visit_date' => $visitDate,
            'technician' => $technician,
            'outcome' => $outcome ?? null,
            'next_steps' => $nextSteps ?? null,
            'photos' => $photos,
            'logged_at' => now_ist(),
            'logged_by' => $actor,
        ];
        $asset['service_visits'][] = $visit;
        if ($photos) {
            if (!isset($asset['geo_photos']) || !is_array($asset['geo_photos'])) {
                $asset['geo_photos'] = [];
            }
            foreach ($photos as $photo) {
                $asset['geo_photos'][] = array_merge($photo, ['visit_id' => $visit['id'], 'uploaded_by' => $actor]);
            }
        }
        $asset['updated_at'] = now_ist();
        warranty_registry_save($registry);
        log_activity('warranty.visit', 'Logged service visit for asset ' . $id, $actor);
        communication_log_add([
            'customer_id' => $asset['customer_id'] ?? null,
            'ticket_id' => $payload['ticket_id'] ?? null,
            'channel' => 'service-visit',
            'summary' => 'Technician ' . $technician . ' visited on ' . $visitDate,
            'details' => $outcome ?? 'Visit recorded',
        ], $actor);
        return warranty_asset_present($asset);
    }
    unset($asset);

    throw new RuntimeException('Warranty asset not found.');
}

function warranty_asset_add_reminder(string $id, array $payload, string $actor): array
{
    $dueOn = sanitize_string($payload['due_on'] ?? '', 20);
    if ($dueOn === null || $dueOn === '' || !validator_date($dueOn)) {
        throw new InvalidArgumentException('Reminder due date must use YYYY-MM-DD format.');
    }
    $type = sanitize_string($payload['type'] ?? '', 120);
    if ($type === null || $type === '') {
        throw new InvalidArgumentException('Reminder type is required.');
    }
    $notes = sanitize_string($payload['notes'] ?? '', 2000, true);

    $registry = warranty_registry_load();
    foreach ($registry['assets'] as &$asset) {
        if (($asset['id'] ?? '') !== $id) {
            continue;
        }
        if (!isset($asset['reminders']) || !is_array($asset['reminders'])) {
            $asset['reminders'] = [];
        }
        $reminder = [
            'id' => uuid('rem'),
            'type' => $type,
            'due_on' => $dueOn,
            'notes' => $notes ?? null,
            'status' => 'pending',
            'created_at' => now_ist(),
            'created_by' => $actor,
        ];
        $asset['reminders'][] = $reminder;
        $asset['updated_at'] = now_ist();
        warranty_registry_save($registry);
        log_activity('warranty.reminder', 'Added reminder for asset ' . $id, $actor);
        return warranty_asset_present($asset);
    }
    unset($asset);

    throw new RuntimeException('Warranty asset not found.');
}

function warranty_asset_update_reminder_status(string $assetId, string $reminderId, string $status, string $actor): array
{
    $status = strtolower($status);
    if (!in_array($status, ['pending', 'completed', 'skipped'], true)) {
        throw new InvalidArgumentException('Invalid reminder status.');
    }
    $registry = warranty_registry_load();
    foreach ($registry['assets'] as &$asset) {
        if (($asset['id'] ?? '') !== $assetId) {
            continue;
        }
        if (!isset($asset['reminders']) || !is_array($asset['reminders'])) {
            $asset['reminders'] = [];
        }
        foreach ($asset['reminders'] as &$reminder) {
            if (($reminder['id'] ?? '') !== $reminderId) {
                continue;
            }
            $reminder['status'] = $status;
            $reminder['updated_at'] = now_ist();
            $reminder['updated_by'] = $actor;
            warranty_registry_save($registry);
            log_activity('warranty.reminder_status', 'Updated reminder ' . $reminderId . ' for asset ' . $assetId, $actor);
            return warranty_asset_present($asset);
        }
        unset($reminder);
    }
    unset($asset);

    throw new RuntimeException('Reminder not found.');
}

function warranty_amc_export_csv(array $filters = []): string
{
    $assets = warranty_assets_list($filters);
    $rows = array_map(static function ($asset) {
        $nextService = null;
        foreach ($asset['reminders'] as $reminder) {
            if (($reminder['status'] ?? '') !== 'pending') {
                continue;
            }
            if ($nextService === null || $reminder['due_on'] < $nextService) {
                $nextService = $reminder['due_on'];
            }
        }
        return [
            'id' => $asset['id'],
            'customer_id' => $asset['customer_id'],
            'customer_name' => $asset['customer_name'],
            'segment' => $asset['segment'],
            'asset_type' => $asset['asset_type'],
            'serial_number' => $asset['serial_number'],
            'installation_date' => $asset['installation_date'],
            'warranty_expiry' => $asset['warranty_expiry'],
            'amc_expiry' => $asset['amc_expiry'],
            'location' => $asset['location'],
            'pincode' => $asset['pincode'],
            'capacity_kw' => $asset['capacity_kw'],
            'next_service_due' => $nextService,
            'service_visit_count' => count($asset['service_visits']),
        ];
    }, $assets);

    $headers = ['id', 'customer_id', 'customer_name', 'segment', 'asset_type', 'serial_number', 'installation_date', 'warranty_expiry', 'amc_expiry', 'location', 'pincode', 'capacity_kw', 'next_service_due', 'service_visit_count'];
    return csv_encode($rows, $headers);
}

/**
 * Document vault with versioning helpers
 */
function documents_vault_load(): array
{
    $data = json_read(DOCUMENTS_INDEX_FILE, ['documents' => [], 'next_sequence' => 1]);
    if (!is_array($data)) {
        $data = ['documents' => [], 'next_sequence' => 1];
    }
    if (!isset($data['documents']) || !is_array($data['documents'])) {
        $data['documents'] = [];
    }
    if (!isset($data['next_sequence']) || !is_int($data['next_sequence'])) {
        $data['next_sequence'] = 1;
    }
    $data['documents'] = array_values(array_map(static function ($document) {
        if (!isset($document['versions']) || !is_array($document['versions'])) {
            $document['versions'] = [];
        }
        if (!isset($document['tags']) || !is_array($document['tags'])) {
            $document['tags'] = [];
        }
        if (!isset($document['customer_ids']) || !is_array($document['customer_ids'])) {
            $document['customer_ids'] = [];
        }
        if (!isset($document['ticket_ids']) || !is_array($document['ticket_ids'])) {
            $document['ticket_ids'] = [];
        }
        return $document;
    }, $data['documents']));
    return $data;
}

function documents_vault_save(array $data): void
{
    if (!isset($data['documents']) || !is_array($data['documents'])) {
        $data['documents'] = [];
    }
    if (!isset($data['next_sequence']) || !is_int($data['next_sequence'])) {
        $data['next_sequence'] = 1;
    }
    json_write(DOCUMENTS_INDEX_FILE, [
        'documents' => array_values($data['documents']),
        'next_sequence' => $data['next_sequence'],
    ]);
}

function documents_vault_present(array $document): array
{
    $document['versions'] = array_values($document['versions'] ?? []);
    return [
        'id' => $document['id'] ?? null,
        'title' => $document['title'] ?? '',
        'description' => $document['description'] ?? '',
        'customer_ids' => $document['customer_ids'] ?? [],
        'ticket_ids' => $document['ticket_ids'] ?? [],
        'tags' => $document['tags'] ?? [],
        'latest_version' => $document['latest_version'] ?? null,
        'versions' => $document['versions'],
        'created_at' => $document['created_at'] ?? null,
        'updated_at' => $document['updated_at'] ?? null,
    ];
}

function documents_vault_list(array $filters = []): array
{
    $vault = documents_vault_load();
    $customerId = strtolower((string) ($filters['customer_id'] ?? ''));
    $ticketId = strtolower((string) ($filters['ticket_id'] ?? ''));
    $tag = strtolower((string) ($filters['tag'] ?? ''));
    $search = strtolower(trim((string) ($filters['search'] ?? '')));

    $documents = array_filter($vault['documents'], static function ($document) use ($customerId, $ticketId, $tag, $search) {
        if ($customerId !== '') {
            $matches = array_filter($document['customer_ids'] ?? [], static function ($candidate) use ($customerId) {
                return strtolower((string) $candidate) === $customerId;
            });
            if (!$matches) {
                return false;
            }
        }
        if ($ticketId !== '') {
            $matches = array_filter($document['ticket_ids'] ?? [], static function ($candidate) use ($ticketId) {
                return strtolower((string) $candidate) === $ticketId;
            });
            if (!$matches) {
                return false;
            }
        }
        if ($tag !== '' && !in_array($tag, array_map('strtolower', $document['tags'] ?? []), true)) {
            return false;
        }
        if ($search !== '') {
            $haystack = strtolower(($document['title'] ?? '') . ' ' . ($document['description'] ?? '') . ' ' . implode(' ', $document['tags'] ?? []));
            if (!str_contains($haystack, $search)) {
                return false;
            }
        }
        return true;
    });

    return array_values(array_map('documents_vault_present', $documents));
}

function documents_vault_validate_payload(array $payload, bool $isNew): array
{
    $title = sanitize_string($payload['title'] ?? '', 200, !$isNew);
    if ($isNew && ($title === null || $title === '')) {
        throw new InvalidArgumentException('Document title is required.');
    }
    $description = sanitize_string($payload['description'] ?? '', 4000, true);

    $customerIds = [];
    if (isset($payload['customer_ids'])) {
        if (!is_array($payload['customer_ids'])) {
            throw new InvalidArgumentException('customer_ids must be an array.');
        }
        foreach ($payload['customer_ids'] as $id) {
            $clean = sanitize_string($id, 160, true);
            if ($clean) {
                $customerIds[] = $clean;
            }
        }
    }

    $ticketIds = [];
    if (isset($payload['ticket_ids'])) {
        if (!is_array($payload['ticket_ids'])) {
            throw new InvalidArgumentException('ticket_ids must be an array.');
        }
        foreach ($payload['ticket_ids'] as $id) {
            $clean = sanitize_string($id, 160, true);
            if ($clean) {
                $ticketIds[] = $clean;
            }
        }
    }

    $tags = [];
    if (isset($payload['tags'])) {
        if (!is_array($payload['tags'])) {
            throw new InvalidArgumentException('tags must be an array.');
        }
        foreach ($payload['tags'] as $tag) {
            $clean = sanitize_string($tag, 60, true);
            if ($clean) {
                $tags[] = strtolower($clean);
            }
        }
    }

    $validated = [];
    if ($title !== null) {
        $validated['title'] = $title;
    }
    if ($description !== null) {
        $validated['description'] = $description ?? '';
    }
    if ($customerIds) {
        $validated['customer_ids'] = array_values(array_unique($customerIds));
    }
    if ($ticketIds) {
        $validated['ticket_ids'] = array_values(array_unique($ticketIds));
    }
    if ($tags) {
        $validated['tags'] = array_values(array_unique($tags));
    }

    return $validated;
}

function documents_vault_record_upload(array $payload, string $actor): array
{
    $documentId = sanitize_string($payload['document_id'] ?? '', 160, true);
    $fileName = sanitize_string($payload['file_name'] ?? '', 255);
    if ($fileName === null || $fileName === '') {
        throw new InvalidArgumentException('File name is required.');
    }
    $filePath = sanitize_string($payload['file_path'] ?? '', 500);
    if ($filePath === null || $filePath === '') {
        throw new InvalidArgumentException('File path is required.');
    }
    $fileSize = isset($payload['file_size']) ? (int) $payload['file_size'] : 0;
    if ($fileSize < 0) {
        $fileSize = 0;
    }
    $checksum = sanitize_string($payload['checksum'] ?? '', 128, true);
    $notes = sanitize_string($payload['notes'] ?? '', 2000, true);

    $vault = documents_vault_load();
    $isNew = $documentId === null || $documentId === '';

    $metadata = documents_vault_validate_payload($payload, $isNew);
    $now = now_ist();

    if ($isNew) {
        $sequence = max(1, (int) $vault['next_sequence']);
        $documentId = 'doc-' . str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
        $vault['next_sequence'] = $sequence + 1;
        $document = array_merge([
            'id' => $documentId,
            'title' => $metadata['title'] ?? $fileName,
            'description' => $metadata['description'] ?? '',
            'customer_ids' => $metadata['customer_ids'] ?? [],
            'ticket_ids' => $metadata['ticket_ids'] ?? [],
            'tags' => $metadata['tags'] ?? [],
            'versions' => [],
            'created_at' => $now,
            'updated_at' => $now,
        ], []);
        $vault['documents'][] = $document;
    }

    foreach ($vault['documents'] as &$document) {
        if (($document['id'] ?? '') !== $documentId) {
            continue;
        }
        if (!$isNew) {
            $document = array_merge($document, $metadata);
            $document['updated_at'] = $now;
        }

        $versionNumber = count($document['versions']) + 1;
        $versionId = $documentId . '-v' . $versionNumber;
        $version = [
            'id' => $versionId,
            'version' => $versionNumber,
            'file_name' => $fileName,
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'checksum' => $checksum ?: null,
            'notes' => $notes ?? null,
            'uploaded_at' => $now,
            'uploaded_by' => $actor,
        ];
        $document['versions'][] = $version;
        $document['latest_version'] = $versionId;
        $document['updated_at'] = $now;
        documents_vault_save($vault);
        log_activity('documents.upload', 'Uploaded document version ' . $versionId, $actor);
        return documents_vault_present($document);
    }
    unset($document);

    throw new RuntimeException('Document not found for upload.');
}

function documents_vault_search(string $query, array $filters = []): array
{
    $filters['search'] = $query;
    return documents_vault_list($filters);
}

function documents_vault_generate_download_token(string $documentId, string $versionId, string $actor, int $ttlSeconds = 900): array
{
    if ($ttlSeconds <= 0) {
        $ttlSeconds = 900;
    }
    $token = bin2hex(random_bytes(16));
    $hash = hash('sha256', $token);
    $expiresAt = time() + $ttlSeconds;

    $tokens = json_read(DOCUMENT_TOKENS_FILE, []);
    if (!is_array($tokens)) {
        $tokens = [];
    }
    $tokens[$hash] = [
        'document_id' => $documentId,
        'version_id' => $versionId,
        'issued_at' => time(),
        'expires_at' => $expiresAt,
        'actor' => $actor,
    ];
    json_write(DOCUMENT_TOKENS_FILE, $tokens);

    return [
        'token' => $token,
        'expires_at' => date('c', $expiresAt),
    ];
}

function documents_vault_validate_download_token(string $token): ?array
{
    $hash = hash('sha256', $token);
    $tokens = json_read(DOCUMENT_TOKENS_FILE, []);
    if (!is_array($tokens) || !isset($tokens[$hash])) {
        return null;
    }
    $record = $tokens[$hash];
    if (($record['expires_at'] ?? 0) < time()) {
        unset($tokens[$hash]);
        json_write(DOCUMENT_TOKENS_FILE, $tokens);
        return null;
    }
    return $record;
}

/**
 * Customer communication timeline helpers
 */
function communication_log_load(): array
{
    $entries = json_read(COMMUNICATION_LOG_FILE, []);
    if (!is_array($entries)) {
        return [];
    }
    return array_values(array_filter($entries, 'is_array'));
}

function communication_log_save(array $entries): void
{
    json_write(COMMUNICATION_LOG_FILE, array_values($entries));
}

function communication_log_present(array $entry): array
{
    return [
        'id' => $entry['id'] ?? null,
        'customer_id' => $entry['customer_id'] ?? null,
        'ticket_id' => $entry['ticket_id'] ?? null,
        'channel' => $entry['channel'] ?? 'note',
        'direction' => $entry['direction'] ?? 'outbound',
        'summary' => $entry['summary'] ?? '',
        'details' => $entry['details'] ?? null,
        'recorded_at' => $entry['recorded_at'] ?? null,
        'actor' => $entry['actor'] ?? null,
        'metadata' => $entry['metadata'] ?? [],
    ];
}

function communication_log_list(array $filters = []): array
{
    $entries = communication_log_load();
    $customerId = strtolower((string) ($filters['customer_id'] ?? ''));
    $ticketId = strtolower((string) ($filters['ticket_id'] ?? ''));
    $channel = strtolower((string) ($filters['channel'] ?? ''));
    $direction = strtolower((string) ($filters['direction'] ?? ''));
    $from = isset($filters['from']) ? strtotime((string) $filters['from']) : null;
    $to = isset($filters['to']) ? strtotime((string) $filters['to']) : null;

    $results = array_filter($entries, static function ($entry) use ($customerId, $ticketId, $channel, $direction, $from, $to) {
        if ($customerId !== '' && strtolower((string) ($entry['customer_id'] ?? '')) !== $customerId) {
            return false;
        }
        if ($ticketId !== '' && strtolower((string) ($entry['ticket_id'] ?? '')) !== $ticketId) {
            return false;
        }
        if ($channel !== '' && strtolower((string) ($entry['channel'] ?? '')) !== $channel) {
            return false;
        }
        if ($direction !== '' && strtolower((string) ($entry['direction'] ?? '')) !== $direction) {
            return false;
        }
        if ($from !== null || $to !== null) {
            $timestamp = strtotime((string) ($entry['recorded_at'] ?? '')) ?: null;
            if ($timestamp === null) {
                return false;
            }
            if ($from !== null && $timestamp < $from) {
                return false;
            }
            if ($to !== null && $timestamp > $to) {
                return false;
            }
        }
        return true;
    });

    usort($results, static function ($a, $b) {
        return strcmp($b['recorded_at'] ?? '', $a['recorded_at'] ?? '');
    });

    return array_values(array_map('communication_log_present', $results));
}

function communication_log_add(array $payload, string $actor): array
{
    $customerId = sanitize_string($payload['customer_id'] ?? '', 160, true);
    $ticketId = sanitize_string($payload['ticket_id'] ?? '', 160, true);
    $channel = sanitize_string($payload['channel'] ?? '', 80, true) ?? 'note';
    $direction = sanitize_string($payload['direction'] ?? '', 20, true) ?? 'outbound';
    $summary = sanitize_string($payload['summary'] ?? '', 400, true) ?? '';
    $details = sanitize_string($payload['details'] ?? '', 4000, true);
    $metadata = [];
    if (isset($payload['metadata'])) {
        if (!is_array($payload['metadata'])) {
            throw new InvalidArgumentException('Communication metadata must be an object.');
        }
        $metadata = $payload['metadata'];
    }

    $entries = communication_log_load();
    $entry = [
        'id' => uuid('comm'),
        'customer_id' => $customerId ?: null,
        'ticket_id' => $ticketId ?: null,
        'channel' => strtolower($channel),
        'direction' => strtolower($direction),
        'summary' => $summary,
        'details' => $details ?: null,
        'metadata' => $metadata,
        'recorded_at' => now_ist(),
        'actor' => $actor,
    ];

    $entries[] = $entry;
    communication_log_save($entries);
    log_activity('communication.add', 'Logged communication ' . $entry['id'], $actor);

    return communication_log_present($entry);
}

function communication_log_export_csv(array $filters = []): string
{
    $entries = communication_log_list($filters);
    $headers = ['id', 'customer_id', 'ticket_id', 'channel', 'direction', 'summary', 'details', 'recorded_at', 'actor'];
    return csv_encode($entries, $headers);
}

/**
 * Subsidy oversight helpers
 */
function subsidy_allowed_stages(): array
{
    return ['applied', 'sanctioned', 'inspected', 'redeemed', 'closed'];
}

function subsidy_stage_checklist(string $stage): array
{
    $lists = [
        'applied' => ['Application form', 'Customer KYC documents', 'Installation proposal'],
        'sanctioned' => ['Sanction letter', 'Financial approval copy'],
        'inspected' => ['Site inspection report', 'Geo-tagged photos', 'Net meter acknowledgement'],
        'redeemed' => ['Subsidy claim form', 'Bank details verification'],
        'closed' => ['Final settlement confirmation', 'Customer satisfaction note'],
    ];
    $stage = strtolower($stage);
    return $lists[$stage] ?? [];
}

function subsidy_records_load(): array
{
    $records = json_read(SUBSIDY_TRACKER_FILE, []);
    if (!is_array($records)) {
        return [];
    }
    return array_values(array_filter($records, 'is_array'));
}

function subsidy_records_save(array $records): void
{
    json_write(SUBSIDY_TRACKER_FILE, array_values($records));
}

function subsidy_record_present(array $record): array
{
    return [
        'id' => $record['id'] ?? null,
        'customer_id' => $record['customer_id'] ?? null,
        'application_no' => $record['application_no'] ?? '',
        'discom' => $record['discom'] ?? '',
        'stage' => $record['stage'] ?? 'applied',
        'amount' => (float) ($record['amount'] ?? 0),
        'dates' => $record['dates'] ?? [],
        'remarks' => $record['remarks'] ?? '',
        'documents' => $record['documents'] ?? [],
        'created_at' => $record['created_at'] ?? null,
        'updated_at' => $record['updated_at'] ?? null,
    ];
}

function validate_subsidy_payload(array $payload, bool $partial = false): array
{
    $customerId = sanitize_string($payload['customer_id'] ?? '', 160, true);
    $applicationNo = sanitize_string($payload['application_no'] ?? '', 160, true);
    if (!$partial && ($applicationNo === null || $applicationNo === '')) {
        throw new InvalidArgumentException('Application number is required.');
    }
    $discom = sanitize_string($payload['discom'] ?? '', 120, true);
    if (!$partial && ($discom === null || $discom === '')) {
        throw new InvalidArgumentException('DISCOM is required.');
    }
    $stage = sanitize_string($payload['stage'] ?? '', 40, true);
    if ($stage !== null && $stage !== '' && !in_array(strtolower($stage), subsidy_allowed_stages(), true)) {
        throw new InvalidArgumentException('Invalid subsidy stage.');
    }
    $amount = isset($payload['amount']) ? (float) $payload['amount'] : null;
    $remarks = sanitize_string($payload['remarks'] ?? '', 4000, true);

    $dates = [];
    if (isset($payload['dates'])) {
        if (!is_array($payload['dates'])) {
            throw new InvalidArgumentException('Dates must be an object.');
        }
        foreach ($payload['dates'] as $key => $value) {
            $cleanKey = strtolower(trim((string) $key));
            if (!in_array($cleanKey, subsidy_allowed_stages(), true)) {
                continue;
            }
            $cleanValue = sanitize_string($value, 20, true);
            if ($cleanValue && !validator_date($cleanValue)) {
                throw new InvalidArgumentException('Date for ' . $cleanKey . ' must use YYYY-MM-DD format.');
            }
            if ($cleanValue) {
                $dates[$cleanKey] = $cleanValue;
            }
        }
    }

    $documents = [];
    if (isset($payload['documents'])) {
        if (!is_array($payload['documents'])) {
            throw new InvalidArgumentException('Documents must be an object.');
        }
        $documents = $payload['documents'];
    }

    $validated = [];
    if ($customerId !== null) {
        $validated['customer_id'] = $customerId ?: null;
    }
    if ($applicationNo !== null) {
        $validated['application_no'] = $applicationNo ?: null;
    }
    if ($discom !== null) {
        $validated['discom'] = $discom ?: null;
    }
    if ($stage) {
        $validated['stage'] = strtolower($stage);
    }
    if ($amount !== null) {
        $validated['amount'] = $amount;
    }
    if ($remarks !== null) {
        $validated['remarks'] = $remarks ?? '';
    }
    if ($dates) {
        $validated['dates'] = $dates;
    }
    if ($documents) {
        $validated['documents'] = $documents;
    }

    return $validated;
}

function subsidy_records_list(array $filters = []): array
{
    $records = subsidy_records_load();
    $stage = strtolower((string) ($filters['stage'] ?? ''));
    $discom = strtolower((string) ($filters['discom'] ?? ''));
    $search = strtolower(trim((string) ($filters['search'] ?? '')));

    $filtered = array_filter($records, static function ($record) use ($stage, $discom, $search) {
        if ($stage !== '' && strtolower((string) ($record['stage'] ?? '')) !== $stage) {
            return false;
        }
        if ($discom !== '' && strtolower((string) ($record['discom'] ?? '')) !== $discom) {
            return false;
        }
        if ($search !== '') {
            $haystack = strtolower(($record['application_no'] ?? '') . ' ' . ($record['customer_id'] ?? '') . ' ' . ($record['remarks'] ?? ''));
            if (!str_contains($haystack, $search)) {
                return false;
            }
        }
        return true;
    });

    usort($filtered, static function ($a, $b) {
        return strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? '');
    });

    return array_values(array_map('subsidy_record_present', $filtered));
}

function subsidy_record_create(array $payload, string $actor): array
{
    $validated = validate_subsidy_payload($payload);
    $records = subsidy_records_load();
    $record = array_merge([
        'id' => uuid('subsidy'),
        'stage' => 'applied',
        'dates' => [],
        'documents' => [],
        'created_at' => now_ist(),
        'updated_at' => now_ist(),
    ], $validated);
    if (!isset($record['stage']) || $record['stage'] === null) {
        $record['stage'] = 'applied';
    }
    if (!isset($record['dates'][$record['stage']])) {
        $record['dates'][$record['stage']] = substr($record['created_at'], 0, 10);
    }
    $records[] = $record;
    subsidy_records_save($records);
    log_activity('subsidy.create', 'Created subsidy record ' . $record['id'], $actor);

    return subsidy_record_present($record);
}

function subsidy_record_update(string $id, array $payload, string $actor): array
{
    $validated = validate_subsidy_payload($payload, true);
    $records = subsidy_records_load();
    foreach ($records as &$record) {
        if (($record['id'] ?? '') !== $id) {
            continue;
        }
        $record = array_merge($record, $validated);
        $record['updated_at'] = now_ist();
        subsidy_records_save($records);
        log_activity('subsidy.update', 'Updated subsidy record ' . $id, $actor);
        return subsidy_record_present($record);
    }
    unset($record);

    throw new RuntimeException('Subsidy record not found.');
}

function subsidy_record_transition_stage(string $id, string $stage, array $payload, string $actor): array
{
    $stage = strtolower($stage);
    if (!in_array($stage, subsidy_allowed_stages(), true)) {
        throw new InvalidArgumentException('Invalid subsidy stage.');
    }
    $records = subsidy_records_load();
    foreach ($records as &$record) {
        if (($record['id'] ?? '') !== $id) {
            continue;
        }
        $record['stage'] = $stage;
        if (!isset($record['dates']) || !is_array($record['dates'])) {
            $record['dates'] = [];
        }
        $date = sanitize_string($payload['date'] ?? '', 20, true);
        if ($date) {
            if (!validator_date($date)) {
                throw new InvalidArgumentException('Transition date must use YYYY-MM-DD format.');
            }
            $record['dates'][$stage] = $date;
        } else {
            $record['dates'][$stage] = substr(now_ist(), 0, 10);
        }
        if (isset($payload['documents']) && is_array($payload['documents'])) {
            if (!isset($record['documents']) || !is_array($record['documents'])) {
                $record['documents'] = [];
            }
            $record['documents'][$stage] = $payload['documents'];
        }
        if (isset($payload['remarks'])) {
            $remarks = sanitize_string($payload['remarks'], 4000, true);
            if ($remarks !== null) {
                $record['remarks'] = $remarks;
            }
        }
        $record['updated_at'] = now_ist();
        subsidy_records_save($records);
        log_activity('subsidy.stage', 'Moved subsidy record ' . $id . ' to ' . $stage, $actor);
        return subsidy_record_present($record);
    }
    unset($record);

    throw new RuntimeException('Subsidy record not found.');
}

function subsidy_dashboard_metrics(): array
{
    $records = subsidy_records_load();
    $totals = ['count' => count($records), 'amount' => 0.0];
    $byStage = [];
    foreach (subsidy_allowed_stages() as $stage) {
        $byStage[$stage] = ['count' => 0, 'amount' => 0.0];
    }
    foreach ($records as $record) {
        $amount = (float) ($record['amount'] ?? 0);
        $totals['amount'] += $amount;
        $stage = strtolower((string) ($record['stage'] ?? 'applied'));
        if (!isset($byStage[$stage])) {
            $byStage[$stage] = ['count' => 0, 'amount' => 0.0];
        }
        $byStage[$stage]['count']++;
        $byStage[$stage]['amount'] += $amount;
    }

    $upcoming = [];
    $today = date('Y-m-d');
    foreach ($records as $record) {
        $dates = $record['dates'] ?? [];
        $stage = $record['stage'] ?? 'applied';
        $checkStage = $stage === 'closed' ? 'closed' : $stage;
        if (!isset($dates[$checkStage])) {
            $upcoming[] = [
                'id' => $record['id'],
                'application_no' => $record['application_no'],
                'pending_stage' => $checkStage,
                'recommended_documents' => subsidy_stage_checklist($checkStage),
            ];
            continue;
        }
        if ($stage !== 'closed') {
            $dueChecklist = subsidy_stage_checklist($stage);
            $missingDocs = array_diff($dueChecklist, array_keys((array) ($record['documents'][$stage] ?? [])));
            if ($missingDocs) {
                $upcoming[] = [
                    'id' => $record['id'],
                    'application_no' => $record['application_no'],
                    'pending_stage' => $stage,
                    'missing_documents' => array_values($missingDocs),
                ];
            }
        }
        if (isset($record['dates']['inspected']) && $record['dates']['inspected'] <= $today && $stage === 'inspected') {
            $upcoming[] = [
                'id' => $record['id'],
                'application_no' => $record['application_no'],
                'pending_stage' => 'redeemed',
                'note' => 'Ready for subsidy redemption submission.',
            ];
        }
    }

    return [
        'totals' => $totals,
        'by_stage' => $byStage,
        'attention' => $upcoming,
    ];
}

function subsidy_records_export_csv(array $filters = []): string
{
    $records = subsidy_records_list($filters);
    $rows = array_map(static function ($record) {
        return [
            'id' => $record['id'],
            'customer_id' => $record['customer_id'],
            'application_no' => $record['application_no'],
            'discom' => $record['discom'],
            'stage' => $record['stage'],
            'amount' => $record['amount'],
            'applied_on' => $record['dates']['applied'] ?? null,
            'sanctioned_on' => $record['dates']['sanctioned'] ?? null,
            'inspected_on' => $record['dates']['inspected'] ?? null,
            'redeemed_on' => $record['dates']['redeemed'] ?? null,
            'closed_on' => $record['dates']['closed'] ?? null,
            'remarks' => $record['remarks'],
        ];
    }, $records);
    $headers = ['id', 'customer_id', 'application_no', 'discom', 'stage', 'amount', 'applied_on', 'sanctioned_on', 'inspected_on', 'redeemed_on', 'closed_on', 'remarks'];
    return csv_encode($rows, $headers);
}

/**
 * Data quality rules helpers
 */
function data_quality_normalize_phone(?string $phone): ?string
{
    if ($phone === null) {
        return null;
    }
    $digits = preg_replace('/[^0-9]/', '', $phone);
    if ($digits === '') {
        return null;
    }
    if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
        $digits = substr($digits, 2);
    }
    if (strlen($digits) < 6) {
        return null;
    }
    return $digits;
}

function data_quality_normalize_email(?string $email): ?string
{
    if ($email === null) {
        return null;
    }
    $clean = strtolower(trim($email));
    return $clean === '' ? null : $clean;
}

function data_quality_validate_date($value): bool
{
    if ($value === null || $value === '') {
        return true;
    }
    $trimmed = substr((string) $value, 0, 10);
    return validator_date($trimmed);
}

function data_quality_scan(bool $refreshCache = true): array
{
    $issues = [];
    $customers = customers_load();
    $leads = leads_load();
    $tickets = tickets_load();
    $settings = load_site_settings();
    $yesNoValues = array_map('strtolower', $settings['Data Quality']['yes_no_values'] ?? ['yes', 'no']);
    $pincodePattern = $settings['Data Quality']['pincode_regex'] ?? null;

    $emailIndex = [];
    $phoneIndex = [];

    $recordSources = [
        ['entity' => 'customer', 'records' => $customers],
        ['entity' => 'lead', 'records' => $leads],
        ['entity' => 'ticket', 'records' => $tickets],
    ];

    foreach ($recordSources as $source) {
        foreach ($source['records'] as $record) {
            $email = data_quality_normalize_email($record['email'] ?? ($record['customer_email'] ?? null));
            $phone = data_quality_normalize_phone($record['phone'] ?? ($record['customer_phone'] ?? null));
            $id = $record['id'] ?? ($record['application_no'] ?? null);
            if ($email) {
                $emailIndex[$email][$source['entity']][] = $id;
            }
            if ($phone) {
                $phoneIndex[$phone][$source['entity']][] = $id;
            }
        }
    }

    foreach ($emailIndex as $email => $groups) {
        $count = 0;
        foreach ($groups as $ids) {
            $count += count($ids);
        }
        if ($count > 1) {
            $issues[] = [
                'id' => uuid('dq'),
                'type' => 'duplicate',
                'field' => 'email',
                'value' => $email,
                'records' => $groups,
            ];
        }
    }
    foreach ($phoneIndex as $phone => $groups) {
        $count = 0;
        foreach ($groups as $ids) {
            $count += count($ids);
        }
        if ($count > 1) {
            $issues[] = [
                'id' => uuid('dq'),
                'type' => 'duplicate',
                'field' => 'phone',
                'value' => $phone,
                'records' => $groups,
            ];
        }
    }

    foreach ($customers as $customer) {
        $id = $customer['id'] ?? null;
        if (!data_quality_validate_date($customer['created_at'] ?? null)) {
            $issues[] = [
                'id' => uuid('dq'),
                'type' => 'invalid_date',
                'field' => 'created_at',
                'value' => $customer['created_at'],
                'entity' => 'customer',
                'record_id' => $id,
            ];
        }
        if (!data_quality_validate_date($customer['updated_at'] ?? null)) {
            $issues[] = [
                'id' => uuid('dq'),
                'type' => 'invalid_date',
                'field' => 'updated_at',
                'value' => $customer['updated_at'],
                'entity' => 'customer',
                'record_id' => $id,
            ];
        }
        if (isset($customer['documents_verified'])) {
            $value = $customer['documents_verified'];
            $normalized = is_bool($value) ? ($value ? 'yes' : 'no') : strtolower((string) $value);
            if (!in_array($normalized, array_merge(['true', 'false', '1', '0'], $yesNoValues), true)) {
                $issues[] = [
                    'id' => uuid('dq'),
                    'type' => 'invalid_yes_no',
                    'field' => 'documents_verified',
                    'value' => $value,
                    'entity' => 'customer',
                    'record_id' => $id,
                ];
            }
        }
        if (isset($customer['pincode']) && $customer['pincode'] !== null) {
            $pin = (string) $customer['pincode'];
            if (!validator_pincode($pin, is_string($pincodePattern) ? $pincodePattern : null)) {
                $issues[] = [
                    'id' => uuid('dq'),
                    'type' => 'invalid_pincode',
                    'field' => 'pincode',
                    'value' => $pin,
                    'entity' => 'customer',
                    'record_id' => $id,
                ];
            }
        }
    }

    foreach ($tickets as $ticket) {
        if (!data_quality_validate_date($ticket['due_date'] ?? null)) {
            $issues[] = [
                'id' => uuid('dq'),
                'type' => 'invalid_date',
                'field' => 'due_date',
                'value' => $ticket['due_date'],
                'entity' => 'ticket',
                'record_id' => $ticket['id'] ?? null,
            ];
        }
    }

    $cache = [
        'last_run' => now_ist(),
        'summary' => [
            'issues_total' => count($issues),
            'duplicates' => count(array_filter($issues, static fn($issue) => $issue['type'] === 'duplicate')),
            'invalid_dates' => count(array_filter($issues, static fn($issue) => $issue['type'] === 'invalid_date')),
        ],
        'issues' => $issues,
    ];

    if ($refreshCache) {
        json_write(DATA_QUALITY_CACHE_FILE, $cache);
    }

    return $cache;
}

function data_quality_get_cache(bool $refresh = false): array
{
    $cache = json_read(DATA_QUALITY_CACHE_FILE, ['last_run' => null, 'summary' => [], 'issues' => []]);
    if ($refresh || !is_array($cache) || empty($cache['last_run'])) {
        $cache = data_quality_scan(true);
    }
    return $cache;
}

function data_quality_dashboard(bool $refresh = false): array
{
    $cache = data_quality_get_cache($refresh);
    $issues = $cache['issues'] ?? [];
    $byType = [];
    foreach ($issues as $issue) {
        $type = $issue['type'] ?? 'unknown';
        if (!isset($byType[$type])) {
            $byType[$type] = 0;
        }
        $byType[$type]++;
    }
    return [
        'last_run' => $cache['last_run'] ?? null,
        'summary' => $cache['summary'] ?? [],
        'issues_by_type' => $byType,
        'total_issues' => count($issues),
    ];
}

function data_quality_export_errors_csv(?array $issues = null): string
{
    if ($issues === null) {
        $cache = data_quality_get_cache(false);
        $issues = $cache['issues'] ?? [];
    }
    $rows = array_map(static function ($issue) {
        return [
            'id' => $issue['id'] ?? null,
            'type' => $issue['type'] ?? null,
            'field' => $issue['field'] ?? null,
            'value' => is_scalar($issue['value'] ?? null) ? $issue['value'] : json_encode($issue['value']),
            'entity' => $issue['entity'] ?? null,
            'record_id' => $issue['record_id'] ?? null,
        ];
    }, $issues);
    $headers = ['id', 'type', 'field', 'value', 'entity', 'record_id'];
    return csv_encode($rows, $headers);
}

function data_quality_merge(array $payload, string $actor): array
{
    $entity = strtolower((string) ($payload['entity'] ?? ''));
    if ($entity !== 'customer') {
        throw new InvalidArgumentException('Merge is currently supported for customers only.');
    }
    $primaryId = sanitize_string($payload['primary_id'] ?? '', 160);
    if ($primaryId === null || $primaryId === '') {
        throw new InvalidArgumentException('Primary record id is required.');
    }
    $duplicateIds = $payload['duplicate_ids'] ?? [];
    if (!is_array($duplicateIds) || !$duplicateIds) {
        throw new InvalidArgumentException('Duplicate ids must be provided.');
    }
    $fields = $payload['fields'] ?? [];
    if (!is_array($fields)) {
        throw new InvalidArgumentException('Merge fields payload must be an object.');
    }

    $customers = customers_load();
    $primary = null;
    foreach ($customers as $index => $customer) {
        if (($customer['id'] ?? '') === $primaryId) {
            $primary = &$customers[$index];
            break;
        }
    }
    if (!$primary) {
        throw new RuntimeException('Primary customer not found.');
    }

    foreach ($fields as $field => $value) {
        if (in_array($field, ['id', 'created_at'], true)) {
            continue;
        }
        $primary[$field] = $value;
    }

    $customers = array_values(array_filter($customers, static function ($customer) use ($primaryId, $duplicateIds) {
        $id = $customer['id'] ?? null;
        if ($id === $primaryId) {
            return true;
        }
        return !in_array($id, $duplicateIds, true);
    }));

    customers_save($customers);
    log_activity('data_quality.merge', 'Merged customers into ' . $primaryId, $actor);

    foreach (customers_load() as $customer) {
        if (($customer['id'] ?? null) === $primaryId) {
            return $customer;
        }
    }

    throw new RuntimeException('Merged customer not found after merge.');
}

/**
 * Management layer helpers
 */

function management_users_load(): array
{
    $users = json_read(DATA_PATH . '/users.json', []);
    if (!is_array($users)) {
        return [];
    }
    return array_values(array_filter($users, 'is_array'));
}

function management_users_save(array $users): void
{
    json_write(DATA_PATH . '/users.json', array_values($users));
}

function management_user_present(array $user): array
{
    return [
        'id' => $user['id'] ?? null,
        'name' => $user['name'] ?? 'User',
        'email' => $user['email'] ?? null,
        'role' => $user['role'] ?? 'employee',
        'status' => $user['status'] ?? 'active',
        'force_reset' => (bool) ($user['force_reset'] ?? false),
        'created_at' => $user['created_at'] ?? null,
        'updated_at' => $user['updated_at'] ?? null,
        'last_login' => $user['last_login'] ?? null,
    ];
}

function management_users_list(array $filters = []): array
{
    $users = management_users_load();
    $role = strtolower((string) ($filters['role'] ?? ''));
    $status = strtolower((string) ($filters['status'] ?? ''));
    $search = strtolower(trim((string) ($filters['search'] ?? '')));

    $filtered = array_filter($users, static function ($user) use ($role, $status, $search) {
        if ($role !== '' && strtolower((string) ($user['role'] ?? '')) !== $role) {
            return false;
        }
        if ($status !== '' && strtolower((string) ($user['status'] ?? '')) !== $status) {
            return false;
        }
        if ($search !== '') {
            $haystack = strtolower(($user['name'] ?? '') . ' ' . ($user['email'] ?? ''));
            if (!str_contains($haystack, $search)) {
                return false;
            }
        }
        return true;
    });

    usort($filtered, static function ($a, $b) {
        return strcmp($a['name'] ?? '', $b['name'] ?? '');
    });

    return array_values(array_map('management_user_present', $filtered));
}

function management_user_create(array $payload, string $actor): array
{
    $name = sanitize_string($payload['name'] ?? '', 160);
    $email = sanitize_string($payload['email'] ?? '', 160);
    $role = sanitize_string($payload['role'] ?? 'employee', 40) ?? 'employee';
    $status = sanitize_string($payload['status'] ?? 'active', 40) ?? 'active';
    $forceReset = normalize_bool($payload['force_reset'] ?? false);
    $password = sanitize_string($payload['password'] ?? '', 255, true);

    if ($name === null || $name === '') {
        throw new InvalidArgumentException('Name is required.');
    }
    if ($email === null || $email === '' || !validator_email($email)) {
        throw new InvalidArgumentException('Valid email is required.');
    }
    if ($password === null || $password === '') {
        throw new InvalidArgumentException('Password is required.');
    }

    $users = management_users_load();
    foreach ($users as $existing) {
        if (strcasecmp($existing['email'] ?? '', $email) === 0) {
            throw new RuntimeException('A user with this email already exists.');
        }
    }

    $now = now_ist();
    $record = [
        'id' => uuid('user'),
        'name' => $name,
        'email' => $email,
        'role' => strtolower($role),
        'status' => strtolower($status),
        'force_reset' => $forceReset,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'created_at' => $now,
        'updated_at' => $now,
        'last_login' => null,
    ];

    $users[] = $record;
    management_users_save($users);
    log_activity('user.create', 'Created user ' . $email, $actor);

    return management_user_present($record);
}

function management_user_update(string $id, array $payload, string $actor): array
{
    $users = management_users_load();
    foreach ($users as &$user) {
        if (($user['id'] ?? '') !== $id) {
            continue;
        }
        $name = array_key_exists('name', $payload) ? sanitize_string($payload['name'], 160) : null;
        $role = array_key_exists('role', $payload) ? sanitize_string($payload['role'], 40) : null;
        $status = array_key_exists('status', $payload) ? sanitize_string($payload['status'], 40) : null;
        $forceReset = array_key_exists('force_reset', $payload) ? normalize_bool($payload['force_reset']) : null;

        if ($name !== null) {
            if ($name === '') {
                throw new InvalidArgumentException('Name cannot be empty.');
            }
            $user['name'] = $name;
        }
        if ($role !== null) {
            $user['role'] = strtolower($role);
        }
        if ($status !== null) {
            $user['status'] = strtolower($status);
        }
        if ($forceReset !== null) {
            $user['force_reset'] = $forceReset;
        }
        $user['updated_at'] = now_ist();
        management_users_save($users);
        log_activity('user.update', 'Updated user ' . ($user['email'] ?? $id), $actor);
        return management_user_present($user);
    }
    unset($user);

    throw new RuntimeException('User not found.');
}

function management_user_reset_password(string $id, string $password, bool $forceReset, string $actor): array
{
    $password = sanitize_string($password, 255);
    if ($password === null || $password === '') {
        throw new InvalidArgumentException('Password cannot be empty.');
    }
    $users = management_users_load();
    foreach ($users as &$user) {
        if (($user['id'] ?? '') !== $id) {
            continue;
        }
        $user['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        $user['force_reset'] = $forceReset;
        $user['updated_at'] = now_ist();
        management_users_save($users);
        log_activity('user.reset_password', 'Reset password for ' . ($user['email'] ?? $id), $actor);
        return management_user_present($user);
    }
    unset($user);

    throw new RuntimeException('User not found.');
}

function management_user_delete(string $id, string $actor): void
{
    $users = management_users_load();
    $count = count($users);
    $users = array_values(array_filter($users, static function ($user) use ($id) {
        return ($user['id'] ?? '') !== $id;
    }));
    if ($count === count($users)) {
        throw new RuntimeException('User not found.');
    }
    management_users_save($users);
    log_activity('user.delete', 'Deleted user ' . $id, $actor);
}

function management_approvals_load(): array
{
    $approvals = json_read(DATA_PATH . '/approvals.json', []);
    if (!is_array($approvals)) {
        return [];
    }
    return array_values(array_filter($approvals, 'is_array'));
}

function management_approvals_save(array $approvals): void
{
    json_write(DATA_PATH . '/approvals.json', array_values($approvals));
}

function management_approval_present(array $approval): array
{
    return [
        'id' => $approval['id'] ?? null,
        'target_type' => $approval['target_type'] ?? null,
        'target_id' => $approval['target_id'] ?? null,
        'changes' => $approval['changes'] ?? [],
        'status' => $approval['status'] ?? 'pending',
        'reason' => $approval['reason'] ?? null,
        'requested_by' => $approval['requested_by'] ?? null,
        'requested_at' => $approval['requested_at'] ?? null,
        'resolved_at' => $approval['resolved_at'] ?? null,
        'resolved_by' => $approval['resolved_by'] ?? null,
        'decision_notes' => $approval['decision_notes'] ?? null,
        'snapshot' => $approval['snapshot'] ?? null,
    ];
}

function management_approvals_list(?string $status = null): array
{
    $approvals = management_approvals_load();
    if ($status !== null && $status !== '') {
        $status = strtolower($status);
        $approvals = array_filter($approvals, static function ($approval) use ($status) {
            return strtolower((string) ($approval['status'] ?? 'pending')) === $status;
        });
    }
    usort($approvals, static function ($a, $b) {
        return strcmp($b['requested_at'] ?? '', $a['requested_at'] ?? '');
    });
    return array_values(array_map('management_approval_present', $approvals));
}

function management_approval_apply(array $approval, string $actor): void
{
    $targetType = strtolower((string) ($approval['target_type'] ?? ''));
    $targetId = (string) ($approval['target_id'] ?? '');
    $changes = is_array($approval['changes'] ?? null) ? $approval['changes'] : [];

    if ($targetType === '' || $targetId === '' || !$changes) {
        return;
    }

    switch ($targetType) {
        case 'user':
            $users = management_users_load();
            foreach ($users as &$user) {
                if (($user['id'] ?? '') !== $targetId) {
                    continue;
                }
                foreach ($changes as $field => $change) {
                    if ($field === 'password_hash') {
                        continue;
                    }
                    $user[$field] = $change['new'] ?? ($user[$field] ?? null);
                }
                $user['updated_at'] = now_ist();
                management_users_save($users);
                return;
            }
            break;
        case 'customer':
            $customers = customers_load();
            foreach ($customers as &$customer) {
                if (($customer['id'] ?? '') !== $targetId) {
                    continue;
                }
                foreach ($changes as $field => $change) {
                    if (in_array($field, ['id'], true)) {
                        continue;
                    }
                    $customer[$field] = $change['new'] ?? ($customer[$field] ?? null);
                }
                $customer['updated_at'] = now_ist();
                customers_save($customers);
                return;
            }
            break;
        default:
            break;
    }
}

function management_approval_update_status(string $id, string $status, string $actor, ?string $notes = null): array
{
    $status = strtolower($status);
    if (!in_array($status, ['approved', 'rejected', 'acknowledged'], true)) {
        throw new InvalidArgumentException('Invalid approval status.');
    }
    $approvals = management_approvals_load();
    foreach ($approvals as &$approval) {
        if (($approval['id'] ?? '') !== $id) {
            continue;
        }
        if ($status === 'acknowledged') {
            $approval['acknowledged_at'] = now_ist();
            $approval['acknowledged_by'] = $actor;
            management_approvals_save($approvals);
            return management_approval_present($approval);
        }
        $approval['status'] = $status;
        $approval['resolved_at'] = now_ist();
        $approval['resolved_by'] = $actor;
        if ($notes !== null) {
            $approval['decision_notes'] = $notes;
        }
        management_approvals_save($approvals);
        if ($status === 'approved') {
            management_approval_apply($approval, $actor);
            log_activity('approval.approved', 'Approved change ' . $id, $actor);
        } else {
            log_activity('approval.rejected', 'Rejected change ' . $id, $actor);
        }
        return management_approval_present($approval);
    }
    unset($approval);

    throw new RuntimeException('Approval request not found.');
}

function management_tasks_load(): array
{
    $tasks = json_read(DATA_PATH . '/tasks.json', []);
    if (!is_array($tasks)) {
        return [];
    }
    return array_values(array_filter($tasks, 'is_array'));
}

function management_tasks_save(array $tasks): void
{
    json_write(DATA_PATH . '/tasks.json', array_values($tasks));
}

function management_task_present(array $task): array
{
    return [
        'id' => $task['id'] ?? null,
        'title' => $task['title'] ?? '',
        'description' => $task['description'] ?? '',
        'priority' => $task['priority'] ?? 'medium',
        'status' => $task['status'] ?? 'To Do',
        'assignee' => $task['assignee'] ?? null,
        'created_at' => $task['created_at'] ?? null,
        'updated_at' => $task['updated_at'] ?? null,
    ];
}

function management_tasks_board(): array
{
    $tasks = management_tasks_load();
    $columns = ['To Do' => [], 'In Progress' => [], 'Done' => []];
    foreach ($tasks as $task) {
        $status = $task['status'] ?? 'To Do';
        if (!isset($columns[$status])) {
            $columns[$status] = [];
        }
        $columns[$status][] = management_task_present($task);
    }
    foreach ($columns as &$column) {
        usort($column, static function ($a, $b) {
            return strcmp($a['created_at'] ?? '', $b['created_at'] ?? '');
        });
    }
    unset($column);
    return $columns;
}

function management_task_create(array $payload, string $actor): array
{
    $title = sanitize_string($payload['title'] ?? '', 160);
    if ($title === null || $title === '') {
        throw new InvalidArgumentException('Task title is required.');
    }
    $description = sanitize_string($payload['description'] ?? '', 2000, true) ?? '';
    $priority = sanitize_string($payload['priority'] ?? 'medium', 40) ?? 'medium';
    $assignee = sanitize_string($payload['assignee'] ?? '', 160, true);

    $tasks = management_tasks_load();
    $now = now_ist();
    $task = [
        'id' => uuid('task'),
        'title' => $title,
        'description' => $description,
        'priority' => strtolower($priority),
        'status' => 'To Do',
        'assignee' => $assignee ?: null,
        'created_at' => $now,
        'updated_at' => $now,
    ];
    $tasks[] = $task;
    management_tasks_save($tasks);
    log_activity('task.create', 'Created task ' . $task['id'], $actor);
    return management_task_present($task);
}

function management_task_update(string $id, array $payload, string $actor): array
{
    $tasks = management_tasks_load();
    foreach ($tasks as &$task) {
        if (($task['id'] ?? '') !== $id) {
            continue;
        }
        if (isset($payload['title'])) {
            $title = sanitize_string($payload['title'], 160);
            if ($title === null || $title === '') {
                throw new InvalidArgumentException('Task title cannot be empty.');
            }
            $task['title'] = $title;
        }
        if (isset($payload['description'])) {
            $task['description'] = sanitize_string($payload['description'], 2000, true) ?? '';
        }
        if (isset($payload['priority'])) {
            $task['priority'] = strtolower((string) $payload['priority']);
        }
        if (isset($payload['status'])) {
            $task['status'] = $payload['status'];
        }
        if (array_key_exists('assignee', $payload)) {
            $task['assignee'] = sanitize_string($payload['assignee'], 160, true) ?: null;
        }
        $task['updated_at'] = now_ist();
        management_tasks_save($tasks);
        log_activity('task.update', 'Updated task ' . $id, $actor);
        return management_task_present($task);
    }
    unset($task);

    throw new RuntimeException('Task not found.');
}

function management_task_delete(string $id, string $actor): void
{
    $tasks = management_tasks_load();
    $count = count($tasks);
    $tasks = array_values(array_filter($tasks, static function ($task) use ($id) {
        return ($task['id'] ?? '') !== $id;
    }));
    if ($count === count($tasks)) {
        throw new RuntimeException('Task not found.');
    }
    management_tasks_save($tasks);
    log_activity('task.delete', 'Deleted task ' . $id, $actor);
}

function management_activity_log_list(array $filters = []): array
{
    $log = json_read(ACTIVITY_LOG_FILE, []);
    if (!is_array($log)) {
        return [];
    }
    $actor = strtolower((string) ($filters['actor'] ?? ''));
    $action = strtolower((string) ($filters['action'] ?? ''));
    $from = isset($filters['from']) ? strtotime((string) $filters['from']) : null;
    $to = isset($filters['to']) ? strtotime((string) $filters['to']) : null;

    $entries = array_filter($log, static function ($entry) use ($actor, $action, $from, $to) {
        if (!is_array($entry)) {
            return false;
        }
        if ($actor !== '' && strtolower((string) ($entry['actor'] ?? '')) !== $actor) {
            return false;
        }
        if ($action !== '' && strtolower((string) ($entry['action'] ?? '')) !== $action) {
            return false;
        }
        $timestamp = strtotime((string) ($entry['timestamp'] ?? '')) ?: null;
        if ($from !== null && ($timestamp === null || $timestamp < $from)) {
            return false;
        }
        if ($to !== null && ($timestamp === null || $timestamp > $to)) {
            return false;
        }
        return true;
    });

    usort($entries, static function ($a, $b) {
        return strcmp($b['timestamp'] ?? '', $a['timestamp'] ?? '');
    });

    return array_values(array_map(static function ($entry) {
        return [
            'id' => $entry['id'] ?? null,
            'timestamp' => $entry['timestamp'] ?? null,
            'action' => $entry['action'] ?? null,
            'details' => $entry['details'] ?? null,
            'actor' => $entry['actor'] ?? null,
        ];
    }, $entries));
}

function management_dashboard_cards(): array
{
    $users = management_users_load();
    $tickets = tickets_load();
    $approvals = management_approvals_load();
    $tasks = management_tasks_load();

    $openTickets = array_filter($tickets, static function ($ticket) {
        $status = strtolower((string) ($ticket['status'] ?? 'open'));
        return !in_array($status, ['resolved', 'closed'], true);
    });
    $pendingApprovals = array_filter($approvals, static function ($approval) {
        return strtolower((string) ($approval['status'] ?? 'pending')) === 'pending';
    });
    $activeTasks = array_filter($tasks, static function ($task) {
        return strtolower((string) ($task['status'] ?? '')) !== 'done';
    });

    return [
        'users' => count($users),
        'complaints_open' => count($openTickets),
        'approvals_pending' => count($pendingApprovals),
        'tasks_active' => count($activeTasks),
    ];
}

function management_resolve_date_range(array $filters): array
{
    $endInput = (string) ($filters['end'] ?? '');
    $startInput = (string) ($filters['start'] ?? '');
    $tz = new DateTimeZone('Asia/Kolkata');
    $end = $endInput !== '' && validator_date($endInput)
        ? DateTime::createFromFormat('Y-m-d H:i:s', $endInput . ' 23:59:59', $tz)
        : new DateTime('now', $tz);
    $start = $startInput !== '' && validator_date($startInput)
        ? DateTime::createFromFormat('Y-m-d H:i:s', $startInput . ' 00:00:00', $tz)
        : (clone $end)->modify('-29 days')->setTime(0, 0, 0);
    if ($start > $end) {
        [$start, $end] = [$end, $start];
    }
    return [$start, $end];
}

function management_timeseries_init(DatePeriod $period): array
{
    $series = [];
    foreach ($period as $date) {
        $series[$date->format('Y-m-d')] = [
            'tickets_closed' => 0,
            'avg_tat_hours' => null,
            'fcr_rate' => null,
            'lead_conversion_rate' => null,
            'service_visits' => 0,
            'defect_rate' => null,
        ];
    }
    return $series;
}

function management_analytics(array $filters = []): array
{
    [$start, $end] = management_resolve_date_range($filters);
    $period = new DatePeriod(clone $start, new DateInterval('P1D'), (clone $end)->modify('+1 day'));
    $timeseries = management_timeseries_init($period);

    $tickets = tickets_load();
    $closedCount = 0;
    $tatTotal = 0.0;
    $fcrCount = 0;

    foreach ($tickets as $ticket) {
        $closedAt = isset($ticket['closed_at']) ? strtotime((string) $ticket['closed_at']) : null;
        $createdAt = isset($ticket['created_at']) ? strtotime((string) $ticket['created_at']) : null;
        if ($closedAt !== null && $closedAt >= $start->getTimestamp() && $closedAt <= $end->getTimestamp()) {
            $closedCount++;
            if ($createdAt !== null) {
                $tatTotal += max(0, ($closedAt - $createdAt) / 3600);
            }
            $day = date('Y-m-d', $closedAt);
            if (isset($timeseries[$day])) {
                $timeseries[$day]['tickets_closed']++;
            }
            $history = array_values(array_filter($ticket['history'] ?? [], static function ($entry) {
                return ($entry['event'] ?? '') === 'status_change';
            }));
            if (count($history) <= 1) {
                $to = strtolower((string) ($history[0]['data']['to'] ?? $ticket['status'] ?? ''));
                if (in_array($to, ['resolved', 'closed'], true)) {
                    $fcrCount++;
                }
            }
        }
    }

    $avgTat = $closedCount > 0 ? round($tatTotal / $closedCount, 2) : null;
    $fcrRate = $closedCount > 0 ? round(($fcrCount / $closedCount) * 100, 2) : null;

    $leads = leads_load();
    $customers = customers_load();
    $leadCount = 0;
    $conversions = 0;
    $leadIdsInRange = [];
    foreach ($leads as $lead) {
        $created = isset($lead['created_at']) ? strtotime((string) $lead['created_at']) : null;
        if ($created !== null && $created >= $start->getTimestamp() && $created <= $end->getTimestamp()) {
            $leadCount++;
            $leadIdsInRange[] = $lead['id'] ?? null;
        }
    }
    foreach ($customers as $customer) {
        $created = isset($customer['created_at']) ? strtotime((string) $customer['created_at']) : null;
        if ($created !== null && $created >= $start->getTimestamp() && $created <= $end->getTimestamp()) {
            if (!empty($customer['converted_from_lead']) && in_array($customer['converted_from_lead'], $leadIdsInRange, true)) {
                $conversions++;
            }
        }
    }
    $leadConversionRate = $leadCount > 0 ? round(($conversions / $leadCount) * 100, 2) : null;

    $warranty = warranty_registry_load();
    $visitsByTech = [];
    foreach ($warranty['assets'] as $asset) {
        foreach ($asset['service_visits'] ?? [] as $visit) {
            $visitTime = isset($visit['logged_at']) ? strtotime((string) $visit['logged_at']) : null;
            if ($visitTime === null || $visitTime < $start->getTimestamp() || $visitTime > $end->getTimestamp()) {
                continue;
            }
            $tech = $visit['technician'] ?? 'Technician';
            $visitsByTech[$tech] = ($visitsByTech[$tech] ?? 0) + 1;
            $day = date('Y-m-d', $visitTime);
            if (isset($timeseries[$day])) {
                $timeseries[$day]['service_visits']++;
            }
        }
    }
    $totalVisits = array_sum($visitsByTech);
    $avgProductivity = $visitsByTech ? round($totalVisits / count($visitsByTech), 2) : null;
    arsort($visitsByTech);
    $topTechnicians = [];
    foreach (array_slice($visitsByTech, 0, 5, true) as $name => $count) {
        $topTechnicians[] = ['technician' => $name, 'visits' => $count];
    }

    $ticketsCreatedInRange = 0;
    $defectTickets = 0;
    foreach ($tickets as $ticket) {
        $createdAt = isset($ticket['created_at']) ? strtotime((string) $ticket['created_at']) : null;
        if ($createdAt === null || $createdAt < $start->getTimestamp() || $createdAt > $end->getTimestamp()) {
            continue;
        }
        $ticketsCreatedInRange++;
        $tags = array_map('strtolower', $ticket['tags'] ?? []);
        $issueType = strtolower((string) ($ticket['metadata']['issue_type'] ?? ''));
        if (in_array('defect', $tags, true) || in_array('quality', $tags, true) || $issueType === 'defect') {
            $defectTickets++;
        }
        $day = date('Y-m-d', $createdAt);
        if (isset($timeseries[$day])) {
            $timeseries[$day]['defect_rate'] = 0.0; // placeholder, compute later
        }
    }
    $defectRate = $ticketsCreatedInRange > 0 ? round(($defectTickets / $ticketsCreatedInRange) * 100, 2) : null;

    $funnel = [
        'applications' => count($customers),
        'sanction_pending' => 0,
        'sanctioned' => 0,
        'inspection_scheduled' => 0,
        'inspection_done' => 0,
        'disbursement_pending' => 0,
        'disbursed' => 0,
    ];
    foreach ($customers as $customer) {
        $sanction = strtolower((string) ($customer['sanction_status'] ?? ''));
        $inspection = strtolower((string) ($customer['inspection_status'] ?? ''));
        $disbursement = strtolower((string) ($customer['disbursement_status'] ?? ''));
        if ($sanction === 'approved' || $sanction === 'sanctioned') {
            $funnel['sanctioned']++;
        } else {
            $funnel['sanction_pending']++;
        }
        if ($inspection === 'scheduled') {
            $funnel['inspection_scheduled']++;
        }
        if (in_array($inspection, ['completed', 'done'], true)) {
            $funnel['inspection_done']++;
        }
        if ($disbursement === 'disbursed') {
            $funnel['disbursed']++;
        } else {
            $funnel['disbursement_pending']++;
        }
    }

    foreach ($timeseries as $day => &$values) {
        if ($values['tickets_closed'] > 0) {
            $values['avg_tat_hours'] = $avgTat;
            $values['fcr_rate'] = $fcrRate;
        }
        if ($leadCount > 0) {
            $values['lead_conversion_rate'] = $leadConversionRate;
        }
        if ($ticketsCreatedInRange > 0) {
            $values['defect_rate'] = $defectRate;
        }
    }
    unset($values);

    return [
        'range' => [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
        ],
        'complaint_tat_hours' => $avgTat,
        'complaint_volume' => $closedCount,
        'fcr_rate' => $fcrRate,
        'installer_productivity' => [
            'average_visits' => $avgProductivity,
            'total_visits' => $totalVisits,
            'top_technicians' => $topTechnicians,
        ],
        'lead_to_customer_rate' => $leadConversionRate,
        'lead_volume' => $leadCount,
        'conversions' => $conversions,
        'pmsg_funnel' => $funnel,
        'defect_rate' => $defectRate,
        'timeseries' => $timeseries,
    ];
}

function management_analytics_export_csv(array $filters = []): string
{
    $analytics = management_analytics($filters);
    $rows = [];
    foreach ($analytics['timeseries'] as $day => $values) {
        $rows[] = [
            'date' => $day,
            'tickets_closed' => $values['tickets_closed'],
            'avg_tat_hours' => $values['avg_tat_hours'],
            'fcr_rate' => $values['fcr_rate'],
            'lead_conversion_rate' => $values['lead_conversion_rate'],
            'service_visits' => $values['service_visits'],
            'defect_rate' => $values['defect_rate'],
        ];
    }
    return csv_encode($rows, ['date', 'tickets_closed', 'avg_tat_hours', 'fcr_rate', 'lead_conversion_rate', 'service_visits', 'defect_rate']);
}

function management_alerts_load(): array
{
    $alerts = json_read(ALERTS_FILE, []);
    if (!is_array($alerts)) {
        return [];
    }
    return array_values(array_filter($alerts, 'is_array'));
}

function management_alerts_save(array $alerts): void
{
    usort($alerts, static function ($a, $b) {
        return strcmp($b['detected_at'] ?? '', $a['detected_at'] ?? '');
    });
    if (count($alerts) > 200) {
        $alerts = array_slice($alerts, 0, 200);
    }
    json_write(ALERTS_FILE, array_values($alerts));
}

function management_alert_present(array $alert): array
{
    $timeline = array_values(array_map(static function ($event) {
        return [
            'event' => $event['event'] ?? null,
            'actor' => $event['actor'] ?? null,
            'note' => $event['note'] ?? null,
            'timestamp' => $event['timestamp'] ?? null,
        ];
    }, $alert['timeline'] ?? []));
    return [
        'id' => $alert['id'] ?? null,
        'type' => $alert['type'] ?? null,
        'message' => $alert['message'] ?? null,
        'severity' => $alert['severity'] ?? 'medium',
        'status' => $alert['status'] ?? 'open',
        'detected_at' => $alert['detected_at'] ?? null,
        'metadata' => $alert['metadata'] ?? [],
        'timeline' => $timeline,
    ];
}

function management_alerts_existing_fingerprints(array $alerts): array
{
    $index = [];
    foreach ($alerts as $pos => $alert) {
        $fingerprint = $alert['fingerprint'] ?? null;
        if ($fingerprint) {
            $index[$fingerprint] = $pos;
        }
    }
    return $index;
}

function management_alerts_detect_bulk_deletes(array $existing): array
{
    $log = json_read(ACTIVITY_LOG_FILE, []);
    if (!is_array($log)) {
        return [];
    }
    $threshold = 3;
    $windowSeconds = 900; // 15 minutes
    $now = time();
    $buckets = [];
    foreach ($log as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $action = (string) ($entry['action'] ?? '');
        if (!str_contains($action, '.delete')) {
            continue;
        }
        $timestamp = strtotime((string) ($entry['timestamp'] ?? '')) ?: null;
        if ($timestamp === null || $timestamp < $now - 86400) {
            continue;
        }
        $parts = explode('.', $action);
        $resource = $parts[0] ?? 'record';
        $bucketKey = (int) floor($timestamp / $windowSeconds) * $windowSeconds;
        $key = $resource . ':' . $bucketKey;
        if (!isset($buckets[$key])) {
            $buckets[$key] = ['resource' => $resource, 'start' => $bucketKey, 'entries' => []];
        }
        $buckets[$key]['entries'][] = $entry;
    }

    $alerts = [];
    foreach ($buckets as $bucket) {
        if (count($bucket['entries']) < $threshold) {
            continue;
        }
        $fingerprint = 'bulk_delete:' . $bucket['resource'] . ':' . $bucket['start'];
        if (isset($existing[$fingerprint])) {
            continue;
        }
        $start = date('c', $bucket['start']);
        $alerts[] = [
            'id' => uuid('alert'),
            'fingerprint' => $fingerprint,
            'type' => 'bulk_delete',
            'message' => sprintf('%d %s records deleted within 15 minutes.', count($bucket['entries']), $bucket['resource']),
            'severity' => 'high',
            'status' => 'open',
            'detected_at' => now_ist(),
            'metadata' => [
                'resource' => $bucket['resource'],
                'window_started_at' => $start,
                'examples' => array_map(static function ($entry) {
                    return [
                        'actor' => $entry['actor'] ?? null,
                        'timestamp' => $entry['timestamp'] ?? null,
                        'details' => $entry['details'] ?? null,
                    ];
                }, array_slice($bucket['entries'], 0, 5)),
            ],
            'timeline' => [
                ['event' => 'detected', 'actor' => 'system', 'note' => null, 'timestamp' => now_ist()],
            ],
        ];
    }
    return $alerts;
}

function management_alerts_detect_login_bursts(array $existing): array
{
    $log = json_read(ACTIVITY_LOG_FILE, []);
    if (!is_array($log)) {
        return [];
    }
    $threshold = 5;
    $windowSeconds = 600; // 10 minutes
    $now = time();
    $buckets = [];
    foreach ($log as $entry) {
        if (($entry['action'] ?? '') !== 'login') {
            continue;
        }
        $timestamp = strtotime((string) ($entry['timestamp'] ?? '')) ?: null;
        if ($timestamp === null || $timestamp < $now - 86400) {
            continue;
        }
        $bucketKey = (int) floor($timestamp / $windowSeconds) * $windowSeconds;
        if (!isset($buckets[$bucketKey])) {
            $buckets[$bucketKey] = [];
        }
        $buckets[$bucketKey][] = $entry;
    }
    $alerts = [];
    foreach ($buckets as $start => $entries) {
        if (count($entries) < $threshold) {
            continue;
        }
        $fingerprint = 'login_burst:' . $start;
        if (isset($existing[$fingerprint])) {
            continue;
        }
        $alerts[] = [
            'id' => uuid('alert'),
            'fingerprint' => $fingerprint,
            'type' => 'login_burst',
            'message' => sprintf('%d admin logins within 10 minutes.', count($entries)),
            'severity' => 'medium',
            'status' => 'open',
            'detected_at' => now_ist(),
            'metadata' => [
                'window_started_at' => date('c', $start),
                'actors' => array_values(array_unique(array_map(static function ($entry) {
                    return $entry['actor'] ?? 'unknown';
                }, $entries))),
            ],
            'timeline' => [
                ['event' => 'detected', 'actor' => 'system', 'note' => null, 'timestamp' => now_ist()],
            ],
        ];
    }
    return $alerts;
}

function management_alerts_detect_imports(array $existing): array
{
    $log = json_read(ACTIVITY_LOG_FILE, []);
    if (!is_array($log)) {
        return [];
    }
    $alerts = [];
    foreach ($log as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $action = (string) ($entry['action'] ?? '');
        if (!str_contains($action, '.import')) {
            continue;
        }
        $details = (string) ($entry['details'] ?? '');
        if (!preg_match('/([0-9]{2,})/', $details, $matches)) {
            continue;
        }
        $count = (int) $matches[1];
        if ($count < 100) {
            continue;
        }
        $parts = explode('.', $action);
        $resource = $parts[0] ?? 'records';
        $day = substr((string) ($entry['timestamp'] ?? ''), 0, 10);
        $fingerprint = 'import:' . $resource . ':' . $day;
        if (isset($existing[$fingerprint])) {
            continue;
        }
        $alerts[] = [
            'id' => uuid('alert'),
            'fingerprint' => $fingerprint,
            'type' => 'large_import',
            'message' => sprintf('Large %s import (%d records) detected.', $resource, $count),
            'severity' => 'medium',
            'status' => 'open',
            'detected_at' => now_ist(),
            'metadata' => [
                'resource' => $resource,
                'count' => $count,
                'actor' => $entry['actor'] ?? null,
                'timestamp' => $entry['timestamp'] ?? null,
            ],
            'timeline' => [
                ['event' => 'detected', 'actor' => 'system', 'note' => null, 'timestamp' => now_ist()],
            ],
        ];
    }
    return $alerts;
}

function management_alerts_detect_disk(array $existing): array
{
    $total = @disk_total_space(SERVER_BASE_PATH);
    $free = @disk_free_space(SERVER_BASE_PATH);
    if ($total <= 0 || $free < 0) {
        return [];
    }
    $percent = ($free / $total) * 100;
    if ($percent >= 15) {
        return [];
    }
    $fingerprint = 'disk_low:' . date('Y-m-d');
    if (isset($existing[$fingerprint])) {
        return [];
    }
    return [[
        'id' => uuid('alert'),
        'fingerprint' => $fingerprint,
        'type' => 'disk_space',
        'message' => sprintf('Disk space low: %.2f%% free.', $percent),
        'severity' => 'critical',
        'status' => 'open',
        'detected_at' => now_ist(),
        'metadata' => [
            'free_bytes' => $free,
            'total_bytes' => $total,
            'percent_free' => round($percent, 2),
        ],
        'timeline' => [
            ['event' => 'detected', 'actor' => 'system', 'note' => null, 'timestamp' => now_ist()],
        ],
    ]];
}

function management_alerts_refresh(): array
{
    $alerts = management_alerts_load();
    $existing = management_alerts_existing_fingerprints($alerts);
    $newAlerts = array_merge(
        management_alerts_detect_bulk_deletes($existing),
        management_alerts_detect_login_bursts($existing),
        management_alerts_detect_imports($existing),
        management_alerts_detect_disk($existing)
    );
    if ($newAlerts) {
        $alerts = array_merge($newAlerts, $alerts);
        management_alerts_save($alerts);
        $alerts = management_alerts_load();
    }
    return $alerts;
}

function management_alerts_list(): array
{
    $alerts = management_alerts_refresh();
    return array_values(array_map('management_alert_present', $alerts));
}

function management_alerts_update_status(string $id, string $status, string $actor, ?string $note = null): array
{
    $status = strtolower($status);
    if (!in_array($status, ['acknowledged', 'closed'], true)) {
        throw new InvalidArgumentException('Invalid alert status.');
    }
    $alerts = management_alerts_load();
    foreach ($alerts as &$alert) {
        if (($alert['id'] ?? '') !== $id) {
            continue;
        }
        $current = strtolower((string) ($alert['status'] ?? 'open'));
        if ($current === 'closed') {
            throw new RuntimeException('Alert already closed.');
        }
        $alert['status'] = $status;
        $alert['timeline'][] = [
            'event' => $status,
            'actor' => $actor,
            'note' => $note,
            'timestamp' => now_ist(),
        ];
        management_alerts_save($alerts);
        if ($status === 'closed') {
            log_activity('alert.closed', 'Closed alert ' . $id, $actor);
        } else {
            log_activity('alert.ack', 'Acknowledged alert ' . $id, $actor);
        }
        return management_alert_present($alert);
    }
    unset($alert);

    throw new RuntimeException('Alert not found.');
}

function management_audit_overview(array $filters = []): array
{
    [$start, $end] = management_resolve_date_range($filters);
    $log = management_activity_log_list([
        'from' => $start->format('Y-m-d'),
        'to' => $end->format('Y-m-d'),
    ]);
    $recentLogins = array_values(array_filter($log, static function ($entry) {
        return ($entry['action'] ?? '') === 'login';
    }));
    $recentDeletes = array_values(array_filter($log, static function ($entry) {
        return str_contains((string) ($entry['action'] ?? ''), '.delete');
    }));
    $recentImports = array_values(array_filter($log, static function ($entry) {
        return str_contains((string) ($entry['action'] ?? ''), '.import');
    }));

    return [
        'range' => [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
        ],
        'logins' => array_slice($recentLogins, 0, 20),
        'deletes' => array_slice($recentDeletes, 0, 20),
        'imports' => array_slice($recentImports, 0, 20),
        'alerts' => management_alerts_list(),
    ];
}

function management_logs_files(): array
{
    $logs = [];
    foreach (glob(LOG_PATH . '/*.log') as $path) {
        if (str_starts_with($path, LOG_ARCHIVE_PATH)) {
            continue;
        }
        $logs[] = [
            'name' => basename($path),
            'path' => $path,
            'size' => @filesize($path) ?: 0,
            'modified_at' => @filemtime($path) ? date('c', (int) filemtime($path)) : null,
        ];
    }
    return $logs;
}

function management_logs_archives_for(string $base): array
{
    $pattern = LOG_ARCHIVE_PATH . '/' . $base . '-*.log';
    $archives = [];
    foreach (glob($pattern) as $path) {
        $archives[] = [
            'name' => basename($path),
            'path' => $path,
            'size' => @filesize($path) ?: 0,
            'created_at' => @filemtime($path) ? date('c', (int) filemtime($path)) : null,
        ];
    }
    usort($archives, static function ($a, $b) {
        return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
    });
    return $archives;
}

function management_logs_purge_archives(int $days = 90): void
{
    $threshold = time() - ($days * 86400);
    foreach (glob(LOG_ARCHIVE_PATH . '/*.log') as $path) {
        $mtime = filemtime($path);
        if ($mtime !== false && $mtime < $threshold) {
            @unlink($path);
        }
    }
}

function management_logs_archive_file(string $name, string $actor, bool $manual = true): ?array
{
    $basename = basename($name);
    $source = LOG_PATH . '/' . $basename;
    if (!is_file($source)) {
        throw new RuntimeException('Log file not found.');
    }
    $contents = file_get_contents($source);
    if ($contents === false || trim($contents) === '') {
        return null;
    }
    $hash = substr(hash('sha256', $contents), 0, 12);
    $timestamp = date('Ymd_His');
    $archiveName = sprintf('%s-%s-%s.log', pathinfo($basename, PATHINFO_FILENAME), $timestamp, $hash);
    $archivePath = LOG_ARCHIVE_PATH . '/' . $archiveName;
    file_put_contents($archivePath, $contents, LOCK_EX);
    file_put_contents($source, '');
    log_activity('log.archive', ($manual ? 'Manual' : 'Automatic') . ' archive for ' . $basename, $actor);
    return [
        'name' => $archiveName,
        'size' => strlen($contents),
        'created_at' => date('c'),
    ];
}

function management_logs_status(): array
{
    management_logs_purge_archives();
    $logs = management_logs_files();
    $archivesCreated = [];
    foreach ($logs as $log) {
        if ($log['size'] > 512000) {
            $archive = management_logs_archive_file($log['name'], 'system', false);
            if ($archive) {
                $archivesCreated[] = $archive;
            }
        }
    }
    $status = [];
    foreach (management_logs_files() as $log) {
        $name = $log['name'];
        $archives = management_logs_archives_for(pathinfo($name, PATHINFO_FILENAME));
        $status[] = [
            'name' => $name,
            'size' => $log['size'],
            'modified_at' => $log['modified_at'],
            'latest_archive' => $archives[0]['name'] ?? null,
            'latest_archive_at' => $archives[0]['created_at'] ?? null,
        ];
    }
    return [
        'logs' => $status,
        'archives_created' => $archivesCreated,
    ];
}

function management_logs_run_archive(string $name, string $actor): ?array
{
    return management_logs_archive_file($name, $actor, true);
}

function management_logs_export(string $name, bool $fromArchive = false): string
{
    $basename = basename($name);
    $path = $fromArchive ? LOG_ARCHIVE_PATH . '/' . $basename : LOG_PATH . '/' . $basename;
    $real = realpath($path);
    if ($real === false || !str_starts_with($real, $fromArchive ? LOG_ARCHIVE_PATH : LOG_PATH)) {
        throw new RuntimeException('Invalid log requested.');
    }
    $contents = file_get_contents($real);
    if ($contents === false) {
        throw new RuntimeException('Unable to read log file.');
    }
    return base64_encode($contents);
}

function management_error_monitor_dashboard(): array
{
    $lines = [];
    if (is_file(SYSTEM_ERROR_FILE)) {
        $data = file(SYSTEM_ERROR_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (is_array($data)) {
            $lines = $data;
        }
    }
    $groups = [];
    foreach ($lines as $line) {
        if (!preg_match('/^\[(.*?)\]\s*(.*)$/', $line, $matches)) {
            continue;
        }
        $timestamp = $matches[1];
        $message = $matches[2];
        $signatureBase = preg_replace('/[0-9]+/', '#', strtolower($message));
        $signature = substr(sha1($signatureBase), 0, 12);
        if (!isset($groups[$signature])) {
            $groups[$signature] = [
                'signature' => $signature,
                'message' => mb_substr($message, 0, 200),
                'count' => 0,
                'first_seen' => $timestamp,
                'last_seen' => $timestamp,
                'examples' => [],
            ];
        }
        $groups[$signature]['count']++;
        if ($timestamp < ($groups[$signature]['first_seen'] ?? $timestamp)) {
            $groups[$signature]['first_seen'] = $timestamp;
        }
        if ($timestamp > ($groups[$signature]['last_seen'] ?? $timestamp)) {
            $groups[$signature]['last_seen'] = $timestamp;
        }
        if (count($groups[$signature]['examples']) < 3) {
            $groups[$signature]['examples'][] = mb_substr($message, -160);
        }
    }
    usort($groups, static function ($a, $b) {
        return $b['count'] <=> $a['count'];
    });

    $recent = array_slice(array_reverse($lines), 0, 20);

    $total = @disk_total_space(SERVER_BASE_PATH);
    $free = @disk_free_space(SERVER_BASE_PATH);
    $disk = null;
    if ($total > 0 && $free >= 0) {
        $disk = [
            'total_bytes' => $total,
            'free_bytes' => $free,
            'percent_free' => round(($free / $total) * 100, 2),
        ];
    }

    return [
        'groups' => array_values($groups),
        'recent' => $recent,
        'disk' => $disk,
    ];
}

function portal_otps_load(): array
{
    $records = json_read(PORTAL_OTP_FILE, []);
    if (!is_array($records)) {
        return [];
    }
    return array_values(array_filter($records, 'is_array'));
}

function portal_otps_save(array $records): void
{
    json_write(PORTAL_OTP_FILE, array_values($records));
}

function portal_otps_prune(array &$records): void
{
    $now = time();
    $records = array_values(array_filter($records, static function ($record) use ($now) {
        return ($record['expires_at'] ?? 0) >= $now;
    }));
}

function portal_customer_find(string $identifier): ?array
{
    $identifier = trim($identifier);
    if ($identifier === '') {
        return null;
    }
    $normalizedPhone = preg_replace('/\D+/', '', $identifier);
    foreach (customers_load() as $customer) {
        if (strcasecmp((string) ($customer['email'] ?? ''), $identifier) === 0) {
            return $customer;
        }
        if ($normalizedPhone !== '') {
            $customerPhone = preg_replace('/\D+/', '', (string) ($customer['phone'] ?? ''));
            if ($customerPhone !== '' && $customerPhone === $normalizedPhone) {
                return $customer;
            }
        }
    }
    return null;
}

function portal_issue_login_otp(string $customerId, string $channel, string $destination): array
{
    $records = portal_otps_load();
    portal_otps_prune($records);
    $code = (string) random_int(100000, 999999);
    $records[] = [
        'id' => uuid('otp'),
        'customer_id' => $customerId,
        'channel' => $channel,
        'destination' => $destination,
        'otp_hash' => password_hash($code, PASSWORD_DEFAULT),
        'expires_at' => time() + 600,
        'issued_at' => now_ist(),
        'attempts' => 0,
    ];
    portal_otps_save($records);
    $logLine = sprintf('[%s] OTP %s sent to %s via %s for customer %s%s', now_ist(), $code, $destination, $channel, $customerId, PHP_EOL);
    file_put_contents(PORTAL_OTP_LOG_FILE, $logLine, FILE_APPEND);
    return ['expires_at' => date('c', time() + 600)];
}

function portal_verify_login_otp(string $customerId, string $otp): bool
{
    $otp = trim($otp);
    if ($otp === '') {
        return false;
    }
    $records = portal_otps_load();
    $now = time();
    $matched = false;
    foreach ($records as $index => $record) {
        if (($record['customer_id'] ?? '') !== $customerId) {
            continue;
        }
        if (($record['expires_at'] ?? 0) < $now) {
            unset($records[$index]);
            continue;
        }
        if (($record['attempts'] ?? 0) >= 5) {
            continue;
        }
        if (password_verify($otp, $record['otp_hash'] ?? '')) {
            unset($records[$index]);
            $matched = true;
            break;
        }
        $records[$index]['attempts'] = ($record['attempts'] ?? 0) + 1;
    }
    portal_otps_save(array_values($records));
    return $matched;
}

function portal_customer_snapshot(string $customerId): array
{
    $customers = customers_load();
    $customer = null;
    foreach ($customers as $record) {
        if (($record['id'] ?? '') === $customerId) {
            $customer = $record;
            break;
        }
    }
    if (!$customer) {
        throw new RuntimeException('Customer not found.');
    }

    $tickets = [];
    foreach (tickets_load() as $ticket) {
        if (($ticket['customer_id'] ?? '') === $customerId) {
            $tickets[] = ticket_present($ticket);
        }
    }

    $assets = [];
    foreach (warranty_registry_load()['assets'] as $asset) {
        if (($asset['customer_id'] ?? '') === $customerId) {
            $assets[] = warranty_asset_present($asset);
        }
    }

    $documents = documents_vault_list(['customer_id' => $customerId]);
    $communications = array_slice(communication_log_list(['customer_id' => $customerId]), 0, 20);

    $stage = 'Application Received';
    $sanction = strtolower((string) ($customer['sanction_status'] ?? ''));
    $inspection = strtolower((string) ($customer['inspection_status'] ?? ''));
    $disbursement = strtolower((string) ($customer['disbursement_status'] ?? ''));
    if ($sanction === 'approved' || $sanction === 'sanctioned') {
        $stage = 'Sanctioned';
    }
    if (in_array($inspection, ['completed', 'done'], true)) {
        $stage = 'Inspection Complete';
    }
    if ($disbursement === 'disbursed') {
        $stage = 'Subsidy Disbursed';
    }

    return [
        'customer' => [
            'id' => $customer['id'] ?? null,
            'full_name' => $customer['full_name'] ?? null,
            'email' => $customer['email'] ?? null,
            'phone' => $customer['phone'] ?? null,
            'state' => $customer['state'] ?? null,
            'pmsg_application_no' => $customer['pmsg_application_no'] ?? null,
            'sanction_status' => $customer['sanction_status'] ?? null,
            'inspection_status' => $customer['inspection_status'] ?? null,
            'disbursement_status' => $customer['disbursement_status'] ?? null,
        ],
        'pmsg_stage' => $stage,
        'tickets' => $tickets,
        'amcs' => $assets,
        'documents' => $documents,
        'communications' => $communications,
    ];
}

function portal_record_update_request(string $customerId, string $message, string $actor): array
{
    $message = sanitize_string($message, 2000);
    if ($message === null || $message === '') {
        throw new InvalidArgumentException('Message is required.');
    }
    $entry = communication_log_add([
        'customer_id' => $customerId,
        'channel' => 'portal',
        'direction' => 'inbound',
        'summary' => 'Portal update request',
        'details' => $message,
    ], $actor);
    log_activity('portal.request_update', 'Customer ' . $customerId . ' requested an update', $actor);
    return $entry;
}

