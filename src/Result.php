<?php

namespace GoncaloSoares\FilamentMonitor;

/**
 * O que uma verificação devolve: o estado, uma frase curta (vai nos alertas e nos cartões) e os números para os gráficos.
 */
final class Result
{
    /**
     * @param  array<string, int|float>  $metrics
     */
    public function __construct(
        public readonly Status $status,
        public readonly string $summary,
        public readonly array $metrics = [],
    ) {}

    /** @param  array<string, int|float>  $metrics */
    public static function ok(string $summary, array $metrics = []): self
    {
        return new self(Status::Ok, $summary, $metrics);
    }

    /** @param  array<string, int|float>  $metrics */
    public static function warning(string $summary, array $metrics = []): self
    {
        return new self(Status::Warning, $summary, $metrics);
    }

    /** @param  array<string, int|float>  $metrics */
    public static function failed(string $summary, array $metrics = []): self
    {
        return new self(Status::Failed, $summary, $metrics);
    }

    /**
     * Estado pelo valor: a partir de $failure é falha, a partir de $warning é aviso (null = sem esse limite).
     *
     * @param  array<string, int|float>  $metrics
     */
    public static function threshold(float $value, ?float $warning, ?float $failure, string $summary, array $metrics = []): self
    {
        $status = match (true) {
            $failure !== null && $value >= $failure => Status::Failed,
            $warning !== null && $value >= $warning => Status::Warning,
            default => Status::Ok,
        };

        return new self($status, $summary, $metrics);
    }
}
