<x-app-layout title="Dashboard Super Admin">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold">Dashboard Super Admin</h1>
    </x-slot>

    <div class="container-px py-6 space-y-6">

        {{-- STAT CARDS --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-stat-card label="Total User" value="{{ $stats['total_user'] }}" icon="fa-users" accent="from-sky-500 to-indigo-600"/>
            <x-stat-card label="Unit Kerja" value="{{ $stats['total_unit'] }}" icon="fa-diagram-project" accent="from-sky-500 to-indigo-600"/>
            <x-stat-card label="Profesi" value="{{ $stats['total_profesi'] }}" icon="fa-user-doctor" accent="from-sky-500 to-indigo-600"/>
            <x-stat-card label="Email Belum Verifikasi" value="{{ $stats['unverified'] }}" icon="fa-envelope-circle-check" accent="from-sky-500 to-indigo-600"/>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <x-section title="Rating 30 Hari">
                <p class="text-4xl font-bold text-slate-800">
                    {{ number_format($review['avg_rating_30d'] ?? 0, 2) }}
                    <span class="text-sm text-slate-500">({{ $review['total_30d'] }} ulasan)</span>
                </p>
            </x-section>

            <x-section title="Top Tenaga Medis" class="lg:col-span-2">
                <div class="divide-y">
                    @forelse($review['top_tenaga_medis'] as $row)
                        <div class="flex items-center justify-between py-2">
                            <div>
                                <div class="font-medium">{{ $row->nama }}</div>
                                <div class="text-xs text-slate-500">{{ $row->jabatan }}</div>
                            </div>
                            <div class="text-right">
                                <div class="font-semibold">{{ number_format($row->avg_rating, 2) }}</div>
                                <div class="text-xs text-slate-500">{{ $row->total_ulasan }} ulasan</div>
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-slate-500">Belum ada data ulasan.</div>
                    @endforelse
                </div>
            </x-section>
        </div>

        <x-section title="Periode & Remunerasi">
            @if($kinerja['periode_aktif'])
                <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <dt class="text-xs text-slate-500">Periode aktif</dt>
                        <dd class="text-lg font-semibold">#{{ $kinerja['periode_aktif']->id }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Total remunerasi periode</dt>
                        <dd class="text-lg font-semibold">{{ number_format($kinerja['total_remunerasi_periode'] ?? 0, 0, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Penilaian pending</dt>
                        <dd class="text-lg font-semibold">{{ $kinerja['penilaian_pending'] }}</dd>
                    </div>
                </dl>
            @else
                <p class="text-sm text-slate-500">Belum ada periode aktif.</p>
            @endif
        </x-section>

    </div>
</x-app-layout>
