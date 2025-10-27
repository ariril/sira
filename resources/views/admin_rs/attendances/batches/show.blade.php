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
        @if(session('status'))
            <div class="bg-emerald-50 border border-emerald-100 text-emerald-800 rounded-xl px-4 py-3">
                {{ session('status') }}
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
                    <div class="font-medium text-slate-800">{{ $batch->file_name }}</div>
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

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <table class="min-w-full">
                <thead class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-6 py-4 text-left">Pegawai</th>
                        <th class="px-6 py-4 text-left">NIP</th>
                        <th class="px-6 py-4 text-left">Tanggal</th>
                        <th class="px-6 py-4 text-left">Masuk</th>
                        <th class="px-6 py-4 text-left">Pulang</th>
                        <th class="px-6 py-4 text-left">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($rows as $r)
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4">{{ $r->user->name ?? '-' }}</td>
                            <td class="px-6 py-4">{{ $r->user->employee_number ?? '-' }}</td>
                            <td class="px-6 py-4">{{ $r->attendance_date?->format('d M Y') }}</td>
                            <td class="px-6 py-4">{{ $r->check_in ? \Carbon\Carbon::parse($r->check_in)->format('H:i') : '-' }}</td>
                            <td class="px-6 py-4">{{ $r->check_out ? \Carbon\Carbon::parse($r->check_out)->format('H:i') : '-' }}</td>
                            <td class="px-6 py-4">{{ (string)$r->attendance_status }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-8 text-center text-slate-500">Tidak ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pt-2 flex justify-end">{{ $rows->links() }}</div>
    </div>
</x-app-layout>
