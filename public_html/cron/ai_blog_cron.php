<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/ai_helpers.php';

$result = ai_run_blog_generation();

try {
    ai_log_run($result['status'], $result['posts_created'], $result['error_message'], $result['raw_output']);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Failed to record AI automation log: ' . $exception->getMessage() . PHP_EOL);
}

if ($result['status'] === 'failed') {
    fwrite(STDERR, 'AI blog cron completed with errors: ' . ($result['error_message'] ?? 'Unknown error') . PHP_EOL);
    exit(1);
}

if ($result['status'] === 'partial') {
    fwrite(STDERR, 'AI blog cron completed partially: ' . ($result['error_message'] ?? 'Check logs for details') . PHP_EOL);
}

fwrite(
    STDOUT,
    sprintf(
        'AI blog cron completed with status %s (posts: %d)%s',
        $result['status'],
        $result['posts_created'],
        PHP_EOL
    )
);
