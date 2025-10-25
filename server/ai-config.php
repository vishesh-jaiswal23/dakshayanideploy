<?php

declare(strict_types=1);

require_once __DIR__ . '/../portal-state.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$state = portal_load_state();
$defaults = portal_default_state()['ai_settings']['gemini'] ?? [];
$settings = portal_normalize_ai_settings($state['ai_settings'] ?? [])['gemini'] ?? $defaults;

$response = [
    'provider' => 'gemini',
    'apiKey' => (string) ($settings['api_key'] ?? ''),
    'models' => [
        'text' => (string) ($settings['text_model'] ?? ''),
        'image' => (string) ($settings['image_model'] ?? ''),
        'tts' => (string) ($settings['tts_model'] ?? ''),
    ],
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
