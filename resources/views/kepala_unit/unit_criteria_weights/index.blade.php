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
                    <a href="{{ route('kepala_unit.unit-criteria-weights.index') }}" class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 text-base">
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
            @php($roundedTotal = (int) round($currentTotal))
            @if(session('danger'))
                <div class="mb-4 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm">
                    {{ session('danger') }}
                </div>
            @elseif($roundedTotal < 100)
                <div class="mb-4 p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm">
                    Total bobot draft saat ini <span class="font-semibold">{{ number_format($currentTotal,2) }}%</span>. Diperlukan tepat <strong>100%</strong> sebelum pengajuan massal.
                </div>
            @elseif($roundedTotal === 100)
                <div class="mb-4 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between">
                    <span>Bobot lengkap 100%. Silakan ajukan untuk persetujuan.</span>
                    <form method="POST" action="{{ route('kepala_unit.unit_criteria_weights.submit_all') }}">
                        @csrf
                        <input type="hidden" name="period_id" value="{{ $periodId }}" />
                        <x-ui.button type="submit" variant="orange" class="h-10 px-6">Ajukan Semua</x-ui.button>
                    </form>
                </div>
            @endif
            <form method="POST" action="{{ route('kepala_unit.unit-criteria-weights.store') }}" class="grid md:grid-cols-12 gap-4 items-end">
                @csrf
                <div class="md:col-span-6">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Kriteria</label>
                    @php($critOptions = $criteria->mapWithKeys(function($c){
                        $label = $c->name . (isset($c->suggested_weight) && $c->suggested_weight !== null ? ' (saran: '.number_format((float)$c->suggested_weight,2).'%)' : '');
                        return [$c->id => $label];
                    }))
                    <x-ui.select name="performance_criteria_id" :options="$critOptions" placeholder="Pilih kriteria" />
                </div>
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Bobot</label>
                    <x-ui.input type="number" step="0.01" min="0" max="100" name="weight" placeholder="0-100" />
                </div>
                <div class="md:col-span-2">
                    <x-ui.button type="submit" variant="orange" class="h-10 w-full">Tambah</x-ui.button>
                </div>
                @if(!$activePeriod)
                    <div class="md:col-span-12 text-sm text-rose-700">Tidak ada periode aktif. Hubungi Admin RS untuk mengaktifkan periode.</div>
                @endif
            </form>
        </div>

        {{-- TABLE --}}
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
            @forelse($items as $it)
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-4">{{ $it->criteria_name }}</td>
                    <td class="px-6 py-4">{{ $it->criteria_type }}</td>
                    <td class="px-6 py-4">{{ $it->period_name ?? '-' }}</td>
                    <td class="px-6 py-4">
                        @php($st = (string)($it->status ?? 'draft'))
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
                        <form method="POST" action="{{ route('kepala_unit.unit-criteria-weights.update', $it->id) }}" class="inline-flex items-center gap-2">
                            @csrf
                            @method('PUT')
                            <x-ui.input type="number" step="0.01" min="0" max="100" name="weight" :value="$it->weight" class="h-9 w-28 text-right" />
                            <x-ui.button type="submit" variant="orange" class="h-9 px-3 text-xs" :disabled="in_array($st,['pending','active'])">Simpan</x-ui.button>
                        </form>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="inline-flex gap-2">
                            {{-- Hilangkan tombol Ajukan per baris; fokus pengajuan massal --}}
                            <form method="POST" action="{{ route('kepala_unit.unit-criteria-weights.destroy', $it->id) }}" onsubmit="return confirm('Hapus bobot ini?')">
                                @csrf
                                @method('DELETE')
                                <x-ui.button type="submit" variant="outline" class="h-9 px-3 text-xs" :disabled="in_array($st,['pending','active'])">Hapus</x-ui.button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-6 py-8 text-center text-slate-500">Belum ada data.</td></tr>
            @endforelse
        </x-ui.table>

        {{-- FOOTER PAGINATION --}}
        <div class="pt-2 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="text-sm text-slate-600">
                Menampilkan
                <span class="font-medium text-slate-800">{{ $items->firstItem() ?? 0 }}</span>
                -
                <span class="font-medium text-slate-800">{{ $items->lastItem() ?? 0 }}</span>
                dari
                <span class="font-medium text-slate-800">{{ $items->total() }}</span>
                data
            </div>
            <div>{{ $items->links() }}</div>
        </div>
    </div>
</x-app-layout>
