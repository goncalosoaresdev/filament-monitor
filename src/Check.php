<?php

namespace GoncaloSoares\FilamentMonitor;

use InvalidArgumentException;

/**
 * Uma verificação, configurada em config('monitor.checks'):
 *
 *     'disk' => ['class' => DiskCheck::class, 'warning' => 80, 'failure' => 90, 'for' => 5],
 *
 * Opções comuns: `label`, `every` (segundos entre corridas, 60), `for` (minutos seguidos no mesmo estado antes de
 * alertar e antes de dar como recuperado, 0), `enabled`. As outras são de cada verificação.
 *
 * O objeto vive enquanto o `monitor:work` correr: pode guardar estado entre corridas (ex. o canário da fila).
 */
abstract class Check
{
    /**
     * @param  array<string, mixed>  $options
     */
    final public function __construct(public readonly string $key, protected readonly array $options = []) {}

    abstract public function run(): Result;

    abstract protected function defaultLabel(): string;

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(string $key, array $config): self
    {
        $class = $config['class'] ?? null;

        if (! is_string($class) || ! is_subclass_of($class, self::class)) {
            throw new InvalidArgumentException("monitor.checks.{$key}: `class` tem de ser uma verificação (".self::class.').');
        }

        return new $class($key, $config);
    }

    /**
     * Gráficos desta verificação na página. Cada um:
     * - `metric`: um dos números do Result; `label`; `unit` ("%", "ms", "MB" ou ""); `decimals`;
     * - `max`: eixo fixo (100 nas percentagens); `warning` / `failure`: linhas dos limites, na mesma unidade;
     * - `type` => 'bars' com `metric` "*.x": uma barra por número que acabe em ".x" (ex. memória por contentor), com
     *   `extra` / `extra_label` / `extra_unit` (outro número do mesmo item, ex. o CPU) ao lado.
     *
     * @return list<array<string, mixed>>
     */
    public function charts(): array
    {
        return [];
    }

    public function label(): string
    {
        return (string) ($this->options['label'] ?? $this->defaultLabel());
    }

    public function every(): int
    {
        return max(1, (int) ($this->options['every'] ?? 60));
    }

    /** Minutos seguidos no mesmo estado antes de alertar (e de dar como recuperado). */
    public function alertAfter(): int
    {
        return max(0, (int) ($this->options['for'] ?? 0));
    }

    protected function option(string $name, mixed $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }

    protected function floatOption(string $name): ?float
    {
        $value = $this->options[$name] ?? null;

        return $value === null ? null : (float) $value;
    }
}
