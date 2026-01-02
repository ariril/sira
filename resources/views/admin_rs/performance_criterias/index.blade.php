<x-app-layout title="Kriteria Kinerja (Admin RS)">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Kriteria Kinerja</h1>
            <x-ui.button as="a" href="{{ route('admin_rs.performance-criterias.create') }}" variant="success" class="h-12 px-6 text-base">
                <i class="fa-solid fa-plus mr-2"></i> Tambah Kriteria
            </x-ui.button>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        {{-- FILTERS --}}
        <form method="GET" class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <div class="grid gap-5 md:grid-cols-12">
                <div class="md:col-span-6">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Cari</label>
                    <x-ui.input name="q" placeholder="Nama / deskripsi" addonLeft="fa-magnifying-glass"
                                value="{{ $filters['q'] ?? '' }}" />
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Tipe</label>
                    <x-ui.select name="type" :options="$types" :value="$filters['type'] ?? null" placeholder="(Semua)" />
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Aktif</label>
                    <x-ui.select name="active" :options="['yes'=>'Ya','no'=>'Tidak']" :value="$filters['active'] ?? null" placeholder="(Semua)" />
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Tampil</label>
                    <x-ui.select name="per_page"
                                 :options="collect($perPageOptions)->mapWithKeys(fn($n) => [$n => $n.' / halaman'])->all()"
                                 :value="(int)request('per_page', $perPage)" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('admin_rs.performance-criterias.index') }}"
                   class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50">
                    <i class="fa-solid fa-rotate-left"></i>
                    Reset
                </a>

                <button type="submit"
                        class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm">
                    <i class="fa-solid fa-filter"></i>
                    Terapkan
                </button>
            </div>
        </form>

        {{-- TABLE --}}
        <x-ui.table min-width="960px">
            <x-slot name="head">
                <tr>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Nama</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Tipe</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Status</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Deskripsi</th>
                    <th class="px-6 py-4 text-right whitespace-nowrap">Aksi</th>
                </tr>
            </x-slot>
            @forelse($items as $it)
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-4 font-medium text-slate-800">{{ $it->name }}</td>
                    <td class="px-6 py-4">
                        @php($t = $it->type?->value)
                        @if($t==='benefit')
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">Benefit</span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200">Cost</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        @if($it->is_active)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">Aktif</span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-100">Nonaktif</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-slate-600">{{ \Illuminate\Support\Str::limit($it->description, 80) }}</td>
                    <td class="px-6 py-4 text-right">
                        <div class="inline-flex gap-2">
                            <x-ui.icon-button as="a" href="{{ route('admin_rs.performance-criterias.edit', $it) }}" icon="fa-pen-to-square" />
                            <form method="POST" action="{{ route('admin_rs.performance-criterias.destroy', $it) }}" onsubmit="return confirm('Hapus kriteria ini?')">
                                @csrf
                                @method('DELETE')
                                <x-ui.icon-button icon="fa-trash" variant="danger" />
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-6 py-8 text-center text-slate-500">Tidak ada data.</td></tr>
            @endforelse
        </x-ui.table>

        {{-- FOOTER PAGINATION --}}
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

        {{-- PROPOSALS SECTION --}}
        <div id="usulan-kriteria" class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-slate-800">Usulan Kriteria Baru</h2>
                <a href="#usulan-kriteria" class="text-sm text-slate-500 hover:underline">#</a>
            </div>

            <x-ui.table min-width="960px">
                <x-slot name="head">
                    <tr>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Nama</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Pengusul</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Bobot Saran</th>
                        <th class="px-6 py-4 text-left whitespace-nowrap">Deskripsi</th>
                        <th class="px-6 py-4 text-right whitespace-nowrap">Aksi</th>
                    </tr>
                </x-slot>
                @forelse($proposals as $p)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4 font-medium text-slate-800">{{ $p->name }}</td>
                        <td class="px-6 py-4">{{ $p->unitHead->name ?? '-' }}</td>
                        <td class="px-6 py-4">{{ isset($p->suggested_weight) ? number_format((float)$p->suggested_weight,2).'%' : '-' }}</td>
                        <td class="px-6 py-4 text-slate-600">{{ \Illuminate\Support\Str::limit($p->description, 100) }}</td>
                        <td class="px-6 py-4 text-right">
                            <div class="inline-flex gap-2">
                                <form method="POST" action="{{ route('admin_rs.criteria_proposals.approve', $p->id) }}">
                                    @csrf
                                    <x-ui.button type="submit" variant="success" class="h-9 px-3 text-xs">Terima</x-ui.button>
                                </form>
                                <form method="POST" action="{{ route('admin_rs.criteria_proposals.reject', $p->id) }}" onsubmit="return confirm('Tolak usulan ini?')">
                                    @csrf
                                    <x-ui.button type="submit" variant="outline" class="h-9 px-3 text-xs">Tolak</x-ui.button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-6 py-8 text-center text-slate-500">Tidak ada usulan.</td></tr>
                @endforelse
            </x-ui.table>
        </div>
    </div>
</x-app-layout>
