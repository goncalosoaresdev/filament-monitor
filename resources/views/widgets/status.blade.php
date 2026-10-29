{{--
    Cartão "Servidor" do painel de controlo (GoncaloSoares\FilamentMonitor\Filament\MonitorStatusWidget). O mesmo estado
    da página Monitorização, em pequeno: o estado geral, até três problemas e os números principais.
--}}
@php
    use GoncaloSoares\FilamentMonitor\Status;

    $state = match (true) {
        $overview['empty'] => 'empty',
        $overview['stale'] => 'stale',
        default => $overview['status']->value,
    };
    $notes = array_filter([
        $ongoing ? trans_choice('monitor::widget.ongoing', $ongoing, ['count' => $ongoing]) : null,
        $silenced ? trans_choice('monitor::widget.silenced', $silenced, ['count' => $silenced]) : null,
    ]);
@endphp

<x-filament-widgets::widget>
    <section class="fm-widget" data-status="{{ $state }}" wire:poll.60s>
        <header class="fm-widget-head">
            <h2 class="fm-widget-title">{{ __('monitor::widget.title') }}</h2>
            <a href="{{ $url }}" class="fm-widget-link" wire:navigate>{{ __('monitor::widget.open') }}</a>
        </header>

        <div class="fm-widget-status" role="status">
            <span class="fm-overview-dot" aria-hidden="true"></span>
            <div>
                <p class="fm-widget-headline">
                    @switch($state)
                        @case('empty') {{ __('monitor::monitor.overview.empty') }} @break
                        @case('stale') {{ __('monitor::monitor.overview.stale') }} @break
                        @case('ok') {{ __('monitor::monitor.overview.ok') }} @break
                        @default {{ trans_choice('monitor::monitor.overview.problems', $overview['problems'], ['count' => $overview['problems']]) }}
                    @endswitch
                </p>
                <p class="fm-widget-meta">
                    @if ($state === 'empty')
                        {{ __('monitor::widget.empty') }}
                    @else
                        {{ __($state === 'stale' ? 'monitor::widget.last_result' : 'monitor::widget.checked', ['ago' => $overview['checkedAt']->diffForHumans()]) }}
                        @foreach ($notes as $note)
                            · {{ $note }}
                        @endforeach
                    @endif
                </p>
            </div>
        </div>

        @if ($problems->isNotEmpty())
            <ul class="fm-widget-problems">
                @foreach ($problems as $check)
                    <li data-status="{{ $check->status->value }}">
                        <span class="fm-dot" aria-hidden="true"></span>
                        <span class="fm-widget-problem-label">{{ $check->label }}</span>
                        <span class="fm-widget-problem-summary" title="{{ $check->summary }}">{{ $check->summary }}</span>
                    </li>
                @endforeach
                @if ($moreProblems > 0)
                    <li class="fm-widget-more"><a href="{{ $url }}" wire:navigate>{{ trans_choice('monitor::widget.more', $moreProblems, ['count' => $moreProblems]) }}</a></li>
                @endif
            </ul>
        @endif

        @if ($readings !== [])
            <dl @class(['fm-widget-readings', 'is-stale' => $overview['stale']])>
                @foreach ($readings as $reading)
                    <div data-status="{{ $reading['status']->value }}" title="{{ $reading['hint'] }}">
                        <dt>{{ $reading['label'] }}</dt>
                        <dd>
                            @if ($reading['status'] !== Status::Ok)
                                <span class="fm-dot" aria-hidden="true"></span>
                            @endif
                            {{ $reading['value'] }}
                        </dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </section>
</x-filament-widgets::widget>
