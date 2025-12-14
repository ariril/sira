<x-app-layout title="Unit">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Unit</h1>
            <x-ui.button as="a" href="{{ route('super_admin.units.create') }}" variant="primary" class="h-12 px-6 text-base">
                <i class="fa-solid fa-plus mr-2"></i> Tambah Unit
            </x-ui.button>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">
        {{-- FILTERS --}}
        <form method="GET" class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <div class="flex flex-wrap items-end gap-4">
                <div class="flex-1 min-w-[260px]">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Cari</label>
                    <x-ui.input name="q" placeholder="Cari nama / kode / email / telp" addonLeft="fa-magnifying-glass" value="{{ request('q') }}" />
                </div>
                <div class="min-w-[200px]">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Tipe</label>
                    <x-ui.select name="type" :options="$types" :value="request('type')" placeholder="Semua Tipe"/>
                </div>
                <div class="min-w-[200px]">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
                    <x-ui.select name="active" :options="['yes'=>'Aktif','no'=>'Nonaktif']" :value="request('active')" placeholder="(Semua)"/>
                </div>
                <div class="min-w-[180px]">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Tampil</label>
                    <x-ui.select name="per_page" :options="collect($perPageOptions)->mapWithKeys(fn($n)=>[$n=>$n.' / halaman'])->all()" :value="(int)request('per_page', $perPage)" />
                </div>
                <div class="ml-auto flex items-center gap-3">
                    <a href="{{ route('super_admin.units.index') }}" class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50">
                        <i class="fa-solid fa-rotate-left"></i> Reset
                    </a>
                    <button type="submit" class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm">
                        <i class="fa-solid fa-filter"></i> Terapkan
                    </button>
                </div>
            </div>
        </form>

        {{-- TABLE --}}
        <x-ui.table min-width="920px">
            <x-slot name="head">
                <tr>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Unit</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Kode</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Tipe</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Kontak</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Status</th>
                    <th class="px-6 py-4 text-right whitespace-nowrap">Aksi</th>
                </tr>
            </x-slot>

            @forelse($units as $u)
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-4">
                        <div class="font-semibold text-slate-800">{{ $u->name }}</div>
                        <div class="text-xs text-slate-400">{{ $u->slug }}</div>
                    </td>
                    <td class="px-6 py-4">{{ $u->code ?? '-' }}</td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100">
                          {{ $u->type?->label() ?? '-' }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <div>{{ $u->phone ?: '-' }}</div>
                        <div class="text-xs text-slate-500 break-all">{{ $u->email ?: '' }}</div>
                    </td>
                    <td class="px-6 py-4">
                        @if($u->is_active)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">Aktif</span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-rose-50 text-rose-700 border border-rose-100">Nonaktif</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex justify-end gap-2">
                            <x-ui.icon-button as="a" href="{{ route('super_admin.units.edit',$u) }}" icon="fa-pen-to-square" />
                            <form action="{{ route('super_admin.units.destroy',$u) }}" method="POST"
                                  onsubmit="return confirm('Hapus unit ini?')">
                                @csrf @method('DELETE')
                                <x-ui.icon-button icon="fa-trash" variant="danger"/>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-6 py-8 text-center text-slate-500">Tidak ada data.</td></tr>
            @endforelse
        </x-ui.table>


        {{-- FOOTER PAGINATION --}}
        <div class="pt-2 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="text-sm text-slate-600">
                Menampilkan
                <span class="font-medium text-slate-800">{{ $units->firstItem() ?? 0 }}</span>
                -
                <span class="font-medium text-slate-800">{{ $units->lastItem() ?? 0 }}</span>
                dari
                <span class="font-medium text-slate-800">{{ $units->total() }}</span>
                data
            </div>
            <div>{{ $units->withQueryString()->links() }}</div>
        </div>
    </div>
</x-app-layout>
