<x-app-layout title="Approval Bobot Kriteria">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Approval Bobot Kriteria</h1>
            <x-ui.button as="a" href="{{ route('kepala_poliklinik.unit_criteria_weights.units') }}" class="h-10 px-4 text-sm">Lihat per Unit</x-ui.button>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        {{-- FILTERS --}}
        <form method="GET" class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <div class="grid gap-5 md:grid-cols-12">
                <div class="md:col-span-6">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Cari</label>
                    <x-ui.input name="q" placeholder="Nama unit / kriteria" value="{{ $filters['q'] ?? '' }}" addonLeft="fa-magnifying-glass" />
                </div>
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
                    <x-ui.select name="status" :options="['pending'=>'Pending','active'=>'Active','rejected'=>'Rejected','draft'=>'Draft']" :value="$filters['status'] ?? 'pending'" placeholder="(Semua)" />
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('kepala_poliklinik.unit_criteria_weights.index') }}" class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </a>
                <button type="submit" class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm">
                    <i class="fa-solid fa-filter"></i> Terapkan
                </button>
            </div>
        </form>

        {{-- TABLE --}}
        <x-ui.table min-width="960px">
            <x-slot name="head">
                <tr>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Unit</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Kriteria</th>
                    <th class="px-6 py-4 text-right whitespace-nowrap">Bobot</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Status</th>
                    <th class="px-6 py-4 text-right whitespace-nowrap">Aksi</th>
                </tr>
            </x-slot>
            @forelse($items as $it)
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-4">{{ $it->unit->name ?? '-' }}</td>
                    <td class="px-6 py-4">{{ $it->performanceCriteria->name ?? '-' }}</td>
                    <td class="px-6 py-4 text-right">{{ number_format((float)($it->weight ?? 0), 2) }}</td>
                    <td class="px-6 py-4">
                        @php($status = $it->status?->value ?? (string) $it->status ?? 'draft')
                        @switch($status)
                            @case('active')
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">Active</span>
                                @break
                            @case('rejected')
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-rose-50 text-rose-700 border border-rose-100">Rejected</span>
                                @break
                            @case('pending')
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-100">Pending</span>
                                @break
                            @default
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-slate-50 text-slate-700 border border-slate-100">Draft</span>
                        @endswitch
                    </td>
                    <td class="px-6 py-4 text-right">
                        @if($status === 'pending')
                            <div class="inline-flex gap-2">
                                <form method="POST" action="{{ route('kepala_poliklinik.unit_criteria_weights.approve', $it) }}">
                                    @csrf
                                    <x-ui.button type="submit" variant="success" class="h-9 px-3 text-xs">Approve</x-ui.button>
                                </form>
                                <form method="POST" action="{{ route('kepala_poliklinik.unit_criteria_weights.reject', $it) }}" onsubmit="return confirm('Tolak bobot ini?');">
                                    @csrf
                                    <input type="hidden" name="reason" value="Tidak sesuai kebijakan" />
                                    <x-ui.button type="submit" variant="danger" class="h-9 px-3 text-xs">Reject</x-ui.button>
                                </form>
                            </div>
                        @else
                            <span class="text-slate-400 text-xs">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-6 py-8 text-center text-slate-500">Tidak ada data.</td></tr>
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
