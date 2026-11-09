# filament-monitor

Server, container and application monitoring for Filament 5, with alerts by Telegram, email and Filament database
notifications. No third-party packages or services.

- A **Monitoring** page with the state of every check, charts (24 h, 7 days, 30 days) and the alert history.
- A **Server** card on the panel dashboard.
- Checks for disk, memory, CPU load, database, Redis, the scheduler, queues, HTTP endpoints and Docker containers.
- English and Portuguese translations.

## How it works

- **`php artisan monitor:work`** is a long-running process of its own (e.g. a Docker service). It runs the checks and
  sends the alerts **right away**. It does not use the queue or the scheduler, because it has to alert precisely when
  those stop.
- **The alert state** lives in a file (`config('monitor.path')`), not in the database or Redis, so "database down" is
  alerted too. The database only stores what the page shows (latest result, samples, incidents); when it is down, the
  alerts still go out.
- **The scheduler and the monitor watch each other:** `monitor:heartbeat` (every minute in the scheduler) beats as
  "scheduler" and alerts if the monitor stops beating. Both must share `config('monitor.path')` (e.g. a volume).
- A problem only alerts after lasting `for` minutes. It escalates (warning to failure), reminds every `remind_every`
  minutes and reports recovery (also after `for` minutes, so a value hovering around a threshold does not alert
  non-stop).

## Requirements

- PHP 8.3+
- Laravel with Filament 5.9+
- Linux for the host checks (`/proc/meminfo`, `/proc/loadavg`)

## Installation

```sh
composer require goncalosoaresdev/filament-monitor
php artisan vendor:publish --tag=monitor-config   # config/monitor.php
php artisan migrate
php artisan filament:assets
```

Not on Packagist yet? Add the repository to your `composer.json` first:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/goncalosoaresdev/filament-monitor" }
]
```

Register the plugin in a panel:

```php
use GoncaloSoares\FilamentMonitor\Filament\FilamentMonitorPlugin;

$panel->plugins([
    FilamentMonitorPlugin::make()
        ->authorize(fn (): bool => (bool) auth()->user()?->is_admin)   // without authorize(), nobody sees the page
        ->navigationGroup('System'),
]);
```

Add to the scheduler (`routes/console.php`), without `onOneServer()` (the lock lives in Redis):

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('monitor:heartbeat')->everyMinute();
Schedule::command('monitor:prune')->daily();   // 15-minute averages after 48 h; deletes what is older than 30 days
```

And run the process, for example as a Docker Compose service sharing the state volume with the scheduler:

```yaml
services:
  monitor:
    image: your-app-image
    command: ["php", "artisan", "monitor:work"]
    restart: unless-stopped
    volumes: [monitor-state:/var/www/html/storage/monitor]

  scheduler:
    image: your-app-image
    command: ["php", "artisan", "schedule:work"]
    volumes: [monitor-state:/var/www/html/storage/monitor]

volumes:
  monitor-state:
```

The monitor process and the scheduler must start with the database and Redis down: no `depends_on` on them and no
migrations on boot. In the scheduler, use `Schedule::withoutInterruptionPolling()` and put `monitor:heartbeat` first,
without `onOneServer()`.

## Checks

In `config('monitor.checks')`, by key. Common options: `class`, `label`, `every` (seconds, 60), `for` (minutes, 0),
`enabled`. The `warning` / `failure` thresholds are in each check's unit.

| Class             | What                                                                         | Threshold unit           |
|-------------------|------------------------------------------------------------------------------|--------------------------|
| `DiskCheck`       | space used on `path`                                                         | %                        |
| `MemoryCheck`     | memory used (`/proc/meminfo`, `MemAvailable`)                                | %                        |
| `CpuLoadCheck`    | 5-minute load per core (`/proc/loadavg`)                                     | load per core            |
| `DatabaseCheck`   | `select 1`; on PostgreSQL, connections against `max_connections` and size    | % of connections         |
| `RedisCheck`      | `PING`, persistence (AOF/RDB), memory against `maxmemory`                    | % of memory              |
| `HeartbeatCheck`  | minutes since the last heartbeat of `name`                                   | minutes                  |
| `QueueCheck`      | test job (`canary`) and waiting jobs (`size_warning`, `size_failure`)        | minutes without a pass   |
| `HttpCheck`       | `url` answers with `status` (200); `slow_ms` is a warning; `timeout`, `headers` | -                     |
| `ContainersCheck` | containers of the same Compose project: stopped, `unhealthy`, restart loops, missing `services`; memory and CPU of each | `restarts` (3 in 15 min) |

