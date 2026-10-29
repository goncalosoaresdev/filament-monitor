<?php

namespace GoncaloSoares\FilamentMonitor\Checks;

use GoncaloSoares\FilamentMonitor\Check;
use GoncaloSoares\FilamentMonitor\Result;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Contentores do Docker pela API (`url`, ex. http://docker-api:2375: um proxy que só deixa ler a lista dos contentores
 * e as estatísticas de cada um). Falha se algum estiver parado, a reiniciar, `unhealthy` ou sempre a reiniciar
 * (`restarts` vezes em 15 minutos, 3), ou se faltar um dos `services` (nomes do compose).
 *
 * Só os do mesmo projeto do compose que o monitor: o projeto lê-se das etiquetas do próprio contentor na lista (o
 * hostname de um contentor é o início do seu id), ou de `project`.
 *
 * Também a memória e o CPU de cada um (`<serviço>.memory_mb`, `<serviço>.cpu_percent`), pelas estatísticas "one-shot"
 * (respondem logo). O CPU é a diferença para a corrida anterior: 100% = um núcleo inteiro, como no `docker stats`.
 */
class ContainersCheck extends Check
{
    private const PROJECT = 'com.docker.compose.project';

    private const SERVICE = 'com.docker.compose.service';

    private ?string $project = null;

    /** @var array<string, int> segundos a correr na corrida anterior, por id do contentor */
    private array $uptimes = [];

    /** @var array<string, list<int>> quando se viu cada contentor reiniciar (últimos 15 minutos), por id */
    private array $restarts = [];

    /** @var array<string, array{total: int, system: int}> CPU da corrida anterior, por id do contentor */
    private array $previousCpu = [];

    protected function defaultLabel(): string
    {
        return __('monitor::checks.containers.label');
    }

    public function charts(): array
    {
        return [[
            'type' => 'bars',
            'metric' => '*.memory_mb',
            'label' => __('monitor::charts.container_memory'),
            'unit' => 'MB',
            'decimals' => 0,
            'extra' => '*.cpu_percent',
            'extra_label' => __('monitor::charts.container_cpu'),
            'extra_unit' => '%',
        ]];
    }

    public function run(): Result
    {
        $all = collect($this->api()->get('/containers/json', ['all' => 1])->throw()->json());
        $this->project ??= $this->option('project') ?: $this->ownProject($all->all());

        $containers = $all
            ->filter(fn (array $container): bool => $this->project === null || ($container['Labels'][self::PROJECT] ?? null) === $this->project)
            ->reject(fn (array $container): bool => ($container['Labels']['com.docker.compose.oneoff'] ?? null) === 'True');

        $problems = [];
        $running = [];
        $now = now()->getTimestamp();
        $loopAfter = max(2, (int) $this->option('restarts', 3));

        foreach ($containers as $container) {
            $id = (string) $container['Id'];
            $name = $this->name($container);
            $status = (string) ($container['Status'] ?? '');
            $restarts = $this->restartsOf($id, $status, $now);

            if (($container['State'] ?? null) !== 'running') {
                $problems[] = __('monitor::checks.containers.not_running', ['name' => $name, 'state' => $container['State'] ?? '?']);
            } elseif (str_contains($status, '(unhealthy)')) {
                $problems[] = __('monitor::checks.containers.unhealthy', ['name' => $name]);
            } elseif ($restarts >= $loopAfter) {
                $problems[] = __('monitor::checks.containers.restarting', ['name' => $name, 'count' => $restarts]);
            } else {
                $running[$id] = $name;
            }
        }

        // Contentores que desapareceram (deploy: os novos têm outro id) não ficam a acumular.
        $ids = $containers->pluck('Id')->all();
        $this->uptimes = array_intersect_key($this->uptimes, array_flip($ids));
        $this->restarts = array_intersect_key($this->restarts, array_flip($ids));

        $services = $containers->map(fn (array $container): ?string => $container['Labels'][self::SERVICE] ?? null)->filter()->all();

        foreach (array_diff((array) $this->option('services', []), $services) as $missing) {
            $problems[] = __('monitor::checks.containers.missing', ['name' => $missing]);
        }

        $metrics = ['running' => count($running), 'problems' => count($problems)] + $this->usage($running);

        return $problems === []
            ? Result::ok(trans_choice('monitor::checks.containers.summary', count($running), ['count' => count($running)]), $metrics)
            : Result::failed(implode('; ', $problems), $metrics);
    }

