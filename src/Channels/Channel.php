<?php

namespace GoncaloSoares\FilamentMonitor\Channels;

use GoncaloSoares\FilamentMonitor\Alert;

/**
 * Um canal envia o alerta na hora, nunca pela fila: a fila pode ser precisamente o que está avariado.
 */
interface Channel
{
    /** Nome curto, para o resultado do "Enviar alerta de teste" e para o log. */
    public function name(): string;

    public function send(Alert $alert): void;
}
