<x-app-layout title="Klaim Tugas Tambahan">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold">Klaim Tugas Tambahan</h1>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <form method="GET" class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <div class="grid gap-5 md:grid-cols-12">
                <div class="md:col-span-5">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Cari</label>
                    <x-ui.input name="q" :value="$q" placeholder="Nama Pegawai / Judul / Periode" addonLeft="fa-magnifying-glass" />
                </div>
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
                    <x-ui.select name="status" :value="$status" :options="['active'=>'Active','completed'=>'Completed','cancelled'=>'Cancelled']" placeholder="(Semua)" />
                </div>
                <div class="md:col-span-2 flex items-end">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="overdue" value="1" @checked($overdue) class="rounded border-slate-300"> Overdue
                    </label>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Tampil</label>
                    <x-ui.select name="per_page" :value="$perPage" :options="collect($perPageOptions)->mapWithKeys(fn($n)=>[$n=>$n.' / halaman'])->all()" />
                </div>
            </div>
            <div class="mt-4 flex justify-end gap-3">
                <a href="{{ route('kepala_unit.additional_task_claims.index') }}" class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 text-base">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </a>
                <x-ui.button type="submit" variant="orange" class="h-12 px-6 text-[15px]"> <i class="fa-solid fa-filter"></i> Terapkan</x-ui.button>
            </div>
        </form>

        <x-ui.table min-width="980px">
            <x-slot name="head">
                <tr>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Pegawai</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Tugas</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Periode</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Claimed</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Deadline Cancel</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Status</th>
                </tr>
            </x-slot>
            @forelse($items as $it)
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-4">{{ $it->user_name }}</td>
                    <td class="px-6 py-4">{{ $it->task_title }}</td>
                    <td class="px-6 py-4">{{ $it->period_name ?? '-' }}</td>
                    <td class="px-6 py-4">{{ $it->claimed_at }}</td>
                    <td class="px-6 py-4">{{ $it->cancel_deadline_at ?? '-' }}</td>
                    <td class="px-6 py-4">
                        @php($st = $it->status)
                        @if($st==='completed')
                            <span class="px-2 py-1 rounded text-xs bg-emerald-100 text-emerald-700">Completed</span>
                        @elseif($st==='cancelled')
                            <span class="px-2 py-1 rounded text-xs bg-rose-100 text-rose-700">Cancelled</span>
                        @else
                            <span class="px-2 py-1 rounded text-xs bg-amber-100 text-amber-700">Active</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-6 py-8 text-center text-slate-500">Belum ada data.</td></tr>
            @endforelse
        </x-ui.table>

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
