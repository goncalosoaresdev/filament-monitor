<?php

namespace GoncaloSoares\FilamentMonitor\Channels;

use GoncaloSoares\FilamentMonitor\Alert;
use GoncaloSoares\FilamentMonitor\Mail\AlertMail;
use Illuminate\Support\Facades\Mail;

/**
 * Email para os endereços da configuração (e não para os utilizadores da base de dados: tem de funcionar sem ela).
 */
final class MailChannel implements Channel
{
    /**
     * @param  list<string>  $to
     */
    public function __construct(private readonly array $to) {}

    public function name(): string
    {
        return 'Email';
    }

    public function send(Alert $alert): void
    {
        Mail::to($this->to)->send(new AlertMail($alert));
    }
}
