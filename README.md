# filament-monitor

Monitorização do servidor, dos contentores e da aplicação para o Filament 5, com alertas por Telegram, email e o sino
do Filament. Sem pacotes nem serviços de terceiros.

## Como funciona

- **`php artisan monitor:work`**, um processo próprio (um serviço no Docker), corre as verificações e envia os alertas
  **na hora**. Não usa a fila nem o scheduler: tem de avisar precisamente quando são eles que param.
- **O estado dos alertas** fica num ficheiro (`config('monitor.path')`), não na base de dados nem no Redis: "base de
  dados em baixo" também é avisado. A base de dados só guarda o que a página mostra (último resultado, números,
  incidentes); se estiver em baixo, os alertas saem na mesma.
- **O scheduler e o monitor vigiam-se um ao outro:** `monitor:heartbeat` (a cada minuto no scheduler) bate como
  "scheduler" e alerta se o monitor deixar de bater. Os dois têm de partilhar `config('monitor.path')` (um volume).
- Um problema só alerta se durar `for` minutos; agrava (aviso → falha), lembra de `remind_every` em `remind_every`
  minutos e avisa quando volta ao normal (também depois de `for` minutos, para um valor a oscilar no limite não alertar
  sem parar).

## Instalação (pacote local)

```json
"repositories": [{ "type": "path", "url": "packages/filament-monitor" }]
```

```sh
composer require goncalosoaresdev/filament-monitor
php artisan vendor:publish --tag=monitor-config   # config/monitor.php
php artisan migrate
```

No painel:

```php
FilamentMonitorPlugin::make()
    ->authorize(fn (): bool => (bool) auth()->user()?->is_admin)   // sem authorize(), ninguém vê a página
    ->navigationGroup('Sistema'),
```

No scheduler (`routes/console.php`), sem `onOneServer()` (o bloqueio está no Redis):

```php
Schedule::command('monitor:heartbeat')->everyMinute();
Schedule::command('monitor:prune')->daily();   // médias de 15 min depois de 48 h; apaga o que passa de 30 dias
```

E o processo, por exemplo no Docker Compose, com o volume partilhado com o scheduler:

```yaml
monitor:
  command: ["php", "artisan", "monitor:work"]
  volumes: [monitor-state:/var/www/html/storage/monitor]
```

## Verificações

Em `config('monitor.checks')`, por chave. Opções comuns: `class`, `label`, `every` (segundos, 60), `for` (minutos, 0),
`enabled`. Limites `warning` / `failure` na unidade de cada uma.

| Classe            | O quê                                                                     | Unidade dos limites      |
|-------------------|---------------------------------------------------------------------------|--------------------------|
| `DiskCheck`       | espaço usado em `path`                                                    | %                        |
| `MemoryCheck`     | memória usada (`/proc/meminfo`, `MemAvailable`)                           | %                        |
| `CpuLoadCheck`    | carga a 5 minutos por núcleo (`/proc/loadavg`)                            | carga por núcleo         |
| `DatabaseCheck`   | `select 1`; no PostgreSQL, ligações face a `max_connections` e tamanho    | % das ligações           |
| `RedisCheck`      | `PING`, gravação em disco (AOF/RDB), memória face a `maxmemory`           | % da memória             |
| `HeartbeatCheck`  | minutos desde o último batimento de `name`                                | minutos                  |
| `QueueCheck`      | trabalho de teste (`canary`) e trabalhos à espera (`size_warning`, `size_failure`) | minutos sem passar |
| `HttpCheck`       | `url` responde com `status` (200); `slow_ms` é aviso                      | -                        |
| `ContainersCheck` | contentores do mesmo projeto do compose: parados, `unhealthy`, sempre a reiniciar, `services` em falta; memória e CPU de cada um | `restarts` (3 em 15 min) |

O `QueueCheck` com canário só serve em filas cujos workers corram qualquer job. O `ContainersCheck` precisa da API do
Docker, e só lê `GET /containers/json` e `GET /containers/{id}/stats`: ponha à frente do socket um proxy que só deixe
passar esses dois (ex. um Caddy com `path_regexp`, como `docker/docker-api/Caddyfile` nesta aplicação). Nunca o socket
montado diretamente, nem um proxy "só GET": `/containers/{id}/json`, `/logs` e `/archive` mostram as variáveis e os
ficheiros dos outros contentores.

O processo do monitor e o scheduler têm de arrancar com a base de dados e o Redis em baixo: sem `depends_on` deles e sem
migrações no arranque. No scheduler, `Schedule::withoutInterruptionPolling()` e o `monitor:heartbeat` primeiro, sem
`onOneServer()`.

Uma verificação nova estende `GoncaloSoares\FilamentMonitor\Check` e devolve um `Result` (estado, resumo e números para
os gráficos, que ficam em `monitor_samples` como `<chave>.<número>`).

## Página

`Monitoring` (no menu, com o número de problemas): o estado de cada verificação, os gráficos (24 h, 7 dias, 30 dias;
cada verificação diz os seus em `Check::charts()`) e o menu "⋮" de cada cartão para silenciar os alertas.
`MonitorIncidents` (fora do menu, ligada pelo cabeçalho): o histórico dos alertas. `MonitorStatusWidget`: o cartão
"Servidor" no painel de controlo do painel (estado, problemas, números principais); `->dashboardWidget(false)` tira-o.

## Silenciar

Na página, ou `php artisan monitor:silence <chave>... --minutes=60` (`--resume` retoma). Silenciada, a verificação
continua a correr e a aparecer, mas não sai nenhum alerta; quando o silêncio acaba e ainda está a falhar, o alerta sai
nessa altura. Útil num deploy, antes de recriar os contentores.

## Canais

- **Telegram:** `MONITOR_TELEGRAM_BOT_TOKEN` (do @BotFather) e `MONITOR_TELEGRAM_CHAT_ID` (de
  `https://api.telegram.org/bot<token>/getUpdates`, depois de mandar uma mensagem ao bot). O token nunca chega ao log.
- **Email:** `MONITOR_ALERT_EMAILS`, separados por vírgulas. Enviado na hora, sem fila.
- **Sino:** `channels.database.model` + `where` (ex. `['is_admin' => true]`). Precisa da base de dados.

Testar: `php artisan monitor:test-alert` ou "Enviar alerta de teste" na página.

## Personalizar

Traduções (`en`, `pt`) e vistas podem ser substituídas na aplicação: `lang/vendor/monitor/` e
`resources/views/vendor/monitor/`. O CSS (`.fm-*`) é registado no Filament e publicado pelo `php artisan filament:assets`.
