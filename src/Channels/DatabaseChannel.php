<?php

namespace GoncaloSoares\FilamentMonitor\Channels;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use GoncaloSoares\FilamentMonitor\Alert;
use GoncaloSoares\FilamentMonitor\AlertType;
use GoncaloSoares\FilamentMonitor\Filament\Monitoring;
use GoncaloSoares\FilamentMonitor\Status;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification as Notifications;
use Throwable;

/**
 * Sino do Filament dos utilizadores escolhidos na configuração (modelo + condições), gravado na hora. Precisa da base de
 * dados: quando ela está em baixo este canal falha e os outros seguem.
 */
final class DatabaseChannel implements Channel
{
    /**
     * @param  class-string<Model>  $model
     * @param  array<string, mixed>  $where
     */
    public function __construct(private readonly string $model, private readonly array $where, private readonly ?string $panel) {}

    public function name(): string
    {
        return __('monitor::monitor.channels.database');
    }

    public function send(Alert $alert): void
    {
        $users = $this->model::query()->where($this->where)->get();

        if ($users->isEmpty()) {
            return;
        }

        $notification = Notification::make()
            ->title($alert->title())
            ->body(implode('<br>', array_map(e(...), $alert->lines())))
            ->status(match (true) {
                $alert->type === AlertType::Test => 'info',
                $alert->type === AlertType::Recovered => 'success',
                $alert->status === Status::Failed => 'danger',
                default => 'warning',
            });

        if ($url = $this->pageUrl()) {
            $notification->actions([
                Action::make('view')
                    ->label(__('monitor::monitor.view'))
                    ->url($url)
                    ->markAsRead(),
            ]);
        }

        // Não pelo sendToDatabase(): a notificação do Filament é ShouldQueue e ia pela fila, que pode ser o que está avariado.
        Notifications::sendNow($users, $notification->toDatabase());
    }

    private function pageUrl(): ?string
    {
        try {
            return $this->panel ? Monitoring::getUrl(panel: $this->panel) : null;
        } catch (Throwable) {
            return null; // A página não está registada nesse painel.
        }
    }
}
