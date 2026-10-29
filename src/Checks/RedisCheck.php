<?php

namespace GoncaloSoares\FilamentMonitor\Checks;

use GoncaloSoares\FilamentMonitor\Check;
use GoncaloSoares\FilamentMonitor\Result;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Throwable;

/**
 * Redis (`connection`, por omissão a "default"): responde? A gravação em disco (AOF/RDB) está a falhar? Com `maxmemory`
 * definido, a memória usada em percentagem (limites `warning`, `failure`).
 */
class RedisCheck extends Check
{
    protected function defaultLabel(): string
    {
        return __('monitor::checks.redis.label');
    }

    public function charts(): array
    {
        return [['metric' => 'memory_mb', 'label' => __('monitor::charts.redis_memory'), 'unit' => 'MB', 'decimals' => 1]];
    }

    public function run(): Result
    {
        $name = (string) $this->option('connection', 'default');

        try {
            $redis = Redis::connection($name);
            $start = hrtime(true);
            $redis->command('ping');
            $ms = round((hrtime(true) - $start) / 1e6, 1);
            $info = $this->info($redis->command('info'));
        } catch (Throwable $e) {
            Redis::purge($name);

            return Result::failed(__('monitor::checks.redis.down', ['error' => Str::limit($e->getMessage(), 200)]));
        }

        $used = (int) ($info['used_memory'] ?? 0);
        $max = (int) ($info['maxmemory'] ?? 0);
        $metrics = ['response_ms' => $ms, 'memory_mb' => round($used / 1024 ** 2, 1)];

        foreach (['aof_last_write_status' => 'AOF', 'rdb_last_bgsave_status' => 'RDB'] as $field => $type) {
            if (isset($info[$field]) && $info[$field] !== 'ok') {
                return Result::failed(__('monitor::checks.redis.persistence', ['type' => $type]), $metrics);
            }
        }

        $summary = __('monitor::checks.redis.summary', ['memory' => Number::fileSize($used, maxPrecision: 1), 'ms' => Number::format($ms, maxPrecision: 1)]);

        if ($max <= 0) {
            return Result::ok($summary, $metrics);
        }

        $percent = round($used / $max * 100, 1);

        return Result::threshold($percent, $this->floatOption('warning'), $this->floatOption('failure'), $summary, $metrics + ['memory_percent' => $percent]);
    }

    /**
     * O phpredis devolve o INFO numa lista simples; o predis agrupa por secção.
     *
     * @return array<string, mixed>
     */
    private function info(mixed $info): array
    {
        if (! is_array($info)) {
            return [];
        }

        $flat = [];

        foreach ($info as $key => $value) {
            is_array($value) ? $flat += $value : $flat[$key] = $value;
        }

        return $flat;
    }
}
