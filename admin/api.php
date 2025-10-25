<?php

declare(strict_types=1);

require_once __DIR__ . '/../server/helpers.php';
require_once __DIR__ . '/../server/modules.php';

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

        case 'blog_generate':
            if ($method !== 'POST') {
                respond_json(['status' => 'error', 'message' => 'Method not allowed.'], 405);
            }
            $post = ai_generate_blog($input, $actor);
            respond_json(['status' => 'ok', 'post' => $post]);
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
