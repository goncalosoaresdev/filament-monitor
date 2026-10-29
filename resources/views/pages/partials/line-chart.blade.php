{{--
    Um número ao longo do período (Charts::line): uma série só, por isso sem legenda (o título diz o que é). O valor do
    canto é o mais recente; com o rato em cima, o desse ponto e a hora, com uma linha vertical a marcar o sítio.
    Desenhado em HTML (alturas em %) e um SVG esticado só para a linha: acompanha a largura sem JavaScript.
--}}
<article class="fm-chart" wire:key="chart-{{ $chart['id'] }}-{{ md5(json_encode($chart['values'])) }}" x-data="{
    hover: null,
    n: {{ $chart['n'] }},
    values: @js($chart['values']),
    heights: @js($chart['heights']),
    times: @js($chart['times']),
    move(event) {
        const box = this.$refs.plot.getBoundingClientRect()
        this.hover = Math.max(0, Math.min(this.n - 1, Math.floor((event.clientX - box.left) / box.width * this.n)))
    },
}">
    <header class="fm-chart-head">
        <div class="fm-chart-names">
            <h3 class="fm-chart-title">{{ $chart['title'] }}</h3>
            {{-- Os limites ficam aqui e não junto das linhas: dois limites próximos (85% e 95%) tapavam-se um ao outro. --}}
            <p class="fm-chart-subtitle">{{ collect([$chart['subtitle'], ...array_column($chart['limits'], 'label')])->implode(' · ') }}</p>
        </div>
        <div class="fm-chart-readout" aria-live="polite">
            <strong x-text="hover === null ? @js($chart['current']) : (values[hover] ?? '-')">{{ $chart['current'] }}</strong>
            <span x-text="hover === null ? @js(__('monitor::charts.now')) : times[hover]">{{ __('monitor::charts.now') }}</span>
        </div>
    </header>

    <div class="fm-plot-area">
        <div class="fm-yaxis" aria-hidden="true">
            @foreach ($chart['ticks'] as [$bottom, $label])
                <span style="bottom: {{ $bottom }}%">{{ $label }}</span>
            @endforeach
        </div>

        <div class="fm-plot" x-ref="plot" x-on:pointermove="move($event)" x-on:pointerleave="hover = null" role="img"
            aria-label="{{ $chart['title'] }}, {{ $chart['subtitle'] }}: {{ $chart['current'] }}">
            @foreach ($chart['ticks'] as [$bottom])
                <span @class(['fm-grid-line', 'is-base' => $bottom == 0]) style="bottom: {{ $bottom }}%"></span>
            @endforeach

            @foreach ($chart['limits'] as $limit)
                <span class="fm-limit" data-status="{{ $limit['status'] }}" style="bottom: {{ $limit['bottom'] }}%" title="{{ $limit['label'] }}"></span>
            @endforeach

            <svg class="fm-line" viewBox="0 0 {{ $chart['n'] }} 100" preserveAspectRatio="none" aria-hidden="true">
                @foreach ($chart['segments'] as $segment)
                    <polygon class="fm-line-area" points="{{ $segment['area'] }}" />
                    <polyline class="fm-line-stroke" points="{{ $segment['line'] }}" vector-effect="non-scaling-stroke" />
                @endforeach
            </svg>

            @if ($chart['last'] !== null)
                <span class="fm-point" x-show="hover === null" style="left: {{ ($chart['last'] + 0.5) / $chart['n'] * 100 }}%; bottom: {{ $chart['heights'][$chart['last']] }}%"></span>
            @endif

            <span class="fm-cross" x-show="hover !== null" x-cloak x-bind:style="`left: ${(hover + 0.5) / n * 100}%`"></span>
            <span class="fm-point" x-show="hover !== null && heights[hover] !== null" x-cloak
                x-bind:style="`left: ${(hover + 0.5) / n * 100}%; bottom: ${heights[hover]}%`"></span>
        </div>
    </div>

    <div class="fm-xaxis" aria-hidden="true">
        @foreach ($chart['axis'] as [$left, $label])
            <span style="left: {{ $left }}%">{{ $label }}</span>
        @endforeach
    </div>
</article>
