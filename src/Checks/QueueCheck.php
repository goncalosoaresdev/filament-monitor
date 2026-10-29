<?php

namespace GoncaloSoares\FilamentMonitor\Checks;

use GoncaloSoares\FilamentMonitor\Check;
use GoncaloSoares\FilamentMonitor\Jobs\QueueCanary;
use GoncaloSoares\FilamentMonitor\Result;
use GoncaloSoares\FilamentMonitor\Status;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

/**
 * Uma fila (`queue`, `connection`):
 * - trabalhos à espera (limites `size_warning`, `size_failure`);
 * - com `canary` (por omissão sim), um job pequeno (QueueCanary) a cada corrida, e há quantos minutos nenhum passou
 *   (limites `warning`, `failure`). Um canário perdido (falhou, Redis limpo) não conta: vale o mais recente que passou.
 *   Só em filas cujos workers aceitem qualquer job: numa fila que só corre jobs escolhidos (ex. App\Queue\TrustedJobs)
 *   o canário nunca passava.
 */
class QueueCheck extends Check
{
    /** Primeiro canário deste processo: até um voltar, conta-se daqui. */
    private ?int $firstSentAt = null;

    protected function defaultLabel(): string
    {
        return __('monitor::checks.queue.label', ['queue' => $this->queue()]);
    }

    public function charts(): array
    {
        return [['metric' => 'size', 'label' => __('monitor::charts.queue_size'), 'unit' => '', 'decimals' => 0, 'warning' => $this->floatOption('size_warning'), 'failure' => $this->floatOption('size_failure')]];
    }

    public function run(): Result
    {
        $size = Queue::connection($this->option('connection'))->size($this->queue());
        $sizeStatus = Result::threshold($size, $this->floatOption('size_warning'), $this->floatOption('size_failure'), '')->status;
        $sizeSummary = trans_choice('monitor::checks.queue.size', $size, ['count' => $size]);

        if (! $this->option('canary', true)) {
            return new Result($sizeStatus, $sizeSummary, ['size' => $size]);
        }

        $canary = $this->canary();

        return new Result(Status::worst($canary->status, $sizeStatus), $canary->summary.' '.$sizeSummary, ['size' => $size] + $canary->metrics);
    }

    private function canary(): Result
    {
        $now = now()->getTimestamp();
        $last = Cache::get($this->cacheKey());
        $last = is_array($last) ? $last : null;

        QueueCanary::dispatch($this->cacheKey(), $now)
            ->onConnection($this->option('connection'))
            ->onQueue($this->queue());
        $this->firstSentAt ??= $now;

        // Com a fila a andar, o último que passou foi posto na corrida anterior (há `every` segundos).
        $reference = $last['sent'] ?? $this->firstSentAt;
        $minutes = round(max(0, $now - $reference - $this->every()) / 60, 1);
        $metrics = ['canary_waiting_minutes' => $minutes];

        if ($last !== null) {
            $metrics['canary_seconds'] = max(0, $last['done'] - $last['sent']);
        }

        $summary = match (true) {
            $minutes >= 1 => __('monitor::checks.queue.waiting', ['minutes' => (int) floor($minutes)]),
            $last === null => __('monitor::checks.queue.sent'),
            default => __('monitor::checks.queue.passed', ['seconds' => $metrics['canary_seconds']]),
        };

        return Result::threshold($minutes, $this->floatOption('warning'), $this->floatOption('failure'), $summary, $metrics);
    }

    private function queue(): string
    {
        return (string) $this->option('queue', 'default');
    }

    private function cacheKey(): string
    {
        return "monitor:canary:{$this->key}";
    }
}
