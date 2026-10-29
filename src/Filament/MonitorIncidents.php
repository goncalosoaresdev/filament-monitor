<?php

namespace GoncaloSoares\FilamentMonitor\Filament;

use Carbon\CarbonInterface;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use GoncaloSoares\FilamentMonitor\Models\MonitorIncident;
use GoncaloSoares\FilamentMonitor\Status;
use Illuminate\Database\Eloquent\Builder;

/**
 * Histórico dos alertas: um incidente por série (abriu, agravou, recuperou), gravado pelo monitor:work. Fica fora do
 * menu; chega-se pela página Monitorização.
 */
class MonitorIncidents extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'monitor/alertas';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'monitor::pages.incidents';

    public static function canAccess(): bool
    {
        return FilamentMonitorPlugin::current()?->isAuthorized() ?? false;
    }

    public function getTitle(): string
    {
        return __('monitor::incidents.title');
    }

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return [
            Monitoring::getUrl() => __('monitor::monitor.navigation'),
            __('monitor::incidents.title'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(MonitorIncident::query())
            ->defaultSort('started_at', 'desc')
            ->columns([
                TextColumn::make('status')
                    ->label(__('monitor::incidents.status'))
                    ->badge(),
                TextColumn::make('label')
                    ->label(__('monitor::incidents.check'))
                    ->description(fn (MonitorIncident $record): string => $record->summary)
                    ->wrap()
                    ->searchable(['label', 'summary']),
                TextColumn::make('started_at')
                    ->label(__('monitor::incidents.started_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('duration')
                    ->label(__('monitor::incidents.duration'))
                    ->state(fn (MonitorIncident $record): string => $record->resolved_at
                        ? $record->started_at->diffForHumans($record->resolved_at, CarbonInterface::DIFF_ABSOLUTE, parts: 2)
                        : __('monitor::incidents.ongoing'))
                    ->badge(fn (MonitorIncident $record): bool => $record->resolved_at === null)
                    ->color(fn (MonitorIncident $record): ?string => $record->resolved_at === null ? $record->status->color() : null),
            ])
            ->filters([
                Filter::make('ongoing')
                    ->label(__('monitor::incidents.ongoing'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNull('resolved_at')),
                SelectFilter::make('status')
                    ->label(__('monitor::incidents.status'))
                    ->options([Status::Failed->value => Status::Failed->label(), Status::Warning->value => Status::Warning->label()]),
                SelectFilter::make('check')
                    ->label(__('monitor::incidents.check'))
                    ->options(fn (): array => MonitorIncident::query()->distinct()->orderBy('label')->pluck('label', 'check')->all()),
            ])
            ->emptyStateHeading(__('monitor::incidents.empty'))
            ->emptyStateDescription(__('monitor::incidents.empty_help'))
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
