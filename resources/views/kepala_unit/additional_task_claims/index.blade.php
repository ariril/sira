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
                        'submitted'=>'Menunggu Review',
                        'approved'=>'Disetujui',
                        'rejected'=>'Ditolak',
                    ]" placeholder="(Semua)" />
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

        <x-ui.table min-width="880px">
            <x-slot name="head">
                <tr>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Pegawai</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Tugas</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Periode</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Submitted</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">
                        Status
                        <span class="inline-block ml-1 text-amber-600 cursor-help" title="Status klaim: submitted (menunggu review), approved, rejected.">!</span>
                    </th>
                    <th class="px-6 py-4 text-right whitespace-nowrap">Aksi</th>
                </tr>
            </x-slot>
            @forelse($items as $it)
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-4">{{ $it->user_name }}</td>
                    <td class="px-6 py-4">{{ $it->task_title }}</td>
                    <td class="px-6 py-4">{{ $it->period_name ?? '-' }}</td>
                    <td class="px-6 py-4">{{ $it->submitted_at }}</td>
                    <td class="px-6 py-4">
                        @php
                            $st = $it->status;
                            $map = [
                                'submitted' => ['Menunggu Review', 'bg-amber-100 text-amber-800', 'Pegawai sudah mengirim hasil, menunggu keputusan kepala unit.'],
                                'approved' => ['Disetujui', 'bg-emerald-100 text-emerald-700', 'Klaim disetujui dan poin akan dihitung.'],
                                'rejected' => ['Ditolak', 'bg-rose-100 text-rose-700', 'Klaim ditolak dan poin tidak diberikan.'],
                            ];
                            [$lbl, $cls, $hint] = $map[$st] ?? [ucfirst((string) $st), 'bg-slate-100 text-slate-700', ''];
                        @endphp
                        <span class="px-2 py-1 rounded text-xs {{ $cls }} cursor-help" title="{{ $hint }}">{{ $lbl }}</span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <x-ui.icon-button
                            icon="fa-eye"
                            type="button"
                            title="Detail"
                            onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'atc-detail-{{ (int) $it->id }}' }))"
                        />
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-6 py-8 text-center text-slate-500">Belum ada data.</td></tr>
            @endforelse
        </x-ui.table>

        {{-- Render modals outside the table wrapper (avoid overflow clipping) --}}
        @foreach($items as $it)
            <x-modal name="atc-detail-{{ (int) $it->id }}" :show="false" maxWidth="lg">
                <div class="p-6">
                    <div class="flex items-start justify-between gap-3">
                        <h2 class="text-lg font-semibold text-slate-800">Detail Klaim</h2>
                        <button type="button" class="text-slate-400 hover:text-slate-600" onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'atc-detail-{{ (int) $it->id }}' }))">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-x-6 gap-y-2 text-sm text-slate-700">
                        <div class="text-slate-500">Submitted</div>
                        <div class="text-right">{{ $it->submitted_at ?? '-' }}</div>

                        <div class="text-slate-500">Reviewed</div>
                        <div class="text-right">{{ $it->reviewed_at ?? '-' }}</div>

                        <div class="text-slate-500">Poin diberikan</div>
                        <div class="text-right">{{ $it->awarded_points !== null ? number_format((float) $it->awarded_points, 0, ',', '.') : '-' }}</div>

                        <div class="text-slate-500">Catatan pegawai</div>
                        <div class="text-right">{{ $it->result_note ?: '-' }}</div>

                        <div class="text-slate-500">Catatan reviewer</div>
                        <div class="text-right">{{ $it->review_comment ?: '-' }}</div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <x-ui.button type="button" variant="outline" onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'atc-detail-{{ (int) $it->id }}' }))">Tutup</x-ui.button>
                    </div>
                </div>
            </x-modal>
        @endforeach

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
