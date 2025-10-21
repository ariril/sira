<x-app-layout title="Alokasi Remunerasi per Unit">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Alokasi per Unit</h1>
            <x-ui.button as="a" href="{{ route('admin_rs.unit-remuneration-allocations.create') }}" variant="success" class="h-12 px-6 text-base">
                <i class="fa-solid fa-plus mr-2"></i> Tambah Alokasi
            </x-ui.button>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        {{-- FILTERS --}}
        <form method="GET" class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <div class="grid gap-5 md:grid-cols-12">
                <div class="md:col-span-6">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Cari</label>
                    <x-ui.input name="q" placeholder="Nama unit / periode" addonLeft="fa-magnifying-glass" value="{{ $filters['q'] ?? '' }}" />
                </div>
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Periode</label>
                    <x-ui.select name="period_id" :options="$periods->pluck('name','id')" :value="$filters['period_id'] ?? null" placeholder="(Semua)" />
                </div>
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
                    <x-ui.select name="published" :options="['yes'=>'Published','no'=>'Draft']" :value="$filters['published'] ?? null" placeholder="(Semua)" />
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('admin_rs.unit-remuneration-allocations.index') }}" class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </a>
                <button type="submit" class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm">
                    <i class="fa-solid fa-filter"></i> Terapkan
                </button>
            </div>
        </form>

        {{-- TABLE --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <table class="min-w-full">
                <thead class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-6 py-4 text-left">Periode</th>
                        <th class="px-6 py-4 text-left">Unit</th>
                        <th class="px-6 py-4 text-right">Jumlah</th>
                        <th class="px-6 py-4 text-left">Status</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($items as $it)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4">{{ $it->period->name ?? '-' }}</td>
                            <td class="px-6 py-4">{{ $it->unit->name ?? '-' }}</td>
                            <td class="px-6 py-4 text-right">{{ number_format((float)($it->amount ?? 0), 2) }}</td>
                            <td class="px-6 py-4">
                                @if(!empty($it->published_at))
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">Published</span>
                                @else
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-100">Draft</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="inline-flex gap-2">
                                    <x-ui.icon-button as="a" href="{{ route('admin_rs.unit-remuneration-allocations.edit', $it) }}" icon="fa-pen-to-square" />
                                    <form method="POST" action="{{ route('admin_rs.unit-remuneration-allocations.destroy', $it) }}" onsubmit="return confirm('Hapus alokasi ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.icon-button icon="fa-trash" variant="danger" />
                                    </form>
                                    <form method="POST" action="{{ route('admin_rs.unit-remuneration-allocations.update', $it) }}">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="publish_toggle" value="{{ empty($it->published_at) ? 1 : 0 }}" />
                                        @if(empty($it->published_at))
                                            <x-ui.button type="submit" variant="success" class="h-9 px-3 text-xs">Publish</x-ui.button>
                                        @else
                                            <x-ui.button type="submit" variant="outline" class="h-9 px-3 text-xs">Jadikan Draft</x-ui.button>
                                        @endif
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-8 text-center text-slate-500">Tidak ada data.</td></tr>
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
