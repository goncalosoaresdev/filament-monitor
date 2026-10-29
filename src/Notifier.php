<?php

namespace GoncaloSoares\FilamentMonitor;

use GoncaloSoares\FilamentMonitor\Channels\Channel;
use GoncaloSoares\FilamentMonitor\Channels\DatabaseChannel;
use GoncaloSoares\FilamentMonitor\Channels\MailChannel;
use GoncaloSoares\FilamentMonitor\Channels\TelegramChannel;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envia um alerta por todos os canais configurados. Um canal que falha não impede os outros (ex. base de dados em
 * baixo: o sino falha, o Telegram e o email seguem).
 */
final class Notifier
{
    /**
     * @param  list<Channel>  $channels
     */
    public function __construct(private readonly array $channels) {}

    public static function fromConfig(): self
    {
        $config = (array) config('monitor.channels');
        $channels = [];

        // Primeiro o que não depende de nada nosso.
        $telegram = $config['telegram'] ?? [];
        if (! empty($telegram['token']) && ! empty($telegram['chat_id'])) {
            $channels[] = new TelegramChannel((string) $telegram['token'], (string) $telegram['chat_id']);
        }

        $to = $config['mail']['to'] ?? [];
        $to = array_values(array_filter(array_map('trim', is_array($to) ? $to : explode(',', (string) $to))));
        if ($to !== []) {
            $channels[] = new MailChannel($to);
        }

        $database = $config['database'] ?? [];
        if (! empty($database['model'])) {
            $channels[] = new DatabaseChannel($database['model'], (array) ($database['where'] ?? []), config('monitor.panel'));
        }

        return new self($channels);
    }

    /**
     * @return array<string, ?string> canal => erro (null quando foi enviado)
     */
    public function send(Alert $alert): array
    {
        $results = [];

        foreach ($this->channels as $channel) {
            try {
                $channel->send($alert);
                $results[$channel->name()] = null;
            } catch (Throwable $e) {
                $results[$channel->name()] = $e->getMessage();
                Log::error("Monitorização: o alerta \"{$alert->title()}\" não saiu por {$channel->name()}: {$e->getMessage()}");
            }
        }

        if ($this->channels === []) {
            Log::warning("Monitorização: nenhum canal de alertas configurado (\"{$alert->title()}\").");
        }

        return $results;
    }
}
