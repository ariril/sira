<x-app-layout title="Rekap Absensi">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Rekap Absensi</h1>
            <div class="flex items-center gap-2">
                <x-ui.button as="a" href="{{ route('admin_rs.attendances.import.form') }}" variant="success" class="h-12 px-6 text-base">
                    <i class="fa-solid fa-file-arrow-up mr-2"></i> Upload CSV
                </x-ui.button>
                <x-ui.button as="a" href="{{ route('admin_rs.attendances.batches') }}" variant="success" class="h-12 px-6 text-base">
                    <i class="fa-solid fa-database mr-2"></i> Batch Import
                </x-ui.button>
            </div>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        {{-- FILTERS --}}
        <form method="GET" class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <div class="grid gap-5 md:grid-cols-12">
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Cari</label>
                    <x-ui.input name="q" placeholder="Nama / NIP" value="{{ $filters['q'] }}" addonLeft="fa-magnifying-glass" />
                </div>
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Unit</label>
                    <x-ui.select name="unit_id" :options="$units" :value="$filters['unit_id']" placeholder="(Semua)" />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
                    <x-ui.select name="status" :options="$statuses" :value="$filters['status']" placeholder="(Semua)" />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Dari</label>
                    <x-ui.input type="date" name="date_from" value="{{ $filters['date_from'] }}" />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Sampai</label>
                    <x-ui.input type="date" name="date_to" value="{{ $filters['date_to'] }}" />
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('admin_rs.attendances.index') }}" class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </a>
                <button type="submit" class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm">
                    <i class="fa-solid fa-filter"></i> Terapkan
                </button>
            </div>
        </form>

        {{-- TABLE --}}
        <x-ui.table min-width="1100px">
            <x-slot name="head">
                <tr>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Nama</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">NIP</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Unit</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Tanggal</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Masuk</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Pulang</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Status</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Sumber</th>
                    <th class="px-6 py-4 text-right whitespace-nowrap">Aksi</th>
                </tr>
            </x-slot>
            @forelse($items as $it)
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-4">{{ $it->user->name ?? '-' }}</td>
                    <td class="px-6 py-4">{{ $it->user->employee_number ?? '-' }}</td>
                    <td class="px-6 py-4">{{ $it->user->unit->name ?? '-' }}</td>
                    <td class="px-6 py-4">{{ $it->attendance_date?->format('d M Y') }}</td>
                    <td class="px-6 py-4">{{ $it->check_in ? \Carbon\Carbon::parse($it->check_in)->format('H:i') : '-' }}</td>
                    <td class="px-6 py-4">{{ $it->check_out ? \Carbon\Carbon::parse($it->check_out)->format('H:i') : '-' }}</td>
                    <td class="px-6 py-4">{{ $it->attendance_status?->value }}</td>
                    <td class="px-6 py-4">{{ $it->source?->value }}</td>
                    <td class="px-6 py-4 text-right">
                        <div class="inline-flex gap-2">
                            <x-ui.icon-button as="a" href="{{ route('admin_rs.attendances.show', $it) }}" icon="fa-eye" />
                            <form method="POST" action="{{ route('admin_rs.attendances.destroy', $it) }}" onsubmit="return confirm('Hapus record ini?')">
                                @csrf
                                @method('DELETE')
                                <x-ui.icon-button icon="fa-trash" variant="danger" />
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="px-6 py-8 text-center text-slate-500">Tidak ada data.</td></tr>
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
