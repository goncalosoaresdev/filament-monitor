<?php

namespace GoncaloSoares\FilamentMonitor;

use Carbon\CarbonImmutable;
use Filament\Support\Facades\FilamentTimezone;
use GoncaloSoares\FilamentMonitor\Models\MonitorCheck;
use GoncaloSoares\FilamentMonitor\Models\MonitorSample;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

/**
 * Os gráficos da página Monitorização, já prontos a desenhar: a vista só põe as alturas (0 a 100) e os textos no sítio.
 *
 * - Linhas: um gráfico por número (Check::charts()), uma série só (sem legenda: o título diz o que é), com as linhas dos
 *   limites de alerta quando cabem na escala. Os minutos sem dados (monitor parado) ficam como um buraco na linha.
 * - Barras: um item por número que encaixe no padrão no último resultado da verificação (ex. a memória de cada contentor
 *   a correr), com a média do período.
 */
final class Charts
{
    /** Período => [minutos, minutos por ponto]. As médias de 15 minutos (MonitorSample::rollup) cabem em 60 e 240. */
    public const PERIODS = [
        '24h' => [24 * 60, 5],
        '7d' => [7 * 24 * 60, 60],
        '30d' => [30 * 24 * 60, 240],
    ];

    /**
     * @param  array<string, Check>  $checks
     * @return array{lines: list<array<string, mixed>>, bars: list<array<string, mixed>>}
     */
    public static function build(array $checks, string $period, CarbonImmutable $now): array
    {
        [$minutes, $step] = self::PERIODS[$period] ?? self::PERIODS['24h'];
        $last = MonitorSample::minute($now) - MonitorSample::minute($now) % $step;
        $first = $last - ($minutes / $step - 1) * $step;
        $buckets = range($first, $last, $step);

        $lines = [];
        $bars = [];

        foreach ($checks as $key => $check) {
            foreach ($check->charts() as $definition) {
                if (($definition['type'] ?? 'line') === 'bars') {
                    $bars[] = ['key' => $key, 'title' => $check->label(), 'definition' => $definition];
                } else {
                    $lines[] = ['key' => $key, 'title' => $check->label(), 'definition' => $definition, 'metric' => "{$key}.{$definition['metric']}"];
                }
            }
        }

        $values = self::lineValues(array_column($lines, 'metric'), $first, $step);
        $axis = self::axis($buckets, $period);
        $times = array_map(fn (int $minute): string => self::time($minute, $step), $buckets);

        return [
            'lines' => array_values(array_filter(array_map(
                fn (array $line): ?array => self::line($line, $buckets, $values[$line['metric']] ?? [], $axis, $times),
                $lines,
            ))),
            'bars' => array_values(array_filter(array_map(fn (array $bar): ?array => self::bars($bar, $first), $bars))),
        ];
    }

    /**
     * @param  list<string>  $metrics
     * @return array<string, array<int, float>> metric => minuto do ponto => média
     */
    private static function lineValues(array $metrics, int $first, int $step): array
    {
        if ($metrics === []) {
            return [];
        }

        $values = [];

        $rows = MonitorSample::query()
            ->whereIn('metric', $metrics)
            ->where('at', '>=', $first)
            ->selectRaw("metric, at - at % {$step} as bucket, avg(value) as value")
            ->groupBy('metric', 'bucket')
            ->toBase()
            ->get();

        foreach ($rows as $row) {
            $values[$row->metric][(int) $row->bucket] = (float) $row->value;
        }

        return $values;
    }

