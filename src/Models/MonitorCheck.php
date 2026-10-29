<?php

namespace GoncaloSoares\FilamentMonitor\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use GoncaloSoares\FilamentMonitor\Status;
use Illuminate\Database\Eloquent\Model;

/**
 * Último resultado de uma verificação, gravado pelo `monitor:work`.
 *
 * @property int $id
 * @property string $key
 * @property string $label
 * @property Status $status
 * @property string $summary
 * @property array<string, int|float>|null $metrics
 * @property CarbonImmutable $since
 * @property CarbonImmutable $checked_at
 * @property CarbonImmutable|null $silenced_until
 */
class MonitorCheck extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'metrics' => 'array',
            'since' => 'immutable_datetime',
            'checked_at' => 'immutable_datetime',
            'silenced_until' => 'immutable_datetime',
        ];
    }

    /**
     * Silencia até $until (null retoma). Uma verificação que ainda não correu fica com uma linha à espera do monitor.
     */
    public static function silence(string $key, ?CarbonInterface $until): void
    {
        $check = static::query()->firstOrNew(['key' => $key]);

        if (! $check->exists) {
            $check->fill(['label' => $key, 'status' => Status::Ok, 'summary' => '', 'since' => now(), 'checked_at' => now()]);
        }

        $check->silenced_until = $until?->toImmutable();
        $check->save();
    }

    public function isSilenced(): bool
    {
        return $this->silenced_until?->isFuture() ?? false;
    }
}
