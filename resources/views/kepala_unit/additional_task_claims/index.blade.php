<x-app-layout title="Klaim Tugas Tambahan">
    <x-slot name="header">
        <h1 class="text-2xl font-semibold">Klaim Tugas Tambahan</h1>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <form method="GET" class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <div class="grid gap-5 md:grid-cols-12">
                <div class="md:col-span-4">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Cari</label>
                    <x-ui.input name="q" :value="$q" placeholder="Nama Pegawai / Judul / Periode" addonLeft="fa-magnifying-glass" />
                </div>
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
                    <x-ui.select name="status" :value="$status" :options="[
                        'active'=>'Diklaim (Berjalan)',
                        'submitted'=>'Dikirim (Menunggu Validasi)',
                        'validated'=>'Tervalidasi (Menunggu Persetujuan)',
                        'approved'=>'Disetujui',
                        'rejected'=>'Ditolak',
                        'completed'=>'Selesai',
                        'cancelled'=>'Dibatalkan',
                        'auto_unclaim'=>'Dilepas Otomatis',
                    ]" placeholder="(Semua)" />
                </div>
                <div class="md:col-span-1 flex items-end">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="overdue" value="1" @checked($overdue) class="rounded border-slate-300"> Overdue
                    </label>
                </div>
                <div class="md:col-span-1 flex items-end">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="violation" value="1" @checked($violation ?? false) class="rounded border-slate-300"> Violation only
                    </label>
                </div>
                <div class="md:col-span-1 flex items-end">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="penalty_applied" value="1" @checked($penaltyAppliedOnly ?? false) class="rounded border-slate-300"> Penalty applied only
                    </label>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Tampil</label>
                    <x-ui.select name="per_page" :value="$perPage" :options="collect($perPageOptions)->mapWithKeys(fn($n)=>[$n=>$n.' / halaman'])->all()" />
                </div>
            </div>
            <div class="mt-4 flex justify-end gap-3">
                <a href="{{ route('kepala_unit.additional_task_claims.index') }}" class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-200 transition-colors">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </a>
                <button type="submit" class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm text-base">
                    <i class="fa-solid fa-filter"></i> Terapkan
                </button>
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
                    <th class="px-6 py-4 text-left whitespace-nowrap">Violation?</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Penalty Snapshot</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Penalty Applied?</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Penalty Amount</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">
                        Status
                        <span class="inline-block ml-1 text-amber-600 cursor-help" title="Status klaim menggambarkan progres pekerjaan pegawai: diklaim → dikirim → validasi → persetujuan → selesai/ditolak/dibatalkan.">!</span>
                    </th>
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
                        @if($it->is_violation)
                            <span class="px-2 py-1 rounded text-xs bg-rose-100 text-rose-700">Ya</span>
                        @else
                            <span class="px-2 py-1 rounded text-xs bg-slate-100 text-slate-700">Tidak</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        @php
                            $pt = (string)($it->penalty_type ?? 'none');
                            $pv = (float)($it->penalty_value ?? 0);
                            $pb = (string)($it->penalty_base ?? 'task_bonus');
                            if ($pt === 'none') {
                                $snap = 'None';
                            } elseif ($pt === 'amount') {
                                $snap = 'Rp ' . number_format($pv, 0, ',', '.');
                            } else {
                                $baseLbl = $pb === 'remuneration' ? 'remunerasi' : 'bonus tugas';
                                $snap = rtrim(rtrim(number_format($pv, 2, ',', '.'), '0'), ',') . '% dari ' . $baseLbl;
                            }
                        @endphp
                        <span class="text-sm text-slate-700">{{ $snap }}</span>
                    </td>
                    <td class="px-6 py-4">
                        @if($it->penalty_applied)
                            <span class="px-2 py-1 rounded text-xs bg-emerald-100 text-emerald-700">Ya</span>
                        @else
                            <span class="px-2 py-1 rounded text-xs bg-slate-100 text-slate-700">Tidak</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        {{ $it->penalty_amount !== null ? ('Rp ' . number_format((float)$it->penalty_amount, 0, ',', '.')) : '-' }}
                    </td>
                    <td class="px-6 py-4">
                        @php
                            $st = $it->status;
                            $map = [
                                'active' => ['Diklaim', 'bg-cyan-100 text-cyan-800', 'Klaim sedang berjalan dan tugas sedang dikerjakan pegawai.'],
                                'submitted' => ['Menunggu Validasi', 'bg-amber-100 text-amber-800', 'Pegawai sudah mengirim hasil, menunggu validasi kepala unit.'],
                                'validated' => ['Menunggu Persetujuan', 'bg-sky-100 text-sky-700', 'Hasil sudah divalidasi, menunggu persetujuan akhir.'],
                                'approved' => ['Disetujui', 'bg-emerald-100 text-emerald-700', 'Klaim disetujui dan nilai/bonus akan dihitung.'],
                                'rejected' => ['Ditolak', 'bg-rose-100 text-rose-700', 'Klaim ditolak; slot klaim dapat kembali tersedia bila kuota masih ada dan belum lewat jatuh tempo.'],
                                'completed' => ['Selesai', 'bg-emerald-100 text-emerald-700', 'Tugas ditandai selesai.'],
                                'cancelled' => ['Dibatalkan', 'bg-slate-100 text-slate-700', 'Klaim dibatalkan oleh pegawai.'],
                                'auto_unclaim' => ['Dilepas Otomatis', 'bg-slate-100 text-slate-700', 'Klaim dilepas otomatis oleh sistem (mis. kadaluarsa/kebijakan).'],
                            ];
                            [$lbl, $cls, $hint] = $map[$st] ?? [ucfirst((string) $st), 'bg-slate-100 text-slate-700', ''];
                        @endphp
                        <span class="px-2 py-1 rounded text-xs {{ $cls }} cursor-help" title="{{ $hint }}">{{ $lbl }}</span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="px-6 py-8 text-center text-slate-500">Belum ada data.</td></tr>
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
            <div>{{ $items->withQueryString()->links() }}</div>
        </div>
    </div>
</x-app-layout>
