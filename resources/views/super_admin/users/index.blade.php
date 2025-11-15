<x-app-layout title="Pengguna">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-slate-800">Pengguna</h1>
            <x-ui.button as="a" href="{{ route('super_admin.users.create') }}" variant="primary" class="h-12 px-6 text-base">
                <i class="fa-solid fa-plus mr-2"></i> Tambah Pengguna
            </x-ui.button>
        </div>
    </x-slot>

    <div class="container-px py-6 space-y-6">

        {{-- FILTERS --}}
        <form method="GET" class="bg-white rounded-2xl shadow-sm p-6 border border-slate-100">
            <div class="grid gap-5 md:grid-cols-6">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1">Cari</label>
                    <x-ui.input name="q" placeholder="Cari nama / email / NIP" addonLeft="fa-magnifying-glass"
                                value="{{ request('q') }}" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Role</label>
                    <x-ui.select name="role" :options="$roles" :value="request('role')" placeholder="Semua Role"/>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Unit</label>
                    <x-ui.select name="unit" :options="$units->pluck('name','id')" :value="request('unit')" placeholder="Semua Unit"/>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Verifikasi Email</label>
                    <x-ui.select name="verified" :options="['yes'=>'Sudah','no'=>'Belum']" :value="request('verified')" placeholder="(Semua)"/>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Tampil</label>
                    <x-ui.select
                        name="per_page"
                        :options="collect($perPageOptions)->mapWithKeys(fn($n) => [$n => $n.' / halaman'])->all()"
                        :value="(int)request('per_page', $perPage)"
                    />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('super_admin.users.index') }}"
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
        <x-ui.table min-width="920px">
            <x-slot name="head">
                <tr>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Pengguna</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Role</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Unit</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Profesi</th>
                    <th class="px-6 py-4 text-left whitespace-nowrap">Status</th>
                    <th class="px-6 py-4 text-right whitespace-nowrap">Aksi</th>
                </tr>
            </x-slot>

            @forelse($users as $u)
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-4">
                        <div class="font-semibold text-slate-800">{{ $u->name }}</div>
                        <div class="text-xs text-slate-500 break-all">{{ $u->email }}</div>
                        @if($u->employee_number)
                            <div class="text-[11px] text-slate-400">NIP: {{ $u->employee_number }}</div>
                        @endif
                    </td>
                    <td class="px-6 py-4">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100">
              {{ \Illuminate\Support\Str::headline(str_replace('_',' ',$u->role)) }}
            </span>
                    </td>
                    <td class="px-6 py-4">{{ $u->unit->name ?? '-' }}</td>
                    <td class="px-6 py-4">{{ $u->profession->name ?? '-' }}</td>
                    <td class="px-6 py-4">
                        @if($u->email_verified_at)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100">
                Terverifikasi
              </span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-100">
                Belum verifikasi
              </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex justify-end gap-2">
                            <x-ui.icon-button as="a" href="{{ route('super_admin.users.edit',$u) }}" icon="fa-pen-to-square" />
                            <form action="{{ route('super_admin.users.destroy',$u) }}" method="POST"
                                  onsubmit="return confirm('Hapus pengguna ini?')">
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
                <span class="font-medium text-slate-800">{{ $users->firstItem() ?? 0 }}</span>
                -
                <span class="font-medium text-slate-800">{{ $users->lastItem() ?? 0 }}</span>
                dari
                <span class="font-medium text-slate-800">{{ $users->total() }}</span>
                data
            </div>

            <div>
                {{ $users->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
