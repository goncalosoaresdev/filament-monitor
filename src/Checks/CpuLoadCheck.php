<?php

namespace GoncaloSoares\FilamentMonitor\Checks;

use GoncaloSoares\FilamentMonitor\Check;
use GoncaloSoares\FilamentMonitor\Result;
use Illuminate\Support\Number;

/**
 * Carga do servidor nos últimos 5 minutos a dividir pelos núcleos (/proc/loadavg e /proc/cpuinfo, do servidor inteiro).
 * 1 por núcleo = processador sempre ocupado. Limites por núcleo: `warning`, `failure`. `cores` evita ler o cpuinfo.
 */
class CpuLoadCheck extends Check
{
    protected function defaultLabel(): string
    {
        return __('monitor::checks.cpu.label');
    }

    public function charts(): array
    {
        return [['metric' => 'load_per_core', 'label' => __('monitor::charts.cpu'), 'unit' => '', 'decimals' => 2, 'warning' => $this->floatOption('warning'), 'failure' => $this->floatOption('failure')]];
    }

    public function run(): Result
    {
        $file = (string) $this->option('loadavg', '/proc/loadavg');
        $load = explode(' ', trim((string) @file_get_contents($file)));
        $cores = (int) ($this->option('cores') ?? $this->countCores());

        if (count($load) < 3 || ! is_numeric($load[1]) || $cores < 1) {
            return Result::failed(__('monitor::checks.cpu.unreadable', ['file' => $file]));
        }

        [$load1, $load5, $load15] = array_map('floatval', array_slice($load, 0, 3));
        $perCore = round($load5 / $cores, 2);

        return Result::threshold(
            $perCore,
            $this->floatOption('warning'),
            $this->floatOption('failure'),
            __('monitor::checks.cpu.summary', ['load' => Number::format($perCore, precision: 2), 'cores' => $cores]),
            ['load1' => $load1, 'load5' => $load5, 'load15' => $load15, 'load_per_core' => $perCore],
        );
    }

    private function countCores(): int
    {
        $cpuinfo = (string) @file_get_contents((string) $this->option('cpuinfo', '/proc/cpuinfo'));

        return preg_match_all('/^processor\s*:/m', $cpuinfo);
    }
}
