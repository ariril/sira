<x-app-layout title="Detail Bobot Kriteria — Kepala Poliklinik">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Detail Bobot Kriteria — {{ $unit->name ?? '-' }}</h1>
            <x-ui.button as="a" href="{{ route('kepala_poliklinik.unit_criteria_weights.units') }}" variant="violet" class="h-12 px-6 text-base">Kembali</x-ui.button>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <form method="GET" class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <div class="grid gap-5 md:grid-cols-12">
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
                    <x-ui.select name="status" :options="['all'=>'(Semua)','draft'=>'Draft','pending'=>'Pending','active'=>'Active','rejected'=>'Rejected']" :value="$filters['status'] ?? 'all'" />
                </div>
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Periode</label>
                    <x-ui.select name="period_id" :options="$periods->pluck('name','id')" :value="$filters['period_id'] ?? null" placeholder="(Semua)" />
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ url()->current() }}" class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </a>
                <button type="submit" class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm">
                    <i class="fa-solid fa-filter"></i> Terapkan
                </button>
            </div>
        </form>

        <x-ui.table min-width="960px">
            <x-slot name="head">
                <tr>
                    <th class="px-6 py-4 text-left">Kriteria</th>
                    <th class="px-6 py-4 text-left">Tipe</th>
                    <th class="px-6 py-4 text-left">Periode</th>
                    <th class="px-6 py-4 text-left">Status</th>
                    <th class="px-6 py-4 text-right">Bobot</th>
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
                            <span class="px-2 py-1 rounded text-xs bg-emerald-100 text-emerald-700">Active</span>
                        @elseif($st==='pending')
                            <span class="px-2 py-1 rounded text-xs bg-amber-100 text-amber-700">Pending</span>
                        @elseif($st==='rejected')
                            <span class="px-2 py-1 rounded text-xs bg-rose-100 text-rose-700">Rejected</span>
                        @else
                            <span class="px-2 py-1 rounded text-xs bg-slate-100 text-slate-700">Draft</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right">{{ number_format((float)($it->weight ?? 0),2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-6 py-8 text-center text-slate-500">Tidak ada data.</td></tr>
            @endforelse
        </x-ui.table>

        <div class="pt-2 flex justify-end">{{ $items->links() }}</div>
    </div>
</x-app-layout>
