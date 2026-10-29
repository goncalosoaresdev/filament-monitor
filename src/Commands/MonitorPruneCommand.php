<?php

namespace GoncaloSoares\FilamentMonitor\Commands;

use GoncaloSoares\FilamentMonitor\Models\MonitorIncident;
use GoncaloSoares\FilamentMonitor\Models\MonitorSample;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('monitor:prune')]
#[Description('Junta os números antigos da monitorização em médias de 15 minutos e apaga o que passou da retenção')]
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
