<?php

namespace GoncaloSoares\FilamentMonitor\Mail;

use GoncaloSoares\FilamentMonitor\Alert;
use GoncaloSoares\FilamentMonitor\Filament\Monitoring;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Throwable;

/**
 * Email de alerta, só texto. Enviado na hora (sem ShouldQueue), de propósito: a fila pode ser o que está avariado.
 */
class AlertMail extends Mailable
{
    public function __construct(public readonly Alert $alert) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->alert->title());
    }

    public function content(): Content
    {
        return new Content(text: 'monitor::mail.alert', with: [
            'lines' => $this->alert->lines(),
            'url' => $this->pageUrl(),
        ]);
    }

    private function pageUrl(): ?string
    {
        try {
            $panel = config('monitor.panel');

            return $panel ? Monitoring::getUrl(panel: $panel) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
