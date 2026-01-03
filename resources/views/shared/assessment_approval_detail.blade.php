<x-app-layout title="Detail Penilaian">
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">Detail Penilaian</h1>
                <p class="text-slate-600 text-sm">Ringkasan skor, data pendukung, dan riwayat approval</p>
            </div>
            <a href="{{ $backUrl }}"
               class="inline-flex items-center gap-2 h-10 px-4 rounded-xl text-sm font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50">
                <i class="fa-solid fa-arrow-left"></i>
                Kembali
            </a>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        {{-- Summary --}}
        <div class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            @php
                $computedTotal = ($breakdown['hasWeights'] ?? false) ? ($breakdown['total'] ?? null) : null;
            @endphp
            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <div class="text-xs text-slate-500">Pegawai</div>
                    <div class="text-lg font-semibold text-slate-900">{{ $pa->user?->name ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-xs text-slate-500">Periode</div>
                    <div class="text-lg font-semibold text-slate-900">{{ $pa->assessmentPeriod?->name ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-xs text-slate-500">Skor Kinerja (bobot periode)</div>
                    <div class="text-lg font-semibold text-slate-900">
                        {{ $computedTotal === null ? '-' : number_format((float) $computedTotal, 2) }}
                    </div>

                    <div class="mt-2 text-xs text-slate-500">Total tersimpan</div>
                    <div class="text-lg font-semibold text-slate-900">{{ $pa->total_wsm_score ?? '-' }}</div>
                </div>
            </div>
        </div>

        {{-- Breakdown --}}
        <div class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h2 class="text-lg font-semibold">Breakdown Perhitungan</h2>
                @if(($breakdown['hasWeights'] ?? false) && isset($breakdown['total']))
                    <div class="text-sm text-slate-600">Total (berdasarkan bobot periode): <span class="font-semibold text-slate-900">{{ number_format((float) $breakdown['total'], 2) }}</span></div>
                @endif
            </div>

            @if(!($breakdown['hasWeights'] ?? false))
                <div class="text-slate-600 text-sm">Bobot aktif untuk unit+periode tidak ditemukan, sehingga breakdown tidak dapat dihitung.</div>
            @else
                <x-ui.table min-width="900px">
                    <x-slot name="head">
                        <tr>
                            <th class="px-6 py-4 text-left whitespace-nowrap">Kriteria</th>
                            <th class="px-6 py-4 text-left whitespace-nowrap">Bobot</th>
                                    <th class="px-6 py-4 text-left whitespace-nowrap">Skor Kinerja</th>
                            <th class="px-6 py-4 text-left whitespace-nowrap">Relatif Unit (%)</th>
                            <th class="px-6 py-4 text-left whitespace-nowrap">Kontribusi</th>
                        </tr>
                    </x-slot>

                    @foreach(($breakdown['rows'] ?? []) as $row)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4">{{ $row['criteria_name'] }}</td>
                            <td class="px-6 py-4">{{ number_format((float) $row['weight'], 2) }}</td>
                            <td class="px-6 py-4">{{ number_format((float) $row['score_wsm'], 2) }}</td>
                            <td class="px-6 py-4">{{ number_format((float) $row['score_relative_unit'], 2) }}</td>
                            <td class="px-6 py-4">{{ number_format((float) $row['contribution'], 4) }}</td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @endif
        </div>

        {{-- Raw Supporting Data --}}
        <div class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <h2 class="text-lg font-semibold mb-4">Data Pendukung (Import)</h2>

            <x-ui.table min-width="900px">
                <x-slot name="head">
                    <tr>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Kriteria</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Nilai Numeric</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Nilai Datetime</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Nilai Text</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Batch</th>
                    </tr>
                </x-slot>

                @forelse($rawValues as $rv)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4">{{ $rv->criteria_name ?? ('Kriteria #' . ($rv->performance_criteria_id ?? '-')) }}</td>
                        <td class="px-6 py-4">{{ $rv->value_numeric ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $rv->value_datetime ? \Illuminate\Support\Carbon::parse($rv->value_datetime)->format('d M Y H:i') : '-' }}</td>
                        <td class="px-6 py-4">{{ $rv->value_text ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $rv->batch_file ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-slate-500">Tidak ada data import untuk penilaian ini.</td>
                    </tr>
                @endforelse
            </x-ui.table>
        </div>

        {{-- Approval History --}}
        <div class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h2 class="text-lg font-semibold">Riwayat Approval</h2>

                @if(auth()->user()?->role === 'admin_rs')
                    @php
                        $hasRejected = ($pa->approvals ?? collect())->contains(fn($a) => ($a->status?->value ?? $a->status) === 'rejected');
                    @endphp
                    @if($hasRejected)
                        <form method="POST" action="{{ route('admin_rs.assessments.resubmit', $approval->id) }}"
                              onsubmit="return confirm('Ajukan ulang penilaian ini?')">
                            @csrf
                            <x-ui.button type="submit" variant="warning" class="h-9 px-3 text-xs">Ajukan Ulang</x-ui.button>
                        </form>
                    @endif
                @endif
            </div>

            <x-ui.table min-width="900px">
                <x-slot name="head">
                    <tr>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Level</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Approver</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Status</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Diproses</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Catatan</th>
                    </tr>
                </x-slot>

                @foreach(($pa->approvals ?? collect())->sortBy('level') as $ap)
                    @php($st = $ap->status?->value ?? $ap->status)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4">{{ $ap->level }}</td>
                        <td class="px-6 py-4">{{ $ap->approver?->name ?? '-' }}</td>
                        <td class="px-6 py-4">
                            @if ($st === 'approved')
                                <span class="px-2 py-1 rounded text-xs bg-emerald-100 text-emerald-700">Approved</span>
                            @elseif ($st === 'rejected')
                                <span class="px-2 py-1 rounded text-xs bg-rose-100 text-rose-700">Rejected</span>
                            @else
                                <span class="px-2 py-1 rounded text-xs bg-amber-100 text-amber-700">Pending</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">{{ $ap->acted_at ? $ap->acted_at->format('d M Y H:i') : '-' }}</td>
                        <td class="px-6 py-4">{{ $ap->note ?? '-' }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
        </div>
    </div>
</x-app-layout>
