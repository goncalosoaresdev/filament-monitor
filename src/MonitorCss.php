<?php

namespace GoncaloSoares\FilamentMonitor;

use Filament\Support\Assets\Css;

/**
 * O Filament versiona o URL do CSS (`?v=`) com a versão do pacote, que num pacote local não muda quando o CSS muda;
 * com o hash do conteúdo, os browsers deixam de usar a cópia antiga assim que o ficheiro muda.
 */
class MonitorCss extends Css
{
    private ?string $contentHash = null;

    public function getVersion(): string
    {
        return $this->contentHash ??= substr((string) md5_file($this->getPath()), 0, 12);
    }
}
