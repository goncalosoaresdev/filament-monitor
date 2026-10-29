<?php

return [
    'navigation' => 'Monitorização',
    'view' => 'Ver a monitorização',
    'check_error' => 'A verificação falhou: :error',

    'status' => [
        'ok' => 'Normal',
        'warning' => 'Aviso',
        'failed' => 'Falha',
    ],

    'channels' => [
        'database' => 'Sino',
    ],

    'alert' => [
        'title' => [
            'failed' => 'Falha: :check',
            'warning' => 'Aviso: :check',
            'reminder' => 'Continua: :check',
            'recovered' => 'Normal: :check',
            'test' => 'Alerta de teste',
        ],
        'since' => 'Desde :time (há :duration).',
        'lasted' => 'Durou :duration.',
        'test_label' => 'Teste',
        'test_summary' => 'Se recebeu isto, os alertas da monitorização chegam por aqui.',
    ],

    'overview' => [
        'ok' => 'Tudo a funcionar',
        'problems' => '{1} 1 verificação com problemas|[2,*] :count verificações com problemas',
        'checked' => 'Verificado :ago. Atualiza sozinho.',
        'stale' => 'O monitor não está a correr',
        'stale_help' => 'Último resultado: :time (:ago). Ver o contentor <code>monitor</code> (<code>php artisan monitor:work</code>).',
        'empty' => 'Ainda sem resultados',
        'empty_help' => 'Os resultados aparecem quando o processo do monitor arrancar: serviço <code>monitor</code> no Docker (<code>php artisan monitor:work</code>).',
    ],

    'card' => [
        'actions' => 'Ações',
        'silenced' => 'Alertas silenciados até :time',
        'ok_since' => 'Normal há :ago',
        'since' => 'Desde :time (:ago)',
    ],

    'silence' => [
        'action' => 'Silenciar alertas',
        'heading' => 'Silenciar os alertas de :check',
        'description' => 'Os resultados continuam a aparecer aqui; só não saem alertas. Se ainda estiver a falhar quando o tempo acabar, o alerta sai nessa altura.',
        'for' => 'Durante',
        'hours' => [
            1 => '1 hora',
            4 => '4 horas',
            24 => '24 horas',
            168 => '7 dias',
        ],
        'submit' => 'Silenciar',
        'saved' => 'Alertas de :check silenciados até :time',
        'resume' => 'Retomar alertas',
        'resumed' => 'Alertas retomados: :checks',
        'done' => 'Alertas silenciados até :time: :checks',
        'unknown' => 'Verificações desconhecidas: :checks (existem: :known)',
    ],

    'test' => [
        'action' => 'Enviar alerta de teste',
        'description' => 'Envia uma mensagem de teste por todos os canais configurados (Telegram, email, sino).',
        'submit' => 'Enviar',
        'sent' => 'enviado',
        'all_sent' => 'Alerta de teste enviado',
        'some_failed' => 'O alerta de teste não saiu por todos os canais',
        'no_channels' => 'Nenhum canal de alertas configurado',
    ],
];
