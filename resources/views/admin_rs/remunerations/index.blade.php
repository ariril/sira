<x-app-layout title="Remunerasi">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Remunerasi</h1>
            <div class="flex items-center gap-2">
                @if(!empty($periodId) && !empty($draftCount))
                    <form method="POST" action="{{ route('admin_rs.remunerations.publish_all') }}" onsubmit="return confirm('Publish semua remunerasi DRAFT untuk filter saat ini?')">
                        @csrf
                        <input type="hidden" name="period_id" value="{{ $periodId }}" />
                        <input type="hidden" name="unit_id" value="{{ $filters['unit_id'] ?? '' }}" />
                        <input type="hidden" name="profession_id" value="{{ $filters['profession_id'] ?? '' }}" />
                        <input type="hidden" name="payment_status" value="{{ $filters['payment_status'] ?? '' }}" />
                        <x-ui.button type="submit" variant="success" class="h-12 px-6 text-base">Publish Semua</x-ui.button>
                    </form>
                @endif
                <x-ui.button as="a" href="{{ route('admin_rs.remunerations.calc.index') }}" variant="success" class="h-12 px-6 text-base">
                    <i class="fa-solid fa-calculator mr-2"></i> Ke Perhitungan
                </x-ui.button>
            </div>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        {{-- FILTERS --}}
        <form method="GET" class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <div class="grid gap-5 md:grid-cols-12">
                <div class="md:col-span-4">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Periode</label>
                    <x-ui.select name="period_id" :options="$periods->pluck('name','id')" :value="$periodId" placeholder="(Semua)" />
                </div>
                <div class="md:col-span-4">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Unit</label>
                    <x-ui.select name="unit_id" :options="$units->pluck('name','id')" :value="$filters['unit_id'] ?? null" placeholder="(Semua)" />
                </div>
                <div class="md:col-span-4">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Profesi</label>
                    <x-ui.select name="profession_id" :options="$professions->pluck('name','id')" :value="$filters['profession_id'] ?? null" placeholder="(Semua)" />
                </div>
                <div class="md:col-span-4">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Status Publish</label>
                    <x-ui.select name="published" :options="['yes'=>'Published','no'=>'Draft']" :value="$filters['published'] ?? null" placeholder="(Semua)" />
                </div>
                <div class="md:col-span-4">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Status Pembayaran</label>
                    <x-ui.select name="payment_status" :options="[
                        'Belum Dibayar' => 'Belum Dibayar',
                        'Dibayar' => 'Dibayar',
                        'Ditahan' => 'Ditahan',
                    ]" :value="$filters['payment_status'] ?? null" placeholder="(Semua)" />
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('admin_rs.remunerations.index') }}" class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </a>
                <button type="submit" class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm">
                    <i class="fa-solid fa-filter"></i> Terapkan
                </button>
            </div>
        </form>

        {{-- TABLE --}}
        <x-ui.table min-width="880px">
            <x-slot name="head">
                <tr>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Nama</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Periode</th>
                    <th class="px-6 py-4 text-right whitespace-nowrap">Jumlah</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Status</th>
                    <th class="px-6 py-4 text-right whitespace-nowrap">Aksi</th>
                </tr>
            </x-slot>

            @forelse($items as $it)
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-4">{{ $it->user->name ?? '-' }}</td>
                    <td class="px-6 py-4">{{ $it->assessmentPeriod->name ?? '-' }}</td>
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
                            <x-ui.icon-button as="a" href="{{ route('admin_rs.remunerations.show', $it) }}" icon="fa-eye" />
                            @if(empty($it->published_at))
                            <form method="POST" action="{{ route('admin_rs.remunerations.publish', $it) }}">
                                @csrf
                                <x-ui.button type="submit" variant="success" class="h-9 px-3 text-xs">Publish</x-ui.button>
                            </form>
                            @endif
                        </div>
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
            <div>{{ $items->withQueryString()->links() }}</div>
        </div>
    </div>
</x-app-layout>
