<?php

namespace GoncaloSoares\FilamentMonitor;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Status: string implements HasColor, HasLabel
{
    case Ok = 'ok';
    case Warning = 'warning';
    case Failed = 'failed';

    public function label(): string
    {
        return __("monitor::monitor.status.{$this->value}");
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return $this->color();
    }

    /** Cor do Filament (badges, notificações). */
    public function color(): string
    {
        return match ($this) {
            self::Ok => 'success',
            self::Warning => 'warning',
            self::Failed => 'danger',
        };
    }

    public function isWorseThan(self $other): bool
    {
        return $this->rank() > $other->rank();
    }

    public static function worst(self ...$statuses): self
    {
        $worst = self::Ok;

        foreach ($statuses as $status) {
            if ($status->isWorseThan($worst)) {
                $worst = $status;
            }
        }

        return $worst;
    }

    private function rank(): int
    {
        return match ($this) {
            self::Ok => 0,
            self::Warning => 1,
            self::Failed => 2,
        };
    }
}
