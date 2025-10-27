<x-app-layout title="Tugas Tambahan Unit">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Tugas Tambahan Unit</h1>
            <x-ui.button as="a" href="{{ route('kepala_unit.additional-tasks.create') }}" class="h-10 px-4 text-sm">
                <i class="fa-solid fa-plus mr-2"></i> Buat Tugas
            </x-ui.button>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <form method="GET" class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <div class="grid gap-5 md:grid-cols-12">
                <div class="md:col-span-5">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Cari</label>
                    <x-ui.input name="q" :value="$q" placeholder="Judul / Periode" addonLeft="fa-magnifying-glass" />
                </div>
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
                    <x-ui.select name="status" :value="$status" :options="['draft'=>'Draft','open'=>'Open','closed'=>'Closed','cancelled'=>'Cancelled']" placeholder="(Semua)" />
                </div>
                <div class="md:col-span-4">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Periode</label>
                    <x-ui.select name="period_id" :options="$periods->pluck('name','id')" :value="$periodId" placeholder="(Semua)" />
                </div>
            </div>
            <div class="mt-4 flex justify-end gap-3">
                <a href="{{ route('kepala_unit.additional-tasks.index') }}" class="inline-flex items-center gap-2 h-10 px-4 rounded-xl text-[15px] font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </a>
                <x-ui.button type="submit" class="h-10 px-4">Terapkan</x-ui.button>
            </div>
        </form>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <table class="min-w-full">
                <thead class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-6 py-4 text-left">Judul</th>
                        <th class="px-6 py-4 text-left">Periode</th>
                        <th class="px-6 py-4 text-left">Tanggal</th>
                        <th class="px-6 py-4 text-right">Poin/Bonus</th>
                        <th class="px-6 py-4 text-left">Status</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                @forelse($items as $it)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4">{{ $it->title }}</td>
                        <td class="px-6 py-4">{{ $it->period_name ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $it->start_date }} s/d {{ $it->due_date }}</td>
                        <td class="px-6 py-4 text-right">{{ number_format((float)($it->points ?? 0),2) }} / {{ number_format((float)($it->bonus_amount ?? 0),2) }}</td>
                        <td class="px-6 py-4">
                            @php($st = $it->status)
                            @if($st==='open')
                                <span class="px-2 py-1 rounded text-xs bg-emerald-100 text-emerald-700">Open</span>
                            @elseif($st==='closed')
                                <span class="px-2 py-1 rounded text-xs bg-slate-200 text-slate-700">Closed</span>
                            @elseif($st==='cancelled')
                                <span class="px-2 py-1 rounded text-xs bg-rose-100 text-rose-700">Cancelled</span>
                            @else
                                <span class="px-2 py-1 rounded text-xs bg-amber-100 text-amber-700">Draft</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="inline-flex gap-2">
                                <x-ui.icon-button as="a" href="{{ route('kepala_unit.additional-tasks.edit', $it->id) }}" icon="fa-pen-to-square" />
                                <form method="POST" action="{{ route('kepala_unit.additional_tasks.open', $it->id) }}">
                                    @csrf @method('PATCH')
                                    <x-ui.button class="h-9 px-3 text-xs" :disabled="$st==='open'">Open</x-ui.button>
                                </form>
                                <form method="POST" action="{{ route('kepala_unit.additional_tasks.close', $it->id) }}">
                                    @csrf @method('PATCH')
                                    <x-ui.button class="h-9 px-3 text-xs" :disabled="$st==='closed'">Close</x-ui.button>
                                </form>
                                <form method="POST" action="{{ route('kepala_unit.additional_tasks.cancel', $it->id) }}" onsubmit="return confirm('Batalkan tugas ini?')">
                                    @csrf @method('PATCH')
                                    <x-ui.button variant="outline" class="h-9 px-3 text-xs" :disabled="$st==='cancelled'">Cancel</x-ui.button>
                                </form>
                                <form method="POST" action="{{ route('kepala_unit.additional-tasks.destroy', $it->id) }}" onsubmit="return confirm('Hapus tugas ini?')">
                                    @csrf @method('DELETE')
                                    <x-ui.icon-button icon="fa-trash" />
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