    /**
     * @param  array{key: string, title: string, definition: array<string, mixed>, metric: string}  $line
     * @param  list<int>  $buckets
     * @param  array<int, float>  $values
     * @param  list<array{float, string}>  $axis
     * @param  list<string>  $times
     * @return array<string, mixed>|null
     */
    private static function line(array $line, array $buckets, array $values, array $axis, array $times): ?array
    {
        if ($values === []) {
            return null;
        }

        $definition = $line['definition'];
        $series = array_map(fn (int $minute): ?float => $values[$minute] ?? null, $buckets);
        $top = isset($definition['max']) ? (float) $definition['max'] : self::niceMax(max(array_filter($series, fn ($v) => $v !== null)) * 1.15);
        // Só os limites que cabem na escala: um limite muito acima dos valores achataria a linha contra o fundo.
        $limits = [];

        foreach (['warning', 'failure'] as $status) {
            $limit = $definition[$status] ?? null;

            if ($limit !== null && $limit <= $top) {
                $limits[] = ['status' => $status, 'bottom' => round($limit / $top * 100, 2), 'label' => __("monitor::charts.limit_{$status}", ['value' => self::format($limit, $definition)])];
            }
        }

        $lastIndex = array_key_last(array_filter($series, fn ($v) => $v !== null));
        $format = fn (?float $value): ?string => $value === null ? null : self::format($value, $definition);

        $heights = array_map(fn (?float $v): ?float => $v === null ? null : round(min(100, $v / $top * 100), 2), $series);

        return [
            'id' => Str::slug($line['metric']),
            'title' => $line['title'],
            'subtitle' => $definition['label'],
            'n' => count($buckets),
            'heights' => $heights,
            'segments' => self::segments($heights),
            'values' => array_map($format, $series),
            'times' => $times,
            'axis' => $axis,
            'ticks' => array_map(fn (float $tick): array => [round($tick / $top * 100, 2), self::format($tick, $definition, axisTop: $top)], [0, $top / 2, $top]),
            'limits' => $limits,
            'current' => $format($series[$lastIndex]),
            'last' => $lastIndex,
        ];
    }

    /**
     * Troços da linha no SVG (x = posição + 0,5, y = 100 - altura): um buraco nos dados interrompe a linha.
     *
     * @param  list<?float>  $heights
     * @return list<array{line: string, area: string}>
     */
    private static function segments(array $heights): array
    {
        $segments = [];
        $points = [];

        foreach ([...$heights, null] as $i => $height) {
            if ($height !== null) {
                $points[] = [$i + 0.5, 100 - $height];

                continue;
            }

            if (count($points) > 1) {
                $line = implode(' ', array_map(fn (array $p): string => "{$p[0]},{$p[1]}", $points));
                $segments[] = ['line' => $line, 'area' => "{$points[0][0]},100 {$line} ".end($points)[0].',100'];
            }

            $points = [];
        }

        return $segments;
    }

    /**
     * @param  array{key: string, title: string, definition: array<string, mixed>}  $bar
     * @return array<string, mixed>|null
     */
    private static function bars(array $bar, int $first): ?array
    {
        $definition = $bar['definition'];

        // Os itens (ex. os contentores) são os do último resultado: assim a consulta usa o índice (metric, at). Com
        // `like 'containers.%.memory_mb'` o PostgreSQL lia a tabela inteira (o índice não serve num like com esta collation).
        $suffix = Str::after($definition['metric'], '*.');
        $names = collect(array_keys(MonitorCheck::query()->where('key', $bar['key'])->first()->metrics ?? []))
            ->filter(fn (string $metric): bool => str_ends_with($metric, ".{$suffix}"))
            ->map(fn (string $metric): string => Str::beforeLast($metric, ".{$suffix}"))
            ->values();

        if ($names->isEmpty()) {
            return null;
        }

        $query = function (string $metric) use ($bar, $names, $first): array {
            $metrics = $names->mapWithKeys(fn (string $name): array => ["{$bar['key']}.{$name}.".Str::after($metric, '*.') => $name])->all();

            return MonitorSample::query()
                ->whereIn('metric', array_keys($metrics))
                ->where('at', '>=', $first)
                ->selectRaw('metric, avg(value) as average, max(value) as peak')
                ->groupBy('metric')
                ->toBase()
                ->get()
                ->mapWithKeys(fn (object $row): array => [$metrics[$row->metric] => ['average' => (float) $row->average, 'peak' => (float) $row->peak]])
                ->all();
        };

        $main = $query($definition['metric']);

        if ($main === []) {
            return null;
        }

        $extra = isset($definition['extra']) ? $query($definition['extra']) : [];
        uasort($main, fn (array $a, array $b): int => $b['average'] <=> $a['average']);
        $top = max(array_column($main, 'average')) ?: 1;
        $extraDefinition = ['unit' => $definition['extra_unit'] ?? '', 'decimals' => 0];

        return [
            'title' => $definition['label'],
            'extraLabel' => $definition['extra_label'] ?? null,
            'items' => array_map(fn (string $name, array $values): array => [
                'name' => $name,
                'width' => round($values['average'] / $top * 100, 2),
                'value' => self::format($values['average'], $definition),
                'peak' => self::format($values['peak'], $definition),
                'extra' => isset($extra[$name]) ? self::format($extra[$name]['average'], $extraDefinition) : null,
            ], array_keys($main), $main),
        ];
    }

