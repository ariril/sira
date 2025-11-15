<x-app-layout title="Batch Import Absensi">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Batch Import Absensi</h1>
            <x-ui.button as="a" href="{{ route('admin_rs.attendances.import.form') }}" variant="success" class="h-12 px-6 text-base">
                <i class="fa-solid fa-file-arrow-up mr-2"></i> Upload Baru
            </x-ui.button>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <x-ui.table min-width="980px">
            <x-slot name="head">
                <tr>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Waktu</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">File</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Diunggah Oleh</th>
                    <th class="px-6 py-4 text-center whitespace-nowrap">Total</th>
                    <th class="px-6 py-4 text-center whitespace-nowrap">Berhasil</th>
                    <th class="px-6 py-4 text-center whitespace-nowrap">Gagal</th>
                    <th class="px-6 py-4 text-right whitespace-nowrap">Aksi</th>
                </tr>
            </x-slot>
            @forelse($items as $it)
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-4">{{ $it->imported_at?->format('d M Y H:i') }}</td>
                    <td class="px-6 py-4">{{ $it->file_name }}</td>
                    <td class="px-6 py-4">{{ $it->importer->name ?? '-' }}</td>
                    <td class="px-6 py-4 text-center">{{ $it->total_rows }}</td>
                    <td class="px-6 py-4 text-center text-emerald-700">{{ $it->success_rows }}</td>
                    <td class="px-6 py-4 text-center text-rose-700">{{ $it->failed_rows }}</td>
                    <td class="px-6 py-4 text-right">
                        <x-ui.icon-button as="a" href="{{ route('admin_rs.attendances.batches.show', $it) }}" icon="fa-eye" />
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-6 py-8 text-center text-slate-500">Belum ada batch.</td></tr>
            @endforelse
        </x-ui.table>

        <div class="pt-2 flex justify-end">{{ $items->links() }}</div>
    </div>
</x-app-layout>
