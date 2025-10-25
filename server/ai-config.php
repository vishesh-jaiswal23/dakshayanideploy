<?php

declare(strict_types=1);

require_once __DIR__ . '/../portal-state.php';
require_once __DIR__ . '/ai-automation.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$state = portal_load_state();
$defaults = portal_default_state()['ai_settings']['gemini'] ?? [];
$settings = portal_normalize_ai_settings($state['ai_settings'] ?? [])['gemini'] ?? $defaults;

$fileConfig = gemini_load_api_configuration();

$apiKey = (string) ($settings['api_key'] ?? '');
if ($apiKey === '' && isset($fileConfig['api_key'])) {
    $apiKey = (string) $fileConfig['api_key'];
}

$textModel = (string) ($settings['text_model'] ?? '');
if ($textModel === '') {
    if (isset($fileConfig['models']['text'])) {
        $textModel = (string) $fileConfig['models']['text'];
    } elseif (isset($fileConfig['default_model'])) {
        $textModel = (string) $fileConfig['default_model'];
    }
}

$imageModel = (string) ($settings['image_model'] ?? '');
if ($imageModel === '' && isset($fileConfig['models']['image'])) {
    $imageModel = (string) $fileConfig['models']['image'];
}

$ttsModel = (string) ($settings['tts_model'] ?? '');
if ($ttsModel === '' && isset($fileConfig['models']['tts'])) {
    $ttsModel = (string) $fileConfig['models']['tts'];
}

$response = [
    'provider' => 'gemini',
    'apiKey' => $apiKey,
    'models' => [
        'text' => $textModel,
        'image' => $imageModel,
        'tts' => $ttsModel,
    ],
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
