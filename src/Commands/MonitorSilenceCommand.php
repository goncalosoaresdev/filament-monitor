<?php

namespace GoncaloSoares\FilamentMonitor\Commands;

use GoncaloSoares\FilamentMonitor\Models\MonitorCheck;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Silencia os alertas de verificações por um tempo (ex. o deploy.sh, enquanto os contentores reiniciam de propósito).
 * O monitor lê isto a cada volta; uma verificação que ainda não correu fica com o silêncio guardado à espera.
 */
#[Signature('monitor:silence {checks* : Check keys (config monitor.checks)} {--minutes=60} {--resume : Resume the alerts}')]
#[Description('Silences (or resumes) the alerts of monitoring checks')]
class MonitorSilenceCommand extends Command
{
    public function handle(): int
    {
        $known = array_keys((array) config('monitor.checks'));
        $checks = (array) $this->argument('checks');

        if ($unknown = array_diff($checks, $known)) {
            $this->error(__('monitor::monitor.silence.unknown', ['checks' => implode(', ', $unknown), 'known' => implode(', ', $known)]));

            return self::FAILURE;
        }

        $until = $this->option('resume') ? null : now()->addMinutes(max(1, (int) $this->option('minutes')));

        foreach ($checks as $key) {
            MonitorCheck::silence($key, $until);
        }

        $this->info($until
            ? __('monitor::monitor.silence.done', ['checks' => implode(', ', $checks), 'time' => $until->toDateTimeString()])
            : __('monitor::monitor.silence.resumed', ['checks' => implode(', ', $checks)]));

        return self::SUCCESS;
    }
}
