<?php

use GoncaloSoares\FilamentMonitor\Checks\CpuLoadCheck;
use GoncaloSoares\FilamentMonitor\Checks\DatabaseCheck;
use GoncaloSoares\FilamentMonitor\Checks\DiskCheck;
use GoncaloSoares\FilamentMonitor\Checks\HeartbeatCheck;
use GoncaloSoares\FilamentMonitor\Checks\MemoryCheck;
use GoncaloSoares\FilamentMonitor\Checks\QueueCheck;
use GoncaloSoares\FilamentMonitor\Checks\RedisCheck;

/*
| filament-monitor (see the package README). Options shared by every check: `class`, `label`, `every` (seconds between
| runs, 60), `for` (minutes in the same state before alerting and before reporting recovery, 0), `enabled`.
*/
return [

    // Prefix of the alert titles: "[Name] Failure: Disk".
    'name' => env('APP_NAME'),

    // Panel ID where the page lives (used for the links in the alerts).
    'panel' => null,

    // Alert state and heartbeats. Must be shared (e.g. a Docker volume) by the monitor process and the scheduler.
    'path' => storage_path('monitor'),

    // Seconds between monitor:work rounds (each check still runs every `every` seconds).
    'tick' => 15,

    // Minutes between reminders while a check keeps failing.
    'remind_every' => 360,

    // With no new results for longer than this (minutes), the page warns that the monitor has stopped.
    'stale_after' => 3,

    // Chart samples: per minute for `full_resolution_hours`, then 15-minute averages.
    'full_resolution_hours' => 48,
    'retention_days' => 30,
    'incidents_retention_days' => 365,

    // The scheduler watches the monitor (monitor:heartbeat): failure after `failure` minutes without a heartbeat, held for `for` minutes.
    'watchdog' => ['failure' => 3, 'for' => 2],

    'checks' => [
        'disk' => ['class' => DiskCheck::class, 'path' => '/', 'warning' => 80, 'failure' => 90, 'for' => 5],
        'memory' => ['class' => MemoryCheck::class, 'warning' => 85, 'failure' => 95, 'for' => 10],
        'cpu' => ['class' => CpuLoadCheck::class, 'warning' => 1.5, 'failure' => 3, 'for' => 10],
        'database' => ['class' => DatabaseCheck::class, 'warning' => 80, 'failure' => 95, 'for' => 2],
        'redis' => ['class' => RedisCheck::class, 'warning' => 90, 'failure' => 98, 'for' => 2],
        'scheduler' => ['class' => HeartbeatCheck::class, 'name' => 'scheduler', 'failure' => 3, 'for' => 2],
        'queue' => ['class' => QueueCheck::class, 'queue' => 'default', 'warning' => 5, 'failure' => 15, 'size_warning' => 500],
    ],

    'channels' => [
        // Comma-separated addresses. Works without the database.
        'mail' => ['to' => env('MONITOR_ALERT_EMAILS')],

        // Telegram bot (see the package README).
        'telegram' => [
            'token' => env('MONITOR_TELEGRAM_BOT_TOKEN'),
            'chat_id' => env('MONITOR_TELEGRAM_CHAT_ID'),
        ],

        // Filament database notifications: the user `model` and the conditions (e.g. ['is_admin' => true]). null = off.
        'database' => ['model' => null, 'where' => []],
    ],

];
