@extends('layouts.app')

@section('content')
    <div class="container-px py-6">
        <h1 class="text-2xl font-semibold mb-4">Dashboard Pegawai Medis</h1>

        {{-- KPI cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-card metric="{{ number_format($me['avg_rating_30d'] ?? 0, 2) }}" label="Avg Rating (30 hari)" />
            <x-card metric="{{ $me['total_review_30d'] ?? 0 }}" label="Total Review (30 hari)" />
            <x-card metric="{{ optional($me['remunerasi_terakhir'])->total ?? '-' }}" label="Remunerasi Terakhir" />
            <x-card metric="{{ $me['nilai_kinerja_terakhir'] ?? '-' }}" label="Skor Kinerja Terakhir" />
        </div>

        @php
            $rows = ($me['recent_reviews'] ?? collect())->values();
            $labels = $rows->map(fn($r) => \Illuminate\Support\Carbon::parse($r->created_at)->format('d M'))->toArray();
            $series = $rows->map(fn($r) => (int)$r->rating)->toArray();
        @endphp

        <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Ganti canvas Chart.js dengan komponen sparkline (ApexCharts) --}}
            <div class="col-span-1 lg:col-span-2 p-4 rounded-xl border">
                <h2 class="font-semibold mb-3">Tren Rating (Ulasan Terakhir)</h2>
                <x-sparkline
                    type="area"
                    :data="$series"
                    :categories="$labels"
                    label="Rating"
                    :y-max="5"
                    :height="180"
                    curve="smooth"
                    :markers="true"
                />
                @if(empty($series))
                    <p class="text-sm text-gray-500 mt-2">Belum ada data ulasan.</p>
                @endif
            </div>

            <div class="col-span-1 p-4 rounded-xl border">
                <h2 class="font-semibold mb-3">Jadwal Mendatang</h2>
                <div class="space-y-2">
                    @forelse($me['jadwal_mendatang'] ?? [] as $j)
                        <div class="border rounded-lg p-2">
                            <div class="font-medium">{{ \Illuminate\Support\Carbon::parse($j->tanggal)->format('d M Y') }}</div>
                            <div class="text-sm text-gray-600">
                                {{ ($j->jam_mulai ?? '') . (isset($j->jam_selesai)?' - '.$j->jam_selesai:'') }}
                                <span class="ml-2">{{ $j->ruangan ?? '' }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500">Belum ada jadwal.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="mt-6 p-4 rounded-xl border">
            <h2 class="font-semibold mb-3">Ulasan Terbaru</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @forelse($me['recent_reviews'] ?? [] as $r)
                    <div class="border rounded-lg p-3">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-500">{{ \Illuminate\Support\Carbon::parse($r->created_at)->format('d M Y H:i') }}</div>
                            <div class="text-sm font-semibold">⭐ {{ $r->rating }}</div>
                        </div>
                        <p class="text-sm mt-1">{{ $r->komentar ?? '—' }}</p>
                    </div>
                @empty
                    <div class="text-sm text-gray-500">Belum ada komentar.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
{{--@push('scripts')--}}
{{--    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>--}}
{{--    <script>--}}
{{--        // Line chart tren rating dari recent_reviews--}}
{{--        (() => {--}}
{{--            const rows = @json(($me['recent_reviews'] ?? collect())->values());--}}
{{--            const labels = rows.map(r => new Date(r.created_at).toLocaleDateString());--}}
{{--            const data   = rows.map(r => Number(r.rating));--}}
{{--            const ctx = document.getElementById('pmTrendChart');--}}
{{--            if (ctx && rows.length) new Chart(ctx, {--}}
{{--                type: 'line',--}}
{{--                data: { labels, datasets: [{ label: 'Rating', data, tension: .3, fill: false }] },--}}
{{--                options: {--}}
{{--                    scales: { y: { beginAtZero: true, max: 5 } },--}}
{{--                    plugins: { legend: { display: false } }--}}
{{--                }--}}
{{--            });--}}
{{--        })();--}}
{{--    </script>--}}
{{--@endpush--}}
