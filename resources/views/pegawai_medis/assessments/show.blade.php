<x-app-layout title="Detail Penilaian">
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold text-slate-800">Detail Penilaian</h1>
            <x-ui.button as="a" href="{{ route('pegawai_medis.assessments.index') }}" variant="outline" class="h-10 px-4">
                Kembali
            </x-ui.button>
        </div>
    </x-slot>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
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
                <div class="text-sm text-slate-500">Skor Kinerja</div>
                @if (($kinerja['applicable'] ?? false) && ($kinerja['hasWeights'] ?? false))
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-lg font-semibold">{{ number_format((float) ($kinerja['total'] ?? 0), 2) }}
                        </div>
                        <button type="button" class="px-3 py-2 rounded-lg border text-sm hover:bg-slate-50" x-data
                            @click="$dispatch('open-modal', 'kinerja-breakdown')">
                            DETAIL
                        </button>
                    </div>
                    @php
                        $source = (string) (($kinerja['calculationSource'] ?? null) ?: 'live');
                        $sourceLabel = $source === 'snapshot' ? 'Snapshot' : 'Live';
                        $sourceHint = $source === 'snapshot'
                            ? 'Periode sudah frozen; nilai diambil dari snapshot dan tidak berubah.'
                            : 'Periode masih berjalan; nilai mengikuti konfigurasi kriteria terbaru.';
                    @endphp
                    <div class="mt-1 text-xs text-slate-500" title="{{ $sourceHint }}">
                        Sumber: <span class="px-2 py-0.5 rounded-full border border-slate-200 bg-slate-50 text-slate-700">{{ $sourceLabel }}</span>
                    </div>
                @else
                    <div class="text-lg font-semibold">-</div>
                    @if (($kinerja['applicable'] ?? false) && !($kinerja['hasWeights'] ?? false))
                        <div class="text-xs text-amber-700 mt-1">Bobot kriteria pada periode ini belum berstatus aktif,
                            skor kinerja belum dapat ditampilkan.</div>
                    @endif
                @endif
            </div>
        </div>

        @if (($kinerja['applicable'] ?? false) && ($kinerja['hasWeights'] ?? false))
            <x-modal name="kinerja-breakdown" maxWidth="lg">
                <div class="p-6 space-y-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-xs uppercase tracking-wide text-slate-500">Detail Perhitungan</div>
                            <div class="text-lg font-semibold text-slate-900">DETAIL Skor Kinerja</div>
                            <div class="text-sm text-slate-600">Total:
                                {{ number_format((float) ($kinerja['total'] ?? 0), 2) }}</div>
                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                @php
                                    $src = (string) (($kinerja['calculationSource'] ?? null) ?: 'live');
                                @endphp
                                @if ($src === 'snapshot')
                                    <span class="px-2 py-0.5 rounded text-xs bg-slate-200 text-slate-800"
                                        title="Periode sudah frozen; skor berasal dari snapshot agar tidak berubah.">Snapshot</span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-xs bg-emerald-100 text-emerald-700"
                                        title="Periode belum frozen; skor mengikuti konfigurasi kriteria terbaru.">Live</span>
                                @endif

                                @if (!empty($kinerja['scope']))
                                    <span class="text-xs text-slate-500">
                                        Scope pembanding: Periode {{ $kinerja['scope']['period'] ?? '-' }},
                                        Unit {{ $kinerja['scope']['unit'] ?? '-' }},
                                        Profesi {{ $kinerja['scope']['profession'] ?? '-' }}.
                                    </span>
                                @endif
                            </div>
                            @if (!empty($kinerja['groupTotalWsm']) && isset($kinerja['sharePct']) && $kinerja['sharePct'] !== null)
                                <div class="text-xs text-slate-500">
                                    Porsi (share) = {{ number_format((float) ($kinerja['sharePct'] ?? 0) * 100, 2) }}%
                                    dari total grup {{ number_format((float) ($kinerja['groupTotalWsm'] ?? 0), 2) }}
                                    (total porsi grup = 100%).
                                </div>
                            @endif
                        </div>
                        <button type="button" class="text-slate-400 hover:text-slate-600"
                            @click="$dispatch('close-modal', 'kinerja-breakdown')">
                            <i class="fa-solid fa-xmark text-lg"></i>
                        </button>
                    </div>

                    <div class="rounded-xl bg-slate-50 border border-slate-200 p-4 space-y-4">
                        <div class="text-sm text-slate-700">
                            Skor Kinerja hanya dihitung dari <span class="font-semibold">kriteria aktif</span> pada
                            periode penilaian dan digunakan sebagai dasar pembagian remunerasi.
                        </div>

                        <div class="text-sm text-slate-700">
                            <div class="font-semibold">Catatan Normalisasi vs Relatif</div>
                            <div class="text-xs text-slate-600">
                                N (Nilai Normalisasi) untuk basis <span class="font-semibold">total_unit</span> dan tipe <span class="font-semibold">benefit</span>
                                secara konsep akan berjumlah 100 jika dijumlahkan across users dalam satu scope.
                                R (Nilai Relatif) adalah skala terhadap max(N) sehingga max(R)=100 dan <span class="font-semibold">tidak</span> harus sum=100.
                            </div>
                        </div>

                        <div>
                            <div class="text-xs uppercase tracking-wide text-slate-500 mb-2">Kriteria dihitung (Aktif
                                pada Periode)</div>
                            <div class="text-xs text-slate-500 mb-2">
                                Jumlah kriteria aktif dihitung: <span class="font-semibold text-slate-700">{{ (int) ($kinerja['activeCriteriaCount'] ?? 0) }}</span>.
                                @if (!empty($kinerja['weightSource']))
                                    Sumber bobot: <span class="font-semibold text-slate-700">{{ $kinerja['weightSource'] }}</span>.
                                @endif
                            </div>
                            <div class="overflow-auto rounded-xl border border-slate-200 bg-white">
                                <table class="min-w-[560px] w-full text-sm">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left">Kriteria</th>
                                            <th class="px-4 py-2 text-right">Bobot</th>
                                            <th class="px-4 py-2 text-right" title="Nilai relatif (0–100) yang dipakai untuk total skor kinerja">Nilai Relatif</th>
                                            <th class="px-4 py-2 text-right" title="Kontribusi = (bobot/ΣBobotAktif)×Nilai Relatif">Kontribusi
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse(($kinerja['rows'] ?? []) as $r)
                                            <tr class="border-t border-slate-100">
                                                <td class="px-4 py-2 font-medium text-slate-800">
                                                    {{ $r['criteria_name'] ?? '-' }}</td>
                                                <td class="px-4 py-2 text-right">
                                                    {{ number_format((float) ($r['weight'] ?? 0), 2) }}</td>
                                                <td class="px-4 py-2 text-right">
                                                    {{ number_format((float) ($r['score_wsm'] ?? 0), 2) }}</td>
                                                <td class="px-4 py-2 text-right">
                                                    {{ number_format((float) ($r['contribution'] ?? 0), 2) }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="px-4 py-3 text-slate-500">Tidak ada.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-xs text-slate-500 mt-2">
                                ΣBobotAktif = {{ number_format((float) ($kinerja['sumWeight'] ?? 0), 2) }}.
                            </div>
                            <div class="text-xs text-slate-500">
                                Total skor kinerja = Σ(bobot×nilai relatif) / Σ(bobot) (hanya kriteria aktif).
                            </div>
                        </div>

                        <div>
                            <div class="text-xs uppercase tracking-wide text-slate-500 mb-2">Kriteria ditampilkan saja
                                (Tidak dihitung)</div>
                            <div class="space-y-1">
                                @forelse(($inactiveCriteriaRows ?? []) as $r)
                                    <div
                                        class="flex items-center justify-between gap-3 rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm">
                                        <div class="font-medium text-slate-800">{{ $r['criteria_name'] ?? '-' }}</div>
                                        <div class="flex items-center gap-2">
                                            <span class="px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-700"
                                                title="Tidak dihitung ke Skor Kinerja">Tidak dihitung</span>
                                            <span class="text-slate-600" title="Nilai relatif unit (0–100)">
                                                {{ number_format((float) ($r['score_wsm'] ?? 0), 2) }}
                                            </span>
                                            <span class="text-slate-400" title="Nilai normalisasi">
                                                ({{ number_format((float) ($r['score_normalisasi'] ?? 0), 2) }})
                                            </span>
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
                        $rel = $kinerja['relativeByCriteria'][(int) $d->performance_criteria_id] ?? null;
                        $norm = $kinerja['normalizedByCriteria'][(int) $d->performance_criteria_id] ?? null;
                        $isActive = isset($activeCriteriaIdSet[(int) $d->performance_criteria_id]);
                    @endphp

                    <tr>
                        <td class="px-4 py-3">{{ $d->performanceCriteria->name ?? '-' }}</td>
                        <td class="px-4 py-3">{{ optional($d->performanceCriteria->type)->value }}</td>

                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <span>{{ $rel !== null ? number_format((float) $rel, 2) : '-' }}</span>
                                @if (!$isActive)
                                    <span class="px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-700"
                                        title="Kriteria ini tetap ditampilkan, tetapi tidak dihitung ke Skor Kinerja">
                                        Tidak dihitung
                                    </span>
                                @endif
                            </div>
                        </td>

                        <td class="px-4 py-3">
                            @if ($raw)
                                <button type="button"
                                    class="inline-flex items-center justify-center w-10 h-10 rounded-full border border-slate-200 text-slate-600 hover:border-emerald-500 hover:text-emerald-700"
                                    x-data @click="$dispatch('open-modal', 'raw-{{ $d->id }}')"
                                    title="Lihat data mentah">
                                    <i class="fa-solid fa-eye"></i>
                                </button>

                                <x-modal name="raw-{{ $d->id }}" maxWidth="lg">
                                    <div class="p-6 space-y-4">
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <div class="text-xs uppercase tracking-wide text-slate-500">Kriteria
                                                </div>
                                                <div class="text-lg font-semibold text-slate-900">
                                                    {{ $d->performanceCriteria->name ?? '-' }}</div>
                                                <div class="text-sm text-slate-600">
                                                    Nilai Kinerja (Relatif Unit):
                                                    {{ $rel !== null ? number_format((float) $rel, 2) : '-' }}
                                                </div>
                                                <div class="text-sm text-slate-600">
                                                    Nilai Normalisasi: {{ $norm !== null ? number_format((float) $norm, 2) : '-' }}
                                                </div>

                                                @if (!$isActive)
                                                    <div class="mt-1 inline-flex">
                                                        <span
                                                            class="px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-700">
                                                            Nonaktif (tidak dihitung ke Skor Kinerja)
                                                        </span>
                                                    </div>
                                                @endif
                                            </div>

                                            <button type="button" class="text-slate-400 hover:text-slate-600"
                                                @click="$dispatch('close-modal', 'raw-{{ $d->id }}')">
                                                <i class="fa-solid fa-xmark text-lg"></i>
                                            </button>
                                        </div>

                                        <div class="rounded-xl bg-slate-50 border border-slate-200 p-4 space-y-3">
                                            <div class="text-sm font-semibold text-slate-800">
                                                {{ $raw['title'] ?? 'Data mentah' }}</div>

                                            @foreach ($raw['lines'] ?? [] as $line)
                                                <div class="flex items-start justify-between gap-3">
                                                    <div>
                                                        <div class="text-sm text-slate-700 font-medium">
                                                            {{ $line['label'] ?? '-' }}</div>
                                                        @if (!empty($line['hint']))
                                                            <div class="text-xs text-slate-500">{{ $line['hint'] }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                    <div class="text-sm font-semibold text-slate-900 text-right">
                                                        {{ $line['value'] ?? '-' }}</div>
                                                </div>
                                            @endforeach

                                            @if (!empty($raw['formula']))
                                                <div class="pt-3 mt-2 border-t border-slate-200 space-y-2">
                                                    <div class="text-xs uppercase tracking-wide text-slate-500">
                                                        Rumus (teks)
                                                    </div>
                                                    @if (!empty($raw['formula']['normalization_text']))
                                                        <div class="text-sm text-slate-800 font-semibold">
                                                            {{ $raw['formula']['normalization_text'] }}
                                                        </div>
                                                    @endif
                                                    @if (!empty($raw['formula']['relative_text']))
                                                        <div class="text-sm text-slate-700">
                                                            {{ $raw['formula']['relative_text'] }}
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif

                                            @php
                                                $maxNorm = $kinerja['maxNormalizedByCriteria'][(int) $d->performance_criteria_id] ?? ($kinerja['maxByCriteria'][(int) $d->performance_criteria_id] ?? null);
                                                $maxNorm = $maxNorm !== null ? (float) $maxNorm : null;
                                            @endphp

                                            <div class="pt-3 mt-2 border-t border-slate-200">
                                                <div class="text-xs text-slate-500 mb-2">
                                                    Max nilai normalisasi (N) dalam scope:
                                                    <span class="font-semibold text-slate-700">{{ $maxNorm !== null ? number_format($maxNorm, 2) : '-' }}</span>
                                                </div>
                                                <div class="text-xs uppercase tracking-wide text-slate-500 mb-1">Nilai
                                                    Kinerja Relatif (0–100)</div>
                                                @if ($rel !== null)
                                                    @if ($norm !== null && $maxNorm !== null && $maxNorm > 0)
                                                        <div class="text-sm text-slate-800 font-semibold">
                                                            {{ number_format((float) $norm, 2) }} /
                                                            {{ number_format($maxNorm, 2) }} × 100
                                                            = {{ number_format((float) $rel, 2) }}
                                                        </div>
                                                    @else
                                                        <div class="text-sm text-slate-500">-</div>
                                                    @endif
                                                @else
                                                    <div class="text-sm text-slate-500">-</div>
                                                @endif
                                            </div>
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
                        <td colspan="4" class="px-4 py-6 text-center text-slate-500">Belum ada detail penilaian.
                        </td>
                    </tr>
                @endforelse

            </x-ui.table>
        </x-section>
    </div>
</x-app-layout>
