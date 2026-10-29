<?php

return [
    'disk' => [
        'label' => 'Disk',
        'summary' => ':used% used: :free free of :total.',
        'unreadable' => 'Could not read the disk at :path.',
    ],

    'memory' => [
        'label' => 'Memory',
        'summary' => ':used% used: :available available of :total.',
        'unreadable' => 'Could not read :file.',
    ],

    'cpu' => [
        'label' => 'CPU',
        'summary' => 'Load of :load per core over the last 5 minutes (:cores cores).',
        'unreadable' => 'Could not read :file.',
    ],

    'database' => [
        'label' => 'Database',
        'summary' => 'Responds in :ms ms.',
        'summary_pgsql' => 'Responds in :ms ms. :connections of :max connections, :size.',
        'down' => 'No connection: :error',
    ],

    'redis' => [
        'label' => 'Redis',
        'summary' => ':memory in memory, responds in :ms ms.',
        'persistence' => 'Writing to disk (:type) is failing: queued jobs may be lost on a restart.',
        'down' => 'No connection: :error',
    ],

    'heartbeat' => [
        'label' => ':name process',
        'monitor' => 'Monitor',
        'recent' => 'Checked in less than a minute ago.',
        'summary' => 'Last heartbeat at :time (:ago).',
        'never' => 'No heartbeat yet (:name).',
    ],

    'queue' => [
        'label' => ':queue queue',
        'sent' => 'Test job queued.',
        'passed' => 'A test job went through in :seconds s.',
        'waiting' => 'No test job has gone through in the last :minutes min.',
        'size' => '{0} No jobs waiting.|{1} 1 job waiting.|[2,*] :count jobs waiting.',
    ],

    'containers' => [
        'label' => 'Containers',
        'summary' => '{1} 1 container running.|[2,*] :count containers running.',
        'not_running' => ':name stopped (:state)',
        'unhealthy' => ':name unhealthy',
        'restarting' => ':name keeps restarting (:count times in 15 min)',
        'missing' => ':name does not exist',
    ],

    'http' => [
        'label' => 'Site :host',
        'summary' => 'Responds in :ms ms.',
        'status' => 'Responded with status :status.',
        'down' => 'No response: :error',
    ],
];
