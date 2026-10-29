<?php

namespace GoncaloSoares\FilamentMonitor;

use Carbon\CarbonImmutable;
use GoncaloSoares\FilamentMonitor\Models\MonitorCheck;
use GoncaloSoares\FilamentMonitor\Models\MonitorIncident;
use GoncaloSoares\FilamentMonitor\Models\MonitorSample;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * O ciclo do `monitor:work`: corre as verificações que estão na hora, decide os alertas (AlertState, num ficheiro), envia-os
 * e só depois grava na base de dados (último resultado, números, incidentes). A base de dados é só para a página: se
 * estiver em baixo, os alertas saem na mesma.
 */
final class Monitor
{
    /** @var array<string, int> próxima corrida de cada verificação (tempo Unix) */
    private array $nextRun = [];

    /** @var array<string, int> silenciadas até (tempo Unix); sem base de dados, ficam as últimas que se leram */
    private array $silences = [];

    /**
     * @param  array<string, Check>  $checks
     */
    public function __construct(
        private readonly array $checks,
        private readonly AlertState $state,
        private readonly Notifier $notifier,
        private readonly int $remindEvery,
    ) {}

    public static function fromConfig(): self
    {
        $checks = [];

        foreach ((array) config('monitor.checks') as $key => $config) {
            if ($config['enabled'] ?? true) {
                $checks[$key] = Check::fromConfig($key, $config);
            }
        }

        return new self($checks, AlertState::load(self::stateFile()), Notifier::fromConfig(), (int) config('monitor.remind_every', 360));
    }

    public static function stateFile(): string
    {
        return rtrim((string) config('monitor.path'), '/').'/state.json';
    }

    /**
     * Uma volta: devolve os resultados das verificações que correram e os alertas enviados.
     *
     * @return array{results: array<string, Result>, alerts: list<Alert>}
     */
    public function tick(CarbonImmutable $now): array
    {
        $results = [];
        $alerts = [];
        $this->loadSilences();

        foreach ($this->checks as $key => $check) {
            if (($this->nextRun[$key] ?? 0) > $now->getTimestamp()) {
                continue;
            }

            $this->nextRun[$key] = $now->getTimestamp() + $check->every();
            $results[$key] = $result = $this->run($check);
            $incident = $this->state->incident($key);
            $silenced = ($this->silences[$key] ?? 0) > $now->getTimestamp();

            if ($alert = $this->state->apply($key, $check->label(), $result, $check->alertAfter(), $this->remindEvery, $now, $silenced)) {
                if (! $silenced) {
                    $this->notifier->send($alert);
                    $alerts[] = $alert;
                }

                $this->state->setIncident($key, $this->recordIncident($alert, $incident));
            }
        }

        $this->state->keepOnly(array_keys($this->checks));

        // Disco cheio ou volume sem escrita: continua com o estado em memória (o alerta do disco sai pelos canais).
        try {
            $this->state->save();
            Heartbeat::beat('monitor');
        } catch (Throwable $e) {
            Log::error("Monitorização: o estado dos alertas não foi gravado ({$e->getMessage()}).");
        }

        // Sem verificações nesta volta, nada a gravar (com a base de dados em baixo, nem tenta).
        if ($results !== []) {
            $this->store($results, $now);
        }

        return ['results' => $results, 'alerts' => $alerts];
    }

    private function loadSilences(): void
    {
        try {
            $this->silences = MonitorCheck::query()
                ->whereNotNull('silenced_until')
                ->get(['key', 'silenced_until'])
                ->mapWithKeys(fn (MonitorCheck $check): array => [$check->key => $check->silenced_until->getTimestamp()])
                ->all();
        } catch (Throwable) {
            DB::purge();
        }
    }

    private function run(Check $check): Result
    {
        try {
            return $check->run();
        } catch (Throwable $e) {
            return Result::failed(__('monitor::monitor.check_error', ['error' => Str::limit($e->getMessage(), 200)]));
        }
    }

    /**
     * Grava o último resultado de cada verificação e os números. Sem base de dados, fica para a próxima volta.
     *
     * @param  array<string, Result>  $results
     */
    private function store(array $results, CarbonImmutable $now): void
    {
        try {
            $samples = [];

            foreach ($results as $key => $result) {
                MonitorCheck::query()->updateOrCreate(['key' => $key], [
                    'label' => $this->checks[$key]->label(),
                    'status' => $result->status,
                    'summary' => Str::limit($result->summary, 497),
                    'metrics' => $result->metrics ?: null,
                    'since' => $this->state->since($key) ?? $now,
                    'checked_at' => $now,
                ]);

                foreach ($result->metrics as $name => $value) {
                    $samples["{$key}.{$name}"] = $value;
                }
            }

            MonitorSample::record($samples, $now);
            MonitorCheck::query()->whereNotIn('key', array_keys($this->checks))->delete();
        } catch (Throwable $e) {
            DB::purge();
            Log::warning("Monitorização: os resultados não foram gravados ({$e->getMessage()}).");
        }
    }

    /**
     * Histórico dos alertas (página Alertas). Devolve o id do incidente aberto, ou null quando fecha. Se a base de dados
     * estava em baixo quando abriu, o incidente é criado no alerta seguinte.
     */
    private function recordIncident(Alert $alert, ?int $id): ?int
    {
        try {
            $incident = ($id ? MonitorIncident::query()->find($id) : null) ?? new MonitorIncident([
                'check' => $alert->check,
                'started_at' => $alert->startedAt,
            ]);

            $incident->fill([
                'label' => $alert->label,
                'summary' => Str::limit($alert->summary, 497),
                'notified_at' => $alert->at,
            ]);

            if (! $incident->exists || $alert->status->isWorseThan($incident->status)) {
                $incident->status = $alert->status;
            }

            if ($alert->type === AlertType::Recovered) {
                $incident->resolved_at = $alert->at;
            }

            $incident->save();

            return $alert->type === AlertType::Recovered ? null : $incident->id;
        } catch (Throwable $e) {
            DB::purge();
            Log::warning("Monitorização: o incidente não foi gravado ({$e->getMessage()}).");

            return $alert->type === AlertType::Recovered ? null : $id;
        }
    }
}
