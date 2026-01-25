<x-app-layout title="Monitoring Bobot Penilai 360 — Daftar Unit">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Daftar Unit</h1>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        <form method="GET" class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <div class="grid gap-5 md:grid-cols-12">
                <div class="md:col-span-6">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Cari Unit</label>
                    <x-ui.input name="q" value="{{ $q ?? '' }}" placeholder="Nama unit" addonLeft="fa-magnifying-glass" />
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <x-ui.button as="a" href="{{ route('kepala_poliklinik.unit_rater_weights.units') }}" variant="violet" class="h-12 px-6 text-base">
                    <i class="fa-solid fa-rotate-left mr-2"></i> Reset
                </x-ui.button>
                <x-ui.button type="submit" variant="violet" class="h-12 px-6 text-base">
                    <i class="fa-solid fa-filter mr-2"></i> Terapkan
                </x-ui.button>
            </div>
        </form>

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
                        <x-ui.button as="a" href="{{ route('kepala_poliklinik.unit_rater_weights.unit', $u->id) }}" variant="violet" class="h-9 px-3 text-sm">Detail</x-ui.button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-6 py-8 text-center text-slate-500">Tidak ada data.</td></tr>
            @endforelse
        </x-ui.table>

        <div class="pt-2 flex justify-end">{{ $units->withQueryString()->links() }}</div>
    </div>
</x-app-layout>
