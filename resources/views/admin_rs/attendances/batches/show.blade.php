<x-app-layout title="Detail Batch Import">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Detail Batch Import</h1>
            <x-ui.button as="a" href="{{ route('admin_rs.attendances.batches') }}" class="h-12 px-6 text-base">
                <i class="fa-solid fa-database mr-2"></i> Semua Batch
            </x-ui.button>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">

        @if($batch->is_superseded)
            <div class="rounded-xl border border-amber-200 bg-amber-50 text-amber-900 px-4 py-3">
                Batch ini telah digantikan oleh upload berikutnya pada periode yang sama. Datanya disimpan sebagai riwayat dan tidak dipakai untuk perhitungan.
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <div class="grid gap-4 sm:grid-cols-2 text-sm">
                <div>
                    <div class="text-slate-500">Waktu Import</div>
                    <div class="font-medium text-slate-800">{{ $batch->imported_at?->format('d M Y H:i') }}</div>
                </div>
                <div>
                    <div class="text-slate-500">Diunggah Oleh</div>
                    <div class="font-medium text-slate-800">{{ $batch->importer->name ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-slate-500">File</div>
                    <div class="font-medium text-slate-800">
                        {{ $batch->file_name }}
                        @if($batch->is_superseded)
                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Digantikan</span>
                        @endif
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <div class="text-slate-500">Total</div>
                        <div class="font-medium text-slate-800">{{ $batch->total_rows }}</div>
                    </div>
                    <div>
                        <div class="text-slate-500">Berhasil</div>
                        <div class="font-medium text-emerald-700">{{ $batch->success_rows }}</div>
                    </div>
                    <div>
                        <div class="text-slate-500">Gagal</div>
                        <div class="font-medium text-rose-700">{{ $batch->failed_rows }}</div>
                    </div>
                </div>
            </div>
        </div>

        <x-ui.table min-width="900px">
            <x-slot name="head">
                <tr>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Pegawai</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">NIP</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Tanggal</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Masuk</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Pulang</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Status</th>
                </tr>
            </x-slot>
            @forelse($rows as $r)
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-4">{{ $r->user->name ?? '-' }}</td>
                    <td class="px-6 py-4">{{ $r->user->employee_number ?? '-' }}</td>
                    <td class="px-6 py-4">{{ $r->attendance_date?->format('d M Y') }}</td>
                    <td class="px-6 py-4">{{ $r->check_in ? \Carbon\Carbon::parse($r->check_in)->format('H:i') : '-' }}</td>
                    <td class="px-6 py-4">{{ $r->check_out ? \Carbon\Carbon::parse($r->check_out)->format('H:i') : '-' }}</td>
                    <td class="px-6 py-4">{{ $r->attendance_status?->value }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-6 py-8 text-center text-slate-500">Tidak ada data.</td></tr>
            @endforelse
        </x-ui.table>

        <div class="pt-2 flex justify-end">{{ $rows->links() }}</div>

        {{-- Preview hasil impor (baris gagal diwarnai) --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-slate-800">Preview Unggahan</h2>
                <div class="text-sm text-slate-600">Gagal: <span class="text-rose-700 font-medium">{{ $previewFailed }}</span> • Berhasil: <span class="text-emerald-700 font-medium">{{ $previewSuccess }}</span></div>
            </div>
            <x-ui.table min-width="1100px">
                <x-slot name="head">
                    <tr>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Row</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Employee Number</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Tanggal</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Scan Masuk</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Scan Keluar</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Alasan</th>
                    </tr>
                </x-slot>
                @forelse($preview as $p)
                    @php($raw=$p->raw_data ?? [])
                    <tr class="{{ $p->success ? 'hover:bg-slate-50' : 'bg-rose-50' }}">
                        <td class="px-6 py-4">{{ $p->row_no }}</td>
                        <td class="px-6 py-4">{{ $p->employee_number ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $raw['attendance_date'] ?? $raw['tanggal'] ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $raw['check_in'] ?? $raw['scan masuk'] ?? '-' }}</td>
                        <td class="px-6 py-4">{{ $raw['check_out'] ?? $raw['scan keluar'] ?? '-' }}</td>
                        <td class="px-6 py-4 text-rose-700">{{ $p->success ? '-' : ($p->error_message ?? 'Gagal') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-8 text-center text-slate-500">Tidak ada data preview.</td></tr>
                @endforelse
            </x-ui.table>
            <div class="pt-2 flex justify-end">{{ $preview->links() }}</div>
        </div>
    </div>
</x-app-layout>
