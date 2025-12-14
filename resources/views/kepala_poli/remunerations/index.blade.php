<x-app-layout title="Monitoring Remunerasi">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-800">Monitoring Remunerasi</h1>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        {{-- FILTERS --}}
        <form method="GET" class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <div class="grid gap-5 md:grid-cols-12">
                <div class="md:col-span-4">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Cari</label>
                    <x-ui.input name="q" placeholder="Nama / Periode / Unit" addonLeft="fa-magnifying-glass"
                                :value="$q" />
                </div>
                <div class="md:col-span-4">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Periode</label>
                    <x-ui.select name="period_id" :options="$periods->pluck('name','id')" :value="$periodId" placeholder="(Semua)" />
                </div>
                <div class="md:col-span-4">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Unit</label>
                    <x-ui.select name="unit_id" :options="$units->pluck('name','id')" :value="$unitId" placeholder="(Semua)" />
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('kepala_poliklinik.remunerations.index') }}" class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </a>
                <button type="submit" class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm">
                    <i class="fa-solid fa-filter"></i> Terapkan
                </button>
            </div>
        </form>

        {{-- TABLE --}}
        <x-ui.table min-width="1100px">
            <x-slot name="head">
                <tr>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Nama</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Unit</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Periode</th>
                    <th class="px-6 py-4 text-right whitespace-nowrap">Jumlah</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Publish</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Status Bayar</th>
                    <th class="px-6 py-4 text-right whitespace-nowrap">Aksi</th>
                </tr>
            </x-slot>
            @forelse($items as $it)
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-4">{{ $it->user_name ?? '-' }}</td>
                    <td class="px-6 py-4">{{ $it->unit_name ?? '-' }}</td>
                    <td class="px-6 py-4">{{ $it->period_name ?? '-' }}</td>
                    <td class="px-6 py-4 text-right">{{ number_format((float)($it->amount ?? 0), 2) }}</td>
                    <td class="px-6 py-4">
                        @if(!empty($it->published_at))
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">Published</span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-100">Draft</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">{{ $it->payment_status ?? '-' }}</td>
                    <td class="px-6 py-4 text-right">
                        <x-ui.icon-button as="a" href="{{ route('kepala_poliklinik.remunerations.show', $it->id) }}" icon="fa-eye" />
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-6 py-8 text-center text-slate-500">Tidak ada data.</td></tr>
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
            <div>{{ $items->withQueryString()->links() }}</div>
        </div>
    </div>
</x-app-layout>
