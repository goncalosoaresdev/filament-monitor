<?php

namespace GoncaloSoares\FilamentMonitor\Checks;

use GoncaloSoares\FilamentMonitor\Check;
use GoncaloSoares\FilamentMonitor\Result;
use Illuminate\Support\Number;

/**
 * Memória usada no servidor (`MemTotal - MemAvailable` de /proc/meminfo, que dentro de um contentor mostra o servidor
 * inteiro). Limites em percentagem: `warning`, `failure`.
 */
class MemoryCheck extends Check
{
    protected function defaultLabel(): string
    {
        return __('monitor::checks.memory.label');
    }

    public function charts(): array
    {
        return [['metric' => 'used_percent', 'label' => __('monitor::charts.memory'), 'unit' => '%', 'max' => 100, 'decimals' => 0, 'warning' => $this->floatOption('warning'), 'failure' => $this->floatOption('failure')]];
    }

    public function run(): Result
    {
        $file = (string) $this->option('meminfo', '/proc/meminfo');
        preg_match_all('/^(\w+):\s+(\d+) kB$/m', (string) @file_get_contents($file), $matches);
        $info = array_combine($matches[1], array_map('intval', $matches[2]));

        if (empty($info['MemTotal']) || ! isset($info['MemAvailable'])) {
            return Result::failed(__('monitor::checks.memory.unreadable', ['file' => $file]));
        }

        $total = $info['MemTotal'] * 1024;
        $available = $info['MemAvailable'] * 1024;
        $used = round(($total - $available) / $total * 100, 1);

        return Result::threshold(
            $used,
            $this->floatOption('warning'),
            $this->floatOption('failure'),
            __('monitor::checks.memory.summary', [
                'used' => Number::format($used, maxPrecision: 1),
                'available' => Number::fileSize($available, maxPrecision: 1),
                'total' => Number::fileSize($total, maxPrecision: 0),
            ]),
            ['used_percent' => $used, 'available_mb' => round($available / 1024 ** 2)],
        );
    }
}
