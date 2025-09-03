<x-app-layout title="Dashboard Kepala Unit">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold">Dashboard Kepala Unit</h1>
    </x-slot>

    <div class="container-px py-6">
        {{-- KPI cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-card metric="{{ $stats['total_pegawai'] ?? 0 }}" label="Total Pegawai" />
            <x-card metric="{{ $stats['total_dokter'] ?? 0 }}"  label="Dokter" />
            <x-card metric="{{ $stats['total_admin'] ?? 0 }}"   label="Staf Administrasi" />
            <x-card metric="{{ number_format($review['avg_rating_unit_30d'] ?? 0, 2) }}" label="Avg Rating (30 hari)" />
        </div>

        {{-- Charts & lists --}}
        <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="col-span-1 p-4 rounded-xl border">
                <h2 class="font-semibold mb-3">Avg Rating Unit (Target 5)</h2>
                <div id="kuAvgRatingGauge"></div>
                <p class="text-xs text-gray-500 mt-2">
                    Total ulasan 30 hari: {{ $review['total_ulasan_unit_30d'] ?? 0 }}
                </p>
            </div>

            <div class="col-span-1 lg:col-span-2 p-4 rounded-xl border">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-semibold">Top Staff (berdasarkan rating)</h2>
                    <span class="text-xs text-gray-500">Min. 3 ulasan</span>
                </div>
                <div id="kuTopStaffBar"></div>
                @if(($review['top_staff'] ?? collect())->isEmpty())
                    <p class="text-sm text-gray-500 mt-2">Belum ada data.</p>
                @endif
            </div>
        </div>

        <div class="mt-6 p-4 rounded-xl border">
            <h2 class="font-semibold mb-3">Komentar Terbaru</h2>
            <div class="space-y-3">
                @forelse(($review['recent_comments'] ?? collect()) as $c)
                    <div class="border-b pb-2">
                        <div class="flex justify-between">
                            <div class="font-medium">{{ $c->nama }}</div>
                            <div class="text-sm">⭐ {{ $c->rating }}</div>
                        </div>
                        <p class="text-sm text-gray-700">{{ $c->komentar }}</p>
                        <div class="text-xs text-gray-500">
                            {{ \Illuminate\Support\Carbon::parse($c->created_at)->diffForHumans() }}
                        </div>
                    </div>
                @empty
                    <div class="text-sm text-gray-500">Belum ada komentar.</div>
                @endforelse
            </div>
        </div>
    </div>

    @push('scripts')
        @once
            <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
        @endonce

        <script>
            (() => {
                const avg = Number(@json($review['avg_rating_unit_30d'] ?? 0)) || 0;
                const el = document.querySelector('#kuAvgRatingGauge');
                if(!el) return;
                new ApexCharts(el, {
                    chart: { type: 'radialBar', height: 220 },
                    series: [ Math.max(0, Math.min(100, (avg/5)*100)) ],
                    labels: ['Avg Rating'],
                    plotOptions: {
                        radialBar: {
                            hollow: { size: '58%' },
                            dataLabels: {
                                value: { formatter: (val) => (val/20).toFixed(2) + ' / 5' }
                            }
                        }
                    }
                }).render();
            })();
        </script>

        <script>
            (() => {
                const rows = @json(($review['top_staff'] ?? collect())->values());
                if(!rows.length) return;
                const labels = rows.map(r => r.nama);
                const data   = rows.map(r => Number(r.avg_rating));
                new ApexCharts(document.querySelector('#kuTopStaffBar'), {
                    chart: { type: 'bar', height: 260 },
                    plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
                    series: [{ name: 'Avg Rating', data }],
                    xaxis: { categories: labels, max: 5 },
                    yaxis: { decimalsInFloat: 1 },
                    tooltip: { y: { formatter: (v) => v.toFixed(2) } }
                }).render();
            })();
        </script>
    @endpush
</x-app-layout>
