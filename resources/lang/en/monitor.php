<?php

return [
    'navigation' => 'Monitoring',
    'view' => 'View monitoring',
    'check_error' => 'The check failed: :error',

    'status' => [
        'ok' => 'OK',
        'warning' => 'Warning',
        'failed' => 'Failed',
    ],

    'channels' => [
        'database' => 'Notifications',
    ],

    'alert' => [
        'title' => [
            'failed' => 'Failed: :check',
            'warning' => 'Warning: :check',
            'reminder' => 'Still failing: :check',
            'recovered' => 'Recovered: :check',
            'test' => 'Test alert',
        ],
        'since' => 'Since :time (:duration ago).',
        'lasted' => 'Lasted :duration.',
        'test_label' => 'Test',
        'test_summary' => 'If you got this, monitoring alerts reach you here.',
    ],

    'overview' => [
        'ok' => 'All systems working',
        'problems' => '{1} 1 check with problems|[2,*] :count checks with problems',
        'checked' => 'Checked :ago. Updates automatically.',
        'stale' => 'The monitor is not running',
        'stale_help' => 'Last result at :time (:ago). Check the <code>monitor</code> container (<code>php artisan monitor:work</code>).',
        'empty' => 'No results yet',
        'empty_help' => 'Results appear once the monitor process starts: the <code>monitor</code> Docker service (<code>php artisan monitor:work</code>).',
    ],

    'card' => [
        'actions' => 'Actions',
        'silenced' => 'Alerts silenced until :time',
        'ok_since' => 'OK for :ago',
        'since' => 'Since :time (:ago)',
    ],

    'silence' => [
        'action' => 'Silence alerts',
        'heading' => 'Silence alerts for :check',
        'description' => 'Results keep showing here; only the alerts stop. If it is still failing when the time is up, the alert goes out then.',
        'for' => 'For',
        'hours' => [
            1 => '1 hour',
            4 => '4 hours',
            24 => '24 hours',
            168 => '7 days',
        ],
        'submit' => 'Silence',
        'saved' => 'Alerts for :check silenced until :time',
        'resume' => 'Resume alerts',
        'resumed' => 'Alerts resumed: :checks',
        'done' => 'Alerts silenced until :time: :checks',
        'unknown' => 'Unknown checks: :checks (available: :known)',
    ],

    'test' => [
        'action' => 'Send test alert',
        'description' => 'Sends a test message through every configured channel (Telegram, email, notifications).',
        'submit' => 'Send',
        'sent' => 'sent',
        'all_sent' => 'Test alert sent',
        'some_failed' => 'The test alert did not go out through every channel',
        'no_channels' => 'No alert channels configured',
    ],
];