    /**
     * Rótulos do eixo dos x, em percentagem da largura: as horas certas de 6 em 6 (24 h), dia sim dia não (7 dias) ou as
     * segundas-feiras (30 dias), na hora dos painéis.
     *
     * @param  list<int>  $buckets
     * @return list<array{float, string}>
     */
    private static function axis(array $buckets, string $period): array
    {
        $axis = [];
        $previous = null;
        $n = count($buckets);

        foreach ($buckets as $i => $minute) {
            $time = self::local($minute);
            $label = match ($period) {
                // Dia sim, dia não: os sete não cabem num gráfico pequeno.
                '7d' => $previous !== null && ! $time->isSameDay($previous) && $time->dayOfYear % 2 === 0 ? $time->translatedFormat('D j') : null,
                '30d' => $previous !== null && ! $time->isSameDay($previous) && $time->isMonday() ? $time->format('d/m') : null,
                default => $time->minute === 0 && $time->hour % 6 === 0 ? $time->format('H:i') : null,
            };
            $previous = $time;

            if ($label !== null) {
                $axis[] = [round($i / $n * 100, 2), $label];
            }
        }

        return $axis;
    }

    /** Texto do quadro ao passar o rato: "28/10 14:05" (ou "28/10 14:00-15:00" com pontos de uma hora ou mais). */
    private static function time(int $minute, int $step): string
    {
        $start = self::local($minute);

        return $step >= 60 ? $start->format('d/m H:i').'-'.$start->addMinutes($step)->format('H:i') : $start->format('d/m H:i');
    }

    private static function local(int $minute): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestamp($minute * 60)->setTimezone(FilamentTimezone::get());
    }

    /**
     * Um valor com a unidade de um gráfico de Check::charts() (ex. "62%", "180 ms"), para mostrar fora dos gráficos.
     *
     * @param  array<string, mixed>  $definition
     */
    public static function formatValue(float $value, array $definition): string
    {
        return self::format($value, $definition);
    }

    /**
     * Valor com a unidade. No eixo (`axisTop`, o topo da escala), as casas decimais que a escala precisa: numa fila com
     * no máximo 0,2 trabalhos à espera em média, o eixo seria "0, 0, 0" com as casas do valor (0).
     *
     * @param  array<string, mixed>  $definition
     */
    private static function format(float $value, array $definition, ?float $axisTop = null): string
    {
        $decimals = (int) ($definition['decimals'] ?? 0);
        $precision = $decimals;

        // O meio da escala (1,25 numa escala até 2,5) tem de se ler exatamente.
        while ($axisTop !== null && $precision < 3 && round($axisTop / 2, $precision) != $axisTop / 2) {
            $precision++;
        }
        $number = Number::format($value, maxPrecision: $precision);
        $unit = (string) ($definition['unit'] ?? '');

        return match ($unit) {
            '' => $number,
            '%' => "{$number}%",
            default => "{$number} {$unit}",
        };
    }

    /** O topo da escala: o número "redondo" (1, 2, 2,5 ou 5 vezes uma potência de 10) logo acima. */
    private static function niceMax(float $value): float
    {
        if ($value <= 0) {
            return 1;
        }

        $power = 10 ** floor(log10($value));

        foreach ([1, 2, 2.5, 5, 10] as $step) {
            if ($value <= $step * $power) {
                return $step * $power;
            }
        }

        return 10 * $power;
    }
}
