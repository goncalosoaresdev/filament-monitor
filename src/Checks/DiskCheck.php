<?php

namespace GoncaloSoares\FilamentMonitor\Checks;

use GoncaloSoares\FilamentMonitor\Check;
use GoncaloSoares\FilamentMonitor\Result;
use Illuminate\Support\Number;

/**
 * Espaço em disco usado em `path` (por omissão "/"). Dentro de um contentor, "/" é o disco onde o Docker guarda as
 * imagens e os volumes (base de dados, Redis, logs). Limites em percentagem: `warning`, `failure`.
 */
class DiskCheck extends Check
{
    protected function defaultLabel(): string
    {
        return __('monitor::checks.disk.label');
    }

    public function charts(): array
    {
        return [['metric' => 'used_percent', 'label' => __('monitor::charts.disk'), 'unit' => '%', 'max' => 100, 'decimals' => 0, 'warning' => $this->floatOption('warning'), 'failure' => $this->floatOption('failure')]];
    }

    public function run(): Result
    {
        $path = (string) $this->option('path', '/');
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);

        if (! $total || $free === false) {
            return Result::failed(__('monitor::checks.disk.unreadable', ['path' => $path]));
        }

        $used = round(($total - $free) / $total * 100, 1);

        return Result::threshold(
            $used,
            $this->floatOption('warning'),
            $this->floatOption('failure'),
            __('monitor::checks.disk.summary', [
                'used' => Number::format($used, maxPrecision: 1),
                'free' => Number::fileSize($free, maxPrecision: 1),
                'total' => Number::fileSize($total, maxPrecision: 0),
            ]),
            ['used_percent' => $used, 'free_gb' => round($free / 1024 ** 3, 2)],
        );
    }
}
