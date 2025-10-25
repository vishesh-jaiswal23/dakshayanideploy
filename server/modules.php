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

