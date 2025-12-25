<x-app-layout title="Perhitungan Remunerasi">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Perhitungan Remunerasi</h1>
            @if(!empty($selectedId))
            <form method="POST" action="{{ route('admin_rs.remunerations.calc.run') }}" @if(!empty($needsRecalcConfirm)) onsubmit="return confirm('Perhitungan remunerasi untuk periode ini sudah pernah dilakukan. Jalankan ulang akan MENGGANTI semua data DRAFT remunerasi. Lanjutkan?')" @endif>
                @csrf
                <input type="hidden" name="period_id" value="{{ $selectedId }}" />
                <x-ui.button type="submit" variant="success" class="h-12 px-6 text-base" :disabled="empty($canRun)">
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

        @if(session('danger'))
            <div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 text-sm flex items-start gap-2">
                <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
                <span>{{ session('danger') }}</span>
            </div>
        @endif

        @if(!empty($selectedId))
        {{-- CHECKLIST PRASYARAT --}}
        @if(!empty($prerequisites))
            <div class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-base font-semibold text-slate-800">Checklist Prasyarat</div>
                        <div class="text-sm text-slate-600 mt-1">Perhitungan hanya dapat dijalankan jika semua prasyarat terpenuhi.</div>
                    </div>
                    @if(!empty($lastCalculated?->calculated_at))
                        <div class="text-sm text-slate-600 text-right">
                            <div>Terakhir dihitung:</div>
                            <div class="font-medium text-slate-800">
                                {{ optional($lastCalculated->calculated_at)->format('d M Y H:i') }}
                                @if(!empty($lastCalculated->revisedBy?->name))
                                    • {{ $lastCalculated->revisedBy->name }}
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

                <div class="mt-4 space-y-2">
                    @foreach($prerequisites as $pr)
                        <div class="flex items-center justify-between rounded-xl ring-1 ring-slate-100 p-3">
                            <div>
                                <div class="font-medium">{{ $pr['label'] ?? '-' }}</div>
                                @if(!empty($pr['detail']))
                                    <div class="text-sm text-slate-600">{{ $pr['detail'] }}</div>
                                @endif
                            </div>
                            @if(!empty($pr['ok']))
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">OK</span>
                            @else
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-rose-50 text-rose-700 border border-rose-100">Belum</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- RINGKASAN --}}
        @php($allocated = (float)($allocSummary['total'] ?? 0))
        @php($remTotal = (float)($summary['total'] ?? 0))
        @php($diff = $allocated - $remTotal)
        <div class="grid gap-5 md:grid-cols-3">
            <x-stat-card label="Total Alokasi Published" value="{{ number_format($allocated,2) }}" icon="fa-sack-dollar" accent="from-emerald-500 to-teal-600" />
            <x-stat-card label="Total Remunerasi Terhitung" value="{{ number_format($remTotal,2) }}" icon="fa-wallet" accent="from-sky-500 to-indigo-600" />
            <x-stat-card label="Sisa Belum Tersalurkan" value="{{ number_format(max($diff,0),2) }}" icon="fa-circle-exclamation" accent="from-amber-500 to-orange-600" />
        </div>

        {{-- TABEL HASIL --}}
        <x-ui.table min-width="880px">
            <x-slot name="head">
                <tr>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Nama</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Unit</th>
                    <th class="px-6 py-4 text-right whitespace-nowrap">Jumlah</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Status Publish</th>
                </tr>
            </x-slot>

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
                <tr><td colspan="4" class="px-6 py-8 text-center text-slate-500">Belum ada remunerasi terhitung. Jalankan perhitungan setelah memilih periode dengan alokasi published.</td></tr>
            @endforelse
        </x-ui.table>
        @endif
    </div>
</x-app-layout>
