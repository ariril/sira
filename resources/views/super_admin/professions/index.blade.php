<x-app-layout title="Professions">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Professions</h1>
            <x-ui.button as="a"
                         href="{{ route('super_admin.professions.create') }}"
                         variant="primary"
                         class="h-12 px-6 text-base">
                <i class="fa-solid fa-plus mr-2"></i> Tambah Profession
            </x-ui.button>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">

        {{-- FILTERS --}}
        <form method="GET" class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <div class="grid gap-5 md:grid-cols-5">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Cari</label>
                    <x-ui.input name="q"
                                placeholder="Nama / kode / deskripsi"
                                addonLeft="fa-magnifying-glass"
                                value="{{ request('q') }}"/>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Status</label>
                    <x-ui.select name="status"
                                 :options="['active' => 'Aktif', 'inactive' => 'Tidak aktif']"
                                 :value="request('status')"
                                 placeholder="(Semua)"/>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Tampil</label>
                    <select name="per_page"
                            class="w-full h-12 px-4 rounded-xl border-slate-300 text-[15px] shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @foreach($perPageOptions as $opt)
                            <option value="{{ $opt }}" @selected((int)request('per_page', $perPage) === $opt)>{{ $opt }} / halaman</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('super_admin.professions.index') }}"
                   class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </a>

                <button type="submit"
                        class="inline-flex items-center gap-2 h-12 px-6 rounded-xl text-[15px] font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm">
                    <i class="fa-solid fa-filter"></i> Terapkan
                </button>
            </div>
        </form>

        {{-- TABLE --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <table class="min-w-full">
                <thead class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-6 py-4 text-left">Nama</th>
                    <th class="px-6 py-4 text-left">Kode</th>
                    <th class="px-6 py-4 text-left">Status</th>
                    <th class="px-6 py-4 text-right">Aksi</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                @forelse($professions as $p)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4 font-semibold text-slate-800">{{ $p->name }}</td>
                        <td class="px-6 py-4">{{ $p->code ?? '-' }}</td>
                        <td class="px-6 py-4">
                            @if($p->is_active)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">Aktif</span>
                            @else
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-rose-50 text-rose-700 border border-rose-100">Tidak aktif</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex justify-end gap-2">
                                <x-ui.icon-button as="a"
                                                  href="{{ route('super_admin.professions.edit', $p) }}"
                                                  icon="fa-pen-to-square"/>
                                <form action="{{ route('super_admin.professions.destroy', $p) }}"
                                      method="POST"
                                      onsubmit="return confirm('Hapus profession ini?')">
                                    @csrf @method('DELETE')
                                    <x-ui.icon-button icon="fa-trash" variant="danger"/>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-8 text-center text-slate-500">
                            Tidak ada data.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- FOOTER PAGINATION --}}
        <div class="pt-2 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="text-sm text-slate-600">
                Menampilkan
                <span class="font-medium text-slate-800">{{ $professions->firstItem() ?? 0 }}</span>
                –
                <span class="font-medium text-slate-800">{{ $professions->lastItem() ?? 0 }}</span>
                dari
                <span class="font-medium text-slate-800">{{ $professions->total() }}</span>
                data
            </div>
            <div>{{ $professions->links() }}</div>
        </div>
    </div>
</x-app-layout>
