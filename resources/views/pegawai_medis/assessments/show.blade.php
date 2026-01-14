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
                            <div class="text-lg font-semibold text-slate-900">Rincian Skor Kinerja</div>
                            <div class="text-sm text-slate-600">Total:
                                {{ number_format((float) ($kinerja['total'] ?? 0), 2) }}</div>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
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
                            </div>

                            @php
                                $scope = (array) ($kinerja['scope'] ?? []);
                                $scopePeriod = (string) (($scope['period'] ?? null) ?: '');
                                $scopeUnit = (string) (($scope['unit'] ?? null) ?: '');
                                $scopeProfession = (string) (($scope['profession'] ?? null) ?: '');
                                $hasScope = trim($scopePeriod . $scopeUnit . $scopeProfession) !== '';
                            @endphp
                            @if ($hasScope)
                                <div class="mt-1 text-xs text-slate-500">
                                    Scope pembanding:
                                    Periode <span class="font-semibold text-slate-700">{{ $scopePeriod !== '' ? $scopePeriod : '-' }}</span>,
                                    Unit <span class="font-semibold text-slate-700">{{ $scopeUnit !== '' ? $scopeUnit : '-' }}</span>,
                                    Profesi <span class="font-semibold text-slate-700">{{ $scopeProfession !== '' ? $scopeProfession : '-' }}</span>.
                                </div>
                            @endif
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
                                            <th class="px-4 py-2 text-right" title="Nilai kinerja untuk kriteria ini (0–100)">Nilai (0–100)</th>
                                            <th class="px-4 py-2 text-right" title="Seberapa besar kriteria ini memengaruhi total skor">Kontribusi</th>
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
                                                    {{ number_format(((float) ($r['score_wsm'] ?? 0) * (float) ($r['weight'] ?? 0)) / 100, 2) }}
                                                </td>
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
                                Total bobot kriteria aktif: {{ number_format((float) ($kinerja['sumWeight'] ?? 0), 2) }}.
                            </div>
                        </div>

                        <div>
                            <div class="text-xs uppercase tracking-wide text-slate-500 mb-2">Kriteria nonaktif (tidak
                                masuk skor)</div>
                            <div class="space-y-1">
                                @forelse(($inactiveCriteriaRows ?? []) as $r)
                                    <div
                                        class="flex items-center justify-between gap-3 rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm">
                                        <div class="font-medium text-slate-800">{{ $r['criteria_name'] ?? '-' }}</div>
                                        <div class="flex items-center gap-2">
                                            <span class="px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-700"
                                                title="Tidak dihitung ke Skor Kinerja">Tidak dihitung</span>
                                            <span class="text-slate-600" title="Nilai kinerja (0–100)">
                                                {{ number_format((float) ($r['score_wsm'] ?? 0), 2) }}
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
            @php
                $kinerjaRowByCriteriaId = collect($kinerja['rows'] ?? [])->keyBy('criteria_id');
            @endphp
            <x-ui.table min-width="760px">
                <x-slot name="head">
                    <tr>
                        <th class="text-left px-4 py-3 whitespace-nowrap">Kriteria</th>
                        <th class="text-left px-4 py-3 whitespace-nowrap">Tipe</th>
                        <th class="text-left px-4 py-3 whitespace-nowrap">Nilai Kinerja (0–100)</th>
                        <th class="text-left px-4 py-3 whitespace-nowrap">Detail</th>
                    </tr>
                </x-slot>
                @forelse($visibleDetails as $d)
                    @php
                        $raw = $rawMetrics[$d->performance_criteria_id] ?? null;
                        $rel = $kinerja['relativeByCriteria'][(int) $d->performance_criteria_id] ?? null;
                        $isActive = isset($activeCriteriaIdSet[(int) $d->performance_criteria_id]);
                        $row = $kinerjaRowByCriteriaId[(int) $d->performance_criteria_id] ?? null;
                        $weight = is_array($row) ? ($row['weight'] ?? null) : null;
                        $simpleContribution = ($weight !== null && $rel !== null)
                            ? (((float) $rel * (float) $weight) / 100)
                            : null;

                        $criteriaId = (int) $d->performance_criteria_id;
                        $criteria = $d->performanceCriteria;
                        $criteriaType = optional($criteria?->type)->value ?? (string) (($criteria?->type ?? null) ?: 'benefit');
                        $criteriaType = $criteriaType === 'cost' ? 'cost' : 'benefit';

                        // These are internal values used to derive the displayed 0–100 score.
                        // We present them in a user-friendly way (no technical terms).
                        $yourBaseValue = $kinerja['normalizedByCriteria'][$criteriaId] ?? null;
                        $yourBaseValue = $yourBaseValue !== null ? (float) $yourBaseValue : null;

                        $bestBaseValue = null;
                        if ($criteriaType === 'cost') {
                            $bestBaseValue = $kinerja['minNormalizedByCriteria'][$criteriaId] ?? ($kinerja['minByCriteria'][$criteriaId] ?? null);
                        } else {
                            $bestBaseValue = $kinerja['maxNormalizedByCriteria'][$criteriaId] ?? ($kinerja['maxByCriteria'][$criteriaId] ?? null);
                        }
                        $bestBaseValue = $bestBaseValue !== null ? (float) $bestBaseValue : null;
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
                                    x-data @click="$dispatch('open-modal', 'nilai-mentah-{{ $d->id }}')"
                                    title="Lihat nilai mentah">
                                    <i class="fa-solid fa-eye"></i>
                                </button>

                                <x-modal name="nilai-mentah-{{ $d->id }}" maxWidth="lg">
                                    <div class="p-6 space-y-4">
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <div class="text-lg font-semibold text-slate-900">
                                                    {{ $d->performanceCriteria->name ?? '-' }}</div>
                                                <div class="text-sm text-slate-600">
                                                    Nilai Kinerja (0–100):
                                                    {{ $rel !== null ? number_format((float) $rel, 2) : '-' }}
                                                </div>
                                                @if ($isActive)
                                                    <div class="mt-2 space-y-1 text-xs text-slate-600">
                                                        @if ($weight !== null)
                                                            <div>
                                                                Bobot: <span class="font-semibold text-slate-800">{{ number_format((float) $weight, 2) }}</span>
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endif

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
                                                @click="$dispatch('close-modal', 'nilai-mentah-{{ $d->id }}')">
                                                <i class="fa-solid fa-xmark text-lg"></i>
                                            </button>
                                        </div>

                                        <div class="rounded-xl bg-slate-50 border border-slate-200 p-4 space-y-3">
                                            @php
                                                $rawTitle = (string) ($raw['title'] ?? 'Rincian');
                                                if (mb_strtolower($rawTitle) === 'detail perhitungan') {
                                                    $rawTitle = 'Rincian';
                                                }
                                            @endphp
                                            <div class="text-sm font-semibold text-slate-800">{{ $rawTitle }}</div>

                                            @if ($rel !== null && $yourBaseValue !== null && $bestBaseValue !== null)
                                                @php
                                                    $parseNumber = function ($v): ?float {
                                                        if ($v === null) {
                                                            return null;
                                                        }
                                                        $s = trim((string) $v);
                                                        if ($s === '' || $s === '-') {
                                                            return null;
                                                        }
                                                        // Keep digits, dot, comma, minus.
                                                        $s = preg_replace('/[^0-9,\.\-]/', '', $s);
                                                        $s = trim((string) $s);
                                                        if ($s === '' || $s === '-' || $s === '.' || $s === ',') {
                                                            return null;
                                                        }
                                                        // If both separators exist, assume comma is thousands separator.
                                                        if (str_contains($s, ',') && str_contains($s, '.')) {
                                                            $s = str_replace(',', '', $s);
                                                        } elseif (str_contains($s, ',') && !str_contains($s, '.')) {
                                                            // Treat comma as decimal separator.
                                                            $s = str_replace(',', '.', $s);
                                                        }
                                                        if (!is_numeric($s)) {
                                                            return null;
                                                        }
                                                        return (float) $s;
                                                    };

                                                    $rawIndividuVal = null;
                                                    $pembandingVal = null;
                                                    foreach (($raw['lines'] ?? []) as $__line) {
                                                        $lbl = mb_strtolower((string) ($__line['label'] ?? ''));
                                                        if ($rawIndividuVal === null && (str_contains($lbl, 'raw individu') || str_contains($lbl, 'nilai mentah individu'))) {
                                                            $rawIndividuVal = $parseNumber($__line['value'] ?? null);
                                                        }
                                                        if ($pembandingVal === null && str_contains($lbl, 'pembanding')) {
                                                            $pembandingVal = $parseNumber($__line['value'] ?? null);
                                                        }
                                                    }

                                                    $derivedBaseValue = null;
                                                    if ($rawIndividuVal !== null && $pembandingVal !== null && (float) $pembandingVal != 0.0) {
                                                        $derivedBaseValue = ((float) $rawIndividuVal / (float) $pembandingVal) * 100.0;
                                                    }
                                                @endphp
                                                <div class="rounded-lg bg-white border border-slate-200 px-3 py-2">
                                                    <div class="text-xs uppercase tracking-wide text-slate-500">Cara nilai 0–100 dihitung</div>
                                                    @if ($derivedBaseValue !== null)
                                                        <div class="mt-1 text-xs text-slate-600">
                                                            Nilai Anda di unit (dari data):
                                                            <span class="font-semibold text-slate-800">{{ number_format((float) $rawIndividuVal, 2) }}</span>
                                                            /
                                                            <span class="font-semibold text-slate-800">{{ number_format((float) $pembandingVal, 2) }}</span>
                                                            × 100
                                                            = <span class="font-semibold text-slate-800">{{ number_format((float) $yourBaseValue, 2) }}</span>
                                                        </div>
                                                    @else
                                                    <div class="mt-1 text-xs text-slate-600">
                                                        Nilai Anda di unit: <span class="font-semibold text-slate-800">{{ number_format((float) $yourBaseValue, 2) }}</span>
                                                    </div>
                                                    @endif
                                                    <div class="text-xs text-slate-600">
                                                        {{ $criteriaType === 'cost' ? 'Nilai terbaik di unit (terendah):' : 'Nilai tertinggi di unit:' }}
                                                        <span class="font-semibold text-slate-800">{{ number_format((float) $bestBaseValue, 2) }}</span>
                                                    </div>
                                                    @if ($criteriaType === 'cost')
                                                        @if ((float) $yourBaseValue > 0)
                                                            <div class="mt-1 text-sm text-slate-700">
                                                                {{ number_format((float) $bestBaseValue, 2) }} /
                                                                {{ number_format((float) $yourBaseValue, 2) }} × 100
                                                                = <span class="font-semibold text-slate-900">{{ number_format((float) $rel, 2) }}</span>
                                                            </div>
                                                        @else
                                                            <div class="mt-1 text-sm text-slate-500">-</div>
                                                        @endif
                                                    @else
                                                        <div class="mt-1 text-sm text-slate-700">
                                                            {{ number_format((float) $yourBaseValue, 2) }} /
                                                            {{ number_format((float) $bestBaseValue, 2) }} × 100
                                                            = <span class="font-semibold text-slate-900">{{ number_format((float) $rel, 2) }}</span>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif

                                            @foreach ($raw['lines'] ?? [] as $line)
                                                @php
                                                    $label = (string) ($line['label'] ?? '-');
                                                    $labelLower = mb_strtolower($label);
                                                    $skipLine = false;
                                                    $isSimpleContribution = false;
                                                    $isWeightedContribution = false;
                                                    $isRelativeLine = false;
                                                    $isWeightLine = false;

                                                    // Normalize the label a bit for matching.
                                                    $labelLowerNorm = str_replace([' ', '×', '*'], ['', 'x', 'x'], $labelLower);
                                                    $isSimpleContribution = str_contains($labelLowerNorm, 'kontribusi') && str_contains($labelLowerNorm, 'rxbobot/100');
                                                    $isWeightedContribution = str_contains($labelLower, 'kontribusi') && str_contains($labelLower, 'berbobot');
                                                    $isRelativeLine = str_contains($labelLower, 'nilai relatif');
                                                    $isWeightLine = trim($labelLower) === 'bobot';

                                                    foreach (['normalisasi', 'normalization', 'max', 'min', 'rumus', 'formula'] as $needle) {
                                                        if (str_contains($labelLower, $needle)) {
                                                            $skipLine = true;
                                                            break;
                                                        }
                                                    }

                                                    // Remove technical repeats inside the modal (already shown on header).
                                                    if ($isRelativeLine || $isWeightLine || $isWeightedContribution) {
                                                        $skipLine = true;
                                                    }

                                                    // Keep only the simple contribution form.
                                                    if (str_contains($labelLower, 'kontribusi') && !$isSimpleContribution && !$isWeightedContribution) {
                                                        $skipLine = true;
                                                    }

                                                    if (str_contains($labelLower, 'pembanding')) {
                                                        $skipLine = false;
                                                    }

                                                    if ($isSimpleContribution) {
                                                        $displayLabel = 'Kontribusi';
                                                    } else {
                                                        $displayLabel = preg_replace('/\s*\([^)]*basis[^)]*\)\s*/i', '', $label);
                                                        $displayLabel = trim((string) $displayLabel);

                                                        // Label untuk user: tampilkan "Nilai mentah".
                                                        if (preg_match('/^raw\b/i', $displayLabel)) {
                                                            $displayLabel = preg_replace('/^raw\b/i', 'Nilai mentah', $displayLabel);
                                                        }
                                                    }
                                                @endphp
                                                @if ($skipLine)
                                                    @continue
                                                @endif

                                                <div class="flex items-start justify-between gap-3">
                                                    <div>
                                                        <div class="text-sm text-slate-700 font-medium">
                                                            {{ $displayLabel !== '' ? $displayLabel : ($line['label'] ?? '-') }}
                                                        </div>
                                                        @if (!empty($line['hint']))
                                                            @php
                                                                $hintLower = mb_strtolower((string) $line['hint']);
                                                                $skipHint = false;
                                                                // Hide technical hints.
                                                                if (str_contains($labelLower, 'kontribusi')) {
                                                                    $skipHint = true;
                                                                }
                                                                foreach (['normalisasi', 'normalization', 'max', 'min', 'rumus', 'formula', 'bobotaktif', 'Σ', 'sigma'] as $needle) {
                                                                    if (str_contains($hintLower, $needle)) {
                                                                        $skipHint = true;
                                                                        break;
                                                                    }
                                                                }
                                                            @endphp
                                                            @if (!$skipHint)
                                                                <div class="text-xs text-slate-500">{{ $line['hint'] }}</div>
                                                            @endif
                                                        @endif

                                                        @if ($isSimpleContribution && $simpleContribution !== null && $rel !== null && $weight !== null)
                                                            <div class="text-xs text-slate-500">
                                                                {{ number_format((float) $rel, 2) }} × {{ number_format((float) $weight, 2) }} / 100
                                                            </div>
                                                        @endif
                                                    </div>
                                                    <div class="text-sm font-semibold text-slate-900 text-right">
                                                        {{ $line['value'] ?? '-' }}
                                                    </div>
                                                </div>
                                            @endforeach
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
