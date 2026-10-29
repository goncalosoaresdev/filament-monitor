<?php

namespace GoncaloSoares\FilamentMonitor;

enum AlertType: string
{
    /** Começou a falhar (ou a avisar) e durou o tempo configurado. */
    case Opened = 'opened';

    /** Passou de aviso a falha. */
    case Escalated = 'escalated';

    /** Continua a falhar (de config('monitor.remind_every') em config('monitor.remind_every') minutos). */
    case Reminder = 'reminder';

    case Recovered = 'recovered';

    /** "Enviar alerta de teste". */
    case Test = 'test';
}
