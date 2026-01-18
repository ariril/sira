<x-app-layout title="Monitor Kinerja">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-800">Monitor Kinerja</h1>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <form method="GET">
                <div class="grid gap-4 md:grid-cols-12 items-end">
                    <div class="md:col-span-4">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Periode</label>
                        <x-ui.select name="period_id" :options="$periods->pluck('name','id')" :value="request('period_id', $period->id)" />
                    </div>

                    <div class="md:col-span-3">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Profesi</label>
                        <x-ui.select name="profession_id" :options="$professions->pluck('name','id')" :value="request('profession_id')" placeholder="Semua" />
                    </div>

                    <div class="md:col-span-3">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Status Kinerja</label>
                        <x-ui.select name="status" :options="$statusOptions" :value="request('status')" placeholder="Semua" />
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-600 mb-1">Cari</label>
                        <x-ui.input name="search" :value="request('search')" placeholder="Nama / NIP" />
                    </div>
                </div>

                <div class="mt-4 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div class="text-sm text-slate-600">
                        <span class="font-medium">Mode:</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $mode === 'snapshot' ? 'bg-indigo-50 text-indigo-700' : 'bg-emerald-50 text-emerald-700' }}">
                            {{ $mode === 'snapshot' ? 'Snapshot' : 'Live' }}
                        </span>
                        @if($mode === 'snapshot')
                            <span class="ml-2 text-xs text-slate-500">(periode sudah frozen: locked/approval/revision/closed)</span>
                        @else
                            <span class="ml-2 text-xs text-slate-500">(periode masih berjalan)</span>
                        @endif
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('kepala_unit.monitor_kinerja.index', ['period_id' => $period->id]) }}"
                            class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50">
                            <i class="fa-solid fa-rotate-left"></i> Reset
                        </a>
                        <button type="submit"
                            class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm">
                            <i class="fa-solid fa-filter"></i> Terapkan
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <x-ui.table minWidth="980px">
            <x-slot name="head">
                <tr>
                    <th class="px-4 py-3 text-left">Pegawai</th>
                    <th class="px-4 py-3 text-left">Profesi</th>
                    <th class="px-4 py-3 text-left">Unit</th>
                    <th class="px-4 py-3 text-left">Periode</th>
                    <th class="px-4 py-3 text-left">Status Kinerja</th>
                    <th class="px-4 py-3 text-right">Skor Kinerja</th>
                    <th class="px-4 py-3 text-left">Terakhir Diperbarui</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </x-slot>

            @forelse($members as $m)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <div class="font-medium text-slate-800">{{ $m->name ?? '-' }}</div>
                        <div class="text-xs text-slate-500">NIP: {{ $m->employee_number ?? '-' }}</div>
                    </td>
                    <td class="px-4 py-3">{{ $m->profession_name ?? '-' }}</td>
                    <td class="px-4 py-3">{{ $m->unit_name ?? '-' }}</td>
                    <td class="px-4 py-3">{{ $period->name }}</td>
                    <td class="px-4 py-3">
                        @php($vs = (string)($m->validation_status ?? ''))
                        @php($cls = str_contains(strtolower($vs),'valid') ? 'bg-emerald-50 text-emerald-700' : (str_contains(strtolower($vs),'tolak') ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-700'))
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $vs ? $cls : 'bg-slate-100 text-slate-700' }}">
                            {{ $vs ?: (($m->total_wsm_score === null) ? 'Belum ada data' : 'Terisi') }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right font-semibold text-slate-800">
                        @if($m->total_wsm_score === null)
                            <span class="text-slate-400">-</span>
                        @else
                            {{ number_format((float)$m->total_wsm_score, 2) }}
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">
                        @if(!empty($m->performance_updated_at))
                            {{ \Illuminate\Support\Carbon::parse($m->performance_updated_at)->format('Y-m-d H:i') }}
                        @else
                            <span class="text-slate-400">-</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        <x-ui.button as="a" variant="outline"
                            href="{{ route('kepala_unit.monitor_kinerja.show', ['period' => $period->id, 'user' => $m->id, 'mode' => $mode]) }}">
                            Detail
                        </x-ui.button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-4 py-10 text-center text-slate-500">
                        Tidak ada pegawai ditemukan.
                    </td>
                </tr>
            @endforelse
        </x-ui.table>

        <div>
            {{ $members->links() }}
        </div>

        <div class="text-xs text-slate-500">
            Catatan: Modul ini hanya menampilkan kinerja (skor/kriteria) dan tidak memuat informasi remunerasi.
        </div>
    </div>
</x-app-layout>
