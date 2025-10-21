<x-app-layout title="Dashboard Super Admin">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold">Dashboard Super Admin</h1>
    </x-slot>

    <div class="container-px py-6 space-y-6">

        {{-- STAT CARDS --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-stat-card label="Total User" value="{{ $stats['total_user'] }}" icon="fa-users"
                         accent="from-sky-500 to-indigo-600"/>
            <x-stat-card label="Unit Kerja" value="{{ $stats['total_unit'] }}" icon="fa-diagram-project"
                         accent="from-sky-500 to-indigo-600"/>
            <x-stat-card label="Profesi" value="{{ $stats['total_profesi'] }}" icon="fa-user-doctor"
                         accent="from-sky-500 to-indigo-600"/>
            <x-stat-card label="Email Belum Verifikasi" value="{{ $stats['unverified'] }}"
                         icon="fa-envelope-circle-check" accent="from-sky-500 to-indigo-600"/>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Progres Bobot & Cakupan Penilaian --}}
            <x-section title="Progres Periode Aktif">
                @if($kinerja['periode_aktif'])
                    <dl class="grid grid-cols-2 gap-4">
                        <div>
                            <dt class="text-xs text-slate-500">Bobot Kriteria Aktif</dt>
                            <dd class="text-lg font-semibold">
                                {{ $kinerja['weights_active'] }} / {{ $kinerja['weights_total'] }}
                                <span class="text-xs text-slate-500">({{ $kinerja['weights_pct'] }}%)</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Cakupan Penilaian</dt>
                            <dd class="text-lg font-semibold">
                                {{ $kinerja['coverage_submitted'] }} / {{ $kinerja['coverage_expected'] }}
                                <span class="text-xs text-slate-500">({{ $kinerja['coverage_pct'] }}%)</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Approval Pending (Lv.1)</dt>
                            <dd class="text-lg font-semibold">{{ $kinerja['pending_l1'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Approval Pending (Lv.2/Lv.3)</dt>
                            <dd class="text-lg font-semibold">{{ $kinerja['pending_l2'] }}
                                / {{ $kinerja['pending_l3'] }}</dd>
                        </div>
                    </dl>
                @else
                    <p class="text-sm text-slate-500">Belum ada periode aktif.</p>
                @endif
            </x-section>

            {{-- Import Absensi Terakhir --}}
            <x-section title="Import Absensi Terakhir">
                @if($kinerja['last_batch']['at'])
                    <dl class="grid grid-cols-2 gap-4">
                        <div>
                            <dt class="text-xs text-slate-500">Tanggal</dt>
                            <dd class="text-lg font-semibold">
                                {{ \Illuminate\Support\Carbon::parse($kinerja['last_batch']['at'])->translatedFormat('d M Y H:i') }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Success Rate</dt>
                            <dd class="text-lg font-semibold">
                                {{ $kinerja['last_batch']['success_rate'] ?? 0 }}%
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Baris</dt>
                            <dd class="text-lg font-semibold">
                                {{ $kinerja['last_batch']['success'] }} / {{ $kinerja['last_batch']['total'] }}
                                <span
                                    class="text-xs text-slate-500">({{ $kinerja['last_batch']['failed'] }} gagal)</span>
                            </dd>
                        </div>
                    </dl>
                @else
                    <p class="text-sm text-slate-500">Belum ada proses import.</p>
                @endif
            </x-section>

            {{-- Ringkasan Remunerasi Periode Aktif --}}
            <x-section title="Remunerasi (Periode Aktif)">
                @if($kinerja['periode_aktif'])
                    <dl class="grid grid-cols-2 gap-4">
                        <div>
                            <dt class="text-xs text-slate-500">Total Nominal</dt>
                            <dd class="text-lg font-semibold">
                                {{ number_format($kinerja['remunerasi']['total_nominal'] ?? 0, 0, ',', '.') }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Dipublish</dt>
                            <dd class="text-lg font-semibold">
                                {{ $kinerja['remunerasi']['published'] }} / {{ $kinerja['remunerasi']['count'] }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Status</dt>
                            <dd class="text-sm font-medium">
                                Belum Dibayar: {{ $kinerja['remunerasi']['by_status']['Belum Dibayar'] ?? 0 }} •
                                Dibayar: {{ $kinerja['remunerasi']['by_status']['Dibayar'] ?? 0 }} •
                                Ditahan: {{ $kinerja['remunerasi']['by_status']['Ditahan'] ?? 0 }}
                            </dd>
                        </div>
                    </dl>
                @else
                    <p class="text-sm text-slate-500">Belum ada periode aktif.</p>
                @endif
            </x-section>
        </div>

        {{-- (Opsional) Tetap tampilkan mutu di bawah --}}
        <x-section title="Mutu Pelayanan (Opsional)">
            <p class="text-4xl font-bold text-slate-800">
                {{ number_format($review['avg_rating_30d'] ?? 0, 2) }}
                <span class="text-sm text-slate-500">({{ $review['total_30d'] }} ulasan)</span>
            </p>
            <div class="mt-4 divide-y">
                @forelse($review['top_tenaga_medis'] as $row)
                    <div class="flex items-center justify-between py-2">
                        <div>
                            <div class="font-medium">{{ $row->nama }}</div>
                            <div class="text-xs text-slate-500">{{ $row->jabatan }}</div>
                        </div>
                        <div class="text-right">
                            <div class="font-semibold">{{ number_format($row->avg_rating, 2) }}</div>
                            <div class="text-xs text-slate-500">{{ $row->total_ulasan }} data</div>
                        </div>
                    </div>
                @empty
                    <div class="text-sm text-slate-500">Belum ada data.</div>
                @endforelse
            </div>
        </x-section>
    </div>
</x-app-layout>
