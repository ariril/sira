<x-app-layout title="Detail Penilaian">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-2xl font-semibold">Detail Penilaian</h1>
            <div class="flex items-center gap-2">
                <a href="{{ route('pegawai_medis.assessments.index') }}" class="px-3 py-2 rounded-lg border">Kembali</a>
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4 mb-4">
            <div class="p-4 rounded-2xl bg-white shadow-sm ring-1 ring-slate-100">
                <div class="text-sm text-slate-500">Periode</div>
                <div class="text-lg font-semibold">{{ $assessment->assessmentPeriod->name ?? '-' }}</div>
            </div>
            <div class="p-4 rounded-2xl bg-white shadow-sm ring-1 ring-slate-100">
                <div class="text-sm text-slate-500">Tanggal</div>
                <div class="text-lg font-semibold">{{ optional($assessment->assessment_date)->format('d M Y') }}</div>
            </div>
            <div class="p-4 rounded-2xl bg-white shadow-sm ring-1 ring-slate-100">
                <div class="text-sm text-slate-500">Status</div>
                <div class="text-lg font-semibold">{{ $assessment->validation_status?->value ?? '-' }}</div>
            </div>
            <div class="p-4 rounded-2xl bg-white shadow-sm ring-1 ring-slate-100">
                <div class="text-sm text-slate-500">Skor WSM</div>
                <div class="text-lg font-semibold">{{ $assessment->total_wsm_score !== null ? number_format($assessment->total_wsm_score, 2) : '-' }}</div>
            </div>
        </div>

        <x-section title="Detail Kriteria" class="overflow-hidden">
            <x-ui.table min-width="760px">
                <x-slot name="head">
                    <tr>
                        <th class="text-left px-4 py-3 whitespace-nowrap">Kriteria</th>
                        <th class="text-left px-4 py-3 whitespace-nowrap">Tipe</th>
                        <th class="text-left px-4 py-3 whitespace-nowrap">Nilai (WSM)</th>
                        <th class="text-left px-4 py-3 whitespace-nowrap">Detail</th>
                    </tr>
                </x-slot>
                @forelse($assessment->details as $d)
                    @php
                        $name = strtolower($d->performanceCriteria->name ?? '');
                        $rawKey = match (true) {
                            str_contains($name, 'absensi') => 'absensi',
                            str_contains($name, 'disiplin') || str_contains($name, 'kedisiplinan') => 'kedisiplinan',
                            str_contains($name, 'kontribusi') => 'kontribusi',
                            str_contains($name, 'pasien') => 'pasien',
                            str_contains($name, 'rating') => 'rating',
                            default => null,
                        };
                        $raw = $rawKey ? ($rawMetrics[$rawKey] ?? null) : null;
                    @endphp
                    <tr>
                        <td class="px-4 py-3">{{ $d->performanceCriteria->name ?? '-' }}</td>
                        <td class="px-4 py-3">{{ optional($d->performanceCriteria->type)->value }}</td>
                        <td class="px-4 py-3">{{ number_format($d->score, 2) }}</td>
                        <td class="px-4 py-3">
                            @if($raw)
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center w-10 h-10 rounded-full border border-slate-200 text-slate-600 hover:border-emerald-500 hover:text-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-200"
                                    x-data
                                    @click="$dispatch('open-modal', 'raw-{{ $d->id }}')"
                                    title="Lihat data mentah"
                                >
                                    <i class="fa-solid fa-eye"></i>
                                </button>

                                <x-modal name="raw-{{ $d->id }}" maxWidth="lg">
                                    <div class="p-6 space-y-4">
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <div class="text-xs uppercase tracking-wide text-slate-500">Kriteria</div>
                                                <div class="text-lg font-semibold text-slate-900">{{ $d->performanceCriteria->name ?? '-' }}</div>
                                                <div class="text-sm text-slate-600">Nilai WSM: {{ number_format($d->score, 2) }}</div>
                                            </div>
                                            <button type="button" class="text-slate-400 hover:text-slate-600" @click="$dispatch('close-modal', 'raw-{{ $d->id }}')">
                                                <i class="fa-solid fa-xmark text-lg"></i>
                                            </button>
                                        </div>

                                        <div class="rounded-xl bg-slate-50 border border-slate-200 p-4 space-y-3">
                                            <div class="text-sm font-semibold text-slate-800">{{ $raw['title'] ?? 'Data mentah' }}</div>
                                            @foreach(($raw['lines'] ?? []) as $line)
                                                <div class="flex items-start justify-between gap-3">
                                                    <div>
                                                        <div class="text-sm text-slate-700 font-medium">{{ $line['label'] ?? '-' }}</div>
                                                        @if(!empty($line['hint']))
                                                            <div class="text-xs text-slate-500">{{ $line['hint'] }}</div>
                                                        @endif
                                                    </div>
                                                    <div class="text-sm font-semibold text-slate-900 text-right">{{ $line['value'] ?? '-' }}</div>
                                                </div>
                                            @endforeach
                                        </div>

                                        <div class="flex items-center gap-2 text-xs text-slate-500">
                                            <i class="fa-solid fa-circle-info"></i>
                                            <span>Nilai WSM berasal dari normalisasi data mentah sesuai bobot kriteria periode ini.</span>
                                        </div>
                                    </div>
                                </x-modal>
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-slate-500">Belum ada detail penilaian.</td>
                    </tr>
                @endforelse
            </x-ui.table>
        </x-section>
    </div>
</x-app-layout>
