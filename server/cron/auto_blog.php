<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../modules.php';

$lock = auto_blog_lock_acquire();
if ($lock === null) {
    if (auto_blog_lock_active()) {
        auto_blog_log_activity('skipped', 'lock', null, 'Another run is in progress');
    } else {
        log_system_error('Auto blog cron unable to acquire lock.');
    }
    exit(0);
}

try {
    server_bootstrap();
    $result = auto_blog_run(['trigger' => 'schedule']);
    if (($result['status'] ?? '') === 'error') {
        $attemptsUsed = (int) ($result['attempts_used'] ?? 0);
        $maxAttempts = (int) ($result['max_attempts'] ?? 0);
        if ($attemptsUsed < $maxAttempts && $maxAttempts > 0) {
            sleep(60);
            auto_blog_run(['trigger' => 'schedule_retry', 'force' => true]);
        }
    }
} catch (Throwable $exception) {
    log_system_error('Auto blog cron crashed', ['error' => $exception->getMessage()]);
    auto_blog_push_alert('Auto blog cron crashed', ['message' => $exception->getMessage()]);
} finally {
    auto_blog_lock_release($lock);
}
