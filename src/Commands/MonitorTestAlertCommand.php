<?php

namespace GoncaloSoares\FilamentMonitor\Commands;

use GoncaloSoares\FilamentMonitor\Alert;
use GoncaloSoares\FilamentMonitor\Notifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('monitor:test-alert')]
#[Description('Envia um alerta de teste por todos os canais configurados')]
class MonitorTestAlertCommand extends Command
{
    public function handle(): int
    {
        $results = Notifier::fromConfig()->send(Alert::test());

        if ($results === []) {
            $this->error(__('monitor::monitor.test.no_channels'));

            return self::FAILURE;
        }

        foreach ($results as $channel => $error) {
            $error === null ? $this->info("{$channel}: ".__('monitor::monitor.test.sent')) : $this->error("{$channel}: {$error}");
        }

        return array_filter($results) === [] ? self::SUCCESS : self::FAILURE;
    }
}
