<x-app-layout title="Dashboard Pegawai Medis">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-800">Dashboard Pegawai Medis</h1>
    </x-slot>
    <div class="container-px py-6">
        @if (! $activePeriod)
            <div class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800">
                Tidak ada periode aktif. Hubungi Admin RS untuk mengaktifkan periode penilaian terlebih dahulu.
            </div>
        @endif

        @if(!empty($approvalBanner) || !empty($rejectedClaim) || !empty($criteriaNotice))
            <div class="mb-4 space-y-2" aria-live="polite">
                @if(!empty($approvalBanner))
                    <div class="rounded-lg px-4 py-3 text-sm bg-emerald-50 text-emerald-800 flex items-center justify-between gap-3">
                        <span>Penilaian periode {{ $approvalBanner['period_name'] }} telah disetujui.</span>
                        <a href="{{ $approvalBanner['ack_url'] }}" class="underline font-medium text-emerald-800">Lihat</a>
                    </div>
                @endif

                @if(!empty($rejectedClaim))
                    <div class="rounded-lg px-4 py-3 text-sm bg-rose-50 text-rose-800 flex items-center justify-between gap-3">
                        <span>Klaim tambahan {{ $rejectedClaim->title ?? 'tugas' }} ditolak @if($rejectedClaim->period_name) ({{ $rejectedClaim->period_name }}) @endif.</span>
                        <a href="{{ $rejectedClaim->ack_url }}" class="underline font-medium text-rose-800">Lihat</a>
                    </div>
                @endif

                @if(!empty($criteriaNotice))
                    <div class="rounded-lg px-4 py-3 text-sm bg-amber-50 text-amber-800 flex items-center justify-between gap-3">
                        <span>Kepala Unit mengubah bobot kriteria @if($criteriaNotice->period_name) ({{ $criteriaNotice->period_name }}) @endif.</span>
                        <a href="{{ $criteriaNotice->ack_url }}" class="underline font-medium text-amber-800">Lihat</a>
                    </div>
                @endif
            </div>
        @endif

        {{-- KPI cards (samakan gaya dengan Admin RS / Super Admin) --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-stat-card label="Rerata Rating (30 hari)" value="{{ ($me['avg_rating_30d'] ?? null) === null ? '—' : number_format((float) $me['avg_rating_30d'], 2) }}" icon="fa-star" accent="from-cyan-500 to-sky-600" />
            <x-stat-card label="Total Review (30 hari)" value="{{ $me['total_review_30d'] ?? 0 }}" icon="fa-comments" accent="from-cyan-500 to-sky-600" />
            <x-stat-card label="Remunerasi Terakhir" value="{{ optional($me['remunerasi_terakhir'])->amount !== null ? ('Rp ' . number_format((float) optional($me['remunerasi_terakhir'])->amount, 0, ',', '.')) : '-' }}" icon="fa-wallet" accent="from-cyan-500 to-sky-600" />
            <x-stat-card label="Skor Kinerja Terakhir" value="{{ ($me['nilai_kinerja_terakhir'] ?? null) === null ? '-' : number_format((float) $me['nilai_kinerja_terakhir'], 2, ',', '.') }}" icon="fa-gauge" accent="from-cyan-500 to-sky-600" />
        </div>
    </div>
</x-app-layout>
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
