<?php

namespace GoncaloSoares\FilamentMonitor\Commands;

use Carbon\CarbonImmutable;
use GoncaloSoares\FilamentMonitor\Monitor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * O processo do monitor (serviço `monitor` no compose): uma volta a cada config('monitor.tick') segundos. Sai ao fim de
 * --max-time (o Docker volta a arrancá-lo, como aos workers da fila) e ao receber SIGTERM, depois da volta em curso.
 */
#[Signature('monitor:work {--once : Uma volta só} {--max-time=3600 : Segundos até sair (o Docker volta a arrancá-lo)}')]
#[Description('Corre as verificações da monitorização e envia os alertas')]
class MonitorWorkCommand extends Command
{
    private bool $stop = false;

    public function handle(): int
    {
        $monitor = Monitor::fromConfig();
        $started = time();
        $tick = max(1, (int) config('monitor.tick', 15));

        $this->trap([SIGTERM, SIGINT, SIGQUIT], function () {
            $this->stop = true;
        });

        do {
            foreach ($monitor->tick(CarbonImmutable::now())['alerts'] as $alert) {
                $this->line(now()->toDateTimeString().' '.$alert->title().': '.$alert->summary);
            }

            if ($this->option('once')) {
                break;
            }

            $next = time() + $tick;

            while (! $this->stop && time() < $next) {
                sleep(1);
            }
        } while (! $this->stop && time() - $started < (int) $this->option('max-time'));

        return self::SUCCESS;
    }
}
