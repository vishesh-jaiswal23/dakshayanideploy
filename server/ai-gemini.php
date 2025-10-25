<?php

declare(strict_types=1);

require_once __DIR__ . '/../portal-state.php';
require_once __DIR__ . '/ai-automation.php';

$options = getopt('', ['task::', 'force', 'help']);

if (isset($options['help'])) {
    echo "Gemini automation runner\n";
    echo "Usage: php server/ai-gemini.php [--task=news|blog|operations|all] [--force]\n";
    exit(0);
}

$taskOption = isset($options['task']) ? strtolower((string) $options['task']) : 'all';
$force = array_key_exists('force', $options);

$taskMap = [
    'news' => 'news_digest',
    'blog' => 'blog_research',
    'operations' => 'operations_watch',
    'ops' => 'operations_watch',
    'all' => 'all',
];

$targets = [];
if (isset($taskMap[$taskOption])) {
    if ($taskMap[$taskOption] !== 'all') {
        $targets = [$taskMap[$taskOption]];
    }
} elseif ($taskOption !== '') {
    fwrite(STDERR, "Unknown task: {$taskOption}\n");
    exit(1);
}

$state = portal_load_state();
gemini_set_portal_state($state);

try {
    $client = new GeminiClient();
} catch (RuntimeException $exception) {
    fwrite(STDERR, 'Gemini configuration error: ' . $exception->getMessage() . "\n");
    exit(1);
}

$automation = new GeminiAutomation($state, $client);
$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));

$results = $automation->runDueAutomations($now, $targets, $force);

$exitCode = 0;
$stateChanged = false;
foreach ($results as $result) {
    $status = strtoupper((string) ($result['status'] ?? 'unknown'));
    $message = (string) ($result['message'] ?? '');
    $automationKey = (string) ($result['automation'] ?? '');
    $line = $automationKey !== '' ? "{$automationKey}: {$status}" : $status;
    if ($message !== '') {
        $line .= ' - ' . $message;
    }
    echo $line . "\n";

    if (($result['status'] ?? '') === 'error') {
        $exitCode = 1;
    }

    if (($result['stateChanged'] ?? false) === true) {
        $stateChanged = true;
    }
}

if ($stateChanged) {
    if (!portal_save_state($state)) {
        fwrite(STDERR, "Failed to persist portal state after automation run.\n");
        $exitCode = 1;
    } else {
        echo "Portal state saved.\n";
    }
} else {
    echo "No state changes detected.\n";
}

exit($exitCode);
