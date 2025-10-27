<x-app-layout title="Perhitungan Remunerasi">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Perhitungan Remunerasi</h1>
            @if(!empty($selectedId))
            <form method="POST" action="{{ route('admin_rs.remunerations.calc.run') }}">
                @csrf
                <input type="hidden" name="period_id" value="{{ $selectedId }}" />
                <x-ui.button type="submit" variant="success" class="h-12 px-6 text-base">
                    <i class="fa-solid fa-calculator mr-2"></i> Jalankan Perhitungan
                </x-ui.button>
            </form>
            @endif
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        {{-- FILTER PERIODE --}}
        <form method="GET" class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <div class="grid gap-5 md:grid-cols-12">
                <div class="md:col-span-4">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Periode Penilaian</label>
                    <x-ui.select name="period_id" :options="$periods->pluck('name','id')" :value="$selectedId" placeholder="Pilih periode" />
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('admin_rs.remunerations.calc.index') }}" class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </a>
                <button type="submit" class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm">
                    <i class="fa-solid fa-filter"></i> Terapkan
                </button>
            </div>
        </form>

        @if(session('status'))
            <div class="bg-emerald-50 border border-emerald-100 text-emerald-800 rounded-xl px-4 py-3">
                {{ session('status') }}
            </div>
        @endif

        @if(!empty($selectedId))
        {{-- RINGKASAN --}}
        <div class="grid gap-5 md:grid-cols-2">
            <x-stat-card title="Alokasi Terpublish" :value="number_format((float)($allocSummary['total'] ?? 0), 2)" subtitle="{{ ($allocSummary['count'] ?? 0) }} item" icon="fa-sack-dollar" color="emerald" />
            <x-stat-card title="Total Remunerasi Terhitung" :value="number_format((float)($summary['total'] ?? 0), 2)" subtitle="{{ ($summary['count'] ?? 0) }} pegawai" icon="fa-wallet" color="sky" />
        </div>

        {{-- TABEL HASIL --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <table class="min-w-full">
                <thead class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-6 py-4 text-left">Nama</th>
                        <th class="px-6 py-4 text-left">Unit</th>
                        <th class="px-6 py-4 text-right">Jumlah</th>
                        <th class="px-6 py-4 text-left">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($remunerations as $it)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4">{{ $it->user->name ?? '-' }}</td>
                            <td class="px-6 py-4">{{ $it->user->unit->name ?? '-' }}</td>
                            <td class="px-6 py-4 text-right">{{ number_format((float)($it->amount ?? 0), 2) }}</td>
                            <td class="px-6 py-4">
                                @if(!empty($it->published_at))
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">Published</span>
                                @else
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-100">Draft</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-8 text-center text-slate-500">Belum ada hasil perhitungan. Klik "Jalankan Perhitungan" untuk membuatnya.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @endif
    </div>
</x-app-layout>
