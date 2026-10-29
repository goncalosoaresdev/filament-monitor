<?php

namespace GoncaloSoares\FilamentMonitor\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;

/**
 * Posto na fila pelo QueueCheck: quando um worker o corre, grava a hora. Prova que a fila anda de ponta a ponta (Redis,
 * worker vivo e não preso noutro trabalho), o que o healthcheck do contentor não vê.
 */
class QueueCanary implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public function __construct(public string $cacheKey, public int $sentAt) {}

    public function handle(): void
    {
        Cache::put($this->cacheKey, ['sent' => $this->sentAt, 'done' => now()->getTimestamp()], now()->addDays(30));
    }
}
