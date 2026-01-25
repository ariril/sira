<x-app-layout title="Bobot Kriteria Unit (Admin RS)">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Bobot Kriteria Unit</h1>
            <x-ui.button as="a" href="{{ route('admin_rs.unit_rater_weights.units') }}" variant="success" class="h-10 px-4 text-sm">Lihat Bobot Kriteria 360</x-ui.button>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        {{-- Filters --}}
        <form method="GET" class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <div class="grid gap-5 md:grid-cols-12">
                <div class="md:col-span-6">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Cari Unit</label>
                    <x-ui.input name="q" value="{{ $q ?? '' }}" placeholder="Nama unit" addonLeft="fa-magnifying-glass" />
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('admin_rs.unit-criteria-weights.index') }}" class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </a>
                <button type="submit" class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm">
                    <i class="fa-solid fa-filter"></i> Terapkan
                </button>
            </div>
        </form>

        {{-- Units --}}
        <x-ui.table min-width="720px">
            <x-slot name="head">
                <tr>
                    <th class="px-6 py-4 text-left">Nama Unit</th>
                    <th class="px-6 py-4 text-left">Kode</th>
                    <th class="px-6 py-4 text-left">Tipe</th>
                    <th class="px-6 py-4 text-right">Aksi</th>
                </tr>
            </x-slot>
            @forelse($units as $u)
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-4 font-medium text-slate-800">{{ $u->name }}</td>
                    <td class="px-6 py-4">{{ $u->code }}</td>
                    <td class="px-6 py-4 capitalize">{{ $u->type }}</td>
                    <td class="px-6 py-4 text-right">
                        <x-ui.button as="a" href="{{ route('admin_rs.unit-criteria-weights.show', $u->id) }}" variant="success" class="h-9 px-3 text-sm">Detail</x-ui.button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-6 py-8 text-center text-slate-500">Tidak ada data.</td></tr>
            @endforelse
        </x-ui.table>

        {{-- Pagination --}}
        <div class="pt-2 flex justify-end">{{ $units->withQueryString()->links() }}</div>
    </div>
</x-app-layout>
