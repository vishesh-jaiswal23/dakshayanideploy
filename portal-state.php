<?php

declare(strict_types=1);

/**
 * Lightweight persistence helpers for the Dakshayani Enterprises portal demo.
 */

const PORTAL_STATE_FILE = __DIR__ . '/data/portal-state.json';
const PORTAL_DEFAULT_ACTIVITY_LIMIT = 100;

/**
 * Return the default application state used when no persisted state exists.
 */
function portal_default_state(): array
{
    return [
        'users' => [
            [
                'id' => 'installer-001',
                'role' => 'installer',
                'name' => 'Arjun Patel',
                'email' => 'installer@example.com',
                'status' => 'active',
                'password_hash' => '$2y$12$cnqud2atAKwNNDOoWOroq.bMB80ApFNgsvXDlNHk1mWzdOSKpHeTi', // Installer@123
                'last_login' => null,
            ],
            [
                'id' => 'customer-001',
                'role' => 'customer',
                'name' => 'Saanvi Rao',
                'email' => 'customer@example.com',
                'status' => 'active',
                'password_hash' => '$2y$12$zSqdGsTObuGxene.eYXrIOQk0Z14v6NibAnM2K3pz3D3zt3KM7p5q', // Customer@123
                'last_login' => null,
            ],
            [
                'id' => 'employee-001',
                'role' => 'employee',
                'name' => 'Rahul Sharma',
                'email' => 'employee@example.com',
                'status' => 'active',
                'password_hash' => '$2y$12$2RWe38Fvc/dEtii5.9L7o.lQQu9/KSix7Jro.pfmoV3sxMKbh/eom', // Employee@123
                'last_login' => null,
            ],
            [
                'id' => 'referrer-001',
                'role' => 'referrer',
                'name' => 'Aditi Kapoor',
                'email' => 'referrer@example.com',
                'status' => 'active',
                'password_hash' => '$2y$12$lO7TeNhJN7JZ08zYbstmYOkLaACkjCOCm0xa/NuaKbVdSAQ5krcNq', // Referrer@123
                'last_login' => null,
            ],
        ],
        'activity_log' => [],
        'updated_at' => null,
    ];
}

/**
 * Load the persisted portal state from disk, falling back to the default state if necessary.
 */
function portal_load_state(): array
{
    $path = PORTAL_STATE_FILE;

    if (!is_file($path)) {
        $state = portal_default_state();
        portal_save_state($state);
        return $state;
    }

    $json = @file_get_contents($path);
    if ($json === false) {
        return portal_default_state();
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return portal_default_state();
    }

    $decoded['users'] = is_array($decoded['users'] ?? null) ? $decoded['users'] : [];
    $decoded['activity_log'] = is_array($decoded['activity_log'] ?? null) ? $decoded['activity_log'] : [];

    return $decoded;
}

/**
 * Persist the supplied portal state to disk.
 */
function portal_save_state(array &$state): void
{
    $directory = dirname(PORTAL_STATE_FILE);
    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    $state['updated_at'] = date('c');
    file_put_contents(
        PORTAL_STATE_FILE,
        json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        LOCK_EX
    );
}

/**
 * Append an entry to the in-memory activity log and keep the log at a manageable size.
 */
function portal_record_activity(array &$state, string $message, string $actor = 'System'): void
{
    $state['activity_log'][] = [
        'timestamp' => date('c'),
        'actor' => $actor,
        'message' => $message,
    ];

    if (count($state['activity_log']) > PORTAL_DEFAULT_ACTIVITY_LIMIT) {
        $state['activity_log'] = array_slice($state['activity_log'], -PORTAL_DEFAULT_ACTIVITY_LIMIT);
    }
}
