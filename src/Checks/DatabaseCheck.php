<?php

namespace GoncaloSoares\FilamentMonitor\Checks;

use GoncaloSoares\FilamentMonitor\Check;
use GoncaloSoares\FilamentMonitor\Result;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Throwable;

/**
 * Base de dados (`connection`, por omissão a principal): responde a `select 1`? No PostgreSQL também as ligações abertas
 * face a `max_connections` (limites em percentagem: `warning`, `failure`) e o tamanho da base de dados.
 */
class DatabaseCheck extends Check
{
    protected function defaultLabel(): string
    {
        return __('monitor::checks.database.label');
    }

    public function charts(): array
    {
        return [['metric' => 'connections', 'label' => __('monitor::charts.connections'), 'unit' => '', 'decimals' => 0]];
    }

    public function run(): Result
    {
        $name = $this->option('connection');

        try {
            $db = DB::connection($name);
            $start = hrtime(true);
            $db->select('select 1');
            $ms = round((hrtime(true) - $start) / 1e6, 1);
        } catch (Throwable $e) {
            // Num processo longo, a ligação partida tem de ser refeita na próxima corrida.
            DB::purge($name);

            // A mensagem do PDO, sem o "(Connection: ..., Host: ..., Database: ..., SQL: ...)" que o Laravel junta.
            return Result::failed(__('monitor::checks.database.down', ['error' => Str::limit(($e->getPrevious() ?? $e)->getMessage(), 200)]));
        }

        if ($db->getDriverName() !== 'pgsql') {
            return Result::ok(__('monitor::checks.database.summary', ['ms' => Number::format($ms, maxPrecision: 1)]), ['response_ms' => $ms]);
        }

        $connections = (int) $db->scalar('select count(*) from pg_stat_activity');
        $max = max(1, (int) $db->scalar('show max_connections'));
        $size = (int) $db->scalar('select pg_database_size(current_database())');
        $percent = round($connections / $max * 100, 1);

        return Result::threshold(
            $percent,
            $this->floatOption('warning'),
            $this->floatOption('failure'),
            __('monitor::checks.database.summary_pgsql', [
                'ms' => Number::format($ms, maxPrecision: 1),
                'connections' => $connections,
                'max' => $max,
                'size' => Number::fileSize($size, maxPrecision: 1),
            ]),
            ['response_ms' => $ms, 'connections' => $connections, 'connections_percent' => $percent, 'size_mb' => round($size / 1024 ** 2, 1)],
        );
    }
}
