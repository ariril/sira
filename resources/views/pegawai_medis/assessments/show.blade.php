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
                <div class="text-sm text-slate-500" title="WSM (Weighted Sum Method)">Skor Kinerja</div>
                @if(($kinerja['applicable'] ?? false) && ($kinerja['hasWeights'] ?? false))
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-lg font-semibold">{{ number_format((float)($kinerja['total'] ?? 0), 2) }}</div>
                        <button
                            type="button"
                            class="px-3 py-2 rounded-lg border text-sm hover:bg-slate-50"
                            x-data
                            @click="$dispatch('open-modal', 'kinerja-breakdown')"
                        >
                            Detail
                        </button>
                    </div>
                @else
                    <div class="text-lg font-semibold">-</div>
                    @if(($kinerja['applicable'] ?? false) && !($kinerja['hasWeights'] ?? false))
                        <div class="text-xs text-amber-700 mt-1">Bobot kriteria periode aktif belum berstatus aktif, skor kinerja belum dapat ditampilkan.</div>
                    @endif
                @endif
            </div>
        </div>

        @if(($kinerja['applicable'] ?? false) && ($kinerja['hasWeights'] ?? false))
            <div class="flex justify-end">
                <button
                    type="button"
                    class="px-3 py-2 rounded-lg border text-sm hover:bg-slate-50"
                    x-data
                    @click="$dispatch('open-modal', 'kinerja-breakdown')"
                >
                    Detail Perhitungan Kinerja
                </button>
            </div>
        @endif

        @if(($kinerja['applicable'] ?? false) && ($kinerja['hasWeights'] ?? false))
            <x-modal name="kinerja-breakdown" maxWidth="lg">
                <div class="p-6 space-y-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-xs uppercase tracking-wide text-slate-500">Detail Perhitungan</div>
                            <div class="text-lg font-semibold text-slate-900">Kinerja (Bobot Aktif Unit)</div>
                            <div class="text-sm text-slate-600">Total: {{ number_format((float)($kinerja['total'] ?? 0), 2) }}</div>
                        </div>
                        <button type="button" class="text-slate-400 hover:text-slate-600" @click="$dispatch('close-modal', 'kinerja-breakdown')">
                            <i class="fa-solid fa-xmark text-lg"></i>
                        </button>
                    </div>

                    <div class="rounded-xl bg-slate-50 border border-slate-200 overflow-hidden">
                        <x-ui.table min-width="760px">
                            <x-slot name="head">
                                <tr>
                                    <th class="text-left px-4 py-3 whitespace-nowrap">Kriteria</th>
                                    <th class="text-right px-4 py-3 whitespace-nowrap">Bobot (%)</th>
                                    <th class="text-right px-4 py-3 whitespace-nowrap" title="WSM (Weighted Sum Method)">Nilai Normalisasi</th>
                                    <th class="text-right px-4 py-3 whitespace-nowrap">Relatif Unit</th>
                                    <th class="text-right px-4 py-3 whitespace-nowrap">Kontribusi</th>
                                </tr>
                            </x-slot>
                            @foreach(($kinerja['rows'] ?? []) as $row)
                                <tr>
                                    <td class="px-4 py-3">{{ $row['criteria_name'] ?? '-' }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format((float)($row['weight'] ?? 0), 2) }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format((float)($row['score_wsm'] ?? 0), 2) }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format((float)($row['score_relative_unit'] ?? 0), 2) }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format((float)($row['contribution'] ?? 0), 2) }}</td>
                                </tr>
                            @endforeach
                        </x-ui.table>
                    </div>

                    <div class="text-xs text-slate-500">
                        Hanya kriteria dengan bobot <span class="font-semibold">aktif</span> pada periode aktif yang dihitung sebagai Kinerja.
                    </div>
                </div>
            </x-modal>
        @endif

        <x-section title="Detail Kriteria" class="overflow-hidden">
            <x-ui.table min-width="760px">
                <x-slot name="head">
                    <tr>
                        <th class="text-left px-4 py-3 whitespace-nowrap">Kriteria</th>
                        <th class="text-left px-4 py-3 whitespace-nowrap">Tipe</th>
                        <th class="text-left px-4 py-3 whitespace-nowrap">Nilai Kinerja (Relatif Unit)</th>
                        <th class="text-left px-4 py-3 whitespace-nowrap">Detail</th>
                    </tr>
                </x-slot>
                @forelse($visibleDetails as $d)
                    @php
                        $raw = $rawMetrics[$d->performance_criteria_id] ?? null;
                        $rel = $kinerja['relativeByCriteria'][(int)$d->performance_criteria_id] ?? null;
                    @endphp
                    <tr>
                        <td class="px-4 py-3">{{ $d->performanceCriteria->name ?? '-' }}</td>
                        <td class="px-4 py-3">{{ optional($d->performanceCriteria->type)->value }}</td>
                        <td class="px-4 py-3">
                            {{ $rel !== null ? number_format((float)$rel, 2) : '-' }}
                        </td>
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
                                                <div class="text-sm text-slate-600">
                                                    Nilai Kinerja (Relatif Unit): {{ $rel !== null ? number_format((float)$rel, 2) : '-' }}
                                                </div>
                                                <div class="text-sm text-slate-600" title="WSM (Weighted Sum Method)">
                                                    Nilai Normalisasi: {{ number_format((float)($d->score ?? 0), 2) }}
                                                </div>
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
                                            @if(!empty($raw['formula']['raw']) && !empty($raw['formula']['result']) && !empty($raw['formula']['denominator']))
                                                <div class="pt-3 mt-2 border-t border-slate-200">
                                                    <div class="text-xs uppercase tracking-wide text-slate-500 mb-1" title="WSM (Weighted Sum Method)">Rumus Normalisasi</div>
                                                    <div class="text-sm text-slate-800 font-semibold">
                                                        {{ number_format($raw['formula']['raw'], 2) }} / {{ number_format($raw['formula']['denominator'], 2) }} × 100 = {{ number_format($raw['formula']['result'], 2) }}
                                                    </div>
                                                    <div class="text-xs text-slate-500">Basis normalisasi: {{ $d->performanceCriteria->normalization_basis ?? '-' }}. Pembagi mengikuti kebijakan basis tersebut (contoh: total unit pada periode yang sama).</div>
                                                </div>
                                            @endif
                                        </div>

                                        <div class="flex items-center gap-2 text-xs text-slate-500">
                                            <i class="fa-solid fa-circle-info"></i>
                                            <span>Nilai normalisasi berasal dari normalisasi data mentah. Nilai Kinerja (Relatif Unit) membandingkan nilai Anda terhadap skor tertinggi di unit pada periode yang sama.</span>
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
