{{--
    Monitorização (GoncaloSoares\FilamentMonitor\Filament\Monitoring): o último resultado de cada verificação, gravado pelo
    monitor:work, e o histórico em gráficos (GoncaloSoares\FilamentMonitor\Charts, já calculado). Estilos .fm-* em
    resources/css/monitor.css (registado no Filament pelo pacote).
--}}
@php
    use Carbon\CarbonInterface;
    use Filament\Support\Icons\Heroicon;
    use GoncaloSoares\FilamentMonitor\Alert;
    use GoncaloSoares\FilamentMonitor\Status;

    $state = match (true) {
        $overview['empty'] => 'empty',
        $overview['stale'] => 'stale',
        default => $overview['status']->value,
    };
@endphp

<x-filament-panels::page>
    <div class="fm" wire:poll.30s>
        <section class="fm-overview" data-status="{{ $state }}" role="status">
            <span class="fm-overview-dot" aria-hidden="true"></span>
            <div>
                <h2 class="fm-overview-title">
                    @switch($state)
                        @case('empty') {{ __('monitor::monitor.overview.empty') }} @break
                        @case('stale') {{ __('monitor::monitor.overview.stale') }} @break
                        @case('ok') {{ __('monitor::monitor.overview.ok') }} @break
                        @default {{ trans_choice('monitor::monitor.overview.problems', $overview['problems'], ['count' => $overview['problems']]) }}
                    @endswitch
                </h2>
                <p class="fm-overview-meta">
                    @if ($state === 'empty')
                        {!! __('monitor::monitor.overview.empty_help') !!}
                    @elseif ($state === 'stale')
                        {!! __('monitor::monitor.overview.stale_help', ['time' => e(Alert::formatTime($overview['checkedAt'])), 'ago' => e($overview['checkedAt']->diffForHumans())]) !!}
                    @else
                        {{ __('monitor::monitor.overview.checked', ['ago' => $overview['checkedAt']->diffForHumans()]) }}
                    @endif
                </p>
            </div>
        </section>

        @if ($checks->isNotEmpty())
            <div @class(['fm-grid', 'is-stale' => $overview['stale']])>
                @foreach ($checks as $check)
                    <article class="fm-card" data-status="{{ $check->status->value }}" wire:key="check-{{ $check->key }}">
                        <header class="fm-card-head">
                            <span class="fm-dot" aria-hidden="true"></span>
                            <h3 class="fm-card-title">{{ $check->label }}</h3>
                            {{-- Só os problemas levam etiqueta: com tudo bem, o ponto chega e a página fica calma. --}}
                            @if ($check->status !== Status::Ok)
                                <x-filament::badge :color="$check->status->color()" size="sm">{{ $check->status->label() }}</x-filament::badge>
                            @endif
                            <x-filament-actions::group
                                :actions="[
                                    ($this->silenceAction)(['check' => $check->key, 'silenced' => $check->isSilenced()]),
                                    ($this->resumeAlertsAction)(['check' => $check->key, 'silenced' => $check->isSilenced()]),
                                ]"
                                :label="__('monitor::monitor.card.actions')"
                                color="gray"
                                size="sm"
                                dropdown-placement="bottom-end"
                            />
                        </header>
                        <p class="fm-card-summary">{{ $check->summary }}</p>
                        <p class="fm-card-meta">
                            @if ($check->status === Status::Ok)
                                {{ __('monitor::monitor.card.ok_since', ['ago' => $check->since->diffForHumans(syntax: CarbonInterface::DIFF_ABSOLUTE)]) }}
                            @else
                                {{ __('monitor::monitor.card.since', ['time' => Alert::formatTime($check->since), 'ago' => $check->since->diffForHumans()]) }}
                            @endif
                        </p>
                        @if ($check->isSilenced())
                            <p class="fm-card-silenced">
                                <x-filament::icon :icon="Heroicon::OutlinedBellSlash" class="fm-card-silenced-icon" />
                                {{ __('monitor::monitor.card.silenced', ['time' => Alert::formatTime($check->silenced_until)]) }}
                            </p>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif

        @if ($charts['lines'] !== [] || $charts['bars'] !== [])
            <section class="fm-history" wire:loading.class="is-loading" wire:target="setPeriod">
                <header class="fm-history-head">
                    <h2 class="fm-history-title">{{ __('monitor::charts.title') }}</h2>
                    <div class="fm-seg" role="group" aria-label="{{ __('monitor::charts.period') }}">
                        @foreach ($periods as $key => $label)
                            <button type="button" wire:click="setPeriod('{{ $key }}')" @class(['is-on' => $period === $key]) @if ($period === $key) aria-pressed="true" @endif>{{ $label }}</button>
                        @endforeach
                    </div>
                </header>

                <div class="fm-charts">
                    @foreach ($charts['lines'] as $chart)
                        @include('monitor::pages.partials.line-chart', ['chart' => $chart])
                    @endforeach

                    @foreach ($charts['bars'] as $chart)
                        @include('monitor::pages.partials.bar-chart', ['chart' => $chart])
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
