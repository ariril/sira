<x-app-layout title="Bobot Penilai 360 - {{ $unitName ?? 'Unit' }}" :suppressGlobalError="true">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-800">Bobot Penilai 360 - {{ $unitName ?? 'Unit' }}</h1>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <x-modal name="help-rater-weights" :show="false" maxWidth="2xl">
            <div class="p-6">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="text-lg font-semibold text-slate-800">Informasi Penggunaan</h2>
                    <button type="button" class="text-slate-400 hover:text-slate-600" x-on:click="$dispatch('close-modal', 'help-rater-weights')">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="mt-3 text-sm text-slate-700 space-y-2">
                    <div>Modul ini digunakan untuk menyusun Bobot Penilai 360 pada periode berjalan.</div>
                    <ul class="list-disc pl-5 space-y-1">
                        <li>Tombol <strong>Cek</strong> menyimpan semua input bobot pada halaman ini sebagai <strong>Draft</strong> (berguna sebelum pindah halaman / sebelum Ajukan Semua).</li>
                        <li>Tombol <strong>Ajukan Semua</strong> mengirim seluruh bobot draft/ditolak menjadi <strong>Pending</strong> jika total per kelompok sudah 100%.</li>
                        <li>Tombol <strong>Salin periode sebelumnya</strong> menyalin bobot periode sebelumnya ke periode aktif sebagai draft (menimpa draft yang ada).</li>
                    </ul>
                </div>

                <div class="mt-5 flex items-center justify-between gap-3">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700 select-none">
                        <input id="rwHelpDontShow" type="checkbox" class="rounded border-slate-300 text-slate-700 focus:ring-slate-300" />
                        Jangan tampilkan lagi
                    </label>
                    <x-ui.button type="button" variant="orange" class="h-10 px-6" x-on:click="window.__closeRwHelpModal()">OK</x-ui.button>
                </div>
            </div>
        </x-modal>

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
                <div class="mt-1">Bobot penilai 360 akan muncul setelah Bobot Kriteria Unit untuk kriteria 360 pada periode ini
                    <strong>diajukan (Pending)</strong> atau sudah <strong>Aktif</strong>.</div>
                <div class="mt-1">Silakan buka Bobot Kriteria Unit lalu klik <strong>Ajukan Semua</strong> untuk kriteria 360.</div>
                <div class="mt-3">
                    <x-ui.button variant="outline" as="a" href="{{ $unitCriteriaWeightsUrl }}">Buka Bobot
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

        <div class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <form method="GET">
                <div class="grid gap-4 md:grid-cols-12 items-end">
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
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
                    <x-ui.select name="status" :options="$statuses" :value="request('status')" placeholder="Semua" class="min-w-[160px]" />
                </div>
                </div>

                <div class="mt-4 flex justify-end gap-3">
                    <a href="{{ route('kepala_unit.rater_weights.index') }}"
                        class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50">
                        <i class="fa-solid fa-rotate-left"></i> Reset
                    </a>
                    <button type="submit"
                        class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm">
                        <i class="fa-solid fa-filter"></i> Terapkan
                    </button>
                </div>
            </form>

            {{-- Catatan dihapus sesuai permintaan --}}
        </div>

        {{-- ACTIONS (align with Unit Criteria Weights placement) --}}
        @php($submitPeriodId = request('assessment_period_id') ?: ($currentPeriodId ?: $activePeriodId))
        <div class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between mb-4">
                <h3 class="text-slate-800 font-semibold">Kelola Bobot (Draft){{ !empty($activePeriodName) ? ' - Periode '.$activePeriodName : '' }}</h3>
                <div class="flex items-center gap-3">
                    @if(!empty($canCopyPrevious ?? false))
                        <form method="POST" action="{{ route('kepala_unit.rater_weights.copy_previous') }}" class="inline-flex">
                            @csrf
                            <x-ui.button type="submit" variant="outline" class="h-10 px-4 text-sm" onclick="return confirm('Salin bobot periode sebelumnya ke periode aktif sebagai draft? Draft yang ada akan ditimpa.')">
                                <i class="fa-solid fa-copy mr-2"></i> Salin periode sebelumnya
                            </x-ui.button>
                        </form>
                    @endif
                </div>
            </div>

            @php($disabledActions = !($hasRelevant360Criteria && $hasRules && $hasProfession))
            @php($readyToSubmit = (bool) ($canSubmitAll ?? false))
            @php($pendingGroups = (int) ($pendingGroupCount ?? 0))
            @php($totalGroups = (int) ($totalGroupCount ?? 0))
            @php($submittedPercent = (float) ($submittedGroupPercent ?? 0))
            @php($allActive = (bool) ($allGroupsActive ?? false))

            @if($disabledActions)
                <div class="mb-4 p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm">
                    Lengkapi prasyarat (kriteria 360, aturan penilai, dan profesi unit) agar bobot penilai 360 bisa dikelola.
                </div>
            @elseif($pendingGroups > 0)
                <div class="mb-4 p-4 rounded-xl bg-blue-50 border border-blue-200 text-blue-800 text-sm">
                    Pengajuan bobot sedang menunggu persetujuan Kepala Poliklinik.
                    @if($totalGroups > 0)
                        <span class="font-semibold">{{ number_format($submittedPercent, 2) }}%</span>
                        kelompok telah diajukan/aktif.
                    @endif
                </div>
            @elseif($allActive && $totalGroups > 0)
                <div class="mb-4 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">
                    Seluruh bobot telah disetujui dan aktif untuk periode {{ $activePeriodName ?? '-' }}.
                </div>
            @elseif($readyToSubmit)
                <div class="mb-4 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between gap-3">
                    <span>Semua kelompok bobot sudah 100% dan siap diajukan. Jika Anda baru mengubah nilai, klik <strong>Cek</strong> terlebih dahulu.</span>
                    <div class="flex items-center gap-2">
                        <x-ui.button variant="outline" type="submit" form="rwBulkForm">Cek</x-ui.button>
                        <form method="POST" action="{{ route('kepala_unit.rater_weights.submit_all') }}" id="rwSubmitAllForm" class="inline-flex">
                            @csrf
                            <input type="hidden" name="assessment_period_id" value="{{ $submitPeriodId }}" />
                            <x-ui.button type="submit" variant="orange">Ajukan Semua</x-ui.button>
                        </form>
                    </div>
                </div>
            @else
                <div class="mb-4 p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm flex items-center justify-between gap-3">
                    <span>Klik <strong>Cek</strong> untuk menyimpan semua input pada halaman ini sebagai draft. Pastikan total per (kriteria + profesi assessee) = <strong>100%</strong> sebelum <strong>Ajukan Semua</strong>.</span>
                    <div class="flex items-center gap-2">
                        <x-ui.button variant="outline" type="submit" form="rwBulkForm">Cek</x-ui.button>
                        <form method="POST" action="{{ route('kepala_unit.rater_weights.submit_all') }}" id="rwSubmitAllForm" class="inline-flex">
                            @csrf
                            <input type="hidden" name="assessment_period_id" value="{{ $submitPeriodId }}" />
                            <x-ui.button type="submit" variant="orange" disabled>Ajukan Semua</x-ui.button>
                        </form>
                    </div>
                </div>
            @endif
        </div>

        {{-- CHECKLIST SUBMIT ALL --}}
        <div id="rwChecklistWrap">
        @if(!empty($submitChecklist) && count($submitChecklist) > 0)
            <div class="space-y-3">
                <h4 class="text-sm font-semibold text-slate-700">Checklist Pengajuan (Wajib 100%)</h4>
                <x-ui.table min-width="720px">
                    <x-slot name="head">
                        <tr>
                            <th class="px-6 py-4 text-left">Kriteria</th>
                            <th class="px-6 py-4 text-left">Profesi (Assessee)</th>
                            <th class="px-6 py-4 text-left whitespace-nowrap">Rincian</th>
                            <th class="px-6 py-4 text-right whitespace-nowrap">
                                Total
                                <i class="fa-solid fa-percent ml-1 text-slate-400" aria-hidden="true"></i>
                            </th>
                            <th class="px-6 py-4 text-left">Status</th>
                        </tr>
                    </x-slot>
                    @foreach($submitChecklist as $row)
                        @php($ok = (bool) ($row['ok'] ?? false))
                        @php($hasNull = (bool) ($row['has_null'] ?? false))
                        @php($sum = (float) ($row['sum'] ?? 0))
                        @php($over = (int) round($sum, 0) > 100)
                        @php($ckey = (string) (($row['criteria_id'] ?? 0) . ':' . ($row['profession_id'] ?? 0)))
                        <tr class="{{ $over ? 'bg-rose-50' : ($ok ? 'bg-emerald-50' : 'bg-amber-50') }} rw-checklist-row" data-checklist-key="{{ $ckey }}">
                            <td class="px-6 py-4">{{ $row['criteria_name'] ?? '-' }}</td>
                            <td class="px-6 py-4">{{ $row['profession_name'] ?? '-' }}</td>
                            <td class="px-6 py-4 text-sm text-slate-700">
                                @php($parts = (array) ($row['parts'] ?? []))
                                @php($labels = ['supervisor' => 'Atasan', 'peer' => 'Rekan', 'subordinate' => 'Bawahan', 'self' => 'Diri'])
                                @php($chunks = [])
                                @foreach($labels as $k => $lbl)
                                    @php($val = (float) ($parts[$k] ?? 0))
                                    @if($val > 0)
                                        @php($chunks[] = $lbl . ' ' . number_format($val, 0))
                                    @endif
                                @endforeach
                                {{ !empty($chunks) ? implode(' / ', $chunks) : '-' }}
                            </td>
                            <td class="px-6 py-4 text-right font-semibold rw-checklist-sum {{ $over ? 'text-rose-700' : ($ok ? 'text-emerald-700' : 'text-amber-800') }}">
                                {{ number_format($sum, 0) }}
                            </td>
                            <td class="px-6 py-4">
                                @if($over)
                                    <span class="px-2 py-1 rounded text-xs bg-rose-100 text-rose-700 rw-checklist-badge">Sudah melewati 100%</span>
                                @elseif($ok)
                                    <span class="px-2 py-1 rounded text-xs bg-emerald-100 text-emerald-700 rw-checklist-badge">OK</span>
                                @elseif($hasNull)
                                    <span class="px-2 py-1 rounded text-xs bg-amber-100 text-amber-800 rw-checklist-badge">Belum lengkap</span>
                                @else
                                    <span class="px-2 py-1 rounded text-xs bg-amber-100 text-amber-800 rw-checklist-badge">Belum 100%</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-ui.table>
            </div>
        @endif
        </div>

        <div id="rwWorkingWrap" class="space-y-3">
            <h4 class="text-sm font-semibold text-slate-700">Draft & Pengajuan Berjalan</h4>
            <form id="rwBulkForm" method="POST" action="{{ route('kepala_unit.rater_weights.cek') }}">
                @csrf
                <x-ui.table min-width="960px">
                    <x-slot name="head">
                        <tr>
                            <th class="px-6 py-4 text-left">Kriteria</th>
                            <th class="px-6 py-4 text-left">Profesi</th>
                            <th class="px-6 py-4 text-left">Tipe Penilai</th>
                            <th class="px-6 py-4 text-right whitespace-nowrap">
                                Bobot
                                <i class="fa-solid fa-percent ml-1 text-slate-400" aria-hidden="true"></i>
                                <span class="inline-block ml-1 text-amber-600 cursor-help" title="Bobot maksimal 100%.">!</span>
                            </th>
                            <th class="px-6 py-4 text-left">Status</th>
                        </tr>
                    </x-slot>

                @forelse($itemsWorking as $row)
                    @php($st = $row->status?->value ?? (string) $row->status)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4">{{ $row->criteria?->name ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $row->assesseeProfession?->name ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $row->assessor_label }}</td>
                        <td class="px-6 py-3 text-right">
                            @php($autoType = $singleRuleTypeByCriteriaId[(int) $row->performance_criteria_id] ?? null)
                            @php($groupKey = (int) $row->assessment_period_id . ':' . (int) $row->performance_criteria_id . ':' . (int) $row->assessee_profession_id)
                            @php($isAutoSingleLine = ((int) ($groupCountsByKey[$groupKey] ?? 0) === 1) && (float) ($row->weight ?? 0) === 100.0)
                            @php($isAutoRuleSingle = !empty($autoType) && (string) $autoType === (string) $row->assessor_type && (float) ($row->weight ?? 0) === 100.0)
                            @php($isAuto100 = $isAutoSingleLine || $isAutoRuleSingle)
                            @php($editable = in_array($st, ['draft', 'rejected'], true) && !$isAuto100)

                            @if($editable)
                                <div class="inline-flex items-center justify-end gap-2">
                                    <x-ui.input
                                        type="number"
                                        step="1"
                                        min="0"
                                        max="100"
                                        name="weights[{{ (int) $row->id }}]"
                                        :value="$row->weight === null ? '' : number_format((float) $row->weight, 0, '.', '')"
                                        :preserve-old="false"
                                        class="h-9 w-24 max-w-[110px] text-right rw-weight"
                                        onkeydown="if(event.key==='Enter'||event.key==='NumpadEnter'){event.preventDefault();return false;}"
                                    />
                                </div>
                            @else
                                <div class="inline-flex items-center justify-end gap-2">
                                    @if($isAuto100)
                                        <x-ui.input type="text" value="100 (otomatis)" :preserve-old="false" disabled class="h-9 w-28 max-w-[140px] text-right bg-slate-100 text-slate-500 border-slate-200" />
                                    @else
                                        <x-ui.input type="text" :value="$row->weight === null ? '-' : number_format((float) $row->weight, 0, '.', '')" :preserve-old="false" disabled class="h-9 w-24 max-w-[110px] text-right bg-slate-100 text-slate-500 border-slate-200" />
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
                                @if(!empty($row->decided_note))
                                    <div class="mt-2">
                                        <button type="button" class="text-xs text-slate-600 underline hover:text-slate-800" x-on:click="$dispatch('open-modal', 'rw-note-{{ (int) $row->id }}')">Lihat komentar</button>
                                    </div>

                                    <x-modal name="rw-note-{{ (int) $row->id }}" :show="false" maxWidth="lg">
                                        <div class="p-6">
                                            <div class="flex items-start justify-between gap-3">
                                                <h2 class="text-lg font-semibold text-slate-800">Komentar Penolakan</h2>
                                                <button type="button" class="text-slate-400 hover:text-slate-600" x-on:click="$dispatch('close-modal', 'rw-note-{{ (int) $row->id }}')">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                            </div>
                                            <div class="mt-3 text-sm text-slate-700 whitespace-pre-wrap">{{ $row->decided_note }}</div>
                                            <div class="mt-5 flex justify-end">
                                                <x-ui.button type="button" variant="outline" x-on:click="$dispatch('close-modal', 'rw-note-{{ (int) $row->id }}')">Tutup</x-ui.button>
                                            </div>
                                        </div>
                                    </x-modal>
                                @endif
                            @else
                                <span class="px-2 py-1 rounded text-xs bg-slate-100 text-slate-700">Draft</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-10 text-center text-sm text-slate-500">Belum ada data.</td>
                    </tr>
                @endforelse
                </x-ui.table>
            </form>
        </div>

        <div>
            {{ $itemsWorking->links() }}
        </div>

        <div class="space-y-3 mt-6">
            <h4 class="text-sm font-semibold text-slate-700">Riwayat</h4>
            <x-ui.table min-width="960px">
                <x-slot name="head">
                    <tr>
                        <th class="px-6 py-4 text-left">Periode</th>
                        <th class="px-6 py-4 text-left">Kriteria</th>
                        <th class="px-6 py-4 text-left">Profesi</th>
                        <th class="px-6 py-4 text-left">Tipe Penilai</th>
                        <th class="px-6 py-4 text-right whitespace-nowrap">
                            Bobot
                            <i class="fa-solid fa-percent ml-1 text-slate-400" aria-hidden="true"></i>
                            <span class="inline-block ml-1 text-amber-600 cursor-help" title="Bobot maksimal 100%.">!</span>
                        </th>
                        <th class="px-6 py-4 text-left">Status</th>
                    </tr>
                </x-slot>

                @forelse($itemsHistory as $row)
                    @php($st = $row->status?->value ?? (string) $row->status)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4">{{ $row->period?->name ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $row->criteria?->name ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $row->assesseeProfession?->name ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $row->assessor_label }}</td>
                        <td class="px-6 py-4 text-right">{{ $row->weight === null ? '-' : number_format((float) $row->weight, 0) }}</td>
                        <td class="px-6 py-4">
                            @if ($st === 'archived')
                                <span class="px-2 py-1 rounded text-xs bg-slate-100 text-slate-700">Arsip</span>
                            @elseif($st === 'active')
                                <span class="px-2 py-1 rounded text-xs bg-emerald-100 text-emerald-700">Aktif</span>
                            @elseif($st === 'pending')
                                <span class="px-2 py-1 rounded text-xs bg-amber-100 text-amber-700">Pending</span>
                            @elseif($st === 'rejected')
                                <span class="px-2 py-1 rounded text-xs bg-rose-100 text-rose-700">Ditolak</span>
                                @if(!empty($row->decided_note))
                                    <div class="mt-2">
                                        <button type="button" class="text-xs text-slate-600 underline hover:text-slate-800" x-on:click="$dispatch('open-modal', 'rw-note-hist-{{ (int) $row->id }}')">Lihat komentar</button>
                                    </div>

                                    <x-modal name="rw-note-hist-{{ (int) $row->id }}" :show="false" maxWidth="lg">
                                        <div class="p-6">
                                            <div class="flex items-start justify-between gap-3">
                                                <h2 class="text-lg font-semibold text-slate-800">Komentar Penolakan</h2>
                                                <button type="button" class="text-slate-400 hover:text-slate-600" x-on:click="$dispatch('close-modal', 'rw-note-hist-{{ (int) $row->id }}')">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                            </div>
                                            <div class="mt-3 text-sm text-slate-700 whitespace-pre-wrap">{{ $row->decided_note }}</div>
                                            <div class="mt-5 flex justify-end">
                                                <x-ui.button type="button" variant="outline" x-on:click="$dispatch('close-modal', 'rw-note-hist-{{ (int) $row->id }}')">Tutup</x-ui.button>
                                            </div>
                                        </div>
                                    </x-modal>
                                @endif
                            @else
                                <span class="px-2 py-1 rounded text-xs bg-slate-100 text-slate-700">Draft</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500">Belum ada data.</td>
                    </tr>
                @endforelse
            </x-ui.table>
        </div>

        <div>
            {{ $itemsHistory->links() }}
        </div>

        <script>
            (function () {
                const storageKey = 'help.hide.kepala_unit.rater_weights';

                window.__closeRwHelpModal = function () {
                    const cb = document.getElementById('rwHelpDontShow');
                    if (cb && cb.checked) {
                        localStorage.setItem(storageKey, '1');
                    } else {
                        localStorage.removeItem(storageKey);
                    }
                    window.dispatchEvent(new CustomEvent('close-modal', { detail: 'help-rater-weights' }));
                };

                document.addEventListener('DOMContentLoaded', function () {
                    try {
                        if (localStorage.getItem(storageKey) !== '1') {
                            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'help-rater-weights' }));
                        }
                    } catch (e) {
                        // ignore
                    }
                });
            })();
        </script>
    </div>
</x-app-layout>
