<?php

namespace GoncaloSoares\FilamentMonitor\Commands;

use GoncaloSoares\FilamentMonitor\Models\MonitorIncident;
use GoncaloSoares\FilamentMonitor\Models\MonitorSample;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('monitor:prune')]
#[Description('Rolls old monitoring samples into 15-minute averages and deletes what is past retention')]
class MonitorPruneCommand extends Command
{
    public function handle(): int
    {
        MonitorSample::rollup(now());

        MonitorIncident::query()
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '<', now()->subDays((int) config('monitor.incidents_retention_days', 365)))
            ->delete();

        return self::SUCCESS;
    }
}
