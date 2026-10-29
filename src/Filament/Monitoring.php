<?php

namespace GoncaloSoares\FilamentMonitor\Filament;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use GoncaloSoares\FilamentMonitor\Alert;
use GoncaloSoares\FilamentMonitor\Charts;
use GoncaloSoares\FilamentMonitor\Check;
use GoncaloSoares\FilamentMonitor\Models\MonitorCheck;
use GoncaloSoares\FilamentMonitor\Models\MonitorIncident;
use GoncaloSoares\FilamentMonitor\Notifier;
use GoncaloSoares\FilamentMonitor\Status;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Estado do servidor: o último resultado de cada verificação, gravado pelo `monitor:work`, e os gráficos dos números
 * gravados (24 h, 7 dias, 30 dias). A página só lê a base de dados (o processo do monitor é que corre as verificações)
 * e atualiza-se sozinha a cada 30 s. Em cada cartão: silenciar os alertas por um tempo.
 */
class Monitoring extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?string $slug = 'monitor';

    protected string $view = 'monitor::pages.monitoring';

    #[Url(as: 'periodo', except: '24h')]
    public string $period = '24h';

    public static function canAccess(): bool
    {
        return FilamentMonitorPlugin::current()?->isAuthorized() ?? false;
    }

    /** Também ativo no histórico de alertas (MonitorIncidents, fora do menu). */
    public static function getNavigationItemActiveRoutePattern(): string|array
    {
        return [static::getRouteName(), MonitorIncidents::getRouteName()];
    }

    public static function getNavigationLabel(): string
    {
        return __('monitor::monitor.navigation');
    }

    public function getTitle(): string
    {
        return __('monitor::monitor.navigation');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return FilamentMonitorPlugin::current()?->getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return FilamentMonitorPlugin::current()?->getNavigationSort();
    }

    /** Quantas verificações têm problemas (ou "!" quando o monitor parou). */
    public static function getNavigationBadge(): ?string
    {
        $state = static::navigationState();

        return match (true) {
            $state['stale'] => '!',
            $state['problems'] > 0 => (string) $state['problems'],
            default => null,
        };
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        $state = static::navigationState();

        return $state['stale'] ? 'danger' : $state['status']->color();
    }

    /** @return array{status: Status, problems: int, checkedAt: ?CarbonImmutable, stale: bool, empty: bool} */
    protected static function navigationState(): array
    {
        return once(fn (): array => static::overview(static::checks()));
    }

    public function setPeriod(string $period): void
    {
        $this->period = isset(Charts::PERIODS[$period]) ? $period : '24h';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('incidents')
                ->label(__('monitor::incidents.title'))
                ->icon(Heroicon::OutlinedClock)
                ->color('gray')
                ->badge(fn (): ?int => MonitorIncident::query()->whereNull('resolved_at')->count() ?: null)
                ->badgeColor('danger')
                ->url(fn (): string => MonitorIncidents::getUrl()),
            Action::make('testAlert')
                ->label(__('monitor::monitor.test.action'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading(__('monitor::monitor.test.action'))
                ->modalDescription(__('monitor::monitor.test.description'))
                ->modalSubmitActionLabel(__('monitor::monitor.test.submit'))
                ->action(function (): void {
                    $results = Notifier::fromConfig()->send(Alert::test());
                    $failed = array_filter($results);

                    Notification::make()
                        ->title(match (true) {
                            $results === [] => __('monitor::monitor.test.no_channels'),
                            $failed === [] => __('monitor::monitor.test.all_sent'),
                            default => __('monitor::monitor.test.some_failed'),
                        })
                        ->body(collect($results)->map(fn (?string $error, string $channel): string => e($channel).': '.e($error ?? __('monitor::monitor.test.sent')))->implode('<br>'))
                        ->status($results !== [] && $failed === [] ? 'success' : 'danger')
                        ->send();
                }),
        ];
    }

    /**
     * Silencia os alertas de uma verificação (os resultados continuam a aparecer).
     */
    public function silenceAction(): Action
    {
        return Action::make('silence')
            ->label(__('monitor::monitor.silence.action'))
            ->icon(Heroicon::OutlinedBellSlash)
            ->visible(fn (array $arguments): bool => ! ($arguments['silenced'] ?? false))
            ->modalHeading(fn (array $arguments): string => __('monitor::monitor.silence.heading', ['check' => $this->checkLabel($arguments)]))
            ->modalDescription(__('monitor::monitor.silence.description'))
            ->schema([
                Select::make('hours')
                    ->label(__('monitor::monitor.silence.for'))
                    ->options(collect([1, 4, 24, 168])->mapWithKeys(fn (int $hours): array => [$hours => __("monitor::monitor.silence.hours.{$hours}")])->all())
                    ->default(1)
                    ->selectablePlaceholder(false)
                    ->required(),
            ])
            ->modalSubmitActionLabel(__('monitor::monitor.silence.submit'))
            ->action(function (array $data, array $arguments): void {
                $until = now()->addHours((int) $data['hours']);
                MonitorCheck::silence($this->checkKey($arguments), $until);

                Notification::make()
                    ->title(__('monitor::monitor.silence.saved', ['check' => $this->checkLabel($arguments), 'time' => Alert::formatTime($until)]))
                    ->success()
                    ->send();
            });
    }

    public function resumeAlertsAction(): Action
    {
        return Action::make('resumeAlerts')
            ->label(__('monitor::monitor.silence.resume'))
            ->icon(Heroicon::OutlinedBell)
            ->visible(fn (array $arguments): bool => (bool) ($arguments['silenced'] ?? false))
            ->action(function (array $arguments): void {
                MonitorCheck::silence($this->checkKey($arguments), null);

                Notification::make()
                    ->title(__('monitor::monitor.silence.resumed', ['checks' => $this->checkLabel($arguments)]))
                    ->success()
                    ->send();
            });
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $checks = static::checks();

        return [
            'checks' => $checks,
            'overview' => static::overview($checks),
            'charts' => Charts::build(static::configuredChecks(), $this->period, CarbonImmutable::now()),
            'periods' => collect(Charts::PERIODS)->keys()->mapWithKeys(fn (string $key): array => [$key => __("monitor::charts.periods.{$key}")])->all(),
        ];
    }

    /**
     * Todas as verificações da configuração, também as desligadas neste processo: a dos contentores só corre no monitor
     * (que tem a API do Docker), mas os números dela estão na base de dados.
     *
     * @return array<string, Check>
     */
    public static function configuredChecks(): array
    {
        $checks = [];

        foreach ((array) config('monitor.checks') as $key => $config) {
            $checks[$key] = Check::fromConfig($key, $config);
        }

        return $checks;
    }

    /** A chave vem do browser: só uma das verificações configuradas. */
    private function checkKey(array $arguments): string
    {
        $key = (string) ($arguments['check'] ?? '');
        abort_unless(array_key_exists($key, (array) config('monitor.checks')), 404);

        return $key;
    }

    private function checkLabel(array $arguments): string
    {
        $key = $this->checkKey($arguments);

        return MonitorCheck::query()->where('key', $key)->value('label') ?? $key;
    }

    /**
     * Pela ordem da configuração, os problemas primeiro.
     *
     * @return Collection<int, MonitorCheck>
     */
    public static function checks(): Collection
    {
        $order = array_flip(array_keys((array) config('monitor.checks')));
        $rank = [Status::Failed->value => 0, Status::Warning->value => 1, Status::Ok->value => 2];

        return MonitorCheck::query()->get()
            ->sortBy([
                fn (MonitorCheck $a, MonitorCheck $b): int => $rank[$a->status->value] <=> $rank[$b->status->value],
                fn (MonitorCheck $a, MonitorCheck $b): int => ($order[$a->key] ?? PHP_INT_MAX) <=> ($order[$b->key] ?? PHP_INT_MAX),
            ])
            ->values();
    }

    /**
     * @param  Collection<int, MonitorCheck>  $checks
     * @return array{status: Status, problems: int, checkedAt: ?CarbonImmutable, stale: bool, empty: bool}
     */
    public static function overview(Collection $checks): array
    {
        $checkedAt = $checks->max('checked_at');

        return [
            'status' => Status::worst(...$checks->pluck('status')->all()),
            'problems' => $checks->filter(fn (MonitorCheck $check): bool => $check->status !== Status::Ok)->count(),
            'checkedAt' => $checkedAt,
            'stale' => $checkedAt !== null && $checkedAt->lt(now()->subMinutes((int) config('monitor.stale_after', 3))),
            'empty' => $checks->isEmpty(),
        ];
    }
}
