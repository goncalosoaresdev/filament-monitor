<?php

namespace GoncaloSoares\FilamentMonitor\Checks;

use GoncaloSoares\FilamentMonitor\Alert;
use GoncaloSoares\FilamentMonitor\Check;
use GoncaloSoares\FilamentMonitor\Heartbeat;
use GoncaloSoares\FilamentMonitor\Result;

/**
 * Há quanto tempo um processo não dá sinal (`name`: "scheduler", que bate com `monitor:heartbeat` a cada minuto; o
 * scheduler usa a mesma verificação para vigiar o "monitor"). Limites em minutos: `warning`, `failure`.
 */
class HeartbeatCheck extends Check
{
    protected function defaultLabel(): string
    {
        return __('monitor::checks.heartbeat.label', ['name' => $this->name()]);
    }

    public function run(): Result
    {
        $last = Heartbeat::last($this->name());

        if ($last === null) {
            return Result::failed(__('monitor::checks.heartbeat.never', ['name' => $this->name()]));
        }

        $minutes = round(max(0, $last->diffInSeconds(now())) / 60, 1);

        return Result::threshold(
            $minutes,
            $this->floatOption('warning'),
            $this->floatOption('failure'),
            $minutes < 1
                ? __('monitor::checks.heartbeat.recent')
                : __('monitor::checks.heartbeat.summary', ['ago' => $last->diffForHumans(), 'time' => Alert::formatTime($last)]),
            ['minutes_since' => $minutes],
        );
    }

    private function name(): string
    {
        return (string) $this->option('name', $this->key);
    }
}
