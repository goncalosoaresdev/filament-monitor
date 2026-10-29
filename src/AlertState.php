<?php

namespace GoncaloSoares\FilamentMonitor;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;

/**
 * Quando alertar, por verificação. Fica num ficheiro (e não na base de dados nem no Redis) para "base de dados em baixo"
 * também ser avisado, e para sobreviver a um reinício do monitor (deploy, --max-time).
 *
 * - Abre quando o estado (aviso ou falha) se mantém `for` minutos: um pico de um minuto não alerta.
 * - Agrava quando passa de aviso a falha (também mantida `for` minutos).
 * - Lembra de `remind_every` em `remind_every` minutos enquanto continuar.
 * - Recupera quando está bem há `for` minutos (sem isto, um valor a oscilar no limite alertava sem parar).
 */
final class AlertState
{
    /**
     * @param  array<string, array{status: string, since: int, alerted: ?string, started_at: ?int, notified_at: ?int, incident: ?int}>  $checks
     */
    private function __construct(private readonly string $file, private array $checks) {}

    public static function load(string $file): self
    {
        $data = is_file($file) ? json_decode((string) @file_get_contents($file), true) : null;

        return new self($file, is_array($data['checks'] ?? null) ? $data['checks'] : []);
    }

    /** Escrita atómica: um ficheiro a meio (monitor morto a gravar) não pode apagar o estado. */
    public function save(): void
    {
        File::ensureDirectoryExists(dirname($this->file));

        $temporary = $this->file.'.'.getmypid().'.tmp';
        File::put($temporary, (string) json_encode(['checks' => $this->checks], JSON_PRETTY_PRINT));
        rename($temporary, $this->file);
    }

    /**
     * Regista o resultado de uma corrida e devolve o alerta a enviar, se houver. Silenciada, só pode devolver o
     * "recuperado" (para fechar o incidente), que não se envia.
     */
    public function apply(string $key, string $label, Result $result, int $alertAfterMinutes, int $remindEveryMinutes, CarbonImmutable $now, bool $silenced = false): ?Alert
    {
        $time = $now->getTimestamp();
        $entry = $this->checks[$key] ?? ['status' => Status::Ok->value, 'since' => $time, 'alerted' => null, 'started_at' => null, 'notified_at' => null, 'incident' => null];

        if ($entry['status'] !== $result->status->value) {
            $entry['status'] = $result->status->value;
            $entry['since'] = $time;
        }

        $held = $time - $entry['since'] >= $alertAfterMinutes * 60;
        $alerted = $entry['alerted'] !== null ? Status::from($entry['alerted']) : null;
        $type = null;

        if ($held && $result->status !== Status::Ok) {
            $type = match (true) {
                $alerted === null => AlertType::Opened,
                $result->status->isWorseThan($alerted) => AlertType::Escalated,
                $result->status === $alerted && $time - (int) $entry['notified_at'] >= $remindEveryMinutes * 60 => AlertType::Reminder,
                default => null,
            };

            if ($silenced) {
                // Nada muda enquanto está silenciado: quando acabar, avisa o que ainda estiver a falhar.
                $type = null;
            } else {
                if ($type === AlertType::Opened) {
                    $entry['started_at'] = $entry['since'];
                }

                // Melhorou de falha para aviso: sem alerta, mas uma nova falha volta a avisar.
                $entry['alerted'] = $result->status->value;
            }
        } elseif ($held && $alerted !== null) {
            // Também silenciado: o incidente fecha (quem chama decide não enviar).
            $type = AlertType::Recovered;
        }

        // Ao recuperar, o estado do alerta é o que estava avisado (o incidente fica com ele).
        $alert = $type === null ? null : new Alert(
            $type,
            $key,
            $label,
            $type === AlertType::Recovered ? $alerted : $result->status,
            $result->summary,
            CarbonImmutable::createFromTimestamp((int) ($entry['started_at'] ?? $entry['since'])),
            $now,
        );

        if ($type !== null) {
            $entry['notified_at'] = $time;
        }

        if ($type === AlertType::Recovered) {
            $entry['alerted'] = $entry['started_at'] = $entry['incident'] = null;
        }

        $this->checks[$key] = $entry;

        return $alert;
    }

    /** Desde quando está no estado atual (para os cartões da página). */
    public function since(string $key): ?CarbonImmutable
    {
        return isset($this->checks[$key]) ? CarbonImmutable::createFromTimestamp($this->checks[$key]['since']) : null;
    }

    public function incident(string $key): ?int
    {
        return $this->checks[$key]['incident'] ?? null;
    }

    public function setIncident(string $key, ?int $id): void
    {
        if (isset($this->checks[$key])) {
            $this->checks[$key]['incident'] = $id;
        }
    }

    /**
     * Esquece as verificações que saíram da configuração.
     *
     * @param  list<string>  $keys
     */
    public function keepOnly(array $keys): void
    {
        $this->checks = array_intersect_key($this->checks, array_flip($keys));
    }
}
