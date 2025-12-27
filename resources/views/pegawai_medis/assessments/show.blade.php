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
                            DETAIL
                        </button>
                    </div>
                @else
                    <div class="text-lg font-semibold">-</div>
                    @if(($kinerja['applicable'] ?? false) && !($kinerja['hasWeights'] ?? false))
                        <div class="text-xs text-amber-700 mt-1">Bobot kriteria pada periode ini belum berstatus aktif, skor kinerja belum dapat ditampilkan.</div>
                    @endif
                @endif
            </div>
        </div>

        @if(($kinerja['applicable'] ?? false) && ($kinerja['hasWeights'] ?? false))
            <x-modal name="kinerja-breakdown" maxWidth="lg">
                <div class="p-6 space-y-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-xs uppercase tracking-wide text-slate-500">Detail Perhitungan</div>
                            <div class="text-lg font-semibold text-slate-900">DETAIL Skor Kinerja</div>
                            <div class="text-sm text-slate-600">Total: {{ number_format((float)($kinerja['total'] ?? 0), 2) }}</div>
                        </div>
                        <button type="button" class="text-slate-400 hover:text-slate-600" @click="$dispatch('close-modal', 'kinerja-breakdown')">
                            <i class="fa-solid fa-xmark text-lg"></i>
                        </button>
                    </div>

                    <div class="rounded-xl bg-slate-50 border border-slate-200 p-4 space-y-4">
                        <div class="text-sm text-slate-700">
                            Skor Kinerja hanya dihitung dari <span class="font-semibold">kriteria aktif</span> pada periode penilaian dan digunakan sebagai dasar pembagian remunerasi.
                        </div>

                        <div>
                            <div class="text-xs uppercase tracking-wide text-slate-500 mb-2">Kriteria dihitung (Aktif pada Periode)</div>
                            <div class="overflow-auto rounded-xl border border-slate-200 bg-white">
                                <table class="min-w-[560px] w-full text-sm">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left">Kriteria</th>
                                            <th class="px-4 py-2 text-right">Bobot</th>
                                            <th class="px-4 py-2 text-right">Nilai Normalisasi</th>
                                            <th class="px-4 py-2 text-right" title="(bobot/Σbobot)×nilai">Kontribusi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse(($kinerja['rows'] ?? []) as $r)
                                            <tr class="border-t border-slate-100">
                                                <td class="px-4 py-2 font-medium text-slate-800">{{ $r['criteria_name'] ?? '-' }}</td>
                                                <td class="px-4 py-2 text-right">{{ number_format((float)($r['weight'] ?? 0), 2) }}</td>
                                                <td class="px-4 py-2 text-right">{{ number_format((float)($r['score_wsm'] ?? 0), 2) }}</td>
                                                <td class="px-4 py-2 text-right">{{ number_format((float)($r['contribution'] ?? 0), 2) }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="px-4 py-3 text-slate-500">Tidak ada.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-xs text-slate-500 mt-2">Total WSM = Σ(bobot×nilai) / Σ(bobot) (hanya kriteria aktif).</div>
                        </div>

                        <div>
                            <div class="text-xs uppercase tracking-wide text-slate-500 mb-2">Kriteria ditampilkan saja (Tidak aktif pada Periode)</div>
                            <div class="space-y-1">
                                @forelse(($inactiveCriteriaRows ?? []) as $r)
                                    <div class="flex items-center justify-between gap-3 rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm">
                                        <div class="font-medium text-slate-800">{{ $r['criteria_name'] ?? '-' }}</div>
                                        <div class="flex items-center gap-2">
                                            <span class="px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-700" title="Tidak dihitung ke Skor Kinerja">Tidak dihitung</span>
                                            <span class="text-slate-600" title="Nilai normalisasi tetap ditampilkan">{{ number_format((float)($r['score_wsm'] ?? 0), 2) }}</span>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-sm text-slate-500">Tidak ada.</div>
                                @endforelse
                            </div>
                        </div>
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
                        $isActive = isset($activeCriteriaIdSet[(int)$d->performance_criteria_id]);
                    @endphp
                    <tr>
                        <td class="px-4 py-3">{{ $d->performanceCriteria->name ?? '-' }}</td>
                        <td class="px-4 py-3">{{ optional($d->performanceCriteria->type)->value }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <span>{{ $rel !== null ? number_format((float)$rel, 2) : '-' }}</span>
                                @if(!$isActive)
                                    <span class="px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-700" title="Kriteria ini tetap ditampilkan, tetapi tidak dihitung ke Skor Kinerja">Nonaktif (tidak dihitung)</span>
                                @endif
                            </div>
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
                                                @if(!$isActive)
                                                    <div class="mt-1 inline-flex">
                                                        <span class="px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-700">Nonaktif (tidak dihitung ke Skor Kinerja)</span>
                                                    </div>
                                                @endif
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
                                                    <div class="text-xs text-slate-500">Basis normalisasi: {{ $d->performanceCriteria->normalization_basis ?? '-' }}. Pembagi mengikuti kebijakan basis tersebut (contoh: total unit + profesi pada periode yang sama).</div>
                                                </div>
                                            @endif

                                            @php
                                                $maxNorm = $kinerja['maxByCriteria'][(int)$d->performance_criteria_id] ?? null;
                                                $maxNorm = $maxNorm !== null ? (float)$maxNorm : null;
                                            @endphp
                                            <div class="pt-3 mt-2 border-t border-slate-200">
                                                <div class="text-xs uppercase tracking-wide text-slate-500 mb-1">Nilai Kinerja Relatif (0–100)</div>
                                                @if($rel !== null && $maxNorm && $maxNorm > 0)
                                                    <div class="text-sm text-slate-800 font-semibold">
                                                        {{ number_format((float)($d->score ?? 0), 2) }} / {{ number_format($maxNorm, 2) }} × 100 = {{ number_format((float)$rel, 2) }}
                                                    </div>
                                                @else
                                                    <div class="text-sm text-slate-500">-</div>
                                                @endif
                                                <div class="text-xs text-slate-500">Dibandingkan terhadap nilai normalisasi tertinggi pada unit + profesi + periode yang sama. Skor relatif hanya untuk memudahkan interpretasi posisi relatif; nilai mentah dan nilai normalisasi tetap ditampilkan.</div>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2 text-xs text-slate-500">
                                            <i class="fa-solid fa-circle-info"></i>
                                            <span>Nilai normalisasi berasal dari normalisasi data mentah. Nilai Kinerja (Relatif Unit) membandingkan nilai Anda terhadap skor tertinggi di unit + profesi pada periode yang sama.</span>
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
