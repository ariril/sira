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
                        <label class="block text-sm font-medium text-slate-600 mb-1">Periode</label>
                        <x-ui.select name="period_id" :options="$periods->pluck('name','id')" :value="$periodId" placeholder="(Semua)" />
                    </div>
                </div>
                <div class="mt-4 flex justify-end gap-3">
                    <a href="{{ route('kepala_unit.unit-criteria-weights.index') }}" class="inline-flex items-center gap-2 h-10 px-4 rounded-xl text-[15px] font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50">
                        <i class="fa-solid fa-rotate-left"></i> Reset
                    </a>
                    <x-ui.button type="submit" class="h-10 px-4">Terapkan</x-ui.button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <h3 class="text-slate-800 font-semibold mb-4">Tambah Bobot (Draft)</h3>
            <form method="POST" action="{{ route('kepala_unit.unit-criteria-weights.store') }}" class="grid md:grid-cols-12 gap-4 items-end">
                @csrf
                <div class="md:col-span-5">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Kriteria</label>
                    <x-ui.select name="performance_criteria_id" :options="$criteria->pluck('name','id')" placeholder="Pilih kriteria" />
                </div>
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Periode</label>
                    <x-ui.select name="assessment_period_id" :options="$periods->pluck('name','id')" :value="$periodId" placeholder="(Opsional)" />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Bobot</label>
                    <x-ui.input type="number" step="0.01" min="0" max="100" name="weight" placeholder="0-100" />
                </div>
                <div class="md:col-span-2">
                    <x-ui.button type="submit" class="h-10 w-full">Tambah</x-ui.button>
                </div>
            </form>
        </div>

        {{-- TABLE --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <table class="min-w-full">
                <thead class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-6 py-4 text-left">Kriteria</th>
                        <th class="px-6 py-4 text-left">Tipe</th>
                        <th class="px-6 py-4 text-left">Periode</th>
                        <th class="px-6 py-4 text-left">Status</th>
                        <th class="px-6 py-4 text-right">Bobot</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
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
                                    <x-ui.button type="submit" class="h-9 px-3 text-xs" :disabled="in_array($st,['pending','active'])">Simpan</x-ui.button>
                                </form>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="inline-flex gap-2">
                                    <form method="POST" action="{{ route('kepala_unit.unit_criteria_weights.submit', $it->id) }}" onsubmit="return confirm('Ajukan untuk persetujuan?')">
                                        @csrf
                                        <input type="hidden" name="unit_head_note" value="Diajukan kepala unit">
                                        <x-ui.button type="submit" variant="success" class="h-9 px-3 text-xs" :disabled="in_array($st,['pending','active'])">Ajukan</x-ui.button>
                                    </form>
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
                </tbody>
            </table>
        </div>

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
