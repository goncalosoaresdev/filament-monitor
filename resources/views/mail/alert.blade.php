{{-- Email só de texto: {!! !!} para o & e as aspas não chegarem como &amp; e &quot;. --}}
@foreach ($lines as $line)
{!! $line !!}
@endforeach
@if ($url)

{!! __('monitor::monitor.view') !!}: {!! $url !!}
@endif