Example:

```php
'checks' => [
    'disk' => ['class' => DiskCheck::class, 'path' => '/', 'warning' => 80, 'failure' => 90, 'for' => 5],
    'queue' => ['class' => QueueCheck::class, 'queue' => 'default', 'warning' => 5, 'failure' => 15, 'size_warning' => 500],
    'web' => ['class' => HttpCheck::class, 'url' => 'http://app:8080/up', 'slow_ms' => 2000, 'for' => 2],
    'containers' => [
        'class' => ContainersCheck::class,
        'url' => env('MONITOR_DOCKER_URL'),   // e.g. http://docker-api:2375
        'services' => ['app', 'queue', 'scheduler', 'monitor', 'pgsql', 'redis'],
        'for' => 3,
    ],
],
```

The `QueueCheck` canary only makes sense on queues whose workers run any job; on restricted queues set
`'canary' => false` and use the size thresholds.

### Docker API for `ContainersCheck`

`ContainersCheck` only reads `GET /containers/json` and `GET /containers/{id}/stats`. Put a proxy in front of the Docker
socket that only lets those two through. **Never** mount the socket directly into the app, and never use a "GET only"
proxy: `/containers/{id}/json`, `/logs` and `/archive` expose the environment variables and files of the other
containers.

A Caddy proxy that does exactly this:

```caddyfile
{
	admin off
	auto_https off
}

:2375 {
	@allowed {
		method GET
		path_regexp ^(/v[0-9.]+)?/containers/(json|[a-zA-Z0-9]+/stats)$
	}

	handle @allowed {
		reverse_proxy unix//var/run/docker.sock
	}

	respond 403
}
```

```yaml
  docker-api:
    image: caddy:2-alpine
    volumes:
      - ./docker-api/Caddyfile:/etc/caddy/Caddyfile:ro
      - /var/run/docker.sock:/var/run/docker.sock:ro
```

Then set `MONITOR_DOCKER_URL=http://docker-api:2375` in the app.

### Custom checks

A new check extends `GoncaloSoares\FilamentMonitor\Check` and returns a `Result` (status, summary and numbers for the
charts, stored in `monitor_samples` as `<key>.<number>`). Charts are declared in `Check::charts()`.

## Page

`Monitoring` (in the navigation, with the number of problems as a badge): the state of every check, the charts and a
"⋮" menu on each card to silence its alerts. `MonitorIncidents` (outside the navigation, linked from the header): the
alert history. `MonitorStatusWidget`: the "Server" card on the panel dashboard (state, problems, key numbers);
`->dashboardWidget(false)` removes it.

## Silencing

From the page, or `php artisan monitor:silence <key>... --minutes=60` (`--resume` resumes). While silenced, a check
keeps running and showing on the page, but no alert is sent; if it is still failing when the silence ends, the alert
goes out then. Handy during a deploy, before recreating the containers.

## Channels

- **Telegram:** `MONITOR_TELEGRAM_BOT_TOKEN` (from @BotFather) and `MONITOR_TELEGRAM_CHAT_ID` (from
  `https://api.telegram.org/bot<token>/getUpdates`, after sending a message to the bot). The token never reaches the
  log.
- **Email:** `MONITOR_ALERT_EMAILS`, comma-separated. Sent right away, not queued.
- **Filament notifications:** `channels.database.model` + `where` (e.g. `['is_admin' => true]`). Needs the database.

Test with `php artisan monitor:test-alert` or "Send test alert" on the page.

## Customising

Translations (`en`, `pt`) and views can be overridden in the app: `lang/vendor/monitor/` and
`resources/views/vendor/monitor/`. The CSS (`.fm-*`) is registered with Filament and published by
`php artisan filament:assets`.

## License

MIT. See [LICENSE](LICENSE).
