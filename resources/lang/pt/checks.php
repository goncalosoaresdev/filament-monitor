<?php

return [
    'disk' => [
        'label' => 'Disco',
        'summary' => ':used% usado: :free livres de :total.',
        'unreadable' => 'Não foi possível ler o disco em :path.',
    ],

    'memory' => [
        'label' => 'Memória',
        'summary' => ':used% usada: :available disponíveis de :total.',
        'unreadable' => 'Não foi possível ler :file.',
    ],

    'cpu' => [
        'label' => 'Processador',
        'summary' => 'Carga de :load por núcleo nos últimos 5 minutos (:cores núcleos).',
        'unreadable' => 'Não foi possível ler :file.',
    ],

    'database' => [
        'label' => 'Base de dados',
        'summary' => 'Responde em :ms ms.',
        'summary_pgsql' => 'Responde em :ms ms. :connections de :max ligações, :size.',
        'down' => 'Sem ligação: :error',
    ],

    'redis' => [
        'label' => 'Redis',
        'summary' => ':memory em memória, responde em :ms ms.',
        'persistence' => 'A gravação em disco (:type) está a falhar: os trabalhos em fila podem perder-se num reinício.',
        'down' => 'Sem ligação: :error',
    ],

    'heartbeat' => [
        'label' => 'Processo :name',
        'monitor' => 'Monitor',
        'recent' => 'Deu sinal há menos de um minuto.',
        'summary' => 'Último sinal: :time (:ago).',
        'never' => 'Nunca deu sinal (:name).',
    ],

    'queue' => [
        'label' => 'Fila :queue',
        'sent' => 'Teste posto na fila.',
        'passed' => 'Um trabalho de teste passou em :seconds s.',
        'waiting' => 'Nenhum trabalho de teste passou nos últimos :minutes min.',
        'size' => '{0} Nenhum trabalho à espera.|{1} 1 trabalho à espera.|[2,*] :count trabalhos à espera.',
    ],

    'containers' => [
        'label' => 'Contentores',
        'summary' => '{1} 1 contentor a correr.|[2,*] :count contentores a correr.',
        'not_running' => ':name parado (:state)',
        'unhealthy' => ':name com problemas (unhealthy)',
        'restarting' => ':name está sempre a reiniciar (:count vezes em 15 min)',
        'missing' => ':name não existe',
    ],

    'http' => [
        'label' => 'Site :host',
        'summary' => 'Responde em :ms ms.',
        'status' => 'Respondeu com o código :status.',
        'down' => 'Sem resposta: :error',
    ],
];
