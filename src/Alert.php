<?php

namespace GoncaloSoares\FilamentMonitor;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Support\Facades\FilamentTimezone;

/**
 * Um alerta a enviar. O texto é o mesmo em todos os canais: título curto + resumo + desde quando.
 */
final class Alert
{
    /**
     * @param  Status  $status  o estado que alertou (num "recuperado", o que estava avisado)
     */
    public function __construct(
        public readonly AlertType $type,
        public readonly string $check,
        public readonly string $label,
        public readonly Status $status,
        public readonly string $summary,
        public readonly CarbonImmutable $startedAt,
        public readonly CarbonImmutable $at,
    ) {}

    public static function test(): self
    {
        $now = CarbonImmutable::now();

        return new self(AlertType::Test, 'test', __('monitor::monitor.alert.test_label'), Status::Ok, __('monitor::monitor.alert.test_summary'), $now, $now);
    }

    /** Ex.: "[Vendus] Falha: Disco". */
    public function title(): string
    {
        $prefix = config('monitor.name') ? '['.config('monitor.name').'] ' : '';

        return $prefix.__("monitor::monitor.alert.title.{$this->titleKey()}", ['check' => $this->label]);
    }

    /** O emoji do estado, para o Telegram. */
    public function emoji(): string
    {
        return match (true) {
            $this->type === AlertType::Test => 'ℹ️',
            $this->type === AlertType::Recovered => '🟢',
            $this->status === Status::Failed => '🔴',
            default => '🟠',
        };
    }

    /**
     * Linhas depois do título: o resumo e desde quando (ou quanto durou).
     *
     * @return list<string>
     */
    public function lines(): array
    {
        $lines = [$this->summary];

        if ($this->type === AlertType::Recovered) {
            $lines[] = __('monitor::monitor.alert.lasted', ['duration' => $this->duration()]);
        } elseif ($this->type !== AlertType::Test) {
            $lines[] = __('monitor::monitor.alert.since', ['time' => self::formatTime($this->startedAt), 'duration' => $this->duration()]);
        }

        return $lines;
    }

    public function duration(): string
    {
        return $this->startedAt->diffForHumans($this->at, CarbonInterface::DIFF_ABSOLUTE, short: false, parts: 2);
    }

    /** Hora na zona dos painéis: "14:05", ou "28/10 14:05" se não for de hoje. */
    public static function formatTime(CarbonInterface $time): string
    {
        $local = $time->toImmutable()->setTimezone(FilamentTimezone::get());

        return $local->isSameDay(CarbonImmutable::now(FilamentTimezone::get())) ? $local->format('H:i') : $local->format('d/m H:i');
    }

    private function titleKey(): string
    {
        return match ($this->type) {
            AlertType::Opened, AlertType::Escalated => $this->status === Status::Failed ? 'failed' : 'warning',
            AlertType::Reminder => 'reminder',
            AlertType::Recovered => 'recovered',
            AlertType::Test => 'test',
        };
    }
}
