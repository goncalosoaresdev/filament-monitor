{{--
    Um número por item no período (Charts::bars), ex. a memória de cada contentor: barras pela média, do maior para o
    menor, com o valor na ponta; o máximo e o número extra (CPU) em texto ao lado.
--}}
<article class="fm-chart fm-bars">
    <header class="fm-chart-head">
        <div class="fm-chart-names">
            <h3 class="fm-chart-title">{{ $chart['title'] }}</h3>
            <p class="fm-chart-subtitle">{{ __('monitor::charts.bars_subtitle') }}</p>
        </div>
    </header>

    <ul class="fm-bar-list">
        @foreach ($chart['items'] as $item)
            <li class="fm-bar-row" wire:key="bar-{{ $item['name'] }}">
                <span class="fm-bar-name">{{ $item['name'] }}</span>
                <span class="fm-bar-track">
                    {{-- A escala é a largura que sobra depois do valor na ponta: a maior barra nunca é cortada. --}}
                    <span class="fm-bar" style="width: calc((100% - 4.5rem) * {{ max(0.005, $item['width'] / 100) }})"></span>
                    <span class="fm-bar-value">{{ $item['value'] }}</span>
                </span>
                <span class="fm-bar-meta">
                    {{ __('monitor::charts.peak', ['value' => $item['peak']]) }}
                    @if ($item['extra'] !== null && $chart['extraLabel'])
                        · {{ $chart['extraLabel'] }} {{ $item['extra'] }}
                    @endif
                </span>
            </li>
        @endforeach
    </ul>
</article>
