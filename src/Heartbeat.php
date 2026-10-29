<?php

namespace GoncaloSoares\FilamentMonitor;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;

/**
 * Batimentos em ficheiros (config('monitor.path')), não no Redis nem na base de dados: têm de funcionar com eles em baixo.
 * O `monitor:work` bate como "monitor" e o scheduler (`monitor:heartbeat`) como "scheduler"; cada um vigia o do outro.
 */
final class Heartbeat
{
    public static function beat(string $name): void
    {
        File::ensureDirectoryExists(self::directory());
        File::put(self::file($name), (string) now()->getTimestamp(), lock: true);
    }

    public static function last(string $name): ?CarbonImmutable
    {
        $file = self::file($name);
        $time = is_file($file) ? (int) @file_get_contents($file) : 0;

        return $time > 0 ? CarbonImmutable::createFromTimestamp($time) : null;
    }

    public static function file(string $name): string
    {
        return self::directory()."/{$name}.heartbeat";
    }

    private static function directory(): string
    {
        return rtrim((string) config('monitor.path'), '/');
    }
}
