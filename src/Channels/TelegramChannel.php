<?php

namespace GoncaloSoares\FilamentMonitor\Channels;

use GoncaloSoares\FilamentMonitor\Alert;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Mensagem por um bot do Telegram (Bot API, sendMessage). O token vai no endereço do pedido: as mensagens de erro do
 * cliente HTTP trazem o endereço, por isso o token sai delas antes de chegarem ao log.
 */
final class TelegramChannel implements Channel
{
    public function __construct(private readonly string $token, private readonly string $chatId) {}

    public function name(): string
    {
        return 'Telegram';
    }

    public function send(Alert $alert): void
    {
        $text = $alert->emoji().' <b>'.e($alert->title()).'</b>'."\n".implode("\n", array_map(e(...), $alert->lines()));

        try {
            Http::timeout(10)
                ->post("https://api.telegram.org/bot{$this->token}/sendMessage", [
                    'chat_id' => $this->chatId,
                    'text' => $text,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ])
                ->throw();
        } catch (Throwable $e) {
            throw new RuntimeException(str_replace($this->token, '***', $e->getMessage()));
        }
    }
}
