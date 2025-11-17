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
                <div class="flex items-end"><x-ui.button type="submit">Filter</x-ui.button></div>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-sm p-6 border border-emerald-100">
            <div class="mb-4 font-medium">Upload CSV</div>
            <form method="POST" action="{{ route('admin_rs.metrics.upload_csv') }}" enctype="multipart/form-data">
                @csrf
                <label class="block border-2 border-dashed border-emerald-200 rounded-2xl p-6 text-center cursor-pointer hover:bg-emerald-50/40">
                    <div class="flex items-center justify-center gap-3 text-emerald-700">
                        <i class="fa-solid fa-file-csv text-2xl"></i>
                        <div class="text-sm">
                            <div class="font-medium">Klik untuk pilih atau tarik & lepas</div>
                            <div class="opacity-80">.csv • Maks. 5 MB</div>
                        </div>
                    </div>
                    <input class="hidden" type="file" name="file" accept=".csv" />
                </label>
                <div class="mt-4">
                    <x-ui.button type="submit" class="h-10 bg-emerald-600 hover:bg-emerald-700">Upload</x-ui.button>
                </div>
            </form>
            <div class="text-xs text-slate-500 mt-3">Header wajib: employee_number, assessment_period_id, performance_criteria_id, value_numeric</div>
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
