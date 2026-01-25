<x-app-layout title="Bobot Kriteria {{ $unitName ?? 'Unit' }}">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold">Bobot Kriteria {{ $unitName ?? 'Unit' }}</h1>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <x-modal name="help-unit-criteria-weights" :show="false" maxWidth="2xl">
            <div class="p-6">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="text-lg font-semibold text-slate-800">Informasi Penggunaan</h2>
                    <button type="button" class="text-slate-400 hover:text-slate-600" x-on:click="$dispatch('close-modal', 'help-unit-criteria-weights')">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="mt-3 text-sm text-slate-700 space-y-2">
                    <div>Modul ini digunakan untuk menyusun Bobot Kriteria Unit pada periode aktif.</div>
                    <ul class="list-disc pl-5 space-y-1">
                        <li>Tambah bobot sebagai <strong>Draft</strong>, lalu sesuaikan hingga total bobot siap diajukan.</li>
                        <li>Tombol <strong>Ajukan Semua</strong> digunakan untuk mengirim seluruh draft (sesuai kebutuhan sisa bobot) menjadi <strong>Pending</strong>.</li>
                        <li>Tombol <strong>Cek</strong> menyimpan perubahan bobot draft pada tabel sebagai <strong>Draft</strong> sebelum Anda pindah halaman / sebelum Ajukan Semua.</li>
                        <li>Bobot berstatus <strong>Ditolak</strong> ditampilkan di bagian <strong>Riwayat</strong> sebagai arsip (read-only).</li>
                        <li>Tombol <strong>Salin periode sebelumnya</strong> menyalin bobot periode sebelumnya menjadi draft periode aktif.</li>
                        <li>Tombol <strong>Salin Bobot Ditolak</strong> menyalin batch penolakan terakhir pada periode aktif menjadi draft.</li>
                    </ul>
                </div>

                <div class="mt-5 flex items-center justify-between gap-3">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700 select-none">
                        <input id="ucwHelpDontShow" type="checkbox" class="rounded border-slate-300 text-slate-700 focus:ring-slate-300" />
                        Jangan tampilkan lagi
                    </label>
                    <x-ui.button type="button" variant="orange" class="h-10 px-6" x-on:click="window.__closeUcwHelpModal()">OK</x-ui.button>
                </div>
            </div>
        </x-modal>

        {{-- FILTERS & ADD --}}
        <div class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <form method="GET">
                <div class="grid gap-5 md:grid-cols-12">
                    <div class="md:col-span-6">
                        <label class="block text-sm font-semibold text-slate-700 mb-1">
                            Filter Periode
                            <span class="inline-block ml-1 text-amber-600 cursor-help" title="Filter ini menampilkan daftar bobot untuk periode tertentu. Tidak memengaruhi periode pada saat Anda menambah bobot (selalu mengikuti periode aktif).">!</span>
                        </label>
                        <x-ui.select name="period_id" :options="$periods->pluck('name','id')" :value="$periodId" placeholder="(Semua)" />
                    </div>
                </div>
                <div class="mt-4 flex justify-end gap-3">
                    <a href="{{ route('kepala_unit.unit-criteria-weights.index') }}" class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-200 transition-colors">
                        <i class="fa-solid fa-rotate-left"></i> Reset
                    </a>
                    <button type="submit" class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm text-base">
                        <i class="fa-solid fa-filter"></i> Terapkan
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between mb-4">
                <h3 class="text-slate-800 font-semibold">Tambah Bobot (Draft){{ $activePeriod ? ' - Periode '.$activePeriod->name : '' }}</h3>
                <div class="flex items-center gap-3">
                    @if($activePeriod && $previousPeriod && ($canCopyPrevious ?? false))
                        <form method="POST" action="{{ route('kepala_unit.unit_criteria_weights.copy_previous') }}" class="inline-flex">
                            @csrf
                            <x-ui.button type="submit" variant="outline" class="h-10 px-4 text-sm" onclick="return confirm('Salin seluruh bobot aktif periode sebelumnya menjadi draft periode aktif?')">
                                <i class="fa-solid fa-copy mr-2"></i> Salin periode sebelumnya
                            </x-ui.button>
                        </form>
                    @endif
                    @if($activePeriod && ($rejectedCountActive ?? 0) > 0)
                        <form method="POST" action="{{ route('kepala_unit.unit_criteria_weights.copy_rejected') }}" class="inline-flex">
                            @csrf
                            <x-ui.button type="submit" variant="outline" class="h-10 px-4 text-sm" onclick="return confirm('Salin bobot ditolak terakhir menjadi draft periode aktif?')">
                                <i class="fa-solid fa-clone mr-2"></i> Salin bobot ditolak
                            </x-ui.button>
                        </form>
                    @endif
                    <a href="{{ route('kepala_unit.criteria_proposals.index') }}" class="text-amber-700 hover:underline text-sm">Usulkan kriteria baru</a>
                </div>
            </div>
            @if(session('danger'))
                <div class="mb-4 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm">
                    {{ session('danger') }}
                </div>
            @elseif(session('warning_360_message'))
                <div class="mb-4 p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm flex items-center justify-between gap-3">
                    <span>{{ session('warning_360_message') }}</span>
                    @if(session('warning_360_url'))
                        <x-ui.button as="a" href="{{ session('warning_360_url') }}" variant="outline" class="h-10 px-4">Lihat</x-ui.button>
                    @endif
                </div>
            @elseif(($pendingCount ?? 0) > 0)
                <div class="mb-4 p-4 rounded-xl bg-blue-50 border border-blue-200 text-blue-800 text-sm">
                    Pengajuan bobot sedang menunggu persetujuan Kepala Poliklinik.
                    <span class="font-semibold">{{ number_format($pendingTotal ?? 0, 2) }}%</span>
                    telah diajukan.
                </div>
            @else
                @if(($usingFallback ?? false))
                    <div class="mb-4 text-sm text-slate-600">
                        Bobot periode ini belum diatur. Sistem menampilkan bobot dari periode sebelumnya.
                    </div>
                @endif
                @php($roundedDraft = (int) round($currentTotal))
                @php($committed = (float) ($committedTotal ?? 0))
                @php($required = max(0, (int) round($requiredTotal ?? 100)))
                @php($draftMeetsRequirement = $required > 0 && $roundedDraft === $required)
                @php($allActiveApproved = ($pendingCount ?? 0) === 0 && !($hasDraftOrRejected ?? false) && !empty($targetPeriodId) && (int) round($activeTotal ?? 0) === 100)
                @php($rejectedWorkingCount = (int) ($rejectedCountPeriod ?? 0))
                @php($hasEditableWorking = (int) $itemsWorking->whereIn('status', ['draft'])->count() > 0)

                @if($allActiveApproved)
                    <div class="mb-4 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between gap-3">
                        <span>Seluruh bobot telah disetujui dan aktif untuk periode {{ $activePeriod->name ?? '-' }}.</span>
                        <form method="POST" action="{{ route('kepala_unit.unit_criteria_weights.request_change') }}" class="flex-shrink-0" onsubmit="return confirm('Ajukan Perubahan Bobot Kriteria?\n\nApakah Anda yakin ingin mengajukan perubahan?\nBobot Penilai 360 yang terkait juga akan disinkronkan kembali menjadi DRAFT dan perlu diajukan ulang.')">
                            @csrf
                            <input type="hidden" name="period_id" value="{{ $periodId ?? $targetPeriodId }}" />
                            <x-ui.button type="submit" variant="orange" class="h-10 px-4">Ajukan Perubahan</x-ui.button>
                        </form>
                    </div>
                @elseif($required === 0)
                    <div class="mb-4 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">
                        Seluruh bobot aktif/pending telah mencapai 100%.
                    </div>
                @elseif($draftMeetsRequirement)
                    <div class="mb-4 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between">
                        <div>
                            <div>Sisa {{ number_format($required, 2) }}% siap diajukan untuk melengkapi total 100%.</div>
                            @if($rejectedWorkingCount > 0)
                                <div class="mt-1 text-xs text-emerald-700">Ada pengajuan yang ditolak. Lihat detailnya di bagian <strong>Riwayat</strong>, lalu buat draft baru dan klik <strong>Ajukan Semua</strong>.</div>
                            @endif
                        </div>
                        <div class="flex items-center gap-3">
                            @if($hasEditableWorking)
                                <x-ui.button variant="outline" type="button" class="h-10 px-4 ucw-cek-btn">Cek</x-ui.button>
                            @endif
                            <form method="POST" action="{{ route('kepala_unit.unit_criteria_weights.submit_all') }}" id="ucwSubmitAllForm" class="inline-flex">
                                @csrf
                                <input type="hidden" name="period_id" value="{{ $periodId ?? $targetPeriodId }}" />
                                <x-ui.button type="submit" variant="orange" class="h-10 px-6">Ajukan Semua</x-ui.button>
                            </form>
                        </div>
                    </div>
                @else
                    <div class="mb-4 p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm flex items-center justify-between gap-3">
                        <div>
                            Total bobot aktif/pending saat ini <span class="font-semibold">{{ number_format($committed,2) }}%</span>. Draf yang siap diajukan baru <span class="font-semibold">{{ number_format($currentTotal,2) }}%</span>.
                            Butuh <strong>{{ number_format(max(0, $required - $roundedDraft), 2) }}%</strong> lagi agar dapat diajukan.
                            @if($rejectedWorkingCount > 0)
                                <div class="mt-2 text-xs text-amber-700">Ada pengajuan yang ditolak. Lihat detailnya di bagian <strong>Riwayat</strong>, lalu buat draft baru untuk revisi.</div>
                            @endif
                        </div>
                        @if($hasEditableWorking)
                            <div class="flex items-center gap-3">
                                <x-ui.button variant="outline" type="button" class="h-10 px-4 ucw-cek-btn">Cek</x-ui.button>
                            </div>
                        @endif
                    </div>
                @endif
            @endif

            {{-- Bulk cek form (kept separate to avoid nested forms inside the table) --}}
            <form id="ucwBulkForm" method="POST" action="{{ route('kepala_unit.unit_criteria_weights.cek') }}" class="hidden">
                @csrf
                <input type="hidden" name="period_id" value="{{ $periodId ?? $targetPeriodId }}" />
            </form>
            <form method="POST" action="{{ route('kepala_unit.unit-criteria-weights.store') }}" class="grid md:grid-cols-12 gap-4 items-end" id="ucwAddForm">
                @csrf
                <div class="md:col-span-6">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Kriteria</label>
                    @php($critOptions = $criteria->mapWithKeys(function($c){
                        $label = $c->name . (isset($c->suggested_weight) && $c->suggested_weight !== null ? ' (saran: '.number_format((float)$c->suggested_weight,2).'%)' : '');
                        return [$c->id => $label];
                    }))
                    <x-ui.select name="performance_criteria_id" :options="$critOptions" placeholder="Pilih kriteria" id="ucwCrit" required />
                </div>
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Bobot</label>
                    <x-ui.input type="number" step="1" min="0" max="100" name="weight" placeholder="0-100" id="ucwWeight" required />
                </div>
                <div class="md:col-span-2">
                    <x-ui.button type="submit" variant="outline" class="h-10 w-full" id="ucwAddBtn">Tambah</x-ui.button>
                </div>
                @if(!$activePeriod)
                    <div class="md:col-span-12 text-sm text-rose-700">Tidak ada periode aktif. Hubungi Admin RS untuk mengaktifkan periode.</div>
                @endif
            </form>
        </div>

        {{-- TABLE: Draft/Pending/Active (editable where allowed) --}}
        <div class="space-y-3">
            <h4 class="text-sm font-semibold text-slate-700">Draft & Pengajuan Berjalan</h4>
            <x-ui.table min-width="1040px">
                <x-slot name="head">
                    <tr>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Kriteria</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Tipe
                            <span class="inline-block ml-1 text-amber-600 cursor-help" title="Tipe Benefit: nilai lebih tinggi semakin baik. Tipe Cost: nilai lebih rendah semakin baik.">!</span>
                        </th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Periode</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Status</th>
                        <th class="px-6 py-4 text-right whitespace-nowrap">
                            Bobot
                            <i class="fa-solid fa-percent ml-1 text-slate-400" aria-hidden="true"></i>
                            <span class="inline-block ml-1 text-amber-600 cursor-help" title="Bobot maksimal 100%.">!</span>
                        </th>
                        <th class="px-6 py-4 text-right whitespace-nowrap">Aksi</th>
                    </tr>
                </x-slot>
                @forelse($itemsWorking as $it)
                    @php($st = (string)($it->status ?? 'draft'))
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4">{{ $it->criteria_name }}</td>
                        <td class="px-6 py-4">{{ $it->criteria_type }}</td>
                        <td class="px-6 py-4">{{ $it->period_name ?? '-' }}</td>
                        <td class="px-6 py-4">
                            @if($st==='active')
                                <span class="px-2 py-1 rounded text-xs bg-emerald-100 text-emerald-700">Aktif</span>
                            @elseif($st==='pending')
                                <span class="px-2 py-1 rounded text-xs bg-amber-100 text-amber-700">Pending</span>
                            @elseif($st==='rejected')
                                <button type="button" class="px-2 py-1 rounded text-xs bg-rose-100 text-rose-700 hover:bg-rose-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-200 cursor-pointer" title="Klik untuk lihat komentar penolakan" onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'ucw-note-{{ (int) $it->id }}' }))">Ditolak</button>
                            @else
                                <span class="px-2 py-1 rounded text-xs bg-slate-100 text-slate-700">Draft</span>
                            @endif
                        </td>
                        <td class="px-6 py-2 text-right">
                            @php($editable = in_array($st,['draft']))
                            @if($editable)
                                @php($weightDisplay = number_format((float) $it->weight, 0))
                                <div class="inline-flex items-center gap-2">
                                    <x-ui.input
                                        type="number"
                                        step="1"
                                        min="0"
                                        max="100"
                                        name="weights[{{ (int) $it->id }}]"
                                        :value="$weightDisplay"
                                        :preserve-old="false"
                                        class="h-9 w-24 max-w-[96px] text-right ucw-weight"
                                    />
                                </div>
                            @else
                                <div class="inline-flex items-center gap-2">
                                    @php($weightDisplay = number_format((float) $it->weight, 0))
                                    <x-ui.input type="number" :value="$weightDisplay" :preserve-old="false" disabled class="h-9 w-24 max-w-[96px] text-right bg-slate-100 text-slate-500 border-slate-200" />
                                    <span class="text-xs text-slate-400">Terkunci</span>
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            @if($editable)
                                <div class="inline-flex gap-2">
                                    {{-- Hilangkan tombol Ajukan per baris; fokus pengajuan massal --}}
                                    <form method="POST" action="{{ route('kepala_unit.unit-criteria-weights.destroy', $it->id) }}" onsubmit="return confirm('Hapus bobot ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.icon-button icon="fa-trash" variant="danger" type="submit" title="Hapus" />
                                    </form> 
                                </div>
                            @else
                                <span class="text-xs text-slate-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-8 text-center text-slate-500">Belum ada data.</td></tr>
                @endforelse
            </x-ui.table>

        </div>

        {{-- TABLE: History (Archived + Rejected) --}}
        <div class="space-y-3 mt-6">
            <h4 class="text-sm font-semibold text-slate-700">Riwayat</h4>
            <x-ui.table min-width="1040px">
                <x-slot name="head">
                    <tr>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Kriteria</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Tipe</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Periode</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Status</th>
                        <th class="px-6 py-4 text-right whitespace-nowrap">
                            Bobot
                            <i class="fa-solid fa-percent ml-1 text-slate-400" aria-hidden="true"></i>
                            <span class="inline-block ml-1 text-amber-600 cursor-help" title="Bobot maksimal 100%.">!</span>
                        </th>
                    </tr>
                </x-slot>
                @forelse($itemsHistory as $it)
                    @php($histStatus = (string) ($it->status ?? 'archived'))
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4">{{ $it->criteria_name }}</td>
                        <td class="px-6 py-4">{{ $it->criteria_type }}</td>
                        <td class="px-6 py-4">{{ $it->period_name ?? '-' }}</td>
                        <td class="px-6 py-4">
                            @if($histStatus === 'rejected')
                                <button type="button" class="px-2 py-1 rounded text-xs bg-rose-100 text-rose-700 hover:bg-rose-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-200 cursor-pointer" title="Klik untuk lihat komentar penolakan" onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'ucw-note-hist-{{ (int) $it->id }}' }))">Ditolak</button>
                            @else
                                <span class="px-2 py-1 rounded text-xs bg-slate-200 text-slate-600">Arsip</span>
                            @endif
                        </td>
                        <td class="px-6 py-2 text-right">
                            @php($weightDisplay = number_format((float) $it->weight, 0))
                            <x-ui.input type="number" :value="$weightDisplay" :preserve-old="false" disabled class="h-9 w-24 max-w-[96px] text-right bg-slate-100 text-slate-500 border-slate-200" />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-6 py-8 text-center text-slate-500">Belum ada riwayat.</td></tr>
                @endforelse
            </x-ui.table>
        </div>

        {{-- Render history rejection modals --}}
        @foreach($itemsHistory as $it)
            @if((string) ($it->status ?? '') === 'rejected')
                <x-modal name="ucw-note-hist-{{ (int) $it->id }}" :show="false" maxWidth="lg">
                    <div class="p-6">
                        <div class="flex items-start justify-between gap-3">
                            <h2 class="text-lg font-semibold text-slate-800">Komentar Penolakan</h2>
                            <button type="button" class="text-slate-400 hover:text-slate-600" onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'ucw-note-hist-{{ (int) $it->id }}' }))">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                        <div class="mt-1 text-xs text-slate-500">Riwayat ini bersifat arsip dan tidak bisa diedit.</div>
                        @php($note = trim((string) ($it->decided_note ?? '')))
                        <div class="mt-3 text-sm text-slate-700 whitespace-pre-wrap">{{ $note !== '' ? $note : 'Tidak ada komentar penolakan.' }}</div>
                        <div class="mt-5 flex justify-end">
                            <x-ui.button type="button" variant="outline" onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'ucw-note-hist-{{ (int) $it->id }}' }))">Tutup</x-ui.button>
                        </div>
                    </div>
                </x-modal>
            @endif
        @endforeach

    </div>

    <script>
        (function () {
            const storageKey = 'help.hide.kepala_unit.unit_criteria_weights';

            window.__closeUcwHelpModal = function () {
                const cb = document.getElementById('ucwHelpDontShow');
                if (cb && cb.checked) {
                    localStorage.setItem(storageKey, '1');
                } else {
                    localStorage.removeItem(storageKey);
                }
                window.dispatchEvent(new CustomEvent('close-modal', { detail: 'help-unit-criteria-weights' }));
            };

            document.addEventListener('DOMContentLoaded', function () {
                try {
                    if (localStorage.getItem(storageKey) !== '1') {
                        window.dispatchEvent(new CustomEvent('open-modal', { detail: 'help-unit-criteria-weights' }));
                    }
                } catch (e) {
                    // ignore
                }
            });
        })();
    </script>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
        const csrf = '{{ csrf_token() }}';

        const crit = document.getElementById('ucwCrit');
        const weight = document.getElementById('ucwWeight');
        const btn = document.getElementById('ucwAddBtn');
        if (!crit || !weight || !btn) return;

        const toggle = () => {
            const hasCrit = Boolean((crit.value || '').trim());
            const weightStr = (weight.value || '').trim();
            const wVal = parseFloat(weightStr);
            const hasWeight = weightStr !== '' && !Number.isNaN(wVal);
            btn.disabled = !(hasCrit && hasWeight);
        };

        crit.addEventListener('change', toggle);
        weight.addEventListener('input', toggle);
        weight.addEventListener('change', toggle);

        toggle();
        requestAnimationFrame(toggle);

        // "Cek" should refresh the page (server-side check), like the 360 module.
        const bulkForm = document.getElementById('ucwBulkForm');
        const cekButtons = Array.from(document.querySelectorAll('.ucw-cek-btn'));
        const clearDynamicInputs = () => {
            if (!bulkForm) return;
            Array.from(bulkForm.querySelectorAll('input[data-ucw-dynamic="1"]')).forEach((el) => el.remove());
        };

        const clampIntBulk = (v) => {
            const n = parseInt(String(v ?? '').trim(), 10);
            if (Number.isNaN(n)) return 0;
            return Math.min(100, Math.max(0, n));
        };

        const submitBulkCek = async () => {
            if (!bulkForm) return;

            // Ensure latest values are saved to DB via bulkCheck.
            clearDynamicInputs();

            const inputs = Array.from(document.querySelectorAll('input.ucw-weight'));
            if (!inputs.length) {
                return;
            }

            inputs.forEach((input) => {
                const name = input.getAttribute('name');
                if (!name) return;
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = name;
                hidden.value = String(clampIntBulk(input.value));
                hidden.setAttribute('data-ucw-dynamic', '1');
                bulkForm.appendChild(hidden);
            });

            bulkForm.submit();
        };

        cekButtons.forEach((btn) => {
            btn.addEventListener('click', async () => {
                cekButtons.forEach((b) => (b.disabled = true));
                try {
                    await submitBulkCek();
                } finally {
                    // If submit is blocked for some reason, re-enable.
                    setTimeout(() => cekButtons.forEach((b) => (b.disabled = false)), 500);
                }
            });
        });
        });
    </script>
    @endpush
</x-app-layout>
