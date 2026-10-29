<?php

namespace GoncaloSoares\FilamentMonitor\Commands;

use Carbon\CarbonImmutable;
use GoncaloSoares\FilamentMonitor\AlertState;
use GoncaloSoares\FilamentMonitor\Checks\HeartbeatCheck;
use GoncaloSoares\FilamentMonitor\Heartbeat;
use GoncaloSoares\FilamentMonitor\Notifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * No scheduler, a cada minuto: o batimento do scheduler (o monitor vigia-o) e a vigia do monitor. Se o `monitor:work`
 * parar, é daqui que sai o alerta, com o estado num ficheiro à parte do dele.
 *
 * Sem onOneServer(): esse bloqueio está no Redis, e isto tem de correr com o Redis em baixo (ver routes/console.php: é
 * o primeiro agendamento, e o scheduler não consulta a cache para "pause" / "interrupt").
 */
#[Signature('monitor:heartbeat')]
#[Description('Batimento do scheduler e vigia do processo da monitorização')]
class MonitorHeartbeatCommand extends Command
{
    public function handle(): int
    {
        // Disco cheio ou volume sem escrita: a vigia corre na mesma (o monitor vê o batimento parado e avisa).
        try {
            Heartbeat::beat('scheduler');
        } catch (Throwable $e) {
            report($e);
        }

        $watchdog = (array) config('monitor.watchdog');
        $check = new HeartbeatCheck('monitor', ['name' => 'monitor', 'label' => __('monitor::checks.heartbeat.monitor')] + $watchdog);
        $state = AlertState::load(rtrim((string) config('monitor.path'), '/').'/watchdog.json');

        $alert = $state->apply('monitor', $check->label(), $check->run(), $check->alertAfter(), (int) config('monitor.remind_every', 360), CarbonImmutable::now());

        if ($alert) {
            Notifier::fromConfig()->send($alert);
            $this->warn($alert->title().': '.$alert->summary);
        }

        try {
            $state->save();
        } catch (Throwable $e) {
            report($e);
        }

        return self::SUCCESS;
    }
}
