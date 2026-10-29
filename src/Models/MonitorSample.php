<?php

namespace GoncaloSoares\FilamentMonitor\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Um número de uma verificação num minuto (`metric` = "disk.used_percent"). Os minutos ficam
 * config('monitor.full_resolution_hours') horas; depois passam a médias de 15 minutos, até
 * config('monitor.retention_days') dias (rollup()).
 *
 * @property int $id
 * @property string $metric
 * @property float $value
 * @property int $at minuto em tempo Unix (agrupar por 15 minutos é `at - at % 15`, igual em PostgreSQL e SQLite)
 */
class MonitorSample extends Model
{
    public const BUCKET_MINUTES = 15;

    public $timestamps = false;

    protected $guarded = [];

    public static function minute(CarbonInterface $time): int
    {
        return intdiv($time->getTimestamp(), 60);
    }

    /**
     * @param  array<string, int|float>  $values  metric => valor
     */
    public static function record(array $values, CarbonInterface $time): void
    {
        $at = self::minute($time);
        $rows = [];

        foreach ($values as $metric => $value) {
            $rows[] = ['metric' => $metric, 'value' => (float) $value, 'at' => $at];
        }

        if ($rows !== []) {
            static::query()->insert($rows);
        }
    }

    /**
     * Junta os minutos mais antigos do que a resolução total em médias de 15 minutos e apaga o que passou da retenção.
     * Só olha para os últimos dias antes do corte (o que está para trás já foi junto), por isso pode correr todos os dias.
     */
    public static function rollup(CarbonInterface $now): void
    {
        $bucket = self::BUCKET_MINUTES;
        $cutoff = self::minute($now->copy()->subHours((int) config('monitor.full_resolution_hours', 48)));
        $cutoff -= $cutoff % $bucket;
        $from = $cutoff - 3 * 24 * 60;

        DB::transaction(function () use ($bucket, $cutoff, $from) {
            $averages = static::query()
                ->whereBetween('at', [$from, $cutoff - 1])
                ->selectRaw("metric, at - at % {$bucket} as bucket, avg(value) as value")
                ->groupBy('metric', 'bucket')
                ->toBase()
                ->get();

            static::query()->whereBetween('at', [$from, $cutoff - 1])->delete();

            foreach ($averages->chunk(500) as $chunk) {
                static::query()->insert($chunk->map(fn (object $row): array => [
                    'metric' => $row->metric,
                    'value' => (float) $row->value,
                    'at' => (int) $row->bucket,
                ])->all());
            }
        });

        static::query()->where('at', '<', self::minute($now->copy()->subDays((int) config('monitor.retention_days', 30))))->delete();
    }
}
