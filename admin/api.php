<?php

declare(strict_types=1);

require_once __DIR__ . '/../server/helpers.php';
require_once __DIR__ . '/../server/modules.php';
require_once __DIR__ . '/lib.php';

ensure_session();
server_bootstrap();

$user = get_authenticated_user();
if (!$user || ($user['role'] ?? '') !== 'admin') {
    respond_json(['status' => 'error', 'message' => 'Authentication required.'], 401);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    require_csrf_token();
} else {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!rate_limit_get('api:' . $ip . ':' . $action, 90, 300)) {
        respond_json(['status' => 'error', 'message' => 'Too many requests. Please slow down.'], 429);
    }
}

$input = [];
if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $input = $decoded;
        }
    }
}

try {
    $actor = $user['email'] ?? ($user['id'] ?? 'admin');

    switch ($action) {
        case 'get_site_settings':
            $settings = load_site_settings();
            respond_json(['status' => 'ok', 'settings' => $settings, 'csrf' => issue_csrf_token()]);
            break;

        case 'search':
            $term = (string) ($_GET['q'] ?? '');
            $results = admin_search($term);
            respond_json(['status' => 'ok', 'results' => $results]);
            break;

        case 'alerts_list':
            $alerts = management_alerts_list();
            $summary = [
                'total' => count($alerts),
                'unread' => count(array_filter($alerts, static fn($alert) => ($alert['status'] ?? 'open') === 'open')),
            ];
            respond_json(['status' => 'ok', 'alerts' => array_map(static function ($alert) {
                return [
                    'id' => $alert['id'],
                    'title' => ucfirst((string) ($alert['type'] ?? 'Alert')),
                    'message' => $alert['message'] ?? '',
                    'severity' => $alert['severity'] ?? 'medium',
                    'time' => $alert['detected_at'] ?? '',
                    'read' => ($alert['status'] ?? 'open') !== 'open',
                ];
            }, $alerts), 'summary' => $summary]);
            break;

        case 'system_health':
            $health = admin_system_health();
            respond_json(['status' => 'ok', 'disk' => $health['disk'], 'errors' => $health['errors'], 'records' => $health['records']]);
            break;

        case 'save_site_settings':
            $section = (string) ($input['section'] ?? '');
            $data = $input['data'] ?? null;
            if ($section === '' || !is_array($data)) {
                respond_json(['status' => 'error', 'message' => 'Invalid payload for site settings.'], 422);
            }
            $current = load_site_settings();
            if (!isset($current[$section])) {
                respond_json(['status' => 'error', 'message' => 'Unknown settings section.'], 404);
            }
            $current[$section] = array_merge($current[$section], $data);
            if (!save_site_settings($current)) {
                throw new RuntimeException('Unable to persist updated settings.');
            }
            log_activity('settings.save', 'Updated settings section ' . $section, $user['email'] ?? 'admin');
            respond_json(['status' => 'ok']);
            break;

        case 'alerts_acknowledge':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $alertId = (string) ($input['id'] ?? ($_GET['id'] ?? ''));
            if ($alertId === '') {
                respond_json(['status' => 'error', 'message' => 'Alert id required.'], 422);
            }
            management_alerts_update_status($alertId, 'acknowledged', $actor);
            respond_json(['status' => 'ok']);
            break;

        case 'alerts_dismiss':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $alertId = (string) ($input['id'] ?? ($_GET['id'] ?? ''));
            if ($alertId === '') {
                respond_json(['status' => 'error', 'message' => 'Alert id required.'], 422);
            }
            management_alerts_update_status($alertId, 'closed', $actor);
            respond_json(['status' => 'ok']);
            break;

        case 'reset_site_settings':
            $section = (string) ($input['section'] ?? '');
            if ($section === '') {
                respond_json(['status' => 'error', 'message' => 'Section is required.'], 422);
            }
            if (!reset_site_settings_section($section)) {
                respond_json(['status' => 'error', 'message' => 'Unable to reset section or section missing.'], 404);
            }
            log_activity('settings.reset', 'Reset settings section ' . $section, $user['email'] ?? 'admin');
            respond_json(['status' => 'ok']);
            break;

        case 'ai_settings.save':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $apiKey = sanitize_string($input['api_key'] ?? '', 512);
            if ($apiKey === null || $apiKey === '') {
                respond_json(['status' => 'error', 'message' => 'API key is required.'], 422);
            }
            $modelsPayload = $input['models'] ?? null;
            if (!is_array($modelsPayload)) {
                respond_json(['status' => 'error', 'message' => 'Model definitions missing.'], 422);
            }
            $models = [];
            foreach (['text', 'image', 'tts'] as $type) {
                $value = sanitize_string($modelsPayload[$type] ?? '', 160);
                if ($value === null || $value === '') {
                    respond_json(['status' => 'error', 'message' => strtoupper($type) . ' model code required.'], 422);
                }
                $models[$type] = $value;
            }
            $settings = ai_settings_get();
            $settings['api_key'] = $apiKey;
            $settings['models'] = $models;
            if (!ai_settings_store($settings)) {
                throw new RuntimeException('Unable to persist AI settings.');
            }
            log_activity('ai.settings.save', 'Updated AI settings', $actor);
            respond_json(['status' => 'ok', 'settings' => ai_settings_public_payload(ai_settings_get())]);
            break;

        case 'ai_settings.reset_defaults':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $defaults = ai_settings_defaults();
            if (!ai_settings_store($defaults)) {
                throw new RuntimeException('Unable to reset AI settings.');
            }
            log_activity('ai.settings.reset', 'Reset AI settings to defaults', $actor);
            respond_json(['status' => 'ok', 'settings' => ai_settings_public_payload(ai_settings_get())]);
            break;

        case 'ai_settings.test':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $settings = ai_settings_get();
            $apiKey = $settings['api_key'] ?? '';
            if ($apiKey === '') {
                respond_json(['status' => 'error', 'message' => 'Configure the API key before testing.'], 422);
            }
            $available = $settings['models'];
            $requested = $input['models'] ?? null;
            $targets = [];
            if (is_array($requested)) {
                foreach ($requested as $type) {
                    $type = strtolower((string) $type);
                    if (isset($available[$type])) {
                        $targets[$type] = $available[$type];
                    }
                }
            }
            if (empty($targets)) {
                $targets = $available;
            }
            $results = [];
            $updated = $settings['last_test_results'] ?? [];
            foreach ($targets as $type => $modelCode) {
                $timestamp = now_ist();
                try {
                    ai_settings_ping_model($type, $modelCode, $apiKey);
                    $results[$type] = ['status' => 'pass', 'reason' => 'Connection successful', 'tested_at' => $timestamp];
                    $updated[$type] = ['status' => 'pass', 'message' => 'Connection successful', 'tested_at' => $timestamp];
                } catch (Throwable $exception) {
                    $message = trim($exception->getMessage());
                    if (mb_strlen($message) > 180) {
                        $message = mb_substr($message, 0, 180) . '…';
                    }
                    log_system_error('AI test failed for ' . $type, ['error' => $message]);
                    $results[$type] = ['status' => 'fail', 'reason' => $message, 'tested_at' => $timestamp];
                    $updated[$type] = ['status' => 'fail', 'message' => $message, 'tested_at' => $timestamp];
                }
            }
            $settings['last_test_results'] = $updated;
            if (!ai_settings_store($settings)) {
                throw new RuntimeException('Unable to persist test results.');
            }
            $summaryParts = [];
            foreach ($results as $type => $info) {
                $summaryParts[] = $type . ':' . $info['status'];
            }
            log_activity('ai.settings.test', 'AI Test Connection ' . implode(',', $summaryParts), $actor);
            respond_json(['status' => 'ok', 'results' => $results]);
            break;

        case 'ai_settings.export':
            $includeKey = false;
            if ($method === 'GET') {
                $includeKey = normalize_bool($_GET['include_key'] ?? false);
            } else {
                $includeKey = normalize_bool($input['include_key'] ?? false);
            }
            $settings = ai_settings_get();
            $export = ['models' => $settings['models']];
            if ($includeKey) {
                $export['api_key'] = $settings['api_key'];
            }
            $encoded = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                throw new RuntimeException('Unable to encode export payload.');
            }
            log_activity('ai.settings.export', 'Exported AI settings (include key: ' . ($includeKey ? 'yes' : 'no') . ')', $actor);
            respond_json(['status' => 'ok', 'export' => base64_encode($encoded)]);
            break;

        case 'ai_settings.import':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $import = $input['settings'] ?? null;
            if (!is_array($import)) {
                respond_json(['status' => 'error', 'message' => 'Import payload must be an object.'], 422);
            }
            $modelsPayload = $import['models'] ?? null;
            if (!is_array($modelsPayload)) {
                respond_json(['status' => 'error', 'message' => 'Import payload missing model codes.'], 422);
            }
            $models = [];
            foreach (['text', 'image', 'tts'] as $type) {
                $value = sanitize_string($modelsPayload[$type] ?? '', 160);
                if ($value === null || $value === '') {
                    respond_json(['status' => 'error', 'message' => strtoupper($type) . ' model code required in import.'], 422);
                }
                $models[$type] = $value;
            }
            $includeKey = normalize_bool($input['include_key'] ?? false);
            $settings = ai_settings_get();
            $settings['models'] = $models;
            if ($includeKey) {
                $apiKey = sanitize_string($import['api_key'] ?? '', 512);
                if ($apiKey === null || $apiKey === '') {
                    respond_json(['status' => 'error', 'message' => 'Import indicated API key but no value provided.'], 422);
                }
                $settings['api_key'] = $apiKey;
            }
            $settings['last_test_results'] = [];
            if (!ai_settings_store($settings)) {
                throw new RuntimeException('Unable to import AI settings.');
            }
            log_activity('ai.settings.import', 'Imported AI settings (include key: ' . ($includeKey ? 'yes' : 'no') . ')', $actor);
            respond_json(['status' => 'ok', 'settings' => ai_settings_public_payload(ai_settings_get())]);
            break;

        case 'models_list':
            respond_json(['status' => 'ok', 'data' => models_registry_list()]);
            break;

        case 'models_create':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $model = models_registry_create($input, $actor);
            respond_json(['status' => 'ok', 'model' => $model]);
            break;

        case 'models_update':
            if ($method !== 'POST' && $method !== 'PUT') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $modelId = (string) ($input['id'] ?? '');
            if ($modelId === '') {
                respond_json(['status' => 'error', 'message' => 'Model id required.'], 422);
            }
            $model = models_registry_update($modelId, $input, $actor);
            respond_json(['status' => 'ok', 'model' => $model]);
            break;

        case 'models_delete':
            if ($method !== 'POST' && $method !== 'DELETE') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $modelId = (string) ($input['id'] ?? ($_GET['id'] ?? ''));
            if ($modelId === '') {
                respond_json(['status' => 'error', 'message' => 'Model id required.'], 422);
            }
            models_registry_delete($modelId, $actor);
            respond_json(['status' => 'ok']);
            break;

        case 'models_set_default':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $modelId = (string) ($input['id'] ?? '');
            if ($modelId === '') {
                respond_json(['status' => 'error', 'message' => 'Model id required.'], 422);
            }
            $data = models_registry_set_default($modelId, $actor);
            respond_json(['status' => 'ok', 'data' => $data]);
            break;

        case 'models_test':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $modelId = (string) ($input['id'] ?? '');
            if ($modelId === '') {
                respond_json(['status' => 'error', 'message' => 'Model id required.'], 422);
            }
            $result = models_registry_test($modelId, $actor);
            respond_json(['status' => 'ok', 'result' => $result['result'] ?? null, 'details' => $result]);
            break;

        case 'models_export':
            $includeKeys = false;
            if ($method === 'GET') {
                $includeKeys = normalize_bool($_GET['include_keys'] ?? false);
            } else {
                $includeKeys = normalize_bool($input['include_keys'] ?? false);
            }
            $json = models_registry_export($includeKeys);
            respond_json(['status' => 'ok', 'export' => base64_encode($json)]);
            break;

        case 'models_import':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $models = $input['models'] ?? null;
            if (!is_array($models)) {
                respond_json(['status' => 'error', 'message' => 'Models payload must be an array.'], 422);
            }
            $includeKeys = normalize_bool($input['include_keys'] ?? false);
            $result = models_registry_import($models, $includeKeys, $actor);
            respond_json(['status' => 'ok', 'result' => $result]);
            break;

        case 'blog_list':
            $filters = [
                'status' => $_GET['status'] ?? null,
                'search' => $_GET['search'] ?? null,
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null,
            ];
            respond_json(['status' => 'ok', 'posts' => blog_posts_list($filters)]);
            break;

        case 'blog_save':
            if (!in_array($method, ['POST', 'PUT'], true)) {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $post = blog_post_upsert($input, $actor);
            respond_json(['status' => 'ok', 'post' => $post]);
            break;

        case 'blog_publish':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $postId = (string) ($input['id'] ?? '');
            if ($postId === '') {
                respond_json(['status' => 'error', 'message' => 'Post id required.'], 422);
            }
            $post = blog_post_publish($postId, $actor);
            respond_json(['status' => 'ok', 'post' => $post]);
            break;

        case 'blog_delete':
            if (!in_array($method, ['POST', 'DELETE'], true)) {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $postId = (string) ($input['id'] ?? ($_GET['id'] ?? ''));
            if ($postId === '') {
                respond_json(['status' => 'error', 'message' => 'Post id required.'], 422);
            }
            blog_post_delete($postId, $actor);
            respond_json(['status' => 'ok']);
            break;

        case 'blog_generate':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $post = ai_generate_blog($input, $actor);
            respond_json(['status' => 'ok', 'post' => $post]);
            break;

        case 'blog_get':
            $postId = (string) ($_GET['id'] ?? '');
            if ($postId === '') {
                respond_json(['status' => 'error', 'message' => 'Post id required.'], 422);
            }
            $post = blog_post_find($postId);
            if ($post === null) {
                respond_json(['status' => 'error', 'message' => 'Blog post not found.'], 404);
            }
            respond_json(['status' => 'ok', 'post' => $post]);
            break;

        case 'blog_generate_cover':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $image = ai_generate_blog_cover($input, $actor);
            respond_json(['status' => 'ok', 'image' => $image]);
            break;

        case 'blog_cover_upload':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $result = blog_upload_cover($input, $actor);
            respond_json(['status' => 'ok', 'image' => $result]);
            break;

        case 'ai_image_generate':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $image = ai_image_generate($input, $actor);
            respond_json(['status' => 'ok', 'image' => $image]);
            break;

        case 'ai_tts_generate':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $clip = ai_tts_generate($input, $actor);
            respond_json(['status' => 'ok', 'clip' => $clip]);
            break;

        case 'leads_list':
            $filters = [
                'state' => $_GET['state'] ?? null,
                'status' => $_GET['status'] ?? null,
                'source' => $_GET['source'] ?? null,
                'assigned_to' => $_GET['assigned_to'] ?? null,
                'search' => $_GET['search'] ?? null,
            ];
            respond_json(['status' => 'ok', 'leads' => leads_list($filters)]);
            break;

        case 'lead_create':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $lead = lead_create($input, $actor);
            respond_json(['status' => 'ok', 'lead' => $lead]);
            break;

        case 'lead_update':
            if (!in_array($method, ['POST', 'PUT'], true)) {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $leadId = (string) ($input['id'] ?? '');
            if ($leadId === '') {
                respond_json(['status' => 'error', 'message' => 'Lead id required.'], 422);
            }
            $lead = lead_update($leadId, $input, $actor);
            respond_json(['status' => 'ok', 'lead' => $lead]);
            break;

        case 'lead_delete':
            if (!in_array($method, ['POST', 'DELETE'], true)) {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $leadId = (string) ($input['id'] ?? ($_GET['id'] ?? ''));
            if ($leadId === '') {
                respond_json(['status' => 'error', 'message' => 'Lead id required.'], 422);
            }
            lead_delete($leadId, $actor);
            respond_json(['status' => 'ok']);
            break;

        case 'lead_convert':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $leadId = (string) ($input['id'] ?? '');
            if ($leadId === '') {
                respond_json(['status' => 'error', 'message' => 'Lead id required.'], 422);
            }
            $customer = lead_convert_to_customer($leadId, $input, $actor);
            respond_json(['status' => 'ok', 'customer' => $customer]);
            break;

        case 'customers_list':
            $filters = [
                'state' => $_GET['state'] ?? null,
                'sanction_status' => $_GET['sanction_status'] ?? null,
                'disbursement_status' => $_GET['disbursement_status'] ?? null,
                'documents_verified' => $_GET['documents_verified'] ?? null,
                'search' => $_GET['search'] ?? null,
            ];
            respond_json(['status' => 'ok', 'customers' => customers_list($filters)]);
            break;

        case 'customer_create':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $customer = customer_create($input, $actor);
            respond_json(['status' => 'ok', 'customer' => $customer]);
            break;

        case 'customer_update':
            if (!in_array($method, ['POST', 'PUT'], true)) {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $customerId = (string) ($input['id'] ?? '');
            if ($customerId === '') {
                respond_json(['status' => 'error', 'message' => 'Customer id required.'], 422);
            }
            $customer = customer_update($customerId, $input, $actor);
            respond_json(['status' => 'ok', 'customer' => $customer]);
            break;

        case 'customer_delete':
            if (!in_array($method, ['POST', 'DELETE'], true)) {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $customerId = (string) ($input['id'] ?? ($_GET['id'] ?? ''));
            if ($customerId === '') {
                respond_json(['status' => 'error', 'message' => 'Customer id required.'], 422);
            }
            customer_delete($customerId, $actor);
            respond_json(['status' => 'ok']);
            break;

        case 'leads_export':
            $filters = [
                'state' => $_GET['state'] ?? null,
                'status' => $_GET['status'] ?? null,
                'source' => $_GET['source'] ?? null,
                'assigned_to' => $_GET['assigned_to'] ?? null,
                'search' => $_GET['search'] ?? null,
            ];
            $csv = leads_export_csv($filters);
            respond_json(['status' => 'ok', 'csv' => base64_encode($csv)]);
            break;

        case 'customers_export':
            $filters = [
                'state' => $_GET['state'] ?? null,
                'sanction_status' => $_GET['sanction_status'] ?? null,
                'disbursement_status' => $_GET['disbursement_status'] ?? null,
                'documents_verified' => $_GET['documents_verified'] ?? null,
                'search' => $_GET['search'] ?? null,
            ];
            $csv = customers_export_csv($filters);
            respond_json(['status' => 'ok', 'csv' => base64_encode($csv)]);
            break;

        case 'leads_import':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $csvData = $input['csv'] ?? '';
            if (!is_string($csvData) || trim($csvData) === '') {
                respond_json(['status' => 'error', 'message' => 'CSV data is required.'], 422);
            }
            $isBase64 = normalize_bool($input['is_base64'] ?? false);
            if ($isBase64) {
                $decoded = base64_decode($csvData, true);
                if ($decoded === false) {
                    respond_json(['status' => 'error', 'message' => 'Invalid base64 csv payload.'], 422);
                }
                $csvData = $decoded;
            }
            $strategy = strtolower((string) ($input['duplicate_strategy'] ?? 'skip'));
            if (!in_array($strategy, ['skip', 'update', 'new'], true)) {
                respond_json(['status' => 'error', 'message' => 'Invalid duplicate strategy.'], 422);
            }
            $result = leads_import_csv($csvData, $strategy, $actor);
            respond_json(['status' => 'ok', 'result' => $result]);
            break;

        case 'customers_import':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $csvData = $input['csv'] ?? '';
            if (!is_string($csvData) || trim($csvData) === '') {
                respond_json(['status' => 'error', 'message' => 'CSV data is required.'], 422);
            }
            $isBase64 = normalize_bool($input['is_base64'] ?? false);
            if ($isBase64) {
                $decoded = base64_decode($csvData, true);
                if ($decoded === false) {
                    respond_json(['status' => 'error', 'message' => 'Invalid base64 csv payload.'], 422);
                }
                $csvData = $decoded;
            }
            $strategy = strtolower((string) ($input['duplicate_strategy'] ?? 'skip'));
            if (!in_array($strategy, ['skip', 'update', 'new'], true)) {
                respond_json(['status' => 'error', 'message' => 'Invalid duplicate strategy.'], 422);
            }
            $result = customers_import_csv($csvData, $strategy, $actor);
            respond_json(['status' => 'ok', 'result' => $result]);
            break;

        case 'referrers_list':
            respond_json(['status' => 'ok', 'referrers' => referrers_list()]);
            break;

        case 'referrer_create':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $referrer = referrer_create($input, $actor);
            respond_json(['status' => 'ok', 'referrer' => $referrer]);
            break;

        case 'referrer_update':
            if (!in_array($method, ['POST', 'PUT'], true)) {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $refId = (string) ($input['id'] ?? '');
            if ($refId === '') {
                respond_json(['status' => 'error', 'message' => 'Referrer id required.'], 422);
            }
            $referrer = referrer_update($refId, $input, $actor);
            respond_json(['status' => 'ok', 'referrer' => $referrer]);
            break;

        case 'referrer_delete':
            if (!in_array($method, ['POST', 'DELETE'], true)) {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $refId = (string) ($input['id'] ?? ($_GET['id'] ?? ''));
            if ($refId === '') {
                respond_json(['status' => 'error', 'message' => 'Referrer id required.'], 422);
            }
            referrer_delete($refId, $actor);
            respond_json(['status' => 'ok']);
            break;

        case 'referrers_export':
            $csv = referrers_export_csv();
            respond_json(['status' => 'ok', 'csv' => base64_encode($csv)]);
            break;

        case 'tickets_list':
            $filters = [
                'status' => $_GET['status'] ?? null,
                'priority' => $_GET['priority'] ?? null,
                'assignee' => $_GET['assignee'] ?? null,
                'customer_id' => $_GET['customer_id'] ?? null,
                'tag' => $_GET['tag'] ?? null,
                'search' => $_GET['search'] ?? null,
            ];
            respond_json(['status' => 'ok', 'tickets' => tickets_list($filters)]);
            break;

        case 'ticket_create':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $ticket = ticket_create($input, $actor);
            respond_json(['status' => 'ok', 'ticket' => $ticket]);
            break;

        case 'ticket_update':
            if (!in_array($method, ['POST', 'PUT'], true)) {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $ticketId = (string) ($input['id'] ?? '');
            if ($ticketId === '') {
                respond_json(['status' => 'error', 'message' => 'Ticket id required.'], 422);
            }
            $ticket = ticket_update($ticketId, $input, $actor);
            respond_json(['status' => 'ok', 'ticket' => $ticket]);
            break;

        case 'ticket_delete':
            if (!in_array($method, ['POST', 'DELETE'], true)) {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $ticketId = (string) ($input['id'] ?? ($_GET['id'] ?? ''));
            if ($ticketId === '') {
                respond_json(['status' => 'error', 'message' => 'Ticket id required.'], 422);
            }
            ticket_delete($ticketId, $actor);
            respond_json(['status' => 'ok']);
            break;

        case 'ticket_add_note':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $ticketId = (string) ($input['ticket_id'] ?? '');
            if ($ticketId === '') {
                respond_json(['status' => 'error', 'message' => 'Ticket id required.'], 422);
            }
            $ticket = ticket_add_note($ticketId, $input, $actor);
            respond_json(['status' => 'ok', 'ticket' => $ticket]);
            break;

        case 'warranty_assets_list':
            $filters = [
                'customer_id' => $_GET['customer_id'] ?? null,
                'segment' => $_GET['segment'] ?? null,
                'due_before' => $_GET['due_before'] ?? null,
                'search' => $_GET['search'] ?? null,
            ];
            respond_json(['status' => 'ok', 'assets' => warranty_assets_list($filters)]);
            break;

        case 'warranty_asset_create':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $asset = warranty_asset_create($input, $actor);
            respond_json(['status' => 'ok', 'asset' => $asset]);
            break;

        case 'warranty_asset_update':
            if (!in_array($method, ['POST', 'PUT'], true)) {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $assetId = (string) ($input['id'] ?? '');
            if ($assetId === '') {
                respond_json(['status' => 'error', 'message' => 'Asset id required.'], 422);
            }
            $asset = warranty_asset_update($assetId, $input, $actor);
            respond_json(['status' => 'ok', 'asset' => $asset]);
            break;

        case 'warranty_asset_delete':
            if (!in_array($method, ['POST', 'DELETE'], true)) {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $assetId = (string) ($input['id'] ?? ($_GET['id'] ?? ''));
            if ($assetId === '') {
                respond_json(['status' => 'error', 'message' => 'Asset id required.'], 422);
            }
            warranty_asset_delete($assetId, $actor);
            respond_json(['status' => 'ok']);
            break;

        case 'warranty_asset_visit':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $assetId = (string) ($input['id'] ?? '');
            if ($assetId === '') {
                respond_json(['status' => 'error', 'message' => 'Asset id required.'], 422);
            }
            $asset = warranty_asset_add_visit($assetId, $input, $actor);
            respond_json(['status' => 'ok', 'asset' => $asset]);
            break;

        case 'warranty_asset_reminder':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $assetId = (string) ($input['id'] ?? '');
            if ($assetId === '') {
                respond_json(['status' => 'error', 'message' => 'Asset id required.'], 422);
            }
            $asset = warranty_asset_add_reminder($assetId, $input, $actor);
            respond_json(['status' => 'ok', 'asset' => $asset]);
            break;

        case 'warranty_asset_reminder_status':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $assetId = (string) ($input['asset_id'] ?? '');
            $reminderId = (string) ($input['reminder_id'] ?? '');
            $statusValue = (string) ($input['status'] ?? '');
            if ($assetId === '' || $reminderId === '' || $statusValue === '') {
                respond_json(['status' => 'error', 'message' => 'Asset id, reminder id, and status are required.'], 422);
            }
            $asset = warranty_asset_update_reminder_status($assetId, $reminderId, $statusValue, $actor);
            respond_json(['status' => 'ok', 'asset' => $asset]);
            break;

        case 'warranty_export':
            $filters = [
                'customer_id' => $_GET['customer_id'] ?? null,
                'segment' => $_GET['segment'] ?? null,
                'due_before' => $_GET['due_before'] ?? null,
                'search' => $_GET['search'] ?? null,
            ];
            $csv = warranty_amc_export_csv($filters);
            respond_json(['status' => 'ok', 'csv' => base64_encode($csv)]);
            break;

        case 'documents_list':
            $filters = [
                'customer_id' => $_GET['customer_id'] ?? null,
                'ticket_id' => $_GET['ticket_id'] ?? null,
                'tag' => $_GET['tag'] ?? null,
                'search' => $_GET['search'] ?? null,
            ];
            respond_json(['status' => 'ok', 'documents' => documents_vault_list($filters)]);
            break;

        case 'document_upload':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $document = documents_vault_record_upload($input, $actor);
            respond_json(['status' => 'ok', 'document' => $document]);
            break;

        case 'documents_search':
            $query = (string) ($_GET['q'] ?? ($input['query'] ?? ''));
            $filters = [
                'customer_id' => $_GET['customer_id'] ?? ($input['customer_id'] ?? null),
                'ticket_id' => $_GET['ticket_id'] ?? ($input['ticket_id'] ?? null),
                'tag' => $_GET['tag'] ?? ($input['tag'] ?? null),
            ];
            respond_json(['status' => 'ok', 'documents' => documents_vault_search($query, $filters)]);
            break;

        case 'document_token':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $documentId = (string) ($input['document_id'] ?? '');
            $versionId = (string) ($input['version_id'] ?? '');
            if ($documentId === '' || $versionId === '') {
                respond_json(['status' => 'error', 'message' => 'Document id and version id are required.'], 422);
            }
            $ttl = isset($input['ttl']) ? (int) $input['ttl'] : 900;
            $token = documents_vault_generate_download_token($documentId, $versionId, $actor, $ttl);
            respond_json(['status' => 'ok', 'token' => $token]);
            break;

        case 'subsidy_list':
            $filters = [
                'stage' => $_GET['stage'] ?? null,
                'discom' => $_GET['discom'] ?? null,
                'search' => $_GET['search'] ?? null,
            ];
            respond_json(['status' => 'ok', 'records' => subsidy_records_list($filters)]);
            break;

        case 'subsidy_create':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $record = subsidy_record_create($input, $actor);
            respond_json(['status' => 'ok', 'record' => $record]);
            break;

        case 'subsidy_update':
            if (!in_array($method, ['POST', 'PUT'], true)) {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $recordId = (string) ($input['id'] ?? '');
            if ($recordId === '') {
                respond_json(['status' => 'error', 'message' => 'Record id required.'], 422);
            }
            $record = subsidy_record_update($recordId, $input, $actor);
            respond_json(['status' => 'ok', 'record' => $record]);
            break;

        case 'subsidy_transition':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $recordId = (string) ($input['id'] ?? '');
            $stage = (string) ($input['stage'] ?? '');
            if ($recordId === '' || $stage === '') {
                respond_json(['status' => 'error', 'message' => 'Record id and stage are required.'], 422);
            }
            $record = subsidy_record_transition_stage($recordId, $stage, $input, $actor);
            respond_json(['status' => 'ok', 'record' => $record]);
            break;

        case 'subsidy_dashboard':
            respond_json(['status' => 'ok', 'dashboard' => subsidy_dashboard_metrics()]);
            break;

        case 'subsidy_export':
            $filters = [
                'stage' => $_GET['stage'] ?? null,
                'discom' => $_GET['discom'] ?? null,
                'search' => $_GET['search'] ?? null,
            ];
            $csv = subsidy_records_export_csv($filters);
            respond_json(['status' => 'ok', 'csv' => base64_encode($csv)]);
            break;

        case 'data_quality_scan':
            $refresh = normalize_bool($_GET['refresh'] ?? ($input['refresh'] ?? true));
            $report = data_quality_scan($refresh);
            respond_json(['status' => 'ok', 'report' => $report]);
            break;

        case 'data_quality_dashboard':
            $refresh = normalize_bool($_GET['refresh'] ?? ($input['refresh'] ?? false));
            respond_json(['status' => 'ok', 'dashboard' => data_quality_dashboard($refresh)]);
            break;

        case 'data_quality_export':
            $cache = data_quality_get_cache(false);
            $csv = data_quality_export_errors_csv($cache['issues'] ?? []);
            respond_json(['status' => 'ok', 'csv' => base64_encode($csv)]);
            break;

        case 'data_quality_merge':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $result = data_quality_merge($input, $actor);
            respond_json(['status' => 'ok', 'record' => $result]);
            break;

        case 'communications_list':
            $filters = [
                'customer_id' => $_GET['customer_id'] ?? null,
                'ticket_id' => $_GET['ticket_id'] ?? null,
                'channel' => $_GET['channel'] ?? null,
                'direction' => $_GET['direction'] ?? null,
                'from' => $_GET['from'] ?? null,
                'to' => $_GET['to'] ?? null,
            ];
            respond_json(['status' => 'ok', 'entries' => communication_log_list($filters)]);
            break;

        case 'communications_add':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $entry = communication_log_add($input, $actor);
            respond_json(['status' => 'ok', 'entry' => $entry]);
            break;

        case 'communications_export':
            $filters = [
                'customer_id' => $_GET['customer_id'] ?? null,
                'ticket_id' => $_GET['ticket_id'] ?? null,
                'channel' => $_GET['channel'] ?? null,
                'direction' => $_GET['direction'] ?? null,
                'from' => $_GET['from'] ?? null,
                'to' => $_GET['to'] ?? null,
            ];
            $csv = communication_log_export_csv($filters);
            respond_json(['status' => 'ok', 'csv' => base64_encode($csv)]);
            break;

        case 'analytics_dashboard':
            $filters = [
                'start' => $_GET['start'] ?? ($input['start'] ?? null),
                'end' => $_GET['end'] ?? ($input['end'] ?? null),
            ];
            respond_json(['status' => 'ok', 'analytics' => management_analytics($filters)]);
            break;

        case 'analytics_export':
            $filters = [
                'start' => $_GET['start'] ?? ($input['start'] ?? null),
                'end' => $_GET['end'] ?? ($input['end'] ?? null),
            ];
            $csv = management_analytics_export_csv($filters);
            respond_json(['status' => 'ok', 'csv' => base64_encode($csv)]);
            break;

        case 'audit_overview':
            $filters = [
                'start' => $_GET['start'] ?? ($input['start'] ?? null),
                'end' => $_GET['end'] ?? ($input['end'] ?? null),
            ];
            respond_json(['status' => 'ok', 'audit' => management_audit_overview($filters)]);
            break;

        case 'alerts_list':
            respond_json(['status' => 'ok', 'alerts' => management_alerts_list()]);
            break;

        case 'alerts_ack':
        case 'alerts_close':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $alertId = (string) ($input['id'] ?? '');
            if ($alertId === '') {
                respond_json(['status' => 'error', 'message' => 'Alert id is required.'], 422);
            }
            $status = $action === 'alerts_ack' ? 'acknowledged' : 'closed';
            $updated = management_alerts_update_status($alertId, $status, $actor, $input['note'] ?? null);
            respond_json(['status' => 'ok', 'alert' => $updated]);
            break;

        case 'logs_status':
            respond_json(['status' => 'ok', 'logs' => management_logs_status()]);
            break;

        case 'logs_archive':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $name = (string) ($input['name'] ?? '');
            if ($name === '') {
                respond_json(['status' => 'error', 'message' => 'Log name required.'], 422);
            }
            $archive = management_logs_run_archive($name, $actor);
            respond_json(['status' => 'ok', 'archive' => $archive]);
            break;

        case 'logs_export':
            $name = (string) ($_GET['name'] ?? ($input['name'] ?? ''));
            if ($name === '') {
                respond_json(['status' => 'error', 'message' => 'Log name required.'], 422);
            }
            $fromArchive = normalize_bool($_GET['archive'] ?? ($input['archive'] ?? false));
            $payload = management_logs_export($name, $fromArchive);
            respond_json(['status' => 'ok', 'log' => $payload]);
            break;

        case 'error_monitor':
            respond_json(['status' => 'ok', 'dashboard' => management_error_monitor_dashboard()]);
            break;

        case 'users_list':
            $filters = [
                'role' => $_GET['role'] ?? ($input['role'] ?? null),
                'status' => $_GET['status'] ?? ($input['status'] ?? null),
                'search' => $_GET['search'] ?? ($input['search'] ?? null),
            ];
            respond_json(['status' => 'ok', 'users' => management_users_list($filters)]);
            break;

        case 'user_create':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $userRecord = management_user_create($input, $actor);
            respond_json(['status' => 'ok', 'user' => $userRecord]);
            break;

        case 'user_update':
            if (!in_array($method, ['POST', 'PUT'], true)) {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $userId = (string) ($input['id'] ?? '');
            if ($userId === '') {
                respond_json(['status' => 'error', 'message' => 'User id required.'], 422);
            }
            $userRecord = management_user_update($userId, $input, $actor);
            respond_json(['status' => 'ok', 'user' => $userRecord]);
            break;

        case 'user_reset_password':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $userId = (string) ($input['id'] ?? '');
            $password = (string) ($input['password'] ?? '');
            if ($userId === '' || $password === '') {
                respond_json(['status' => 'error', 'message' => 'User id and password required.'], 422);
            }
            $forceReset = normalize_bool($input['force_reset'] ?? true);
            $userRecord = management_user_reset_password($userId, $password, $forceReset, $actor);
            respond_json(['status' => 'ok', 'user' => $userRecord]);
            break;

        case 'user_delete':
            if (!in_array($method, ['POST', 'DELETE'], true)) {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $userId = (string) ($input['id'] ?? ($_GET['id'] ?? ''));
            if ($userId === '') {
                respond_json(['status' => 'error', 'message' => 'User id required.'], 422);
            }
            management_user_delete($userId, $actor);
            respond_json(['status' => 'ok']);
            break;

        case 'approvals_list':
            $statusFilter = $_GET['status'] ?? ($input['status'] ?? null);
            respond_json(['status' => 'ok', 'approvals' => management_approvals_list($statusFilter)]);
            break;

        case 'approval_update':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $approvalId = (string) ($input['id'] ?? '');
            $status = (string) ($input['status'] ?? '');
            if ($approvalId === '' || $status === '') {
                respond_json(['status' => 'error', 'message' => 'Approval id and status required.'], 422);
            }
            $record = management_approval_update_status($approvalId, $status, $actor, $input['note'] ?? null);
            respond_json(['status' => 'ok', 'approval' => $record]);
            break;

        case 'tasks_board':
            respond_json(['status' => 'ok', 'board' => management_tasks_board()]);
            break;

        case 'task_create':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $task = management_task_create($input, $actor);
            respond_json(['status' => 'ok', 'task' => $task]);
            break;

        case 'task_update':
            if (!in_array($method, ['POST', 'PUT'], true)) {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $taskId = (string) ($input['id'] ?? '');
            if ($taskId === '') {
                respond_json(['status' => 'error', 'message' => 'Task id required.'], 422);
            }
            $task = management_task_update($taskId, $input, $actor);
            respond_json(['status' => 'ok', 'task' => $task]);
            break;

        case 'task_delete':
            if (!in_array($method, ['POST', 'DELETE'], true)) {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $taskId = (string) ($input['id'] ?? ($_GET['id'] ?? ''));
            if ($taskId === '') {
                respond_json(['status' => 'error', 'message' => 'Task id required.'], 422);
            }
            management_task_delete($taskId, $actor);
            respond_json(['status' => 'ok']);
            break;

        case 'dashboard_cards':
            respond_json(['status' => 'ok', 'cards' => management_dashboard_cards()]);
            break;

        case 'activity_log':
            $filters = [
                'actor' => $_GET['actor'] ?? ($input['actor'] ?? null),
                'action' => $_GET['action'] ?? ($input['action'] ?? null),
                'from' => $_GET['from'] ?? ($input['from'] ?? null),
                'to' => $_GET['to'] ?? ($input['to'] ?? null),
            ];
            respond_json(['status' => 'ok', 'entries' => management_activity_log_list($filters)]);
            break;

        default:
            respond_json(['status' => 'error', 'message' => 'Unknown action.'], 400);
    }
} catch (Throwable $exception) {
    log_system_error('API exception: ' . $exception->getMessage(), [
        'action' => $action,
        'method' => $method,
    ]);
    respond_json(['status' => 'error', 'message' => 'Unexpected server error.'], 500);
}
