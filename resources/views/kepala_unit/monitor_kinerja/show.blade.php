<x-app-layout title="Detail Kinerja">
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-800">Detail Kinerja Pegawai</h1>
                <div class="text-sm text-slate-600">Periode: <span class="font-medium">{{ $period->name }}</span></div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('kepala_unit.monitor_kinerja.index', ['period_id' => $period->id]) }}"
                    class="inline-flex items-center gap-2 h-10 px-4 rounded-xl text-sm font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50">
                    <i class="fa-solid fa-arrow-left"></i> Kembali
                </a>
            </div>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <div class="grid gap-4 md:grid-cols-12">
                <div class="md:col-span-8">
                    <div class="text-lg font-semibold text-slate-800">{{ $target->name }}</div>
                    <div class="text-sm text-slate-600 mt-1">
                        <span class="mr-3">NIP: <span class="font-medium">{{ $target->employee_number ?? '-' }}</span></span>
                        <span class="mr-3">Profesi: <span class="font-medium">{{ $target->profession?->name ?? '-' }}</span></span>
                        <span>Unit: <span class="font-medium">{{ $target->unit?->name ?? '-' }}</span></span>
                    </div>

                    <div class="mt-3 text-sm text-slate-600">
                        <span class="font-medium">Mode:</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $mode === 'snapshot' ? 'bg-indigo-50 text-indigo-700' : 'bg-emerald-50 text-emerald-700' }}">
                            {{ $mode === 'snapshot' ? 'Snapshot' : 'Live' }}
                        </span>
                        <span class="ml-2 text-xs text-slate-500">Sumber: {{ $detail['calculation_source'] ?? '-' }}</span>
                        @if(!empty($detail['snapshotted_at']))
                            <span class="ml-2 text-xs text-slate-500">Snapshot at: {{ \Illuminate\Support\Carbon::parse($detail['snapshotted_at'])->format('Y-m-d H:i') }}</span>
                        @endif
                    </div>
                </div>

                <div class="md:col-span-4">
                    @php($vs = (string)($detail['assessment']?->validation_status?->value ?? $detail['assessment']?->validation_status ?? ''))
                    <div class="text-sm text-slate-600">Status Validasi</div>
                    <div class="mt-1">
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">
                            {{ $vs ?: '—' }}
                        </span>
                    </div>

                    @if(!empty($detail['latest_approval']))
                        <div class="mt-3 text-xs text-slate-600">
                            <div class="font-semibold text-slate-700">Approval Terakhir</div>
                            <div>Level: {{ $detail['latest_approval']['level'] ?? '-' }}</div>
                            <div>Status: {{ $detail['latest_approval']['status'] ?? '-' }}</div>
                            <div>Oleh: {{ $detail['latest_approval']['approver_name'] ?? '-' }}</div>
                            @if(!empty($detail['latest_approval']['acted_at']))
                                <div>Waktu: {{ \Illuminate\Support\Carbon::parse($detail['latest_approval']['acted_at'])->format('Y-m-d H:i') }}</div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-12">
            <div class="md:col-span-6 bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
                <div class="text-sm text-slate-600">Skor Akhir Kinerja</div>
                <div class="mt-1 text-3xl font-semibold text-slate-800">
                    @if($detail['total_wsm_relative'] === null)
                        <span class="text-slate-400">-</span>
                    @else
                        {{ number_format((float)$detail['total_wsm_relative'], 2) }}
                    @endif
                </div>
                <div class="mt-1 text-xs text-slate-500">Ringkasan skor kinerja untuk perankingan.</div>
            </div>

            <div class="md:col-span-6 bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
                <div class="text-sm text-slate-600">Nilai Kinerja (Normalisasi)</div>
                <div class="mt-1 text-3xl font-semibold text-slate-800">
                    @if($detail['total_wsm_value'] === null)
                        <span class="text-slate-400">-</span>
                    @else
                        {{ number_format((float)$detail['total_wsm_value'], 2) }}
                    @endif
                </div>
                <div class="mt-1 text-xs text-slate-500">Ditampilkan bila tersedia pada perhitungan kinerja.</div>
            </div>
        </div>

        @if(!empty($detail['snapshot_detail_missing']))
            <div class="p-4 rounded-xl border text-sm bg-amber-50 border-amber-200 text-amber-800">
                <div class="font-semibold">Detail per kriteria tidak tersedia untuk snapshot periode ini.</div>
                <div class="mt-1">Sistem tetap menampilkan ringkasan skor yang tersedia.</div>
            </div>
        @endif

        <x-ui.table minWidth="1100px">
            <x-slot name="head">
                <tr>
                    <th class="px-4 py-3 text-left">Kriteria</th>
                    <th class="px-4 py-3 text-left">Tipe</th>
                    <th class="px-4 py-3 text-right">Nilai Mentah</th>
                    <th class="px-4 py-3 text-right">Nilai Normalisasi</th>
                    <th class="px-4 py-3 text-right">Bobot</th>
                    <th class="px-4 py-3 text-right">Skor Kriteria</th>
                    <th class="px-4 py-3 text-left">Status</th>
                </tr>
            </x-slot>

            @forelse(($detail['criteria'] ?? []) as $c)
                @php($included = (bool)($c['included_in_wsm'] ?? false))
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <div class="font-medium text-slate-800">{{ $c['criteria_name'] ?? ('Kriteria #' . ($c['criteria_id'] ?? '-')) }}</div>
                        <div class="text-xs text-slate-500">Basis: {{ $c['normalization_basis'] ?? '-' }}</div>
                    </td>
                    <td class="px-4 py-3">
                        @php($type = strtoupper((string)($c['type'] ?? '-')))
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">{{ $type }}</span>
                    </td>
                    <td class="px-4 py-3 text-right">{{ number_format((float)($c['raw'] ?? 0), 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format((float)($c['nilai_normalisasi'] ?? 0), 6) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format((float)($c['weight'] ?? 0), 2) }}</td>
                    <td class="px-4 py-3 text-right font-semibold text-slate-800">
                        {{ number_format((float)($c['skor_tertimbang'] ?? 0), 2) }}
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $included ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                            {{ $included ? 'Dihitung' : 'Tidak dihitung' }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-4 py-10 text-center text-slate-500">Tidak ada detail kriteria.</td>
                </tr>
            @endforelse
        </x-ui.table>

        <div class="text-xs text-slate-500">
            Catatan: Halaman ini bersifat read-only dan tidak memuat data remunerasi.
        </div>
    </div>
</x-app-layout>
