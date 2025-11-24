<x-app-layout title="Manual Metrics">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Manual Metrics</h1>
            <div class="flex gap-3">
                <x-ui.button as="a" href="{{ route('admin_rs.metrics.create') }}" variant="success" class="h-12 px-6 text-base">Tambah</x-ui.button>
            </div>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <form method="GET" class="grid md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Periode</label>
                    <x-ui.select name="period_id" :options="$periods" :value="$periodId" placeholder="(Semua)" />
                </div>
                <div class="flex items-end"><x-ui.button type="submit" variant="success">Filter</x-ui.button></div>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <div class="mb-4 font-medium">Upload Excel/CSV</div>
            <form method="POST" action="{{ route('admin_rs.metrics.upload_csv') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-2">File Excel/CSV</label>
                    <label class="flex flex-col items-center justify-center w-full h-40 border-2 border-dashed rounded-xl cursor-pointer bg-slate-50 hover:bg-slate-100 border-slate-200">
                        <div class="flex flex-col items-center justify-center pt-5 pb-6 text-slate-600">
                            <i class="fa-solid fa-file-excel text-3xl mb-2 text-emerald-500"></i>
                            <p class="text-sm"><span class="font-semibold">Klik untuk pilih</span> atau tarik & lepas</p>
                            <p class="text-xs text-slate-500">.xlsx, .xls, .csv • Maks. 5 MB</p>
                        </div>
                        <input type="file" name="file" accept=".xlsx,.xls,.csv,text/csv" class="hidden" required />
                    </label>
                </div>
                <div class="flex items-start gap-3">
                    <input type="checkbox" id="replace_existing" name="replace_existing" value="1" class="mt-1">
                    <label for="replace_existing" class="text-sm text-slate-700">Timpa nilai pada periode aktif bila sudah ada. Sistem akan memakai Periode Aktif secara otomatis.</label>
                </div>
                <div class="flex">
                    <x-ui.button type="submit" variant="success" class="h-12 px-6 text-base">
                        <i class="fa-solid fa-file-arrow-up mr-2"></i> Unggah & Import
                    </x-ui.button>
                </div>
            </form>
            <div class="text-xs text-slate-500 mt-3">
                Header (Indonesia) yang didukung: <span class="font-medium">nip</span>, <span class="font-medium">id_kriteria</span> atau <span class="font-medium">kriteria</span>, dan nilai sesuai tipe data (<span class="font-medium">nilai</span> untuk angka/percent/boolean, <span class="font-medium">nilai_tanggal</span> untuk tanggal/waktu, <span class="font-medium">nilai_teks</span> untuk teks). Periode akan otomatis diisi dengan Periode Aktif.
            </div>
        </div>

        <x-ui.table min-width="980px">
            <x-slot name="head">
                <tr>
                    <th class="px-6 py-4 text-left">Pegawai</th>
                    <th class="px-6 py-4 text-left">NIP</th>
                    <th class="px-6 py-4 text-left">Kriteria</th>
                    <th class="px-6 py-4 text-left">Periode</th>
                    <th class="px-6 py-4 text-left">Nilai</th>
                    <th class="px-6 py-4 text-left">Sumber</th>
                </tr>
            </x-slot>
            @forelse($items as $it)
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-4">{{ $it->user->name ?? '-' }}</td>
                    <td class="px-6 py-4">{{ $it->user->employee_number ?? '-' }}</td>
                    <td class="px-6 py-4">{{ $it->criteria->name ?? '-' }}</td>
                    <td class="px-6 py-4">{{ $it->period->name ?? '-' }}</td>
                    <td class="px-6 py-4">{{ $it->value_numeric ?? $it->value_text ?? '-' }}</td>
                    <td class="px-6 py-4">{{ $it->source_type }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-6 py-8 text-center text-slate-500">Tidak ada data.</td></tr>
            @endforelse
        </x-ui.table>

        <div class="pt-2 flex justify-end">{{ $items->links() }}</div>
    </div>
</x-app-layout>
