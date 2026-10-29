<?php

namespace GoncaloSoares\FilamentMonitor\Models;

use Carbon\CarbonImmutable;
use GoncaloSoares\FilamentMonitor\Status;
use Illuminate\Database\Eloquent\Model;

/**
 * Uma série de alertas de uma verificação: abriu, agravou, recuperou. Os antigos saem no monitor:prune (o model:prune
 * só procura em app/Models).
 *
 * @property int $id
 * @property string $check
 * @property string $label
 * @property Status $status
 * @property string $summary
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $notified_at
 * @property CarbonImmutable|null $resolved_at
 */
class MonitorIncident extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'started_at' => 'immutable_datetime',
            'notified_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
        ];
    }
}
