<?php

use GoncaloSoares\FilamentMonitor\Checks\CpuLoadCheck;
use GoncaloSoares\FilamentMonitor\Checks\DatabaseCheck;
use GoncaloSoares\FilamentMonitor\Checks\DiskCheck;
use GoncaloSoares\FilamentMonitor\Checks\HeartbeatCheck;
use GoncaloSoares\FilamentMonitor\Checks\MemoryCheck;
use GoncaloSoares\FilamentMonitor\Checks\QueueCheck;
use GoncaloSoares\FilamentMonitor\Checks\RedisCheck;

/*
| filament-monitor (README do pacote). Opções comuns a todas as verificações: `class`, `label`, `every` (segundos entre
| corridas, 60), `for` (minutos seguidos no mesmo estado antes de alertar e de dar como recuperado, 0), `enabled`.
*/
return [

    // Antes do título dos alertas: "[Nome] Falha: Disco".
    'name' => env('APP_NAME'),

    // Painel onde está a página (ligações nos alertas).
    'panel' => null,

    // Estado dos alertas e batimentos. Num volume partilhado pelo processo do monitor e pelo scheduler.
    'path' => storage_path('monitor'),

    // Segundos entre voltas do monitor:work (cada verificação corre de `every` em `every` segundos).
    'tick' => 15,

    // Minutos entre lembretes enquanto uma verificação continuar a falhar.
    'remind_every' => 360,

    // Sem resultados novos há mais do que isto (minutos), a página avisa que o monitor parou.
    'stale_after' => 3,

    // Números para os gráficos: ao minuto durante `full_resolution_hours`, depois médias de 15 minutos.
    'full_resolution_hours' => 48,
    'retention_days' => 30,
    'incidents_retention_days' => 365,

    // O scheduler vigia o monitor (monitor:heartbeat): falha sem batimento há `failure` minutos, mantido `for` minutos.
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
        // Endereços separados por vírgulas. Sem base de dados também funciona.
        'mail' => ['to' => env('MONITOR_ALERT_EMAILS')],

        // Bot do Telegram (README do pacote).
        'telegram' => [
            'token' => env('MONITOR_TELEGRAM_BOT_TOKEN'),
            'chat_id' => env('MONITOR_TELEGRAM_CHAT_ID'),
        ],

        // Sino do Filament: `model` dos utilizadores e as condições (ex. ['is_admin' => true]). null = desligado.
        'database' => ['model' => null, 'where' => []],
    ],

];
