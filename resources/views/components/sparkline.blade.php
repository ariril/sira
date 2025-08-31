@php
    use Illuminate\Support\Str;
    $chartId = $id ?? ('spark-'.Str::random(6));
    $type = $type ?? 'line';            // 'line' | 'area' | 'bar'
    $height = $height ?? 120;           // px
    $label = $label ?? 'Series';
    $data = $data ?? [];                // [1,2,3,...]
    $categories = $categories ?? [];    // ['Jan','Feb',...]
    $decimals = $decimals ?? 0;         // y-axis decimals
    $yMax = $yMax ?? null;              // e.g. 5 for rating
    $curve = $curve ?? 'smooth';        // 'smooth' | 'straight' | 'stepline'
    $markers = isset($markers) ? (bool)$markers : false;
@endphp

<div id="{{ $chartId }}"></div>

@push('scripts')
    <script>
        (function(){
            var el = document.querySelector('#{{ $chartId }}');
            if(!el || !window.ApexCharts) return;

            var options = {
                chart: { type: '{{ $type }}', height: {{ $height }}, sparkline: { enabled: true } },
                series: [{ name: @json($label), data: @json(array_values($data)) }],
                xaxis: { categories: @json(array_values($categories)) },
                yaxis: {
                    decimalsInFloat: {{ $decimals }},
                    {{ $yMax !== null ? "max: ".(float)$yMax."," : "" }}
                    min: 0
                },
                dataLabels: { enabled: false },
                stroke: { curve: '{{ $curve }}', width: 2 },
                markers: { size: {{ $markers ? 3 : 0 }} },
                tooltip: { x: { show: true } }
            };

            var chart = new ApexCharts(el, options);
            chart.render();
        })();
    </script>
@endpush
