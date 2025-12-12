<x-app-layout title="Bobot Kriteria Unit">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold">Bobot Kriteria Unit</h1>
    </x-slot>

    <div class="container-px py-6 space-y-6">
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
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-slate-800 font-semibold">Tambah Bobot (Draft){{ $activePeriod ? ' - Periode '.$activePeriod->name : '' }}</h3>
                <a href="{{ route('kepala_unit.criteria_proposals.index') }}" class="text-amber-700 hover:underline text-sm">Usulkan kriteria baru</a>
            </div>
            @if(session('danger'))
                <div class="mb-4 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm">
                    {{ session('danger') }}
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
                        <form method="POST" action="{{ route('kepala_unit.unit_criteria_weights.submit_all') }}">
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
                    <x-ui.input type="number" step="0.01" min="0" max="100" name="weight" placeholder="0-100" id="ucwWeight" required />
                </div>
                <div class="md:col-span-2">
                    <x-ui.button type="submit" variant="orange" class="h-10 w-full" id="ucwAddBtn">Tambah</x-ui.button>
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
                        <th class="px-6 py-4 text-right whitespace-nowrap">Bobot</th>
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
                                <form method="POST" action="{{ route('kepala_unit.unit-criteria-weights.update', $it->id) }}" class="inline-flex items-center gap-2">
                                    @csrf
                                    @method('PUT')
                                    @php($weightDisplay = number_format((float) $it->weight, 0))
                                    <x-ui.input type="number" step="0.01" min="0" max="100" name="weight" :value="$weightDisplay" class="h-9 w-24 max-w-[96px] text-right" />
                                    <x-ui.button type="submit" variant="orange" class="h-9 px-3 text-xs">Simpan</x-ui.button>
                                </form>
                            @else
                                <div class="inline-flex items-center gap-2">
                                    @php($weightDisplay = number_format((float) $it->weight, 0))
                                    <x-ui.input type="number" :value="$weightDisplay" disabled class="h-9 w-24 max-w-[96px] text-right bg-slate-100 text-slate-500 border-slate-200" />
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
                                        <x-ui.button type="submit" variant="outline" class="h-9 px-3 text-xs">Hapus</x-ui.button>
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
                        <th class="px-6 py-4 text-right whitespace-nowrap">Bobot</th>
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
                            <x-ui.input type="number" :value="$weightDisplay" disabled class="h-9 w-24 max-w-[96px] text-right bg-slate-100 text-slate-500 border-slate-200" />
                        </td>
                        <td class="px-6 py-4 text-right"><span class="text-xs text-slate-400">—</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-8 text-center text-slate-500">Belum ada riwayat.</td></tr>
                @endforelse
            </x-ui.table>
        </div>
    </div>
</x-app-layout>
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
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
    });
</script>
@endpush
