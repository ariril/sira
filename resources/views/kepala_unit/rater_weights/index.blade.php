<x-app-layout title="Bobot Penilai 360">
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold text-slate-800">Bobot Penilai 360</h1>
            @php($submitPeriodId = request('assessment_period_id') ?: ($currentPeriodId ?: $activePeriodId))
            <form method="POST" action="{{ route('kepala_unit.rater_weights.submit_all') }}" class="flex items-center gap-2">
                @csrf
                <input type="hidden" name="assessment_period_id" value="{{ $submitPeriodId }}" />
                <x-ui.button type="submit" variant="orange" :disabled="!($hasRelevant360Criteria && $hasRules && $hasProfession)">
                    Ajukan Semua
                </x-ui.button>
            </form>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        @if (session('status'))
            <div class="p-4 rounded-xl border text-sm bg-emerald-50 border-emerald-200 text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="p-4 rounded-xl border text-sm bg-rose-50 border-rose-200 text-rose-800">
                <div class="font-semibold">Tidak dapat memproses permintaan.</div>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (!$hasRelevant360Criteria)
            <div class="p-4 rounded-xl border text-sm bg-amber-50 border-amber-200 text-amber-800">
                <div class="font-semibold">Kriteria 360 belum dipilih untuk periode ini.</div>
                <div class="mt-1">Bobot penilai 360 hanya relevan jika unit sudah menambahkan kriteria 360 pada
                    periode berjalan (melalui Bobot Kriteria Unit).</div>
                <div class="mt-3">
                    <x-ui.button variant="orange" as="a" href="{{ $unitCriteriaWeightsUrl }}">Buka Bobot
                        Kriteria Unit</x-ui.button>
                </div>
            </div>
        @elseif(!$hasRules)
            <div class="p-4 rounded-xl border text-sm bg-amber-50 border-amber-200 text-amber-800">
                <div class="font-semibold">Aturan Kriteria 360 belum tersedia untuk kriteria unit.</div>
                <div class="mt-1">Admin RS perlu menambahkan aturan tipe penilai untuk kriteria 360 yang dipakai unit
                    pada periode ini.</div>
                <div class="mt-3">
                    <x-ui.button variant="success" as="a" href="{{ $rulesUrl }}">Buka Aturan Kriteria
                        360</x-ui.button>
                </div>
            </div>
        @endif

        @if (!$hasProfession)
            <div class="p-4 rounded-xl border text-sm bg-amber-50 border-amber-200 text-amber-800">
                <div class="font-semibold">Profesi unit belum terdeteksi.</div>
                <div class="mt-1">Tambahkan pegawai medis pada unit Anda (dengan profesi) agar dropdown profesi
                    tersedia.</div>
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
            <form method="GET" class="grid gap-4 md:grid-cols-12 items-end">
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Periode</label>
                    <x-ui.select name="assessment_period_id" :options="$periods->pluck('name', 'id')" :value="request('assessment_period_id')" placeholder="Semua" />
                </div>
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Kriteria</label>
                    <x-ui.select name="performance_criteria_id" :options="$criteriaOptions" :value="request('performance_criteria_id')" placeholder="Semua" />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Profesi</label>
                    <x-ui.select name="assessee_profession_id" :options="$professions->pluck('name', 'id')" :value="request('assessee_profession_id')"
                        placeholder="Semua" />
                </div>
                <div class="md:col-span-1">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
                    <x-ui.select name="status" :options="$statuses" :value="request('status')" placeholder="Semua" />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Atasan Level</label>
                    <x-ui.select name="assessor_level" :options="$supervisorLevelOptions" :value="request('assessor_level')" placeholder="Semua" />
                </div>
                <div class="md:col-span-1 flex gap-2">
                    <x-ui.button type="submit" variant="outline" class="w-full">Filter</x-ui.button>
                </div>
            </form>

            <div class="mt-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div class="text-sm text-slate-600">
                    <div class="font-medium text-slate-700">Catatan</div>
                    <div>Bobot penilai 360 bersifat per (Periode, Unit, Kriteria, Profesi) dan harus berjumlah 100%.</div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-x-auto">
            <div class="px-6 py-4 border-b border-slate-100">
                <div class="font-semibold text-slate-800">Draft & Periode Berjalan</div>
                <div class="text-sm text-slate-500">Menampilkan Draft/Revisi/Pending/Aktif pada periode berjalan (atau
                    periode terpilih).</div>
            </div>

            <x-ui.table min-width="960px">
                <x-slot name="head">
                    <tr>
                        <th class="px-6 py-4 text-left">Periode</th>
                        <th class="px-6 py-4 text-left">Unit</th>
                        <th class="px-6 py-4 text-left">Kriteria</th>
                        <th class="px-6 py-4 text-left">Profesi</th>
                        <th class="px-6 py-4 text-left">Tipe Penilai</th>
                        <th class="px-6 py-4 text-right">Bobot (%)</th>
                        <th class="px-6 py-4 text-left">Status</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </x-slot>

                @forelse($itemsWorking as $row)
                    @php($st = $row->status?->value ?? (string) $row->status)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4">{{ $row->period?->name ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $row->unit?->name ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $row->criteria?->name ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $row->assesseeProfession?->name ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $row->assessor_label }}</td>
                        <td class="px-6 py-3 text-right">
                            @php($autoType = $singleRuleTypeByCriteriaId[(int) $row->performance_criteria_id] ?? null)
                            @php($isAuto100 = !empty($autoType) && (string) $autoType === (string) $row->assessor_type && (float) ($row->weight ?? 0) === 100.0)
                            @php($editable = in_array($st, ['draft', 'rejected'], true) && !$isAuto100)

                            @if($editable)
                                <form method="POST" action="{{ route('kepala_unit.rater_weights.update', $row) }}" class="inline-flex items-center justify-end gap-2">
                                    @csrf
                                    @method('PUT')
                                    <x-ui.input type="number" step="0.01" min="0" max="100" name="weight" :value="number_format((float) ($row->weight ?? 0), 2, '.', '')" :preserve-old="false" class="h-9 w-24 max-w-[110px] text-right" />
                                    <x-ui.button type="submit" variant="orange" class="h-9 px-3 text-xs">Simpan</x-ui.button>
                                </form>
                            @else
                                <div class="inline-flex items-center justify-end gap-2">
                                    @if($isAuto100)
                                        <x-ui.input type="text" value="100 (otomatis)" :preserve-old="false" disabled class="h-9 w-28 max-w-[140px] text-right bg-slate-100 text-slate-500 border-slate-200" />
                                    @else
                                        <x-ui.input type="number" :value="number_format((float) ($row->weight ?? 0), 2, '.', '')" :preserve-old="false" disabled class="h-9 w-24 max-w-[110px] text-right bg-slate-100 text-slate-500 border-slate-200" />
                                    @endif
                                    <span class="text-xs text-slate-400">Terkunci</span>
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if ($st === 'active')
                                <span class="px-2 py-1 rounded text-xs bg-emerald-100 text-emerald-700">Aktif</span>
                            @elseif($st === 'pending')
                                <span class="px-2 py-1 rounded text-xs bg-amber-100 text-amber-700">Pending</span>
                            @elseif($st === 'rejected')
                                <span class="px-2 py-1 rounded text-xs bg-rose-100 text-rose-700">Ditolak</span>
                            @else
                                <span class="px-2 py-1 rounded text-xs bg-slate-100 text-slate-700">Draft</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-xs text-slate-400">—</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-10 text-center text-sm text-slate-500">Belum ada data.</td>
                    </tr>
                @endforelse
            </x-ui.table>
        </div>

        <div>
            {{ $itemsWorking->links() }}
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-x-auto">
            <div class="px-6 py-4 border-b border-slate-100">
                <div class="font-semibold text-slate-800">Riwayat</div>
                <div class="text-sm text-slate-500">Menampilkan periode sebelumnya dan/atau status Arsip.</div>
            </div>

            <x-ui.table min-width="960px">
                <x-slot name="head">
                    <tr>
                        <th class="px-6 py-4 text-left">Periode</th>
                        <th class="px-6 py-4 text-left">Unit</th>
                        <th class="px-6 py-4 text-left">Kriteria</th>
                        <th class="px-6 py-4 text-left">Profesi</th>
                        <th class="px-6 py-4 text-left">Tipe Penilai</th>
                        <th class="px-6 py-4 text-right">Bobot (%)</th>
                        <th class="px-6 py-4 text-left">Status</th>
                    </tr>
                </x-slot>

                @forelse($itemsHistory as $row)
                    @php($st = $row->status?->value ?? (string) $row->status)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4">{{ $row->period?->name ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $row->unit?->name ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $row->criteria?->name ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $row->assesseeProfession?->name ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $row->assessor_label }}</td>
                        <td class="px-6 py-4 text-right">{{ number_format((float) $row->weight, 2) }}</td>
                        <td class="px-6 py-4">
                            @if ($st === 'archived')
                                <span class="px-2 py-1 rounded text-xs bg-slate-100 text-slate-700">Arsip</span>
                            @elseif($st === 'active')
                                <span class="px-2 py-1 rounded text-xs bg-emerald-100 text-emerald-700">Aktif</span>
                            @elseif($st === 'pending')
                                <span class="px-2 py-1 rounded text-xs bg-amber-100 text-amber-700">Pending</span>
                            @elseif($st === 'rejected')
                                <span class="px-2 py-1 rounded text-xs bg-rose-100 text-rose-700">Ditolak</span>
                            @else
                                <span class="px-2 py-1 rounded text-xs bg-slate-100 text-slate-700">Draft</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-10 text-center text-sm text-slate-500">Belum ada data.</td>
                    </tr>
                @endforelse
            </x-ui.table>
        </div>

        <div>
            {{ $itemsHistory->links() }}
        </div>
    </div>
</x-app-layout>
