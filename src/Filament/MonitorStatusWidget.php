<?php

namespace GoncaloSoares\FilamentMonitor\Filament;

use Filament\Widgets\Widget;
use GoncaloSoares\FilamentMonitor\Charts;
use GoncaloSoares\FilamentMonitor\Models\MonitorCheck;
use GoncaloSoares\FilamentMonitor\Models\MonitorIncident;
use GoncaloSoares\FilamentMonitor\Status;

/**
 * Cartão "Servidor" no painel de controlo: o estado geral, as verificações com problemas e os números principais (os
 * primeiros gráficos de linha da configuração: disco, memória, processador). Lê o mesmo que a página Monitorização.
 */
class MonitorStatusWidget extends Widget
{
    /** Quantas verificações com problemas cabem no cartão (as outras ficam na página). */
    public const PROBLEMS = 3;

    /** Quantos números principais. */
    public const READINGS = 3;

    protected static ?int $sort = -10;

    protected static bool $isLazy = false;

    protected string $view = 'monitor::widgets.status';

    public static function canView(): bool
    {
        return Monitoring::canAccess();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $checks = Monitoring::checks();
        $problems = $checks->filter(fn (MonitorCheck $check): bool => $check->status !== Status::Ok);

        return [
            'overview' => Monitoring::overview($checks),
            'problems' => $problems->take(self::PROBLEMS),
            'moreProblems' => max(0, $problems->count() - self::PROBLEMS),
            'readings' => $this->readings($checks->keyBy('key')->all()),
            'ongoing' => MonitorIncident::query()->whereNull('resolved_at')->count(),
            'silenced' => $checks->filter(fn (MonitorCheck $check): bool => $check->isSilenced())->count(),
            'url' => Monitoring::getUrl(),
        ];
    }

    /**
     * O primeiro número de linha de cada verificação, pela ordem da configuração, com o último valor gravado.
     *
     * @param  array<string, MonitorCheck>  $checks
     * @return list<array{label: string, hint: string, value: string, status: Status}>
     */
    private function readings(array $checks): array
    {
        $readings = [];

        foreach (Monitoring::configuredChecks() as $key => $check) {
            $definition = collect($check->charts())->first(fn (array $chart): bool => ($chart['type'] ?? 'line') === 'line');
            $value = $definition && isset($checks[$key]) ? ($checks[$key]->metrics[$definition['metric']] ?? null) : null;

            if ($value !== null) {
                $readings[] = ['label' => $checks[$key]->label, 'hint' => $definition['label'], 'value' => Charts::formatValue((float) $value, $definition), 'status' => $checks[$key]->status];
            }

            if (count($readings) === self::READINGS) {
                break;
            }
        }

        return $readings;
    }
}
