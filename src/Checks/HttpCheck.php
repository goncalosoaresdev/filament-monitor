<?php

namespace GoncaloSoares\FilamentMonitor\Checks;

use GoncaloSoares\FilamentMonitor\Check;
use GoncaloSoares\FilamentMonitor\Result;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Throwable;

/**
 * Um endereço responde com `status` (200) dentro de `timeout` segundos (10)? Mais lento do que `slow_ms` é aviso.
 */
class HttpCheck extends Check
{
    protected function defaultLabel(): string
    {
        return __('monitor::checks.http.label', ['host' => parse_url((string) $this->option('url'), PHP_URL_HOST) ?: $this->option('url')]);
    }

    public function charts(): array
    {
        return [['metric' => 'response_ms', 'label' => __('monitor::charts.response_time'), 'unit' => 'ms', 'decimals' => 0, 'warning' => $this->floatOption('slow_ms')]];
    }

    public function run(): Result
    {
        $start = hrtime(true);

        try {
            $response = Http::timeout((int) $this->option('timeout', 10))
                ->withHeaders((array) $this->option('headers', []))
                ->withoutRedirecting()
                ->get((string) $this->option('url'));
        } catch (Throwable $e) {
            return Result::failed(__('monitor::checks.http.down', ['error' => Str::limit($e->getMessage(), 200)]));
        }

        $ms = (int) round((hrtime(true) - $start) / 1e6);
        $metrics = ['response_ms' => $ms];

        if ($response->status() !== (int) $this->option('status', 200)) {
            return Result::failed(__('monitor::checks.http.status', ['status' => $response->status()]), $metrics);
        }

        return Result::threshold($ms, $this->floatOption('slow_ms'), null, __('monitor::checks.http.summary', ['ms' => Number::format($ms)]), $metrics);
    }
}
