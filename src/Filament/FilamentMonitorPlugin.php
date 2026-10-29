<?php

namespace GoncaloSoares\FilamentMonitor\Filament;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * Regista a página "Monitorização" (e o cartão "Servidor" no painel de controlo) num painel:
 *
 *     FilamentMonitorPlugin::make()
 *         ->authorize(fn (): bool => auth()->user()?->is_super_admin)
 *         ->navigationGroup('Sistema')
 *
 * Sem authorize(), ninguém vê a página: o estado do servidor não é para todos.
 */
class FilamentMonitorPlugin implements Plugin
{
    public const ID = 'filament-monitor';

    protected ?Closure $authorize = null;

    protected ?string $navigationGroup = null;

    protected ?int $navigationSort = null;

    protected bool $dashboardWidget = true;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function current(): ?static
    {
        try {
            $plugin = filament(self::ID);
        } catch (\Throwable) {
            return null;
        }

        return $plugin instanceof static ? $plugin : null;
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function register(Panel $panel): void
    {
        $panel->pages([Monitoring::class, MonitorIncidents::class]);

        if ($this->dashboardWidget) {
            $panel->widgets([MonitorStatusWidget::class]);
        }
    }

    public function boot(Panel $panel): void {}

    /** Cartão "Servidor" no painel de controlo do painel (por omissão, sim). */
    public function dashboardWidget(bool $condition = true): static
    {
        $this->dashboardWidget = $condition;

        return $this;
    }

    public function authorize(Closure $callback): static
    {
        $this->authorize = $callback;

        return $this;
    }

    public function isAuthorized(): bool
    {
        return $this->authorize !== null && (bool) ($this->authorize)();
    }

    public function navigationGroup(?string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup;
    }

    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function getNavigationSort(): ?int
    {
        return $this->navigationSort;
    }
}
