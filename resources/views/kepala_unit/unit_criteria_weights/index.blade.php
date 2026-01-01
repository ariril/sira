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
                        <li>Tombol <strong>Ajukan Semua</strong> digunakan untuk mengirim seluruh draft/rejected (sesuai kebutuhan sisa bobot) menjadi <strong>Pending</strong>.</li>
                        <li>Tombol <strong>Salin periode sebelumnya</strong> menyalin bobot periode sebelumnya menjadi draft periode aktif.</li>
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
                    @if($activePeriod && $previousPeriod)
                        <form method="POST" action="{{ route('kepala_unit.unit_criteria_weights.copy_previous') }}" class="inline-flex">
                            @csrf
                            <x-ui.button type="submit" variant="outline" class="h-10 px-4 text-sm" onclick="return confirm('Salin seluruh bobot aktif periode sebelumnya menjadi draft periode aktif?')">
                                <i class="fa-solid fa-copy mr-2"></i> Salin periode sebelumnya
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
                @php($roundedDraft = (int) round($currentTotal))
                @php($committed = (float) ($committedTotal ?? 0))
                @php($required = max(0, (int) round($requiredTotal ?? 100)))
                @php($draftMeetsRequirement = $required > 0 && $roundedDraft === $required)
                @php($allActiveApproved = ($pendingCount ?? 0) === 0 && !($hasDraftOrRejected ?? false) && !empty($targetPeriodId) && (int) round($activeTotal ?? 0) === 100)

                @if($allActiveApproved)
                    <div class="mb-4 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between gap-3">
                        <span>Seluruh bobot telah disetujui dan aktif untuk periode {{ $activePeriod->name ?? '-' }}.</span>
                        <form method="POST" action="{{ route('kepala_unit.unit_criteria_weights.request_change') }}" class="flex-shrink-0">
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
                        <span>Sisa {{ number_format($required, 2) }}% siap diajukan untuk melengkapi total 100%.</span>
                        <form method="POST" action="{{ route('kepala_unit.unit_criteria_weights.submit_all') }}" id="ucwSubmitAllForm">
                            @csrf
                            <input type="hidden" name="period_id" value="{{ $periodId ?? $targetPeriodId }}" />
                            <x-ui.button type="submit" variant="orange" class="h-10 px-6">Ajukan Semua</x-ui.button>
                        </form>
                    </div>
                @else
                    <div class="mb-4 p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm">
                        Total bobot aktif/pending saat ini <span class="font-semibold">{{ number_format($committed,2) }}%</span>. Draf yang siap diajukan baru <span class="font-semibold">{{ number_format($currentTotal,2) }}%</span>.
                        Butuh <strong>{{ number_format(max(0, $required - $roundedDraft), 2) }}%</strong> lagi agar dapat diajukan.
                    </div>
                @endif
            @endif
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
                                <span class="px-2 py-1 rounded text-xs bg-rose-100 text-rose-700">Ditolak</span>
                            @else
                                <span class="px-2 py-1 rounded text-xs bg-slate-100 text-slate-700">Draft</span>
                            @endif
                        </td>
                        <td class="px-6 py-2 text-right">
                            @php($editable = in_array($st,['draft','rejected']))
                            @if($editable)
                                @php($weightDisplay = number_format((float) $it->weight, 0))
                                <div class="inline-flex items-center gap-2">
                                    <x-ui.input
                                        type="number"
                                        step="1"
                                        min="0"
                                        max="100"
                                        name="weight"
                                        :value="$weightDisplay"
                                        :preserve-old="false"
                                        class="h-9 w-24 max-w-[96px] text-right ucw-weight"
                                        data-update-url="{{ route('kepala_unit.unit-criteria-weights.update', $it->id) }}"
                                        data-initial-value="{{ (int) $weightDisplay }}"
                                    />
                                    <span class="text-xs text-slate-400 ucw-save-status" aria-live="polite"></span>
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

        {{-- TABLE: History (Archived) --}}
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
                        <th class="px-6 py-4 text-right whitespace-nowrap">Aksi</th>
                    </tr>
                </x-slot>
                @forelse($itemsHistory as $it)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4">{{ $it->criteria_name }}</td>
                        <td class="px-6 py-4">{{ $it->criteria_type }}</td>
                        <td class="px-6 py-4">{{ $it->period_name ?? '-' }}</td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 rounded text-xs bg-slate-200 text-slate-600">Riwayat</span>
                        </td>
                        <td class="px-6 py-2 text-right">
                            @php($weightDisplay = number_format((float) $it->weight, 0))
                            <x-ui.input type="number" :value="$weightDisplay" :preserve-old="false" disabled class="h-9 w-24 max-w-[96px] text-right bg-slate-100 text-slate-500 border-slate-200" />
                        </td>
                        <td class="px-6 py-4 text-right"><span class="text-xs text-slate-400">—</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-8 text-center text-slate-500">Belum ada riwayat.</td></tr>
                @endforelse
            </x-ui.table>
        </div>
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
</x-app-layout>
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

        // Autosave weight edits (draft/rejected) to reduce button clutter.
        const inputs = Array.from(document.querySelectorAll('input.ucw-weight'));
        const clampInt = (v) => {
            const n = parseInt(String(v ?? '').trim(), 10);
            if (Number.isNaN(n)) return null;
            return Math.min(100, Math.max(0, n));
        };

        inputs.forEach((input) => {
            const statusEl = input.closest('.inline-flex')?.querySelector('.ucw-save-status');
            const url = input.dataset.updateUrl;
            if (!url) return;

            let timer = null;
            let lastSaved = clampInt(input.dataset.initialValue ?? input.value);

            const setStatus = (text, kind) => {
                if (!statusEl) return;
                statusEl.textContent = text || '';
                statusEl.classList.remove('text-slate-400', 'text-emerald-600', 'text-rose-600');
                if (kind === 'ok') statusEl.classList.add('text-emerald-600');
                else if (kind === 'err') statusEl.classList.add('text-rose-600');
                else statusEl.classList.add('text-slate-400');
            };

            const saveNow = async () => {
                const val = clampInt(input.value);
                if (val === null) {
                    setStatus('Isi 0–100', 'err');
                    return false;
                }

                // Normalize displayed value to integer.
                if (String(input.value) !== String(val)) {
                    input.value = String(val);
                }

                if (val === lastSaved) {
                    setStatus('', '');
                    return true;
                }

                setStatus('Menyimpan…', '');

                const fd = new FormData();
                fd.append('_method', 'PUT');
                fd.append('weight', String(val));

                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        body: fd,
                    });

                    const data = await res.json().catch(() => null);
                    if (!res.ok || !data?.ok) {
                        const msg = data?.message || 'Gagal menyimpan';
                        setStatus(msg, 'err');
                        return false;
                    }

                    lastSaved = val;
                    setStatus('Tersimpan', 'ok');
                    setTimeout(() => setStatus('', ''), 1200);
                    return true;
                } catch (e) {
                    setStatus('Gagal menyimpan', 'err');
                    return false;
                }
            };

            const schedule = () => {
                if (timer) clearTimeout(timer);
                timer = setTimeout(saveNow, 350);
            };

            // Expose saver for submit flush.
            input.__ucwSaveNow = saveNow;
            input.__ucwClearTimer = () => { if (timer) clearTimeout(timer); timer = null; };

            input.addEventListener('input', schedule);
            input.addEventListener('change', saveNow);
            input.addEventListener('blur', saveNow);
        });

        // Flush pending autosaves before submitting Ajukan Semua.
        const submitAllForm = document.getElementById('ucwSubmitAllForm');
        if (submitAllForm) {
            submitAllForm.addEventListener('submit', async (e) => {
                e.preventDefault();

                const savers = Array.from(document.querySelectorAll('input.ucw-weight'))
                    .map((el) => {
                        el.__ucwClearTimer?.();
                        return el.__ucwSaveNow?.();
                    })
                    .filter(Boolean);

                if (savers.length) {
                    const results = await Promise.allSettled(savers);
                    const ok = results.every((r) => r.status === 'fulfilled' && r.value === true);
                    if (!ok) return;
                }

                submitAllForm.submit();
            });
        }
    });
</script>
@endpush