    /**
     * Memória e CPU dos contentores a correr, todos ao mesmo tempo. Um que não responda fica sem números nesta corrida.
     *
     * @param  array<string, string>  $running  id => nome
     * @return array<string, float>
     */
    private function usage(array $running): array
    {
        if ($running === []) {
            return [];
        }

        $url = rtrim((string) $this->option('url'), '/');
        $responses = Http::pool(fn (Pool $pool) => array_map(
            fn (string $id) => $pool->as($id)->timeout(5)->acceptJson()->get("{$url}/containers/{$id}/stats", ['stream' => 'false', 'one-shot' => 'true']),
            array_keys($running),
        ));

        $metrics = [];
        $seen = [];

        foreach ($running as $id => $name) {
            $response = $responses[$id] ?? null;

            if (! $response instanceof Response || ! $response->successful()) {
                continue;
            }

            $stats = (array) $response->json();
            $seen[$id] = true;
            $memory = $stats['memory_stats'] ?? [];
            // Como o `docker stats`: sem a cache de ficheiros que o kernel pode largar (cgroup v2 e v1).
            $used = (int) ($memory['usage'] ?? 0) - (int) ($memory['stats']['inactive_file'] ?? $memory['stats']['total_inactive_file'] ?? 0);
            $metrics["{$name}.memory_mb"] = round(max(0, $used) / 1024 ** 2, 1);

            $cpu = ['total' => (int) ($stats['cpu_stats']['cpu_usage']['total_usage'] ?? 0), 'system' => (int) ($stats['cpu_stats']['system_cpu_usage'] ?? 0)];
            $previous = $this->previousCpu[$id] ?? null;
            $this->previousCpu[$id] = $cpu;

            if ($previous && $cpu['system'] > $previous['system'] && $cpu['total'] >= $previous['total']) {
                $cores = (int) ($stats['cpu_stats']['online_cpus'] ?? 1);
                $metrics["{$name}.cpu_percent"] = round(($cpu['total'] - $previous['total']) / ($cpu['system'] - $previous['system']) * max(1, $cores) * 100, 1);
            }
        }

        // Contentores que desapareceram (deploy) não ficam a acumular.
        $this->previousCpu = array_intersect_key($this->previousCpu, $seen);

        return $metrics;
    }

    /**
     * Quantas vezes o contentor reiniciou nos últimos 15 minutos. A API da lista não diz (o RestartCount só vem no
     * "inspect", que traria as variáveis do .env): vê-se pelo tempo a correr do Status ("Up 5 minutes") a descer de uma
     * corrida para a outra, no mesmo contentor. Os workers que saem de hora a hora (--max-time) contam uma vez.
     */
    private function restartsOf(string $id, string $status, int $now): int
    {
        $uptime = self::uptime($status);
        $previous = $this->uptimes[$id] ?? null;
        $recent = array_values(array_filter($this->restarts[$id] ?? [], fn (int $time): bool => $time > $now - 15 * 60));

        if ($uptime !== null) {
            if ($previous !== null && $uptime < $previous) {
                $recent[] = $now;
            }

            $this->uptimes[$id] = $uptime;
        }

        $this->restarts[$id] = $recent;

        return count($recent);
    }

    /**
     * Segundos a correr pelo Status da API ("Up 3 seconds", "Up About a minute", "Up 2 hours (healthy)"), aproximados
     * como o Docker os escreve (nunca descem enquanto o contentor corre). Null quando não está a correr.
     */
    public static function uptime(string $status): ?int
    {
        if (! preg_match('/^Up (.+?)(?: \(.*\))?$/', $status, $match)) {
            return null;
        }

        $text = strtolower($match[1]);

        if (str_starts_with($text, 'less than')) {
            return 0;
        }

        if (preg_match('/^about an? (minute|hour)$/', $text, $about)) {
            return $about[1] === 'minute' ? 60 : 3600;
        }

        if (preg_match('/^(\d+) (second|minute|hour|day|week|month|year)s?$/', $text, $amount)) {
            return (int) $amount[1] * ['second' => 1, 'minute' => 60, 'hour' => 3600, 'day' => 86400, 'week' => 604800, 'month' => 2592000, 'year' => 31536000][$amount[2]];
        }

        return null;
    }

    /**
     * Nome do serviço no compose; com réplicas (`--scale`), o serviço e o número.
     *
     * @param  array<string, mixed>  $container
     */
    private function name(array $container): string
    {
        $service = $container['Labels'][self::SERVICE] ?? null;

        if ($service === null) {
            return ltrim((string) ($container['Names'][0] ?? '?'), '/');
        }

        $number = (int) ($container['Labels']['com.docker.compose.container-number'] ?? 1);

        return $number > 1 ? "{$service}-{$number}" : $service;
    }

    /**
     * O projeto do compose do próprio monitor: o hostname de um contentor é o início do seu id.
     *
     * @param  list<array<string, mixed>>  $containers
     */
    private function ownProject(array $containers): ?string
    {
        $hostname = (string) gethostname();

        foreach ($containers as $container) {
            if ($hostname !== '' && str_starts_with((string) $container['Id'], $hostname)) {
                return $container['Labels'][self::PROJECT] ?? null;
            }
        }

        return null;
    }

    private function api(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) $this->option('url'), '/'))->timeout(5)->acceptJson();
    }
}
